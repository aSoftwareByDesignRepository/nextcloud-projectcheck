<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\ActivityService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ActivityServiceProjectAuditTest extends TestCase
{
	public function testLogProjectCreatedPublishesSubject(): void
	{
		$event = $this->createMock(IEvent::class);
		$event->expects(self::once())->method('setApp')->with('projectcheck')->willReturnSelf();
		$event->expects(self::once())->method('setType')->with('projectcheck')->willReturnSelf();
		$event->expects(self::once())->method('setAuthor')->with('alice')->willReturnSelf();
		$event->expects(self::once())->method('setAffectedUser')->with('alice')->willReturnSelf();
		$event->expects(self::once())->method('setObject')->with('project', 7, 'Alpha')->willReturnSelf();
		$event->expects(self::once())->method('setSubject')->with('project_created', self::callback(
			static fn (array $p): bool => ($p['actor'] ?? null) === 'alice'
				&& ($p['project'] ?? null) === 'Alpha'
				&& ($p['project_id'] ?? null) === 7
		))->willReturnSelf();

		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturn($event);
		$manager->expects(self::once())->method('publish')->with($event);

		$project = new Project();
		$project->setId(7);
		$project->setName('Alpha');

		$svc = new ActivityService($manager, $this->createMock(LoggerInterface::class));
		$svc->logProjectCreated('alice', $project);
	}

	public function testLogProjectUpdatedIncludesAllowlistedFieldsMessage(): void
	{
		$event = $this->createMock(IEvent::class);
		$event->method('setApp')->willReturnSelf();
		$event->method('setType')->willReturnSelf();
		$event->method('setAuthor')->willReturnSelf();
		$event->method('setAffectedUser')->willReturnSelf();
		$event->method('setObject')->willReturnSelf();
		$event->expects(self::once())->method('setSubject')->with('project_updated', self::anything())->willReturnSelf();
		$event->expects(self::once())->method('setMessage')->with('project_updated_fields', [
			'changes' => 'name, status',
		])->willReturnSelf();

		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturn($event);
		$manager->expects(self::once())->method('publish')->with($event);

		$project = new Project();
		$project->setId(3);
		$project->setName('Beta');

		$svc = new ActivityService($manager, $this->createMock(LoggerInterface::class));
		$svc->logProjectUpdated('bob', $project, ['name', 'status', '']);
	}

	public function testLogProjectDeletedUsesProviderCompatibleParams(): void
	{
		$event = $this->createMock(IEvent::class);
		$event->method('setApp')->willReturnSelf();
		$event->method('setType')->willReturnSelf();
		$event->method('setAuthor')->willReturnSelf();
		$event->method('setAffectedUser')->willReturnSelf();
		$event->method('setObject')->willReturnSelf();
		$event->expects(self::once())->method('setSubject')->with('project_deleted', self::callback(
			static fn (array $p): bool => ($p['actor'] ?? null) === 'carol'
				&& ($p['project'] ?? null) === 'Gamma'
				&& ($p['project_id'] ?? null) === 9
				&& ($p['time_entries'] ?? null) === 4
				&& ($p['project_members'] ?? null) === 2
				&& !array_key_exists('project_name', $p)
		))->willReturnSelf();

		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturn($event);
		$manager->expects(self::once())->method('publish')->with($event);

		$project = new Project();
		$project->setId(9);
		$project->setName('Gamma');

		$svc = new ActivityService($manager, $this->createMock(LoggerInterface::class));
		$svc->logProjectDeleted('carol', $project, [
			'time_entries' => 4,
			'project_members' => 2,
		]);
	}
}
