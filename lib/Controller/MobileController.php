<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\MobileGateService;
use OCA\ProjectCheck\Service\MobileSettlementService;
use OCP\App\IAppManager;
use OCP\Authentication\Exceptions\ExpiredTokenException;
use OCP\Authentication\Exceptions\InvalidTokenException;
use OCP\Authentication\Exceptions\WipeTokenException;
use OCP\Authentication\Token\IProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Mobile companion API v1 (SERVER-MOBILE-API) + v1.1 settlement / idempotent create.
 *
 * CSRF posture: NoCSRFRequired on routes — official app uses Basic app-password.
 * Mutations accept passesCSRFCheck OR Basic credentials that checkPassword as
 * the current session user. Forged Bearer/Basic headers must not skip CSRF.
 */
class MobileController extends Controller
{
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly MobileGateService $gate,
		private readonly MobileBookingService $booking,
		private readonly MobileSettlementService $settlement,
		private readonly IAppManager $appManager,
		private readonly IUserManager $userManager,
		private readonly IProvider $tokenProvider,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function bootstrap(): JSONResponse
	{
		$uid = $this->requireUid();
		$displayName = $this->userSession->getUser()?->getDisplayName() ?? $uid;
		$version = $this->appManager->getAppVersion(Application::APP_ID, false);
		$pushAvailable = $this->appManager->isEnabledForUser('notifications');
		$canSettle = $this->settlement->actorCanSettleAnything($uid);
		return new JSONResponse($this->gate->bootstrapPayload(
			$uid,
			$displayName,
			$version,
			$pushAvailable,
			$canSettle,
		));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function projects(?string $q = null, ?string $limit = null): JSONResponse
	{
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		$limitInt = $limit !== null && is_numeric($limit) ? (int)$limit : null;
		return new JSONResponse($this->booking->listProjectsForBooking($uid, $q, $limitInt));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function resolveHourlyRate(int $id, ?string $date = null, ?string $employeeUserId = null): JSONResponse
	{
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		return new JSONResponse($this->booking->resolveHourlyRate($uid, $id, $date, $employeeUserId));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function timeEntries(
		?string $from = null,
		?string $to = null,
		?string $billingStatus = null,
	): JSONResponse {
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		return new JSONResponse($this->booking->listMyEntries($uid, $from, $to, $billingStatus));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function createTimeEntry(): JSONResponse
	{
		$this->assertSafeMutationChannel();
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		return new JSONResponse($this->booking->createEntry($uid, $this->jsonBody()), 201);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function updateTimeEntry(int $id): JSONResponse
	{
		$this->assertSafeMutationChannel();
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		return new JSONResponse($this->booking->updateEntry($uid, $id, $this->jsonBody()));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function deleteTimeEntry(int $id): JSONResponse
	{
		$this->assertSafeMutationChannel();
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		$this->booking->deleteEntry($uid, $id);
		return new JSONResponse(['ok' => true]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function settlementEntries(
		?string $from = null,
		?string $to = null,
		?string $billingStatus = null,
	): JSONResponse {
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		return new JSONResponse($this->settlement->listSettleableEntries($uid, $from, $to, $billingStatus));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function changeEntryBilling(int $id): JSONResponse
	{
		$this->assertSafeMutationChannel();
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		$body = $this->jsonBody();
		$target = (string)($body['target'] ?? '');
		return new JSONResponse($this->settlement->changeEntryStatus($uid, $id, $target));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function projectSettlementPreview(int $id): JSONResponse
	{
		$this->assertSafeMutationChannel();
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		$result = $this->settlement->previewProjectSettle($uid, $id, $this->jsonBody());
		return new JSONResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function projectSettlementApply(int $id): JSONResponse
	{
		$this->assertSafeMutationChannel();
		$uid = $this->requireUid();
		$this->gate->assertGatePassed($uid);
		$result = $this->settlement->applyProjectSettle($uid, $id, $this->jsonBody());
		return new JSONResponse($result);
	}

	private function requireUid(): string
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new MobileApiException('unauthenticated', 'Authentication required.', 401);
		}
		return $user->getUID();
	}

	/**
	 * Mutations accept a valid CSRF token OR Basic credentials that
	 * checkPassword as the current user. Forged Bearer/Basic headers must not
	 * skip CSRF for a cookie session.
	 */
	private function assertSafeMutationChannel(): void
	{
		if ($this->request->passesCSRFCheck()) {
			return;
		}
		if ($this->authorizationBasicAuthenticatesCurrentUser()) {
			return;
		}

		throw new PermissionDeniedException('mutate', 'mobile', 'CSRF or app password required');
	}

	/**
	 * True only when Authorization Basic credentials authenticate as the
	 * current session UID — either via login password (checkPassword) or a
	 * Login Flow / CLI app password (IProvider::getToken). Forged Bearer or
	 * Basic shape alone must not skip CSRF.
	 */
	private function authorizationBasicAuthenticatesCurrentUser(): bool
	{
		$auth = trim((string)$this->request->getHeader('Authorization'));
		if (preg_match('/^Basic\s+(\S+)$/i', $auth, $m) !== 1) {
			return false;
		}
		$decoded = base64_decode($m[1], true);
		if ($decoded === false || !str_contains($decoded, ':')) {
			return false;
		}
		[$login, $secret] = explode(':', $decoded, 2);
		if ($login === '' || $secret === '') {
			return false;
		}
		$current = $this->userSession->getUser();
		if ($current === null) {
			return false;
		}

		$authed = $this->userManager->checkPassword($login, $secret);
		if ($authed !== false) {
			return hash_equals($current->getUID(), $authed->getUID());
		}

		try {
			$token = $this->tokenProvider->getToken($secret);
		} catch (InvalidTokenException|ExpiredTokenException|WipeTokenException) {
			return false;
		}

		if (!hash_equals($current->getUID(), $token->getUID())) {
			return false;
		}

		// Bind the Basic login name to the token (UID or loginName used at mint).
		return hash_equals($token->getUID(), $login)
			|| hash_equals($token->getLoginName(), $login);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function jsonBody(): array
	{
		$raw = file_get_contents('php://input');
		if (is_string($raw) && $raw !== '') {
			try {
				$decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
				if (is_array($decoded)) {
					return $decoded;
				}
			} catch (\JsonException) {
				// fall through to request params
			}
		}
		$params = $this->request->getParams();
		unset($params['id'], $params['_route']);
		return $params;
	}
}
