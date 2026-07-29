<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $v)
 * @method string getClientRequestId()
 * @method void setClientRequestId(string $v)
 * @method int getTimeEntryId()
 * @method void setTimeEntryId(int $v)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $v)
 */
class MobileIdempotency extends Entity
{
	protected string $userId = '';
	protected string $clientRequestId = '';
	protected int $timeEntryId = 0;
	protected int $createdAt = 0;

	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('clientRequestId', 'string');
		$this->addType('timeEntryId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
