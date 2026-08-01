<?php

declare(strict_types=1);

/**
 * Request DTO for {@see CrmCustomerWriteFacade} (CHECK-SUITE FC-PC-WRITE).
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

/**
 * @psalm-immutable
 */
final class CrmCustomerWriteRequest
{
	public function __construct(
		public readonly string $actorUid,
		public readonly string $displayName,
		public readonly int $crmCompanyId,
		public readonly string $crmCompanySlug,
		public readonly ?string $email = null,
		public readonly ?string $phone = null,
		public readonly ?string $address = null,
		public readonly ?string $contactPerson = null,
		public readonly ?string $crmUpdatedAt = null,
		public readonly ?int $existingPcCustomerId = null,
	) {
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public static function fromArray(array $input): self
	{
		$existing = $input['existingPcCustomerId'] ?? null;
		$existingId = $existing === null || $existing === '' ? null : (int)$existing;

		return new self(
			actorUid: trim((string)($input['actorUid'] ?? '')),
			displayName: trim((string)($input['displayName'] ?? '')),
			crmCompanyId: (int)($input['crmCompanyId'] ?? 0),
			crmCompanySlug: trim((string)($input['crmCompanySlug'] ?? '')),
			email: self::nullableString($input['email'] ?? null),
			phone: self::nullableString($input['phone'] ?? null),
			address: self::nullableString($input['address'] ?? null),
			contactPerson: self::nullableString($input['contactPerson'] ?? null),
			crmUpdatedAt: self::nullableString($input['crmUpdatedAt'] ?? null),
			existingPcCustomerId: ($existingId !== null && $existingId > 0) ? $existingId : null,
		);
	}

	private static function nullableString(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		$trimmed = trim((string)$value);
		return $trimmed === '' ? null : $trimmed;
	}
}
