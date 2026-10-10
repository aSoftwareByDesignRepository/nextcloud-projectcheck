<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Support;

use OCP\IDBConnection;

/**
 * Purges domain rows left behind by integration-test fixtures.
 *
 * HTTP integration tests create real customers, projects, members, time
 * entries and activity via the app's services — persistence is the point of
 * the test — but tearDown previously deleted only users and license state,
 * leaking fixture rows into the dev database on every run.
 *
 * Fixture rows are identified by name-prefix markers that are only ever
 * generated inside tests/Integration. Purging them cannot touch real data.
 */
final class IntegrationFixtureCleanup
{
	/** Customer/project name prefixes reserved for integration fixtures. */
	private const NAME_PREFIXES = [
		'PC Book Cust ', 'PC Book Proj ',
		'PC Stl Cust ', 'PC Stl Proj ',
		'Legacy PC Cust ', 'Legacy PC Proj ',
		'Reassign A ', 'Reassign B ', 'Reassign Proj ',
		'Soft Price Cust ', 'Soft Price Proj ', 'Renamed Soft Price ',
		'Budget Change Cust ', 'Budget Change Proj ',
		'Empty Budget ', 'Empty Budget Proj ',
		'Empty Dec Create ', 'Empty Dec Create Proj ',
		'Garbage Dec ', 'Garbage Proj ',
		'No Hours Field ', 'No Hours Field Proj ',
		'Saved Without Hours Field ',
		'OCC After Race ', 'OCC Cust ', 'OCC First Writer ', 'OCC Proj ',
		'Stale Hours ', 'Stale Hours Proj ',
		'PC Par Cust ', 'PC Par Proj ',
		'PC_UPGRADE_BACKUP_IT',
	];

	/** client_request_id prefixes used by mobile idempotency fixtures. */
	private const IDEM_PREFIXES = ['stl-create-', 'idem-', 'par-'];

	/**
	 * Fixture user-id prefixes (IntegrationTestUsers::ensure constants).
	 * Child rows keyed by uid survive a name-marker purge when a test dies
	 * before its named parent exists — the uid leg closes that hole.
	 */
	private const UID_PREFIXES = [
		'pc_gate_', 'pc_book_', 'pc_mob_', 'pc_stl_', 'pc_par_', 'pc_upgrade_',
	];

	public static function purge(IDBConnection $db): void
	{
		$params = [];
		$nameWhere = self::likeAny('name', self::NAME_PREFIXES, $params);
		$projectIds = self::ids($db, 'oc_pc_projects', $nameWhere, $params);
		$customerIds = self::ids($db, 'oc_pc_customers', $nameWhere, $params);

		if ($projectIds !== []) {
			$in = self::intList($projectIds);
			$entryIds = self::ids($db, 'oc_pc_time_entries', "project_id IN ($in)", []);
			$memberIds = self::ids($db, 'oc_pc_project_members', "project_id IN ($in)", []);
			$db->executeStatement("DELETE FROM oc_pc_time_entries WHERE project_id IN ($in)");
			$db->executeStatement("DELETE FROM oc_pc_project_members WHERE project_id IN ($in)");
			$db->executeStatement("DELETE FROM oc_pc_project_files WHERE project_id IN ($in)");
			$db->executeStatement("DELETE FROM oc_pc_pm_rates WHERE project_id IN ($in)");
			$db->executeStatement(
				"DELETE FROM oc_activity WHERE app = 'projectcheck' AND object_type = 'project' AND object_id IN ($in)"
			);
			if ($entryIds !== []) {
				$entryIn = self::intList($entryIds);
				$db->executeStatement(
					"DELETE FROM oc_activity WHERE app = 'projectcheck' AND object_type = 'time_entry' AND object_id IN ($entryIn)"
				);
			}
			if ($memberIds !== []) {
				$memberIn = self::intList($memberIds);
				$db->executeStatement(
					"DELETE FROM oc_activity WHERE app = 'projectcheck' AND object_type = 'project_member' AND object_id IN ($memberIn)"
				);
			}
		}
		if ($projectIds !== []) {
			$in = self::intList($projectIds);
			$db->executeStatement("DELETE FROM oc_pc_projects WHERE id IN ($in)");
		}
		if ($customerIds !== []) {
			$in = self::intList($customerIds);
			$db->executeStatement("DELETE FROM oc_pc_customers WHERE id IN ($in)");
		}

		// Mobile idempotency rows for test request ids.
		$idemParams = [];
		$idemWhere = self::likeAny('client_request_id', self::IDEM_PREFIXES, $idemParams);
		$db->executeStatement("DELETE FROM oc_pc_mob_idem WHERE $idemWhere", $idemParams);

		// Fixture-uid leg: rows keyed by uid, independent of name markers.
		$uidParams = [];
		$uidWhere = self::likeAny('user_id', self::UID_PREFIXES, $uidParams);
		$db->executeStatement("DELETE FROM oc_pc_time_entries WHERE $uidWhere", $uidParams);
		$db->executeStatement("DELETE FROM oc_pc_project_members WHERE $uidWhere", $uidParams);
		$db->executeStatement("DELETE FROM oc_pc_emp_rates WHERE $uidWhere", $uidParams);
		$db->executeStatement("DELETE FROM oc_pc_pm_rates WHERE $uidWhere", $uidParams);
		$db->executeStatement("DELETE FROM oc_pc_mob_idem WHERE $uidWhere", $uidParams);
		$seatParams = [];
		$seatWhere = self::likeAny('uid', self::UID_PREFIXES, $seatParams);
		$db->executeStatement("DELETE FROM oc_pc_mobile_seats WHERE $seatWhere", $seatParams);
		$snapParams = [];
		$snapWhere = self::likeAny('user_id', self::UID_PREFIXES, $snapParams);
		$db->executeStatement("DELETE FROM oc_pc_user_account_snapshots WHERE $snapWhere", $snapParams);

		// Activity rows whose subjectparams embed fixture names.
		$actParams = [];
		$actWhere = self::likeAny('subjectparams', self::NAME_PREFIXES, $actParams);
		$db->executeStatement(
			"DELETE FROM oc_activity WHERE app = 'projectcheck' AND ($actWhere)",
			$actParams
		);
	}

	/** @return int[] */
	private static function ids(IDBConnection $db, string $table, string $where, array $params): array
	{
		$result = $db->executeQuery("SELECT id FROM $table WHERE $where", $params);
		$ids = [];
		while ($row = $result->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();
		return $ids;
	}

	private static function likeAny(string $column, array $prefixes, array &$params): string
	{
		$clauses = [];
		foreach ($prefixes as $prefix) {
			$clauses[] = "$column LIKE ?";
			$params[] = $prefix . '%';
		}
		return '(' . implode(' OR ', $clauses) . ')';
	}

	/** @param int[] $ids */
	private static function intList(array $ids): string
	{
		return implode(',', array_map('intval', $ids));
	}
}
