<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

use OCP\IConfig;
use OCA\ProjectCheck\AppInfo\Application;

/**
 * SSRF gate for outbound webhook targets (OWASP API #7).
 *
 * A subscription URL is a standing invitation for the server to POST user
 * event data somewhere — validation must be airtight:
 *   - https only (dev escape hatch behind an explicit app config flag)
 *   - no userinfo (https://user:pass@host smuggles credentials into logs)
 *   - DNS resolves to public IPs only — every A/AAAA record is checked;
 *     one private answer fails the whole URL (DNS rebind-safe at create
 *     time; delivery-time re-resolution is bounded by the receiver's own
 *     signature verification)
 *   - max length 500
 *
 * Dev/dogfood escape: app value `agent_webhook_dev_http` = '1' allows
 * http:// and private/loopback targets. NEVER documented for production —
 * used by the local test stack only.
 */
final class WebhookUrlGuard
{
	public const DEV_FLAG = 'agent_webhook_dev_http';

	public function __construct(
		private readonly IConfig $config,
	) {
	}

	/**
	 * @throws AgentException 422 validation with a stable field code
	 */
	public function assertAllowed(string $url): void
	{
		$fail = static function (string $fieldCode, string $message): void {
			throw new AgentException('validation', $message, 422, [
				'fields' => ['url' => $fieldCode],
			]);
		};

		$url = trim($url);
		if ($url === '' || mb_strlen($url) > 500) {
			$fail('invalid_length', 'Webhook URL must be 1–500 characters.');
		}
		if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
			$fail('invalid_chars', 'Webhook URL must not contain control characters.');
		}
		$parts = parse_url($url);
		if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
			$fail('invalid_format', 'Webhook URL must be an absolute URL.');
		}
		$dev = $this->config->getAppValue(Application::APP_ID, self::DEV_FLAG, '0') === '1';
		$scheme = strtolower((string)$parts['scheme']);
		if (!$dev && $scheme !== 'https') {
			$fail('https_required', 'Webhook URL must use https://.');
		}
		if ($dev && !in_array($scheme, ['https', 'http'], true)) {
			$fail('https_required', 'Webhook URL must use https:// (http allowed only in dev).');
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			$fail('no_userinfo', 'Webhook URL must not contain credentials.');
		}
		if (isset($parts['query']) || isset($parts['fragment'])) {
			// Query secrets rot in logs and fragment is meaningless on POST —
			// keep the target a clean endpoint.
			$fail('no_query', 'Webhook URL must not contain a query string or fragment.');
		}
		$host = (string)$parts['host'];
		// parse_url keeps brackets on IPv6 literals — strip them so the
		// IP check sees ::1, not '[::1]'.
		if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
			$host = substr($host, 1, -1);
		}
		if ($dev) {
			return;
		}
		$ips = $this->resolve($host);
		if ($ips === []) {
			$fail('unresolvable', 'Webhook host does not resolve.');
		}
		foreach ($ips as $ip) {
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
				$fail('private_target', 'Webhook host must resolve to a public address.');
			}
		}
	}

	/**
	 * Every address the host can resolve to — A AND AAAA. Checking only
	 * IPv4 would let a public-A/private-AAAA dual-stack host slip through
	 * on IPv6-preferring networks.
	 *
	 * @return list<string>
	 */
	private function resolve(string $host): array
	{
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return [$host];
		}
		$ips = [];
		$records = @dns_get_record($host, DNS_A + DNS_AAAA);
		if (is_array($records)) {
			foreach ($records as $record) {
				if (isset($record['ip']) && is_string($record['ip'])) {
					$ips[] = $record['ip'];
				}
				if (isset($record['ipv6']) && is_string($record['ipv6'])) {
					$ips[] = $record['ipv6'];
				}
			}
		}
		return $ips;
	}
}
