<?php

declare(strict_types=1);

/**
 * ProjectCheck customer write surface for CustomerCheck (CHECK-SUITE FC-PC-WRITE).
 *
 * Server-side only. Never writes CRM tables. Never opens a transaction that
 * spans apps. Consumers must tolerate class absence → capability false.
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

use OCA\ProjectCheck\Db\CustomerMapper;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;

/**
 * Server-side only. Not an HTTP surface.
 */
class CrmCustomerWriteFacade
{
	public const FACADE_VERSION = 1;

	/** Slug charset locked by suite SHARED-IDENTITY / FC-PC-WRITE. */
	private const SLUG_PATTERN = '/^[a-z0-9-]{3,64}$/';

	public function __construct(
		private readonly CustomerService $customerService,
		private readonly ProjectService $projectService,
		private readonly CustomerMapper $customerMapper,
	) {
	}

	public function createCustomer(CrmCustomerWriteRequest $req): FacadeResult
	{
		$validation = $this->validateCommon($req, requireExisting: false, requireDisplayName: true);
		if ($validation !== null) {
			return $validation;
		}

		if (!$this->projectService->canUserCreateCustomer($req->actorUid)) {
			return FacadeResult::failure('permission_denied', 'Actor may not create ProjectCheck customers.');
		}

		$existing = $this->customerMapper->findByName($req->displayName);
		if ($existing !== null) {
			return FacadeResult::failure('duplicate_name', 'A customer with this name already exists.', [
				'existingPcCustomerId' => (int)$existing->getId(),
			]);
		}

		try {
			$customer = $this->customerService->createCustomer([
				'name' => $req->displayName,
				'email' => $req->email,
				'phone' => $req->phone,
				'address' => $req->address,
				'contact_person' => $req->contactPerson,
			], $req->actorUid);
		} catch (\Throwable $e) {
			return $this->mapCreateException($e, $req->displayName);
		}

		return FacadeResult::success([
			'pcCustomerId' => (int)$customer->getId(),
			'displayName' => (string)$customer->getName(),
			'created' => true,
		]);
	}

	public function ensureLink(CrmCustomerWriteRequest $req): FacadeResult
	{
		$validation = $this->validateCommon($req, requireExisting: true, requireDisplayName: false);
		if ($validation !== null) {
			return $validation;
		}

		$customerId = (int)$req->existingPcCustomerId;
		$customer = $this->customerService->getCustomer($customerId);
		if ($customer === null) {
			return FacadeResult::failure('not_found', 'ProjectCheck customer not found.');
		}

		if (!$this->customerService->canUserViewCustomer($req->actorUid, $customerId)
			&& !$this->customerService->canUserEditCustomer($req->actorUid, $customerId)
		) {
			return FacadeResult::failure('permission_denied', 'Actor may not link this ProjectCheck customer.');
		}

		return FacadeResult::success([
			'pcCustomerId' => (int)$customer->getId(),
			'displayName' => (string)$customer->getName(),
			'created' => false,
		]);
	}

	public function updateDisplayName(CrmCustomerWriteRequest $req): FacadeResult
	{
		$validation = $this->validateCommon($req, requireExisting: true, requireDisplayName: true);
		if ($validation !== null) {
			return $validation;
		}

		$customerId = (int)$req->existingPcCustomerId;
		if (!$this->customerService->canUserEditCustomer($req->actorUid, $customerId)) {
			return FacadeResult::failure('permission_denied', 'Actor may not edit this ProjectCheck customer.');
		}

		$customer = $this->customerService->getCustomer($customerId);
		if ($customer === null) {
			return FacadeResult::failure('not_found', 'ProjectCheck customer not found.');
		}

		try {
			$updated = $this->customerService->updateCustomer($customerId, [
				'name' => $req->displayName,
			]);
		} catch (\Throwable $e) {
			$message = $e->getMessage();
			if (stripos($message, 'already exists') !== false) {
				$existing = $this->customerMapper->findByName($req->displayName);
				$data = $existing !== null
					? ['existingPcCustomerId' => (int)$existing->getId()]
					: [];
				return FacadeResult::failure('duplicate_name', 'A customer with this name already exists.', $data);
			}
			return FacadeResult::failure('validation_failed', $message);
		}

		return FacadeResult::success([
			'pcCustomerId' => (int)$updated->getId(),
			'displayName' => (string)$updated->getName(),
			'created' => false,
		]);
	}

	private function validateCommon(
		CrmCustomerWriteRequest $req,
		bool $requireExisting,
		bool $requireDisplayName,
	): ?FacadeResult {
		if ($req->actorUid === '') {
			return FacadeResult::failure('validation_failed', 'actorUid is required.');
		}
		if ($req->crmCompanyId <= 0) {
			return FacadeResult::failure('validation_failed', 'crmCompanyId must be > 0.');
		}
		if (!preg_match(self::SLUG_PATTERN, $req->crmCompanySlug)) {
			return FacadeResult::failure('validation_failed', 'crmCompanySlug must match [a-z0-9-]{3,64}.');
		}
		if ($requireDisplayName && $req->displayName === '') {
			return FacadeResult::failure('validation_failed', 'displayName is required.');
		}
		if ($req->email !== null && filter_var($req->email, FILTER_VALIDATE_EMAIL) === false) {
			return FacadeResult::failure('validation_failed', 'email is invalid.');
		}
		if ($requireExisting && ($req->existingPcCustomerId === null || $req->existingPcCustomerId <= 0)) {
			return FacadeResult::failure('validation_failed', 'existingPcCustomerId is required.');
		}

		return null;
	}

	private function mapCreateException(\Throwable $e, string $displayName): FacadeResult
	{
		$message = $e->getMessage();
		if (stripos($message, 'Access denied') !== false) {
			return FacadeResult::failure('permission_denied', 'Actor may not create ProjectCheck customers.');
		}
		if ($this->isUniqueConstraintViolation($e) || stripos($message, 'already exists') !== false) {
			$existing = $this->customerMapper->findByName($displayName);
			$data = $existing !== null
				? ['existingPcCustomerId' => (int)$existing->getId()]
				: [];
			return FacadeResult::failure('duplicate_name', 'A customer with this name already exists.', $data);
		}
		return FacadeResult::failure('validation_failed', $message);
	}

	private function isUniqueConstraintViolation(\Throwable $e): bool
	{
		if ($e instanceof \OCP\DB\Exception && $e->getReason() === \OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
			return true;
		}
		$previous = $e->getPrevious();
		return $previous instanceof \Throwable && $this->isUniqueConstraintViolation($previous);
	}
}
