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
use OCP\Util;

class ProjectFileService
{
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
		$this->assertProjectAccess($projectId, $userId);

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

		$stored = [];
		foreach ($uploads as $upload) {
			if (empty($upload['tmp_name']) || $upload['error'] !== UPLOAD_ERR_OK) {
				continue;
			}

			$stored[] = $this->storeSingleFile($projectId, $upload, $userId);
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

		$file = $folder->newFile($uniqueName);
		$content = fopen($upload['tmp_name'], 'rb');
		$file->putContent(stream_get_contents($content));
		fclose($content);

		$entity = new ProjectFile();
		$entity->setProjectId($projectId);
		$entity->setStoragePath('project_files/' . $projectId . '/' . $uniqueName);
		$entity->setDisplayName($originalName);
		$mimeType = $upload['type'] ?? null;
		if (!$mimeType && !empty($upload['tmp_name'])) {
			$mimeType = Util::getMimeType($upload['tmp_name']);
		}
		$entity->setMimeType($mimeType ?: Util::getMimeType($originalName));
		$entity->setSize((int) ($upload['size'] ?? 0));
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
		$name = trim($name);
		return $name === '' ? 'upload.bin' : $name;
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

