<?php

declare(strict_types=1);

/**
 * Project entity for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class Project
 *
 * @package OCA\ProjectControl\Db
 *
 * @method string getName()
 * @method void setName(string $name)
 * @method string getShortDescription()
 * @method void setShortDescription(string $shortDescription)
 * @method string getDetailedDescription()
 * @method void setDetailedDescription(string $detailedDescription)
 * @method int getCustomerId()
 * @method void setCustomerId(int $customerId)
 * @method float getHourlyRate()
 * @method void setHourlyRate(float $hourlyRate)
 * @method float getTotalBudget()
 * @method void setTotalBudget(float $totalBudget)
 * @method float getAvailableHours()
 * @method void setAvailableHours(float $availableHours)
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getPriority()
 * @method void setPriority(string $priority)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method \DateTime getStartDate()
 * @method void setStartDate(\DateTime $startDate)
 * @method \DateTime getEndDate()
 * @method void setEndDate(\DateTime $endDate)
 * @method string getTags()
 * @method void setTags(string $tags)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 * @method string getCustomerName()
 * @method void setCustomerName(string $customerName)
 * @method string getProjectType()
 * @method void setProjectType(string $projectType)
 * @method string getCostRateMode()
 * @method void setCostRateMode(string $costRateMode)
 */
class Project extends Entity
{
	protected $tableName = 'pc_projects';
	protected $name;
	protected $shortDescription;
	protected $detailedDescription;
	protected $customerId;
	protected $hourlyRate;
	protected $totalBudget;
	protected $availableHours;
	protected $category;
	protected $priority;
	protected $status;
	protected $startDate;
	protected $endDate;
	protected $tags;
	protected $createdBy;
	protected $createdAt;
	protected $updatedAt;
	protected $customerName;
	protected $projectType;
	protected $costRateMode;
	/** Materialized settlement counters — maintained by ProjectSettlementCounterService only. */
	protected $stlOpenHours;
	protected $stlInvoicedHours;
	protected $stlPaidHours;
	protected $stlExcludedHours;
	protected $stlOpenAmount;
	protected $stlInvoicedAmount;
	protected $stlPaidAmount;
	protected $stlExcludedAmount;
	protected $stlUpdatedAt;

	/**
	 * Project constructor
	 */
	public function __construct()
	{
		$this->addType('name', 'string');
		$this->addType('shortDescription', 'string');
		$this->addType('detailedDescription', 'string');
		$this->addType('customerId', 'integer');
		$this->addType('hourlyRate', 'float');
		$this->addType('totalBudget', 'float');
		$this->addType('availableHours', 'float');
		$this->addType('category', 'string');
		$this->addType('priority', 'string');
		$this->addType('status', 'string');
		$this->addType('startDate', 'datetime');
		$this->addType('endDate', 'datetime');
		$this->addType('tags', 'string');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$this->addType('projectType', 'string');
		$this->addType('costRateMode', 'string');
		$this->addType('stlOpenHours', 'float');
		$this->addType('stlInvoicedHours', 'float');
		$this->addType('stlPaidHours', 'float');
		$this->addType('stlExcludedHours', 'float');
		$this->addType('stlOpenAmount', 'float');
		$this->addType('stlInvoicedAmount', 'float');
		$this->addType('stlPaidAmount', 'float');
		$this->addType('stlExcludedAmount', 'float');
		$this->addType('stlUpdatedAt', 'datetime');
	}

	/**
	 * Settlement counters in the canonical shape consumed by
	 * {@see \OCA\ProjectCheck\Util\SettlementPosture::fromCounters()} and the
	 * settlement services. Values are read-only derivatives of time entries.
	 *
	 * @return array{open_hours: float, invoiced_hours: float, paid_hours: float, excluded_hours: float,
	 *               open_amount: float, invoiced_amount: float, paid_amount: float, excluded_amount: float}
	 */
	public function getSettlementCounters(): array
	{
		return [
			'open_hours' => (float) ($this->stlOpenHours ?? 0),
			'invoiced_hours' => (float) ($this->stlInvoicedHours ?? 0),
			'paid_hours' => (float) ($this->stlPaidHours ?? 0),
			'excluded_hours' => (float) ($this->stlExcludedHours ?? 0),
			'open_amount' => (float) ($this->stlOpenAmount ?? 0),
			'invoiced_amount' => (float) ($this->stlInvoicedAmount ?? 0),
			'paid_amount' => (float) ($this->stlPaidAmount ?? 0),
			'excluded_amount' => (float) ($this->stlExcludedAmount ?? 0),
		];
	}

	/**
	 * Derived settlement posture (never stored — spec D2).
	 */
	public function getSettlementPosture(): string
	{
		return \OCA\ProjectCheck\Util\SettlementPosture::fromCounters($this->getSettlementCounters());
	}

	public function getCostRateMode(): string
	{
		return \OCA\ProjectCheck\Util\CostRateMode::normalize($this->costRateMode ?? null);
	}

	/**
	 * Get budget consumption percentage
	 *
	 * @param float $usedHours
	 * @return float
	 */
	/**
	 * @deprecated Use {@see BudgetService::getProjectBudgetInfo()} — uses actual entry costs, not hours × project rate.
	 */
	public function getBudgetConsumption(float $usedHours = 0): float
	{
		if ($this->getTotalBudget() <= 0) {
			return 0;
		}

		$usedBudget = $usedHours * $this->getHourlyRate();
		return ($usedBudget / $this->getTotalBudget()) * 100;
	}

	/**
	 * Check if project is over budget
	 *
	 * @param float $usedHours
	 * @return bool
	 */
	public function isOverBudget(float $usedHours = 0): bool
	{
		return $this->getBudgetConsumption($usedHours) > 100;
	}

	/**
	 * Get project type with fallback to 'client' if not set
	 *
	 * @return string
	 */
	public function getProjectType(): string
	{
		return $this->projectType ?? 'client';
	}

	/**
	 * Get project type display name
	 *
	 * @return string
	 */
	public function getProjectTypeDisplayName(): string
	{
		$types = [
			'client' => 'Client Project',
			'admin' => 'Administrative',
			'sales' => 'Sales & Marketing',
			'customer' => 'Customer Support',
			'product' => 'Product Development',
			'meeting' => 'Meetings & Overhead',
			'internal' => 'Internal Project',
			'research' => 'Research & Development',
			'training' => 'Training & Education',
			'other' => 'Other'
		];

		return $types[$this->getProjectType()] ?? 'Unknown';
	}

	/**
	 * Check if project is billable (client-facing)
	 *
	 * @return bool
	 */
	public function isBillable(): bool
	{
		return $this->getProjectType() === 'client';
	}

	/**
	 * Check if project is overhead (non-billable internal work)
	 *
	 * @return bool
	 */
	public function isOverhead(): bool
	{
		$overheadTypes = ['admin', 'meeting', 'internal', 'training'];
		return in_array($this->getProjectType(), $overheadTypes);
	}

	/**
	 * Get budget warning level
	 *
	 * @param float $usedHours
	 * @return string
	 */
	/**
	 * @deprecated Use {@see BudgetService::getProjectBudgetInfo()} for warning levels.
	 */
	public function getBudgetWarningLevel(float $usedHours = 0): string
	{
		$consumption = $this->getBudgetConsumption($usedHours);

		if ($consumption >= 100) {
			return 'critical';
		} elseif ($consumption >= 90) {
			return 'warning';
		} elseif ($consumption >= 80) {
			return 'notice';
		}

		return 'none';
	}

	/**
	 * Get remaining budget
	 *
	 * @param float $usedHours
	 * @return float
	 */
	public function getRemainingBudget(float $usedHours = 0): float
	{
		$usedBudget = $usedHours * $this->getHourlyRate();
		return max(0, $this->getTotalBudget() - $usedBudget);
	}

	/**
	 * Get remaining hours
	 *
	 * @param float $usedHours
	 * @return float
	 */
	public function getRemainingHours(float $usedHours = 0): float
	{
		return max(0, $this->getAvailableHours() - $usedHours);
	}

	/**
	 * Check if project is active
	 *
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->getStatus() === 'Active';
	}

	/**
	 * Check if project is completed
	 *
	 * @return bool
	 */
	public function isCompleted(): bool
	{
		return $this->getStatus() === 'Completed';
	}

	/**
	 * Check if project is cancelled
	 *
	 * @return bool
	 */
	public function isCancelled(): bool
	{
		return $this->getStatus() === 'Cancelled';
	}

	/**
	 * Check if project is archived (reversible: can be reactivated to Active/On Hold)
	 *
	 * @return bool
	 */
	public function isArchived(): bool
	{
		return $this->getStatus() === 'Archived';
	}

	/**
	 * Whether the project is open for full metadata edits (name, budget, team, files, etc.)
	 *
	 * @return bool
	 */
	public function isEditableState(): bool
	{
		return !$this->isCompleted() && !$this->isCancelled() && !$this->isArchived();
	}

	/**
	 * Whether new time can be logged on this project
	 *
	 * @return bool
	 */
	public function allowsTimeTracking(): bool
	{
		$s = (string)$this->getStatus();
		return $s === 'Active' || $s === 'On Hold';
	}

	/**
	 * Get tags as array
	 *
	 * @return array
	 */
	public function getTagsArray(): array
	{
		if (empty($this->getTags())) {
			return [];
		}

		return array_map('trim', explode(',', $this->getTags()));
	}

	/**
	 * Set tags from array
	 *
	 * @param array $tags
	 */
	public function setTagsArray(array $tags): void
	{
		$this->setTags(implode(', ', array_filter($tags)));
	}

	/**
	 * Get project duration in days
	 *
	 * @return int|null
	 */
	public function getDurationDays(): ?int
	{
		if (!$this->getStartDate() || !$this->getEndDate()) {
			return null;
		}

		$start = $this->getStartDate();
		$end = $this->getEndDate();

		return $start->diff($end)->days;
	}

	/**
	 * Check if project is overdue
	 *
	 * @return bool
	 */
	public function isOverdue(): bool
	{
		if (!$this->getEndDate() || $this->isCompleted() || $this->isCancelled() || $this->isArchived()) {
			return false;
		}

		return $this->getEndDate() < new \DateTime();
	}
}
