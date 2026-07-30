<?php

declare(strict_types=1);

/**
 * Enforces ProjectCheck app access for all app controllers and maps domain
 * exceptions on mobile JSON routes to the SERVER-MOBILE-API error envelope.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Middleware;

use OCA\ProjectCheck\Controller\HealthController;
use OCA\ProjectCheck\Exception\AppAccessDeniedException;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Exception\PaymentRequiredException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
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
	private const HTTP_PAYMENT_REQUIRED = 402;

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

		// Public health must stay reachable even with a logged-in user who lacks app ACL.
		if ($class === HealthController::class) {
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
		// Registered non-globally (app container only), so every call here
		// originates from a ProjectCheck controller. Dispatch strictly on our
		// own domain exception types; anything unknown is rethrown below.
		$l = $this->l10nFactory->get(AccessControlService::APP_ID);

		if ($exception instanceof MobileGateException) {
			return $this->mobileEnvelope(
				$exception->getErrorCode(),
				$this->gateMessage($exception->getErrorCode(), $l),
				self::HTTP_PAYMENT_REQUIRED,
			);
		}
		if ($exception instanceof PaymentRequiredException) {
			return $this->mobileEnvelope(
				$this->mapPaymentCode($exception->getErrorCode()),
				$exception->getMessage(),
				self::HTTP_PAYMENT_REQUIRED,
			);
		}
		if ($exception instanceof MobileApiException) {
			$body = [
				'error' => [
					'code' => $exception->getErrorCode(),
					'message' => $exception->getMessage(),
				],
			];
			if ($exception->getDetails() !== []) {
				$body['error']['details'] = $exception->getDetails();
			}
			return new JSONResponse($body, $exception->getHttpStatus());
		}
		if ($exception instanceof PermissionDeniedException && $this->isMobilePath()) {
			return $this->mobileEnvelope(
				'forbidden',
				$l->t('You do not have permission for this action.'),
				Http::STATUS_FORBIDDEN,
			);
		}

		if (!$exception instanceof AppAccessDeniedException) {
			throw $exception;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			throw $exception;
		}

		$path = (string) ($this->request->getPathInfo() ?? '');
		$isApi = str_contains($path, '/api/')
			|| str_contains($path, '/mobile/')
			|| str_starts_with($path, '/ocs/');
		$accept = strtolower((string) $this->request->getHeader('Accept'));
		$contentType = strtolower((string) $this->request->getHeader('Content-Type'));
		$xRequestedWith = strtolower((string) $this->request->getHeader('X-Requested-With'));
		$wantsJson = str_contains($accept, 'application/json')
			|| str_contains($contentType, 'application/json')
			|| $xRequestedWith === 'xmlhttprequest';

		if ($isApi || $wantsJson || $this->request->getMethod() !== 'GET') {
			if ($this->isMobilePath()) {
				return $this->mobileEnvelope(
					'forbidden',
					$l->t('You do not have access to ProjectCheck.'),
					Http::STATUS_FORBIDDEN,
				);
			}
			return new JSONResponse([
				'error' => $l->t('You do not have access to ProjectCheck.'),
				'message' => $l->t('You do not have access to ProjectCheck.'),
				'code' => 'app_access_denied',
			], Http::STATUS_FORBIDDEN);
		}
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

	private function isMobilePath(): bool
	{
		$path = (string)($this->request->getPathInfo() ?? '');
		return str_contains($path, '/mobile/');
	}

	private function mobileEnvelope(string $code, string $message, int $status): JSONResponse
	{
		return new JSONResponse(['error' => ['code' => $code, 'message' => $message]], $status);
	}

	private function mapPaymentCode(string $code): string
	{
		return match ($code) {
			'LICENSE_REQUIRED' => 'license_missing',
			'LICENSE_EXPIRED' => 'license_expired',
			'NO_MOBILE_SEAT' => 'seat_required',
			'SEAT_LIMIT_EXCEEDED' => 'seat_limit_exceeded',
			default => strtolower($code),
		};
	}

	private function gateMessage(string $code, \OCP\IL10N $l): string
	{
		return match ($code) {
			'license_missing' => $l->t('No mobile license is stored on this server.'),
			'license_expired' => $l->t('The mobile license has expired.'),
			'seat_required' => $l->t('You do not have a mobile seat assigned.'),
			'seat_limit_exceeded' => $l->t('Your mobile seat is above the licensed limit.'),
			default => $l->t('ProjectCheck Mobile is not licensed for this user.'),
		};
	}
}
