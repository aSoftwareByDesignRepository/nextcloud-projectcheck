<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentScopeChecker;
use PHPUnit\Framework\TestCase;

/**
 * Scope vocabulary is exact-match only — the checks an attacker would
 * probe first (wildcards, prefix tricks, case games) must all fail closed.
 */
class AgentScopeCheckerTest extends TestCase
{
	public function testValidFormatsAccepted(): void
	{
		$this->assertTrue(AgentScopeChecker::isValidFormat('projectcheck:time_entries:read'));
		$this->assertTrue(AgentScopeChecker::isValidFormat('projectcheck:invoices:write'));
		$this->assertTrue(AgentScopeChecker::isValidFormat('a1_b2:c3_d4:write'));
	}

	public function testInvalidFormatsRejected(): void
	{
		$cases = [
			'',
			'projectcheck:time_entries',                 // missing action
			'projectcheck:time_entries:read:extra',      // too many segments
			'projectcheck:Time_Entries:READ',            // case games
			'projectcheck:*:read',                       // wildcard resource
			'projectcheck:time_entries:*',               // wildcard action
			'*:*:*',
			'projectcheck:*',                            // app-wide wildcard
			'projectcheck :time_entries:read',           // embedded space
			"projectcheck:time_entries:read\n",          // control chars
			'projectcheck:time-entries:read',            // dash not allowed
			'projectcheck::read',                        // empty resource
			':time_entries:read',                      // empty app
			'projectcheck:time_entries:delete',          // v1 action set is read|write only
		];
		foreach ($cases as $scope) {
			$this->assertFalse(
				AgentScopeChecker::isValidFormat($scope),
				"scope should be invalid: " . var_export($scope, true)
			);
		}
	}

	public function testReadDoesNotSatisfyWrite(): void
	{
		$granted = ['projectcheck:time_entries:read'];
		$this->assertFalse(
			AgentScopeChecker::satisfied($granted, 'projectcheck:time_entries:write'),
			'read scope must never satisfy a write requirement'
		);
		$this->assertTrue(AgentScopeChecker::satisfied($granted, 'projectcheck:time_entries:read'));
	}

	public function testPrefixMatchIsNotSatisfaction(): void
	{
		$granted = ['projectcheck:time_entr:write', 'projectcheck:time_entries_extended:write'];
		$this->assertFalse(
			AgentScopeChecker::satisfied($granted, 'projectcheck:time_entries:write'),
			'prefix/partial matches must not satisfy'
		);
	}

	public function testValidateGrantRequestDeduplicatesAndValidates(): void
	{
		$declared = ['projectcheck:a:read', 'projectcheck:b:write'];
		$grants = AgentScopeChecker::validateGrantRequest(
			['projectcheck:a:read', ' projectcheck:a:read ', 'projectcheck:b:write'],
			$declared,
		);
		$this->assertSame(['projectcheck:a:read', 'projectcheck:b:write'], $grants);
	}

	public function testValidateGrantRequestRejectsUndeclared(): void
	{
		$this->expectException(AgentException::class);
		AgentScopeChecker::validateGrantRequest(
			['projectcheck:secret:read'],
			['projectcheck:a:read'],
		);
	}

	public function testValidateGrantRequestRejectsWildcard(): void
	{
		try {
			AgentScopeChecker::validateGrantRequest(['projectcheck:*:read'], ['projectcheck:a:read']);
			$this->fail('wildcard grant must throw');
		} catch (AgentException $e) {
			$this->assertSame('validation', $e->getErrorCode());
		}
	}

	public function testValidateGrantRequestRejectsEmpty(): void
	{
		try {
			AgentScopeChecker::validateGrantRequest(['  '], ['projectcheck:a:read']);
			$this->fail('empty grant list must throw');
		} catch (AgentException $e) {
			$this->assertSame('validation', $e->getErrorCode());
		}
	}

	public function testValidateGrantRequestRejectsNonString(): void
	{
		try {
			AgentScopeChecker::validateGrantRequest([['nested']], ['projectcheck:a:read']);
			$this->fail('non-string scope must throw');
		} catch (AgentException $e) {
			$this->assertSame('validation', $e->getErrorCode());
		}
	}
}
