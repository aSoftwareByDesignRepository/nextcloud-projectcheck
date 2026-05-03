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
	 * Calculate the cost for this time entry.
	 *
	 * Uses fixed-point math so totals are not subject to IEEE-754 drift
	 * when summed in PHP (audit ref. A5). The DB-side aggregations remain
	 * authoritative; this helper only feeds UI / single-row display.
	 *
	 * @return float
	 */
	public function getCost()
	{
		return \OCA\ProjectCheck\Util\Money::asFloat(
			\OCA\ProjectCheck\Util\Money::mul(
				$this->getHours(),
				$this->getHourlyRate(),
				\OCA\ProjectCheck\Util\Money::MONEY_SCALE
			),
			\OCA\ProjectCheck\Util\Money::MONEY_SCALE
		);
	}

	/**
	 * Get a non-locale-sensitive ISO date string for serialization.
	 *
	 * For user-visible display use {@see \OCA\ProjectCheck\Service\LocaleFormatService::date()}
	 * (server-side) or `ProjectCheckFormat.dateFmt` (client-side); this helper
	 * intentionally returns an ISO-8601 date so JSON payloads stay
	 * machine-parseable across locales (audit ref. AUDIT-FINDINGS B10/H28).
	 *
	 * @return string
	 */
	public function getFormattedDate()
	{
		return $this->getDate()->format('Y-m-d');
	}

	/**
	 * Get time entry summary for display.
	 *
	 * Note: pre-formatted currency / hour fields are intentionally omitted.
	 * The locale and currency context belongs in the presentation layer
	 * (server: {@see \OCA\ProjectCheck\Service\LocaleFormatService};
	 * client: `js/common/format.js`), so we expose raw numeric values and
	 * let the caller render them. This avoids leaking the hard-coded
	 * `€` glyph into JSON payloads (audit ref. AUDIT-FINDINGS B10/H28).
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
			'description' => $this->getDescription(),
			'hourlyRate' => $this->getHourlyRate(),
			'cost' => $this->getCost(),
			'createdAt' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
			'updatedAt' => $this->getUpdatedAt()->format('Y-m-d H:i:s')
		];
	}

	/**
	 * Validate time entry data. Values are machine-readable codes; translate in the service layer.
	 *
	 * @return array field name => error code
	 */
	public function validate()
	{
		$errors = [];

		if ($this->getProjectId() <= 0) {
			$errors['projectId'] = 'invalid';
		}

		if (empty($this->getUserId())) {
			$errors['userId'] = 'required';
		}

		if (!$this->getDate()) {
			$errors['date'] = 'required';
		} else {
			$today = new \DateTime();
			$entryDate = $this->getDate();
			if ($entryDate > $today) {
				$errors['date'] = 'in_future';
			}
		}

		if ($this->getHours() <= 0) {
			$errors['hours'] = 'not_positive';
		} elseif ($this->getHours() > 24) {
			$errors['hours'] = 'exceeds_24';
		}

		if (!empty($this->getDescription()) && strlen($this->getDescription()) > 1000) {
			$errors['description'] = 'too_long';
		}

		if ($this->getHourlyRate() <= 0) {
			$errors['hourlyRate'] = 'not_positive';
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
