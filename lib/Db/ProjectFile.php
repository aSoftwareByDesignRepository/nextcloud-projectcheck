<?php

declare(strict_types=1);

/**
 * Project file entity
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

class ProjectFile extends Entity
{
	protected $tableName = 'pc_project_files';
	protected $projectId;
	protected $storagePath;
	protected $displayName;
	protected $mimeType;
	protected $size;
	protected $uploadedBy;
	protected $createdAt;

	public function __construct()
	{
		$this->addType('projectId', 'integer');
		$this->addType('storagePath', 'string');
		$this->addType('displayName', 'string');
		$this->addType('mimeType', 'string');
		$this->addType('size', 'integer');
		$this->addType('uploadedBy', 'string');
		$this->addType('createdAt', 'datetime');
	}
}

