<?php

declare(strict_types=1);

/**
 * Migration to add foreign keys for referential integrity
 *
 * @copyright Copyright (c) 2024
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version1003Date202401010003 extends SimpleMigrationStep
{
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Add FK: projects.customer_id -> customers.id (RESTRICT on delete)
        if ($schema->hasTable('projects') && $schema->hasTable('customers')) {
            $projects = $schema->getTable('projects');
            if (!$projects->hasForeignKey('fk_projects_customer')) {
                $projects->addForeignKeyConstraint(
                    $schema->getTable('customers'),
                    ['customer_id'],
                    ['id'],
                    ['onDelete' => 'RESTRICT'],
                    'fk_projects_customer'
                );
            }
        }

        // Add FK: project_members.project_id -> projects.id (CASCADE on delete)
        if ($schema->hasTable('project_members') && $schema->hasTable('projects')) {
            $members = $schema->getTable('project_members');
            if (!$members->hasForeignKey('fk_members_project')) {
                $members->addForeignKeyConstraint(
                    $schema->getTable('projects'),
                    ['project_id'],
                    ['id'],
                    ['onDelete' => 'CASCADE'],
                    'fk_members_project'
                );
            }
        }

        // Add FK: time_entries.project_id -> projects.id (CASCADE on delete)
        if ($schema->hasTable('time_entries') && $schema->hasTable('projects')) {
            $timeEntries = $schema->getTable('time_entries');
            if (!$timeEntries->hasForeignKey('fk_time_entries_project')) {
                $timeEntries->addForeignKeyConstraint(
                    $schema->getTable('projects'),
                    ['project_id'],
                    ['id'],
                    ['onDelete' => 'CASCADE'],
                    'fk_time_entries_project'
                );
            }
        }

        return $schema;
    }
}
