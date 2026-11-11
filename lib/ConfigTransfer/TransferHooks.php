<?php

declare(strict_types=1);

/**
 * ProjectCheck wiring for the shared transfer engine.
 *
 *  - requireTransferAdmin gates on the app's real door-admin role
 *    (AccessControlService::isAppAdmin — system admins + configured app
 *    admins).
 *  - validateValue/normalizeValue run the SAME rules the settings write
 *    path applies in AppConfigController::saveAppDefaults /
 *    AccessControlService (validator parity, NN-08 — import grants no
 *    bypass). The settings API silently skips invalid values; the
 *    transfer pipeline rejects them loudly instead.
 *  - Access door is app-local appconfig (no platform restriction mirror)
 *    → no mirrored keys; writeMirrored stays a NO_MIRRORED_KEYS guard
 *    (carecheck precedent).
 *  - createSafetySnapshot delegates to UpgradeBackupService for the
 *    mandatory pre-replace snapshot (FS-2).
 *  - auditEvent logs to LoggerInterface — ProjectCheck has no generic
 *    audit sink (ActivityService is typed domain events only).
 */

namespace OCA\ProjectCheck\ConfigTransfer;

use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\UpgradeBackupService;
use Psr\Log\LoggerInterface;

final class TransferHooks implements ConfigTransferHooks
{
	private const DOOR_USER_LIST_KEYS = [
		'access_allowed_user_ids',
		'app_admin_user_ids',
	];

	private const DOOR_GROUP_LIST_KEYS = [
		'access_allowed_group_ids',
	];

	private const INT_RANGE_KEYS = [
		'budget_warning_threshold' => [0, 100],
		'budget_critical_threshold' => [0, 100],
		'items_per_page' => [5, 100],
		'max_projects_per_user' => [1, PHP_INT_MAX],
		'retention_time_entries_years' => [0, PHP_INT_MAX],
	];

	private const YES_NO_KEYS = [
		'enable_time_tracking',
		'enable_customer_management',
		'enable_budget_tracking',
	];

	private const PROJECT_STATUSES = ['Active', 'On Hold', 'Completed', 'Cancelled'];

	private const PROJECT_PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

	public function __construct(
		private readonly AccessControlService $access,
		private readonly UpgradeBackupService $backups,
		private readonly LoggerInterface $logger,
	) {
	}

	public function requireTransferAdmin(string $uid): void
	{
		if ($uid === '' || !$this->access->isAppAdmin($uid)) {
			throw new PermissionDeniedException('administer', 'projectcheck config transfer');
		}
	}

	public function validateValue(string $key, string $value): ?string
	{
		try {
			$this->normalize($key, $value);
			return null;
		} catch (\InvalidArgumentException $e) {
			return $e->getMessage() !== '' ? $e->getMessage() : 'INVALID_VALUE';
		} catch (\JsonException) {
			return 'INVALID_JSON';
		} catch (\Throwable) {
			return 'INVALID_VALUE';
		}
	}

	public function normalizeValue(string $key, string $value): string
	{
		try {
			return $this->normalize($key, $value);
		} catch (\Throwable) {
			return $value; // validateValue already rejected bad input
		}
	}

	/**
	 * Same per-key rules AppConfigController::saveAppDefaults /
	 * saveAccessPolicy apply — a value the settings API would refuse is
	 * refused here, and the persisted form is byte-identical (NN-08).
	 */
	private function normalize(string $key, string $value): string
	{
		return match (true) {
			$key === 'access_restriction_enabled' => $this->normalizeBool01($value),
			$key === 'currency' => preg_match('/^[A-Z]{3}$/', strtoupper(trim($value))) === 1
				? strtoupper(trim($value))
				: throw new \InvalidArgumentException('INVALID_CURRENCY'),
			$key === 'default_hourly_rate' => is_numeric($value) && (float)$value >= 0
				? number_format((float)$value, 2, '.', '')
				: throw new \InvalidArgumentException('INVALID_RATE'),
			$key === 'default_project_status' => in_array($value, self::PROJECT_STATUSES, true)
				? $value
				: throw new \InvalidArgumentException('INVALID_STATUS'),
			$key === 'default_project_priority' => in_array($value, self::PROJECT_PRIORITIES, true)
				? $value
				: throw new \InvalidArgumentException('INVALID_PRIORITY'),
			isset(self::INT_RANGE_KEYS[$key]) => $this->normalizeIntRange($key, $value),
			in_array($key, self::YES_NO_KEYS, true) => $this->normalizeYesNo($value),
			in_array($key, self::DOOR_USER_LIST_KEYS, true),
				in_array($key, self::DOOR_GROUP_LIST_KEYS, true) => $this->normalizeIdList($value),
			default => strlen($value) <= 65536
				? $value
				: throw new \InvalidArgumentException('TOO_LONG'),
		};
	}

	/** Door flag is stored as '0'/'1'; the save API also accepts yes/on/true. */
	private function normalizeBool01(string $value): string
	{
		$v = strtolower(trim($value));
		return match (true) {
			in_array($v, ['1', 'yes', 'on', 'true'], true) => '1',
			in_array($v, ['0', 'no', 'off', 'false', ''], true) => '0',
			default => throw new \InvalidArgumentException('INVALID_BOOL'),
		};
	}

	private function normalizeYesNo(string $value): string
	{
		$v = strtolower(trim($value));
		return match ($v) {
			'yes' => 'yes',
			'no' => 'no',
			default => throw new \InvalidArgumentException('INVALID_YES_NO'),
		};
	}

	private function normalizeIntRange(string $key, string $value): string
	{
		[$min, $max] = self::INT_RANGE_KEYS[$key];
		if (preg_match('/^-?\d+$/', trim($value)) !== 1) {
			throw new \InvalidArgumentException('INVALID_INT');
		}
		$int = (int)trim($value);
		if ($int < $min || $int > $max) {
			throw new \InvalidArgumentException('OUT_OF_RANGE');
		}
		return (string)$int;
	}

	private function normalizeIdList(string $value): string
	{
		$list = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
		if (!is_array($list) || !array_is_list($list)) {
			throw new \InvalidArgumentException('INVALID_ID_LIST');
		}
		$clean = [];
		foreach ($list as $id) {
			if (is_string($id) && $id !== '') {
				$clean[] = $id;
			}
		}
		return json_encode(array_values(array_unique($clean)), JSON_THROW_ON_ERROR);
	}

	public function validateMirrored(array $keyValues): ?string
	{
		return $keyValues === [] ? null : 'NO_MIRRORED_KEYS';
	}

	public function writeMirrored(array $keyValues): void
	{
		if ($keyValues !== []) {
			throw new \InvalidArgumentException('NO_MIRRORED_KEYS');
		}
	}

	public function securityRelevantKeys(): array
	{
		return [
			'access_restriction_enabled',
			'access_allowed_user_ids',
			'access_allowed_group_ids',
			'app_admin_user_ids',
			'retention_time_entries_years',
		];
	}

	public function createSafetySnapshot(string $reason): ?string
	{
		try {
			$result = $this->backups->createSnapshot($reason);
			return (string)($result['id'] ?? '');
		} catch (\Throwable) {
			// No data to back up yet (fresh instance) — not an error, but no
			// snapshot id either; the apply must not be silently unprotected
			// when tables DO exist, so rethrow real failures on non-empty DBs.
			if ($this->backups->hasDataToBackup()) {
				throw new \RuntimeException('TRANSFER_SNAPSHOT_FAILED');
			}
			return null;
		}
	}

	public function settingsSectionForKey(string $key): ?string
	{
		return match (true) {
			str_starts_with($key, 'access_') => 'access',
			$key === 'app_admin_user_ids' => 'admins',
			// Internal bookkeeping keys never get a "set it on this
			// instance" link — there is no settings surface for them.
			in_array($key, [
				'uninstall_repair_pass',
				'settlement_counters_ready',
				'upgrade_backup_last_snapshot_id',
				'upgrade_backup_max_snapshots',
				'transfer_receipts',
				'transfer_job_state',
				'transfer_last_import_at',
				'date_format',
				'time_format',
				'budget_alerts',
			], true) => null,
			default => 'defaults',
		};
	}

	public function auditEvent(string $action, string $actor, array $details): void
	{
		// No generic audit sink exists in ProjectCheck — the logger is the
		// documented fallback; never silent.
		$this->logger->info('projectcheck: config transfer ' . $action, [
			'app' => 'projectcheck',
			'actor' => $actor === '' ? 'cli' : $actor,
			'details' => $details,
		]);
	}
}
