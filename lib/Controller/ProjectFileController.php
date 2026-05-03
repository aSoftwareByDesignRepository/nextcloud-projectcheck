<?php

declare(strict_types=1);

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
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\IL10N;

class ProjectFileController extends Controller
{
	private ProjectFileService $fileService;
	private IUserSession $userSession;
	private IURLGenerator $urlGenerator;
	private IL10N $l;

	public function __construct(
		string $appName,
		IRequest $request,
		ProjectFileService $fileService,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
		IL10N $l
	) {
		parent::__construct($appName, $request);
		$this->fileService = $fileService;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
	}

	/**
	 * Upload one or more files for a project.
	 *
	 * Rate-limited per user to prevent storage exhaustion / abuse uploads
	 * (storage I/O is the most expensive write path in this app).
	 *
	 * @return Response
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function upload(int $projectId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$uploads = $this->request->getUploadedFile('project_files');
			$this->fileService->addFilesFromUpload($projectId, $uploads ?? [], $user->getUID());
		} catch (\Throwable $e) {
			if ($this->request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
				return new DataResponse(['error' => $this->l->t('File upload failed. Please check your input and try again.')], 400);
			}
			$url = $this->request->getParam(
				'redirect',
				$this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $projectId])
			);
			return new RedirectResponse($url . '?message=error&error_text=' . urlencode($this->l->t('File upload failed. Please check your input and try again.')));
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
	 * List files for a project.
	 *
	 * Rate-limited to throttle enumeration of file metadata across projects.
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 120, period: 60)]
	public function list(int $projectId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
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
			return new DataResponse(['error' => $this->l->t('Could not load project files.')], 400);
		}
	}

	/**
	 * Download a project file.
	 *
	 * Rate-limited per user to throttle bulk download / scraping attempts.
	 * NoCSRFRequired is intentional: GET download links must be embeddable
	 * and idempotent.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 120, period: 60)]
	public function download(int $projectId, int $fileId)
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$projectFile = $this->fileService->getFile($projectId, $fileId, $user->getUID());
			$file = $this->fileService->resolveFile($projectFile);

			$mime = $projectFile->getMimeType() ?: 'application/octet-stream';
			// Inline rendering is only safe for content the browser cannot
			// execute. SVG/HTML/JS/etc. are forced to attachment to prevent
			// stored-XSS via uploaded files (defence in depth on top of the
			// upload-time MIME blocklist).
			$inlineSafe = preg_match(
				'#^(image/(jpeg|png|gif|webp|bmp|x-icon)|application/pdf|text/plain|text/csv)$#i',
				$mime
			) === 1;
			$disposition = $inlineSafe ? 'inline' : 'attachment';

			// Sanitize filename for header: ASCII fallback + RFC 5987 UTF-8.
			$displayName = (string)$projectFile->getDisplayName();
			$asciiName = preg_replace('/[^\x20-\x7E]+/', '_', $displayName) ?? 'download';
			$asciiName = addcslashes($asciiName, '"\\');
			$utf8Name = rawurlencode($displayName);

			$response = new FileDisplayResponse($file);
			$response->addHeader('Content-Type', $mime);
			$response->addHeader(
				'Content-Disposition',
				$disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . $utf8Name
			);
			// Defence-in-depth: never let a download be sniffed into a
			// dangerous type.
			$response->addHeader('X-Content-Type-Options', 'nosniff');

			return $response;
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $this->l->t('File not found or access denied.')], 404);
		}
	}

	/**
	 * Delete a project file.
	 *
	 * Rate-limited so a compromised session cannot mass-delete files in a burst.
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function delete(int $projectId, int $fileId): DataResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new DataResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		try {
			$this->fileService->deleteFile($projectId, $fileId, $user->getUID());
			return new DataResponse(['success' => true]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $this->l->t('Could not delete the file.')], 400);
		}
	}
}

