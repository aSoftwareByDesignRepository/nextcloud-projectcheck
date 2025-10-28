<?php
/**
 * ProjectMember entity for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class ProjectMember
 *
 * @package OCA\ProjectControl\Db
 *
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getRole()
 * @method void setRole(string $role)
 * @method float getHourlyRate()
 * @method void setHourlyRate(float $hourlyRate)
 * @method \DateTime getAssignedAt()
 * @method void setAssignedAt(\DateTime $assignedAt)
 * @method string getAssignedBy()
 * @method void setAssignedBy(string $assignedBy)
 */
class ProjectMember extends Entity {
	protected $tableName = 'project_members';
	protected $projectId;
	protected $userId;
	protected $role;
	protected $hourlyRate;
	protected $assignedAt;
	protected $assignedBy;

	/**
	 * ProjectMember constructor
	 */
	public function __construct() {
		$this->addType('projectId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('role', 'string');
		$this->addType('hourlyRate', 'float');
		$this->addType('assignedAt', 'datetime');
		$this->addType('assignedBy', 'string');
	}

	/**
	 * Check if member is project manager
	 *
	 * @return bool
	 */
	public function isProjectManager(): bool {
		return $this->getRole() === 'Project Manager';
	}

	/**
	 * Check if member is developer
	 *
	 * @return bool
	 */
	public function isDeveloper(): bool {
		return $this->getRole() === 'Developer';
	}

	/**
	 * Check if member is tester
	 *
	 * @return bool
	 */
	public function isTester(): bool {
		return $this->getRole() === 'Tester';
	}

	/**
	 * Check if member is consultant
	 *
	 * @return bool
	 */
	public function isConsultant(): bool {
		return $this->getRole() === 'Consultant';
	}

	/**
	 * Get role priority for sorting
	 *
	 * @return int
	 */
	public function getRolePriority(): int {
		$priorities = [
			'Project Manager' => 1,
			'Developer' => 2,
			'Tester' => 3,
			'Consultant' => 4,
		];
		
		return $priorities[$this->getRole()] ?? 5;
	}

	/**
	 * Check if member has custom hourly rate
	 *
	 * @return bool
	 */
	public function hasCustomHourlyRate(): bool {
		return $this->getHourlyRate() !== null && $this->getHourlyRate() > 0;
	}

	/**
	 * Get effective hourly rate (custom rate or project rate)
	 *
	 * @param float $projectHourlyRate
	 * @return float
	 */
	public function getEffectiveHourlyRate(float $projectHourlyRate): float {
		return $this->hasCustomHourlyRate() ? $this->getHourlyRate() : $projectHourlyRate;
	}

	/**
	 * Get assignment duration in days
	 *
	 * @return int
	 */
	public function getAssignmentDurationDays(): int {
		$assignedAt = $this->getAssignedAt();
		$now = new \DateTime();
		
		return $assignedAt->diff($now)->days;
	}

	/**
	 * Check if member was assigned recently (within last 7 days)
	 *
	 * @return bool
	 */
	public function isRecentlyAssigned(): bool {
		return $this->getAssignmentDurationDays() <= 7;
	}

	/**
	 * Get role display name
	 *
	 * @return string
	 */
	public function getRoleDisplayName(): string {
		return $this->getRole();
	}

	/**
	 * Get role description
	 *
	 * @return string
	 */
	public function getRoleDescription(): string {
		$descriptions = [
			'Project Manager' => 'Manages project timeline, budget, and team coordination',
			'Developer' => 'Implements features and fixes bugs',
			'Tester' => 'Tests functionality and reports issues',
			'Consultant' => 'Provides expert advice and guidance',
		];
		
		return $descriptions[$this->getRole()] ?? 'Team member';
	}

	/**
	 * Check if role can edit project
	 *
	 * @return bool
	 */
	public function canEditProject(): bool {
		return $this->isProjectManager();
	}

	/**
	 * Check if role can view project
	 *
	 * @return bool
	 */
	public function canViewProject(): bool {
		return true; // All team members can view the project
	}

	/**
	 * Check if role can manage team members
	 *
	 * @return bool
	 */
	public function canManageTeam(): bool {
		return $this->isProjectManager();
	}

	/**
	 * Check if role can track time
	 *
	 * @return bool
	 */
	public function canTrackTime(): bool {
		return in_array($this->getRole(), ['Project Manager', 'Developer', 'Tester', 'Consultant']);
	}
}
