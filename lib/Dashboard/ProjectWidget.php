<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Dashboard;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\AppIconService;
use OCA\ProjectCheck\Service\BudgetService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * ProjectCheck overview desklet for the Nextcloud Dashboard.
 *
 * Red-team:
 * - Fail-closed on access control and schema guard.
 * - Hard SQL/row limit — never N+1 budget over the full project list (was
 *   returning 100+ WidgetItems and hanging the dashboard).
 * - WidgetItem / WidgetButton constructor order is type-critical (title,
 *   subtitle, link, icon) — swapped args made links non-navigable.
 * - Absolute URLs only; theme-safe surface icons + desklet CSS in load().
 * - Keep legacy widget id {@code projectcontrol-projects} so existing user
 *   dashboard layouts keep the desklet.
 */
class ProjectWidget implements IAPIWidget, IAPIWidgetV2, IButtonWidget, IIconWidget, IConditionalWidget, IReloadableWidget
{
	use RegistersDeskletStylesTrait;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly IUserSession $userSession,
		private readonly ProjectService $projectService,
		private readonly AccessControlService $accessControl,
		private readonly BudgetService $budgetService,
		private readonly SchemaGuardService $schemaGuard,
		private readonly AppIconService $appIcons,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getId(): string
	{
		// Legacy id — do not rename (persisted in users' dashboard layouts).
		return 'projectcontrol-projects';
	}

	public function getTitle(): string
	{
		return $this->l10n->t('My projects');
	}

	public function getOrder(): int
	{
		return 40;
	}

	public function getIconClass(): string
	{
		return 'icon-category-organization';
	}

	public function getIconUrl(): string
	{
		return $this->appIcons->absoluteSurfaceIconUrl();
	}

	public function getUrl(): ?string
	{
		return $this->urlGenerator->linkToRouteAbsolute('projectcheck.page.index');
	}

	public function getReloadInterval(): int
	{
		return 300;
	}

	public function isEnabled(): bool
	{
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return $this->accessControl->canUseApp($user->getUID());
	}

	public function load(): void
	{
		$this->registerDeskletStylesForWidget();
	}

	public function getItems(string $userId, ?string $since = null, int $limit = 7): array
	{
		return $this->getItemsV2($userId, $since, $limit)->getItems();
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems
	{
		$empty = $this->l10n->t('No projects yet. Open ProjectCheck to create or join one.');
		if (!$this->accessControl->canUseApp($userId)) {
			return new WidgetItems([], $this->l10n->t('ProjectCheck is not available for your account.'));
		}

		$limit = max(1, min(20, $limit));
		$icon = $this->getIconUrl();

		try {
			$this->schemaGuard->ensureReady();
			$projects = $this->projectService->getProjectsByUser($userId, $limit);
			$items = [];
			foreach ($projects as $i => $project) {
				$name = trim((string)$project->getName());
				$status = $this->statusLabel((string)$project->getStatus());
				$subtitle = $status;
				try {
					$budget = $this->budgetService->getProjectBudgetInfo($project, $userId);
					$pct = (float)($budget['consumption_percentage'] ?? 0.0);
					if (($budget['total_budget'] ?? 0) > 0) {
						$subtitle = $this->l10n->t('%1$s · Budget %2$s%% used', [
							$status,
							(string)round($pct, 1),
						]);
					}
				} catch (\Throwable) {
					// Status-only subtitle if budget lookup fails for one row.
				}
				$items[] = new WidgetItem(
					$name !== '' ? $name : $this->l10n->t('Untitled project'),
					$subtitle,
					$this->urlGenerator->linkToRouteAbsolute('projectcheck.project.show', [
						'id' => $project->getId(),
					]),
					$icon,
					(string)$project->getId() . '-' . $i,
				);
			}

			if ($items === []) {
				return new WidgetItems([], $empty);
			}

			return new WidgetItems($items, '');
		} catch (SchemaRepairFailedException $e) {
			$this->logger->error('ProjectCheck dashboard widget blocked: schema repair failed', [
				'app' => Application::APP_ID,
				'userId' => $userId,
				'exception' => $e,
			]);
			return new WidgetItems([], $this->l10n->t('ProjectCheck is updating. Try again in a moment.'));
		} catch (\Throwable $e) {
			$this->logger->error('ProjectCheck dashboard widget failed', [
				'app' => Application::APP_ID,
				'userId' => $userId,
				'exception' => $e,
			]);
			return new WidgetItems([], $this->l10n->t('Could not load projects.'));
		}
	}

	public function getWidgetButtons(string $userId): array
	{
		if (!$this->accessControl->canUseApp($userId)) {
			return [];
		}

		$buttons = [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->urlGenerator->linkToRouteAbsolute('projectcheck.project.index'),
				$this->l10n->t('View all projects'),
			),
		];

		if ($this->projectService->canUserCreateProject($userId)) {
			$buttons[] = new WidgetButton(
				WidgetButton::TYPE_NEW,
				$this->urlGenerator->linkToRouteAbsolute('projectcheck.project.create'),
				$this->l10n->t('Add project'),
			);
		}

		return $buttons;
	}

	private function statusLabel(string $status): string
	{
		return match ($status) {
			'Active' => $this->l10n->t('Active'),
			'Completed' => $this->l10n->t('Completed'),
			'On Hold' => $this->l10n->t('On Hold'),
			'Cancelled' => $this->l10n->t('Cancelled'),
			'Archived' => $this->l10n->t('Archived'),
			default => $status !== '' ? $status : $this->l10n->t('Unknown'),
		};
	}
}
