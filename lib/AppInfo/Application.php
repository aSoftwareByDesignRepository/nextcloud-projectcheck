<?php

declare(strict_types=1);

/**
 * Application class for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\AppInfo;

use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\INavigationManager;
use OCP\L10N\IFactory;
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
		$context->registerService(
			\OCA\ProjectCheck\Service\AccessControlService::class,
			function ($c) {
				return new \OCA\ProjectCheck\Service\AccessControlService(
					$c->query(\OCP\IConfig::class),
					$c->query(\OCP\IGroupManager::class),
					$c->query(\OCP\IUserManager::class),
					$c->query(\Psr\Log\LoggerInterface::class)
				);
			}
		);
		$context->registerService(\OCA\ProjectCheck\Middleware\AppAccessMiddleware::class, function ($c) {
			return new \OCA\ProjectCheck\Middleware\AppAccessMiddleware(
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCA\ProjectCheck\Service\AccessControlService::class),
				$c->query(\OCP\IRequest::class),
				$c->query(\OCP\IURLGenerator::class),
				$c->query(\OCP\L10N\IFactory::class),
				$c->query(\Psr\Log\LoggerInterface::class)
			);
		});
		$context->registerMiddleware(\OCA\ProjectCheck\Middleware\AppAccessMiddleware::class);

		// Register services
		$context->registerService('ProjectService', function ($c) {
			return new \OCA\ProjectCheck\Service\ProjectService(
				$c->query(\OCP\IDBConnection::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCP\IUserManager::class),
				$c->query(\OCP\IConfig::class),
				$c->query(\OCP\IGroupManager::class),
				$c->query(\OCA\ProjectCheck\Db\ProjectMapper::class),
				$c->query(\OCA\ProjectCheck\Service\BudgetService::class),
				$c->query(\OCA\ProjectCheck\Service\AccessControlService::class)
			);
		});

		$context->registerService('CustomerService', function ($c) {
			return new \OCA\ProjectCheck\Service\CustomerService(
				$c->query(\OCA\ProjectCheck\Db\CustomerMapper::class),
				$c->query('ProjectService'),
				$c->query(\OCA\ProjectCheck\Db\TimeEntryMapper::class),
				$c->query(\OCA\ProjectCheck\Service\AccessControlService::class)
			);
		});

		$context->registerService('TimeEntryService', function ($c) {
			return new \OCA\ProjectCheck\Service\TimeEntryService(
				$c->query(\OCA\ProjectCheck\Db\TimeEntryMapper::class),
				$c->query(\OCA\ProjectCheck\Db\ProjectMapper::class),
				$c->query('ProjectService'),
				$c->query(\OCP\L10N\IFactory::class)->get(self::APP_ID)
			);
		});

		$context->registerService('BudgetService', function ($c) {
			return new \OCA\ProjectCheck\Service\BudgetService(
				$c->query(\OCA\ProjectCheck\Db\TimeEntryMapper::class),
				$c->query(\OCP\IConfig::class),
				$c->query(\Psr\Log\LoggerInterface::class),
				$c->query(\OCP\L10N\IFactory::class)->get(self::APP_ID),
				self::APP_ID
			);
		});

		$context->registerService('ProjectMemberService', function ($c) {
			return new \OCA\ProjectCheck\Service\ProjectMemberService(
				$c->query(\OCP\IDBConnection::class),
				$c->query(\OCA\ProjectCheck\Db\ProjectMapper::class),
				$c->query(\OCA\ProjectCheck\Db\TimeEntryMapper::class)
			);
		});

		// Register ProjectMemberService with its class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\ProjectMemberService::class, function ($c) {
			return $c->query('ProjectMemberService');
		});

		$context->registerService('DeletionService', function ($c) {
			return new \OCA\ProjectCheck\Service\DeletionService(
				$c->query(\OCP\IDBConnection::class),
				$c->query(\OCA\ProjectCheck\Db\CustomerMapper::class),
				$c->query(\OCA\ProjectCheck\Db\TimeEntryMapper::class),
				$c->query(\OCA\ProjectCheck\Db\ProjectMapper::class),
				$c->query('ProjectService'),
				$c->query('ProjectMemberService')
			);
		});

		// Register DeletionService with its class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\DeletionService::class, function ($c) {
			return $c->query('DeletionService');
		});

		$context->registerService('ActivityService', function ($c) {
			return new \OCA\ProjectCheck\Service\ActivityService(
				$c->query(\OCP\Activity\IManager::class)
			);
		});

		// Register ActivityService with its class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\ActivityService::class, function ($c) {
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

		// CSPService requires ContentSecurityPolicyNonceManager (server container). Never use new CSPService() with no args.
		$context->registerService(\OCA\ProjectCheck\Service\CSPService::class, function ($c) {
			return new \OCA\ProjectCheck\Service\CSPService(
				$c->query(ContentSecurityPolicyNonceManager::class)
			);
		});
		$context->registerService(\OCA\ProjectCheck\Service\DefaultRequestTokenService::class, function ($c) {
			return new \OCA\ProjectCheck\Service\DefaultRequestTokenService(
				$c->query(\OC\Security\CSRF\CsrfTokenManager::class)
			);
		});
		$context->registerService(
			\OCA\ProjectCheck\Service\IRequestTokenProvider::class,
			fn ($c) => $c->query(\OCA\ProjectCheck\Service\DefaultRequestTokenService::class)
		);

		// Register DateFormatService
		$context->registerService(\OCA\ProjectCheck\Service\DateFormatService::class, function ($c) {
			return new \OCA\ProjectCheck\Service\DateFormatService(
				$c->query(\OCP\IConfig::class),
				$c->query(\OCP\IUserSession::class)
			);
		});

		$context->registerService(\OCA\ProjectCheck\Service\JsL10nCatalogBuilder::class, function ($c) {
			return new \OCA\ProjectCheck\Service\JsL10nCatalogBuilder(
				$c->query(\OCP\L10N\IFactory::class),
				$c->query(\OCP\App\IAppManager::class)
			);
		});

		// Register BudgetAlertService
		$context->registerService(\OCA\ProjectCheck\Service\BudgetAlertService::class, function ($c) {
			return new \OCA\ProjectCheck\Service\BudgetAlertService(
				$c->query(\OCP\IConfig::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCP\Notification\IManager::class),
				$c->query(\OCP\IUserManager::class),
				$c->query(\Psr\Log\LoggerInterface::class),
				$c->query(\OCA\ProjectCheck\Service\ProjectService::class),
				$c->query(\OCA\ProjectCheck\Service\TimeEntryService::class)
			);
		});

		// Register BudgetService with class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\BudgetService::class, function ($c) {
			return $c->query('BudgetService');
		});

		// Register TimeEntryService with class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\TimeEntryService::class, function ($c) {
			return $c->query('TimeEntryService');
		});

		// Register ProjectService with class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\ProjectService::class, function ($c) {
			return $c->query('ProjectService');
		});

		// Register CustomerService with class name for auto-wiring
		$context->registerService(\OCA\ProjectCheck\Service\CustomerService::class, function ($c) {
			return $c->query('CustomerService');
		});

		// Register TimeEntryController explicitly (ensures DI build)
		$context->registerService(\OCA\ProjectCheck\Controller\TimeEntryController::class, function ($c) {
			return new \OCA\ProjectCheck\Controller\TimeEntryController(
				$c->query('appName'),
				$c->query(\OCP\IRequest::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCA\ProjectCheck\Service\TimeEntryService::class),
				$c->query(\OCA\ProjectCheck\Service\ProjectService::class),
				$c->query(\OCA\ProjectCheck\Service\CustomerService::class),
				$c->query(\OCA\ProjectCheck\Service\BudgetService::class),
				$c->query(\OCP\IURLGenerator::class),
				$c->query(\OCP\IConfig::class),
				$c->query(\OCA\ProjectCheck\Service\DateFormatService::class),
				$c->query(\OCA\ProjectCheck\Service\DeletionService::class),
				$c->query(\OCA\ProjectCheck\Service\ActivityService::class),
				$c->query(\OCA\ProjectCheck\Service\CSPService::class),
				$c->query(\OCP\L10N\IFactory::class)->get(self::APP_ID),
				$c->query(\Psr\Log\LoggerInterface::class)
			);
		});

		// Alias for legacy mis-cased controller resolution
		$context->registerService(\OCA\ProjectCheck\Controller\TimeentryController::class, function ($c) {
			return $c->query(\OCA\ProjectCheck\Controller\TimeEntryController::class);
		});

		// Register mappers
		$context->registerService(\OCA\ProjectCheck\Db\ProjectMapper::class, function ($c) {
			return new \OCA\ProjectCheck\Db\ProjectMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		$context->registerService(\OCA\ProjectCheck\Db\CustomerMapper::class, function ($c) {
			return new \OCA\ProjectCheck\Db\CustomerMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		$context->registerService(\OCA\ProjectCheck\Db\TimeEntryMapper::class, function ($c) {
			return new \OCA\ProjectCheck\Db\TimeEntryMapper(
				$c->query(\OCP\IDBConnection::class)
			);
		});

		$context->registerService(\OCA\ProjectCheck\Controller\AppConfigController::class, function ($c) {
			return new \OCA\ProjectCheck\Controller\AppConfigController(
				$c->query('appName'),
				$c->query(\OCP\IRequest::class),
				$c->query(\OCP\IUserSession::class),
				$c->query(\OCA\ProjectCheck\Service\AccessControlService::class),
				$c->query(\OCP\IConfig::class),
				$c->query(\OCP\IURLGenerator::class),
				$c->query(\OCP\L10N\IFactory::class),
				$c->query(\Psr\Log\LoggerInterface::class),
				$c->query(\OCP\EventDispatcher\IEventDispatcher::class),
				$c->query(\OCA\ProjectCheck\Service\ProjectService::class),
				$c->query(\OCA\ProjectCheck\Service\CustomerService::class),
				$c->query(\OCA\ProjectCheck\Service\TimeEntryService::class),
				$c->query(\OCP\IUserManager::class),
				$c->query(\OCP\IGroupManager::class),
				$c->query(\OCA\ProjectCheck\Service\CSPService::class)
			);
		});

		// Register controllers
		$context->registerService(\OCA\ProjectCheck\Controller\ProjectMemberController::class, function ($c) {
			return new \OCA\ProjectCheck\Controller\ProjectMemberController(
				$c->query('appName'),
				$c->query(\OCP\IRequest::class),
				$c->query(\OCP\IUserSession::class),
				$c->query('ProjectMemberService'),
				$c->query(\OCA\ProjectCheck\Service\ProjectService::class),
				$c->query('DeletionService'),
				$c->query('ActivityService'),
				$c->query(\OCA\ProjectCheck\Service\CSPService::class),
				$c->query(\OCP\L10N\IFactory::class)->get(self::APP_ID)
			);
		});

		// Register capabilities
		$context->registerCapability(\OCA\ProjectCheck\Capabilities::class);

		$context->registerDashboardWidget(\OCA\ProjectCheck\Dashboard\ProjectWidget::class);

		// Register event listeners
		$context->registerEventListener(UserDeletedEvent::class, \OCA\ProjectCheck\Listener\UserDeletedListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, \OCA\ProjectCheck\Listener\EnrichTemplateNavigationContext::class);
	}

	/**
	 * Boot the app
	 */
	public function boot(IBootContext $context): void
	{
		$this->registerNavigationWhenAllowed();

		// Load CSS and JS files ONLY on projectcheck routes to avoid leaking into other apps
		try {
			$request = $this->getContainer()->get(\OCP\IRequest::class);
			$path = $request->getPathInfo();
			if (strpos($path, '/apps/projectcheck') === 0 || strpos($path, '/index.php/apps/projectcheck') === 0) {
				Util::addStyle(self::APP_ID, 'projects');
				Util::addStyle(self::APP_ID, 'deletion-modal');
				Util::addScript(self::APP_ID, 'projects');
				Util::addScript(self::APP_ID, 'common/deletion-modal');
				Util::addScript(self::APP_ID, 'service-worker-register');
			}
		} catch (\Throwable $e) {
			// If request is unavailable, do nothing to keep other apps safe
		}
	}

	/**
	 * Add top navigation only for users who may use the app (static info.xml entry removed).
	 */
	private function registerNavigationWhenAllowed(): void
	{
		try {
			$container = $this->getContainer();
			$userSession = $container->get(\OCP\IUserSession::class);
			$user = $userSession->getUser();
			if ($user === null) {
				return;
			}
			$access = $container->get(\OCA\ProjectCheck\Service\AccessControlService::class);
			if (!$access->canUseApp($user->getUID())) {
				return;
			}
			$navigationManager = $container->get(INavigationManager::class);
			$urlGenerator = $container->get(\OCP\IURLGenerator::class);
			$l10nFactory = $container->get(IFactory::class);
			$navigationManager->add(function () use ($urlGenerator, $l10nFactory): array {
				return [
					'id' => self::APP_ID,
					'app' => self::APP_ID,
					'order' => 10,
					'href' => $urlGenerator->linkToRoute('projectcheck.page.index'),
					'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
					'name' => $l10nFactory->get(self::APP_ID)->t('ProjectCheck'),
				];
			});
		} catch (\Throwable $e) {
			// best-effort
		}
	}
}
