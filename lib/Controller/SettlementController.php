<?php

declare(strict_types=1);

/**
 * JSON endpoints for settlement actions (feature spec §9.2).
 *
 * All routes are mutating POSTs and rely on Nextcloud's automatic
 * `requesttoken` CSRF verification (no NoCSRFRequired — spec §8.2) plus the
 * app access + schema guard middlewares. Authorization happens inside
 * {@see TimeEntryBillingService} / {@see ProjectSettlementService} per entry
 * and per project; this controller only maps typed exceptions to HTTP codes:
 *
 *   404 TimeEntryNotFoundException
 *   403 PermissionDeniedException
 *   400 ValidationException / InvalidBillingTransitionException
 *   409 SettlementConflictException / BillingLockedException
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCA\ProjectCheck\Exception\InvalidBillingTransitionException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\SettlementConflictException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SettlementController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private TimeEntryBillingService $billingService,
		private ProjectSettlementService $projectSettlementService,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Single entry transition. Body: { "target": "invoiced" }
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function changeEntryStatus(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$data = $this->readBody();
		$target = strtolower(trim((string) ($data['target'] ?? '')));

		return $this->guard(function () use ($id, $target, $user): JSONResponse {
			$entry = $this->billingService->changeStatus($id, $target, $user->getUID());
			return new JSONResponse([
				'success' => true,
				'timeEntry' => $entry->getSummary(),
				'allowedTargets' => BillingStatus::allowedTargets($entry->getBillingStatus()),
				'message' => $this->l->t('Settlement status updated.'),
			]);
		});
	}

	/**
	 * Preview for filter-mode bulk operations.
	 * Body: { "filters": { "billing_status": "open", ... }, "target": "invoiced" }
	 * Explicit id selection skips preview and posts directly to bulk.
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function preview(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$data = $this->readBody();
		$target = strtolower(trim((string) ($data['target'] ?? '')));

		return $this->guard(function () use ($data, $target, $user): JSONResponse {
			$filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
			$result = $this->billingService->previewByFilters($filters, $target, $user->getUID());
			return new JSONResponse(['success' => true] + $result);
		});
	}

	/**
	 * Bulk apply.
	 * Ids mode:     { "ids": [...], "target": "..." } (explicit user selection — no token)
	 * Filter mode:  { "filters": {...}, "target": "...", "token": "..." }
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function bulk(): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$data = $this->readBody();
		$target = strtolower(trim((string) ($data['target'] ?? '')));

		return $this->guard(function () use ($data, $target, $user): JSONResponse {
			if (is_array($data['ids'] ?? null) && ($data['ids'] ?? []) !== []) {
				$result = $this->billingService->bulkChangeStatusByIds($data['ids'], $target, $user->getUID());
			} else {
				$filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
				$token = (string) ($data['token'] ?? '');
				$result = $this->billingService->applyByFilters($filters, $target, $user->getUID(), $token);
			}

			return new JSONResponse([
				'success' => true,
				'applied' => $result['applied'],
				'failed' => $result['failed'],
				'message' => $this->bulkResultMessage($result['applied'], count($result['failed'])),
			]);
		});
	}

	/**
	 * Project-level settle preview. Body:
	 * { "action": "invoice_open"|"mark_paid", "date_from"?: "Y-m-d", "date_to"?: "Y-m-d" }
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function projectPreview(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$data = $this->readBody();

		return $this->guard(function () use ($id, $data, $user): JSONResponse {
			$result = $this->projectSettlementService->previewProjectSettle(
				$id,
				(string) ($data['action'] ?? ''),
				['date_from' => (string) ($data['date_from'] ?? ''), 'date_to' => (string) ($data['date_to'] ?? '')],
				$user->getUID()
			);
			return new JSONResponse(['success' => true] + $result);
		});
	}

	/**
	 * Project-level settle apply. Same body as preview plus "token".
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function projectApply(int $id): JSONResponse
	{
		$user = $this->userSession->getUser();
		if (!$user) {
			return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
		}

		$data = $this->readBody();

		return $this->guard(function () use ($id, $data, $user): JSONResponse {
			$result = $this->projectSettlementService->applyProjectSettle(
				$id,
				(string) ($data['action'] ?? ''),
				['date_from' => (string) ($data['date_from'] ?? ''), 'date_to' => (string) ($data['date_to'] ?? '')],
				$user->getUID(),
				(string) ($data['token'] ?? '')
			);
			return new JSONResponse([
				'success' => true,
				'applied' => $result['applied'],
				'failed' => $result['failed'],
				'message' => $this->bulkResultMessage($result['applied'], count($result['failed'])),
			]);
		});
	}

	// ---------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	private function readBody(): array
	{
		$raw = file_get_contents('php://input');
		$data = json_decode((string) $raw, true);
		if (!is_array($data)) {
			$data = $this->request->getParams();
		}
		return is_array($data) ? $data : [];
	}

	private function bulkResultMessage(int $applied, int $failedCount): string
	{
		if ($failedCount === 0) {
			return $this->l->n(
				'%n entry updated.',
				'%n entries updated.',
				$applied
			);
		}
		return $this->l->t('%1$s entries updated, %2$s skipped (changed meanwhile or not allowed).', [
			(string) $applied,
			(string) $failedCount,
		]);
	}

	/**
	 * Run a settlement action and translate typed exceptions to HTTP codes.
	 *
	 * @param callable(): JSONResponse $action
	 */
	private function guard(callable $action): JSONResponse
	{
		try {
			return $action();
		} catch (TimeEntryNotFoundException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Time entry not found'),
			], 404);
		} catch (PermissionDeniedException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('Access denied'),
			], 403);
		} catch (InvalidBillingTransitionException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('This status change is not allowed.'),
				'code' => 'invalid_billing_transition',
				'from' => $e->getFromStatus(),
				'to' => $e->getToStatus(),
			], 400);
		} catch (SettlementConflictException $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'code' => $e->getConflictCode(),
			], 409);
		} catch (ValidationException $e) {
			$message = trim($e->getMessage());
			return new JSONResponse([
				'success' => false,
				'error' => $message !== '' ? $message : $this->l->t('Invalid parameters'),
			], 400);
		} catch (\Throwable $e) {
			$this->logger->error('ProjectCheck settlement action failed', ['exception' => $e]);
			return new JSONResponse([
				'success' => false,
				'error' => $this->l->t('The settlement action failed. Please try again.'),
			], 500);
		}
	}
}
