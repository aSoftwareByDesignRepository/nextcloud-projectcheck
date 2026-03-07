<?php

declare(strict_types=1);

/**
 * Personal settings for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IConfig;
use OCP\IUserSession;

/**
 * Personal settings for projectcheck app
 */
class PersonalSettings implements ISettings
{
    /** @var IConfig */
    private $config;

    /** @var IUserSession */
    private $userSession;

    /**
     * PersonalSettings constructor
     *
     * @param IConfig $config
     * @param IUserSession $userSession
     */
    public function __construct(IConfig $config, IUserSession $userSession)
    {
        $this->config = $config;
        $this->userSession = $userSession;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm()
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new TemplateResponse('projectcheck', 'personal-settings', []);
        }

        $userId = $user->getUID();
        $defaultHourlyRate = $this->config->getUserValue($userId, 'projectcheck', 'default_hourly_rate', '');
        $dashboardRefreshInterval = $this->config->getUserValue($userId, 'projectcheck', 'dashboard_refresh_interval', '30');
        $showCompletedProjects = $this->config->getUserValue($userId, 'projectcheck', 'show_completed_projects', 'yes');
        $timeEntryReminder = $this->config->getUserValue($userId, 'projectcheck', 'time_entry_reminder', 'yes');
        $emailNotifications = $this->config->getUserValue($userId, 'projectcheck', 'email_notifications', 'yes');
        $defaultTimeEntryDuration = $this->config->getUserValue($userId, 'projectcheck', 'default_time_entry_duration', '1.00');
        $budgetWarningThreshold = $this->config->getUserValue($userId, 'projectcheck', 'budget_warning_threshold', '80');
        $budgetCriticalThreshold = $this->config->getUserValue($userId, 'projectcheck', 'budget_critical_threshold', '90');

        $parameters = [
            'default_hourly_rate' => $defaultHourlyRate,
            'dashboard_refresh_interval' => $dashboardRefreshInterval,
            'show_completed_projects' => $showCompletedProjects,
            'time_entry_reminder' => $timeEntryReminder,
            'email_notifications' => $emailNotifications,
            'default_time_entry_duration' => $defaultTimeEntryDuration,
            'budget_warning_threshold' => $budgetWarningThreshold,
            'budget_critical_threshold' => $budgetCriticalThreshold
        ];

        return new TemplateResponse('projectcheck', 'personal-settings', $parameters);
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection()
    {
        return 'projectcheck';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70 will be displayed after "Sharing" (50) and before "Groupware" (80)
     */
    public function getPriority()
    {
        return 50;
    }
}
