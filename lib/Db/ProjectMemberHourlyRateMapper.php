<?php

declare(strict_types=1);

/**
 * Mapper for per-project member hourly rate history.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class ProjectMemberHourlyRateMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'pc_pm_rates', ProjectMemberHourlyRate::class);
	}

	/**
	 * @return ProjectMemberHourlyRate[]
	 */
	public function findByProjectAndUser(int $projectId, string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('effective_from', 'DESC')
			->addOrderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function findEffectiveRate(int $projectId, string $userId, string $entryDateYmd): ?ProjectMemberHourlyRate
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->lte('effective_from', $qb->createNamedParameter($entryDateYmd)))
			->orderBy('effective_from', 'DESC')
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function existsForProjectUserAndDate(int $projectId, string $userId, string $effectiveFromYmd): bool
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('effective_from', $qb->createNamedParameter($effectiveFromYmd)))
			->setMaxResults(1);
		return $qb->executeQuery()->fetchOne() !== false;
	}

	public function insertRate(ProjectMemberHourlyRate $rate): ProjectMemberHourlyRate
	{
		$rate->setCreatedAt(new \DateTime());
		return $this->insert($rate);
	}
}
