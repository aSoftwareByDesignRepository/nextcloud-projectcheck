<?php

declare(strict_types=1);

/**
 * Central access and app-level policy for ProjectCheck.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AccessControlService
{
	public const APP_ID = 'projectcheck';

	/** IConfig app keys (values as strings, JSON for arrays) */
	public const KEY_ACCESS_RESTRICTION = 'access_restriction_enabled';
	public const KEY_ACCESS_ALLOWED_USER_IDS = 'access_allowed_user_ids';
	public const KEY_ACCESS_ALLOWED_GROUP_IDS = 'access_allowed_group_ids';
	public const KEY_APP_ADMIN_USER_IDS = 'app_admin_user_ids';

	/** @var array<string, bool> */
	private array $adminCache = [];

	/** @var array<string, bool> */
	private array $groupCache = [];

	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Real Nextcloud system administrators (fail-safe, cannot be disabled by app).
	 */
	public function isSystemAdministrator(string $userId): bool
	{
		if ($userId === '') {
			return false;
		}
		if (!array_key_exists($userId, $this->adminCache)) {
			$this->adminCache[$userId] = (bool) $this->groupManager->isAdmin($userId);
		}
		return $this->adminCache[$userId];
	}

	public function isAccessRestrictionEnabled(): bool
	{
		return $this->config->getAppValue(self::APP_ID, self::KEY_ACCESS_RESTRICTION, '0') === '1';
	}

	/**
	 * @return list<string>
	 */
	public function getAllowedUserIds(): array
	{
		return $this->decodeIdList(
			$this->config->getAppValue(self::APP_ID, self::KEY_ACCESS_ALLOWED_USER_IDS, '[]')
		);
	}

	/**
	 * @return list<string>
	 */
	public function getAllowedGroupIds(): array
	{
		return $this->decodeIdList(
			$this->config->getAppValue(self::APP_ID, self::KEY_ACCESS_ALLOWED_GROUP_IDS, '[]')
		);
	}

	/**
	 * @return list<string>
	 */
	public function getAppAdminUserIds(): array
	{
		return $this->decodeIdList(
			$this->config->getAppValue(self::APP_ID, self::KEY_APP_ADMIN_USER_IDS, '[]')
		);
	}

	/**
	 * @return array{
	 *   restrictionEnabled: bool,
	 *   allowedUserIds: list<string>,
	 *   allowedGroupIds: list<string>,
	 *   appAdminUserIds: list<string>
	 * }
	 */
	public function getPolicyState(): array
	{
		return [
			'restrictionEnabled' => $this->isAccessRestrictionEnabled(),
			'allowedUserIds' => $this->getAllowedUserIds(),
			'allowedGroupIds' => $this->getAllowedGroupIds(),
			'appAdminUserIds' => $this->getAppAdminUserIds(),
		];
	}

	public function canUseApp(string $userId): bool
	{
		if ($userId === '') {
			return false;
		}

		if ($this->isSystemAdministrator($userId)) {
			return true;
		}

		// App-level admins must be able to use the app to open org settings.
		foreach ($this->getAppAdminUserIds() as $adminId) {
			if ($adminId === $userId) {
				return true;
			}
		}

		if (!$this->isAccessRestrictionEnabled()) {
			return true;
		}

		foreach ($this->getAllowedUserIds() as $allowed) {
			if ($allowed === $userId) {
				return true;
			}
		}

		foreach ($this->getAllowedGroupIds() as $groupId) {
			if ($this->isInGroupCached($userId, $groupId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Can manage org-wide / app config (not necessarily NC system admin).
	 */
	public function canManageAppConfiguration(string $userId): bool
	{
		if ($userId === '') {
			return false;
		}
		if ($this->isSystemAdministrator($userId)) {
			return true;
		}
		foreach ($this->getAppAdminUserIds() as $adminId) {
			if ($adminId === $userId) {
				return true;
			}
		}
		return false;
	}

	public function canManageAppConfigurationByUser(IUser $user): bool
	{
		return $this->canManageAppConfiguration($user->getUID());
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function saveAccessPolicy(
		bool $restrictionEnabled,
		array $userIds,
		array $groupIds
	): void {
		$normalizedUsers = $this->normalizeUserIds($userIds);
		$normalizedGroups = $this->normalizeGroupIds($groupIds);

		if ($restrictionEnabled && $normalizedUsers === [] && $normalizedGroups === []) {
			throw new \InvalidArgumentException('When access restriction is on, at least one user or one group is required');
		}

		$this->config->setAppValue(self::APP_ID, self::KEY_ACCESS_RESTRICTION, $restrictionEnabled ? '1' : '0');
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_ACCESS_ALLOWED_USER_IDS,
			json_encode($normalizedUsers, JSON_THROW_ON_ERROR)
		);
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_ACCESS_ALLOWED_GROUP_IDS,
			json_encode($normalizedGroups, JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @param list<string> $userIds
	 */
	public function saveAppAdmins(array $userIds): void
	{
		$normalized = $this->normalizeUserIds($userIds);
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_APP_ADMIN_USER_IDS,
			json_encode($normalized, JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * Apply org-wide access policy in one call (org admin or system admin).
	 * Validates all lists before writing any value.
	 *
	 * @throws \InvalidArgumentException
	 * @param list<string> $allowedUserIds
	 * @param list<string> $allowedGroupIds
	 * @param list<string> $appAdminUserIds
	 */
	public function applyFullAccessPolicy(
		bool $restrictionEnabled,
		array $allowedUserIds,
		array $allowedGroupIds,
		array $appAdminUserIds
	): void {
		$u = $this->normalizeUserIds($allowedUserIds);
		$g = $this->normalizeGroupIds($allowedGroupIds);
		$a = $this->normalizeUserIds($appAdminUserIds);
		if ($restrictionEnabled && $u === [] && $g === []) {
			throw new \InvalidArgumentException('When access restriction is on, at least one user or one group is required');
		}
		$this->config->setAppValue(self::APP_ID, self::KEY_ACCESS_RESTRICTION, $restrictionEnabled ? '1' : '0');
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_ACCESS_ALLOWED_USER_IDS,
			json_encode($u, JSON_THROW_ON_ERROR)
		);
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_ACCESS_ALLOWED_GROUP_IDS,
			json_encode($g, JSON_THROW_ON_ERROR)
		);
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_APP_ADMIN_USER_IDS,
			json_encode($a, JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * Remove a user id from all policy lists (after account deletion).
	 */
	public function removeUserIdFromAllLists(string $userId): void
	{
		$allowUsers = array_values(array_filter(
			$this->getAllowedUserIds(),
			static fn (string $id): bool => $id !== $userId
		));
		$appAdmins = array_values(array_filter(
			$this->getAppAdminUserIds(),
			static fn (string $id): bool => $id !== $userId
		));
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_ACCESS_ALLOWED_USER_IDS,
			json_encode($allowUsers, JSON_THROW_ON_ERROR)
		);
		$this->config->setAppValue(
			self::APP_ID,
			self::KEY_APP_ADMIN_USER_IDS,
			json_encode($appAdmins, JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * @return list<string>
	 */
	public function decodeIdList(string $json): array
	{
		$json = trim($json);
		if ($json === '') {
			return [];
		}
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			$this->logger->warning('Invalid JSON in ProjectCheck access list; resetting', [ 'app' => self::APP_ID ]);
			return [];
		}
		if (!is_array($data)) {
			return [];
		}
		$out = [];
		foreach ($data as $item) {
			if (is_string($item) && $item !== '') {
				$out[] = $item;
			}
		}
		return array_values(array_unique($out));
	}

	/**
	 * @param list<string> $raw
	 * @return list<string>
	 * @throws \InvalidArgumentException
	 */
	public function normalizeUserIds(array $raw): array
	{
		$out = [];
		foreach ($raw as $id) {
			$id = is_string($id) ? trim($id) : '';
			if ($id === '') {
				continue;
			}
			if ($this->userManager->get($id) === null) {
				throw new \InvalidArgumentException('User does not exist: ' . $id);
			}
			$out[] = $id;
		}
		return array_values(array_unique($out));
	}

	/**
	 * @param list<string> $raw
	 * @return list<string>
	 * @throws \InvalidArgumentException
	 */
	public function normalizeGroupIds(array $raw): array
	{
		$out = [];
		foreach ($raw as $id) {
			$id = is_string($id) ? trim($id) : '';
			if ($id === '') {
				continue;
			}
			if ($this->groupManager->get($id) === null) {
				throw new \InvalidArgumentException('Group does not exist: ' . $id);
			}
			$out[] = $id;
		}
		return array_values(array_unique($out));
	}

	private function isInGroupCached(string $userId, string $groupId): bool
	{
		$key = $userId . "\0" . $groupId;
		if (!array_key_exists($key, $this->groupCache)) {
			$this->groupCache[$key] = (bool) $this->groupManager->isInGroup($userId, $groupId);
		}
		return $this->groupCache[$key];
	}
}
