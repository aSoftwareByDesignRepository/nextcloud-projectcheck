<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Dashboard;

use OCA\ProjectCheck\Dashboard\ProjectWidget;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\AppIconService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCP\App\IAppManager;
use OCP\Dashboard\Model\WidgetButton;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProjectWidgetTest extends TestCase
{
	private AccessControlService&MockObject $access;
	private ProjectService&MockObject $projects;
	private BudgetService&MockObject $budget;
	private SchemaGuardService&MockObject $schema;
	private IUserSession&MockObject $session;
	private ProjectWidget $widget;

	protected function setUp(): void
	{
		parent::setUp();
		$this->access = $this->createMock(AccessControlService::class);
		$this->projects = $this->createMock(ProjectService::class);
		$this->budget = $this->createMock(BudgetService::class);
		$this->schema = $this->createMock(SchemaGuardService::class);
		$this->session = $this->createMock(IUserSession::class);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('imagePath')->willReturn('/apps/projectcheck/img/app-dashboard.svg');
		$url->method('getAbsoluteURL')->willReturnCallback(static fn (string $p) => 'https://nc.test' . $p);
		$url->method('linkToRouteAbsolute')->willReturnCallback(static function (string $route, array $params = []): string {
			if ($route === 'projectcheck.project.show') {
				return 'https://nc.test/apps/projectcheck/projects/' . ($params['id'] ?? '');
			}
			if ($route === 'projectcheck.project.index') {
				return 'https://nc.test/apps/projectcheck/projects';
			}
			if ($route === 'projectcheck.project.create') {
				return 'https://nc.test/apps/projectcheck/projects/create';
			}
			return 'https://nc.test/apps/projectcheck/';
		});
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $s, array $a = []) {
			foreach ($a as $i => $v) {
				$s = str_replace('%' . ($i + 1) . '$s', (string)$v, $s);
			}
			return $s;
		});
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppVersion')->willReturn('2.0.99');
		$icons = new AppIconService($url, $apps);
		$this->widget = new ProjectWidget(
			$l10n,
			$url,
			$this->session,
			$this->projects,
			$this->access,
			$this->budget,
			$this->schema,
			$icons,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testKeepsLegacyWidgetIdForLayoutCompatibility(): void
	{
		$this->assertSame('projectcontrol-projects', $this->widget->getId());
	}

	public function testIconUrlUsesDarkSurfaceAsset(): void
	{
		$this->assertStringContainsString('app-dashboard.svg', $this->widget->getIconUrl());
		$this->assertStringStartsWith('https://', $this->widget->getIconUrl());
	}

	public function testDisabledWhenUserCannotUseApp(): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$this->session->method('getUser')->willReturn($user);
		$this->access->method('canUseApp')->with('bob')->willReturn(false);
		$this->assertFalse($this->widget->isEnabled());
	}

	public function testItemsEmptyForDeniedUser(): void
	{
		$this->access->method('canUseApp')->with('bob')->willReturn(false);
		$this->projects->expects($this->never())->method('getProjectsByUser');
		$items = $this->widget->getItemsV2('bob');
		$this->assertSame([], $items->getItems());
		$this->assertStringContainsString('not available', $items->getEmptyContentMessage());
	}

	public function testItemsRespectLimitAndWidgetItemFieldOrder(): void
	{
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->schema->expects($this->once())->method('ensureReady');
		$project = new Project();
		$project->setId(42);
		$project->setName('Apollo');
		$project->setStatus('Active');
		$this->projects->expects($this->once())
			->method('getProjectsByUser')
			->with('alice', 7)
			->willReturn([$project]);
		$this->budget->method('getProjectBudgetInfo')->willReturn([
			'total_budget' => 1000,
			'consumption_percentage' => 12.5,
		]);

		$bag = $this->widget->getItemsV2('alice', null, 7);
		$this->assertCount(1, $bag->getItems());
		$item = $bag->getItems()[0];
		$this->assertSame('Apollo', $item->getTitle());
		$this->assertStringContainsString('Active', $item->getSubtitle());
		$this->assertStringContainsString('12.5', $item->getSubtitle());
		$this->assertSame('https://nc.test/apps/projectcheck/projects/42', $item->getLink());
		$this->assertStringContainsString('app-dashboard.svg', $item->getIconUrl());
		$this->assertStringNotContainsString('icon-play', $item->getTitle());
	}

	public function testLimitIsClampedBeforeServiceCall(): void
	{
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->schema->method('ensureReady');
		$this->projects->expects($this->once())
			->method('getProjectsByUser')
			->with('alice', 20)
			->willReturn([]);
		$this->widget->getItems('alice', null, 999);
	}

	public function testWidgetButtonsUseAbsoluteLinkAndCorrectArgOrder(): void
	{
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->projects->method('canUserCreateProject')->with('alice')->willReturn(true);
		$buttons = $this->widget->getWidgetButtons('alice');
		$this->assertCount(2, $buttons);
		$this->assertSame(WidgetButton::TYPE_MORE, $buttons[0]->getType());
		$this->assertSame('https://nc.test/apps/projectcheck/projects', $buttons[0]->getLink());
		$this->assertSame('View all projects', $buttons[0]->getText());
		$this->assertSame(WidgetButton::TYPE_NEW, $buttons[1]->getType());
		$this->assertSame('https://nc.test/apps/projectcheck/projects/create', $buttons[1]->getLink());
		$this->assertSame('Add project', $buttons[1]->getText());
	}
}
