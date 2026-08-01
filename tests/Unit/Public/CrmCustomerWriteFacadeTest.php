<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Public;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\CustomerMapper;
use OCA\ProjectCheck\Public\CrmCustomerWriteFacade;
use OCA\ProjectCheck\Public\CrmCustomerWriteRequest;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * CHECK-SUITE FC-PC-WRITE / AC-FC1.
 */
class CrmCustomerWriteFacadeTest extends TestCase
{
	private CustomerService&MockObject $customers;
	private ProjectService&MockObject $projects;
	private CustomerMapper&MockObject $mapper;
	private CrmCustomerWriteFacade $facade;

	protected function setUp(): void
	{
		$this->customers = $this->createMock(CustomerService::class);
		$this->projects = $this->createMock(ProjectService::class);
		$this->mapper = $this->createMock(CustomerMapper::class);
		$this->facade = new CrmCustomerWriteFacade($this->customers, $this->projects, $this->mapper);
	}

	public function testCreateCustomerSuccess(): void
	{
		$this->projects->method('canUserCreateCustomer')->with('alice')->willReturn(true);
		$this->mapper->method('findByName')->with('Acme GmbH')->willReturn(null);

		$created = new Customer();
		$created->setId(42);
		$created->setName('Acme GmbH');
		$this->customers->expects($this->once())
			->method('createCustomer')
			->with(
				$this->callback(static fn (array $data): bool => ($data['name'] ?? '') === 'Acme GmbH'
					&& ($data['email'] ?? null) === 'info@acme.example'),
				'alice',
			)
			->willReturn($created);

		$result = $this->facade->createCustomer($this->validRequest());

		$this->assertTrue($result->ok);
		$this->assertNull($result->code);
		$this->assertSame(42, $result->data['pcCustomerId']);
		$this->assertTrue($result->data['created']);
	}

	public function testCreateCustomerDenied(): void
	{
		$this->projects->method('canUserCreateCustomer')->with('bob')->willReturn(false);

		$result = $this->facade->createCustomer($this->validRequest(['actorUid' => 'bob']));

		$this->assertFalse($result->ok);
		$this->assertSame('permission_denied', $result->code);
		$this->customers->expects($this->never())->method('createCustomer');
	}

	public function testCreateCustomerDuplicateName(): void
	{
		$this->projects->method('canUserCreateCustomer')->willReturn(true);
		$existing = new Customer();
		$existing->setId(7);
		$existing->setName('Acme GmbH');
		$this->mapper->method('findByName')->willReturn($existing);

		$result = $this->facade->createCustomer($this->validRequest());

		$this->assertFalse($result->ok);
		$this->assertSame('duplicate_name', $result->code);
		$this->assertSame(7, $result->data['existingPcCustomerId']);
	}

	public function testCreateCustomerRejectsInvalidSlug(): void
	{
		$result = $this->facade->createCustomer($this->validRequest(['crmCompanySlug' => 'BAD']));

		$this->assertFalse($result->ok);
		$this->assertSame('validation_failed', $result->code);
	}

	public function testEnsureLinkSuccessIdempotent(): void
	{
		$customer = new Customer();
		$customer->setId(42);
		$customer->setName('Acme GmbH');
		$this->customers->method('getCustomer')->with(42)->willReturn($customer);
		$this->customers->method('canUserViewCustomer')->with('alice', 42)->willReturn(true);

		$req = $this->validRequest(['existingPcCustomerId' => 42, 'displayName' => '']);
		$result = $this->facade->ensureLink($req);
		$this->assertTrue($result->ok);
		$this->assertFalse($result->data['created']);
		$this->assertSame(42, $result->data['pcCustomerId']);

		$result2 = $this->facade->ensureLink($req);
		$this->assertTrue($result2->ok);
	}

	public function testEnsureLinkNotFound(): void
	{
		$this->customers->method('getCustomer')->willReturn(null);

		$result = $this->facade->ensureLink($this->validRequest(['existingPcCustomerId' => 99]));

		$this->assertFalse($result->ok);
		$this->assertSame('not_found', $result->code);
	}

	public function testEnsureLinkPermissionDenied(): void
	{
		$customer = new Customer();
		$customer->setId(42);
		$customer->setName('Acme');
		$this->customers->method('getCustomer')->willReturn($customer);
		$this->customers->method('canUserViewCustomer')->willReturn(false);
		$this->customers->method('canUserEditCustomer')->willReturn(false);

		$result = $this->facade->ensureLink($this->validRequest(['existingPcCustomerId' => 42]));

		$this->assertFalse($result->ok);
		$this->assertSame('permission_denied', $result->code);
	}

	public function testUpdateDisplayNameSuccess(): void
	{
		$customer = new Customer();
		$customer->setId(42);
		$customer->setName('Old');
		$this->customers->method('canUserEditCustomer')->with('alice', 42)->willReturn(true);
		$this->customers->method('getCustomer')->with(42)->willReturn($customer);

		$updated = new Customer();
		$updated->setId(42);
		$updated->setName('Acme GmbH');
		$this->customers->expects($this->once())
			->method('updateCustomer')
			->with(42, ['name' => 'Acme GmbH'])
			->willReturn($updated);

		$result = $this->facade->updateDisplayName($this->validRequest(['existingPcCustomerId' => 42]));

		$this->assertTrue($result->ok);
		$this->assertSame('Acme GmbH', $result->data['displayName']);
	}

	public function testUpdateDisplayNameDuplicate(): void
	{
		$customer = new Customer();
		$customer->setId(42);
		$customer->setName('Old');
		$this->customers->method('canUserEditCustomer')->willReturn(true);
		$this->customers->method('getCustomer')->willReturn($customer);
		$this->customers->method('updateCustomer')->willThrowException(new \Exception('A customer with this name already exists'));

		$result = $this->facade->updateDisplayName($this->validRequest(['existingPcCustomerId' => 42]));

		$this->assertFalse($result->ok);
		$this->assertSame('duplicate_name', $result->code);
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function validRequest(array $overrides = []): CrmCustomerWriteRequest
	{
		return CrmCustomerWriteRequest::fromArray(array_merge([
			'actorUid' => 'alice',
			'displayName' => 'Acme GmbH',
			'email' => 'info@acme.example',
			'crmCompanyId' => 17,
			'crmCompanySlug' => 'acme-gmbh',
			'crmUpdatedAt' => '2026-07-26 12:00:00',
		], $overrides));
	}
}
