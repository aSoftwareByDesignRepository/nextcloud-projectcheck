<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Util\BillingStatus;
use PHPUnit\Framework\TestCase;

/**
 * Strip / preview outstanding math (spec D9) without a database.
 */
class TimeEntryBillingBucketsTest extends TestCase
{
	public function testOutstandingIsOpenPlusInvoiced(): void
	{
		$buckets = [
			BillingStatus::OPEN => ['hours' => 12.5, 'amount' => 1250.00, 'count' => 3],
			BillingStatus::INVOICED => ['hours' => 40.0, 'amount' => 4000.50, 'count' => 7],
			BillingStatus::PAID => ['hours' => 100.0, 'amount' => 9999.99, 'count' => 20],
			BillingStatus::EXCLUDED => ['hours' => 2.0, 'amount' => 0.0, 'count' => 1],
		];

		$outstanding = TimeEntryBillingService::outstandingFromBuckets($buckets);

		$this->assertSame(52.5, $outstanding['hours']);
		$this->assertSame(5250.5, $outstanding['amount']);
		$this->assertSame(10, $outstanding['count']);
	}

	public function testOutstandingIgnoresPaidAndExcluded(): void
	{
		$buckets = [
			BillingStatus::OPEN => ['hours' => 0, 'amount' => 0, 'count' => 0],
			BillingStatus::INVOICED => ['hours' => 0, 'amount' => 0, 'count' => 0],
			BillingStatus::PAID => ['hours' => 162.0, 'amount' => 17764.14, 'count' => 16],
			BillingStatus::EXCLUDED => ['hours' => 5.0, 'amount' => 100.0, 'count' => 2],
		];

		$outstanding = TimeEntryBillingService::outstandingFromBuckets($buckets);

		$this->assertSame(0.0, $outstanding['hours']);
		$this->assertSame(0.0, $outstanding['amount']);
		$this->assertSame(0, $outstanding['count']);
	}

	public function testOutstandingToleratesMissingBuckets(): void
	{
		$outstanding = TimeEntryBillingService::outstandingFromBuckets([]);

		$this->assertSame(0.0, $outstanding['hours']);
		$this->assertSame(0.0, $outstanding['amount']);
		$this->assertSame(0, $outstanding['count']);
	}
}
