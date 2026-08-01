#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for MobileBookingService D5 (open-only mutations).
 * Additive — does not touch AZC / Family A or PC2 codec signing.
 */

require __DIR__ . '/harness.php';

$booking = 'lib/Service/MobileBookingService.php';

runMutations(dirname(__DIR__, 2), 'MobileBookingServiceTest', [
	[
		'name' => 'excluded-update-allowed',
		'file' => $booking,
		'search' => "if (\$status === BillingStatus::OPEN) {\n\t\t\treturn;\n\t\t}",
		'replace' => "if (\$status === BillingStatus::OPEN || \$status === BillingStatus::EXCLUDED) {\n\t\t\treturn;\n\t\t}",
	],
	[
		'name' => 'open-check-skipped',
		'file' => $booking,
		'search' => "if (\$status === BillingStatus::OPEN) {\n\t\t\treturn;\n\t\t}",
		'replace' => "if (true) {\n\t\t\treturn;\n\t\t}",
	],
	[
		'name' => 'create-trusts-client-rate',
		'file' => $booking,
		'search' => "'description' => \$description,\n\t\t\t// Never trust client rate — omit so server resolves.\n\t\t];",
		'replace' => "'description' => \$description,\n\t\t\t'hourly_rate' => \$body['hourlyRate'] ?? 999,\n\t\t];",
	],
]);
