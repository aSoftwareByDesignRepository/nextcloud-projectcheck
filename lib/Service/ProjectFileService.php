<?php

declare(strict_types=1);

/**
 * Service for handling project file uploads and access
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
use OCP\IRequest;
use OCP\IUserSession;

class ProjectFileService
{
	private const MAX_FILES_PER_UPLOAD = 20;
	private const MAX_FILE_SIZE_BYTES = 52428800; // 50 MiB per file

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

		// Normalize uploaded files array (handle PHP's multi-file structure)
		if (isset($uploads['tmp_name']) && is_array($uploads['tmp_name'])) {
			$normalized = [];
			foreach ($uploads['tmp_name'] as $idx => $tmpName) {
				$normalized[] = [
					'name' => $uploads['name'][$idx] ?? '',
					'type' => $uploads['type'][$idx] ?? '',
					'tmp_name' => $tmpName,
					'error' => $uploads['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
					'size' => $uploads['size'][$idx] ?? 0,
				];
			}
			$uploads = $normalized;
		} elseif (isset($uploads['tmp_name'])) {
			$uploads = [$uploads];
		}

		if (count($uploads) > self::MAX_FILES_PER_UPLOAD) {
			throw new \InvalidArgumentException('Too many files in one upload request');
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
	 * @param ProjectFile $projectFile
	 * @return ISimpleFile
	 */
	public function resolveFile(ProjectFile $projectFile): ISimpleFile
	{
		$path = $projectFile->getStoragePath();
		$parts = explode('/', $path, 3);
		if (count($parts) < 3) {
			throw new \Exception('Invalid file path');
		}
		$folderPath = $parts[0] ?? '';
		$projectFolder = $parts[1] ?? '';
		$fileName = $parts[2] ?? '';

		$root = $this->appData->getFolder($folderPath);
		$projectFolder = $root->getFolder($projectFolder);
		return $projectFolder->getFile($fileName);
	}

	private function storeSingleFile(int $projectId, array $upload, string $userId): ProjectFile
	{
		$folder = $this->getProjectFolder($projectId);

		$originalName = $upload['name'] ?? 'upload.bin';
		$cleanName = $this->sanitizeFileName($originalName);
		$uniqueName = uniqid('file_', true) . '_' . $cleanName;

		$tmpName = (string)($upload['tmp_name'] ?? '');
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			throw new \InvalidArgumentException('Invalid uploaded file');
		}

		$size = (int)($upload['size'] ?? 0);
		if ($size < 0) {
			throw new \InvalidArgumentException('Invalid file size');
		}
		if ($size > self::MAX_FILE_SIZE_BYTES) {
			throw new \InvalidArgumentException('File is too large');
		}

		$file = $folder->newFile($uniqueName);
		$content = @fopen($tmpName, 'rb');
		if ($content === false) {
			throw new \RuntimeException('Could not read uploaded file');
		}
		$rawContent = stream_get_contents($content);
		fclose($content);
		if ($rawContent === false) {
			throw new \RuntimeException('Could not read uploaded file');
		}
		$file->putContent($rawContent);

		$entity = new ProjectFile();
		$entity->setProjectId($projectId);
		$entity->setStoragePath('project_files/' . $projectId . '/' . $uniqueName);
		$entity->setDisplayName($originalName);
		$mimeType = $this->detectMimeType(
			$tmpName,
			is_string($upload['type'] ?? null) ? (string)$upload['type'] : null
		);
		$entity->setMimeType($mimeType);
		$entity->setSize($size);
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

	private function sanitizeFileName(string $name): string
	{
		// Keep it readable while removing path separators
		$name = preg_replace('/[\\\\\\/]+/', '-', $name);
		$name = preg_replace('/[\x00-\x1f\x7f]+/', '', $name);
		$name = trim($name);
		if ($name === '') {
			return 'upload.bin';
		}

		return substr($name, 0, 255);
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

	private function detectMimeType(string $tmpName, ?string $reportedMimeType): string
	{
		if ($reportedMimeType !== null && trim($reportedMimeType) !== '') {
			return trim($reportedMimeType);
		}

		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				$detected = finfo_file($finfo, $tmpName);
				finfo_close($finfo);
				if (is_string($detected) && $detected !== '') {
					return $detected;
				}
			}
		}

		return 'application/octet-stream';
	}

	private function assertProjectAccess(int $projectId, string $userId): void
	{
		if (!$this->projectService->canUserAccessProject($userId, $projectId)) {
			throw new \Exception('Access denied');
		}
	}

	private function assertProjectManage(int $projectId, string $userId): void
	{
		if (!$this->projectService->canUserEditProject($userId, $projectId)) {
			throw new \Exception('Access denied');
		}
	}
}

