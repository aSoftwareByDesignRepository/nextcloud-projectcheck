<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\CustomerController;
use OCA\ProjectCheck\Controller\EmployeeController;
use OCA\ProjectCheck\Controller\LicenseController;
use OCA\ProjectCheck\Controller\ProjectMemberController;
use OCA\ProjectCheck\Controller\TimeEntryController;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Web mutating time-entry / customer / employee / member / license endpoints must carry UserRateLimit
 * (parity with MobileController + ProjectController).
 */
final class MutatingEndpointRateLimitContractTest extends TestCase
{
	/** @return list<array{0: class-string, 1: string}> */
	public function mutatingMethods(): array
	{
		return [
			[TimeEntryController::class, 'store'],
			[TimeEntryController::class, 'update'],
			[TimeEntryController::class, 'updatePost'],
			[TimeEntryController::class, 'delete'],
			[TimeEntryController::class, 'deletePost'],
			[CustomerController::class, 'store'],
			[CustomerController::class, 'update'],
			[CustomerController::class, 'updatePost'],
			[CustomerController::class, 'delete'],
			[CustomerController::class, 'deletePost'],
			[EmployeeController::class, 'assignProject'],
			[EmployeeController::class, 'addHourlyRate'],
			[EmployeeController::class, 'unassignProject'],
			[EmployeeController::class, 'unassignProjectPost'],
			[ProjectMemberController::class, 'remove'],
			[ProjectMemberController::class, 'removePost'],
			[LicenseController::class, 'apply'],
			[LicenseController::class, 'remove'],
			[LicenseController::class, 'assignSeat'],
			[LicenseController::class, 'removeSeat'],
		];
	}

	/**
	 * @dataProvider mutatingMethods
	 * @param class-string $class
	 */
	public function testMutatingMethodHasUserRateLimit(string $class, string $methodName): void
	{
		$ref = new ReflectionMethod($class, $methodName);
		$attrs = $ref->getAttributes(UserRateLimit::class);
		self::assertCount(1, $attrs, $class . '::' . $methodName . ' must declare UserRateLimit');
		$args = $attrs[0]->getArguments();
		self::assertArrayHasKey('limit', $args);
		self::assertArrayHasKey('period', $args);
		self::assertGreaterThan(0, (int) $args['limit']);
		self::assertGreaterThan(0, (int) $args['period']);
	}
}
