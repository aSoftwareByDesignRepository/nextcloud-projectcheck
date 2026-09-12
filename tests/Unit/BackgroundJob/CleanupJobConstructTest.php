<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\BackgroundJob;

use OCA\ProjectCheck\BackgroundJob\CleanupJob as BgCleanupJob;
use OCA\ProjectCheck\Cron\CleanupJob as CronCleanupJob;
use OCA\ProjectCheck\Service\BudgetAlertService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Regression: OCP\BackgroundJob\Job requires ITimeFactory — empty parent::__construct()
 * fatals on cron DI (GitHub #12). Do not cover jobs with newInstanceWithoutConstructor only.
 */
final class CleanupJobConstructTest extends TestCase
{
	public function testBackgroundCleanupJobConstructsWithTimeFactory(): void
	{
		$time = $this->createMock(ITimeFactory::class);
		$job = new BgCleanupJob(
			$time,
			$this->createMock(LoggerInterface::class),
			$this->createMock(ProjectService::class),
			$this->createMock(TimeEntryService::class),
			$this->createMock(CustomerService::class),
			$this->createMock(IConfig::class),
			$this->createMock(SchemaGuardService::class),
		);
		self::assertInstanceOf(BgCleanupJob::class, $job);
	}

	public function testCronCleanupJobConstructsWithTimeFactory(): void
	{
		$time = $this->createMock(ITimeFactory::class);
		$job = new CronCleanupJob(
			$time,
			$this->createMock(LoggerInterface::class),
			$this->createMock(ProjectService::class),
			$this->createMock(TimeEntryService::class),
			$this->createMock(CustomerService::class),
			$this->createMock(BudgetAlertService::class),
			$this->createMock(IConfig::class),
			$this->createMock(SchemaGuardService::class),
		);
		self::assertInstanceOf(CronCleanupJob::class, $job);
	}

	public function testNoEmptyParentConstructInJobSources(): void
	{
		$files = [
			dirname(__DIR__, 3) . '/lib/BackgroundJob/CleanupJob.php',
			dirname(__DIR__, 3) . '/lib/Cron/CleanupJob.php',
		];
		foreach ($files as $file) {
			$src = (string) file_get_contents($file);
			self::assertDoesNotMatchRegularExpression(
				'/parent::__construct\s*\(\s*\)/',
				$src,
				$file . ' must pass ITimeFactory to parent::__construct($time)'
			);
			$self = new ReflectionClass(
				str_contains($file, '/Cron/') ? CronCleanupJob::class : BgCleanupJob::class
			);
			$ctor = $self->getConstructor();
			self::assertNotNull($ctor);
			$first = $ctor->getParameters()[0] ?? null;
			self::assertNotNull($first);
			self::assertSame(ITimeFactory::class, $first->getType()?->getName());
		}
	}
}
