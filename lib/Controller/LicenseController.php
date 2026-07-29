<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Exception\LicenseException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\LicenseService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class LicenseController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly AccessControlService $access,
		private readonly LicenseService $license,
		private readonly IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function show(): JSONResponse
	{
		try {
			$this->requireAppAdmin();
			return new JSONResponse($this->license->status());
		} catch (LicenseException $e) {
			return $this->fromLicenseException($e);
		}
	}

	#[NoAdminRequired]
	public function apply(): JSONResponse
	{
		try {
			$uid = $this->requireAppAdmin();
			$key = $this->request->getParam('key');
			if (!is_string($key) || trim($key) === '') {
				return new JSONResponse([
					'ok' => false,
					'error' => 'license_invalid',
					'message' => 'A license key is required.',
				], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			return new JSONResponse($this->license->apply($uid, $key));
		} catch (LicenseException $e) {
			return $this->fromLicenseException($e);
		}
	}

	#[NoAdminRequired]
	public function remove(): JSONResponse
	{
		try {
			$this->requireAppAdmin();
			return new JSONResponse($this->license->remove());
		} catch (LicenseException $e) {
			return $this->fromLicenseException($e);
		}
	}

	#[NoAdminRequired]
	public function seats(): JSONResponse
	{
		try {
			$this->requireAppAdmin();
			$limit = (int)$this->request->getParam('limit', 50);
			$offset = (int)$this->request->getParam('offset', 0);
			return new JSONResponse($this->license->listSeats($limit, $offset));
		} catch (LicenseException $e) {
			return $this->fromLicenseException($e);
		}
	}

	#[NoAdminRequired]
	public function assignSeat(): JSONResponse
	{
		try {
			$uid = $this->requireAppAdmin();
			$result = $this->license->assignSeat($uid, $this->request->getParam('userId'));
			return new JSONResponse(
				$result['seat'],
				$result['created'] ? Http::STATUS_CREATED : Http::STATUS_OK,
			);
		} catch (LicenseException $e) {
			return $this->fromLicenseException($e);
		}
	}

	#[NoAdminRequired]
	public function removeSeat(string $uid): JSONResponse
	{
		try {
			$this->requireAppAdmin();
			$this->license->removeSeat($uid);
			return new JSONResponse(['deleted' => true]);
		} catch (LicenseException $e) {
			return $this->fromLicenseException($e);
		}
	}

	private function requireAppAdmin(): string
	{
		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';
		if ($uid === '' || !$this->access->canManageAppConfiguration($uid)) {
			throw new LicenseException('access_denied', 'App admin required.', 403);
		}
		return $uid;
	}

	private function fromLicenseException(LicenseException $e): JSONResponse
	{
		return new JSONResponse([
			'ok' => false,
			'error' => $e->getErrorCode(),
			'message' => $e->getMessage(),
		], $e->getHttpStatus());
	}
}
