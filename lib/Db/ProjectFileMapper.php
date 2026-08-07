<?php

declare(strict_types=1);

/**
 * Mapper for project file records
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class ProjectFileMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'pc_project_files', ProjectFile::class);
	}

	/**
	 * Find a file by id
	 *
	 * @param int $id
	 * @return ProjectFile|null
	 */
	public function find(int $id): ?ProjectFile
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		try {
			return $this->findEntity($qb);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * @param int $projectId
	 * @return ProjectFile[]
	 */
	public function findByProject(int $projectId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)))
			->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}
}

