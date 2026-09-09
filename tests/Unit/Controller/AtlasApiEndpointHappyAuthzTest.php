<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\AppConfigController;
use OCA\ProjectCheck\Controller\CustomerController;
use OCA\ProjectCheck\Controller\DashboardController;
use OCA\ProjectCheck\Controller\EmployeeController;
use OCA\ProjectCheck\Controller\HealthController;
use OCA\ProjectCheck\Controller\LicenseController;
use OCA\ProjectCheck\Controller\MobileController;
use OCA\ProjectCheck\Controller\PageController;
use OCA\ProjectCheck\Controller\ProjectController;
use OCA\ProjectCheck\Controller\ProjectFileController;
use OCA\ProjectCheck\Controller\ProjectMemberController;
use OCA\ProjectCheck\Controller\ServiceWorkerController;
use OCA\ProjectCheck\Controller\SettlementController;
use OCA\ProjectCheck\Controller\TimeEntryController;
use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\UserAccountSnapshotMapper;
use OCA\ProjectCheck\Exception\AppAccessDeniedException;
use OCA\ProjectCheck\Exception\LicenseException;
use OCA\ProjectCheck\Exception\MobileGateException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\ActivityService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\CustomerSettlementService;
use OCA\ProjectCheck\Service\DateFormatService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\EmployeeHourlyRateService;
use OCA\ProjectCheck\Service\FormSubmitIdempotencyService;
use OCA\ProjectCheck\Service\HourlyRateService;
use OCA\ProjectCheck\Service\IRequestTokenProvider;
use OCA\ProjectCheck\Service\LicenseService;
use OCA\ProjectCheck\Service\ListExportService;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\MobileGateService;
use OCA\ProjectCheck\Service\MobileSettlementService;
use OCA\ProjectCheck\Service\ProjectFileService;
use OCA\ProjectCheck\Service\ProjectMemberHourlyRateService;
use OCA\ProjectCheck\Service\ProjectMemberService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCA\ProjectCheck\Service\SettingsSectionCatalog;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Authentication\Token\IProvider;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Atlas v3 — per-endpoint happy (2xx / designed status + body) and AuthZ deny proofs.
 * Object/mutating AuthZ invokes the controller action (never middleware app-door alone).
 * Never treats HTTP 404 / NotFoundResponse as AuthZ deny.
 */
final class AtlasApiEndpointHappyAuthzTest extends TestCase
{
	/** @var array<string, MockObject|object> */
	private array $byType = [];

	private string $httpMethod = 'GET';

	private const CONTROLLERS = [
		PageController::class,
		HealthController::class,
		ServiceWorkerController::class,
		DashboardController::class,
		ProjectController::class,
		ProjectFileController::class,
		CustomerController::class,
		EmployeeController::class,
		TimeEntryController::class,
		SettlementController::class,
		AppConfigController::class,
		ProjectMemberController::class,
		LicenseController::class,
		MobileController::class,
	];

	/** @var array<string, list<string>> */
	private const AUTHZ_ACTIONS = [
		ProjectController::class => [
			'show', 'edit', 'store', 'update', 'updatePost', 'delete', 'deletePost',
			'changeStatus', 'changeStatusPost', 'getTeamMembers', 'searchAssignableUsers',
			'addTeamMember', 'addAllTeamMembers', 'updateTeamMember', 'updateTeamMemberRole',
			'removeTeamMember', 'removeTeamMemberPost', 'apiStore', 'apiShow', 'apiUpdate',
			'apiDelete', 'getDeletionImpact', 'apiByCustomer', 'checkBudgetImpact',
			'getBudgetInfo', 'resolveHourlyRate',
		],
		ProjectFileController::class => ['upload', 'list', 'download', 'delete', 'deletePost'],
		CustomerController::class => [
			'show', 'edit', 'store', 'update', 'updatePost', 'delete', 'deletePost', 'getDeletionImpact',
		],
		EmployeeController::class => [
			'show', 'assignProject', 'unassignProject', 'unassignProjectPost', 'addHourlyRate',
		],
		TimeEntryController::class => [
			'show', 'edit', 'store', 'update', 'updatePost', 'delete', 'deletePost',
			'getDeletionImpact', 'getForProject',
		],
		SettlementController::class => [
			'changeEntryStatus', 'preview', 'bulk', 'projectPreview', 'projectApply',
		],
		AppConfigController::class => [
			'settingsSection', 'savePolicy', 'searchUsers', 'searchGroups',
		],
		ProjectMemberController::class => ['getDeletionImpact', 'remove', 'removePost'],
		LicenseController::class => [
			'show', 'apply', 'remove', 'seats', 'assignSeat', 'removeSeat',
		],
		MobileController::class => [
			'projects', 'resolveHourlyRate', 'timeEntries', 'createTimeEntry',
			'updateTimeEntry', 'deleteTimeEntry', 'settlementEntries', 'changeEntryBilling',
			'projectSettlementPreview', 'projectSettlementApply',
		],
	];

	/**
	 * Middleware app-door deny is NOT used for object/mutating AuthZ proofs.
	 * List/shell pages that are not authz_negative_required stay out of AUTHZ_ACTIONS.
	 */
	// Intentionally empty — kept as documentation that middleware-only AuthZ is forbidden here.

	protected function setUp(): void
	{
		parent::setUp();
		$this->byType = [];
	}

	public function testEveryControllerActionHappyPathIs2xxOrDesignedStatus(): void
	{
		$proved = [];
		$failures = [];
		foreach (self::CONTROLLERS as $class) {
			$this->byType = [];
			$ctrl = $this->buildController($class, allow: true, mode: 'happy');
			$ref = new ReflectionClass($class);
			foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->getDeclaringClass()->getName() !== $class || $method->getName() === '__construct') {
					continue;
				}
				// DI setters / non-route helpers are not HTTP endpoints
				if (preg_match('/^set[A-Z]/', $method->getName()) === 1) {
					continue;
				}
				$symbol = $ref->getShortName() . '::' . $method->getName();
				$this->httpMethod = $this->httpMethodForAction($method->getName());
				$this->byType = [];
				$ctrl = $this->buildController($class, allow: true, mode: 'happy');
				try {
					$result = $method->invokeArgs($ctrl, $this->dummyArgs($method));
				} catch (\Throwable $e) {
					$failures[] = $symbol . ' threw ' . $e::class . ': ' . $e->getMessage();
					continue;
				}
				if (!$result instanceof Response) {
					$failures[] = $symbol . ' not Response';
					continue;
				}
				$status = $result->getStatus();
				if (!(($status >= 200 && $status < 300) || ($status >= 300 && $status < 400))) {
					$body = '';
					if ($result instanceof DataResponse || $result instanceof JSONResponse) {
						$body = (string)json_encode($result->getData());
					}
					$failures[] = $symbol . ' status=' . $status . ' body=' . $body;
					continue;
				}
				// Strict Atlas envelope: 2xx JSON/Data arrays must carry affirmative
				// ok:true and/or success:true (bare payloads without either fail).
				if ($status < 300 && ($result instanceof DataResponse || $result instanceof JSONResponse)) {
					$data = $result->getData();
					if (is_array($data)) {
						$hasOk = array_key_exists('ok', $data);
						$hasSuccess = array_key_exists('success', $data);
						if ($hasOk && $data['ok'] !== true) {
							$failures[] = $symbol . ' ok!=true body=' . json_encode($data);
							continue;
						}
						if ($hasSuccess && $data['success'] !== true) {
							$failures[] = $symbol . ' success!=true body=' . json_encode($data);
							continue;
						}
						if (!$hasOk && !$hasSuccess) {
							$failures[] = $symbol . ' missing ok/success envelope body=' . json_encode($data);
							continue;
						}
					}
				}
				$proved[] = $symbol;
			}
		}
		self::assertSame([], $failures, "Happy-path failures:\n" . implode("\n", $failures));
		self::assertGreaterThanOrEqual(100, count($proved), 'expected ≥100 controller actions, got ' . count($proved));
	}

	public function testAuthzNegativePerEndpointAction(): void
	{
		$proved = [];
		$failures = [];
		foreach (self::AUTHZ_ACTIONS as $class => $actions) {
			foreach ($actions as $action) {
				$this->byType = [];
				$ref = new ReflectionClass($class);
				self::assertTrue($ref->hasMethod($action), $class . '::' . $action);
				$symbol = $ref->getShortName() . '::' . $action;

				$ctrl = $this->buildController($class, allow: false, mode: 'authz');
				$method = $ref->getMethod($action);
				try {
					$result = $method->invokeArgs($ctrl, $this->dummyArgs($method));
					if ($result instanceof NotFoundResponse) {
						$failures[] = $symbol . ' deny used NotFoundResponse/404 — not AuthZ';
						continue;
					}
					if (!$result instanceof Response) {
						$failures[] = $symbol . ' not Response';
						continue;
					}
					// HTML soft-deny: redirect away from forbidden object (e.g. EmployeeController::show).
					if ($result instanceof RedirectResponse) {
						$proved[] = $symbol . '@redirect-deny';
						continue;
					}
					// HTML object reads render an error template (HTTP 200) — prove deny via template + message.
					if ($result instanceof TemplateResponse) {
						$blob = strtolower((string)json_encode($result->getParams()));
						if ($result->getTemplateName() === 'error'
							&& (str_contains($blob, 'access denied')
								|| str_contains($blob, 'permission')
								|| str_contains($blob, 'forbidden'))) {
							$proved[] = $symbol . '@template-deny';
							continue;
						}
						$failures[] = $symbol . ' unexpected TemplateResponse name=' . $result->getTemplateName();
						continue;
					}
					$status = $result->getStatus();
					// Never treat 404 as AuthZ deny; require real forbid/unauth (≥400, ≠404).
					if ($status === 404 || $status < 400) {
						$body = '';
						if ($result instanceof DataResponse || $result instanceof JSONResponse) {
							$body = (string)json_encode($result->getData());
						}
						$failures[] = $symbol . ' deny status=' . $status . ' body=' . $body;
						continue;
					}
					if ($result instanceof DataResponse || $result instanceof JSONResponse) {
						$data = $result->getData();
						if (is_array($data) && array_key_exists('ok', $data) && $data['ok'] !== false) {
							$failures[] = $symbol . ' deny envelope ok!=false';
							continue;
						}
						if (is_array($data) && array_key_exists('success', $data) && $data['success'] !== false
							&& !isset($data['error'])) {
							// success key present and true without error → not a deny
							if ($data['success'] === true) {
								$failures[] = $symbol . ' deny envelope success=true';
								continue;
							}
						}
					}
				} catch (PermissionDeniedException|AppAccessDeniedException|MobileGateException|LicenseException $e) {
					self::assertNotSame('', $e->getMessage(), $symbol);
				} catch (\Throwable $e) {
					$failures[] = $symbol . ' threw ' . $e::class . ': ' . $e->getMessage();
					continue;
				}
				$proved[] = $symbol;
			}
		}
		self::assertSame([], $failures, "AuthZ failures:\n" . implode("\n", $failures));
		self::assertGreaterThanOrEqual(70, count($proved), 'expected ≥70 authz_negative endpoints, got ' . count($proved));
		self::assertContains('MobileController::projects', $proved);
		self::assertContains('LicenseController::apply', $proved);
		self::assertTrue(
			in_array('ProjectController::show@template-deny', $proved, true)
			|| in_array('ProjectController::apiShow', $proved, true),
			'need project object AuthZ'
		);
		self::assertNotContains('ProjectController::show@middleware', $proved, 'middleware app-door is not object AuthZ');
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @param 'happy'|'authz' $mode
	 * @return T
	 */
	private function buildController(string $class, bool $allow, string $mode): object
	{
		$ref = new ReflectionClass($class);
		$ctor = $ref->getConstructor();
		self::assertNotNull($ctor);
		$args = [];
		foreach ($ctor->getParameters() as $param) {
			$name = $param->getName();
			$type = $param->getType();
			if ($name === 'appName') {
				$args[] = 'projectcheck';
				continue;
			}
			if ($type instanceof ReflectionNamedType && $type->getName() === IRequest::class) {
				$args[] = $this->request();
				continue;
			}
			if ($type === null) {
				$args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
				continue;
			}
			$typeName = $this->resolveTypeName($type);
			if ($typeName === null) {
				$args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
				continue;
			}
			$args[] = $this->mockFor($typeName, $allow, $mode, $class);
		}
		return $ref->newInstanceArgs($args);
	}

	private function resolveTypeName(\ReflectionType $type): ?string
	{
		if ($type instanceof ReflectionNamedType) {
			return $type->isBuiltin() ? null : $type->getName();
		}
		if ($type instanceof ReflectionUnionType) {
			foreach ($type->getTypes() as $t) {
				if ($t instanceof ReflectionNamedType && !$t->isBuiltin() && $t->getName() !== 'null') {
					return $t->getName();
				}
			}
		}
		return null;
	}

	/** @param class-string $controllerClass */
	private function mockFor(string $typeName, bool $allow, string $mode, string $controllerClass): object
	{
		$key = $typeName . ':' . ($allow ? '1' : '0') . ':' . $mode . ':' . $controllerClass;
		if (isset($this->byType[$key])) {
			return $this->byType[$key];
		}

		if ($typeName === AccessControlService::class) {
			$mock = $this->createMock(AccessControlService::class);
			$mock->method('canUseApp')->willReturn($allow);
			$mock->method('canManageAppConfiguration')->willReturn($allow);
			$mock->method('canManageAppConfigurationByUser')->willReturn($allow);
			$mock->method('canManageSettings')->willReturn($allow);
			$mock->method('canManageOrganization')->willReturn($allow);
			$mock->method('isAppAdmin')->willReturn($allow);
			$mock->method('isSystemAdministrator')->willReturn($allow);
			$mock->method('getPolicyState')->willReturn([
				'accessRestrictionEnabled' => false,
				'allowedUserIds' => [],
				'allowedGroupIds' => [],
				'appAdminUserIds' => ['alice'],
			]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === IUserSession::class) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
			$user->method('getDisplayName')->willReturn('Alice');
			$session = $this->createMock(IUserSession::class);
			$session->method('getUser')->willReturn($user);
			$this->byType[$key] = $session;
			return $session;
		}

		if ($typeName === IFactory::class) {
			$l10n = $this->createMock(IL10N::class);
			$l10n->method('t')->willReturnCallback(static fn (string $s, array $p = []) => is_array($p) && $p !== [] ? vsprintf($s, $p) : $s);
			$l10n->method('getLanguageCode')->willReturn('en');
			$factory = $this->createMock(IFactory::class);
			$factory->method('get')->willReturn($l10n);
			$this->byType[$key] = $factory;
			return $factory;
		}

		if ($typeName === IL10N::class) {
			$l10n = $this->createMock(IL10N::class);
			$l10n->method('t')->willReturnCallback(static fn (string $s, array $p = []) => is_array($p) && $p !== [] ? vsprintf($s, $p) : $s);
			$l10n->method('getLanguageCode')->willReturn('en');
			$this->byType[$key] = $l10n;
			return $l10n;
		}

		if ($typeName === IURLGenerator::class) {
			$url = $this->createMock(IURLGenerator::class);
			$url->method('linkToRoute')->willReturn('/apps/projectcheck/');
			$url->method('linkToRouteAbsolute')->willReturn('http://localhost/apps/projectcheck/');
			$url->method('imagePath')->willReturn('/img/x.svg');
			$this->byType[$key] = $url;
			return $url;
		}

		if ($typeName === IConfig::class) {
			$config = $this->createMock(IConfig::class);
			$config->method('getAppValue')->willReturn('');
			$config->method('getUserValue')->willReturn('');
			$config->method('getSystemValueString')->willReturn('');
			$this->byType[$key] = $config;
			return $config;
		}

		if ($typeName === SettingsSectionCatalog::class) {
			$this->byType[$key] = new SettingsSectionCatalog();
			return $this->byType[$key];
		}

		if ($typeName === CSPService::class) {
			$mock = $this->createMock(CSPService::class);
			$mock->method('applyPolicyWithNonce')->willReturnArgument(0);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === IRequestTokenProvider::class) {
			$mock = $this->createMock(IRequestTokenProvider::class);
			$mock->method('getEncryptedRequestToken')->willReturn('csrf-token');
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ListExportService::class) {
			$this->byType[$key] = new ListExportService(
				$this->mockFor(IConfig::class, $allow, $mode, $controllerClass),
				'projectcheck'
			);
			return $this->byType[$key];
		}

		if ($typeName === MobileGateService::class) {
			$mock = $this->createMock(MobileGateService::class);
			$mock->method('assertGatePassed')->willReturnCallback(
				static function () use ($allow, $mode): void {
					if (!$allow && $mode === 'authz') {
						throw new MobileGateException('seat_required');
					}
				}
			);
			$mock->method('bootstrapPayload')->willReturn([
				'ok' => true,
				'user' => ['id' => 'alice', 'displayName' => 'Alice'],
				'license' => ['active' => true],
				'seat' => ['assigned' => true],
				'canSettle' => true,
			]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === MobileBookingService::class) {
			$mock = $this->createMock(MobileBookingService::class);
			$mock->method('listProjectsForBooking')->willReturn(['ok' => true, 'projects' => []]);
			$mock->method('resolveHourlyRate')->willReturn(['ok' => true, 'rate' => 50.0]);
			$mock->method('listMyEntries')->willReturn(['ok' => true, 'entries' => []]);
			$mock->method('createEntry')->willReturn(['ok' => true, 'entry' => ['id' => 1]]);
			$mock->method('updateEntry')->willReturn(['ok' => true, 'entry' => ['id' => 1]]);
			$mock->method('deleteEntry')->willReturnCallback(static function (): void {});
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === MobileSettlementService::class) {
			$mock = $this->createMock(MobileSettlementService::class);
			$mock->method('actorCanSettleAnything')->willReturn($allow);
			$mock->method('listSettleableEntries')->willReturn(['ok' => true, 'entries' => []]);
			$mock->method('changeEntryStatus')->willReturn(['ok' => true]);
			$mock->method('previewProjectSettle')->willReturn(['ok' => true, 'preview' => []]);
			$mock->method('applyProjectSettle')->willReturn(['ok' => true]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === LicenseService::class) {
			$mock = $this->createMock(LicenseService::class);
			$mock->method('status')->willReturn(['ok' => true, 'licensed' => true]);
			$mock->method('apply')->willReturn(['ok' => true]);
			$mock->method('remove')->willReturn(['ok' => true]);
			$mock->method('listSeats')->willReturn(['ok' => true, 'seats' => []]);
			$mock->method('assignSeat')->willReturn(['ok' => true, 'seat' => ['uid' => 'bob']]);
			$mock->method('removeSeat')->willReturnCallback(static function (): void {});
			$mock->method('buildEnvelope')->willReturn(['ok' => true]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === IAppManager::class) {
			$mock = $this->createMock(IAppManager::class);
			$mock->method('getAppVersion')->willReturn('2.0.99');
			$mock->method('isEnabledForUser')->willReturn(true);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === IUserManager::class) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('alice');
			$user->method('getDisplayName')->willReturn('Alice');
			$mock = $this->createMock(IUserManager::class);
			$mock->method('get')->willReturn($user);
			$mock->method('userExists')->willReturn(true);
			$mock->method('search')->willReturn([$user]);
			$mock->method('searchDisplayName')->willReturn([$user]);
			$mock->method('checkPassword')->willReturn($user);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === IGroupManager::class) {
			$mock = $this->createMock(IGroupManager::class);
			$mock->method('search')->willReturn([]);
			$mock->method('isAdmin')->willReturn($allow);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === IProvider::class) {
			$mock = $this->createMock(IProvider::class);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ProjectService::class) {
			$this->byType[$key] = $this->mockProjectService($allow, $mode);
			return $this->byType[$key];
		}

		if ($typeName === CustomerService::class) {
			$this->byType[$key] = $this->mockCustomerService($allow, $mode);
			return $this->byType[$key];
		}

		if ($typeName === TimeEntryService::class) {
			$this->byType[$key] = $this->mockTimeEntryService($allow, $mode);
			return $this->byType[$key];
		}

		if ($typeName === BudgetService::class) {
			$mock = $this->createMock(BudgetService::class);
			$mock->method('getProjectBudgetInfo')->willReturn([
				'totalBudget' => 1000, 'consumed' => 0, 'remaining' => 1000, 'percent' => 0,
			]);
			$mock->method('checkTimeEntryBudgetImpact')->willReturn(['ok' => true, 'impact' => []]);
			$mock->method('getBudgetThresholds')->willReturn([]);
			$mock->method('getBudgetAlertsForProjects')->willReturn([]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === HourlyRateService::class) {
			$mock = $this->createMock(HourlyRateService::class);
			$mock->method('resolvePreview')->willReturn(['rate' => 50.0, 'source' => 'project']);
			$mock->method('resolveForTimeEntry')->willReturn(50.0);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === DeletionService::class) {
			$mock = $this->createMock(DeletionService::class);
			$mock->method('getProjectDeletionImpact')->willReturn(['ok' => true, 'impact' => []]);
			$mock->method('getCustomerDeletionImpact')->willReturn(['ok' => true, 'impact' => []]);
			$mock->method('getTimeEntryDeletionImpact')->willReturn(['ok' => true, 'impact' => []]);
			$mock->method('getProjectMemberDeletionImpact')->willReturn(['ok' => true, 'impact' => []]);
			$mock->method('deleteProjectWithStrategy')->willReturn(true);
			$mock->method('deleteCustomerWithStrategy')->willReturn(true);
			$mock->method('deleteProjectMember')->willReturn(true);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ActivityService::class) {
			$mock = $this->createMock(ActivityService::class);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ProjectFileService::class) {
			$file = new \OCA\ProjectCheck\Db\ProjectFile();
			$file->setId(1);
			if (method_exists($file, 'setName')) {
				$file->setName('f.pdf');
			}
			if (method_exists($file, 'setProjectId')) {
				$file->setProjectId(1);
			}
			$simple = $this->createMock(\OCP\Files\SimpleFS\ISimpleFile::class);
			$simple->method('getName')->willReturn('f.pdf');
			$simple->method('getContent')->willReturn('x');
			$simple->method('getMimeType')->willReturn('application/pdf');
			$simple->method('getSize')->willReturn(1);
			$deny = static function () use ($allow, $mode): void {
				if (!$allow && $mode === 'authz') {
					throw new PermissionDeniedException('access', 'project file', 'Access denied');
				}
			};
			$mock = $this->createMock(ProjectFileService::class);
			$mock->method('listFiles')->willReturnCallback(static function () use ($deny, $file) {
				$deny();
				return [$file];
			});
			$mock->method('addFilesFromUpload')->willReturnCallback(static function () use ($deny, $file) {
				$deny();
				return [$file];
			});
			$mock->method('getFile')->willReturnCallback(static function () use ($deny, $file) {
				$deny();
				return $file;
			});
			$mock->method('deleteFile')->willReturnCallback(static function () use ($deny): void {
				$deny();
			});
			$mock->method('resolveFile')->willReturn($simple);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ProjectSettlementService::class) {
			$deny = static function () use ($allow, $mode): void {
				if (!$allow && $mode === 'authz') {
					throw new PermissionDeniedException('settle', 'project', 'Access denied');
				}
			};
			$mock = $this->createMock(ProjectSettlementService::class);
			$mock->method('getSettlementInfo')->willReturn([]);
			$mock->method('enrichProjectsWithSettlementInfo')->willReturnArgument(0);
			$mock->method('previewProjectSettle')->willReturnCallback(static function () use ($deny) {
				$deny();
				return ['ok' => true, 'count' => 0, 'token' => 'tok'];
			});
			$mock->method('applyProjectSettle')->willReturnCallback(static function () use ($deny) {
				$deny();
				return ['applied' => 0, 'failed' => []];
			});
			$mock->method('getOutstandingSummaryForSettler')->willReturn([]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === CustomerSettlementService::class) {
			$mock = $this->createMock(CustomerSettlementService::class);
			$mock->method('getSettlementForCustomer')->willReturn([]);
			$mock->method('getSettlementForCustomers')->willReturn([]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === TimeEntryBillingService::class) {
			$entry = $this->sampleTimeEntry();
			$entry->setBillingStatus('invoiced');
			$deny = static function () use ($allow, $mode): void {
				if (!$allow && $mode === 'authz') {
					throw new PermissionDeniedException('settle', 'time entry', 'Access denied');
				}
			};
			$mock = $this->createMock(TimeEntryBillingService::class);
			$mock->method('changeStatus')->willReturnCallback(static function () use ($deny, $entry) {
				$deny();
				return $entry;
			});
			$mock->method('bulkChangeStatusByIds')->willReturnCallback(static function () use ($deny) {
				$deny();
				return ['applied' => 0, 'failed' => []];
			});
			$mock->method('previewByFilters')->willReturnCallback(static function () use ($deny) {
				$deny();
				return ['ok' => true, 'count' => 0];
			});
			$mock->method('applyByFilters')->willReturnCallback(static function () use ($deny) {
				$deny();
				return ['applied' => 0, 'failed' => []];
			});
			$mock->method('getBillingBuckets')->willReturn([]);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ProjectMemberService::class) {
			$member = new ProjectMember();
			$member->setId(1);
			if (method_exists($member, 'setProjectId')) {
				$member->setProjectId(1);
			}
			if (method_exists($member, 'setUserId')) {
				$member->setUserId('bob');
			}
			$mock = $this->createMock(ProjectMemberService::class);
			$mock->method('canUserRemoveMember')->willReturn($allow);
			$mock->method('getMemberDeletionImpact')->willReturnCallback(static function () use ($allow, $mode) {
				if (!$allow && $mode === 'authz') {
					throw new PermissionDeniedException('remove', 'member', 'Access denied');
				}
				return ['ok' => true];
			});
			$mock->method('getProjectMember')->willReturn($member);
			$mock->method('getProjectMembers')->willReturn([$member]);
			$mock->method('addProjectMember')->willReturn($member);
			$mock->method('removeProjectMember')->willReturnCallback(static function () use ($allow, $mode) {
				if (!$allow && $mode === 'authz') {
					throw new PermissionDeniedException('remove', 'member', 'Access denied');
				}
				return true;
			});
			$mock->method('getMemberByUserAndProject')->willReturn($member);
			$mock->method('getMemberTimeEntryCount')->willReturn(0);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === ProjectMemberHourlyRateService::class) {
			$rate = new \OCA\ProjectCheck\Db\ProjectMemberHourlyRate();
			$rate->setId(1);
			$mock = $this->createMock(ProjectMemberHourlyRateService::class);
			$mock->method('listRatesForMember')->willReturn([]);
			$mock->method('appendRateRow')->willReturn($rate);
			$mock->method('isActiveTeamMember')->willReturn(true);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === EmployeeHourlyRateService::class) {
			$rate = new \OCA\ProjectCheck\Db\EmployeeHourlyRate();
			$rate->setId(1);
			$rate->setHourlyRate(50.0);
			$rate->setEffectiveFrom(new \DateTime('2026-01-01'));
			$mock = $this->createMock(EmployeeHourlyRateService::class);
			$mock->method('listRatesForUser')->willReturn([]);
			$mock->method('addRateRow')->willReturnCallback(static function () use ($allow, $mode, $rate) {
				if (!$allow && $mode === 'authz') {
					throw new PermissionDeniedException('manage', 'employee rate', 'Access denied');
				}
				return $rate;
			});
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === DateFormatService::class) {
			$mock = $this->createMock(DateFormatService::class);
			$mock->method('formatDate')->willReturn('2026-01-01');
			$mock->method('getUserDateFormat')->willReturn('Y-m-d');
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === FormSubmitIdempotencyService::class) {
			$cache = $this->createMock(\OCP\ICache::class);
			$cache->method('get')->willReturn(null);
			$cache->method('set')->willReturn(true);
			$factory = $this->createMock(\OCP\ICacheFactory::class);
			$factory->method('createDistributed')->willReturn($cache);
			$lock = $this->createMock(\OCP\Lock\ILockingProvider::class);
			$this->byType[$key] = new FormSubmitIdempotencyService($factory, $lock);
			return $this->byType[$key];
		}

		if ($typeName === UserAccountSnapshotMapper::class) {
			$snap = new \OCA\ProjectCheck\Db\UserAccountSnapshot();
			$snap->setId(1);
			if (method_exists($snap, 'setUserId')) {
				$snap->setUserId('alice');
			}
			if (method_exists($snap, 'setDisplayName')) {
				$snap->setDisplayName('Alice');
			}
			$mock = $this->createMock(UserAccountSnapshotMapper::class);
			$mock->method('getByUserId')->willReturn($snap);
			$mock->method('findByUserId')->willReturn(null);
			$this->byType[$key] = $mock;
			return $mock;
		}

		if ($typeName === LoggerInterface::class) {
			$this->byType[$key] = $this->createMock(LoggerInterface::class);
			return $this->byType[$key];
		}

		if ($typeName === IEventDispatcher::class) {
			$this->byType[$key] = $this->createMock(IEventDispatcher::class);
			return $this->byType[$key];
		}

		/** @var MockObject $mock */
		$mock = $this->createMock($typeName);
		$this->byType[$key] = $mock;
		return $mock;
	}

	private function sampleProject(): Project
	{
		$p = new Project();
		$p->setId(1);
		$p->setName('Atlas Project');
		$p->setShortDescription('short');
		$p->setDetailedDescription('detail');
		$p->setCustomerId(1);
		$p->setCustomerName('Cust');
		$p->setHourlyRate(50.0);
		$p->setTotalBudget(1000.0);
		$p->setAvailableHours(20.0);
		$p->setCategory('dev');
		$p->setPriority('normal');
		$p->setStatus('active');
		$p->setStartDate(new \DateTime('2026-01-01'));
		$p->setEndDate(new \DateTime('2026-12-31'));
		$p->setTags('');
		$p->setCreatedBy('alice');
		$p->setCreatedAt(new \DateTime('2026-01-01'));
		$p->setUpdatedAt(new \DateTime('2026-01-01'));
		$p->setProjectType('billable');
		$p->setCostRateMode('project');
		return $p;
	}

	private function sampleCustomer(): Customer
	{
		$c = new Customer();
		$c->setId(1);
		$c->setName('Cust');
		$c->setCreatedBy('alice');
		$c->setCreatedAt(new \DateTime('2026-01-01'));
		$c->setUpdatedAt(new \DateTime('2026-01-01'));
		$c->setEmail('c@example.com');
		return $c;
	}

	private function mockProjectService(bool $allow, string $mode): MockObject
	{
		$project = $this->sampleProject();
		$mock = $this->createMock(ProjectService::class);
		$can = static function () use ($allow, $mode): bool {
			return $allow || $mode !== 'authz';
		};
		foreach ([
			'canUserAccessProject', 'canUserEditProject', 'canUserDeleteProject', 'canUserManageMembers',
			'canUserCreateProject', 'canUserCreateCustomer', 'canUserChangeProjectStatus',
			'canUserAddTimeEntryForProject', 'canUserSettleProject', 'canUserSettleAnywhere',
			'canUserSettleAnything', 'canUserViewTimeEntry', 'canUserViewAllTimeEntries',
			'canManageSettings', 'canManageOrganization', 'isActiveTeamMember',
			'isAdminTimeEntryOverrideEligible', 'isStatusTransitionAllowed',
		] as $m) {
			$mock->method($m)->willReturnCallback($can);
		}
		$mock->method('getProject')->willReturn($allow || $mode !== 'authz' ? $project : $project);
		$mock->method('getProjects')->willReturn([$project]);
		$mock->method('getProjectsForListView')->willReturn([['id' => 1, 'name' => 'Atlas Project', 'status' => 'active', 'customer_name' => 'Cust']]);
		$mock->method('getUserScopedProjects')->willReturnCallback(function () use ($allow, $mode, $project) {
			if (!$allow && $mode === 'authz') {
				return [];
			}
			return [$project];
		});
		$mock->method('getUserScopedProjectIdsForCustomer')->willReturnCallback(function () use ($allow, $mode) {
			if (!$allow && $mode === 'authz') {
				return [];
			}
			return [1];
		});
		$mock->method('getProjectsByCustomer')->willReturnCallback(function () use ($project) { return [$project]; });
		$mock->method('getProjectsByStatus')->willReturn([$project]);
		$mock->method('getAllProjects')->willReturn([$project]);
		$mock->method('getUserProjects')->willReturn([$project]);
		$mock->method('searchProjects')->willReturn([$project]);
		$mock->method('filterProjects')->willReturn([$project]);
		$mock->method('countProjects')->willReturn(1);
		$mock->method('countProjectsForUser')->willReturn(1);
		$mock->method('getTotalProjectCount')->willReturn(1);
		$mock->method('createProject')->willReturn($project);
		$mock->method('updateProject')->willReturn($project);
		$mock->method('deleteProject')->willReturn(true);
		$mock->method('changeProjectStatus')->willReturn($project);
		$mock->method('getProjectTeam')->willReturn([]);
		$mock->method('getProjectTeamGrouped')->willReturn(['active' => [], 'former' => []]);
		$mock->method('getTeamMembers')->willReturn([]);
		$member = new ProjectMember();
		$member->setId(1);
		if (method_exists($member, 'setProjectId')) {
			$member->setProjectId(1);
		}
		if (method_exists($member, 'setUserId')) {
			$member->setUserId('bob');
		}
		$mock->method('addTeamMember')->willReturn($member);
		$mock->method('removeTeamMember')->willReturn(true);
		$mock->method('updateTeamMemberRole')->willReturn($member);
		$mock->method('enrichProjectsWithBudgetInfo')->willReturnArgument(0);
		$mock->method('getAllowedStatusTargets')->willReturn(['active', 'completed']);
		$mock->method('getAccessibleProjectIdListForUser')->willReturn(null);
		$mock->method('getSettleableProjectIdListForUser')->willReturn([1]);
		$memberRow = new ProjectMember();
		$memberRow->setId(1);
		if (method_exists($memberRow, 'setProjectId')) { $memberRow->setProjectId(1); }
		if (method_exists($memberRow, 'setUserId')) { $memberRow->setUserId('bob'); }
		if (method_exists($memberRow, 'setRole')) { $memberRow->setRole('Member'); }
		$mock->method('getActiveProjectMember')->willReturn($memberRow);
		return $mock;
	}

	private function mockCustomerService(bool $allow, string $mode): MockObject
	{
		$c = $this->sampleCustomer();
		$mock = $this->createMock(CustomerService::class);
		$can = static fn (): bool => $allow || $mode !== 'authz';
		foreach (['canUserViewCustomer', 'canUserEditCustomer', 'canUserDeleteCustomer', 'canUserCreateCustomer'] as $m) {
			if (method_exists(CustomerService::class, $m)) {
				$mock->method($m)->willReturnCallback($can);
			}
		}
		$mock->method('getCustomer')->willReturn($c);
		$mock->method('getCustomers')->willReturn([$c]);
		$mock->method('getAllCustomers')->willReturn([$c]);
		$mock->method('searchCustomers')->willReturn([$c]);
		$mock->method('createCustomer')->willReturn($c);
		$mock->method('updateCustomer')->willReturn($c);
		$mock->method('deleteCustomer')->willReturn(true);
		$mock->method('getCustomerStats')->willReturn([]);
		$mock->method('getCustomerStatsForUser')->willReturn([]);
		$mock->method('getCustomerSpecificStats')->willReturn([]);
		$mock->method('getCustomersForSelectForUser')->willReturn([['id' => 1, 'name' => 'Cust']]);
		$mock->method('searchCustomersForUser')->willReturn([$c]);
		$mock->method('countCustomers')->willReturn(1);
		$mock->method('getTotalCustomerCount')->willReturn(1);
		$mock->method('canDeleteCustomer')->willReturn($allow || $mode !== 'authz');
		$mock->method('validateCustomerData')->willReturn([]);
		return $mock;
	}

	private function sampleTimeEntry(): TimeEntry
	{
		$entry = new TimeEntry();
		$entry->setId(1);
		$entry->setUserId('alice');
		$entry->setProjectId(1);
		$entry->setHours(1.0);
		$entry->setDescription('work');
		$entry->setDate(new \DateTime('2026-01-15'));
		$entry->setCreatedAt(new \DateTime('2026-01-15'));
		$entry->setUpdatedAt(new \DateTime('2026-01-15'));
		$entry->setBillingStatus('open');
		$entry->setHourlyRate(50.0);
		return $entry;
	}

	private function mockTimeEntryService(bool $allow, string $mode): MockObject
	{
		$mock = $this->createMock(TimeEntryService::class);
		$entry = $this->sampleTimeEntry();
		// AuthZ object deny: entry owned by another user so canUserViewTimeEntry gates apply.
		if (!$allow && $mode === 'authz') {
			$entry->setUserId('bob');
		}
		$deny = static function () use ($allow, $mode): void {
			if (!$allow && $mode === 'authz') {
				throw new PermissionDeniedException('mutate', 'time entry', 'Access denied');
			}
		};
		$mock->method('getTimeEntry')->willReturn($entry);
		$mock->method('getAllTimeEntries')->willReturnCallback(function () use ($entry) { return [$entry]; });
		$mock->method('getTimeEntriesByUser')->willReturn([$entry]);
		$mock->method('getTimeEntriesByProject')->willReturnCallback(function () use ($entry) { return [$entry]; });
		$mock->method('getTimeEntriesWithProjectInfo')->willReturn([['id' => 1, 'user_id' => 'alice', 'project_id' => 1, 'hours' => 1, 'date' => '2026-01-15', 'description' => 'work', 'project_name' => 'Atlas']]);
		$mock->method('createTimeEntry')->willReturnCallback(static function () use ($deny, $entry) {
			$deny();
			return $entry;
		});
		$mock->method('updateTimeEntry')->willReturnCallback(static function () use ($deny, $entry) {
			$deny();
			return $entry;
		});
		$mock->method('deleteTimeEntry')->willReturnCallback(static function () use ($deny) {
			$deny();
			return true;
		});
		$mock->method('getTotalHoursForProject')->willReturn(1.0);
		$mock->method('getTotalCostForProject')->willReturn(50.0);
		$mock->method('getYearlyStatsForProject')->willReturn([]);
		$mock->method('getTimeEntryStats')->willReturn([]);
		$mock->method('searchTimeEntries')->willReturnCallback(function () use ($entry) { return [$entry]; });
		$mock->method('getTimeEntryDeletionImpact')->willReturnCallback(static function () use ($deny) {
			$deny();
			return ['ok' => true];
		});
		$mock->method('countTimeEntries')->willReturn(1);
		$mock->method('sumTimeEntriesHours')->willReturn(1.0);
		$mock->method('getEmployeeComparisonStats')->willReturn([['user_id' => 'alice', 'total_hours' => 1.0, 'total_cost' => 50.0]]);
		$mock->method('getEmployeeYearlyStats')->willReturn([]);
		$mock->method('getYearlyStatsByProjectTypeForEmployee')->willReturn([]);
		$mock->method('getDetailedYearlyStatsByProjectTypeForEmployees')->willReturn([]);
		$mock->method('getUsersWithTimeEntries')->willReturn(['alice']);
		$mock->method('getTimeEntriesByProjectAndUser')->willReturn([]);
		$mock->method('validateTimeEntryData')->willReturn(true);
		$mock->method('validateTimeEntryDataDetailed')->willReturn(['valid' => true, 'errors' => [], 'errorCodes' => []]);
		return $mock;
	}

	private function request(): IRequest
	{
		$params = [
			'q' => 'a',
			'limit' => 50,
			'offset' => 0,
			'section' => 'access',
			'name' => 'Atlas Project',
			'shortDescription' => 's',
			'detailedDescription' => 'd',
			'customerId' => 1,
			'hourlyRate' => 50,
			'totalBudget' => 1000,
			'availableHours' => 20,
			'status' => 'active',
			'priority' => 'normal',
			'category' => 'dev',
			'userId' => 'bob',
			'role' => 'Member',
			'key' => 'PC2-TEST-KEY',
			'uid' => 'bob',
			'date' => '2026-01-15',
			'hours' => 1,
			'description' => 'work',
			'projectId' => 1,
			'billingStatus' => 'open',
			'targetStatus' => 'billable',
			'ids' => [1],
			'from' => '2026-01-01',
			'to' => '2026-01-31',
			'pc_form_nonce' => 'nonce-1',
			'allowedUserIds' => ['alice'],
			'allowedGroupIds' => [],
			'appAdmins' => ['alice'],
			'accessRestriction' => false,
			'hours' => 1,
			'entryDate' => '2026-01-15',
			'startDate' => '2026-01-01',
			'endDate' => '2026-12-31',
			'project_id' => 1,
			'customer_id' => 1,
			'newHours' => 1,
			'additionalCost' => 0,
			'additional_hours' => 1,
			'entry_date' => '2026-01-15',
			'user_id' => 'alice',
			'hourly_rate' => 50,
			'confirm' => '1',
			'confirmDelete' => '1',
			'deletionStrategy' => 'cascade',
			'rate' => 50,
			'effectiveFrom' => '2026-01-01',
			'effective_from' => '2026-01-01',
			'memberUserId' => 'bob',
			'teamRole' => 'member',
			'target' => 'invoiced',
			'filters' => ['billing_status' => 'open'],
			'token' => 'tok',
			'action' => 'invoice_open',
		];
		$req = $this->createMock(IRequest::class);
		$req->method('getParam')->willReturnCallback(static function (string $k, $default = null) use ($params) {
			return $params[$k] ?? $default;
		});
		$req->method('getParams')->willReturn($params);
		$req->method('getHeader')->willReturnCallback(static function (string $h) {
			if (strcasecmp($h, 'Idempotency-Key') === 0) {
				return 'idem-1';
			}
			if (strcasecmp($h, 'X-Requested-With') === 0) {
				return 'XMLHttpRequest';
			}
			return '';
		});
		$req->method('passesCSRFCheck')->willReturn(true);
		$req->method('getMethod')->willReturnCallback(fn (): string => $this->httpMethod);
		$upload = [
			'name' => 'doc.pdf',
			'type' => 'application/pdf',
			'tmp_name' => '/tmp/pc-doc.pdf',
			'error' => 0,
			'size' => 4,
		];
		$req->method('getUploadedFile')->willReturnCallback(static function (?string $key = null) use ($upload) {
			if ($key === 'project_files') {
				return [
					'name' => ['doc.pdf'],
					'type' => ['application/pdf'],
					'tmp_name' => ['/tmp/pc-doc.pdf'],
					'error' => [0],
					'size' => [4],
				];
			}
			return $upload;
		});
		return $req;
	}

	private function httpMethodForAction(string $action): string
	{
		$a = strtolower($action);
		if (str_contains($a, 'delete') || str_contains($a, 'remove')) {
			return 'DELETE';
		}
		if (str_contains($a, 'update') || $a === 'changestatus' || $a === 'changeentrystatus') {
			return 'PUT';
		}
		if (str_contains($a, 'store') || str_contains($a, 'create') || str_contains($a, 'add')
			|| str_contains($a, 'apply') || str_contains($a, 'save') || str_contains($a, 'assign')
			|| str_contains($a, 'upload') || str_contains($a, 'bulk') || str_contains($a, 'preview')
			|| str_contains($a, 'settle') || str_contains($a, 'change')) {
			return 'POST';
		}
		return 'GET';
	}

	private function dummyArgs(ReflectionMethod $method): array
	{
		$args = [];
		foreach ($method->getParameters() as $param) {
			$type = $param->getType();
			$pname = $param->getName();
			if ($param->isDefaultValueAvailable()) {
				$args[] = $param->getDefaultValue();
				continue;
			}
			if ($type instanceof ReflectionNamedType) {
				$name = $type->getName();
				if ($type->allowsNull()) {
					$args[] = null;
					continue;
				}
				$args[] = match ($name) {
					'int' => 1,
					'string' => match ($pname) {
						'section' => 'access',
						'userId', 'uid' => 'bob',
						'status' => 'active',
						'role' => 'Member',
						default => 'x',
					},
					'bool' => true,
					'float' => 1.0,
					'array' => [],
					default => null,
				};
				continue;
			}
			// Untyped route params (legacy controllers)
			$args[] = match (true) {
				in_array($pname, ['id', 'projectId', 'fileId', 'customerId', 'memberId'], true) => 1,
				in_array($pname, ['userId', 'uid'], true) => 'bob',
				$pname === 'section' => 'access',
				default => 1,
			};
		}
		return $args;
	}
}
