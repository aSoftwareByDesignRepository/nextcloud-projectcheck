<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Facade;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;

/**
 * Read-only ProjectCheck customer/project surface for CustomerCheck (PSD §5.2).
 *
 * openHours comes from settlement counters (stl_open_hours) — never a hardcoded stub 0.
 */
class CrmCustomerReadFacade
{
	public const FACADE_VERSION = 2;

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
	 * Open billable minutes across projects for a PC customer (Command Center §7.3).
	 * Uses settlement open hours. Returns 0 when none; never negative.
	 * Caller must pass a real pcCustomerId — never call without an id.
	 */
	public function sumOpenBillableMinutes(int $pcCustomerId): int
	{
		if ($pcCustomerId <= 0) {
			return 0;
		}
		$sum = 0;
		foreach ($this->listProjects($pcCustomerId, 500) as $project) {
			$status = mb_strtolower((string)($project['status'] ?? 'active'));
			if (in_array($status, ['done', 'closed', 'cancelled', 'archived'], true)) {
				continue;
			}
			$sum += (int)round(max(0.0, (float)($project['openHours'] ?? 0)) * 60);
		}

		return max(0, $sum);
	}

	/**
	 * @return list<array{id: int, name: string, status: string, openHours: float, open: bool, uninvoiced?: bool}>
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
			$status = method_exists($project, 'getStatus') ? (string)$project->getStatus() : 'active';
			$statusLower = mb_strtolower($status);
			$open = !in_array($statusLower, ['done', 'closed', 'cancelled', 'archived'], true);
			$openHours = 0.0;
			if (method_exists($project, 'getStlOpenHours')) {
				$openHours = max(0.0, (float)$project->getStlOpenHours());
			}
			$out[] = [
				'id' => (int)$project->getId(),
				'name' => (string)$project->getName(),
				'status' => $status,
				'openHours' => $openHours,
				'open' => $open,
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
