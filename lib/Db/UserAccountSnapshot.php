<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Snapshot of a Nextcloud user label when their account is removed (HR / audit).
 *
 * @method int|null getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getDisplayName()
 * @method void setDisplayName(string $displayName)
 * @method \DateTime getAccountDeletedAt()
 * @method void setAccountDeletedAt(\DateTime $accountDeletedAt)
 */
class UserAccountSnapshot extends Entity
{
	/** @var int|null */
	public $id;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $displayName;

	/** @var \DateTime */
	protected $accountDeletedAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('displayName', 'string');
		$this->addType('accountDeletedAt', 'datetime');
	}
}
