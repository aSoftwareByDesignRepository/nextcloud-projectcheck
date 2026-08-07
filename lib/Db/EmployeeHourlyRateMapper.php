<?php

declare(strict_types=1);

/**
 * Mapper for employee hourly rate history.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EmployeeHourlyRateMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'pc_emp_rates', EmployeeHourlyRate::class);
	}

	/**
	 * @return EmployeeHourlyRate[]
	 */
	public function findByUser(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('effective_from', 'DESC')
			->addOrderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function findEffectiveRate(string $userId, string $entryDateYmd): ?EmployeeHourlyRate
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->lte('effective_from', $qb->createNamedParameter($entryDateYmd)))
			->orderBy('effective_from', 'DESC')
			->setMaxResults(1);
		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function existsForUserAndDate(string $userId, string $effectiveFromYmd): bool
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('effective_from', $qb->createNamedParameter($effectiveFromYmd)))
			->setMaxResults(1);
		return $qb->executeQuery()->fetchOne() !== false;
	}

	public function insertRate(EmployeeHourlyRate $rate): EmployeeHourlyRate
	{
		$rate->setCreatedAt(new \DateTime());
		return $this->insert($rate);
	}
}
