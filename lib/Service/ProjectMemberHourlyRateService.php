<?php

declare(strict_types=1);

/**
 * Append-only per-project member hourly rate history.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Db\ProjectMemberHourlyRate;
use OCA\ProjectCheck\Db\ProjectMemberHourlyRateMapper;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Util\Money;
use OCA\ProjectCheck\Util\SafeDateTime;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class ProjectMemberHourlyRateService
{
	public function __construct(
		private ProjectMemberHourlyRateMapper $mapper,
		private IDBConnection $db,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return ProjectMemberHourlyRate[]
	 */
	public function listRatesForMember(int $projectId, string $userId): array
	{
		return $this->mapper->findByProjectAndUser($projectId, $userId);
	}

	/**
	 * @throws RateResolutionException
	 */
	public function resolveRateForProjectMember(int $projectId, string $userId, string $entryDateYmd): float
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDateYmd)) {
			throw new RateResolutionException($this->l->t('Invalid date format'), 'invalid_date');
		}

		if (!$this->isActiveTeamMember($projectId, $userId)) {
			throw new RateResolutionException(
				$this->l->t('You must be on the project team to log time.'),
				'not_on_team'
			);
		}

		$row = $this->mapper->findEffectiveRate($projectId, $userId, $entryDateYmd);
		if ($row !== null && (float) $row->getHourlyRate() > 0) {
			return Money::asFloat(Money::normalize($row->getHourlyRate(), Money::MONEY_SCALE));
		}

		$legacyRate = $this->getLegacyMemberRate($projectId, $userId);
		if ($legacyRate > 0) {
			return Money::asFloat(Money::normalize($legacyRate, Money::MONEY_SCALE));
		}

		throw new RateResolutionException(
			$this->l->t('No project rate is effective for this person on this date. Add a rate on the project team with an effective-from date on or before the work date.'),
			'member_rate_missing'
		);
	}

	/**
	 * @throws \Exception
	 */
	public function appendRateRow(
		int $projectId,
		string $userId,
		float $hourlyRate,
		string $effectiveFromYmd,
		string $actorUserId,
	): ProjectMemberHourlyRate {
		if (!$this->isActiveTeamMember($projectId, $userId)) {
			throw new \Exception($this->l->t('Team member not found'));
		}
		if ($hourlyRate <= 0) {
			throw new \Exception($this->l->t('Hourly rate must be a positive number'));
		}
		if ($this->mapper->existsForProjectUserAndDate($projectId, $userId, $effectiveFromYmd)) {
			throw new \Exception($this->l->t('A rate with this effective-from date already exists. Choose a different date.'));
		}
		$today = gmdate('Y-m-d');
		if ($effectiveFromYmd > $today) {
			throw new \Exception($this->l->t('Effective-from date cannot be in the future.'));
		}

		$entity = new ProjectMemberHourlyRate();
		$entity->setProjectId($projectId);
		$entity->setUserId($userId);
		$entity->setHourlyRate(Money::asFloat(Money::normalize($hourlyRate, Money::MONEY_SCALE)));
		$entity->setEffectiveFrom(new \DateTime($effectiveFromYmd));
		$entity->setCreatedBy($actorUserId);

		$saved = $this->mapper->insertRate($entity);
		$this->logger->info('ProjectCheck: project member hourly rate row added', [
			'app' => 'projectcheck',
			'project_id' => $projectId,
			'user_id' => $userId,
			'effective_from' => $effectiveFromYmd,
			'by' => $actorUserId,
		]);
		return $saved;
	}

	public function seedInitialRateIfNeeded(int $projectId, string $userId, float $hourlyRate, string $actorUserId): void
	{
		if ($hourlyRate <= 0) {
			return;
		}
		$effectiveFrom = gmdate('Y-m-d');
		if ($this->mapper->existsForProjectUserAndDate($projectId, $userId, $effectiveFrom)) {
			return;
		}
		try {
			$this->appendRateRow($projectId, $userId, $hourlyRate, $effectiveFrom, $actorUserId);
		} catch (\Exception $e) {
			// duplicate race — ignore
		}
	}

	public function isActiveTeamMember(int $projectId, string $userId): bool
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('pc_project_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE)))
			->setMaxResults(1);
		return $qb->executeQuery()->fetchOne() !== false;
	}

	private function getLegacyMemberRate(int $projectId, string $userId): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('hourly_rate')
			->from('pc_project_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->setMaxResults(1);
		$val = $qb->executeQuery()->fetchOne();
		return $val !== false ? (float) $val : 0.0;
	}
}
