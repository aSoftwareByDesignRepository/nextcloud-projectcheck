<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Idempotency payload fingerprint for mobile offline-create keys.
 *
 * Reusing a client_request_id with a *different* payload must be a 409
 * conflict, not a silent replay of the first entry. The sha256 fingerprint
 * of the normalized create payload is stored alongside the key so the
 * replay path can detect the mismatch. NULL = row written before this
 * column existed (cannot prove mismatch → legacy replay).
 */
class Version2016Date20260801000000 extends SimpleMigrationStep
{
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('pc_mob_idem')) {
			$t = $schema->getTable('pc_mob_idem');
			if (!$t->hasColumn('payload_hash')) {
				$t->addColumn('payload_hash', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
			}
		}

		return $schema;
	}
}
