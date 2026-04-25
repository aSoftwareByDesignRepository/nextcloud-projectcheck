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
}
