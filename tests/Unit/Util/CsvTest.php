<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase {
	/**
	 * @dataProvider formulaPrefixProvider
	 */
	public function testSanitizeFieldNeutralizesFormulaPrefixes(string $input, string $expected): void {
		$this->assertSame($expected, Csv::sanitizeField($input));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function formulaPrefixProvider(): array {
		return [
			'equals' => ['=cmd', "'=cmd"],
			'plus' => ['+sum(A1:A2)', "'+sum(A1:A2)"],
			'minus' => ['-2+3', "'-2+3"],
			'at' => ['@calc', "'@calc"],
			'tab' => ["\t=1+1", "'\t=1+1"],
			'carriage return' => ["\r=1+1", "'\r=1+1"],
			'plain text untouched' => ['Project Alpha', 'Project Alpha'],
			'empty untouched' => ['', ''],
			'inner formula untouched' => ['a=b', 'a=b'],
		];
	}

	public function testLineQuotesEscapesAndJoinsWithSemicolon(): void {
		$line = Csv::line(['Name', 'He said "hi"', 'a;b']);
		$this->assertSame('"Name";"He said ""hi""";"a;b"' . "\n", $line);
	}

	public function testLineSanitizesEveryCell(): void {
		$line = Csv::line(['=cmd', 'safe']);
		$this->assertSame("\"'=cmd\";\"safe\"\n", $line);
	}

	public function testLineCastsNullAndNumbers(): void {
		$line = Csv::line([null, 42, 1.5]);
		$this->assertSame('"";"42";"1.5"' . "\n", $line);
	}

	public function testLineDoesNotEmitBom(): void {
		$this->assertStringStartsNotWith("\xEF\xBB\xBF", Csv::line(['x']));
	}
}
