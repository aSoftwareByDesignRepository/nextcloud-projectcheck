<?php

declare(strict_types=1);

/**
 * Load sidebar scripts listener for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCP\App\IAppManager;

/**
 * Listener for loading sidebar scripts
 */
class LoadSidebarScripts implements IEventListener
{
    /** @var IAppManager */
    private $appManager;

    /**
     * LoadSidebarScripts constructor
     *
     * @param IAppManager $appManager
     */
    public function __construct(IAppManager $appManager)
    {
        $this->appManager = $appManager;
    }

    /**
     * Handle the event
     *
     * @param Event $event
     */
    public function handle(Event $event): void
    {
        // Only load scripts if the app is enabled
        if (!$this->appManager->isEnabledForUser('projectcheck')) {
            return;
        }

        // Load CSS and JS files for the sidebar
        Util::addStyle('projectcheck', 'sidebar');
        Util::addScript('projectcheck', 'sidebar');
    }
}
