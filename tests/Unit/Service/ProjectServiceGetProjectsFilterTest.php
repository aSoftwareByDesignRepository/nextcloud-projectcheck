<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\ProjectService;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Contract test: joined customer rows must use table-qualified filter columns.
 */
class ProjectServiceGetProjectsFilterTest extends TestCase
{
	public function testGetProjectsSearchFilterUsesQualifiedProjectColumns(): void
	{
		$likeCalls = [];
		$composite = $this->createMock(\OCP\DB\QueryBuilder\ICompositeExpression::class);
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('orX')->willReturn($composite);
		$expr->method('like')->willReturnCallback(static function ($column, $param) use (&$likeCalls) {
			$likeCalls[] = $column;
			return $column . ':' . $param;
		});

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('leftJoin')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('orderBy')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn('param');

		$result = $this->createMock(\OCP\DB\IResult::class);
		$result->method('fetch')->willReturn(false);
		$qb->method('executeQuery')->willReturn($result);

		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(true);
		$accessControl = $this->createMock(AccessControlService::class);
		$accessControl->method('canManageAppConfiguration')->willReturn(true);

		$service = new ProjectService(
			$db,
			$userSession,
			$this->createMock(IUserManager::class),
			$this->createMock(IConfig::class),
			$groupManager,
			null,
			null,
			$accessControl,
		);

		$service->getProjects(['search' => 'alpha']);

		$this->assertSame(
			['p.name', 'p.short_description', 'c.name'],
			$likeCalls,
			'Search filters must qualify joined columns to avoid ambiguous SQL errors.',
		);
	}
}
