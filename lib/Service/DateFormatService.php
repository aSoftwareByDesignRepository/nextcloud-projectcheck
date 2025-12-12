<?php

/**
 * Date format service for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCP\IConfig;
use OCP\IUserSession;

/**
 * Service for handling date format preferences
 */
class DateFormatService
{
    /** @var IConfig */
    private $config;

    /** @var IUserSession */
    private $userSession;

    /** @var string */
    private $appName = 'projectcheck';

    /**
     * DateFormatService constructor
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
     * Get user's preferred date format
     *
     * @param string|null $userId
     * @return string
     */
    public function getUserDateFormat($userId = null)
    {
        if ($userId === null) {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : null;
        }

        if (!$userId) {
            return 'd.m.Y'; // Default format for European users
        }

        return $this->config->getUserValue($userId, $this->appName, 'date_format', 'd.m.Y');
    }

    /**
     * Get user's preferred time format
     *
     * @param string|null $userId
     * @return string
     */
    public function getUserTimeFormat($userId = null)
    {
        if ($userId === null) {
            $user = $this->userSession->getUser();
            $userId = $user ? $user->getUID() : null;
        }

        if (!$userId) {
            return 'H:i'; // Default format
        }

        return $this->config->getUserValue($userId, $this->appName, 'time_format', 'H:i');
    }

    /**
     * Format a date according to user's preferences
     *
     * @param \DateTime|string $date
     * @param string|null $userId
     * @return string
     */
    public function formatDate($date, $userId = null)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        if (!$date instanceof \DateTime) {
            return '';
        }

        $format = $this->getUserDateFormat($userId);
        return $date->format($format);
    }

    /**
     * Format a datetime according to user's preferences
     *
     * @param \DateTime|string $datetime
     * @param string|null $userId
     * @return string
     */
    public function formatDateTime($datetime, $userId = null)
    {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }

        if (!$datetime instanceof \DateTime) {
            return '';
        }

        $dateFormat = $this->getUserDateFormat($userId);
        $timeFormat = $this->getUserTimeFormat($userId);

        return $datetime->format($dateFormat . ' ' . $timeFormat);
    }

    /**
     * Parse a date string according to user's format
     *
     * @param string $dateString
     * @param string|null $userId
     * @return \DateTime|null
     */
    public function parseDate($dateString, $userId = null)
    {
        $format = $this->getUserDateFormat($userId);

        // Handle different input formats
        $formats = [
            $format, // User's preferred format
            'Y-m-d', // ISO format
            'd.m.Y', // European format
            'm/d/Y', // US format
            'd/m/Y'  // Alternative European format
        ];

        foreach ($formats as $testFormat) {
            $date = \DateTime::createFromFormat($testFormat, $dateString);
            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Get available date formats
     *
     * @return array
     */
    public function getAvailableDateFormats()
    {
        return [
            'Y-m-d' => 'YYYY-MM-DD',
            'd.m.Y' => 'DD.MM.YYYY',
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY'
        ];
    }

    /**
     * Get available time formats
     *
     * @return array
     */
    public function getAvailableTimeFormats()
    {
        return [
            'H:i' => '24-hour (HH:MM)',
            'h:i A' => '12-hour (HH:MM AM/PM)',
            'h:i a' => '12-hour (HH:MM am/pm)'
        ];
    }
}
