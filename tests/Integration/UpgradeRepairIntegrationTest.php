<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Repair\EnsureProjectCheckSchema;
use OCA\ProjectCheck\Repair\UninstallDropTables;
use OCP\Migration\IOutput;
use Test\TestCase;

class UpgradeRepairIntegrationTest extends TestCase
{
	public function testRepairStepsResolveFromContainer(): void
	{
		foreach ([
			EnsureProjectCheckSchema::class,
			UninstallDropTables::class,
		] as $class) {
			$step = \OC::$server->get($class);
			$this->assertInstanceOf($class, $step);
		}
	}

	public function testEnsureProjectCheckSchemaRunsWithoutFatal(): void
	{
		/** @var EnsureProjectCheckSchema $step */
		$step = \OC::$server->get(EnsureProjectCheckSchema::class);
		$output = $this->createMock(IOutput::class);
		$output->method('info');

		$step->run($output);
		$this->addToAssertionCount(1);
	}
}
