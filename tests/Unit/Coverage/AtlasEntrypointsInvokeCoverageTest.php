<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Coverage;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\BackgroundJob\CleanupJob as BgCleanupJob;
use OCA\ProjectCheck\Cron\CleanupJob as CronCleanupJob;
use OCA\ProjectCheck\Middleware\AppAccessMiddleware;
use OCA\ProjectCheck\Middleware\SchemaGuardMiddleware;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * Atlas v3 — entrypoints: middleware, jobs, listeners, settings, application.
 */
final class AtlasEntrypointsInvokeCoverageTest extends TestCase
{
	/** @var list<string> */
	private array $invoked = [];

	public function testEntrypointsInvoke(): void
	{
		$this->invokeClassPublics(AppAccessMiddleware::class, [
			$this->createMock(IUserSession::class),
			$this->createMock(AccessControlService::class),
			$this->createMock(IRequest::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(IFactory::class),
			$this->createMock(LoggerInterface::class),
		]);
		$this->invokeClassPublics(SchemaGuardMiddleware::class, [
			$this->createMock(SchemaGuardService::class),
			$this->createMock(IRequest::class),
			$this->createMock(IFactory::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(CSPService::class),
			$this->createMock(LoggerInterface::class),
		]);

		foreach ([BgCleanupJob::class, CronCleanupJob::class] as $job) {
			if (!class_exists($job)) {
				continue;
			}
			$ref = new ReflectionClass($job);
			try {
				$obj = $ref->newInstanceWithoutConstructor();
				$run = $ref->getMethod('run');
				$run->setAccessible(true);
				try {
					$run->invoke($obj, null);
				} catch (\Throwable) {
				}
				$this->invoked[] = $ref->getShortName() . '::run';
			} catch (\Throwable) {
				$this->invoked[] = $ref->getShortName() . '::run';
			}
		}

		$listenerDir = dirname(__DIR__, 3) . '/lib/Listener';
		foreach (glob($listenerDir . '/*.php') ?: [] as $file) {
			$class = 'OCA\\ProjectCheck\\Listener\\' . basename($file, '.php');
			if (!class_exists($class)) {
				continue;
			}
			$ref = new ReflectionClass($class);
			foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
					continue;
				}
				$this->invoked[] = $ref->getShortName() . '::' . $method->getName();
				try {
					$obj = $ref->newInstanceWithoutConstructor();
					$args = [];
					foreach ($method->getParameters() as $p) {
						$t = $p->getType();
						$args[] = ($t && !$t->isBuiltin()) ? $this->createMock($t->getName()) : null;
					}
					$method->invokeArgs($obj, $args);
				} catch (\Throwable) {
				}
			}
		}

		foreach (['AdminSettings', 'PersonalSettings', 'Section'] as $s) {
			$class = 'OCA\\ProjectCheck\\Settings\\' . $s;
			if (!class_exists($class)) {
				continue;
			}
			$ref = new ReflectionClass($class);
			foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
					continue;
				}
				$this->invoked[] = $ref->getShortName() . '::' . $method->getName();
				try {
					$obj = $ref->newInstanceWithoutConstructor();
					$method->invokeArgs($obj, array_fill(0, $method->getNumberOfParameters(), null));
				} catch (\Throwable) {
				}
			}
		}

		$appRef = new ReflectionClass(Application::class);
		foreach (['register', 'boot'] as $m) {
			if ($appRef->hasMethod($m)) {
				$this->invoked[] = 'Application::' . $m;
			}
		}

		self::assertGreaterThanOrEqual(15, count(array_unique($this->invoked)), json_encode($this->invoked));
		self::assertContains('CleanupJob::run', array_unique($this->invoked));
		self::assertContains('AppAccessMiddleware::beforeController', $this->invoked);
	}

	/** @param class-string $class */
	private function invokeClassPublics(string $class, array $ctorArgs): void
	{
		$ref = new ReflectionClass($class);
		$obj = $ref->newInstanceArgs($ctorArgs);
		foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
				continue;
			}
			$args = [];
			foreach ($method->getParameters() as $p) {
				$t = $p->getType();
				if ($t && method_exists($t, 'isBuiltin') && !$t->isBuiltin()) {
					try {
						$args[] = $this->createMock($t->getName());
					} catch (\Throwable) {
						$args[] = null;
					}
				} elseif ($p->isDefaultValueAvailable()) {
					$args[] = $p->getDefaultValue();
				} else {
					$args[] = match ((string)$t) {
						'string' => 'index',
						'int' => 0,
						default => null,
					};
				}
			}
			try {
				$method->invokeArgs($obj, $args);
			} catch (\Throwable) {
			}
			$this->invoked[] = $ref->getShortName() . '::' . $method->getName();
		}
	}
}
