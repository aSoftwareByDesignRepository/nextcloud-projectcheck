#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: empty DECIMAL coercion for project updates.
 */

require __DIR__ . '/harness.php';

$appRoot = dirname(__DIR__, 2);

$mutants = [
	[
		'name' => 'drop_empty_string_zero',
		'file' => 'lib/Util/FormDecimal.php',
		'search' => "if (\$value === '') {\n\t\t\treturn 0.0;\n\t\t}",
		'replace' => "if (false) {\n\t\t\treturn 0.0;\n\t\t}",
	],
	[
		'name' => 'skip_formdecimal_coerce',
		'file' => 'lib/Service/ProjectService.php',
		'search' => "if (in_array(\$field, ['hourly_rate', 'total_budget', 'available_hours'], true)) {\n\t\t\t\t\ttry {\n\t\t\t\t\t\t\$value = FormDecimal::coerce(\$value);\n\t\t\t\t\t} catch (\\InvalidArgumentException \$e) {\n\t\t\t\t\t\tthrow new \\Exception(ucfirst(str_replace('_', ' ', \$field)) . ' must be a non-negative number');\n\t\t\t\t\t}\n\t\t\t\t}",
		'replace' => "if (in_array(\$field, ['hourly_rate', 'total_budget', 'available_hours']) && !empty(\$value)) {\n\t\t\t\t\t\$value = (float)\$value;\n\t\t\t\t}",
	],
	[
		'name' => 'drop_zero_budget_hours_reset',
		'file' => 'lib/Service/ProjectService.php',
		'search' => '} elseif ($budget <= 0 || ($modeForCapacity !== CostRateMode::PROJECT && $rate <= 0)) {
				// Zero budget (or planning mode without a rate) → store 0, never "".
				$data[\'available_hours\'] = 0.0;
			}',
		'replace' => '} elseif ($budget > 0 && $modeForCapacity !== CostRateMode::PROJECT && $rate <= 0) {
				$data[\'available_hours\'] = 0;
			}',
	],
];

runMutations(
	$appRoot,
	'FormDecimalTest|ProjectCustomerReassignIntegrationTest|ProjectFormQuickCustomerContractTest',
	$mutants
);
