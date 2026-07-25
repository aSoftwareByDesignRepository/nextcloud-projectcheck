<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Facade;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;

/**
 * Read-only ProjectCheck customer/project surface for CustomerCheck (PSD §5.2).
 */
class CrmCustomerReadFacade
{
	public const FACADE_VERSION = 1;

	public function __construct(
		private readonly CustomerService $customerService,
		private readonly ProjectService $projectService,
	) {
	}

	/** @return array<string, mixed>|null */
	public function getCustomer(int $id): ?array
	{
		$customer = $this->customerService->getCustomer($id);
		return $customer === null ? null : $this->customerDto($customer);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listCustomers(int $limit = 500): array
	{
		$customers = $this->customerService->getCustomers([]);
		$customers = array_slice($customers, 0, max(1, min(2000, $limit)));
		return array_map(fn (Customer $c) => $this->customerDto($c), $customers);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function searchCustomers(string $q, int $limit = 50): array
	{
		$q = trim($q);
		if ($q === '') {
			return [];
		}
		$customers = $this->customerService->searchCustomers($q);
		$customers = array_slice($customers, 0, max(1, min(200, $limit)));
		return array_map(fn (Customer $c) => $this->customerDto($c), $customers);
	}

	/**
	 * @return list<array{id: int, name: string, status: string, openHours: float}>
	 */
	public function listProjects(int $customerId, int $limit = 200): array
	{
		if ($customerId <= 0) {
			return [];
		}
		$projects = $this->projectService->getProjectsByCustomer($customerId);
		$out = [];
		foreach (array_slice($projects, 0, max(1, min(500, $limit))) as $project) {
			if (!$project instanceof Project) {
				continue;
			}
			$out[] = [
				'id' => (int)$project->getId(),
				'name' => (string)$project->getName(),
				'status' => method_exists($project, 'getStatus') ? (string)$project->getStatus() : 'active',
				'openHours' => 0.0,
			];
		}
		return $out;
	}

	/** @return array<string, mixed> */
	private function customerDto(Customer $customer): array
	{
		return [
			'id' => (int)$customer->getId(),
			'name' => (string)$customer->getName(),
			'email' => $customer->getEmail(),
			'phone' => $customer->getPhone(),
			'contactPerson' => $customer->getContactPerson(),
			'address' => $customer->getAddress(),
		];
	}
}
