<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\CustomerSettlementService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Spec §8.2 / §12.6: Manager AR rollups use settleable projects; Members use accessible.
 */
class CustomerSettlementScopeTest extends TestCase
{
	public function testGlobalSettlerUsesUnscopedProjects(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$projectService->expects($this->once())
			->method('getSettleableProjectIdListForUser')
			->with('admin')
			->willReturn(null);
		$projectService->expects($this->never())->method('getAccessibleProjectIdListForUser');

		$scope = $this->invokeResolveScope($projectService, 'admin');
		$this->assertNull($scope);
	}

	public function testManagerUsesSettleableOnly(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$projectService->expects($this->once())
			->method('getSettleableProjectIdListForUser')
			->with('manager')
			->willReturn([10, 20]);
		$projectService->expects($this->never())->method('getAccessibleProjectIdListForUser');

		$scope = $this->invokeResolveScope($projectService, 'manager');
		$this->assertSame([10, 20], $scope);
	}

	public function testPureMemberFallsBackToAccessible(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$projectService->expects($this->once())
			->method('getSettleableProjectIdListForUser')
			->with('member')
			->willReturn([]);
		$projectService->expects($this->once())
			->method('getAccessibleProjectIdListForUser')
			->with('member')
			->willReturn([7, 8]);

		$scope = $this->invokeResolveScope($projectService, 'member');
		$this->assertSame([7, 8], $scope);
	}

	/**
	 * @return list<int>|null
	 */
	private function invokeResolveScope(ProjectService $projectService, string $userId): ?array
	{
		$service = new CustomerSettlementService(
			$this->createMock(IDBConnection::class),
			$projectService
		);
		$ref = new \ReflectionMethod(CustomerSettlementService::class, 'resolveRollupProjectScope');
		$ref->setAccessible(true);
		/** @var list<int>|null $scope */
		$scope = $ref->invoke($service, $userId);
		return $scope;
	}
}
