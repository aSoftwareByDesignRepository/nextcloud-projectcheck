<?php

declare(strict_types=1);

/**
 * Mutation gauntlet for the agent-auth pack (projectcheck render).
 *
 * Every mutant attacks a security invariant — a surviving mutant means a
 * test hole an attacker could drive a truck through. Survivors are
 * release-blockers by definition.
 *
 * Usage (from app root):
 *   php tests/Mutation/run-agent-auth-mutations.php
 */

$appRoot = dirname(__DIR__, 2);
require $appRoot . '/tests/Mutation/harness.php';

$mutants = [
	[
		'name' => 'scope_regex_trailing_newline',
		'file' => 'lib/Agent/AgentScopeChecker.php',
		'search' => '(read|write)\\z/',
		'replace' => '(read|write)$/',
	],
	[
		'name' => 'scope_regex_allows_delete',
		'file' => 'lib/Agent/AgentScopeChecker.php',
		'search' => '(read|write)',
		'replace' => '(read|write|delete)',
	],
	[
		'name' => 'scope_satisfied_prefix_match',
		'file' => 'lib/Agent/AgentScopeChecker.php',
		'search' => 'in_array($required, $granted, true)',
		'replace' => '(static function () use ($granted, $required) { foreach ($granted as $g) { if (str_starts_with($required, $g)) { return true; } } return false; })()',
	],
	[
		'name' => 'manifest_path_dotstar',
		'file' => 'lib/Agent/AgentManifest.php',
		'search' => '\'[^/]+\', $pattern',
		'replace' => '\'.*\', $pattern',
	],
	[
		'name' => 'manifest_requires_confirm_off',
		'file' => 'lib/Agent/AgentManifest.php',
		'search' => 'requiresConfirm',
		'replace' => 'requiresConfirmUnused',
	],

	[
		'name' => 'token_resolve_ignores_revoked',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => '$token->isRevoked() || ',
		'replace' => '',
	],
	[
		'name' => 'token_resolve_ignores_expiry',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => '|| $token->isExpired($now)',
		'replace' => '',
	],
	[
		'name' => 'token_mint_skips_cap',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => '>= self::MAX_TOKENS_PER_USER',
		'replace' => '>= 999999',
	],
	[
		'name' => 'token_prefix_widened',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => 'str_starts_with($value, \'ckst_\')',
		'replace' => 'true',
	],
	[
		'name' => 'token_budget_always_allows',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => 'if ($count <= self::MUTATION_BUDGET_PER_WINDOW)',
		'replace' => 'if (true)',
	],
	[
		'name' => 'token_serialize_leaks_hash',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => '\'scopes\' => $token->scopeList(),',
		'replace' => '\'scopes\' => $token->scopeList(), \'tokenHash\' => $token->getTokenHash(),',
	],
	[
		'name' => 'mw_skips_admin_prefix_guard',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'foreach (self::ADMIN_PREFIXES as $prefix)',
		'replace' => 'foreach ([] as $prefix)',
	],
	[
		'name' => 'mw_undeclared_path_allowed',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'throw new AgentException(\'agent_scope_denied\', \'No declared agent tool for this route.\', 403);',
		'replace' => '$tool = [];',
	],
	[
		'name' => 'mw_scope_check_bypassed',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if (!$this->agentContext->hasScope($scope)) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'mw_seat_check_bypassed',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => '$this->seatGate->assertEntitled($token->getUserId());',
		'replace' => 'null;',
	],
	[
		'name' => 'mw_confirm_gate_bypassed',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if ($this->manifest->requiresConfirm($tool) && !$this->confirmFlagIsTrue()) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'mw_basic_base64_bypass',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if (stripos($auth, \'Basic \') === 0) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'mw_unknown_param_allowed',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => '$this->assertNoUnknownParams($tool, $path);',
		'replace' => 'null;',
	],
	[
		'name' => 'mw_rate_limit_bypassed',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if ($this->manifest->isMutating($tool)) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'mw_context_arm_wrong_user',
		'file' => 'lib/Agent/AgentContext.php',
		'search' => '$this->uid = $uid;',
		'replace' => '$this->uid = \'admin\';',
	],
	[
		'name' => 'mw_confirm_string_false_accepted',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'in_array($value, [true, 1, \'1\', \'true\', \'on\'], true)',
		'replace' => 'in_array($value, [true, 1, \'1\', \'true\', \'on\', \'false\'], true)',
	],
];

runMutations($appRoot, 'Agent', $mutants);
