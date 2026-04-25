<?php

declare(strict_types=1);

/**
 * Enforces ProjectCheck app access for all app controllers.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Middleware;

use OCA\ProjectCheck\Exception\AppAccessDeniedException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class AppAccessMiddleware extends Middleware
{
	public function __construct(
		private IUserSession $userSession,
		private AccessControlService $accessControl,
		private IRequest $request,
		private IURLGenerator $urlGenerator,
		private IFactory $l10nFactory,
		private LoggerInterface $logger
	) {
	}

	/**
	 * @param object $controller
	 * @param string $methodName
	 */
	public function beforeController($controller, $methodName): void
	{
		$class = is_object($controller) ? get_class($controller) : '';
		if (!str_starts_with($class, 'OCA\\ProjectCheck\\Controller\\')) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$uid = $user->getUID();
		if ($this->accessControl->canUseApp($uid)) {
			return;
		}

		$this->logger->warning('projectcheck app access denied', [
			'userId' => $uid,
			'path' => $this->request->getPathInfo() ?? '',
		]);

		throw new AppAccessDeniedException('app_access_denied');
	}

	/**
	 * @param object $controller
	 * @param string $methodName
	 * @return \OCP\AppFramework\Http\Response|mixed
	 */
	public function afterException($controller, $methodName, \Exception $exception)
	{
		if (!$exception instanceof AppAccessDeniedException) {
			throw $exception;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			throw $exception;
		}

		$path = (string) ($this->request->getPathInfo() ?? '');
		$isApi = str_contains($path, '/api/') || str_starts_with($path, '/ocs/');
		$wantsJson = $this->request->getHeader('Accept') === 'application/json'
			|| str_starts_with((string) $this->request->getHeader('Content-Type'), 'application/json');

		if ($isApi || $wantsJson || $this->request->getMethod() !== 'GET') {
			return new JSONResponse([
				'error' => 'app_access_denied',
				'message' => 'app_access_denied',
			], Http::STATUS_FORBIDDEN);
		}

		$l = $this->l10nFactory->get(AccessControlService::APP_ID);
		$response = new TemplateResponse(
			AccessControlService::APP_ID,
			'access-denied',
			[
				'l' => $l,
				'message' => $l->t('You do not have access to ProjectCheck.'),
				'homeUrl' => $this->urlGenerator->linkToDefaultPageUrl(),
			]
		);
		$response->setStatus(Http::STATUS_FORBIDDEN);
		$response->renderAs(TemplateResponse::RENDER_AS_USER);
		return $response;
	}
}
