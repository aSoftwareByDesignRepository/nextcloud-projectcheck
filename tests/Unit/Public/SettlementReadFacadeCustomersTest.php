<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Public;

use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Public\SettlementReadFacade;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\LocaleFormatService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

class SettlementReadFacadeCustomersTest extends TestCase
{
	public function testListCustomersScopedToSettleableProjects(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$customerService = $this->createMock(CustomerService::class);
		$projectService->method('getSettleableProjectIdListForUser')->with('mgr')->willReturn([11, 12]);

		$p1 = new \OCA\ProjectCheck\Db\Project();
		$p1->setCustomerId(3);
		$p2 = new \OCA\ProjectCheck\Db\Project();
		$p2->setCustomerId(3);
		$projectService->method('getProject')->willReturnMap([
			[11, $p1],
			[12, $p2],
		]);

		$customer = new \OCA\ProjectCheck\Db\Customer();
		$customer->setId(3);
		$customer->setName('Acme');
		$customer->setAddress('Street 1');
		$customer->setEmail('a@example.com');
		$customerService->method('getCustomer')->with(3)->willReturn($customer);

		$facade = new SettlementReadFacade(
			$this->createMock(TimeEntryMapper::class),
			$projectService,
			$customerService,
			$this->createMock(ProjectSettlementService::class),
			$this->createMock(LocaleFormatService::class),
			$this->createMock(IAppManager::class),
		);

		$list = $facade->listCustomersForInvoicing('mgr');
		$this->assertCount(1, $list);
		$this->assertSame(3, $list[0]['id']);
		$this->assertSame('Acme', $list[0]['name']);
	}

	public function testGlobalSettlerUsesCustomerSelect(): void
	{
		$projectService = $this->createMock(ProjectService::class);
		$customerService = $this->createMock(CustomerService::class);
		$projectService->method('getSettleableProjectIdListForUser')->willReturn(null);
		$customerService->expects($this->once())
			->method('getCustomersForSelectForUser')
			->with('admin')
			->willReturn([
				['id' => 2, 'name' => 'Beta', 'email' => null, 'contactPerson' => null],
				['id' => 1, 'name' => 'Alpha', 'email' => 'x@y.z', 'contactPerson' => null],
			]);

		$facade = new SettlementReadFacade(
			$this->createMock(TimeEntryMapper::class),
			$projectService,
			$customerService,
			$this->createMock(ProjectSettlementService::class),
			$this->createMock(LocaleFormatService::class),
			$this->createMock(IAppManager::class),
		);

		$list = $facade->listCustomersForInvoicing('admin');
		$this->assertSame('Alpha', $list[0]['name']);
		$this->assertSame('Beta', $list[1]['name']);
	}
}
