<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OC\Security\CSRF\CsrfTokenManager;

class DefaultRequestTokenService implements IRequestTokenProvider
{
	public function __construct(
		private readonly CsrfTokenManager $csrfTokenManager
	) {
	}

	public function getEncryptedRequestToken(): string
	{
		return $this->csrfTokenManager->getToken()->getEncryptedValue();
	}

	public function isRequestTokenValid(string $token): bool
	{
		$token = trim($token);
		if ($token === '') {
			return false;
		}
		try {
			return $this->csrfTokenManager->isTokenValid(new \OC\Security\CSRF\CsrfToken($token));
		} catch (\Throwable) {
			return false;
		}
	}
}
