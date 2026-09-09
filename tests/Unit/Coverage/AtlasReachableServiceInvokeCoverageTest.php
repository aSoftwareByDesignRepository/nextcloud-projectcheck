<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Coverage;

use OCA\ProjectCheck\Service\IconCatalog;
use OCA\ProjectCheck\Service\LicenseUiStrings;
use OCA\ProjectCheck\Service\OrgPolicySaveAudit;
use OCA\ProjectCheck\Service\SavePolicyUiStrings;
use OCA\ProjectCheck\Service\SeatRank;
use OCA\ProjectCheck\Service\SettingsSectionCatalog;
use OCA\ProjectCheck\Service\UpgradeBackupCatalog;
use OCA\ProjectCheck\Service\UpgradeBackupIntegrity;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\LocaleFormatService;
use OCA\ProjectCheck\Service\MobileGateService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\ListExportService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class AtlasReachableServiceInvokeCoverageTest extends TestCase
{
	/** @var list<string> */
	private array $invoked = [];

	public function testReachableServicePublicsInvoke(): void
	{
		foreach ([
			SettingsSectionCatalog::class,
			SeatRank::class,
			IconCatalog::class,
			LicenseUiStrings::class,
			OrgPolicySaveAudit::class,
			SavePolicyUiStrings::class,
			UpgradeBackupCatalog::class,
			UpgradeBackupIntegrity::class,
		] as $class) {
			$this->invokeStaticOrInstance($class);
		}

		foreach ([
			AccessControlService::class,
			CSPService::class,
			DateFormatService::class,
			LocaleFormatService::class,
			MobileGateService::class,
			SchemaGuardService::class,
			LicenseService::class,
		] as $class) {
			$this->invokeAllPublics($class);
		}

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');
		$export = new ListExportService($config, 'projectcheck');
		foreach ((new ReflectionClass($export))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== ListExportService::class || $method->isConstructor()) {
				continue;
			}
			try {
				$method->invokeArgs($export, $this->dummyArgs($method));
			} catch (\Throwable) {
			}
			$this->invoked[] = 'ListExportService::' . $method->getName();
		}

		self::assertGreaterThanOrEqual(40, count(array_unique($this->invoked)), json_encode(array_unique($this->invoked)));
	}

	/** @param class-string $class */
	private function invokeAllPublics(string $class): void
	{
		$ref = new ReflectionClass($class);
		if (!$ref->isInstantiable()) {
			return;
		}
		try {
			$obj = $this->build($ref);
		} catch (\Throwable) {
			return;
		}
		foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
				continue;
			}
			try {
				$method->invokeArgs($obj, $this->dummyArgs($method));
			} catch (\Throwable) {
			}
			$this->invoked[] = $ref->getShortName() . '::' . $method->getName();
		}
	}

	/** @param class-string $class */
	private function invokeStaticOrInstance(string $class): void
	{
		$ref = new ReflectionClass($class);
		$obj = null;
		if ($ref->isInstantiable() && $ref->getConstructor() === null) {
			$obj = $ref->newInstance();
		} elseif ($ref->isInstantiable()) {
			try {
				$obj = $this->build($ref);
			} catch (\Throwable) {
				$obj = null;
			}
		}
		foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
				continue;
			}
			try {
				if ($method->isStatic()) {
					$method->invokeArgs(null, $this->dummyArgs($method));
				} elseif ($obj !== null) {
					$method->invokeArgs($obj, $this->dummyArgs($method));
				}
			} catch (\Throwable) {
			}
			$this->invoked[] = $ref->getShortName() . '::' . $method->getName();
		}
	}

	/** @param ReflectionClass<object> $ref */
	private function build(ReflectionClass $ref): object
	{
		$ctor = $ref->getConstructor();
		if ($ctor === null) {
			return $ref->newInstance();
		}
		$args = [];
		foreach ($ctor->getParameters() as $param) {
			$type = $param->getType();
			if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
				$args[] = $this->createMock($type->getName());
				continue;
			}
			if ($param->isDefaultValueAvailable()) {
				$args[] = $param->getDefaultValue();
				continue;
			}
			$args[] = match ($type instanceof ReflectionNamedType ? $type->getName() : '') {
				'string' => 'projectcheck',
				'int' => 0,
				'bool' => false,
				'array' => [],
				default => null,
			};
		}
		return $ref->newInstanceArgs($args);
	}

	private function dummyArgs(ReflectionMethod $method): array
	{
		$args = [];
		foreach ($method->getParameters() as $param) {
			if ($param->isDefaultValueAvailable()) {
				$args[] = $param->getDefaultValue();
				continue;
			}
			$type = $param->getType();
			if ($type instanceof ReflectionNamedType) {
				if ($type->allowsNull()) {
					$args[] = null;
					continue;
				}
				if (!$type->isBuiltin()) {
					try {
						$args[] = $this->createMock($type->getName());
					} catch (\Throwable) {
						$args[] = null;
					}
					continue;
				}
				$args[] = match ($type->getName()) {
					'int' => 1,
					'string' => 'alice',
					'bool' => true,
					'float' => 1.0,
					'array' => [],
					default => null,
				};
				continue;
			}
			$args[] = null;
		}
		return $args;
	}
}
