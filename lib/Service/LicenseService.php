<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\LicenseState;
use OCA\ProjectCheck\Db\LicenseStateMapper;
use OCA\ProjectCheck\Db\MobileSeat;
use OCA\ProjectCheck\Db\MobileSeatMapper;
use OCA\ProjectCheck\Exception\LicenseException;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\License\Pc2Codec;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * PC2 singleton license + named mobile seats (web UI stays ungated).
 */
class LicenseService
{
	public const MOBILE_APP_STATUS = 'available';

	private const SEAT_LOCK = 'projectcheck/seat_assign';
	private const LICENSE_LOCK = 'projectcheck/license_apply';

	public function __construct(
		private readonly IDBConnection $db,
		private readonly LicenseStateMapper $licenseState,
		private readonly MobileSeatMapper $seats,
		private readonly ITimeFactory $timeFactory,
		private readonly IUserManager $userManager,
		private readonly ILockingProvider $locking,
	) {
	}

	/** @return array<string, mixed> */
	public function status(): array
	{
		$state = $this->licenseState->findSingleton();
		$today = $this->timeFactory->getDateTime()->format('Y-m-d');
		$valid = $state !== null && Pc2Codec::isValidOn($state->getValidUntil(), $today);
		$daysLeft = null;
		$expiresSoon = false;
		if ($state !== null) {
			$until = \DateTimeImmutable::createFromFormat('!Y-m-d', $state->getValidUntil());
			$todayDt = \DateTimeImmutable::createFromFormat('!Y-m-d', $today);
			if ($until !== false && $todayDt !== false) {
				$daysLeft = (int)$todayDt->diff($until)->format('%r%a');
				$expiresSoon = $daysLeft >= 0 && $daysLeft <= 30;
			}
		}
		return [
			'state' => $state === null ? null : [
				'customerId' => $state->getCustomerId(),
				'issuedAt' => $state->getIssuedAt(),
				'validUntil' => $state->getValidUntil(),
				'mobileSeats' => $state->getMobileSeats(),
				'valid' => $valid,
				'expiresSoon' => $expiresSoon,
				'daysRemaining' => $daysLeft,
				'appliedAt' => $state->getAppliedAt(),
				'appliedBy' => $state->getAppliedBy(),
			],
			'seats' => [
				'assigned' => $this->seats->countAll(),
				'limit' => $state?->getMobileSeats() ?? 0,
			],
			'envelope' => $this->buildEnvelope(),
			'mobileAppStatus' => self::MOBILE_APP_STATUS,
		];
	}

	/**
	 * @return array{format: string, payloadB64: string, signatureB64: string}|null
	 */
	public function buildEnvelope(): ?array
	{
		$state = $this->licenseState->findSingleton();
		if ($state === null) {
			return null;
		}
		$today = $this->timeFactory->getDateTime()->format('Y-m-d');
		if (!Pc2Codec::isValidOn($state->getValidUntil(), $today)) {
			return null;
		}
		return [
			'format' => Pc2Codec::FORMAT,
			'payloadB64' => $state->getPayloadB64(),
			'signatureB64' => $state->getSignatureB64(),
		];
	}

	public function isMobilePlanActive(): bool
	{
		$state = $this->licenseState->findSingleton();
		if ($state === null || $state->getMobileSeats() < 1) {
			return false;
		}
		$today = $this->timeFactory->getDateTime()->format('Y-m-d');
		return Pc2Codec::isValidOn($state->getValidUntil(), $today);
	}

	public function isUserSeated(string $uid): bool
	{
		return $this->seats->findByUid($uid) !== null;
	}

	/**
	 * Mobile gate inputs for one user (rungs 3–6).
	 *
	 * @return array{hasLicense: bool, licenseValid: bool, seatAssigned: bool, seatWithinLimit: bool,
	 *               payloadB64: ?string, signatureB64: ?string}
	 */
	public function gateState(string $uid): array
	{
		$state = $this->licenseState->findSingleton();
		$seat = $this->seats->findByUid($uid);
		$withinLimit = false;
		if ($seat !== null && $state !== null) {
			$rankInput = array_map(
				static fn (MobileSeat $s): array => ['id' => (int)$s->getId(), 'assignedAt' => $s->getAssignedAt()],
				$this->seats->findAllRanked(),
			);
			$withinLimit = SeatRank::isWithinLimit($rankInput, (int)$seat->getId(), $state->getMobileSeats());
		}
		$today = $this->timeFactory->getDateTime()->format('Y-m-d');
		return [
			'hasLicense' => $state !== null,
			'licenseValid' => $state !== null && Pc2Codec::isValidOn($state->getValidUntil(), $today),
			'seatAssigned' => $seat !== null,
			'seatWithinLimit' => $withinLimit,
			'payloadB64' => $state?->getPayloadB64(),
			'signatureB64' => $state?->getSignatureB64(),
		];
	}

	/**
	 * Gate for mobile write APIs. Web UI stays ungated.
	 * Codes match {@see MobileGateService} / SERVER-MOBILE-API §2.
	 *
	 * @throws MobileGateException
	 */
	public function assertMobileAccess(string $uid): void
	{
		$state = $this->gateState($uid);
		if (!$state['hasLicense']) {
			throw new MobileGateException('license_missing');
		}
		if (!$state['licenseValid']) {
			throw new MobileGateException('license_expired');
		}
		if (!$state['seatAssigned']) {
			throw new MobileGateException('seat_required');
		}
		if (!$state['seatWithinLimit']) {
			throw new MobileGateException('seat_limit_exceeded');
		}
	}

	/** @return array<string, mixed> */
	public function apply(string $uid, string $wireKey): array
	{
		$error = Pc2Codec::classifyError($wireKey);
		if ($error !== '') {
			$message = match ($error) {
				Pc2Codec::ERROR_INVALID_FORMAT => 'The key does not have the expected PC2.<payload>.<signature> shape.',
				Pc2Codec::ERROR_INVALID_SIGNATURE => 'The signature does not match — the key was altered or not issued for this product.',
				default => 'The key payload failed validation for ProjectCheck.',
			};
			throw new LicenseException('license_invalid', $message);
		}

		/** @var array{payload: array<string, mixed>, payloadB64: string, signatureB64: string} $verified */
		$verified = Pc2Codec::parseAndVerify($wireKey);
		$payload = $verified['payload'];

		return $this->withExclusiveLock(
			self::LICENSE_LOCK,
			'license_busy',
			'Another license update is in progress. Try again in a moment.',
			409,
			function () use ($uid, $payload, $verified): array {
				$state = new LicenseState();
				$state->setCustomerId((string)$payload['customerId']);
				$state->setIssuedAt((string)$payload['issuedAt']);
				$state->setValidUntil((string)$payload['validUntil']);
				$state->setMobileSeats((int)$payload['mobileSeats']);
				$state->setPayloadB64($verified['payloadB64']);
				$state->setSignatureB64($verified['signatureB64']);
				$state->setAppliedAt($this->timeFactory->getTime());
				$state->setAppliedBy($uid);

				$this->db->beginTransaction();
				try {
					$this->licenseState->deleteAll();
					$this->licenseState->insert($state);
					$this->db->commit();
				} catch (\Throwable $e) {
					$this->db->rollBack();
					throw $e;
				}

				return $this->status();
			},
		);
	}

	/** @return array<string, mixed> */
	public function remove(): array
	{
		return $this->withExclusiveLock(
			self::LICENSE_LOCK,
			'license_busy',
			'Another license update is in progress. Try again in a moment.',
			409,
			function (): array {
				$this->db->beginTransaction();
				try {
					$this->seats->deleteAll();
					$this->licenseState->deleteAll();
					$this->db->commit();
				} catch (\Throwable $e) {
					$this->db->rollBack();
					throw $e;
				}
				return $this->status();
			},
		);
	}

	/**
	 * @return array{data: list<array<string, mixed>>, total: int, limit: int, offset: int}
	 */
	public function listSeats(int $limit = 50, int $offset = 0): array
	{
		$limit = max(1, min(200, $limit));
		$offset = max(0, $offset);
		$ranked = $this->seats->findAllRanked();
		$seatLimit = $this->licenseState->findSingleton()?->getMobileSeats() ?? 0;
		$rankInput = array_map(
			static fn (MobileSeat $s): array => ['id' => (int)$s->getId(), 'assignedAt' => $s->getAssignedAt()],
			$ranked,
		);
		$rows = [];
		foreach ($ranked as $seat) {
			$rows[] = [
				'uid' => $seat->getUid(),
				'displayName' => $this->userManager->get($seat->getUid())?->getDisplayName() ?? $seat->getUid(),
				'assignedAt' => $seat->getAssignedAt(),
				'assignedBy' => $seat->getAssignedBy(),
				'withinLimit' => SeatRank::isWithinLimit($rankInput, (int)$seat->getId(), $seatLimit),
			];
		}
		return [
			'data' => array_slice($rows, $offset, $limit),
			'total' => count($rows),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	/**
	 * @return array{created: bool, seat: array<string, mixed>}
	 */
	public function assignSeat(string $adminUid, mixed $userId): array
	{
		if (!is_string($userId) || trim($userId) === '') {
			throw new LicenseException('unknown_user', 'This Nextcloud user does not exist.');
		}
		$userId = trim($userId);
		if (!$this->userManager->userExists($userId)) {
			throw new LicenseException('unknown_user', 'This Nextcloud user does not exist.');
		}
		$existing = $this->seats->findByUid($userId);
		if ($existing !== null) {
			return ['created' => false, 'seat' => $this->seatRow($existing)];
		}

		return $this->withExclusiveLock(
			self::SEAT_LOCK,
			'seat_busy',
			'Another seat update is in progress. Try again in a moment.',
			409,
			function () use ($adminUid, $userId): array {
				$existing = $this->seats->findByUid($userId);
				if ($existing !== null) {
					return ['created' => false, 'seat' => $this->seatRow($existing)];
				}
				$limit = $this->licenseState->findSingleton()?->getMobileSeats() ?? 0;
				if ($this->seats->countAll() >= $limit) {
					throw new LicenseException(
						'seat_limit_reached',
						'All licensed seats are assigned. Remove a seat or upgrade the license.',
						409,
					);
				}
				$seat = new MobileSeat();
				$seat->setUid($userId);
				$seat->setAssignedAt($this->timeFactory->getTime());
				$seat->setAssignedBy($adminUid);
				try {
					$seat = $this->seats->insert($seat);
				} catch (\OCP\DB\Exception) {
					$existing = $this->seats->findByUid($userId);
					if ($existing !== null) {
						return ['created' => false, 'seat' => $this->seatRow($existing)];
					}
					throw new LicenseException('seat_assign_failed', 'Could not assign the seat.');
				}
				return ['created' => true, 'seat' => $this->seatRow($seat)];
			},
		);
	}

	public function removeSeat(string $uid): void
	{
		$seat = $this->seats->findByUid($uid);
		if ($seat === null) {
			throw new LicenseException('seat_not_found', 'Seat not found.', 404);
		}
		$this->seats->delete($seat);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function seatRow(MobileSeat $seat): array
	{
		$limit = $this->licenseState->findSingleton()?->getMobileSeats() ?? 0;
		$ranked = $this->seats->findAllRanked();
		$rankInput = array_map(
			static fn (MobileSeat $s): array => ['id' => (int)$s->getId(), 'assignedAt' => $s->getAssignedAt()],
			$ranked,
		);
		return [
			'uid' => $seat->getUid(),
			'displayName' => $this->userManager->get($seat->getUid())?->getDisplayName() ?? $seat->getUid(),
			'assignedAt' => $seat->getAssignedAt(),
			'assignedBy' => $seat->getAssignedBy(),
			'withinLimit' => SeatRank::isWithinLimit($rankInput, (int)$seat->getId(), $limit),
		];
	}

	/**
	 * @template T
	 * @param callable(): T $fn
	 * @return T
	 */
	private function withExclusiveLock(
		string $key,
		string $busyCode,
		string $busyMessage,
		int $busyStatus,
		callable $fn,
	): mixed {
		$attempts = 0;
		while (true) {
			try {
				$this->locking->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
				break;
			} catch (LockedException) {
				if (++$attempts >= 40) {
					throw new LicenseException($busyCode, $busyMessage, $busyStatus);
				}
				usleep(25_000);
			}
		}
		try {
			return $fn();
		} finally {
			$this->locking->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}
