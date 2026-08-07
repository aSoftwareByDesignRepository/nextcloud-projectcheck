<?php

declare(strict_types=1);

/**
 * Builds filtered list exports (CSV / JSON) for projects, time entries,
 * customers and employees.
 *
 * Controllers stay responsible for auth, visibility scoping and filter
 * parsing; this service only turns already-authorised row sets into a
 * downloadable payload. CSV uses the shared {@see Csv} dialect
 * (semicolon, quoted cells, decimal comma, formula sanitisation). JSON
 * uses machine-friendly numbers and ISO timestamps.
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Util\CostRateMode;
use OCA\ProjectCheck\Util\Csv;
use OCA\ProjectCheck\Util\Money;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IConfig;

final class ListExportService
{
	public const FORMAT_CSV = 'csv';
	public const FORMAT_JSON = 'json';

	/**
	 * Hard ceiling on exportable rows. Protects PHP memory and response time
	 * when a filter matches an enormous set. Clients get a clear 422 and must
	 * narrow filters — never a truncated silent file.
	 */
	public const MAX_EXPORT_ROWS = 100_000;

	/** Custom response headers the download client may read (same-origin). */
	public const HEADER_ROW_COUNT = 'X-ProjectCheck-Export-Row-Count';
	public const HEADER_FORMAT = 'X-ProjectCheck-Export-Format';

	/** UTF-8 BOM — written exactly once into CSV DataDownloadResponse bodies. */
	private const UTF8_BOM = "\xEF\xBB\xBF";

	/** @var IConfig */
	private $config;

	/** @var string */
	private $appName;

	public function __construct(IConfig $config, string $appName = 'projectcheck')
	{
		$this->config = $config;
		$this->appName = $appName;
	}

	/**
	 * Allowlist export formats. Anything unknown falls back to CSV so a
	 * crafted `?format=` never changes auth/scoping behaviour — only the
	 * serialiser.
	 */
	public function normalizeFormat(?string $format): string
	{
		$normalized = strtolower(trim((string)$format));
		return $normalized === self::FORMAT_JSON ? self::FORMAT_JSON : self::FORMAT_CSV;
	}

	public function getCurrencyCode(): string
	{
		$currencyCode = strtoupper(trim((string)$this->config->getAppValue($this->appName, 'currency', 'EUR')));
		if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
			return 'EUR';
		}
		return $currencyCode;
	}

	/**
	 * @param array<int, array{project?: mixed, budgetInfo?: mixed}> $enrichedProjects
	 * @param array<int, array<string, mixed>> $settlementInfoByProject keyed by project id (spec §11.3)
	 * @return array{format: string, content: string, filename: string, row_count: int, mime: string}
	 */
	public function exportProjects(array $enrichedProjects, string $format, array $settlementInfoByProject = []): array
	{
		$format = $this->normalizeFormat($format);
		$currency = $this->getCurrencyCode();
		$rows = [];

		foreach ($enrichedProjects as $item) {
			$project = $item['project'] ?? null;
			if (!$project instanceof Project) {
				continue;
			}
			$budgetInfo = is_array($item['budgetInfo'] ?? null) ? $item['budgetInfo'] : [];
			$settlement = $settlementInfoByProject[(int)$project->getId()] ?? [];
			$settlementCounters = is_array($settlement['counters'] ?? null) ? $settlement['counters'] : [];

			$totalBudget = (float)($budgetInfo['total_budget'] ?? ($project->getTotalBudget() ?? 0));
			$usedBudget = (float)($budgetInfo['used_budget'] ?? 0);
			$remainingBudget = (float)($budgetInfo['remaining_budget'] ?? $totalBudget);
			$consumption = (float)($budgetInfo['consumption_percentage'] ?? 0);
			$usedHours = (float)($budgetInfo['used_hours'] ?? 0);
			$remainingHours = (float)($budgetInfo['remaining_hours'] ?? 0);

			$rows[] = [
				'name' => (string)($project->getName() ?? ''),
				'customer' => (string)($project->getCustomerName() ?? ''),
				'project_type' => (string)$project->getProjectTypeDisplayName(),
				'status' => (string)($project->getStatus() ?? ''),
				'priority' => (string)($project->getPriority() ?? ''),
				'category' => (string)($project->getCategory() ?? ''),
				'tags' => (string)($project->getTags() ?? ''),
				'start_date' => $project->getStartDate() ? $project->getStartDate()->format('Y-m-d') : '',
				'end_date' => $project->getEndDate() ? $project->getEndDate()->format('Y-m-d') : '',
				'total_budget' => $totalBudget,
				'used_budget' => $usedBudget,
				'remaining_budget' => $remainingBudget,
				'budget_used_percent' => $consumption,
				'hours_logged' => $usedHours,
				'remaining_hours' => $remainingHours,
				'hourly_rate' => (float)($project->getHourlyRate() ?? 0),
				'pricing_mode' => $this->pricingModeLabel($project->getCostRateMode()),
				'short_description' => (string)($project->getShortDescription() ?? ''),
				'created_by' => (string)($project->getCreatedBy() ?? ''),
				'created_at' => $project->getCreatedAt() ? $project->getCreatedAt()->format('Y-m-d H:i') : '',
				'settlement_posture' => (string)($settlement['posture'] ?? ''),
				'open_hours' => (float)($settlementCounters['open_hours'] ?? 0),
				'invoiced_hours' => (float)($settlementCounters['invoiced_hours'] ?? 0),
				'paid_hours' => (float)($settlementCounters['paid_hours'] ?? 0),
				'excluded_hours' => (float)($settlementCounters['excluded_hours'] ?? 0),
				'outstanding_hours' => (float)($settlement['outstanding_hours'] ?? 0),
				'outstanding_amount' => (float)($settlement['outstanding_amount'] ?? 0),
				'paid_percent' => !empty($settlement['progress']['has_chargeable'])
					? (int)($settlement['progress']['paid_percent'] ?? 0)
					: '',
				'billed_percent' => !empty($settlement['progress']['has_chargeable'])
					? (int)($settlement['progress']['billed_percent'] ?? 0)
					: '',
			];
		}

		$headers = [
			'Name',
			'Customer',
			'Project Type',
			'Status',
			'Priority',
			'Category',
			'Tags',
			'Start Date',
			'End Date',
			'Total Budget (' . $currency . ')',
			'Used Budget (' . $currency . ')',
			'Remaining Budget (' . $currency . ')',
			'Budget Used (%)',
			'Hours Logged',
			'Remaining Hours',
			'Hourly Rate (' . $currency . ')',
			'Pricing Mode',
			'Short Description',
			'Created By',
			'Created At',
			'Settlement Posture',
			'Open Hours',
			'Invoiced Hours',
			'Paid Hours',
			'Not Billable Hours',
			'Outstanding Hours',
			'Outstanding Amount (' . $currency . ')',
			'Paid (%)',
			'Invoiced or paid (%)',
		];

		$csvRowMapper = static function (array $row): array {
			return [
				$row['name'],
				$row['customer'],
				$row['project_type'],
				$row['status'],
				$row['priority'],
				$row['category'],
				$row['tags'],
				$row['start_date'],
				$row['end_date'],
				self::csvDecimal((float)$row['total_budget'], 2),
				self::csvDecimal((float)$row['used_budget'], 2),
				self::csvDecimal((float)$row['remaining_budget'], 2),
				self::csvDecimal((float)$row['budget_used_percent'], 1),
				self::csvDecimal((float)$row['hours_logged'], 2),
				self::csvDecimal((float)$row['remaining_hours'], 2),
				self::csvDecimal((float)$row['hourly_rate'], 2),
				$row['pricing_mode'],
				$row['short_description'],
				$row['created_by'],
				$row['created_at'],
				$row['settlement_posture'],
				self::csvDecimal((float)$row['open_hours'], 2),
				self::csvDecimal((float)$row['invoiced_hours'], 2),
				self::csvDecimal((float)$row['paid_hours'], 2),
				self::csvDecimal((float)$row['excluded_hours'], 2),
				self::csvDecimal((float)$row['outstanding_hours'], 2),
				self::csvDecimal((float)$row['outstanding_amount'], 2),
				$row['paid_percent'] === '' ? '' : (string)(int)$row['paid_percent'],
				$row['billed_percent'] === '' ? '' : (string)(int)$row['billed_percent'],
			];
		};

		return $this->pack('projects', $format, $headers, $rows, $csvRowMapper, $currency);
	}

	/**
	 * @param array<int, array<string, mixed>> $timeEntries
	 * @return array{format: string, content: string, filename: string, row_count: int, mime: string}
	 */
	public function exportTimeEntries(array $timeEntries, string $format): array
	{
		$format = $this->normalizeFormat($format);
		$currency = $this->getCurrencyCode();
		$rows = [];

		foreach ($timeEntries as $entry) {
			$timeEntry = $entry['timeEntry'] ?? null;
			// Entities expose getters via __call, so method_exists() is unreliable.
			if (!$timeEntry instanceof TimeEntry) {
				continue;
			}

			$hours = (float)$timeEntry->getHours();
			$rate = (float)$timeEntry->getHourlyRate();
			$totalAmount = Money::asFloat(
				Money::mul($hours, $rate, Money::MONEY_SCALE),
				Money::MONEY_SCALE
			);

			$projectTypeDisplayName = (string)($entry['project_type_display_name'] ?? $entry['project_type'] ?? 'Client Project');

			$rows[] = [
				'date' => $timeEntry->getDate() ? $timeEntry->getDate()->format('Y-m-d') : '',
				'project' => (string)($entry['projectName'] ?? 'Unknown Project'),
				'customer' => (string)($entry['customerName'] ?? ''),
				'project_type' => $projectTypeDisplayName,
				'description' => (string)($timeEntry->getDescription() ?? ''),
				'hours' => $hours,
				'hourly_rate' => $rate,
				'total_amount' => $totalAmount,
				'user' => (string)($entry['userDisplayName'] ?? $timeEntry->getUserId() ?? ''),
				'created_at' => $timeEntry->getCreatedAt() ? $timeEntry->getCreatedAt()->format('Y-m-d H:i') : '',
				'billing_status' => $timeEntry->getBillingStatus(),
				'billed_at' => $timeEntry->getBilledAt() ? $timeEntry->getBilledAt()->format('Y-m-d H:i') : '',
				'paid_at' => $timeEntry->getPaidAt() ? $timeEntry->getPaidAt()->format('Y-m-d H:i') : '',
			];
		}

		$headers = [
			'Date',
			'Project',
			'Customer',
			'Project Type',
			'Description',
			'Hours',
			'Hourly Rate (' . $currency . ')',
			'Total Amount (' . $currency . ')',
			'User',
			'Created At',
			'Billing Status',
			'Billed At',
			'Paid At',
		];

		$csvRowMapper = static function (array $row): array {
			return [
				$row['date'],
				$row['project'],
				$row['customer'],
				$row['project_type'],
				$row['description'],
				self::csvDecimal((float)$row['hours'], 2),
				self::csvDecimal((float)$row['hourly_rate'], 2),
				self::csvDecimal((float)$row['total_amount'], 2),
				$row['user'],
				$row['created_at'],
				$row['billing_status'],
				$row['billed_at'],
				$row['paid_at'],
			];
		};

		return $this->pack('time_entries', $format, $headers, $rows, $csvRowMapper, $currency);
	}

	/**
	 * @param array<int, Customer> $customers
	 * @param array<int, array<string, mixed>> $settlementByCustomer keyed by customer id (spec §11.3)
	 * @return array{format: string, content: string, filename: string, row_count: int, mime: string}
	 */
	public function exportCustomers(array $customers, string $format, array $settlementByCustomer = []): array
	{
		$format = $this->normalizeFormat($format);
		$currency = $this->getCurrencyCode();
		$rows = [];

		foreach ($customers as $customer) {
			if (!$customer instanceof Customer) {
				continue;
			}
			$cid = (int)$customer->getId();
			$settlement = $settlementByCustomer[$cid] ?? [];
			$rows[] = [
				'name' => (string)($customer->getName() ?? ''),
				'email' => (string)($customer->getEmail() ?? ''),
				'phone' => (string)($customer->getPhone() ?? ''),
				'address' => (string)($customer->getAddress() ?? ''),
				'contact_person' => (string)($customer->getContactPerson() ?? ''),
				'project_count' => (int)($customer->getProjectCount() ?? 0),
				'settlement_posture' => (string)($settlement['posture'] ?? ''),
				'outstanding_hours' => (float)($settlement['outstanding_hours'] ?? 0),
				'outstanding_amount' => (float)($settlement['outstanding_amount'] ?? 0),
				'paid_percent' => !empty($settlement['progress']['has_chargeable'])
					? (int)($settlement['progress']['paid_percent'] ?? 0)
					: '',
				'billed_percent' => !empty($settlement['progress']['has_chargeable'])
					? (int)($settlement['progress']['billed_percent'] ?? 0)
					: '',
				'created_by' => (string)($customer->getCreatedBy() ?? ''),
				'created_at' => $customer->getCreatedAt() ? $customer->getCreatedAt()->format('Y-m-d H:i') : '',
				'updated_at' => $customer->getUpdatedAt() ? $customer->getUpdatedAt()->format('Y-m-d H:i') : '',
			];
		}

		$headers = [
			'Name',
			'Email',
			'Phone',
			'Address',
			'Contact Person',
			'Projects',
			'Settlement',
			'Outstanding Hours',
			'Outstanding Amount (' . $currency . ')',
			'Paid (%)',
			'Invoiced or paid (%)',
			'Created By',
			'Created At',
			'Updated At',
		];

		$csvRowMapper = static function (array $row): array {
			return [
				$row['name'],
				$row['email'],
				$row['phone'],
				$row['address'],
				$row['contact_person'],
				(string)$row['project_count'],
				$row['settlement_posture'],
				self::csvDecimal((float)$row['outstanding_hours'], 2),
				self::csvDecimal((float)$row['outstanding_amount'], 2),
				$row['paid_percent'] === '' ? '' : (string)(int)$row['paid_percent'],
				$row['billed_percent'] === '' ? '' : (string)(int)$row['billed_percent'],
				$row['created_by'],
				$row['created_at'],
				$row['updated_at'],
			];
		};

		return $this->pack('customers', $format, $headers, $rows, $csvRowMapper, $currency);
	}

	/**
	 * @param array<int, array<string, mixed>> $employeeStats Full (unpaginated) comparison stats
	 * @return array{format: string, content: string, filename: string, row_count: int, mime: string}
	 */
	public function exportEmployees(array $employeeStats, string $format): array
	{
		$format = $this->normalizeFormat($format);
		$currency = $this->getCurrencyCode();
		$rows = [];
		$rank = 0;

		foreach ($employeeStats as $employee) {
			if (!is_array($employee)) {
				continue;
			}
			$rank++;
			$userId = (string)($employee['user_id'] ?? '');
			$displayName = (string)($employee['user_display_name'] ?? $userId);
			$totalHours = (float)($employee['total_hours'] ?? 0);
			$totalCost = (float)($employee['total_cost'] ?? 0);
			$avgRate = array_key_exists('avg_hourly_rate', $employee)
				? (float)$employee['avg_hourly_rate']
				: ($totalHours > 0.0 ? ($totalCost / $totalHours) : 0.0);

			$rows[] = [
				'rank' => $rank,
				'employee' => $displayName,
				'user_id' => $userId,
				'total_hours' => $totalHours,
				'total_revenue' => $totalCost,
				'avg_hourly_rate' => $avgRate,
			];
		}

		$headers = [
			'Rank',
			'Employee',
			'User ID',
			'Total Hours',
			'Total Revenue (' . $currency . ')',
			'Avg. Hourly Rate (' . $currency . ')',
		];

		$csvRowMapper = static function (array $row): array {
			return [
				(string)$row['rank'],
				$row['employee'],
				$row['user_id'],
				self::csvDecimal((float)$row['total_hours'], 1),
				self::csvDecimal((float)$row['total_revenue'], 2),
				self::csvDecimal((float)$row['avg_hourly_rate'], 2),
			];
		};

		return $this->pack('employees', $format, $headers, $rows, $csvRowMapper, $currency);
	}

	/**
	 * Whether a row set is within the export ceiling.
	 */
	public function exceedsMaxRows(int $rowCount): bool
	{
		return $rowCount > self::MAX_EXPORT_ROWS;
	}

	/**
	 * Turn a packed export into a direct file download.
	 *
	 * CSV gets a single UTF-8 BOM here (never on the client) so Excel opens
	 * umlauts correctly without a double-BOM. Cache headers forbid shared
	 * caches from storing export bodies that may contain PII.
	 *
	 * @param array{format: string, content: string, filename: string, row_count: int, mime: string} $packed
	 */
	public function toDownloadResponse(array $packed): DataDownloadResponse
	{
		$format = $this->normalizeFormat($packed['format'] ?? self::FORMAT_CSV);
		$content = (string)($packed['content'] ?? '');
		$filename = $this->sanitizeDownloadFilename(
			(string)($packed['filename'] ?? ('export.' . ($format === self::FORMAT_JSON ? 'json' : 'csv'))),
			$format
		);
		$mime = (string)($packed['mime'] ?? ($format === self::FORMAT_JSON
			? 'application/json; charset=utf-8'
			: 'text/csv; charset=utf-8'));
		$rowCount = max(0, (int)($packed['row_count'] ?? 0));

		if ($format === self::FORMAT_CSV && !str_starts_with($content, self::UTF8_BOM)) {
			$content = self::UTF8_BOM . $content;
		}

		$response = new DataDownloadResponse($content, $filename, $mime);
		$response->addHeader(self::HEADER_ROW_COUNT, (string)$rowCount);
		$response->addHeader(self::HEADER_FORMAT, $format);
		$response->addHeader('Cache-Control', 'no-store, private');
		$response->addHeader('Pragma', 'no-cache');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		return $response;
	}

	/**
	 * @param list<string> $headers
	 * @param list<array<string, mixed>> $rows
	 * @param callable(array<string, mixed>): list<string|int|float|null> $csvRowMapper
	 * @return array{format: string, content: string, filename: string, row_count: int, mime: string}
	 */
	private function pack(
		string $basename,
		string $format,
		array $headers,
		array $rows,
		callable $csvRowMapper,
		string $currency
	): array {
		$timestamp = date('Y-m-d_H-i-s');
		$rowCount = count($rows);
		$safeBase = preg_replace('/[^a-z0-9_-]+/i', '_', $basename) ?: 'export';

		if ($format === self::FORMAT_JSON) {
			// Compact JSON — pretty-print would roughly double payload size.
			$content = json_encode([
				'exported_at' => (new \DateTimeImmutable('now'))->format('c'),
				'record_count' => $rowCount,
				'currency' => $currency,
				'records' => $rows,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if ($content === false) {
				throw new \RuntimeException('Failed to encode export as JSON');
			}

			return [
				'format' => self::FORMAT_JSON,
				'content' => $content,
				'filename' => $safeBase . '_' . $timestamp . '.json',
				'row_count' => $rowCount,
				'mime' => 'application/json; charset=utf-8',
			];
		}

		// Build CSV via a memory stream so we do not keep N intermediate
		// concatenated copies of the growing string in userland.
		$fp = fopen('php://temp', 'r+b');
		if ($fp === false) {
			throw new \RuntimeException('Failed to open export buffer');
		}
		try {
			fwrite($fp, Csv::line($headers));
			foreach ($rows as $row) {
				fwrite($fp, Csv::line($csvRowMapper($row)));
			}
			rewind($fp);
			$csv = stream_get_contents($fp);
			if ($csv === false) {
				throw new \RuntimeException('Failed to read export buffer');
			}
		} finally {
			fclose($fp);
		}

		return [
			'format' => self::FORMAT_CSV,
			'content' => $csv,
			'filename' => $safeBase . '_' . $timestamp . '.csv',
			'row_count' => $rowCount,
			'mime' => 'text/csv; charset=utf-8',
		];
	}

	/**
	 * Keep Content-Disposition filenames free of quotes/slashes so the
	 * Nextcloud DownloadResponse header stays well-formed.
	 */
	private function sanitizeDownloadFilename(string $filename, string $format): string
	{
		$base = basename(str_replace(["\0", '\\'], '', $filename));
		$base = preg_replace('/[^\w.\-()+ ]+/', '_', $base) ?? '';
		$base = ltrim($base, '.');
		if ($base === '') {
			$base = 'export.' . ($format === self::FORMAT_JSON ? 'json' : 'csv');
		}
		return $base;
	}

	private function pricingModeLabel(string $mode): string
	{
		return match (CostRateMode::normalize($mode)) {
			CostRateMode::EMPLOYEE => 'Rate per employee',
			CostRateMode::PROJECT_MEMBER => 'Rate per person on this project',
			default => 'One rate for the whole project',
		};
	}

	private static function csvDecimal(float $value, int $decimals): string
	{
		return number_format($value, $decimals, ',', '');
	}
}
