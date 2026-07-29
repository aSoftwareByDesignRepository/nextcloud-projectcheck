<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUid()
 * @method void setUid(string $v)
 * @method int getAssignedAt()
 * @method void setAssignedAt(int $v)
 * @method string getAssignedBy()
 * @method void setAssignedBy(string $v)
 */
class MobileSeat extends Entity
{
	protected string $uid = '';
	protected int $assignedAt = 0;
	protected string $assignedBy = '';

	public function __construct()
	{
		$this->addType('uid', 'string');
		$this->addType('assignedAt', 'integer');
		$this->addType('assignedBy', 'string');
	}
}
