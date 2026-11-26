<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Exception\IdempotencyConflictException;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCP\IL10N;

/**
 * Thin mobile façade for catalog reads + creates (customers, projects).
 * Same contract as MobileBookingService: no second business logic — delegates
 * to ProjectService/CustomerService, shapes mobile JSON only.
 *
 * Creates accept a clientRequestId; replay maps via FormSubmitIdempotencyService
 * under the 'mc:'/'mp:' nonce namespaces (web 'pc_form_nonce' keys are 32-hex
 * and cannot collide).
 */
class MobileCatalogService
{
	/** logical field → web form key; both camelCase and snake_case input accepted. */
	private const PROJECT_FIELDS = [
		'name' => 'name',
		'customerId' => 'customer_id',
		'shortDescription' => 'short_description',
		'detailedDescription' => 'detailed_description',
		'hourlyRate' => 'hourly_rate',
		'totalBudget' => 'total_budget',
		'costRateMode' => 'cost_rate_mode',
		'category' => 'category',
		'priority' => 'priority',
		'status' => 'status',
		'startDate' => 'start_date',
		'endDate' => 'end_date',
		'tags' => 'tags',
		'projectType' => 'project_type',
	];

	private const CUSTOMER_FIELDS = [
		'name' => 'name',
		'email' => 'email',
		'phone' => 'phone',
		'address' => 'address',
		'contactPerson' => 'contact_person',
	];

	/** Numeric coercion accepted (int|float|numeric string) — everything else is 422. */
	private const NUMERIC_FIELDS = ['customerId', 'hourlyRate', 'totalBudget'];
	/** Must be a string in dd.mm.yyyy or yyyy-mm-dd format that checkdate accepts. */
	private const DATE_FIELDS = ['startDate', 'endDate'];

	public function __construct(
		private readonly ProjectService $projects,
		private readonly CustomerService $customers,
		private readonly FormSubmitIdempotencyService $formIdempotency,
		private readonly IL10N $l,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function listCustomers(string $uid): array
	{
		return ['customers' => $this->customers->getCustomersForSelectForUser($uid, null)];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function createCustomer(string $uid, array $body): array
	{
		$clientRequestId = $this->clientRequestId($body);
		$data = $this->mapFields($body, self::CUSTOMER_FIELDS);

		if (!isset($data['name']) || trim((string)$data['name']) === '') {
			throw new MobileApiException('validation', $this->l->t('Customer name is required.'), 422, [
				'fields' => ['name' => 'required'],
			]);
		}
		if (!$this->projects->canUserCreateCustomer($uid)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot create customers.'), 403);
		}

		try {
			$id = $this->formIdempotency->rememberCreate(
				$uid,
				$this->nonce('mc', $clientRequestId),
				function () use ($data, $uid): int {
					try {
						return (int)$this->customers->createCustomer($data, $uid)->getId();
					} catch (\Exception $e) {
						throw $this->mapCreateError($e, 'customer');
					}
				},
				$this->fingerprint($data),
			);
		} catch (IdempotencyConflictException) {
			throw new MobileApiException(
				'idempotency_mismatch',
				$this->l->t('This request id was already used with different data.'),
				409,
			);
		}

		$customer = $this->customers->getCustomer($id);
		if (!$customer instanceof Customer) {
			// Idempotent replay whose key outlived the entity (deleted inside the
			// 1h nonce window): returning the dead id would report a live row.
			throw new MobileApiException(
				'conflict',
				$this->l->t('The previously created entity no longer exists.'),
				409,
			);
		}
		return ['customer' => $this->customerRow($customer)];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function createProject(string $uid, array $body): array
	{
		$clientRequestId = $this->clientRequestId($body);
		$data = $this->mapFields($body, self::PROJECT_FIELDS);

		$fields = [];
		if (!isset($data['name']) || trim((string)$data['name']) === '') {
			$fields['name'] = 'required';
		}
		if (!isset($data['customer_id']) || (int)$data['customer_id'] < 1) {
			$fields['customerId'] = 'required';
		}
		if ($fields !== []) {
			throw new MobileApiException('validation', $this->l->t('Please correct the highlighted fields.'), 422, [
				'fields' => $fields,
			]);
		}
		if (!$this->projects->canUserCreateProject($uid)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot create projects.'), 403);
		}

		try {
			$id = $this->formIdempotency->rememberCreate(
				$uid,
				$this->nonce('mp', $clientRequestId),
				function () use ($data): int {
					try {
						return (int)$this->projects->createProject($data)->getId();
					} catch (\Exception $e) {
						throw $this->mapCreateError($e, 'project');
					}
				},
				$this->fingerprint($data),
			);
		} catch (IdempotencyConflictException) {
			throw new MobileApiException(
				'idempotency_mismatch',
				$this->l->t('This request id was already used with different data.'),
				409,
			);
		}

		$project = $this->projects->getProject($id);
		if (!$project instanceof Project) {
			// Idempotent replay whose key outlived the entity (deleted inside the
			// 1h nonce window): returning the dead id would report a live row.
			throw new MobileApiException(
				'conflict',
				$this->l->t('The previously created entity no longer exists.'),
				409,
			);
		}
		return ['project' => $this->projectRow($project)];
	}

	/**
	 * Accept both camelCase and snake_case spellings; last write wins on
	 * duplicates so a client cannot smuggle two conflicting values for one field.
	 *
	 * STRICT typing at the trust boundary: downstream services validate values
	 * but not types (strict_types makes strlen()/mapper calls TypeError → 500
	 * on arrays, ints, bools). Every field is type-checked here — wrong types
	 * are a 422 with a fields map, never a downstream crash. null and '' on
	 * optional fields mean "absent".
	 *
	 * @param array<string, mixed> $body
	 * @param array<string, string> $map
	 * @return array<string, mixed>
	 */
	private function mapFields(array $body, array $map): array
	{
		$data = [];
		$errors = [];
		foreach ($map as $camel => $snake) {
			$present = false;
			$value = null;
			if (array_key_exists($camel, $body)) {
				$present = true;
				$value = $body[$camel];
			}
			if (array_key_exists($snake, $body)) {
				$present = true;
				$value = $body[$snake];
			}
			if (!$present || $value === null) {
				continue;
			}
			if (in_array($camel, self::NUMERIC_FIELDS, true)) {
				if (is_int($value) || is_float($value)
					|| (is_string($value) && is_numeric(trim($value)) && trim($value) !== '')) {
					$data[$snake] = is_string($value) ? trim($value) : $value;
				} else {
					$errors[$camel] = 'invalid_type';
				}
				continue;
			}
			if (in_array($camel, self::DATE_FIELDS, true)) {
				if (is_string($value) && trim($value) === '') {
					continue; // empty means absent
				}
				if (!is_string($value) || !self::isAcceptedDate($value)) {
					$errors[$camel] = 'invalid_date';
					continue;
				}
				$data[$snake] = $value;
				continue;
			}
			if (!is_string($value)) {
				$errors[$camel] = 'invalid_type';
				continue;
			}
			$data[$snake] = $value;
		}
		if ($errors !== []) {
			throw new MobileApiException('validation', $this->l->t('Please correct the highlighted fields.'), 422, [
				'fields' => $errors,
			]);
		}
		return $data;
	}

	/**
	 * Same acceptance set as ProjectService::parseEuropeanDate, but strict:
	 * checkdate rejects '2026-02-30' instead of letting DateTime silently
	 * normalize it to March 2nd — a machine API must not accept + mutate input.
	 */
	private static function isAcceptedDate(string $value): bool
	{
		$value = trim($value);
		if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m) === 1) {
			return checkdate((int)$m[2], (int)$m[1], (int)$m[3]);
		}
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1) {
			return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
		}
		return false;
	}

	private function clientRequestId(array $body): ?string
	{
		$raw = $body['clientRequestId'] ?? $body['client_request_id'] ?? null;
		if ($raw === null) {
			return null;
		}
		$id = trim((string)$raw);
		if ($id === '') {
			return null;
		}
		// ≤ 60 so the 'mc:'/'mp:' nonce prefix stays inside the 64-char nonce limit.
		if (strlen($id) > 60 || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1) {
			throw new MobileApiException('validation', $this->l->t('Invalid client request id.'), 422, [
				'fields' => ['clientRequestId' => 'invalid'],
			]);
		}
		return $id;
	}

	private function nonce(string $domain, ?string $clientRequestId): ?string
	{
		return $clientRequestId === null ? null : $domain . ':' . $clientRequestId;
	}

	private function fingerprint(array $data): string
	{
		$canonical = $data;
		ksort($canonical);
		return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Domain services throw plain \Exception with fixed, user-safe English
	 * messages. Map the meaningful ones to mobile error codes; everything else
	 * is a field-level validation problem.
	 */
	private function mapCreateError(\Exception $e, string $domain): MobileApiException
	{
		$message = $e->getMessage();
		if ($message === 'Access denied') {
			return new MobileApiException('forbidden', $this->l->t('Access denied.'), 403);
		}
		if (stripos($message, 'already exists') !== false) {
			return new MobileApiException('conflict', $message, 409);
		}
		$fields = [];
		if ($domain === 'project' && stripos($message, 'Customer not found') !== false) {
			$fields = ['customerId' => 'not_found'];
		}
		return new MobileApiException(
			'validation',
			$message,
			422,
			$fields !== [] ? ['fields' => $fields] : [],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function customerRow(Customer $customer): array
	{
		return [
			'id' => (int)$customer->getId(),
			'name' => (string)$customer->getName(),
			'email' => $customer->getEmail(),
			'contactPerson' => $customer->getContactPerson(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function projectRow(Project $project): array
	{
		return [
			'id' => (int)$project->getId(),
			'name' => (string)$project->getName(),
			'customerId' => (int)$project->getCustomerId(),
			'customerName' => (string)($project->getCustomerName() ?? ''),
			'status' => strtolower((string)$project->getStatus()) === 'on hold'
				? 'on_hold'
				: strtolower((string)$project->getStatus()),
		];
	}
}
