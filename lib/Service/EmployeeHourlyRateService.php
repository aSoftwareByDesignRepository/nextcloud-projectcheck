<?php

declare(strict_types=1);

/**
 * Append-only employee hourly rate history (admin).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\EmployeeHourlyRate;
use OCA\ProjectCheck\Db\EmployeeHourlyRateMapper;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Util\Money;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class EmployeeHourlyRateService
{
	public function __construct(
		private EmployeeHourlyRateMapper $mapper,
		private AccessControlService $accessControl,
		private IUserManager $userManager,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return EmployeeHourlyRate[]
	 */
	public function listRatesForUser(string $userId): array
	{
		return $this->mapper->findByUser($userId);
	}

	/**
	 * @throws RateResolutionException
	 */
	public function resolveRateForDate(string $userId, string $entryDateYmd): float
	{
		$this->assertValidDateYmd($entryDateYmd);
		$row = $this->mapper->findEffectiveRate($userId, $entryDateYmd);
		if ($row === null) {
			throw new RateResolutionException(
				$this->l->t('No employee hourly rate is effective on this date. Add a rate under Employees with an effective-from date on or before the work date.'),
				'employee_rate_missing'
			);
		}
		$rate = (float) $row->getHourlyRate();
		if ($rate <= 0) {
			throw new RateResolutionException(
				$this->l->t('No employee hourly rate is effective on this date. Add a rate under Employees with an effective-from date on or before the work date.'),
				'employee_rate_missing'
			);
		}
		return Money::asFloat(Money::normalize($rate, Money::MONEY_SCALE));
	}

	/**
	 * @param array{hourly_rate: mixed, effective_from: mixed} $data
	 * @throws \Exception
	 */
	public function addRateRow(string $targetUserId, array $data, string $actorUserId): EmployeeHourlyRate
	{
		if (!$this->accessControl->canManageAppConfiguration($actorUserId)) {
			throw new \Exception($this->l->t('Access denied'));
		}
		$targetUserId = trim($targetUserId);
		if ($targetUserId === '' || $this->userManager->get($targetUserId) === null) {
			throw new \Exception($this->l->t('User not found'));
		}

		$rateRaw = $data['hourly_rate'] ?? null;
		if (!is_numeric($rateRaw) || (float) $rateRaw <= 0) {
			throw new \Exception($this->l->t('Hourly rate must be a positive number'));
		}
		$rate = Money::asFloat(Money::normalize($rateRaw, Money::MONEY_SCALE));

		$effectiveFrom = $this->parseEffectiveFrom($data['effective_from'] ?? null);
		if ($this->mapper->existsForUserAndDate($targetUserId, $effectiveFrom)) {
			throw new \Exception($this->l->t('A rate with this effective-from date already exists. Choose a different date.'));
		}

		$today = gmdate('Y-m-d');
		if ($effectiveFrom > $today) {
			throw new \Exception($this->l->t('Effective-from date cannot be in the future.'));
		}

		$entity = new EmployeeHourlyRate();
		$entity->setUserId($targetUserId);
		$entity->setHourlyRate($rate);
		$entity->setEffectiveFrom(new \DateTime($effectiveFrom));
		$entity->setCreatedBy($actorUserId);

		$saved = $this->mapper->insertRate($entity);
		$this->logger->info('ProjectCheck: employee hourly rate row added', [
			'app' => 'projectcheck',
			'user_id' => $targetUserId,
			'effective_from' => $effectiveFrom,
			'by' => $actorUserId,
		]);
		return $saved;
	}

	private function parseEffectiveFrom(mixed $value): string
	{
		if ($value instanceof \DateTimeInterface) {
			return $value->format('Y-m-d');
		}
		$s = trim((string) $value);
		if ($s === '') {
			throw new \Exception($this->l->t('Effective-from date is required'));
		}
		if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $s, $m)) {
			$s = $m[3] . '-' . $m[2] . '-' . $m[1];
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
			throw new \Exception($this->l->t('Invalid date format'));
		}
		$dt = \DateTime::createFromFormat('Y-m-d', $s);
		if ($dt === false) {
			throw new \Exception($this->l->t('Invalid date format'));
		}
		return $s;
	}

	private function assertValidDateYmd(string $ymd): void
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
			throw new RateResolutionException($this->l->t('Invalid date format'), 'invalid_date');
		}
	}
}
