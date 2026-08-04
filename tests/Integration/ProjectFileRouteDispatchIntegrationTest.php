<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Controller\ProjectFileController;
use Test\TestCase;

/**
 * End-to-end routing proof for project file URLs after GitHub #8:
 * HTTP paths still hit ProjectFileController (not ProjectfileController).
 *
 * @group integration
 */
final class ProjectFileRouteDispatchIntegrationTest extends TestCase
{
	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud is not bootstrapped');
		}
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 */
	public static function fileUrls(): array
	{
		return [
			['GET', '/apps/projectcheck/projects/1/files'],
			['POST', '/apps/projectcheck/projects/1/files'],
			['GET', '/apps/projectcheck/projects/1/files/9/download'],
			['DELETE', '/apps/projectcheck/projects/1/files/9'],
			['POST', '/apps/projectcheck/projects/1/files/9/delete'],
		];
	}

	/** @dataProvider fileUrls */
	public function testFileUrlDispatchesToProjectFileController(string $method, string $url): void
	{
		$out = [];
		$code = 0;
		exec(
			'cd /var/www/html && php occ router:match --method=' . escapeshellarg($method) . ' '
			. escapeshellarg($url) . ' 2>&1',
			$out,
			$code
		);
		$joined = implode("\n", $out);

		$this->assertSame(0, $code, $joined);
		$this->assertStringContainsString('ProjectFileController', $joined);
		$this->assertStringNotContainsString('| ProjectfileController |', $joined);
		$this->assertMatchesRegularExpression('/projectcheck\\.project_file\\./i', $joined);
	}

	public function testProjectFileControllerResolvesAndCompactNameFailsFresh(): void
	{
		$container = \OC::$server->getRegisteredAppContainer(Application::APP_ID);
		$controller = $container->query(ProjectFileController::class);
		$this->assertInstanceOf(ProjectFileController::class, $controller);

		$out = [];
		$code = 0;
		exec(
			'php -r ' . escapeshellarg(
				'require "/var/www/html/lib/base.php";'
				. '$c=OC::$server->getRegisteredAppContainer("projectcheck");'
				. 'try{$c->query("OCA\\\\ProjectCheck\\\\Controller\\\\ProjectfileController");echo "BAD";}'
				. 'catch(Throwable $e){echo (str_contains($e->getMessage(),"ProjectfileController")?"GOOD":"OTHER");}'
			) . ' 2>&1',
			$out,
			$code
		);
		$this->assertSame('GOOD', trim(implode("\n", $out)));
	}
}
