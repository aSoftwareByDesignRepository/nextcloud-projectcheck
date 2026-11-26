<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Agent;

/**
 * Scope vocabulary (pinned): `app:resource:action`, action ∈ {read, write}.
 * Exact string containment in the token's scope set — NO wildcards, NO
 * prefix matching, NO case folding. `projectcheck:time_entries:read` must
 * never satisfy `projectcheck:time_entries:write`.
 */
final class AgentScopeChecker
{
	/**
	 * Well-formed scope: app:resource:action, lowercase segments,
	 * action ∈ {read, write}. Anything else fails closed.
	 */
	public static function isValidFormat(string $scope): bool
	{
		return preg_match('/^[a-z0-9_]+:[a-z0-9_]+:(read|write)\z/', $scope) === 1;
	}

	public static function containsWildcard(string $scope): bool
	{
		return str_contains($scope, '*');
	}

	/**
	 * Exact-match satisfaction — the only rule that exists in v1.
	 *
	 * @param list<string> $granted
	 */
	public static function satisfied(array $granted, string $required): bool
	{
		return in_array($required, $granted, true);
	}

	/**
	 * Normalize + validate a requested scope list against the manifest's
	 * declared universe. Returns the deduplicated grant list or throws.
	 *
	 * @param list<mixed> $requested
	 * @param list<string> $declared
	 * @return list<string>
	 */
	public static function validateGrantRequest(array $requested, array $declared): array
	{
		$grants = [];
		foreach ($requested as $scope) {
			if (!is_string($scope)) {
				throw new AgentException('validation', 'Each scope must be a string.', 422, [
					'fields' => ['scopes' => 'invalid_type'],
				]);
			}
			$scope = trim($scope);
			if ($scope === '') {
				continue;
			}
			if (!self::isValidFormat($scope) || self::containsWildcard($scope)) {
				throw new AgentException('validation', 'Invalid scope: ' . $scope, 422, [
					'fields' => ['scopes' => 'invalid_format'],
				]);
			}
			if (!in_array($scope, $declared, true)) {
				// Never grant what the contract does not declare.
				throw new AgentException('validation', 'Undeclared scope: ' . $scope, 422, [
					'fields' => ['scopes' => 'undeclared_scope'],
				]);
			}
			$grants[$scope] = true;
		}
		$result = array_keys($grants);
		if ($result === []) {
			throw new AgentException('validation', 'At least one scope is required.', 422, [
				'fields' => ['scopes' => 'required'],
			]);
		}
		return $result;
	}
}
