<?php

declare(strict_types=1);

/**
 * Cross-app facade result DTO (CHECK-SUITE FC-0).
 *
 * Prefer this over exceptions across app boundaries so consumers can map
 * machine codes to UI without catching foreign exception classes.
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

/**
 * @psalm-immutable
 */
final class FacadeResult
{
	/**
	 * @param array<string, mixed>|null $data
	 */
	public function __construct(
		public readonly bool $ok,
		public readonly ?string $code = null,
		public readonly ?string $message = null,
		public readonly ?array $data = null,
	) {
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function success(array $data): self
	{
		return new self(true, null, null, $data);
	}

	/**
	 * @param array<string, mixed>|null $data
	 */
	public static function failure(string $code, ?string $message = null, ?array $data = null): self
	{
		return new self(false, $code, $message, $data);
	}
}
