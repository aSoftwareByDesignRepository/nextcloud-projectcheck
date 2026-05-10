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
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\SavePolicyUiStrings;

/**
 * Admin settings for projectcheck app
 */
class AdminSettings implements ISettings
{
    /** @var IConfig */
    private $config;

    /** @var IFactory */
    private $l10nFactory;

    /** @var AccessControlService */
    private $accessControl;

    /** @var IURLGenerator */
    private $urlGenerator;

    public function __construct(IConfig $config, IFactory $l10nFactory, IURLGenerator $urlGenerator, AccessControlService $accessControl)
    {
        $this->config = $config;
        $this->l10nFactory = $l10nFactory;
        $this->urlGenerator = $urlGenerator;
        $this->accessControl = $accessControl;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm()
    {
        $l = $this->l10nFactory->get('projectcheck');
        $policy = $this->accessControl->getPolicyState();
        $orgCurrency = strtoupper(trim($this->config->getAppValue('projectcheck', 'currency', 'EUR')));
        if (preg_match('/^[A-Z]{3}$/', $orgCurrency) !== 1) {
            $orgCurrency = 'EUR';
        }
        $defaultHourlyRate = $this->config->getAppValue('projectcheck', 'default_hourly_rate', '50.00');
        $defaultProjectStatus = $this->config->getAppValue('projectcheck', 'default_project_status', 'Active');
        $defaultProjectPriority = $this->config->getAppValue('projectcheck', 'default_project_priority', 'Medium');
        $budgetWarningThreshold = $this->config->getAppValue('projectcheck', 'budget_warning_threshold', '80');
        $budgetCriticalThreshold = $this->config->getAppValue('projectcheck', 'budget_critical_threshold', '90');
        $itemsPerPage = $this->config->getAppValue('projectcheck', 'items_per_page', '20');
        $maxProjectsPerUser = $this->config->getAppValue('projectcheck', 'max_projects_per_user', '100');
        $enableTimeTracking = $this->config->getAppValue('projectcheck', 'enable_time_tracking', 'yes');
        $enableCustomerManagement = $this->config->getAppValue('projectcheck', 'enable_customer_management', 'yes');
        $enableBudgetTracking = $this->config->getAppValue('projectcheck', 'enable_budget_tracking', 'yes');

        $parameters = [
            'l' => $l,
            'formUiStrings' => SavePolicyUiStrings::forForm($l),
            'policy' => $policy,
            'allowedUserLines' => implode("\n", $policy['allowedUserIds'] ?? []),
            'allowedGroupLines' => implode("\n", $policy['allowedGroupIds'] ?? []),
            'appAdminLines' => implode("\n", $policy['appAdminUserIds'] ?? []),
            'orgCurrency' => $orgCurrency,
            'default_hourly_rate' => $defaultHourlyRate,
            'default_project_status' => $defaultProjectStatus,
            'default_project_priority' => $defaultProjectPriority,
            'budget_warning_threshold' => $budgetWarningThreshold,
            'budget_critical_threshold' => $budgetCriticalThreshold,
            'items_per_page' => $itemsPerPage,
            'max_projects_per_user' => $maxProjectsPerUser,
            'enable_time_tracking' => $enableTimeTracking,
            'enable_customer_management' => $enableCustomerManagement,
            'enable_budget_tracking' => $enableBudgetTracking,
            'saveUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.savePolicy'),
            'orgSearchUsersUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.searchUsers'),
            'orgSearchGroupsUrl' => $this->urlGenerator->linkToRoute('projectcheck.app_config.searchGroups'),
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
