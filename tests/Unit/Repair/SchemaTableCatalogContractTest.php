<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Repair;

use PHPUnit\Framework\TestCase;

/**
 * Pins the app-note inside the generated SchemaTableCatalog docblock.
 *
 * projectcheck is the family's only rename-app: EnsureProjectCheckSchema and
 * SchemaGuardService verify ProjectCheckTableCatalog::REQUIRED_TABLES (the
 * post-rename set), while the generated catalog's EXPECTED_TABLES still lists
 * pre-rename source names and only feeds the sync-uninstall drop list. The
 * generic generated docblock claims otherwise, so the file carries a marked
 * app-note that UninstallSynthesizer round-trips across regen. If a regen or
 * hand-edit strips the markers or the correction, this suite fails loudly.
 */
final class SchemaTableCatalogContractTest extends TestCase
{
	private string $catalogSource;

	protected function setUp(): void
	{
		parent::setUp();
		$path = dirname(__DIR__, 3) . '/lib/Repair/SchemaTableCatalog.php';
		$this->assertFileExists($path);
		$this->catalogSource = (string)file_get_contents($path);
	}

	public function testAppNoteMarkersPresentAndBalanced(): void
	{
		$this->assertSame(
			1,
			substr_count($this->catalogSource, ' * @app-note-begin'),
			'generated catalog must carry exactly one @app-note-begin marker (regen preserves the marked block)',
		);
		$this->assertSame(
			1,
			substr_count($this->catalogSource, ' * @app-note-end'),
			'generated catalog must carry exactly one @app-note-end marker',
		);
		$this->assertLessThan(
			strpos($this->catalogSource, ' * @app-note-end'),
			strpos($this->catalogSource, ' * @app-note-begin'),
			'@app-note-begin must precede @app-note-end',
		);
	}

	public function testAppNoteContradictsGenericExpectedTablesClaim(): void
	{
		$begin = strpos($this->catalogSource, ' * @app-note-begin');
		$end = strpos($this->catalogSource, ' * @app-note-end');
		$this->assertNotFalse($begin);
		$this->assertNotFalse($end);
		$note = substr($this->catalogSource, $begin, $end - $begin);

		$this->assertStringContainsString('NOT a must-exist set', $note);
		$this->assertStringContainsString('ProjectCheckTableCatalog::REQUIRED_TABLES', $note);
		$this->assertStringContainsString('EnsureProjectCheckSchema', $note);
		$this->assertStringContainsString('SchemaGuardService', $note);
	}

	public function testCatalogKeepsBothTableConstants(): void
	{
		$this->assertStringContainsString('public const EXPECTED_TABLES', $this->catalogSource);
		$this->assertStringContainsString('public const DROPPED_LEGACY_TABLES', $this->catalogSource);
	}
}
