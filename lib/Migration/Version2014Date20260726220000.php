<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Enforce UNIQUE on pc_customers.name (CHECK-SUITE FC-PC-WRITE TOCTOU fix).
 *
 * Application already rejects duplicate names; without a UNIQUE constraint two
 * concurrent createCustomer races can both pass findByName and insert twins.
 * Deduplicate existing collisions before adding the index.
 */
class Version2014Date20260726220000 extends SimpleMigrationStep
{
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		if (!$this->db->tableExists('pc_customers')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('name')
			->selectAlias($qb->func()->count('*'), 'cnt')
			->from('pc_customers')
			->groupBy('name')
			->having($qb->expr()->gt($qb->func()->count('*'), $qb->createNamedParameter(1, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$dupNames = [];
		while ($row = $result->fetch()) {
			$dupNames[] = (string)$row['name'];
		}
		$result->closeCursor();

		foreach ($dupNames as $name) {
			$find = $this->db->getQueryBuilder();
			$find->select('id', 'name')
				->from('pc_customers')
				->where($find->expr()->eq('name', $find->createNamedParameter($name)))
				->orderBy('id', 'ASC');
			$rows = $find->executeQuery();
			$keepFirst = true;
			while ($row = $rows->fetch()) {
				$id = (int)$row['id'];
				if ($keepFirst) {
					$keepFirst = false;
					continue;
				}
				$newName = mb_substr($name . ' (#' . $id . ')', 0, 255);
				$upd = $this->db->getQueryBuilder();
				$upd->update('pc_customers')
					->set('name', $upd->createNamedParameter($newName))
					->where($upd->expr()->eq('id', $upd->createNamedParameter($id, \PDO::PARAM_INT)));
				$upd->executeStatement();
				$output->info('Renamed duplicate pc_customers id=' . $id . ' → ' . $newName);
			}
			$rows->closeCursor();
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('pc_customers')) {
			return null;
		}

		$table = $schema->getTable('pc_customers');
		$changed = false;
		foreach (['pc_customers_name_idx', 'pc_cus_name_idx'] as $legacy) {
			if ($table->hasIndex($legacy)) {
				$table->dropIndex($legacy);
				$changed = true;
			}
		}
		if (!$table->hasIndex('pc_cus_name_uq')) {
			$table->addUniqueIndex(['name'], 'pc_cus_name_uq');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
