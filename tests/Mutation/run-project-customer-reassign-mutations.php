#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: empty DECIMAL coercion + soft pricing on project updates.
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
		'name' => 'skip_formdecimal_coerce_in_payload',
		'file' => 'lib/Util/ProjectFormPayload.php',
		'search' => "\$staged[\$field] = FormDecimal::coerce(\$value);",
		'replace' => "\$staged[\$field] = \$value;",
	],
	[
		'name' => 'always_require_rate_even_when_pricing_untouched',
		'file' => 'lib/Service/ProjectService.php',
		'search' => "if (\$mode === CostRateMode::PROJECT && \$budget > 0 && \$rate <= 0 && \$pricingTouched) {",
		'replace' => "if (\$mode === CostRateMode::PROJECT && \$budget > 0 && \$rate <= 0 && true) {",
	],
	[
		'name' => 'never_force_available_hours_zero',
		'file' => 'lib/Service/ProjectService.php',
		'search' => "} else {\n\t\t\t// Zero budget or missing rate → store 0, never \"\".\n\t\t\t\$data['available_hours'] = 0.0;\n\t\t}",
		'replace' => "} else {\n\t\t\t// mutated: leave whatever the form sent\n\t\t}",
	],
	[
		'name' => 'partial_api_coerces_blank_decimals_instead_of_omit',
		'file' => 'lib/Util/ProjectFormPayload.php',
		'search' => "if (!\$fullForm && self::isBlank(\$value)) {\n\t\t\t\t\t// Partial API: omit so merge keeps the stored DECIMAL.\n\t\t\t\t\tcontinue;\n\t\t\t\t}",
		'replace' => "if (false && !\$fullForm && self::isBlank(\$value)) {\n\t\t\t\t\t// Partial API: omit so merge keeps the stored DECIMAL.\n\t\t\t\t\tcontinue;\n\t\t\t\t}",
	],
];

runMutations(
	$appRoot,
	'FormDecimalTest|ProjectFormPayloadTest|ProjectCustomerReassignIntegrationTest|ProjectFormSaveSoftPricingIntegrationTest',
	$mutants
);
