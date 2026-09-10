<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Support;

use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Reliable integration-test user lifecycle for ProjectCheck HTTP suites.
 *
 * Nextcloud rejects createUser when a data directory already exists for the uid
 * (orphaned after a failed test run). ensure() deletes leftovers, creates the
 * user when missing, and tolerates userExists races between check and create.
 */
final class IntegrationTestUsers
{
	public static function ensure(IUserManager $userManager, string $uid, string $password): IUser
	{
		if ($userManager->userExists($uid)) {
			$user = $userManager->get($uid);
			if ($user !== null) {
				return $user;
			}
		}

		self::removeOrphanDataDirectory($uid);

		try {
			if (!$userManager->createUser($uid, $password)) {
				if ($userManager->userExists($uid)) {
					$user = $userManager->get($uid);
					if ($user !== null) {
						return $user;
					}
				}
				throw new \RuntimeException('Failed to create integration test user: ' . $uid);
			}
		} catch (\InvalidArgumentException $e) {
			if ($userManager->userExists($uid)) {
				$user = $userManager->get($uid);
				if ($user !== null) {
					return $user;
				}
			}
			if (self::orphanDataDirectoryExists($uid) && str_contains($e->getMessage(), 'files already exist')) {
				self::removeOrphanDataDirectory($uid);
				if (!$userManager->createUser($uid, $password)) {
					throw new \RuntimeException('Failed to create integration test user after orphan cleanup: ' . $uid, 0, $e);
				}
			} else {
				throw $e;
			}
		}

		$user = $userManager->get($uid);
		if ($user === null) {
			throw new \RuntimeException('Created user could not be loaded: ' . $uid);
		}

		return $user;
	}

	public static function remove(IUserManager $userManager, string ...$uids): void
	{
		foreach ($uids as $uid) {
			if ($userManager->userExists($uid)) {
				$userManager->get($uid)?->delete();
			}
			self::removeOrphanDataDirectory($uid);
		}
	}

	private static function removeOrphanDataDirectory(string $uid): void
	{
		if (!isset(\OC::$server)) {
			return;
		}

		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		$dataDirectory = rtrim($config->getSystemValueString('datadirectory', \OC::$SERVERROOT . '/data'), '/');
		$path = $dataDirectory . '/' . $uid;
		if (!is_dir($path)) {
			return;
		}

		self::removeDirectoryRecursively($path);
	}

	private static function orphanDataDirectoryExists(string $uid): bool
	{
		if (!isset(\OC::$server)) {
			return false;
		}

		/** @var IConfig $config */
		$config = \OC::$server->get(IConfig::class);
		$dataDirectory = rtrim($config->getSystemValueString('datadirectory', \OC::$SERVERROOT . '/data'), '/');
		return is_dir($dataDirectory . '/' . $uid);
	}

	private static function removeDirectoryRecursively(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$items = scandir($path);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$full = $path . '/' . $item;
			if (is_dir($full)) {
				self::removeDirectoryRecursively($full);
			} else {
				@unlink($full);
			}
		}

		@rmdir($path);
	}
}
