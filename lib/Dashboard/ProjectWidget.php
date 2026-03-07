<?php

declare(strict_types=1);

/**
 * Dashboard widget for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Dashboard;

use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;

/**
 * Dashboard widget for project overview
 */
class ProjectWidget implements IAPIWidget, IButtonWidget, IIconWidget, IWidget
{
    /** @var IL10N */
    private $l10n;

    /** @var IURLGenerator */
    private $urlGenerator;

    /** @var IUserSession */
    private $userSession;

    /** @var ProjectService */
    private $projectService;

    /** @var TimeEntryService */
    private $timeEntryService;

    /**
     * ProjectWidget constructor
     *
     * @param IL10N $l10n
     * @param IURLGenerator $urlGenerator
     * @param IUserSession $userSession
     * @param ProjectService $projectService
     * @param TimeEntryService $timeEntryService
     */
    public function __construct(
        IL10N $l10n,
        IURLGenerator $urlGenerator,
        IUserSession $userSession,
        ProjectService $projectService,
        TimeEntryService $timeEntryService
    ) {
        $this->l10n = $l10n;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->projectService = $projectService;
        $this->timeEntryService = $timeEntryService;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return 'projectcontrol-projects';
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->l10n->t('Project Control');
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return 10;
    }

    /**
     * @return string
     */
    public function getIconClass(): string
    {
        return 'icon-projectcontrol';
    }

    /**
     * @return string
     */
    public function getUrl(): ?string
    {
        return $this->urlGenerator->linkToRoute('projectcheck.page.index');
    }

    /**
     * @return WidgetButton[]
     */
    public function getWidgetButtons(string $userId, ?string $since = null): array
    {
        return [
            new WidgetButton(
                WidgetButton::TYPE_MORE,
                $this->l10n->t('View all projects'),
                $this->urlGenerator->linkToRoute('projectcheck.project.index')
            ),
            new WidgetButton(
                WidgetButton::TYPE_SETUP,
                $this->l10n->t('Add project'),
                $this->urlGenerator->linkToRoute('projectcheck.project.create')
            )
        ];
    }

    /**
     * @return array
     */
    public function getItems(string $userId, ?string $since = null, int $limit = 7): array
    {
        $user = $this->userSession->getUser();
        if (!$user || $user->getUID() !== $userId) {
            return [];
        }

        try {
            // Get user's projects
            $projects = $this->projectService->getProjectsByUser($userId, $limit);
            $items = [];

            foreach ($projects as $project) {
                // Calculate budget consumption (simplified for now)
                $budgetConsumption = 0; // TODO: Implement proper calculation
                $status = $this->getProjectStatus($project->getStatus());
                $icon = $this->getProjectIcon($project->getStatus());

                $items[] = new WidgetItem(
                    $icon,
                    $project->getName(),
                    $this->l10n->t('Budget: %1$s%% consumed', [$budgetConsumption]),
                    $this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $project->getId()]),
                    $status
                );
            }

            return $items;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get project status for display
     *
     * @param string $status
     * @return string
     */
    private function getProjectStatus(string $status): string
    {
        switch ($status) {
            case 'Active':
                return $this->l10n->t('Active');
            case 'Completed':
                return $this->l10n->t('Completed');
            case 'On Hold':
                return $this->l10n->t('On Hold');
            case 'Cancelled':
                return $this->l10n->t('Cancelled');
            default:
                return $status;
        }
    }

    /**
     * Get project icon based on status
     *
     * @param string $status
     * @return string
     */
    private function getProjectIcon(string $status): string
    {
        switch ($status) {
            case 'Active':
                return 'icon-play';
            case 'Completed':
                return 'icon-checkmark';
            case 'On Hold':
                return 'icon-pause';
            case 'Cancelled':
                return 'icon-close';
            default:
                return 'icon-projectcontrol';
        }
    }

    /**
     * @return string
     */
    public function getIconUrl(): string
    {
        return $this->urlGenerator->imagePath('projectcheck', 'app-dark.svg');
    }

    /**
     * @param string $userId
     * @param array $widgetData
     * @return array
     */
    public function load(string $userId, array $widgetData): array
    {
        return $this->getItems($userId);
    }
}
