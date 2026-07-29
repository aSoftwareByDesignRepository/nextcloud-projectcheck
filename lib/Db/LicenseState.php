<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Singleton row (SPEC §4.2) — paste replaces in one transaction.
 *
 * @method string getCustomerId()
 * @method void setCustomerId(string $v)
 * @method string getIssuedAt()
 * @method void setIssuedAt(string $v)
 * @method string getValidUntil()
 * @method void setValidUntil(string $v)
 * @method int getMobileSeats()
 * @method void setMobileSeats(int $v)
 * @method string getPayloadB64()
 * @method void setPayloadB64(string $v)
 * @method string getSignatureB64()
 * @method void setSignatureB64(string $v)
 * @method int getAppliedAt()
 * @method void setAppliedAt(int $v)
 * @method string getAppliedBy()
 * @method void setAppliedBy(string $v)
 */
class LicenseState extends Entity
{
	protected string $customerId = '';
	protected string $issuedAt = '';
	protected string $validUntil = '';
	protected int $mobileSeats = 0;
	protected string $payloadB64 = '';
	protected string $signatureB64 = '';
	protected int $appliedAt = 0;
	protected string $appliedBy = '';

	public function __construct()
	{
		$this->addType('customerId', 'string');
		$this->addType('issuedAt', 'string');
		$this->addType('validUntil', 'string');
		$this->addType('mobileSeats', 'integer');
		$this->addType('payloadB64', 'string');
		$this->addType('signatureB64', 'string');
		$this->addType('appliedAt', 'integer');
		$this->addType('appliedBy', 'string');
	}
}
