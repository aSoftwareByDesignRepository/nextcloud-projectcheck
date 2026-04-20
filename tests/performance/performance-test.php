<?php

/**
 * Performance test script for projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

require_once __DIR__ . '/../../../lib/base.php';

use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\TimeEntry;

class PerformanceTest
{
    private $projectService;
    private $customerService;
    private $timeEntryService;
    private $testUserId = 'testuser';

    public function __construct()
    {
        $this->projectService = \OC::$server->get(ProjectService::class);
        $this->customerService = \OC::$server->get(CustomerService::class);
        $this->timeEntryService = \OC::$server->get(TimeEntryService::class);
    }

    /**
     * Run all performance tests
     */
    public function runAllTests()
    {
        echo "Starting Performance Tests...\n";
        echo "================================\n\n";

        $this->testDatabasePerformance();
        $this->testSearchPerformance();
        $this->testListPagePerformance();
        $this->testMemoryUsage();
        $this->testConcurrentAccess();

        echo "\nPerformance Tests Completed!\n";
    }

    /**
     * Test database performance with large datasets
     */
    private function testDatabasePerformance()
    {
        echo "Testing Database Performance...\n";

        $startTime = microtime(true);

        // Create test data
        $this->createTestData();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        echo "Created 100+ projects, 50+ customers, 1000+ time entries in {$duration} seconds\n";

        // Test query performance
        $this->testQueryPerformance();
    }

    /**
     * Create test data for performance testing
     */
    private function createTestData()
    {
        // Create customers
        for ($i = 1; $i <= 50; $i++) {
            $customerData = [
                'name' => "Test Customer {$i}",
                'email' => "customer{$i}@test.com",
                'phone' => "+49 123 456789{$i}",
                'address' => "Test Address {$i}, 12345 Test City",
                'contact_person' => "Contact Person {$i}"
            ];

            try {
                $this->customerService->createCustomer($customerData, $this->testUserId);
            } catch (Exception $e) {
                // Ignore duplicates
            }
        }

        // Create projects
        for ($i = 1; $i <= 100; $i++) {
            $projectData = [
                'name' => "Test Project {$i}",
                'short_description' => "Short description for project {$i}",
                'detailed_description' => "Detailed description for project {$i}",
                'customer_id' => rand(1, 50),
                'hourly_rate' => rand(50, 200),
                'total_budget' => rand(1000, 50000),
                'available_hours' => rand(10, 500),
                'category' => ['Development', 'Design', 'Consulting'][rand(0, 2)],
                'priority' => ['High', 'Medium', 'Low'][rand(0, 2)],
                'status' => ['Active', 'Completed', 'On Hold'][rand(0, 2)],
                'start_date' => date('Y-m-d', strtotime("-" . rand(1, 365) . " days")),
                'end_date' => date('Y-m-d', strtotime("+" . rand(1, 365) . " days"))
            ];

            try {
                $this->projectService->createProject($projectData, $this->testUserId);
            } catch (Exception $e) {
                // Ignore duplicates
            }
        }

        // Create time entries
        for ($i = 1; $i <= 1000; $i++) {
            $timeEntryData = [
                'project_id' => rand(1, 100),
                'date' => date('Y-m-d', strtotime("-" . rand(1, 365) . " days")),
                'hours' => rand(1, 8) + (rand(0, 3) * 0.25),
                'description' => "Time entry description {$i}",
                'hourly_rate' => rand(50, 200)
            ];

            try {
                $this->timeEntryService->createTimeEntry($timeEntryData, $this->testUserId);
            } catch (Exception $e) {
                // Ignore duplicates
            }
        }
    }

    /**
     * Test query performance
     */
    private function testQueryPerformance()
    {
        echo "Testing Query Performance...\n";

        // Test project listing
        $startTime = microtime(true);
        $projects = $this->projectService->getProjectsByUser($this->testUserId, 50);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Project listing (50 items): {$duration} seconds\n";

        // Test customer listing
        $startTime = microtime(true);
        $customers = $this->customerService->getCustomersByUser($this->testUserId, 50);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Customer listing (50 items): {$duration} seconds\n";

        // Test time entry listing
        $startTime = microtime(true);
        $timeEntries = $this->timeEntryService->getTimeEntriesByUser($this->testUserId, 100);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Time entry listing (100 items): {$duration} seconds\n";
    }

    /**
     * Test search performance
     */
    private function testSearchPerformance()
    {
        echo "Testing Search Performance...\n";

        // Test project search
        $startTime = microtime(true);
        $projects = $this->projectService->searchProjects('Test', $this->testUserId);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Project search: {$duration} seconds\n";

        // Test customer search
        $startTime = microtime(true);
        $customers = $this->customerService->searchCustomers('Test', $this->testUserId);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Customer search: {$duration} seconds\n";

        // Test time entry search
        $startTime = microtime(true);
        $timeEntries = $this->timeEntryService->searchTimeEntries('description', $this->testUserId);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "Time entry search: {$duration} seconds\n";
    }

    /**
     * Test list page loading performance
     */
    private function testListPagePerformance()
    {
        echo "Testing List Page Performance...\n";

        // Simulate list page loading
        $startTime = microtime(true);

        // Load projects with pagination
        $projects = $this->projectService->getProjectsByUser($this->testUserId, 20);

        // Load customers with pagination
        $customers = $this->customerService->getCustomersByUser($this->testUserId, 20);

        // Load time entries with pagination
        $timeEntries = $this->timeEntryService->getTimeEntriesByUser($this->testUserId, 50);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        echo "List page loading: {$duration} seconds\n";
    }

    /**
     * Test memory usage
     */
    private function testMemoryUsage()
    {
        echo "Testing Memory Usage...\n";

        $initialMemory = memory_get_usage(true);

        // Load large dataset
        $projects = $this->projectService->getProjectsByUser($this->testUserId, 1000);
        $customers = $this->customerService->getCustomersByUser($this->testUserId, 1000);
        $timeEntries = $this->timeEntryService->getTimeEntriesByUser($this->testUserId, 1000);

        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;

        echo "Memory usage for large dataset: " . $this->formatBytes($memoryUsed) . "\n";
        echo "Peak memory usage: " . $this->formatBytes(memory_get_peak_usage(true)) . "\n";
    }

    /**
     * Test concurrent access (simulated)
     */
    private function testConcurrentAccess()
    {
        echo "Testing Concurrent Access (Simulated)...\n";

        $startTime = microtime(true);

        // Simulate multiple concurrent requests
        for ($i = 0; $i < 10; $i++) {
            $this->simulateConcurrentRequest();
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        echo "Concurrent access test (10 requests): {$duration} seconds\n";
    }

    /**
     * Simulate a concurrent request
     */
    private function simulateConcurrentRequest()
    {
        // Simulate typical user request
        $projects = $this->projectService->getProjectsByUser($this->testUserId, 10);
        $customers = $this->customerService->getCustomersByUser($this->testUserId, 10);
        $timeEntries = $this->timeEntryService->getTimeEntriesByUser($this->testUserId, 20);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Run performance tests
if (php_sapi_name() === 'cli') {
    $test = new PerformanceTest();
    $test->runAllTests();
}
