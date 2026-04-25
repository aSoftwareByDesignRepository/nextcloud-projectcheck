<?php

declare(strict_types=1);

/**
 * BREACH-mitigating encrypted request token (CSRF) for templates and XHR.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
namespace OCA\ProjectCheck\Service;

interface IRequestTokenProvider
{
	/**
	 * @return string Encrypted value suitable for the requesttoken / CSRF form field
	 */
	public function getEncryptedRequestToken(): string;
}
