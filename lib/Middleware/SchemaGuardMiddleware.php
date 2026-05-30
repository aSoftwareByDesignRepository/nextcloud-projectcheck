<?php

declare(strict_types=1);

/**
 * Repairs ProjectCheck schema before controllers run.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Middleware;

use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCA\ProjectCheck\Util\ErrorPageParams;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class SchemaGuardMiddleware extends Middleware
{
	public function __construct(
		private SchemaGuardService $schemaGuard,
		private IRequest $request,
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
		private CSPService $cspService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param object $controller
	 * @param string $methodName
	 */
	public function beforeController($controller, $methodName): void
	{
		if (!$this->isProjectCheckController($controller)) {
			return;
		}

		$this->schemaGuard->ensureReady();
	}

	private function isProjectCheckController(object $controller): bool
	{
		$ref = new \ReflectionClass($controller);
		while ($ref !== false) {
			if ($ref->getNamespaceName() === 'OCA\\ProjectCheck\\Controller') {
				return true;
			}
			$ref = $ref->getParentClass();
		}

		return false;
	}

	/**
	 * @param object $controller
	 * @param string $methodName
	 * @return \OCP\AppFramework\Http\Response|mixed
	 */
	public function afterException($controller, $methodName, \Exception $exception)
	{
		if (!$exception instanceof SchemaRepairFailedException) {
			throw $exception;
		}

		$this->logger->error('ProjectCheck: request blocked due to schema repair failure', [
			'exception' => $exception,
			'path' => $this->request->getPathInfo() ?? '',
		]);

		$l = $this->l10nFactory->get('projectcheck');
		$userMessage = $l->t(
			'ProjectCheck could not initialize its database tables. Please contact an administrator.'
		);

		$path = (string) ($this->request->getPathInfo() ?? '');
		$isApi = str_contains($path, '/api/') || str_starts_with($path, '/ocs/');
		$accept = strtolower((string) $this->request->getHeader('Accept'));
		$contentType = strtolower((string) $this->request->getHeader('Content-Type'));
		$xRequestedWith = strtolower((string) $this->request->getHeader('X-Requested-With'));
		$wantsJson = str_contains($accept, 'application/json')
			|| str_contains($contentType, 'application/json')
			|| $xRequestedWith === 'xmlhttprequest';

		if ($isApi || $wantsJson || $this->request->getMethod() !== 'GET') {
			return new JSONResponse([
				'error' => 'schema_repair_failed',
				'message' => $userMessage,
			], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		$response = new TemplateResponse(
			'projectcheck',
			'error',
			ErrorPageParams::build(
				$l,
				$this->urlGenerator,
				$userMessage,
				'projectcheck.dashboard.index',
				$l->t('Back to Dashboard'),
			),
			'guest'
		);
		$response->setStatus(Http::STATUS_SERVICE_UNAVAILABLE);
		$response->renderAs('guest');

		return $this->cspService->applyPolicyWithNonce($response, 'guest');
	}
}
