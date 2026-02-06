<?php

/**
 * Controller for project file management
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Service\ProjectFileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;

class ProjectFileController extends Controller
{
	private ProjectFileService $fileService;
	private IUserSession $userSession;
	private IURLGenerator $urlGenerator;

	public function __construct(
		string $appName,
		IRequest $request,
		ProjectFileService $fileService,
		IUserSession $userSession,
		IURLGenerator $urlGenerator
	) {
		parent::__construct($appName, $request);
		$this->fileService = $fileService;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Upload one or more files for a project
	 *
	 * @return Response
	 */
	#[NoAdminRequired]
	public function upload(int $projectId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$uploads = $this->request->getUploadedFile('project_files');
			$this->fileService->addFilesFromUpload($projectId, $uploads ?? [], $user->getUID());
		} catch (\Throwable $e) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $e->getMessage()], 400);
			}
			$url = $this->request->getParam(
				'redirect',
				$this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $projectId])
			);
			return new RedirectResponse($url . '?message=error&error_text=' . urlencode($e->getMessage()));
		}

		if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
			return new DataResponse(['success' => true]);
		}

		$url = $this->request->getParam(
			'redirect',
			$this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $projectId])
		);
		return new RedirectResponse($url . '?message=success');
	}

	/**
	 * List files for a project
	 */
	#[NoAdminRequired]
	public function list(int $projectId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$files = $this->fileService->listFiles($projectId, $user->getUID());

			return new DataResponse([
				'success' => true,
				'files' => array_map(static function ($file) {
					return [
						'id' => $file->getId(),
						'name' => $file->getDisplayName(),
						'mime_type' => $file->getMimeType(),
						'size' => $file->getSize(),
						'uploaded_by' => $file->getUploadedBy(),
						'created_at' => $file->getCreatedAt()?->format(\DateTime::ATOM),
					];
				}, $files),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}

	/**
	 * Download a project file
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function download(int $projectId, int $fileId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$projectFile = $this->fileService->getFile($projectId, $fileId, $user->getUID());
			$file = $this->fileService->resolveFile($projectFile);

			$response = new FileDisplayResponse($file);
			$response->addHeader('Content-Type', $projectFile->getMimeType() ?: 'application/octet-stream');
			// Keep inline display but provide filename for compatibility
			$response->addHeader(
				'Content-Disposition',
				'inline; filename="' . addcslashes($projectFile->getDisplayName(), '"\\') . '"'
			);

			return $response;
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], 404);
		}
	}

	/**
	 * Delete a project file
	 */
	#[NoAdminRequired]
	public function delete(int $projectId, int $fileId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => 'User not authenticated'], 401);
		}

		try {
			$this->fileService->deleteFile($projectId, $fileId, $user->getUID());
			return new DataResponse(['success' => true]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], 400);
		}
	}
}

