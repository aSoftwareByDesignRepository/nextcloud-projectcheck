<?php

declare(strict_types=1);

/**
 * Builds a msgid => template string map for the browser. Values are the raw
 * translated templates from the locale JSON (placeholders like %s remain).
 * This avoids IL10N::t($msgid) with no args, which throws ValueError for
 * strings that use sprintf (e.g. "Project \"%s\" was created…").
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCP\App\IAppManager;
use OCP\L10N\IFactory;

class JsL10nCatalogBuilder
{
	public const APP_ID = 'projectcheck';

	public function __construct(
		private IFactory $l10nFactory,
		private IAppManager $appManager,
	) {
	}

	/**
	 * @return array<string, string> English msgid => template for the current UI language
	 */
	public function buildForApp(): array
	{
		$enPath = $this->getL10nJsonPath('en');
		if ($enPath === null) {
			return [];
		}
		$enData = $this->loadTranslationsFile($enPath);
		$enTranslations = $enData['translations'] ?? [];
		if (!\is_array($enTranslations) || $enTranslations === []) {
			return [];
		}

		$langFile = $this->resolveLocaleJsonFile();
		$langTranslations = $enTranslations;
		if ($langFile !== null && $langFile !== $enPath) {
			$loaded = $this->loadTranslationsFile($langFile)['translations'] ?? [];
			if (\is_array($loaded) && $loaded !== []) {
				$langTranslations = $loaded;
			}
		}

		$out = [];
		foreach (array_keys($enTranslations) as $msgId) {
			if (!\is_string($msgId) || $msgId === '') {
				continue;
			}
			if (isset($langTranslations[$msgId]) && \is_string($langTranslations[$msgId]) && $langTranslations[$msgId] !== '') {
				$out[$msgId] = $langTranslations[$msgId];
			} else {
				$out[$msgId] = $enTranslations[$msgId] ?? $msgId;
			}
		}

		return $out;
	}

	/**
	 * Nextcloud may report de_DE; we ship de.json. Try a small candidate list.
	 */
	private function resolveLocaleJsonFile(): ?string
	{
		$raw = (string) $this->l10nFactory->findLanguage(self::APP_ID);
		$raw = preg_replace('/[^a-z0-9_-]/i', '', $raw) ?? 'en';
		$candidates = array_unique(
			array_filter(
				[
					$raw,
					$this->primarySubtag($raw, '-'),
					$this->primarySubtag($raw, '_'),
				],
				static fn ($v) => $v !== null && $v !== '',
			),
		);
		if ($candidates === []) {
			$candidates = [ 'en' ];
		}
		foreach ($candidates as $code) {
			if ($code === 'en') {
				return $this->getL10nJsonPath('en');
			}
			$path = $this->getL10nJsonPath($code);
			if ($path !== null) {
				return $path;
			}
		}

		return $this->getL10nJsonPath('en');
	}

	private function primarySubtag(string $raw, string $sep): ?string
	{
		if (!str_contains($raw, $sep)) {
			return null;
		}
		$base = explode($sep, $raw, 2)[0];

		return $base !== '' ? $base : null;
	}

	/**
	 * @return array{translations?: array<string, string>}
	 */
	private function loadTranslationsFile(string $path): array
	{
		$raw = @file_get_contents($path);
		if ($raw === false) {
			return [];
		}
		try {
			/** @var array $data */
			$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return [];
		}

		return \is_array($data) ? $data : [];
	}

	private function getL10nJsonPath(string $lang): ?string
	{
		try {
			$appPath = $this->appManager->getAppPath(self::APP_ID);
		} catch (\Throwable) {
			return null;
		}
		$path = $appPath . '/l10n/' . $lang . '.json';

		return is_file($path) ? $path : null;
	}
}
