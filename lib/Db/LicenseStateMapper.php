<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<LicenseState>
 */
class LicenseStateMapper extends QBMapper
{
	public const TABLE = 'pc_license_state';

	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, self::TABLE, LicenseState::class);
	}

	public function findSingleton(): ?LicenseState
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->orderBy('id', 'DESC')
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function deleteAll(): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName());
		$qb->executeStatement();
	}
}
