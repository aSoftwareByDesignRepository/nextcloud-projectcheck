<?php

/**
 * Customer entity for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Customer entity
 *
 * @method int getId()
 * @method void setId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getEmail()
 * @method void setEmail(string|null $email)
 * @method string|null getPhone()
 * @method void setPhone(string|null $phone)
 * @method string|null getAddress()
 * @method void setAddress(string|null $address)
 * @method string|null getContactPerson()
 * @method void setContactPerson(string|null $contactPerson)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 * @method int|null getProjectCount()
 * @method void setProjectCount(int|null $projectCount)
 * @method bool|null getCanDelete()
 * @method void setCanDelete(bool|null $canDelete)
 */
class Customer extends Entity
{
	/** @var string */
	protected $name;

	/** @var string|null */
	protected $email;

	/** @var string|null */
	protected $phone;

	/** @var string|null */
	protected $address;

	/** @var string|null */
	protected $contactPerson;

	/** @var string */
	protected $createdBy;

	/** @var \DateTime */
	protected $createdAt;

	/** @var \DateTime */
	protected $updatedAt;

	/** @var int|null */
	protected $projectCount;

	/** @var bool|null */
	protected $canDelete;

	/**
	 * Customer constructor
	 */
	public function __construct()
	{
		$this->addType('name', 'string');
		$this->addType('email', 'string');
		$this->addType('phone', 'string');
		$this->addType('address', 'string');
		$this->addType('contactPerson', 'string');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$this->addType('projectCount', 'integer');
		$this->addType('canDelete', 'boolean');
	}

	/**
	 * Get display name for the customer
	 *
	 * @return string
	 */
	public function getDisplayName()
	{
		return $this->getName();
	}

	/**
	 * Get contact information as a formatted string
	 *
	 * @return string
	 */
	public function getContactInfo()
	{
		$info = [];

		if ($this->getContactPerson()) {
			$info[] = $this->getContactPerson();
		}

		if ($this->getEmail()) {
			$info[] = $this->getEmail();
		}

		if ($this->getPhone()) {
			$info[] = $this->getPhone();
		}

		return implode(' | ', $info);
	}

	/**
	 * Check if customer has complete contact information
	 *
	 * @return bool
	 */
	public function hasCompleteContactInfo()
	{
		return !empty($this->getEmail()) && !empty($this->getPhone()) && !empty($this->getContactPerson());
	}

	/**
	 * Get customer summary for display
	 *
	 * @return array
	 */
	public function getSummary()
	{
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'email' => $this->getEmail(),
			'contactPerson' => $this->getContactPerson(),
			'hasCompleteInfo' => $this->hasCompleteContactInfo(),
			'createdAt' => $this->getCreatedAt()->format('d.m.Y H:i:s'),
			'updatedAt' => $this->getUpdatedAt()->format('d.m.Y H:i:s')
		];
	}

	/**
	 * Validate customer data
	 *
	 * @return array Array of validation errors
	 */
	public function validate()
	{
		$errors = [];

		// Validate name
		if (empty($this->getName())) {
			$errors['name'] = 'Customer name is required';
		} elseif (strlen($this->getName()) > 100) {
			$errors['name'] = 'Customer name must be 100 characters or less';
		}

		// Validate email if provided
		if (!empty($this->getEmail()) && !filter_var($this->getEmail(), FILTER_VALIDATE_EMAIL)) {
			$errors['email'] = 'Invalid email format';
		}

		// Validate phone if provided
		if (!empty($this->getPhone()) && strlen($this->getPhone()) > 50) {
			$errors['phone'] = 'Phone number must be 50 characters or less';
		}

		// Validate contact person if provided
		if (!empty($this->getContactPerson()) && strlen($this->getContactPerson()) > 100) {
			$errors['contactPerson'] = 'Contact person name must be 100 characters or less';
		}

		// Validate address if provided
		if (!empty($this->getAddress()) && strlen($this->getAddress()) > 500) {
			$errors['address'] = 'Address must be 500 characters or less';
		}

		// Validate email length if provided
		if (!empty($this->getEmail()) && strlen($this->getEmail()) > 254) {
			$errors['email'] = 'Email address must be 254 characters or less';
		}

		return $errors;
	}

	/**
	 * Check if customer data is valid
	 *
	 * @return bool
	 */
	public function isValid()
	{
		return empty($this->validate());
	}
}
