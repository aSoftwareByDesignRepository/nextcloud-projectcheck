<?php

declare(strict_types=1);

/**
 * Contract tests for Brazilian Portuguese (pt_BR) catalogs.
 *
 * @copyright Copyright (c) 2026, aSoftwareByDesignRepository
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\L10n;

use PHPUnit\Framework\TestCase;

class PtBrCatalogContractTest extends TestCase
{
	private string $appRoot;

	protected function setUp(): void
	{
		$this->appRoot = dirname(__DIR__, 3);
	}

	public function testPtBrJsonExistsWithFullParityAndPluralForm(): void
	{
		$en = $this->loadJson('en.json');
		$pt = $this->loadJson('pt_BR.json');

		$enKeys = array_keys($en['translations']);
		$ptKeys = array_keys($pt['translations']);
		sort($enKeys);
		sort($ptKeys);

		$this->assertSame($enKeys, $ptKeys, 'pt_BR must contain exactly the same msgid set as en');
		$this->assertArrayHasKey('pluralForm', $pt);
		$this->assertStringContainsString('nplurals=2', (string)$pt['pluralForm']);
		$this->assertGreaterThan(1000, count($enKeys));
	}

	public function testPtBrJsRegistersProjectcheckAndMatchesJsonKeyCount(): void
	{
		$jsPath = $this->appRoot . '/l10n/pt_BR.js';
		$this->assertFileExists($jsPath);
		$js = (string)file_get_contents($jsPath);
		$this->assertStringContainsString('OC.L10N.register(', $js);
		$this->assertStringContainsString('"projectcheck"', $js);
		$this->assertStringContainsString('nplurals=2', $js);

		$pt = $this->loadJson('pt_BR.json');
		foreach (array_keys($pt['translations']) as $key) {
			$this->assertStringContainsString(json_encode($key, JSON_UNESCAPED_UNICODE), $js);
		}
	}

	public function testPtBrPreservesPrintfAndNamedPlaceholders(): void
	{
		$en = $this->loadJson('en.json')['translations'];
		$pt = $this->loadJson('pt_BR.json')['translations'];

		foreach ($en as $key => $enVal) {
			$ptVal = $pt[$key] ?? null;
			$this->assertNotNull($ptVal, "Missing pt_BR key: {$key}");
			if (is_array($enVal)) {
				$this->assertIsArray($ptVal);
				$this->assertCount(count($enVal), $ptVal, "Plural form count mismatch for {$key}");
				foreach ($enVal as $i => $form) {
					$this->assertSame(
						$this->printfPlaceholders((string)$form),
						$this->printfPlaceholders((string)$ptVal[$i]),
						"Plural printf mismatch for {$key}[{$i}]",
					);
				}
				continue;
			}
			$this->assertIsString($ptVal);
			$this->assertSame(
				$this->printfPlaceholders((string)$enVal),
				$this->printfPlaceholders((string)$ptVal),
				"Printf mismatch for {$key}",
			);
			$this->assertSame(
				$this->namedPlaceholders((string)$enVal),
				$this->namedPlaceholders((string)$ptVal),
				"Named placeholder mismatch for {$key}",
			);
		}
	}

	public function testPtBrKeepsProductNamesAndCoreUxStrings(): void
	{
		$pt = $this->loadJson('pt_BR.json')['translations'];

		$this->assertSame('ProjectCheck', $pt['ProjectCheck']);
		$this->assertSame('CustomerCheck', $pt['CustomerCheck']);
		$this->assertSame('Acesso negado', $pt['Access denied']);
		$this->assertStringContainsString('negado', mb_strtolower((string)$pt['Access denied']));
		$this->assertStringNotContainsString('Verificação do Projeto', (string)$pt['ProjectCheck']);
	}

	public function testPtBrTranslationsAreMostlyLocalized(): void
	{
		$en = $this->loadJson('en.json')['translations'];
		$pt = $this->loadJson('pt_BR.json')['translations'];

		$identical = 0;
		$comparable = 0;
		foreach ($en as $key => $enVal) {
			if (!is_string($enVal) || !is_string($pt[$key] ?? null)) {
				continue;
			}
			// Skip identity strings / brands / codes.
			if ($enVal === $key && preg_match('/^[A-Za-z0-9 _.-]+$/', $enVal) && strlen($enVal) < 40) {
				// Still count short UI words.
			}
			if (in_array($key, ['ProjectCheck', 'CustomerCheck', 'AGPL', 'CRM', 'CSV', 'PDF', 'API', 'OK'], true)) {
				continue;
			}
			if (trim($enVal) === '' || preg_match('/^[%\\\\{\\}0-9\\s.,:;\\/·—–-]+$/u', $enVal)) {
				continue;
			}
			$comparable++;
			if ($enVal === $pt[$key]) {
				$identical++;
			}
		}

		$this->assertGreaterThan(500, $comparable);
		$ratio = $identical / max(1, $comparable);
		$this->assertLessThan(
			0.25,
			$ratio,
			sprintf('Too many English leftovers in pt_BR (%.1f%% identical of %d comparable)', $ratio * 100, $comparable),
		);
	}

	/**
	 * @return array{translations: array<string, mixed>, pluralForm?: string}
	 */
	private function loadJson(string $file): array
	{
		$path = $this->appRoot . '/l10n/' . $file;
		$this->assertFileExists($path);
		/** @var array{translations: array<string, mixed>, pluralForm?: string} $data */
		$data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

		return $data;
	}

	/**
	 * @return list<string>
	 */
	private function printfPlaceholders(string $s): array
	{
		preg_match_all('/%%|%(?:\d+\$)?[sd]|%n/', $s, $m);

		return $m[0];
	}

	/**
	 * @return list<string>
	 */
	private function namedPlaceholders(string $s): array
	{
		preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $s, $m);

		return $m[0];
	}
}
