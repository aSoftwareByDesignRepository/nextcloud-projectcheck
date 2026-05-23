<?php

declare(strict_types=1);

/**
 * Employee hourly rate history row.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method float getHourlyRate()
 * @method void setHourlyRate(float $hourlyRate)
 * @method \DateTime getEffectiveFrom()
 * @method void setEffectiveFrom(\DateTime $effectiveFrom)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class EmployeeHourlyRate extends Entity
{
	protected $tableName = 'pc_employee_hourly_rates';
	protected $userId;
	protected $hourlyRate;
	protected $effectiveFrom;
	protected $createdBy;
	protected $createdAt;

	public function __construct()
	{
		$this->addType('userId', 'string');
		$this->addType('hourlyRate', 'float');
		$this->addType('effectiveFrom', 'date');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'datetime');
	}
}
