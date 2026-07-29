<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\SeatRank;
use PHPUnit\Framework\TestCase;

final class SeatRankTest extends TestCase
{
	public function testRanksByAssignedAtThenId(): void
	{
		$seats = [
			['id' => 2, 'assignedAt' => 100],
			['id' => 1, 'assignedAt' => 100],
			['id' => 3, 'assignedAt' => 50],
		];
		$ranks = SeatRank::ranks($seats);
		$this->assertSame(1, $ranks[3]);
		$this->assertSame(2, $ranks[1]);
		$this->assertSame(3, $ranks[2]);
		$this->assertTrue(SeatRank::isWithinLimit($seats, 3, 1));
		$this->assertFalse(SeatRank::isWithinLimit($seats, 2, 1));
	}
}
