<?php

declare(strict_types=1);

/**
 * Service for handling project file uploads and access.
 *
 * Hardening notes (audit reference: AUDIT-FINDINGS.md A2/A3):
 *  - Server-side MIME detection (finfo) is authoritative; the client-reported
 *    type is only stored as advisory metadata after sanity checks.
 *  - Uploads are streamed from `tmp_name` into the app storage as a file
 *    pointer (no buffering of the full file in PHP memory).
 *  - Filenames are normalized with a strict allow-list and an executable /
 *    server-side-handler blocklist; double extensions like `evil.php.jpg`
 *    are reduced to a safe single extension.
 *  - Aggregate request size is capped on top of the per-file cap.
 *  - Storage paths are validated against traversal before being resolved.
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\ProjectFile;
use OCA\ProjectCheck\Db\ProjectFileMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IAppData;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use Psr\Log\LoggerInterface;
use OCP\IUserSession;

class ProjectFileService
{
	public const MAX_FILES_PER_UPLOAD = 20;
	public const MAX_FILE_SIZE_BYTES = 52428800; // 50 MiB per file
	public const MAX_REQUEST_BYTES = 209715200; // 200 MiB aggregate per request

	/**
	 * Filename extensions that must never be stored under any circumstance,
	 * even if the user "really" wants to upload them. These are file types
	 * which can be executed/served by a web server or which can carry
	 * server-side handlers (`.htaccess`).
	 */
	private const FORBIDDEN_EXTENSIONS = [
		'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
		'pl', 'pm', 'cgi', 'py', 'pyc', 'pyo', 'rb', 'sh', 'bash', 'zsh', 'ksh',
		'jsp', 'jspx', 'asp', 'aspx', 'cfm', 'cfc', 'do', 'action',
		'exe', 'dll', 'bat', 'cmd', 'com', 'scr', 'msi', 'msp', 'vbs', 'vbe',
		'wsf', 'wsh', 'ps1', 'psm1', 'ps1xml',
		'htaccess', 'htpasswd', 'ini', 'config', 'conf', 'env', 'lock',
	];

	/** Server-MIME types we refuse to accept regardless of extension. */
	private const FORBIDDEN_MIME_PREFIXES = [
		'application/x-php', 'application/x-httpd-php', 'text/x-php',
		'application/x-perl', 'application/x-python',
		'application/x-ruby', 'application/x-shellscript',
		'application/x-msdos-program', 'application/x-msdownload',
		'application/x-executable', 'application/vnd.microsoft.portable-executable',
	];

	private ProjectFileMapper $mapper;
	private ProjectService $projectService;
	private IAppData $appData;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		ProjectFileMapper $mapper,
		ProjectService $projectService,
		IAppDataFactory $appDataFactory,
		IUserSession $userSession,
		LoggerInterface $logger
	) {
		$this->mapper = $mapper;
		$this->projectService = $projectService;
		$this->appData = $appDataFactory->get('projectcheck');
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * @param int $projectId
	 * @param array $uploads from IRequest::getUploadedFile (can be single or array)
	 * @param string $userId
	 * @return ProjectFile[]
	 */
	public function addFilesFromUpload(int $projectId, array $uploads, string $userId): array
	{
		$this->assertProjectManage($projectId, $userId);

		if (empty($uploads)) {
			return [];
		}

		$uploads = $this->normalizeUploadsArray($uploads);

		if (count($uploads) > self::MAX_FILES_PER_UPLOAD) {
			throw new \InvalidArgumentException('Too many files in one upload request');
		}

		// Aggregate size cap (in addition to per-file cap)
		$aggregate = 0;
		foreach ($uploads as $u) {
			$aggregate += max(0, (int)($u['size'] ?? 0));
		}
		if ($aggregate > self::MAX_REQUEST_BYTES) {
			throw new \InvalidArgumentException('Total upload size exceeds the allowed limit');
		}

		$stored = [];
		$hadUploadError = false;
		$lastUploadError = null;
		foreach ($uploads as $upload) {
			$errorCode = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
			if ($errorCode !== UPLOAD_ERR_OK) {
				$hadUploadError = true;
				$lastUploadError = $this->mapUploadErrorCode($errorCode);
				continue;
			}
			if (empty($upload['tmp_name'])) {
				continue;
			}

			$stored[] = $this->storeSingleFile($projectId, $upload, $userId);
		}

		if ($stored === [] && $hadUploadError) {
			throw new \RuntimeException($lastUploadError ?? 'Upload failed');
		}

		return $stored;
	}

	/**
	 * Normalize PHP's split-array upload structure into a list of per-file entries.
	 *
	 * @param array<string,mixed> $uploads
	 * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
	 */
	private function normalizeUploadsArray(array $uploads): array
	{
		if (isset($uploads['tmp_name']) && is_array($uploads['tmp_name'])) {
			$normalized = [];
			foreach ($uploads['tmp_name'] as $idx => $tmpName) {
				$normalized[] = [
					'name' => (string)($uploads['name'][$idx] ?? ''),
					'type' => (string)($uploads['type'][$idx] ?? ''),
					'tmp_name' => (string)$tmpName,
					'error' => (int)($uploads['error'][$idx] ?? UPLOAD_ERR_NO_FILE),
					'size' => (int)($uploads['size'][$idx] ?? 0),
				];
			}
			return $normalized;
		}
		if (isset($uploads['tmp_name'])) {
			return [[
				'name' => (string)($uploads['name'] ?? ''),
				'type' => (string)($uploads['type'] ?? ''),
				'tmp_name' => (string)$uploads['tmp_name'],
				'error' => (int)($uploads['error'] ?? UPLOAD_ERR_NO_FILE),
				'size' => (int)($uploads['size'] ?? 0),
			]];
		}
		// Already a list?
		$out = [];
		foreach ($uploads as $u) {
			if (!is_array($u) || !isset($u['tmp_name'])) {
				continue;
			}
			$out[] = [
				'name' => (string)($u['name'] ?? ''),
				'type' => (string)($u['type'] ?? ''),
				'tmp_name' => (string)$u['tmp_name'],
				'error' => (int)($u['error'] ?? UPLOAD_ERR_NO_FILE),
				'size' => (int)($u['size'] ?? 0),
			];
		}
		return $out;
	}

	/**
	 * @param int $projectId
	 * @param string $userId
	 * @return ProjectFile[]
	 */
	public function listFiles(int $projectId, string $userId): array
	{
		$this->assertProjectAccess($projectId, $userId);

		return $this->mapper->findByProject($projectId);
	}

	/**
	 * @param int $projectId
	 * @param int $fileId
	 * @param string $userId
	 * @return ProjectFile
	 */
	public function getFile(int $projectId, int $fileId, string $userId): ProjectFile
	{
		$this->assertProjectAccess($projectId, $userId);

		$file = $this->mapper->find($fileId);
		if (!$file || (int)$file->getProjectId() !== $projectId) {
			throw new DoesNotExistException('File not found');
		}

		return $file;
	}

	/**
	 * @param int $projectId
	 * @param int $fileId
	 * @param string $userId
	 */
	public function deleteFile(int $projectId, int $fileId, string $userId): void
	{
		$this->assertProjectManage($projectId, $userId);

		$file = $this->getFile($projectId, $fileId, $userId);

		try {
			$appFile = $this->resolveFile($file);
			$appFile->delete();
		} catch (\Throwable $e) {
			$this->logger->warning('Could not delete file from storage: ' . $e->getMessage(), ['app' => 'projectcheck']);
		}

		$this->mapper->delete($file);
	}

	/**
	 * Resolve a stored ProjectFile to the underlying ISimpleFile.
	 *
	 * The stored path follows the rigid layout `project_files/<projectId>/<basename>`.
	 * We re-validate every component to guard against any historical/migrated
	 * row that might contain unexpected separators.
	 *
	 * @throws \RuntimeException when the path is malformed.
	 */
	public function resolveFile(ProjectFile $projectFile): ISimpleFile
	{
		$path = (string)$projectFile->getStoragePath();
		$parts = explode('/', $path);
		if (count($parts) !== 3) {
			throw new \RuntimeException('Invalid file path');
		}
		[$root, $folder, $name] = $parts;
		if ($root !== 'project_files') {
			throw new \RuntimeException('Invalid storage root');
		}
		if ($folder === '' || $folder !== (string)(int)$folder) {
			throw new \RuntimeException('Invalid project folder');
		}
		if ($name === '' || str_contains($name, '/') || str_contains($name, "\0") || $name === '.' || $name === '..') {
			throw new \RuntimeException('Invalid file name');
		}

		$rootFolder = $this->appData->getFolder($root);
		$projectFolder = $rootFolder->getFolder($folder);
		return $projectFolder->getFile($name);
	}

	/**
	 * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $upload
	 */
	private function storeSingleFile(int $projectId, array $upload, string $userId): ProjectFile
	{
		$tmpName = $upload['tmp_name'];
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			throw new \InvalidArgumentException('Invalid uploaded file');
		}

		$size = $upload['size'];
		if ($size <= 0) {
			throw new \InvalidArgumentException('Empty upload is not allowed');
		}
		if ($size > self::MAX_FILE_SIZE_BYTES) {
			throw new \InvalidArgumentException('File is too large');
		}
		// Cross-check with the actual size on disk - PHP's reported size is
		// derived from headers and can be wrong if the upload was truncated.
		$actualSize = @filesize($tmpName);
		if ($actualSize === false || $actualSize <= 0) {
			throw new \InvalidArgumentException('Could not determine uploaded file size');
		}
		if ($actualSize > self::MAX_FILE_SIZE_BYTES) {
			throw new \InvalidArgumentException('File is too large');
		}

		// Server-side MIME detection is authoritative.
		$mimeType = $this->detectServerMimeType($tmpName);
		foreach (self::FORBIDDEN_MIME_PREFIXES as $blocked) {
			if (str_starts_with($mimeType, $blocked)) {
				throw new \InvalidArgumentException('This file type is not allowed');
			}
		}

		$cleanName = $this->sanitizeFileName($upload['name']);
		$uniqueName = $this->buildUniqueStorageName($cleanName);

		$folder = $this->getProjectFolder($projectId);

		$stream = @fopen($tmpName, 'rb');
		if ($stream === false) {
			throw new \RuntimeException('Could not read uploaded file');
		}

		try {
			$file = $folder->newFile($uniqueName);
			// putContent supports a resource argument and streams it without
			// buffering the entire payload into a PHP string.
			$file->putContent($stream);
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}

		$entity = new ProjectFile();
		$entity->setProjectId($projectId);
		$entity->setStoragePath('project_files/' . $projectId . '/' . $uniqueName);
		$entity->setDisplayName($cleanName);
		$entity->setMimeType($mimeType);
		$entity->setSize((int)$actualSize);
		$entity->setUploadedBy($userId);
		$entity->setCreatedAt(new \DateTime());

		return $this->mapper->insert($entity);
	}

	private function getProjectFolder(int $projectId): ISimpleFolder
	{
		try {
			$root = $this->appData->getFolder('project_files');
		} catch (\Throwable $e) {
			$root = $this->appData->newFolder('project_files');
		}

		try {
			return $root->getFolder((string)$projectId);
		} catch (\Throwable $e) {
			return $root->newFolder((string)$projectId);
		}
	}

	/**
	 * Sanitize a user-provided filename and reduce dangerous double extensions
	 * such as `evil.php.jpg` -> `evil.jpg`. Returns a safe display name.
	 *
	 * Steps:
	 *  1. Strip control characters and path separators.
	 *  2. Strip leading dots so we never produce hidden / dotfiles.
	 *  3. Drop any extension segment that is in the forbidden list.
	 *  4. Limit the total length to 200 bytes (well below the 255 column cap).
	 *  5. Ensure a non-empty display name.
	 */
	public function sanitizeFileName(string $name): string
	{
		$name = preg_replace('/[\\\\\\/]+/', '-', $name) ?? $name;
		$name = preg_replace('/[\x00-\x1f\x7f]+/u', '', $name) ?? $name;
		$name = preg_replace('/\s+/u', ' ', $name) ?? $name;
		$name = trim($name);
		$name = ltrim($name, '.');
		if ($name === '') {
			return 'upload.bin';
		}

		$parts = explode('.', $name);
		if (count($parts) > 1) {
			$base = array_shift($parts);
			$safeExt = [];
			foreach ($parts as $segment) {
				$segLower = strtolower($segment);
				if ($segLower === '' || in_array($segLower, self::FORBIDDEN_EXTENSIONS, true)) {
					continue;
				}
				if (!preg_match('/^[a-z0-9]{1,16}$/i', $segment)) {
					continue;
				}
				$safeExt[] = $segment;
			}
			// Keep at most one trailing extension to prevent polyglot tricks
			if ($safeExt !== []) {
				$ext = array_pop($safeExt);
				$name = $base . '.' . $ext;
			} else {
				$name = $base;
			}
		}

		// Neutralise bare server-side handler names like `.htaccess`,
		// `.htpasswd`, `.env`, etc. The leading dot is already stripped above
		// so what's left is e.g. `htaccess` - if that matches a forbidden
		// token in isolation we replace the entire filename with the safe
		// fallback rather than store something that looks dangerous.
		if (in_array(strtolower($name), self::FORBIDDEN_EXTENSIONS, true)) {
			$name = 'upload.bin';
		}

		if (strlen($name) > 200) {
			$name = substr($name, 0, 200);
		}
		if ($name === '' || $name === '.' || $name === '..') {
			$name = 'upload.bin';
		}
		return $name;
	}

	private function buildUniqueStorageName(string $cleanName): string
	{
		$prefix = bin2hex(random_bytes(8));
		// Strip any character not safe for storage filenames.
		$safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $cleanName) ?? $cleanName;
		if ($safe === '' || $safe === '.' || $safe === '..') {
			$safe = 'upload.bin';
		}
		return $prefix . '_' . $safe;
	}

	private function mapUploadErrorCode(int $errorCode): string
	{
		return match ($errorCode) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large',
			UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file',
			UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension',
			default => 'Upload failed',
		};
	}

	/**
	 * Detect MIME type using the server's finfo. Falls back to a safe
	 * generic type when finfo is unavailable so we never leak the
	 * client-claimed type into authoritative metadata.
	 */
	public function detectServerMimeType(string $tmpName): string
	{
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				try {
					$detected = finfo_file($finfo, $tmpName);
					if (is_string($detected) && $detected !== '') {
						return strtolower($detected);
					}
				} finally {
					finfo_close($finfo);
				}
			}
		}
		return 'application/octet-stream';
	}

	private function assertProjectAccess(int $projectId, string $userId): void
	{
		if (!$this->projectService->canUserAccessProject($userId, $projectId)) {
			throw new \RuntimeException('Access denied');
		}
	}

	private function assertProjectManage(int $projectId, string $userId): void
	{
		if (!$this->projectService->canUserEditProject($userId, $projectId)) {
			throw new \RuntimeException('Access denied');
		}
	}
}
