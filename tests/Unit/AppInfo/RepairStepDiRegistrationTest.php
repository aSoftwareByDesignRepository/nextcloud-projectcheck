<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\AppInfo;

use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/** @see arbeitszeitcheck RepairStepDiRegistrationTest — guards occ upgrade repair-step DI. */
final class RepairStepDiRegistrationTest extends TestCase
{
	public static function repairStepClassesFromInfoXml(): array
	{
		$infoPath = dirname(__DIR__, 3) . '/appinfo/info.xml';
		$contents = file_get_contents($infoPath);
		if ($contents === false) {
			throw new \RuntimeException('Could not read appinfo/info.xml at ' . $infoPath);
		}
		$xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET);
		if ($xml === false) {
			throw new \RuntimeException('Could not parse appinfo/info.xml at ' . $infoPath);
		}

		$classes = [];
		foreach ($xml->{'repair-steps'}->children() as $phase) {
			foreach ($phase->step as $step) {
				$classes[] = (string)$step;
			}
		}

		$unique = array_values(array_unique($classes));
		return array_combine($unique, array_map(static fn (string $class): array => [$class], $unique));
	}

	/** @dataProvider repairStepClassesFromInfoXml */
	public function testRepairStepIsRegisteredInApplication(string $class): void
	{
		$source = file_get_contents(dirname(__DIR__, 3) . '/lib/AppInfo/Application.php');
		$this->assertIsString($source);

		$short = (new ReflectionClass($class))->getShortName();
		$this->assertMatchesRegularExpression(
			'/registerService\((?:\\\\?' . preg_quote($short, '/') . '|' . preg_quote($class, '/') . ')::class/',
			$source,
			$class . ' must be registered in Application.php (occ upgrade resolves repair steps from the container)',
		);
	}

	/** @dataProvider repairStepClassesFromInfoXml */
	public function testRepairStepFactoryPassesEnoughConstructorArguments(string $class): void
	{
		$source = file_get_contents(dirname(__DIR__, 3) . '/lib/AppInfo/Application.php');
		$this->assertIsString($source);

		$short = (new ReflectionClass($class))->getShortName();
		$pattern = '/registerService\(' . preg_quote($short, '/') . '::class,\s*function\s*\(\$c\)[^{]*\{\s*return new ' . preg_quote($short, '/') . '\((.*?)\);/s';
		if (!preg_match($pattern, $source, $matches)) {
			$this->fail($class . ' factory block not found in Application.php');
		}

		$required = (new ReflectionClass($class))->getConstructor()?->getNumberOfRequiredParameters() ?? 0;
		$passed = substr_count($matches[1], '$c->query(');

		$this->assertGreaterThanOrEqual(
			$required,
			$passed,
			sprintf('%s requires %d constructor argument(s) but Application.php passes %d', $class, $required, $passed),
		);
	}

	public function testEnsureSchemaRepairStepsWireIConfigWhenRequired(): void
	{
		$source = file_get_contents(dirname(__DIR__, 3) . '/lib/AppInfo/Application.php');
		$this->assertIsString($source);

		foreach (array_keys(self::repairStepClassesFromInfoXml()) as $class) {
			$ref = new ReflectionClass($class);
			if (!str_starts_with($ref->getShortName(), 'Ensure')) {
				continue;
			}
			$needsConfig = false;
			foreach ($ref->getConstructor()?->getParameters() ?? [] as $param) {
				$type = $param->getType();
				if ($type instanceof ReflectionNamedType && $type->getName() === IConfig::class) {
					$needsConfig = true;
					break;
				}
			}
			if (!$needsConfig) {
				continue;
			}

			$short = $ref->getShortName();
			$this->assertMatchesRegularExpression(
				'/registerService\(' . preg_quote($short, '/') . '::class,.*?IConfig::class/s',
				$source,
				$short . ' must receive IConfig in its Application.php factory',
			);
		}
	}
}
