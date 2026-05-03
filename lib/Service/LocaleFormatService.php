<?php

declare(strict_types=1);

/**
 * Server-side counterpart to {@see js/common/format.js}.
 *
 * Audit reference: `pm/app-ideas/projectcheck/AUDIT-FINDINGS.md` finding B10/H28.
 * Templates used to render numbers and currency with `number_format($x, 2)` +
 * a hard-coded `€` glyph, which silently produces an `en_US`-style grouping
 * (`1,234.56`) for German users (who expect `1.234,56`). This service routes
 * every server-rendered locale-sensitive value through one entry point so
 * the user's Nextcloud locale and the org-configured currency code drive the
 * displayed text.
 *
 * The implementation prefers PHP's `intl` extension (always available in a
 * production Nextcloud install) but falls back to a deterministic pure-PHP
 * formatter when `NumberFormatter` is missing. The currency code is fetched
 * from the projectcheck app config (`projectcheck/currency`, defaults to
 * `EUR`) so administrators can later change it without further code edits.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use DateTimeInterface;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\L10N\IFactory;

class LocaleFormatService
{
	private const APP_ID = 'projectcheck';

	private IConfig $config;
	private IFactory $l10nFactory;
	private IUserSession $userSession;
	private ?string $cachedLocale = null;
	private ?string $cachedCurrency = null;
	private ?IL10N $cachedL10n = null;

	public function __construct(IConfig $config, IFactory $l10nFactory, IUserSession $userSession)
	{
		$this->config = $config;
		$this->l10nFactory = $l10nFactory;
		$this->userSession = $userSession;
	}

	/**
	 * Resolve the active BCP-47-ish locale tag (e.g. "de_DE", "en_GB").
	 * Falls back to the language code when no locale is set on the user.
	 */
	public function getLocale(): string
	{
		if ($this->cachedLocale !== null) {
			return $this->cachedLocale;
		}
		$locale = '';
		try {
			$user = $this->userSession->getUser();
			if ($user !== null) {
				$locale = (string)$this->config->getUserValue($user->getUID(), 'core', 'locale', '');
				if ($locale === '') {
					$locale = (string)$this->config->getUserValue($user->getUID(), 'core', 'lang', '');
				}
			}
		} catch (\Throwable $e) {
			$locale = '';
		}
		if ($locale === '') {
			$locale = (string)$this->config->getSystemValueString('default_locale', '');
		}
		if ($locale === '') {
			$locale = (string)$this->config->getSystemValueString('default_language', 'en');
		}
		$this->cachedLocale = $locale !== '' ? $locale : 'en';
		return $this->cachedLocale;
	}

	/**
	 * Resolve the org currency. Defaults to EUR; administrators can override
	 * via `occ config:app:set projectcheck currency --value=USD`.
	 */
	public function getCurrency(): string
	{
		if ($this->cachedCurrency !== null) {
			return $this->cachedCurrency;
		}
		$code = strtoupper(trim((string)$this->config->getAppValue(self::APP_ID, 'currency', 'EUR')));
		if (!preg_match('/^[A-Z]{3}$/', $code)) {
			$code = 'EUR';
		}
		$this->cachedCurrency = $code;
		return $code;
	}

	/**
	 * Locale-aware decimal number, e.g. `1.234,56` for `de_DE`.
	 *
	 * @param int|float|string|null $value
	 */
	public function number($value, int $minDecimals = 0, int $maxDecimals = 2): string
	{
		$n = $this->toFiniteFloat($value);
		if ($n === null) {
			return '—';
		}
		if (class_exists(\NumberFormatter::class)) {
			$fmt = new \NumberFormatter($this->getLocale(), \NumberFormatter::DECIMAL);
			$fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $minDecimals);
			$fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimals);
			$out = $fmt->format($n);
			if (is_string($out)) {
				return $out;
			}
		}
		return $this->fallbackNumber($n, $maxDecimals);
	}

	/**
	 * Locale-aware currency, using the configured currency code by default.
	 * Examples: `1.234,56 €` (de_DE), `€1,234.56` (en_GB).
	 *
	 * @param int|float|string|null $value
	 */
	public function currency($value, ?string $currencyCode = null): string
	{
		$n = $this->toFiniteFloat($value);
		if ($n === null) {
			return '—';
		}
		$code = $currencyCode !== null && preg_match('/^[A-Za-z]{3}$/', $currencyCode)
			? strtoupper($currencyCode)
			: $this->getCurrency();
		if (class_exists(\NumberFormatter::class)) {
			$fmt = new \NumberFormatter($this->getLocale(), \NumberFormatter::CURRENCY);
			$out = $fmt->formatCurrency($n, $code);
			if (is_string($out)) {
				return $out;
			}
		}
		return $code . ' ' . $this->fallbackNumber($n, 2);
	}

	/**
	 * Locale-aware percentage; `value` is the *displayable* number (12.5 for
	 * 12.5 %, not 0.125), matching the JS `percent()` contract.
	 *
	 * @param int|float|string|null $value
	 */
	public function percent($value, int $maxDecimals = 1): string
	{
		$n = $this->toFiniteFloat($value);
		if ($n === null) {
			return '—';
		}
		return $this->number($n, 0, $maxDecimals) . "\u{00A0}%";
	}

	/**
	 * Locale-aware short date (e.g. `30.04.2026` for de_DE).
	 *
	 * @param DateTimeInterface|string|int|null $value
	 */
	public function date($value, string $width = 'short'): string
	{
		$dt = $this->toDateTime($value);
		if ($dt === null) {
			return '—';
		}
		$out = $this->getL10n()->l('date', $dt, ['width' => $width]);
		if (is_string($out) && $out !== '') {
			return $out;
		}
		return $dt->format('Y-m-d');
	}

	/**
	 * Locale-aware date+time.
	 *
	 * @param DateTimeInterface|string|int|null $value
	 */
	public function dateTime($value, string $width = 'short'): string
	{
		$dt = $this->toDateTime($value);
		if ($dt === null) {
			return '—';
		}
		$out = $this->getL10n()->l('datetime', $dt, ['width' => $width]);
		if (is_string($out) && $out !== '') {
			return $out;
		}
		return $dt->format('Y-m-d H:i');
	}

	/**
	 * Locale-aware hours (e.g. `1,5 h` for de_DE).
	 *
	 * @param int|float|string|null $value
	 */
	public function hours($value): string
	{
		$n = $this->toFiniteFloat($value);
		if ($n === null) {
			return '—';
		}
		return $this->number($n, 0, 2) . "\u{00A0}h";
	}

	/**
	 * @param int|float|string|null $value
	 */
	private function toFiniteFloat($value): ?float
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (is_string($value)) {
			$value = trim($value);
			if ($value === '') {
				return null;
			}
			// Accept comma decimal as well so locale-formatted strings
			// round-trip (server reads `1,5` from a German form input).
			if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
				$value = str_replace(',', '.', $value);
			}
		}
		if (!is_numeric($value)) {
			return null;
		}
		$n = (float)$value;
		if (!is_finite($n)) {
			return null;
		}
		return $n;
	}

	/**
	 * @param DateTimeInterface|string|int|null $value
	 */
	private function toDateTime($value): ?\DateTime
	{
		if ($value === null || $value === '') {
			return null;
		}
		if ($value instanceof \DateTime) {
			return $value;
		}
		if ($value instanceof DateTimeInterface) {
			return \DateTime::createFromFormat('U', $value->format('U')) ?: null;
		}
		if (is_int($value)) {
			$dt = new \DateTime();
			$dt->setTimestamp($value);
			return $dt;
		}
		try {
			return new \DateTime((string)$value);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function fallbackNumber(float $n, int $maxDecimals): string
	{
		// Best-effort locale grouping when intl is unavailable: pick the
		// thousands and decimal separator from the locale tag's region.
		$locale = $this->getLocale();
		[$decimal, $thousands] = $this->guessSeparators($locale);
		return number_format($n, $maxDecimals, $decimal, $thousands);
	}

	/**
	 * @return array{0:string,1:string} [decimal, thousands]
	 */
	private function guessSeparators(string $locale): array
	{
		$normalized = strtolower(str_replace('-', '_', $locale));
		// Comma-decimal locales (most of continental Europe).
		$commaDecimal = ['de', 'de_de', 'de_at', 'de_ch', 'fr', 'fr_fr', 'fr_be', 'es', 'es_es', 'it', 'it_it', 'nl', 'nl_nl', 'pt', 'pt_pt', 'pt_br', 'pl', 'pl_pl', 'ru', 'ru_ru', 'sv', 'sv_se', 'no', 'da', 'da_dk', 'cs', 'sk', 'hu', 'fi'];
		foreach ($commaDecimal as $tag) {
			if ($normalized === $tag || str_starts_with($normalized, $tag . '_')) {
				return [',', '.'];
			}
		}
		return ['.', ','];
	}

	private function getL10n(): IL10N
	{
		if ($this->cachedL10n === null) {
			$this->cachedL10n = $this->l10nFactory->get(self::APP_ID);
		}
		return $this->cachedL10n;
	}
}
