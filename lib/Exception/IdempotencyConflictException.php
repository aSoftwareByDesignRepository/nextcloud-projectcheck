<?php

declare(strict_types=1);

/**
 * Thrown when an idempotency key/nonce is replayed with a *different* payload
 * than the request that originally claimed it. Controllers translate this into
 * HTTP 409 — a silent replay of the first request's result would be a data
 * integrity bug (the second payload is never applied).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

class IdempotencyConflictException extends Exception
{
}
