#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: empty progress bar must stay hidden at 0%.
 */

require __DIR__ . '/harness.php';

$appRoot = dirname(__DIR__, 2);

$mutants = [
	[
		'name' => 'always_show_bar',
		'file' => 'lib/Util/ProgressCellPresentation.php',
		'search' => "if (!is_finite(\$pct) || \$pct <= 0.0) {\n\t\t\treturn false;\n\t\t}\n\t\treturn true;",
		'replace' => "if (!is_finite(\$pct)) {\n\t\t\treturn false;\n\t\t}\n\t\treturn true;",
	],
	[
		'name' => 'no_clamp_at_100',
		'file' => 'lib/Util/ProgressCellPresentation.php',
		'search' => 'return min(100.0, $pct);',
		'replace' => 'return $pct;',
	],
	[
		'name' => 'negative_hours_pass_through',
		'file' => 'lib/Util/ProgressCellPresentation.php',
		'search' => "if (!is_finite(\$h) || \$h < 0.0) {\n\t\t\treturn 0.0;\n\t\t}",
		'replace' => "if (!is_finite(\$h)) {\n\t\t\treturn 0.0;\n\t\t}",
	],
];

runMutations(
	$appRoot,
	'ProgressCellPresentationTest|DangerButtonThemeContractTest',
	$mutants
);
