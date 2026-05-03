<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\ProjectFileService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Db\ProjectFileMapper;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Unit tests for {@see ProjectFileService}.
 *
 * Audit reference: AUDIT-FINDINGS A2/A3 (upload hardening) and the previously
 * "no dedicated tests" entry in the test coverage matrix.
 *
 * The service has heavy I/O dependencies (NC IAppData), so we exercise the
 * pure behavioural surface (filename sanitization, MIME detection, dispatch
 * defences) without touching the actual storage backend.
 */
class ProjectFileServiceTest extends TestCase {
	private ProjectFileService $service;

	protected function setUp(): void {
		parent::setUp();
		$mapper = $this->createMock(ProjectFileMapper::class);
		$projectService = $this->createMock(ProjectService::class);
		$appData = $this->createMock(IAppData::class);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);
		$userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->service = new ProjectFileService($mapper, $projectService, $factory, $userSession, $logger);
	}

	public function testSanitizeFileNameStripsControlAndPathSeparators(): void {
		$out = $this->service->sanitizeFileName("..\\..\\evil\x00name.jpg");
		// Path traversal segments must collapse: backslashes -> dashes,
		// NUL byte stripped, embedded ".." reduced so the polyglot reconstruction
		// is impossible. Only the .jpg trailing extension survives, attached
		// to the cleaned base.
		$this->assertStringEndsWith('.jpg', $out);
		$this->assertStringNotContainsString("\x00", $out);
		$this->assertStringNotContainsString('\\', $out);
		$this->assertStringNotContainsString('..', $out);
		$this->assertStringNotContainsString('/', $out);
	}

	public function testSanitizeFileNameDropsForbiddenServerExtensions(): void {
		// Double-extension polyglot: server should never see `.php` survive.
		$this->assertSame('evil.jpg', $this->service->sanitizeFileName('evil.php.jpg'));
		$this->assertSame('inner', $this->service->sanitizeFileName('inner.php'));
		// Bare server-handler names get neutralised entirely, NOT stored as
		// `htaccess`. Same for `.env`, `.htpasswd`, etc.
		$this->assertSame('upload.bin', $this->service->sanitizeFileName('.htaccess'));
		$this->assertSame('upload.bin', $this->service->sanitizeFileName('.HTACCESS'));
		$this->assertSame('upload.bin', $this->service->sanitizeFileName('.env'));
	}

	public function testSanitizeFileNameKeepsMultipleSafeExtensionsAsSingleTrailing(): void {
		// "report.tar.gz" -> only the last safe extension is kept to prevent
		// polyglot tricks while still producing a useful filename.
		$out = $this->service->sanitizeFileName('report.tar.gz');
		$this->assertSame('report.gz', $out);
	}

	public function testSanitizeFileNameRejectsDotfiles(): void {
		$this->assertSame('upload.bin', $this->service->sanitizeFileName('.'));
		$this->assertSame('upload.bin', $this->service->sanitizeFileName('..'));
		$this->assertSame('upload.bin', $this->service->sanitizeFileName(''));
		$this->assertSame('upload.bin', $this->service->sanitizeFileName(str_repeat(' ', 30)));
	}

	public function testSanitizeFileNameTruncatesAt200Characters(): void {
		$long = str_repeat('a', 250) . '.txt';
		$out = $this->service->sanitizeFileName($long);
		$this->assertLessThanOrEqual(200, strlen($out));
	}

	public function testDetectServerMimeTypeReturnsLowercase(): void {
		// finfo on a temp PNG-shaped buffer should give image/png.
		$tmp = tmpfile();
		// PNG signature
		fwrite($tmp, "\x89PNG\r\n\x1a\n");
		$path = stream_get_meta_data($tmp)['uri'];
		$mime = $this->service->detectServerMimeType($path);
		fclose($tmp);
		$this->assertSame(strtolower($mime), $mime, 'MIME type must be lowercase');
		// finfo may report image/png or application/octet-stream depending
		// on libmagic db; just ensure it is a sane non-empty string.
		$this->assertNotSame('', $mime);
	}

	public function testForbiddenExtensionsListIsCovered(): void {
		// We use reflection to assert critical execution risks are blocked
		// at sanitize time - this is a regression net for future audit cycles.
		$ref = new ReflectionClass(ProjectFileService::class);
		$prop = $ref->getReflectionConstant('FORBIDDEN_EXTENSIONS');
		$list = $prop->getValue();
		foreach (['php', 'phtml', 'phar', 'sh', 'exe', 'jsp', 'asp', 'htaccess'] as $must) {
			$this->assertContains($must, $list, "Forbidden list must contain '$must'");
		}
	}
}
