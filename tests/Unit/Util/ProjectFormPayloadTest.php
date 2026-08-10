<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\ProjectFormPayload;
use PHPUnit\Framework\TestCase;

class ProjectFormPayloadTest extends TestCase
{
	public function testFullFormCoercesEmptyDecimalsToZero(): void
	{
		$staged = ProjectFormPayload::stage([
			'name' => 'Alpha',
			'short_description' => 'Desc',
			'customer_id' => '12',
			'total_budget' => '',
			'hourly_rate' => '',
			'available_hours' => '',
			'requesttoken' => 'secret',
			'_method' => 'PUT',
		], true);

		$this->assertSame(0.0, $staged['total_budget']);
		$this->assertSame(0.0, $staged['hourly_rate']);
		$this->assertSame(0.0, $staged['available_hours']);
		$this->assertSame('Alpha', $staged['name']);
		$this->assertArrayNotHasKey('requesttoken', $staged);
		$this->assertArrayNotHasKey('_method', $staged);
	}

	public function testPartialApiOmitsEmptyDecimals(): void
	{
		$staged = ProjectFormPayload::stage([
			'status' => 'On Hold',
			'total_budget' => '',
			'hourly_rate' => '',
			'category' => '',
		], false);

		$this->assertSame(['status' => 'On Hold'], $staged);
	}

	public function testFullFormKeepsEmptyOptionalStrings(): void
	{
		$staged = ProjectFormPayload::stage([
			'name' => 'Beta',
			'category' => '',
			'tags' => '',
		], true);

		$this->assertSame('', $staged['category']);
		$this->assertSame('', $staged['tags']);
	}

	public function testFullFormCoercesWhitespaceDecimals(): void
	{
		$staged = ProjectFormPayload::stage([
			'total_budget' => '  ',
			'hourly_rate' => "\t",
		], true);

		$this->assertSame(0.0, $staged['total_budget']);
		$this->assertSame(0.0, $staged['hourly_rate']);
	}

	public function testPartialApiKeepsExplicitZeroRate(): void
	{
		$staged = ProjectFormPayload::stage([
			'hourly_rate' => '0',
			'status' => 'Active',
		], false);

		$this->assertSame(0.0, $staged['hourly_rate']);
		$this->assertSame('Active', $staged['status']);
	}

	public function testRejectsGarbageDecimal(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		ProjectFormPayload::stage(['hourly_rate' => 'nope'], true);
	}
}
