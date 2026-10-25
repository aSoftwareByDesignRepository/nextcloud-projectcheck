<?php

declare(strict_types=1);

/**
 * Contract proof for the upgrade-backup coordination contract:
 * UpgradeBackupService::restore() holds LOCK_KEY exclusively — every
 * background job that scans/writes app tables must take the SHARED lock,
 * skip on LockedException without doing work, and always release.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\BackgroundJob;

use OCA\ProjectCheck\BackgroundJob\CleanupJob as BgCleanupJob;
use OCA\ProjectCheck\Cron\CleanupJob as CronCleanupJob;
use OCA\ProjectCheck\Service\BudgetAlertService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Service\UpgradeBackupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UpgradeBackupSharedLockTest extends TestCase
{
	/** Every app background job — the whole family is wired into the contract. */
	private const JOB_CLASSES = [
		BgCleanupJob::class,
		CronCleanupJob::class,
	];

	/**
	 * Static contract: every job class (a) declares an ILockingProvider
	 * constructor dependency and (b) its source acquires the shared lock on
	 * UpgradeBackupService::LOCK_KEY and releases it in a finally.
	 */
	public function testEveryJobIsWiredIntoSharedLockContract(): void
	{
		foreach (self::JOB_CLASSES as $class) {
			$ref = new \ReflectionClass($class);
			$ctor = $ref->getConstructor();
			$this->assertNotNull($ctor, $class);
			$hasLockDep = false;
			foreach ($ctor->getParameters() as $p) {
				$t = $p->getType();
				if ($t instanceof \ReflectionNamedType && $t->getName() === ILockingProvider::class) {
					$hasLockDep = true;
				}
			}
			$this->assertTrue($hasLockDep, "$class constructor lacks an ILockingProvider dependency");

			$file = $ref->getFileName();
			$this->assertNotFalse($file, $class);
			$src = (string)file_get_contents($file);
			$this->assertStringContainsString('UpgradeBackupService::LOCK_KEY', $src, $class);
			$this->assertStringContainsString('ILockingProvider::LOCK_SHARED', $src, $class);
			$this->assertStringContainsString('releaseLock(UpgradeBackupService::LOCK_KEY', $src, $class);
			$this->assertStringContainsString('} finally {', $src, $class);
		}
		// Sanity: the contract really does cover every job class on disk — no
		// silently missed class when a third job lands (jobs live in
		// lib/BackgroundJob/ AND lib/Cron/).
		$libRoot = dirname((string)(new \ReflectionClass(BgCleanupJob::class))->getFileName(), 2);
		$onDisk = [];
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($libRoot, \FilesystemIterator::SKIP_DOTS)) as $f) {
			$path = $f->getPathname();
			if (str_ends_with($path, 'Job.php')) {
				$relative = substr($path, strlen($libRoot) + 1, -4);
				$onDisk[] = 'OCA\\ProjectCheck\\' . str_replace('/', '\\', $relative);
			}
		}
		sort($onDisk);
		$declared = self::JOB_CLASSES;
		sort($declared);
		$this->assertSame($onDisk, $declared, 'JOB_CLASSES must enumerate every *Job.php under lib/');
	}

	private function lockedProvider(): ILockingProvider
	{
		$locking = $this->createMock(ILockingProvider::class);
		$locking->method('acquireLock')
			->with(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED)
			->willThrowException(new LockedException('restore in progress'));
		return $locking;
	}

	/**
	 * A job locked out by a restore skips without touching app tables —
	 * the schema guard (first statement of doRun) is never reached and the
	 * skip is logged as a warning, not an error.
	 */
	public function testLockedOutBackgroundJobSkipsWithoutTouchingAppTables(): void
	{
		$schemaGuard = $this->createMock(SchemaGuardService::class);
		$schemaGuard->expects($this->never())->method('ensureReady');
		$projectService = $this->createMock(ProjectService::class);
		$projectService->expects($this->never())->method('getAllProjects');
		$timeEntryService = $this->createMock(TimeEntryService::class);
		$timeEntryService->expects($this->never())->method('getTimeEntriesByDateRange');
		$customerService = $this->createMock(CustomerService::class);
		$customerService->expects($this->never())->method('getAllCustomers');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')
			->with($this->stringContains('skipping'), $this->arrayHasKey('app'));
		$logger->expects($this->never())->method('error');

		$job = new BgCleanupJob(
			$this->createMock(ITimeFactory::class),
			$logger,
			$projectService,
			$timeEntryService,
			$customerService,
			$this->createMock(IConfig::class),
			$schemaGuard,
			$this->lockedProvider(),
		);
		$job->run(null);
	}

	public function testLockedOutCronJobSkipsWithoutTouchingAppTables(): void
	{
		$schemaGuard = $this->createMock(SchemaGuardService::class);
		$schemaGuard->expects($this->never())->method('ensureReady');
		$projectService = $this->createMock(ProjectService::class);
		$projectService->expects($this->never())->method('getAllProjects');
		$budgetAlerts = $this->createMock(BudgetAlertService::class);
		$budgetAlerts->expects($this->never())->method('checkBudgetAlerts');

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')
			->with($this->stringContains('skipping'), $this->arrayHasKey('app'));
		$logger->expects($this->never())->method('error');

		$job = new CronCleanupJob(
			$this->createMock(ITimeFactory::class),
			$logger,
			$projectService,
			$this->createMock(TimeEntryService::class),
			$this->createMock(CustomerService::class),
			$budgetAlerts,
			$this->createMock(IConfig::class),
			$schemaGuard,
			$this->lockedProvider(),
		);
		$job->run(null);
	}

	/**
	 * acquire+release must bracket the work even when it blows up — a leaked
	 * shared lock would starve the exclusive restore.
	 */
	public function testSharedLockIsReleasedWhenWorkThrows(): void
	{
		$locking = $this->createMock(ILockingProvider::class);
		$locking->expects($this->once())->method('acquireLock')
			->with(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED, $this->isType('string'));
		$locking->expects($this->once())->method('releaseLock')
			->with(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED);

		$schemaGuard = $this->createMock(SchemaGuardService::class);
		$schemaGuard->method('ensureReady')
			->willThrowException(new \RuntimeException('db gone'));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')
			->with($this->stringContains('Error in ProjectControl cleanup job'));

		$job = new BgCleanupJob(
			$this->createMock(ITimeFactory::class),
			$logger,
			$this->createMock(ProjectService::class),
			$this->createMock(TimeEntryService::class),
			$this->createMock(CustomerService::class),
			$this->createMock(IConfig::class),
			$schemaGuard,
			$locking,
		);
		$job->run(null);
	}

	public function testHappyPathAcquiresAndReleasesSharedLock(): void
	{
		$locking = $this->createMock(ILockingProvider::class);
		$locking->expects($this->once())->method('acquireLock')
			->with(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED, $this->isType('string'));
		$locking->expects($this->once())->method('releaseLock')
			->with(UpgradeBackupService::LOCK_KEY, ILockingProvider::LOCK_SHARED);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('0');
		$projectService = $this->createMock(ProjectService::class);
		$projectService->method('getAllProjects')->willReturn([]);
		$customerService = $this->createMock(CustomerService::class);
		$customerService->method('getAllCustomers')->willReturn([]);

		$job = new BgCleanupJob(
			$this->createMock(ITimeFactory::class),
			$this->createMock(LoggerInterface::class),
			$projectService,
			$this->createMock(TimeEntryService::class),
			$customerService,
			$config,
			$this->createMock(SchemaGuardService::class),
			$locking,
		);
		$job->run(null);
	}
}
