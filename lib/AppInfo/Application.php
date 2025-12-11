<?php

/**
 * Application class for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\ProjectCheck
 */
class Application extends App implements IBootstrap
{
	public const APP_ID = 'projectcheck';

	public function __construct()
	{
		parent::__construct(self::APP_ID);
	}

	/**
	 * Register the app
	 */
	public function register(IRegistrationContext $context): void
	{
		// Register services
		$context->registerService('ProjectService', function ($c) {
			return new \OCA\ProjectControl\Service\ProjectService(
				$c->query(\OCP\IDBConnection::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCP\IUserManager::class),
				$c->query(\OCP\IConfig::class)
			);
		});

		$context->registerService('CustomerService', function ($c) {
			return new \OCA\ProjectControl\Service\CustomerService(
				$c->query(\OCA\ProjectControl\Db\CustomerMapper::class),
				$c->query('ProjectService'),
				$c->query(\OCA\ProjectControl\Db\TimeEntryMapper::class)
			);
		});

		$context->registerService('TimeEntryService', function ($c) {
			return new \OCA\ProjectControl\Service\TimeEntryService(
				$c->query(\OCA\ProjectControl\Db\TimeEntryMapper::class),
				$c->query(\OCA\ProjectControl\Db\ProjectMapper::class)
			);
		});

		$context->registerService('BudgetService', function ($c) {
			return new \OCA\ProjectControl\Service\BudgetService(
				$c->query(\OCA\ProjectControl\Db\TimeEntryMapper::class),
				$c->query(\OCP\IConfig::class),
				$c->query(\Psr\Log\LoggerInterface::class),
				$c->query(\OCP\L10N\IFactory::class)->get(self::APP_ID),
				self::APP_ID
			);
		});

		$context->registerService('ProjectMemberService', function ($c) {
			return new \OCA\ProjectControl\Service\ProjectMemberService(
				$c->query(\OCP\IDBConnection::class),
				$c->query(\OCA\ProjectControl\Db\ProjectMapper::class),
				$c->query(\OCA\ProjectControl\Db\TimeEntryMapper::class)
			);
		});

		// Register ProjectMemberService with its class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\ProjectMemberService::class, function ($c) {
			return $c->query('ProjectMemberService');
		});

		$context->registerService('DeletionService', function ($c) {
			return new \OCA\ProjectControl\Service\DeletionService(
				$c->query(\OCP\IDBConnection::class),
				$c->query(\OCA\ProjectControl\Db\CustomerMapper::class),
				$c->query(\OCA\ProjectControl\Db\TimeEntryMapper::class),
				$c->query(\OCA\ProjectControl\Db\ProjectMapper::class),
				$c->query('ProjectService'),
				$c->query('ProjectMemberService')
			);
		});

		// Register DeletionService with its class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\DeletionService::class, function ($c) {
			return $c->query('DeletionService');
		});

		$context->registerService('ActivityService', function ($c) {
			return new \OCA\ProjectControl\Service\ActivityService(
				$c->query(\OCP\Activity\IManager::class)
			);
		});

		// Register ActivityService with its class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\ActivityService::class, function ($c) {
			return $c->query('ActivityService');
		});

		// Register ProjectFile mapper and service (ProjectCheck namespace)
		$context->registerService(\OCA\ProjectCheck\Db\ProjectFileMapper::class, function ($c) {
			return new \OCA\ProjectCheck\Db\ProjectFileMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		$context->registerService(\OCA\ProjectCheck\Service\ProjectFileService::class, function ($c) {
			return new \OCA\ProjectCheck\Service\ProjectFileService(
				$c->query(\OCA\ProjectCheck\Db\ProjectFileMapper::class),
				$c->query(\OCA\ProjectCheck\Service\ProjectService::class),
				$c->query(\OCP\Files\AppData\IAppDataFactory::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\Psr\Log\LoggerInterface::class)
			);
		});

		// Register CSPService (no dependencies)
		$context->registerService(\OCA\ProjectControl\Service\CSPService::class, function ($c) {
			return new \OCA\ProjectControl\Service\CSPService();
		});

		// Register BudgetAlertService
		$context->registerService(\OCA\ProjectControl\Service\BudgetAlertService::class, function ($c) {
			return new \OCA\ProjectControl\Service\BudgetAlertService(
				$c->query(\OCP\IConfig::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCP\Notification\IManager::class),
				$c->query(\OCP\IUserManager::class),
				$c->query(\Psr\Log\LoggerInterface::class),
				$c->query(\OCA\ProjectControl\Service\ProjectService::class),
				$c->query(\OCA\ProjectControl\Service\TimeEntryService::class)
			);
		});

		// Register DateFormatService
		$context->registerService(\OCA\ProjectControl\Service\DateFormatService::class, function ($c) {
			return new \OCA\ProjectControl\Service\DateFormatService(
				$c->query(\OCP\IConfig::class),
				$c->query(\OCP\IUserSession::class)
			);
		});

		// Register BudgetService with class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\BudgetService::class, function ($c) {
			return $c->query('BudgetService');
		});

		// Register TimeEntryService with class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\TimeEntryService::class, function ($c) {
			return $c->query('TimeEntryService');
		});

		// Register ProjectService with class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\ProjectService::class, function ($c) {
			return $c->query('ProjectService');
		});

		// Register CustomerService with class name for auto-wiring
		$context->registerService(\OCA\ProjectControl\Service\CustomerService::class, function ($c) {
			return $c->query('CustomerService');
		});

		// Register mappers
		$context->registerService(\OCA\ProjectControl\Db\ProjectMapper::class, function ($c) {
			return new \OCA\ProjectControl\Db\ProjectMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		$context->registerService(\OCA\ProjectControl\Db\CustomerMapper::class, function ($c) {
			return new \OCA\ProjectControl\Db\CustomerMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		$context->registerService(\OCA\ProjectControl\Db\TimeEntryMapper::class, function ($c) {
			return new \OCA\ProjectControl\Db\TimeEntryMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		// Register controllers
		$context->registerService(\OCA\ProjectControl\Controller\ProjectMemberController::class, function ($c) {
			return new \OCA\ProjectControl\Controller\ProjectMemberController(
				$c->query('appName'),
				$c->query(\OCP\IRequest::class),
				$c->query(\OCP\IUserSession::class),
				$c->query('ProjectMemberService'),
				$c->query('DeletionService'),
				$c->query('ActivityService')
			);
		});

		// Register capabilities
		$context->registerCapability(\OCA\ProjectControl\Capabilities::class);
	}

	/**
	 * Boot the app
	 */
	public function boot(IBootContext $context): void
	{
		// Load CSS and JS files ONLY on projectcheck routes to avoid leaking into other apps
		try {
			$request = $this->getContainer()->get(\OCP\IRequest::class);
			$path = $request->getPathInfo();
			if (strpos($path, '/apps/projectcheck') === 0 || strpos($path, '/index.php/apps/projectcheck') === 0) {
				Util::addStyle(self::APP_ID, 'projects');
				Util::addStyle(self::APP_ID, 'deletion-modal');
				Util::addScript(self::APP_ID, 'projects');
				Util::addScript(self::APP_ID, 'common/deletion-modal');
			}
		} catch (\Throwable $e) {
			// If request is unavailable, do nothing to keep other apps safe
		}
	}
}
