<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Service\ListExportService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class ListExportServiceTest extends TestCase
{
	private ListExportService $service;
	private IConfig $config;

	protected function setUp(): void
	{
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string =>
				($app === 'projectcheck' && $key === 'currency') ? 'EUR' : $default
		);
		$this->service = new ListExportService($this->config, 'projectcheck');
	}

	public function testNormalizeFormatAllowlistsJsonAndDefaultsToCsv(): void
	{
		$this->assertSame('csv', $this->service->normalizeFormat(null));
		$this->assertSame('csv', $this->service->normalizeFormat(''));
		$this->assertSame('csv', $this->service->normalizeFormat('CSV'));
		$this->assertSame('csv', $this->service->normalizeFormat('xlsx'));
		$this->assertSame('json', $this->service->normalizeFormat('JSON'));
		$this->assertSame('json', $this->service->normalizeFormat(' json '));
	}

	public function testExportProjectsCsvSanitizesFormulasAndUsesDecimalComma(): void
	{
		$project = new Project();
		$project->setName('=HYPERLINK("http://evil")');
		$project->setCustomerName('+cmd');
		$project->setStatus('Active');
		$project->setPriority('High');
		$project->setProjectType('client');
		$project->setHourlyRate(100.5);
		$project->setTotalBudget(2000.0);

		$packed = $this->service->exportProjects([[
			'project' => $project,
			'budgetInfo' => [
				'total_budget' => 2000.0,
				'used_budget' => 500.25,
				'remaining_budget' => 1499.75,
				'consumption_percentage' => 25.0,
				'used_hours' => 5.0,
				'remaining_hours' => 15.0,
			],
		]], 'csv');

		$this->assertSame('csv', $packed['format']);
		$this->assertSame(1, $packed['row_count']);
		$this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\"", $packed['content']);
		$this->assertStringContainsString("\"'+cmd\"", $packed['content']);
		$this->assertStringContainsString('"2000,00";"500,25";"1499,75";"25,0";"5,00";"15,00";"100,50"', $packed['content']);
		$this->assertStringStartsNotWith("\xEF\xBB\xBF", $packed['content']);
	}

	public function testExportProjectsJsonUsesNumericValues(): void
	{
		$project = new Project();
		$project->setName('Alpha');
		$project->setStatus('Active');
		$project->setProjectType('client');
		$project->setHourlyRate(80.0);

		$packed = $this->service->exportProjects([[
			'project' => $project,
			'budgetInfo' => [
				'total_budget' => 800.0,
				'used_budget' => 80.0,
				'remaining_budget' => 720.0,
				'consumption_percentage' => 10.0,
				'used_hours' => 1.0,
				'remaining_hours' => 9.0,
			],
		]], 'json');

		$decoded = json_decode($packed['content'], true);
		$this->assertSame(1, $decoded['record_count']);
		$this->assertEqualsWithDelta(800.0, (float)$decoded['records'][0]['total_budget'], 0.0001);
		$this->assertSame('Alpha', $decoded['records'][0]['name']);
	}

	public function testExportCustomersAndEmployees(): void
	{
		$customer = new Customer();
		$customer->setId(1);
		$customer->setName('ACME');
		$customer->setEmail('a@example.com');
		$customer->setProjectCount(3);
		$customer->setCreatedBy('alice');
		$customer->setCreatedAt(new \DateTime('2026-01-01 10:00:00'));
		$customer->setUpdatedAt(new \DateTime('2026-01-02 11:00:00'));

		$customerPacked = $this->service->exportCustomers([$customer], 'csv', [
			1 => [
				'posture' => 'partial',
				'outstanding_hours' => 4.5,
				'outstanding_amount' => 450.0,
			],
		]);
		$this->assertSame(1, $customerPacked['row_count']);
		$this->assertStringContainsString('"ACME";"a@example.com"', $customerPacked['content']);
		$this->assertStringContainsString('Settlement', $customerPacked['content']);
		$this->assertStringContainsString('partial', $customerPacked['content']);
		$this->assertStringContainsString('4,50', $customerPacked['content']);

		$employeePacked = $this->service->exportEmployees([[
			'user_id' => 'bob',
			'user_display_name' => 'Bob Builder',
			'total_hours' => 12.5,
			'total_cost' => 1250.0,
			'avg_hourly_rate' => 100.0,
		]], 'json');

		$decoded = json_decode($employeePacked['content'], true);
		$this->assertSame(1, $decoded['record_count']);
		$this->assertSame('Bob Builder', $decoded['records'][0]['employee']);
		$this->assertSame(1, $decoded['records'][0]['rank']);
	}

	public function testExportTimeEntriesCsv(): void
	{
		$entry = new TimeEntry();
		$entry->setDate(new \DateTime('2026-03-01'));
		$entry->setDescription('Work');
		$entry->setHours(2.5);
		$entry->setHourlyRate(100.0);
		$entry->setUserId('alice');
		$entry->setCreatedAt(new \DateTime('2026-03-01 12:00:00'));

		$packed = $this->service->exportTimeEntries([[
			'timeEntry' => $entry,
			'projectName' => 'P1',
			'customerName' => 'C1',
			'project_type_display_name' => 'Client Project',
			'userDisplayName' => 'Alice',
		]], 'csv');

		$this->assertSame(1, $packed['row_count']);
		$this->assertStringContainsString('"2026-03-01";"P1";"C1";"Client Project";"Work";"2,50";"100,00";"250,00";"Alice"', $packed['content']);
	}

	public function testToDownloadResponseAddsBomAndSecurityHeaders(): void
	{
		$csvResponse = $this->service->toDownloadResponse([
			'format' => 'csv',
			'content' => "\"Name\"\n\"Alpha\"\n",
			'filename' => 'projects_2026-01-01_00-00-00.csv',
			'row_count' => 1,
			'mime' => 'text/csv; charset=utf-8',
		]);

		$this->assertInstanceOf(\OCP\AppFramework\Http\DataDownloadResponse::class, $csvResponse);
		$body = (string)$csvResponse->render();
		$this->assertStringStartsWith("\xEF\xBB\xBF", $body);
		$this->assertSame(1, substr_count($body, "\xEF\xBB\xBF"), 'BOM must appear exactly once');
		$headers = $csvResponse->getHeaders();
		$this->assertSame('1', $headers['X-ProjectCheck-Export-Row-Count'] ?? null);
		$this->assertSame('csv', $headers['X-ProjectCheck-Export-Format'] ?? null);
		$this->assertSame('no-store, private', $headers['Cache-Control'] ?? null);
		$this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);

		$jsonResponse = $this->service->toDownloadResponse([
			'format' => 'json',
			'content' => '{}',
			'filename' => 'a.json',
			'row_count' => 0,
			'mime' => 'application/json; charset=utf-8',
		]);
		$this->assertSame('{}', $jsonResponse->render());
		$this->assertStringNotContainsString("\xEF\xBB\xBF", $jsonResponse->render());
		$this->assertSame('json', $jsonResponse->getHeaders()['X-ProjectCheck-Export-Format'] ?? null);
	}

	public function testExceedsMaxRows(): void
	{
		$this->assertFalse($this->service->exceedsMaxRows(0));
		$this->assertFalse($this->service->exceedsMaxRows(ListExportService::MAX_EXPORT_ROWS));
		$this->assertTrue($this->service->exceedsMaxRows(ListExportService::MAX_EXPORT_ROWS + 1));
	}
}
