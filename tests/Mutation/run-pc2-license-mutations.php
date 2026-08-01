#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: PC2 verification (SPEC §8.1) + seat ranking (§8.4).
 */

require __DIR__ . '/harness.php';

$codec = 'lib/License/Pc2Codec.php';
$rank = 'lib/Service/SeatRank.php';
$licenseSvc = 'lib/Service/LicenseService.php';

runMutations(dirname(__DIR__, 2), 'Pc2CodecTest|SeatRankTest|LicensePanelContractTest|LicenseServiceGateTest|MobileGateServiceTest', [
	[
		'name' => 'format-prefix-inverted',
		'file' => $codec,
		'search' => "if (count(\$parts) !== 3 || \$parts[0] !== self::FORMAT) {",
		'replace' => "if (count(\$parts) !== 3 || \$parts[0] === self::FORMAT) {",
	],
	[
		'name' => 'signature-length-inverted',
		'file' => $codec,
		'search' => 'strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES',
		'replace' => 'strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES',
	],
	[
		'name' => 'signature-check-inverted',
		'file' => $codec,
		'search' => 'if (!sodium_crypto_sign_verify_detached($signature, $payloadBytes, VendorPublicKey::bytes())) {',
		'replace' => 'if (sodium_crypto_sign_verify_detached($signature, $payloadBytes, VendorPublicKey::bytes())) {',
	],
	[
		'name' => 'canonical-equality-skipped',
		'file' => $codec,
		'search' => 'if (!hash_equals(self::canonicalJson($payload), $payloadBytes)) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'version-check-inverted',
		'file' => $codec,
		'search' => "if ((\$payload['v'] ?? null) !== self::VERSION) {",
		'replace' => "if ((\$payload['v'] ?? null) === self::VERSION) {",
	],
	[
		'name' => 'product-check-skipped',
		'file' => $codec,
		'search' => "if ((\$payload['product'] ?? null) !== self::PRODUCT) {",
		'replace' => "if (false) {",
	],
	[
		'name' => 'date-order-inverted',
		'file' => $codec,
		'search' => "if (\$payload['validUntil'] < \$payload['issuedAt']) {",
		'replace' => "if (\$payload['validUntil'] > \$payload['issuedAt']) {",
	],
	[
		'name' => 'zero-seats-allowed',
		'file' => $codec,
		'search' => 'if (!is_int($seats) || $seats < 1 || $seats > 10000) {',
		'replace' => 'if (!is_int($seats) || $seats < 0 || $seats > 10000) {',
	],
	[
		'name' => 'seat-cap-removed',
		'file' => $codec,
		'search' => 'if (!is_int($seats) || $seats < 1 || $seats > 10000) {',
		'replace' => 'if (!is_int($seats) || $seats < 1) {',
	],
	[
		'name' => 'validity-excludes-last-day',
		'file' => $codec,
		'search' => 'return $today <= $validUntil;',
		'replace' => 'return $today < $validUntil;',
	],
	[
		'name' => 'rank-order-reversed',
		'file' => $rank,
		'search' => "return \$a['assignedAt'] <=> \$b['assignedAt'];",
		'replace' => "return \$b['assignedAt'] <=> \$a['assignedAt'];",
	],
	[
		'name' => 'tiebreak-reversed',
		'file' => $rank,
		'search' => "return \$a['id'] <=> \$b['id'];",
		'replace' => "return \$b['id'] <=> \$a['id'];",
	],
	[
		'name' => 'limit-fence-exclusive',
		'file' => $rank,
		'search' => 'return $ranks[$seatId] <= $limit;',
		'replace' => 'return $ranks[$seatId] < $limit;',
	],
	[
		'name' => 'rank-never-increments',
		'file' => $rank,
		'search' => "\t\t\t\$ranks[\$seat['id']] = \$rank;\n\t\t\t\$rank++;",
		'replace' => "\t\t\t\$ranks[\$seat['id']] = \$rank;",
	],
	[
		'name' => 'seat-lock-busy-mislabelled-as-limit',
		'file' => $licenseSvc,
		'search' => "self::SEAT_LOCK,\n\t\t\t'seat_busy',",
		'replace' => "self::SEAT_LOCK,\n\t\t\t'seat_limit_reached',",
	],
	[
		'name' => 'gate-skips-license-missing',
		'file' => 'lib/Service/MobileGateService.php',
		'search' => "if (!\$state['hasLicense']) {\n\t\t\tthrow new MobileGateException('license_missing');\n\t\t}",
		'replace' => "if (false) {\n\t\t\tthrow new MobileGateException('license_missing');\n\t\t}",
	],
	[
		'name' => 'gate-skips-seat-required',
		'file' => 'lib/Service/MobileGateService.php',
		'search' => "if (!\$state['seatAssigned']) {\n\t\t\tthrow new MobileGateException('seat_required');\n\t\t}",
		'replace' => "if (false) {\n\t\t\tthrow new MobileGateException('seat_required');\n\t\t}",
	],
	[
		'name' => 'gate-skips-within-limit',
		'file' => 'lib/Service/MobileGateService.php',
		'search' => "if (!\$state['seatWithinLimit']) {\n\t\t\tthrow new MobileGateException('seat_limit_exceeded');\n\t\t}",
		'replace' => "if (false) {\n\t\t\tthrow new MobileGateException('seat_limit_exceeded');\n\t\t}",
	],
]);
