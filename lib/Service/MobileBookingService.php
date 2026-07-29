<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\MobileIdempotencyMapper;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Exception\BillingLockedException;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;

/**
 * Thin mobile booking façade — reuses TimeEntryService / ProjectService / HourlyRateService.
 * No second business logic; only mobile JSON shaping + durationMinutes ↔ hours.
 */
class MobileBookingService
{
	private const MAX_DURATION_MINUTES = 24 * 60;
	private const MAX_PROJECTS = 100;
	private const MAX_ENTRIES = 200;

	public function __construct(
		private readonly ProjectService $projects,
		private readonly TimeEntryService $timeEntries,
		private readonly HourlyRateService $rates,
		private readonly IL10N $l,
		private readonly ?MobileIdempotencyMapper $idempotency = null,
		private readonly ?ITimeFactory $timeFactory = null,
	) {
	}

	/**
	 * @return array{projects: list<array<string, mixed>>}
	 */
	public function listProjectsForBooking(string $uid, ?string $q, ?int $limit): array
	{
		$limit = max(1, min(self::MAX_PROJECTS, $limit ?? 50));
		$filters = [
			'status' => ['Active', 'On Hold'],
			'limit' => $limit,
		];
		if ($q !== null && trim($q) !== '') {
			$filters['search'] = trim($q);
		}

		$rows = [];
		foreach ($this->projects->getProjectsForUserTimeEntry($uid, $filters) as $project) {
			if (!$project instanceof Project) {
				continue;
			}
			if (!$this->projects->canUserAddTimeEntryForProject($uid, (int)$project->getId())) {
				continue;
			}
			$rows[] = $this->projectRow($project);
			if (count($rows) >= $limit) {
				break;
			}
		}

		return ['projects' => $rows];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function resolveHourlyRate(string $uid, int $projectId, ?string $date, ?string $employeeUserId): array
	{
		if (!$this->projects->canUserAddTimeEntryForProject($uid, $projectId)
			&& !$this->projects->canUserAccessProject($uid, $projectId)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot book time on this project.'), 403);
		}
		$employee = ($employeeUserId !== null && trim($employeeUserId) !== '')
			? trim($employeeUserId)
			: $uid;
		// A6 parity with web resolveHourlyRate: preview is self-only (no teammate rate leakage).
		if ($employee !== $uid) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot preview rates for other users.'), 403);
		}

		$parsed = $this->parseYmd($date ?? date('Y-m-d'));
		if ($parsed === null) {
			throw new MobileApiException('validation', $this->l->t('Date must be YYYY-MM-DD.'), 422, [
				'fields' => ['date' => 'invalid_format'],
			]);
		}

		try {
			$preview = $this->rates->resolvePreview($projectId, $employee, $parsed);
		} catch (RateResolutionException $e) {
			throw new MobileApiException('validation', $e->getMessage(), 422, [
				'fields' => ['rate' => $e->getCodeKey()],
			]);
		}

		return [
			'projectId' => $projectId,
			'date' => $parsed->format('Y-m-d'),
			'employeeUserId' => $employee,
			'hourlyRate' => $preview['hourly_rate'],
			'costRateMode' => $preview['cost_rate_mode'],
			'source' => $preview['source'],
		];
	}

	/**
	 * @return array{entries: list<array<string, mixed>>}
	 */
	public function listMyEntries(string $uid, ?string $from, ?string $to, ?string $billingStatus): array
	{
		$status = $billingStatus === null || $billingStatus === ''
			? BillingStatus::OPEN
			: BillingStatus::normalize($billingStatus);
		if ($status !== 'all' && !in_array($status, BillingStatus::ALL, true) && $status !== 'outstanding') {
			throw new MobileApiException('validation', $this->l->t('Invalid billing status filter.'), 422, [
				'fields' => ['billingStatus' => 'invalid'],
			]);
		}

		$filters = [
			'user_id' => $uid,
			'limit' => self::MAX_ENTRIES,
			'offset' => 0,
		];
		if ($from !== null && $from !== '') {
			if ($this->parseYmd($from) === null) {
				throw new MobileApiException('validation', $this->l->t('from must be YYYY-MM-DD.'), 422);
			}
			$filters['date_from'] = $from;
		}
		if ($to !== null && $to !== '') {
			if ($this->parseYmd($to) === null) {
				throw new MobileApiException('validation', $this->l->t('to must be YYYY-MM-DD.'), 422);
			}
			$filters['date_to'] = $to;
		}
		if ($status !== 'all') {
			$filters['billing_status'] = $status;
		}

		$raw = $this->timeEntries->getTimeEntriesWithProjectInfo($filters);
		$entries = [];
		foreach ($raw as $row) {
			$entries[] = $this->entryRowFromJoined($row);
		}
		return ['entries' => $entries];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function createEntry(string $uid, array $body): array
	{
		$clientRequestId = $this->normalizeClientRequestId(
			isset($body['clientRequestId']) ? (string)$body['clientRequestId'] : (isset($body['client_request_id']) ? (string)$body['client_request_id'] : null)
		);

		if ($clientRequestId !== null && $this->idempotency !== null) {
			$existingMap = $this->idempotency->findByUserAndRequestId($uid, $clientRequestId);
			if ($existingMap !== null) {
				$prior = $this->timeEntries->getTimeEntry((int)$existingMap->getTimeEntryId());
				if ($prior !== null) {
					return $this->entryRow($prior);
				}
				// Stale map (entry deleted) — free the key so a retry can succeed.
				$this->idempotency->deleteByUserAndRequestId($uid, $clientRequestId);
			}
		}

		$projectId = (int)($body['projectId'] ?? $body['project_id'] ?? 0);
		if ($projectId < 1) {
			throw new MobileApiException('validation', $this->l->t('Project is required.'), 422, [
				'fields' => ['projectId' => 'required'],
			]);
		}
		if (!$this->projects->canUserAddTimeEntryForProject($uid, $projectId)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot book time on this project.'), 403);
		}

		$date = (string)($body['date'] ?? '');
		$minutes = $this->resolveDurationMinutes($body);
		$hours = round($minutes / 60.0, 4);
		$description = isset($body['description']) ? (string)$body['description'] : '';

		$data = [
			'project_id' => $projectId,
			'date' => $date,
			'hours' => $hours,
			'description' => $description,
			// Never trust client rate — omit so server resolves.
		];

		$validation = $this->timeEntries->validateTimeEntryDataDetailed($data);
		if ($validation['errors'] !== []) {
			throw new MobileApiException('validation', $this->l->t('Please check the highlighted fields.'), 422, [
				'fields' => $validation['errorCodes'],
				'messages' => $validation['errors'],
			]);
		}

		try {
			$entry = $this->timeEntries->createTimeEntry($data, $uid);
		} catch (PermissionDeniedException) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot book time on this project.'), 403);
		} catch (ValidationException $e) {
			throw new MobileApiException('validation', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('Validation failed.'), 422, [
				'fields' => $e->getErrors(),
			]);
		}

		if ($clientRequestId !== null && $this->idempotency !== null) {
			$now = $this->timeFactory !== null ? $this->timeFactory->getTime() : time();
			$inserted = $this->idempotency->tryInsert($uid, $clientRequestId, (int)$entry->getId(), $now);
			if (!$inserted) {
				// Lost the race: another request owns this key. Remove our orphan
				// so AR / hours are never double-booked, then return the winner.
				$orphanId = (int)$entry->getId();
				try {
					$this->timeEntries->deleteTimeEntryForMaintenance($orphanId);
				} catch (\Throwable) {
					// Best-effort; unique map still points at the winner.
				}
				$winner = $this->idempotency->findByUserAndRequestId($uid, $clientRequestId);
				if ($winner !== null) {
					$prior = $this->timeEntries->getTimeEntry((int)$winner->getTimeEntryId());
					if ($prior !== null) {
						return $this->entryRow($prior);
					}
				}
				// Winner map vanished between insert conflict and read — surface conflict.
				throw new MobileApiException(
					'conflict',
					$this->l->t('This request was already processed. Refresh and try again.'),
					409,
				);
			}
		}

		return $this->entryRow($entry);
	}

	/**
	 * UUID / opaque client key for offline create retries. Null = no idempotency.
	 */
	private function normalizeClientRequestId(?string $raw): ?string
	{
		if ($raw === null) {
			return null;
		}
		$id = trim($raw);
		if ($id === '') {
			return null;
		}
		if (strlen($id) > 64 || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1) {
			throw new MobileApiException('validation', $this->l->t('Invalid client request id.'), 422, [
				'fields' => ['clientRequestId' => 'invalid'],
			]);
		}
		return $id;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function updateEntry(string $uid, int $id, array $body): array
	{
		$existing = $this->timeEntries->getTimeEntry($id);
		if ($existing === null) {
			throw new MobileApiException('not_found', $this->l->t('Time entry not found.'), 404);
		}
		if (!$existing->isOwnedBy($uid)) {
			throw new MobileApiException('forbidden', $this->l->t('You can only edit your own time entries.'), 403);
		}
		// Spec D5 / SERVER-MOBILE-API §3.6: mobile may mutate only open entries.
		$this->assertEntryOpenForMutation($existing, 'edit');

		$data = [];
		if (array_key_exists('projectId', $body) || array_key_exists('project_id', $body)) {
			$projectId = (int)($body['projectId'] ?? $body['project_id']);
			if ($projectId < 1) {
				throw new MobileApiException('validation', $this->l->t('Project is required.'), 422);
			}
			if (!$this->projects->canUserAddTimeEntryForProject($uid, $projectId)) {
				throw new MobileApiException('forbidden', $this->l->t('You cannot book time on this project.'), 403);
			}
			$data['project_id'] = $projectId;
		}
		if (array_key_exists('date', $body)) {
			$data['date'] = (string)$body['date'];
		}
		if (array_key_exists('durationMinutes', $body)
			|| array_key_exists('startTime', $body)
			|| array_key_exists('endTime', $body)
			|| array_key_exists('hours', $body)) {
			$minutes = $this->resolveDurationMinutes($body);
			$data['hours'] = round($minutes / 60.0, 4);
		}
		if (array_key_exists('description', $body)) {
			$data['description'] = (string)$body['description'];
		}
		// Strip any client hourlyRate — frozen/re-resolved server-side.
		unset($data['hourly_rate'], $data['hourlyRate']);

		if ($data === []) {
			return $this->entryRow($existing);
		}

		$merged = [
			'project_id' => $data['project_id'] ?? $existing->getProjectId(),
			'date' => $data['date'] ?? $existing->getFormattedDate(),
			'hours' => $data['hours'] ?? $existing->getHours(),
			'description' => $data['description'] ?? ($existing->getDescription() ?? ''),
		];
		$validation = $this->timeEntries->validateTimeEntryDataDetailed($merged);
		if ($validation['errors'] !== []) {
			throw new MobileApiException('validation', $this->l->t('Please check the highlighted fields.'), 422, [
				'fields' => $validation['errorCodes'],
				'messages' => $validation['errors'],
			]);
		}

		try {
			$entry = $this->timeEntries->updateTimeEntry($id, $data, $uid);
		} catch (TimeEntryNotFoundException) {
			throw new MobileApiException('not_found', $this->l->t('Time entry not found.'), 404);
		} catch (PermissionDeniedException) {
			throw new MobileApiException('forbidden', $this->l->t('Access denied.'), 403);
		} catch (BillingLockedException $e) {
			throw new MobileApiException(
				'entry_not_editable',
				$e->getMessage() !== '' ? $e->getMessage() : $this->l->t('This time entry can no longer be edited.'),
				409,
				['billingStatus' => $e->getBillingStatus()],
			);
		} catch (ValidationException $e) {
			throw new MobileApiException('validation', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('Validation failed.'), 422, [
				'fields' => $e->getErrors(),
			]);
		}

		return $this->entryRow($entry);
	}

	public function deleteEntry(string $uid, int $id): void
	{
		$existing = $this->timeEntries->getTimeEntry($id);
		if ($existing === null) {
			throw new MobileApiException('not_found', $this->l->t('Time entry not found.'), 404);
		}
		if (!$existing->isOwnedBy($uid)) {
			throw new MobileApiException('forbidden', $this->l->t('You can only delete your own time entries.'), 403);
		}
		// Spec D5 / SERVER-MOBILE-API §3.6: mobile may mutate only open entries.
		$this->assertEntryOpenForMutation($existing, 'delete');

		try {
			$this->timeEntries->deleteTimeEntry($id, $uid);
		} catch (TimeEntryNotFoundException) {
			throw new MobileApiException('not_found', $this->l->t('Time entry not found.'), 404);
		} catch (PermissionDeniedException) {
			throw new MobileApiException('forbidden', $this->l->t('Access denied.'), 403);
		} catch (BillingLockedException $e) {
			throw new MobileApiException(
				'entry_not_editable',
				$e->getMessage() !== '' ? $e->getMessage() : $this->l->t('This time entry can no longer be deleted.'),
				409,
				['billingStatus' => $e->getBillingStatus()],
			);
		}
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private function resolveDurationMinutes(array $body): int
	{
		if (array_key_exists('durationMinutes', $body) && $body['durationMinutes'] !== null && $body['durationMinutes'] !== '') {
			if (!is_numeric($body['durationMinutes'])) {
				throw new MobileApiException('validation', $this->l->t('Duration must be an integer number of minutes.'), 422, [
					'fields' => ['durationMinutes' => 'invalid'],
				]);
			}
			$raw = $body['durationMinutes'];
			$asFloat = (float)$raw;
			if (!is_int($raw) && $asFloat !== (float)(int)$asFloat) {
				throw new MobileApiException('validation', $this->l->t('Duration must be an integer number of minutes.'), 422, [
					'fields' => ['durationMinutes' => 'invalid'],
				]);
			}
			return $this->assertMinutesRange((int)$asFloat);
		}

		if (isset($body['hours']) && $body['hours'] !== null && $body['hours'] !== '') {
			if (!is_numeric($body['hours'])) {
				throw new MobileApiException('validation', $this->l->t('Hours must be a positive number.'), 422);
			}
			$hours = (float)$body['hours'];
			$minutes = (int)round($hours * 60);
			return $this->assertMinutesRange($minutes);
		}

		$start = isset($body['startTime']) ? trim((string)$body['startTime']) : '';
		$end = isset($body['endTime']) ? trim((string)$body['endTime']) : '';
		if ($start !== '' && $end !== '') {
			$startMin = $this->parseHmToMinutes($start, 'startTime');
			$endMin = $this->parseHmToMinutes($end, 'endTime');
			if ($endMin <= $startMin) {
				throw new MobileApiException('validation', $this->l->t('End time must be after start time.'), 422, [
					'fields' => ['endTime' => 'before_start'],
				]);
			}
			return $this->assertMinutesRange($endMin - $startMin);
		}

		throw new MobileApiException('validation', $this->l->t('Provide durationMinutes or startTime and endTime.'), 422, [
			'fields' => ['durationMinutes' => 'required'],
		]);
	}

	private function assertMinutesRange(int $minutes): int
	{
		if ($minutes < 1 || $minutes > self::MAX_DURATION_MINUTES) {
			throw new MobileApiException(
				'validation',
				$this->l->t('Duration must be between 1 and %s minutes.', [(string)self::MAX_DURATION_MINUTES]),
				422,
				['fields' => ['durationMinutes' => 'out_of_range']],
			);
		}
		return $minutes;
	}

	private function parseHmToMinutes(string $value, string $field): int
	{
		if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m) !== 1) {
			throw new MobileApiException('validation', $this->l->t('Time must be HH:MM.'), 422, [
				'fields' => [$field => 'invalid_format'],
			]);
		}
		return ((int)$m[1]) * 60 + (int)$m[2];
	}

	private function parseYmd(?string $value): ?\DateTimeImmutable
	{
		if ($value === null || $value === '') {
			return null;
		}
		$dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		if ($dt === false) {
			return null;
		}
		$errors = \DateTimeImmutable::getLastErrors();
		if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
			return null;
		}
		return $dt;
	}

	/**
	 * Mobile mutations are open-only (D5). Invoiced/paid are billing-locked;
	 * excluded (overhead) is also view-only on mobile even though web may reopen it.
	 *
	 * @param 'edit'|'delete' $action
	 */
	private function assertEntryOpenForMutation(TimeEntry $existing, string $action): void
	{
		$status = $existing->getBillingStatus();
		if ($status === BillingStatus::OPEN) {
			return;
		}

		$message = $action === 'delete'
			? ($existing->isBillingLocked()
				? $this->l->t('This time entry has already been invoiced or paid and can no longer be deleted.')
				: $this->l->t('Excluded time entries cannot be deleted from the mobile app.'))
			: ($existing->isBillingLocked()
				? $this->l->t('This time entry has already been invoiced or paid and can no longer be edited.')
				: $this->l->t('Excluded time entries cannot be edited from the mobile app.'));

		throw new MobileApiException(
			'entry_not_editable',
			$message,
			409,
			['billingStatus' => $status],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function projectRow(Project $project): array
	{
		return [
			'id' => (int)$project->getId(),
			'name' => (string)$project->getName(),
			'customerName' => (string)($project->getCustomerName() ?? ''),
			'status' => strtolower((string)$project->getStatus()) === 'on hold'
				? 'on_hold'
				: strtolower((string)$project->getStatus()),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function entryRow(TimeEntry $entry): array
	{
		$hours = (float)$entry->getHours();
		$minutes = (int)round($hours * 60);
		$project = $this->projects->getProject((int)$entry->getProjectId());
		return [
			'id' => (int)$entry->getId(),
			'projectId' => (int)$entry->getProjectId(),
			'projectName' => $project !== null ? (string)$project->getName() : '',
			'customerName' => $project !== null ? (string)($project->getCustomerName() ?? '') : '',
			'userId' => (string)$entry->getUserId(),
			'date' => $entry->getFormattedDate(),
			'durationMinutes' => $minutes,
			'hours' => $hours,
			'description' => (string)($entry->getDescription() ?? ''),
			'hourlyRate' => (float)$entry->getHourlyRate(),
			'billingStatus' => $entry->getBillingStatus(),
			'billingLocked' => $entry->isBillingLocked(),
			'editable' => !$entry->isBillingLocked() && $entry->getBillingStatus() === BillingStatus::OPEN,
			'createdAt' => $entry->getCreatedAt()->format('c'),
			'updatedAt' => $entry->getUpdatedAt()->format('c'),
		];
	}

	/**
	 * @param array{timeEntry?: TimeEntry, projectName?: string, customerName?: string}|array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function entryRowFromJoined(array $row): array
	{
		if (isset($row['timeEntry']) && $row['timeEntry'] instanceof TimeEntry) {
			$base = $this->entryRow($row['timeEntry']);
			if (isset($row['projectName'])) {
				$base['projectName'] = (string)$row['projectName'];
			}
			if (isset($row['customerName'])) {
				$base['customerName'] = (string)$row['customerName'];
			}
			return $base;
		}

		$hours = (float)($row['hours'] ?? 0);
		$status = BillingStatus::normalize((string)($row['billing_status'] ?? $row['billingStatus'] ?? BillingStatus::OPEN));
		$locked = in_array($status, [BillingStatus::INVOICED, BillingStatus::PAID], true);
		return [
			'id' => (int)($row['id'] ?? 0),
			'projectId' => (int)($row['project_id'] ?? $row['projectId'] ?? 0),
			'projectName' => (string)($row['project_name'] ?? $row['projectName'] ?? ''),
			'customerName' => (string)($row['customer_name'] ?? $row['customerName'] ?? ''),
			'userId' => (string)($row['user_id'] ?? $row['userId'] ?? ''),
			'date' => (string)($row['date'] ?? ''),
			'durationMinutes' => (int)round($hours * 60),
			'hours' => $hours,
			'description' => (string)($row['description'] ?? ''),
			'hourlyRate' => (float)($row['hourly_rate'] ?? $row['hourlyRate'] ?? 0),
			'billingStatus' => $status,
			'billingLocked' => $locked,
			'editable' => !$locked && $status === BillingStatus::OPEN,
			'createdAt' => isset($row['created_at']) ? (string)$row['created_at'] : null,
			'updatedAt' => isset($row['updated_at']) ? (string)$row['updated_at'] : null,
		];
	}
}
