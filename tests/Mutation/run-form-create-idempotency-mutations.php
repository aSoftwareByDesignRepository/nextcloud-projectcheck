#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: HTML/API create idempotency + lock/replay.
 */

require __DIR__ . '/harness.php';

$appRoot = dirname(__DIR__, 2);
$file = 'lib/Service/FormSubmitIdempotencyService.php';

runMutations(
	$appRoot,
	'FormSubmitIdempotencyServiceTest',
	[
		[
			'name' => 'skip_replay_cached_id',
			'file' => $file,
			'search' => "\$existing = \$this->readProjectId(\$cache->get(\$key));\n\t\tif (\$existing !== null) {\n\t\t\treturn \$existing;\n\t\t}",
			'replace' => "\$existing = \$this->readProjectId(\$cache->get(\$key));\n\t\tif (false && \$existing !== null) {\n\t\t\treturn \$existing;\n\t\t}",
		],
		[
			'name' => 'never_release_lock_on_failure',
			'file' => $file,
			'search' => "} finally {\n\t\t\t\$this->locking->releaseLock(\$lockKey, ILockingProvider::LOCK_EXCLUSIVE);\n\t\t}",
			'replace' => "} finally {\n\t\t\tif (false) {\n\t\t\t\t\$this->locking->releaseLock(\$lockKey, ILockingProvider::LOCK_EXCLUSIVE);\n\t\t\t}\n\t\t}",
		],
		[
			'name' => 'clear_mapping_after_successful_create',
			'file' => $file,
			'search' => "if (\$projectId === null) {\n\t\t\t\t\$cache->remove(\$key);\n\t\t\t\tthrow \$e;\n\t\t\t}",
			'replace' => "if (true) {\n\t\t\t\t\$cache->remove(\$key);\n\t\t\t\tthrow \$e;\n\t\t\t}",
		],
		[
			'name' => 'normalize_accepts_whitespace_garbage',
			'file' => $file,
			'search' => "if (preg_match('/^[A-Za-z0-9._:-]+$/', \$nonce) !== 1) {\n\t\t\treturn null;\n\t\t}",
			'replace' => "if (false && preg_match('/^[A-Za-z0-9._:-]+$/', \$nonce) !== 1) {\n\t\t\treturn null;\n\t\t}",
		],
	]
);
