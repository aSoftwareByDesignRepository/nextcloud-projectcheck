<?php

declare(strict_types=1);

/**
 * Admin settings for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IConfig;

/**
 * Admin settings for projectcheck app
 */
class AdminSettings implements ISettings
{
    /** @var IConfig */
    private $config;

    /**
     * AdminSettings constructor
     *
     * @param IConfig $config
     */
    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm()
    {
        $defaultHourlyRate = $this->config->getAppValue('projectcheck', 'default_hourly_rate', '50.00');
        $budgetWarningThreshold = $this->config->getAppValue('projectcheck', 'budget_warning_threshold', '80');
        $maxProjectsPerUser = $this->config->getAppValue('projectcheck', 'max_projects_per_user', '100');
        $enableTimeTracking = $this->config->getAppValue('projectcheck', 'enable_time_tracking', 'yes');
        $enableCustomerManagement = $this->config->getAppValue('projectcheck', 'enable_customer_management', 'yes');
        $enableBudgetTracking = $this->config->getAppValue('projectcheck', 'enable_budget_tracking', 'yes');

        $parameters = [
            'default_hourly_rate' => $defaultHourlyRate,
            'budget_warning_threshold' => $budgetWarningThreshold,
            'max_projects_per_user' => $maxProjectsPerUser,
            'enable_time_tracking' => $enableTimeTracking,
            'enable_customer_management' => $enableCustomerManagement,
            'enable_budget_tracking' => $enableBudgetTracking
        ];

        return new TemplateResponse('projectcheck', 'admin-settings', $parameters);
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
