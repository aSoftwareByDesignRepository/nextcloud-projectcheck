<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class TimeEntryServiceValidationTest extends TestCase {
	private function makeService(): TimeEntryService {
		$timeEntryMapper = $this->createMock(TimeEntryMapper::class);
		$projectMapper = $this->createMock(ProjectMapper::class);
		$projectService = $this->createMock(ProjectService::class);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new TimeEntryService(
			$timeEntryMapper,
			$projectMapper,
			$projectService,
			$l
		);
	}

	public function testValidateTimeEntryDataDetailedReturnsCodesAndMessages(): void {
		$svc = $this->makeService();
		$out = $svc->validateTimeEntryDataDetailed([]);
		$this->assertArrayHasKey('errors', $out);
		$this->assertArrayHasKey('errorCodes', $out);
		$this->assertSame('required', $out['errorCodes']['project_id'] ?? null);
		$this->assertSame('required', $out['errorCodes']['date'] ?? null);
		$this->assertSame('required', $out['errorCodes']['hours'] ?? null);
		$this->assertSame('required', $out['errorCodes']['hourly_rate'] ?? null);
		$this->assertStringContainsString('Project', $out['errors']['project_id']);
	}

	public function testHoursExceeds24Code(): void {
		$svc = $this->makeService();
		$today = (new \DateTime('today'))->format('Y-m-d');
		$out = $svc->validateTimeEntryDataDetailed([
			'project_id' => 1,
			'date' => $today,
			'hours' => 25,
			'hourly_rate' => 50,
		]);
		$this->assertSame('exceeds_24', $out['errorCodes']['hours'] ?? null);
	}

	public function testDateInFutureCode(): void {
		$svc = $this->makeService();
		$future = (new \DateTime('tomorrow'))->format('Y-m-d');
		$out = $svc->validateTimeEntryDataDetailed([
			'project_id' => 1,
			'date' => $future,
			'hours' => 1,
			'hourly_rate' => 0,
		]);
		$this->assertSame('in_future', $out['errorCodes']['date'] ?? null);
	}

	public function testValidateTimeEntryDataMatchesErrorsOnly(): void {
		$svc = $this->makeService();
		$errors = $svc->validateTimeEntryData([]);
		$detailed = $svc->validateTimeEntryDataDetailed([]);
		$this->assertEquals($detailed['errors'], $errors);
	}
}
