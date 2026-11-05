<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\CustomerMapper;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use PHPUnit\Framework\TestCase;

class CustomerServiceTest extends TestCase
{
	private function makeService(CustomerMapper $customerMapper, ProjectService $projectService): CustomerService
	{
		return new CustomerService(
			$customerMapper,
			$projectService,
			$this->createMock(TimeEntryMapper::class),
			$this->createMock(AccessControlService::class),
		);
	}

	public function testCreateCustomerMapsUniqueViolationToAlreadyExists(): void
	{
		// The findByName pre-check passes (free name) but pc_cus_name_uq fires
		// on the write — a concurrent create claimed it first. The serialized
		// outcome must be the same already-exists error, never the raw DB
		// exception.
		$customerMapper = $this->createMock(CustomerMapper::class);
		$customerMapper->method('findByName')->willReturn(null);
		$customerMapper->method('insert')->willThrowException(
			self::dbException(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION)
		);

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserCreateCustomer')->willReturn(true);

		$svc = $this->makeService($customerMapper, $projectService);

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('A customer with this name already exists');
		$svc->createCustomer(['name' => 'Acme GmbH'], 'alice');
	}

	public function testCreateCustomerRethrowsNonUniqueDbException(): void
	{
		$dbError = self::dbException(\OCP\DB\Exception::REASON_CONNECTION_LOST);
		$customerMapper = $this->createMock(CustomerMapper::class);
		$customerMapper->method('findByName')->willReturn(null);
		$customerMapper->method('insert')->willThrowException($dbError);

		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('canUserCreateCustomer')->willReturn(true);

		$svc = $this->makeService($customerMapper, $projectService);

		try {
			$svc->createCustomer(['name' => 'Acme GmbH'], 'alice');
			$this->fail('expected the DB exception to propagate');
		} catch (\Exception $e) {
			$this->assertSame($dbError, $e);
		}
	}

	public function testUpdateCustomerMapsUniqueViolationToAlreadyExists(): void
	{
		$existing = new Customer();
		$existing->setId(7);
		$existing->setName('Old Name');

		$customerMapper = $this->createMock(CustomerMapper::class);
		$customerMapper->method('find')->with(7)->willReturn($existing);
		$customerMapper->method('findByName')->willReturn(null);
		$customerMapper->method('update')->willThrowException(
			self::dbException(\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION)
		);

		$svc = $this->makeService($customerMapper, $this->createMock(ProjectService::class));

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('A customer with this name already exists');
		$svc->updateCustomer(7, ['name' => 'Taken Name']);
	}

	private static function dbException(int $reason): \OCP\DB\Exception
	{
		return new class('db error', 0, null, $reason) extends \OCP\DB\Exception {
			public function __construct(
				string $message,
				int $code,
				?\Throwable $previous,
				private int $reasonValue,
			) {
				parent::__construct($message, $code, $previous);
			}

			public function getReason(): ?int
			{
				return $this->reasonValue;
			}
		};
	}
}
