<?php

declare(strict_types=1);

/**
 * ProjectCheck transfer manifest — every appconfig key and every
 * pc_* table classified exactly once
 * (gate: scripts/check-config-transfer-manifest.py).
 *
 * The 'transfer' settings section lives in
 * {@see \OCA\ProjectCheck\Service\SettingsSectionCatalog} (in-app
 * settings, door-admin only) — the section slug is registered there and
 * dispatched by templates/org-app-settings.php.
 */

namespace OCA\ProjectCheck\ConfigTransfer;

final class TransferManifest extends AbstractTransferManifest
{
	private const SCHEMA_VERSION = 1;

	public function schemaVersion(): int
	{
		return self::SCHEMA_VERSION;
	}

	/** @return array<string,array> */
	public function keys(): array
	{
		return self::normaliseKeys([
			// ── access door / admin policy — app-local appconfig (no platform
			// restriction mirror in ProjectCheck → 'plain'; the atomic write is
			// a plain setValueString inside the apply transaction — see
			// TransferHooks::writeMirrored guard / carecheck precedent) ──
			'access_restriction_enabled' => ['class' => 'plain', 'security' => true, 'section' => 'access'],
			'access_allowed_user_ids' => ['class' => 'plain', 'security' => true, 'refs' => ['kind' => 'uid_list_json'], 'section' => 'access'],
			'access_allowed_group_ids' => ['class' => 'plain', 'security' => true, 'refs' => ['kind' => 'gid_list_json'], 'section' => 'access'],
			'app_admin_user_ids' => ['class' => 'plain', 'security' => true, 'refs' => ['kind' => 'uid_list_json'], 'section' => 'admins'],
			// ── app defaults (Settings → Defaults) — validated through the
			// same rules AppConfigController::saveAppDefaults applies ──
			'currency' => ['class' => 'plain', 'section' => 'defaults'],
			'default_hourly_rate' => ['class' => 'plain', 'section' => 'defaults'],
			'default_project_status' => ['class' => 'plain', 'section' => 'defaults'],
			'default_project_priority' => ['class' => 'plain', 'section' => 'defaults'],
			'budget_warning_threshold' => ['class' => 'plain', 'section' => 'defaults'],
			'budget_critical_threshold' => ['class' => 'plain', 'section' => 'defaults'],
			'items_per_page' => ['class' => 'plain', 'section' => 'defaults'],
			'max_projects_per_user' => ['class' => 'plain', 'section' => 'defaults'],
			'enable_time_tracking' => ['class' => 'plain', 'section' => 'defaults'],
			'enable_customer_management' => ['class' => 'plain', 'section' => 'defaults'],
			'enable_budget_tracking' => ['class' => 'plain', 'section' => 'defaults'],
			// GDPR-flavoured retention window (occ/env-managed knob, read by
			// CleanupJob; destructive when set low) — security-relevant.
			'retention_time_entries_years' => ['class' => 'plain', 'security' => true, 'section' => 'defaults'],
			// ── per-user values (oc_preferences — instance-personal,
			// excluded per spec AS-05; declared so the gate sees them.
			// budget_warning_threshold/budget_critical_threshold/items_per_page
			// also exist as user values but share the app-key names above) ──
			'date_format' => 'internal',
			'time_format' => 'internal',
			'budget_alerts' => 'internal',
			// ── internal bookkeeping — never transferred ──
			'uninstall_repair_pass' => 'internal',
			'settlement_counters_ready' => 'internal',
			'upgrade_backup_last_snapshot_id' => 'internal',
			'upgrade_backup_max_snapshots' => 'internal',
			'transfer_receipts' => 'internal',
			'transfer_job_state' => 'internal',
			'transfer_last_import_at' => 'internal',
		]);
	}

	/** @return array<string,array> */
	public function tables(): array
	{
		return self::normaliseTables([
			// ══ business data ══
			// name carries a real DB unique index (pc_cus_name_uq).
			// created_by is provenance — carried verbatim like every
			// creator column in this app (a deleted/missing creator
			// degrades a view, never sinks a row).
			'pc_customers' => ['class' => 'business', 'strategy' => 'upsert-natural-key',
				'natural_key' => ['name']],
			// No DB unique constraint on projects — (customer, name, created_at)
			// is the honest stable identity (crm_deal precedent): same-name
			// projects under one customer are legitimate, created_at
			// disambiguates them.
			'pc_projects' => ['class' => 'business', 'strategy' => 'upsert-natural-key',
				'natural_key' => ['customer_id', 'name', 'created_at'],
				'fk' => ['customer_id' => 'pc_customers'], 'depends_on' => ['pc_customers']],
			// Unique index pc_members_unique_idx is (project_id, user_id).
			// user_id is the row's identity — skipped when the user does not
			// exist on the target; assigned_by is provenance (verbatim).
			'pc_project_members' => ['class' => 'user-data', 'strategy' => 'upsert-natural-key',
				'natural_key' => ['project_id', 'user_id'],
				'fk' => ['project_id' => 'pc_projects'], 'depends_on' => ['pc_projects'],
				'uid_ref' => ['user_id']],
			// Time entries have no honest natural key (several entries per
			// project+user+date are legitimate) — spec default seed-if-empty
			// on merge, wipe+load on replace. user_id is the entry owner →
			// uid-ref; billing_changed_by is provenance (verbatim).
			'pc_time_entries' => ['class' => 'business', 'strategy' => 'seed-if-empty',
				'fk' => ['project_id' => 'pc_projects'], 'depends_on' => ['pc_projects'],
				'uid_ref' => ['user_id']],
			// Effective-dated rate history; unique (user_id, effective_from)
			// and (project_id, user_id, effective_from) respectively.
			'pc_emp_rates' => ['class' => 'business', 'strategy' => 'upsert-natural-key',
				'natural_key' => ['user_id', 'effective_from'],
				'uid_ref' => ['user_id']],
			'pc_pm_rates' => ['class' => 'business', 'strategy' => 'upsert-natural-key',
				'natural_key' => ['project_id', 'user_id', 'effective_from'],
				'fk' => ['project_id' => 'pc_projects'], 'depends_on' => ['pc_projects'],
				'uid_ref' => ['user_id']],

			// ══ internal / instance-bound — NEVER transferred ══
			// storage_path points at files on the SOURCE instance's
			// filesystem; rows without their blobs would dangle
			// (bc_tx_attachments precedent — no appdata blobs declared).
			'pc_project_files' => ['class' => 'internal'],
			// Display-name tombstones for accounts deleted on THIS instance —
			// the target keeps its own deletion history.
			'pc_user_account_snapshots' => ['class' => 'internal'],
			// License payload+signature, mobile seat grants, and mobile sync
			// idempotency rows are bound to this instance's license and
			// devices (family precedent: *_license_state / *_mobile_seat /
			// *_mob_idem are internal everywhere).
			'pc_license_state' => ['class' => 'internal'],
			'pc_mobile_seats' => ['class' => 'internal'],
			'pc_mob_idem' => ['class' => 'internal'],
		]);
	}
}
