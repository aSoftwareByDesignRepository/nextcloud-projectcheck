<?php

declare(strict_types=1);

/**
 * TimeEntry entity for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * TimeEntry entity
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method \DateTime getDate()
 * @method void setDate(\DateTime $date)
 * @method float getHours()
 * @method void setHours(float $hours)
 * @method string|null getDescription()
 * @method void setDescription(string|null $description)
 * @method float getHourlyRate()
 * @method void setHourlyRate(float $hourlyRate)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class TimeEntry extends Entity
{
	/** @var int */
	protected $projectId;

	/** @var string */
	protected $userId;

	/** @var \DateTime */
	protected $date;

	/** @var float */
	protected $hours;

	/** @var string|null */
	protected $description;

	/** @var float */
	protected $hourlyRate;

	/** @var \DateTime */
	protected $createdAt;

	/** @var \DateTime */
	protected $updatedAt;

	/**
	 * TimeEntry constructor
	 */
	public function __construct()
	{
		$this->addType('projectId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('date', 'date');
		$this->addType('hours', 'float');
		$this->addType('description', 'string');
		$this->addType('hourlyRate', 'float');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * Calculate the cost for this time entry
	 *
	 * @return float
	 */
	public function getCost()
	{
		return $this->getHours() * $this->getHourlyRate();
	}

	/**
	 * Get formatted hours
	 *
	 * @return string
	 */
	public function getFormattedHours()
	{
		return number_format($this->getHours(), 2);
	}

	/**
	 * Get formatted cost
	 *
	 * @return string
	 */
	public function getFormattedCost()
	{
		return '€' . number_format($this->getCost(), 2);
	}

	/**
	 * Get formatted hourly rate
	 *
	 * @return string
	 */
	public function getFormattedHourlyRate()
	{
		return '€' . number_format($this->getHourlyRate(), 2);
	}

	/**
	 * Get formatted date
	 *
	 * @return string
	 */
	public function getFormattedDate()
	{
		return $this->getDate()->format('Y-m-d');
	}

	/**
	 * Get time entry summary for display
	 *
	 * @return array
	 */
	public function getSummary()
	{
		return [
			'id' => $this->getId(),
			'projectId' => $this->getProjectId(),
			'userId' => $this->getUserId(),
			'date' => $this->getFormattedDate(),
			'hours' => $this->getHours(),
			'formattedHours' => $this->getFormattedHours(),
			'description' => $this->getDescription(),
			'hourlyRate' => $this->getHourlyRate(),
			'formattedHourlyRate' => $this->getFormattedHourlyRate(),
			'cost' => $this->getCost(),
			'formattedCost' => $this->getFormattedCost(),
			'createdAt' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
			'updatedAt' => $this->getUpdatedAt()->format('Y-m-d H:i:s')
		];
	}

	/**
	 * Validate time entry data
	 *
	 * @return array Array of validation errors
	 */
	public function validate()
	{
		$errors = [];

		// Validate project ID
		if ($this->getProjectId() <= 0) {
			$errors['projectId'] = 'Valid project ID is required';
		}

		// Validate user ID
		if (empty($this->getUserId())) {
			$errors['userId'] = 'User ID is required';
		}

		// Validate date
		if (!$this->getDate()) {
			$errors['date'] = 'Date is required';
		} else {
			$today = new \DateTime();
			$entryDate = $this->getDate();
			if ($entryDate > $today) {
				$errors['date'] = 'Date cannot be in the future';
			}
		}

		// Validate hours
		if ($this->getHours() <= 0) {
			$errors['hours'] = 'Hours must be greater than 0';
		} elseif ($this->getHours() > 24) {
			$errors['hours'] = 'Hours cannot exceed 24 per day';
		}

		// Validate description
		if (!empty($this->getDescription()) && strlen($this->getDescription()) > 1000) {
			$errors['description'] = 'Description must be 1000 characters or less';
		}

		// Validate hourly rate
		if ($this->getHourlyRate() <= 0) {
			$errors['hourlyRate'] = 'Hourly rate must be greater than 0';
		}

		return $errors;
	}

	/**
	 * Check if time entry data is valid
	 *
	 * @return bool
	 */
	public function isValid()
	{
		return empty($this->validate());
	}
}
