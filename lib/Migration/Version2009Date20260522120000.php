<?php

declare(strict_types=1);

/**
 * Cost pricing modes and effective-dated hourly rate history tables.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Throwable;

class Version2009Date20260522120000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('pc_projects') && !$schema->getTable('pc_projects')->hasColumn('cost_rate_mode')) {
			$table = $schema->getTable('pc_projects');
			$table->addColumn('cost_rate_mode', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'project',
			]);
			$changed = true;
		}

		$changed = RateHistoryTables::apply($schema) || $changed;

		return $changed ? $schema : null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		$this->seedMemberRatesFromLegacy($output);
	}

	private function seedMemberRatesFromLegacy(IOutput $output): void
	{
		if (!$this->db->tableExists('pc_project_members') || !$this->db->tableExists(RateHistoryTables::PROJECT_MEMBER)) {
			return;
		}

		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('m.project_id', 'm.user_id', 'm.hourly_rate', 'm.assigned_at', 'm.assigned_by')
				->from('pc_project_members', 'm')
				->where($qb->expr()->gt('m.hourly_rate', $qb->createNamedParameter('0')));
			$rs = $qb->executeQuery();
			$seeded = 0;
			while ($row = $rs->fetch()) {
				$projectId = (int) $row['project_id'];
				$userId = (string) $row['user_id'];
				$rate = (float) $row['hourly_rate'];
				if ($rate <= 0) {
					continue;
				}
				$assignedAt = $row['assigned_at'] ?? null;
				$effectiveFrom = '1970-01-01';
				if ($assignedAt !== null && $assignedAt !== '') {
					$ts = strtotime((string) $assignedAt);
					if ($ts !== false) {
						$effectiveFrom = gmdate('Y-m-d', $ts);
					}
				}
				$createdBy = (string) ($row['assigned_by'] ?? 'system');
				if ($this->memberRateHistoryExists($projectId, $userId, $effectiveFrom)) {
					continue;
				}
				$ins = $this->db->getQueryBuilder();
				$now = gmdate('Y-m-d H:i:s');
				$ins->insert(RateHistoryTables::PROJECT_MEMBER)
					->values([
						'project_id' => $ins->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
						'user_id' => $ins->createNamedParameter($userId),
						'hourly_rate' => $ins->createNamedParameter((string) $rate),
						'effective_from' => $ins->createNamedParameter($effectiveFrom),
						'created_by' => $ins->createNamedParameter($createdBy !== '' ? $createdBy : 'system'),
						'created_at' => $ins->createNamedParameter($now),
					]);
				$ins->executeStatement();
				$seeded++;
			}
			$rs->closeCursor();
			if ($seeded > 0) {
				$output->info(sprintf('ProjectCheck: seeded %d %s row(s) from legacy member rates.', $seeded, RateHistoryTables::PROJECT_MEMBER));
			}
		} catch (Throwable $e) {
			$output->warning('ProjectCheck: member rate history seed skipped: ' . $e->getMessage());
		}
	}

	private function memberRateHistoryExists(int $projectId, string $userId, string $effectiveFrom): bool
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from(RateHistoryTables::PROJECT_MEMBER)
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('effective_from', $qb->createNamedParameter($effectiveFrom)))
			->setMaxResults(1);
		$found = $qb->executeQuery()->fetchOne();
		return $found !== false;
	}
}
