<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\ProjectController;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Mutating project save endpoints must carry UserRateLimit (DoS / scrape brake).
 */
final class ProjectFormMutatingRateLimitContractTest extends TestCase
{
	/** @return list<string> */
	public function mutatingMethods(): array
	{
		return [
			['store'],
			['update'],
			['updatePost'],
			['apiStore'],
			['apiUpdate'],
			['delete'],
			['deletePost'],
			['apiDelete'],
			['addTeamMember'],
			['addAllTeamMembers'],
			['updateTeamMember'],
			['updateTeamMemberRole'],
			['removeTeamMember'],
			['removeTeamMemberPost'],
		];
	}

	/**
	 * @dataProvider mutatingMethods
	 */
	public function testMutatingMethodHasUserRateLimit(string $methodName): void
	{
		$ref = new ReflectionMethod(ProjectController::class, $methodName);
		$attrs = $ref->getAttributes(UserRateLimit::class);
		self::assertCount(1, $attrs, $methodName . ' must declare UserRateLimit');
		$args = $attrs[0]->getArguments();
		self::assertArrayHasKey('limit', $args);
		self::assertArrayHasKey('period', $args);
		self::assertGreaterThan(0, (int) $args['limit']);
		self::assertGreaterThan(0, (int) $args['period']);
	}
}
