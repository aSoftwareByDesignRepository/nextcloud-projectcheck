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
		'search' => 'if (!$this->auditedDryRun && $this->manifest->requiresConfirm($tool) && !$this->truthyFlag(\'confirm\')) {',
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
		'search' => "\t\t\$retryAfter = (\$tool !== null && \$this->manifest->isMutating(\$tool))\n\t\t\t? \$this->tokenService->consumeMutationBudget(\$token, \$now)\n\t\t\t: \$this->tokenService->consumeReadBudget(\$token, \$now);",
		'replace' => "\t\t\$retryAfter = null;",
	],
	[
		'name' => 'mw_429_writes_audit_row',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => "\t\tif (\$retryAfter !== null) {\n\t\t\tthrow new AgentException('rate_limited', 'Agent rate limit exceeded.', 429, ['retryAfter' => \$retryAfter]);\n\t\t}",
		'replace' => "\t\tif (\$retryAfter !== null) {\n\t\t\t\$this->writeAudit(\$token, \$tool, \$path, AgentAudit::RESULT_DENIED);\n\t\t\tthrow new AgentException('rate_limited', 'Agent rate limit exceeded.', 429, ['retryAfter' => \$retryAfter]);\n\t\t}",
	],
	[
		'name' => 'mw_stray_id_whitelisted',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => "\$name !== '_route'",
		'replace' => "!in_array(\$name, ['id', '_route'], true)",
	],
	[
		'name' => 'mw_crid_ambiguity_skipped',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => "\t\tif (is_string(\$cridCamel) && trim(\$cridCamel) !== ''",
		'replace' => "\t\tif (false && is_string(\$cridCamel) && trim(\$cridCamel) !== ''",
	],
	[
		'name' => 'svc_read_budget_always_allows',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => 'if ($count <= self::READ_BUDGET_PER_WINDOW) {',
		'replace' => 'if (true) {',
	],
	[
		'name' => 'wh_job_no_ssrf_recheck',
		'file' => 'lib/BackgroundJob/AgentWebhookDeliverJob.php',
		'search' => '$pinIps = $this->urlGuard->resolveForDelivery($webhook->getUrl());',
		'replace' => '$pinIps = [];',
	],
	[
		'name' => 'wh_job_no_ip_pin',
		'file' => 'lib/BackgroundJob/AgentWebhookDeliverJob.php',
		'search' => 'if ($pinIps !== [] && defined(\'CURLOPT_RESOLVE\')) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'mw_context_arm_wrong_user',
		'file' => 'lib/Agent/AgentContext.php',
		'search' => '$this->uid = $uid;',
		'replace' => '$this->uid = \'admin\';',
	],
	[
		'name' => 'mw_dryrun_gate_skipped',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if ($this->auditedDryRun && !$this->manifest->supportsDryRun($tool)) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'mw_dryrun_still_requires_confirm',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if (!$this->auditedDryRun && $this->manifest->requiresConfirm($tool)',
		'replace' => 'if (true && $this->manifest->requiresConfirm($tool)',
	],
	[
		'name' => 'mw_dryrun_audited_as_ok',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'AgentAudit::RESULT_DRYRUN',
		'replace' => 'AgentAudit::RESULT_OK',
	],
	[
		'name' => 'manifest_dryrun_on_read_allowed',
		'file' => 'lib/Agent/AgentManifest.php',
		'search' => '($tool[\'dryRun\'] ?? null) === true && $this->isMutating($tool)',
		'replace' => '($tool[\'dryRun\'] ?? null) === true',
	],
	[
		'name' => 'mw_confirm_string_false_accepted',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'in_array($value, [true, 1, \'1\', \'true\', \'on\'], true)',
		'replace' => 'in_array($value, [true, 1, \'1\', \'true\', \'on\', \'false\'], true)',
	],
	[
		'name' => 'audit_result_default_swallows_ok',
		'file' => 'lib/Agent/AgentAudit.php',
		'search' => "protected string \$result = '';",
		'replace' => 'protected string $result = self::RESULT_OK;',
	],
	// audit_actortype_default_swallows_agent removed: dead-by-construction —
	// AgentAuditMapper::record() calls setActorType('agent') explicitly, so
	// the entity default can never reach the DB (and the column has its own
	// 'agent' default as backstop). A mutant there can never be killed by
	// unit tests that mock the mapper.
	[
		'name' => 'resolve_missing_table_leaks_500',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => "} catch (\\OCP\\DB\\Exception) {\n\t\t\t\$token = null;",
		'replace' => '} catch (\\OCP\\DB\\Exception $e) {\n\t\t\tthrow $e;',
	],
	[
		'name' => 'mint_cap_counts_dead_tokens',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => '$this->tokens->countActiveByUser($uid, $now) - ($replacing !== null ? 1 : 0);',
		'replace' => 'count($this->tokens->findByUser($uid));',
	],
	[
		'name' => 'rotate_at_cap_deadlocks',
		'file' => 'lib/Agent/AgentTokenService.php',
		'search' => ' - ($replacing !== null ? 1 : 0)',
		'replace' => '',
	],
	[
		'name' => 'audit_failure_masks_denial',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'catch (\\Throwable $e) {',
		'replace' => 'catch (\\LogicException $e) {',
	],
	[
		'name' => 'uninstall_drops_on_disable',
		'file' => 'lib/Repair/UninstallAgentTables.php',
		'search' => '		if (!UninstallRepairFlow::isRemovalContext()) {',
		'replace' => '		if (false) {',
	],
	[
		'name' => 'mint_label_array_coerced',
		'file' => 'lib/Controller/AgentTokenController.php',
		'search' => 'if (!is_string($rawLabel)) {',
		'replace' => 'if (false) {',
	],
	[
		'name' => 'foreign_token_403_oracle',
		'file' => 'lib/Controller/AgentTokenController.php',
		'search' => "if (\$token->getUserId() === \$uid || \$this->accessGate->canAdminister(\$uid)) {\n\t\t\treturn \$token;\n\t\t}\n\t\tthrow new AgentException('not_found', 'Token not found.', 404);",
		'replace' => "if (\$token->getUserId() === \$uid || \$this->accessGate->canAdminister(\$uid)) {\n\t\t\treturn \$token;\n\t\t}\n\t\tthrow new AgentException('forbidden', 'Not your token.', 403);",
	],
	[
		'name' => 'updatepolicy_nonadmin_allowed',
		'file' => 'lib/Controller/AgentTokenController.php',
		'search' => "if (!\$this->accessGate->canAdminister(\$uid)) {\n\t\t\tthrow new AgentException('forbidden', 'App admin required.', 403);",
		'replace' => 'if (false) {',
	],
	[
		'name' => 'selfmint_flag_ignored',
		'file' => 'lib/Controller/AgentTokenController.php',
		'search' => 'if ($this->selfMintEnabled() && $this->accessGate->canUseApp($uid)) {',
		'replace' => 'if (false && $this->accessGate->canUseApp($uid)) {',
	],

	// ── NH-02 outbound webhooks ──────────────────────────────────────
	[
		'name' => 'wh_guard_http_allowed',
		'file' => 'lib/Agent/WebhookUrlGuard.php',
		'search' => "if (!\$dev && \$scheme !== 'https') {",
		'replace' => 'if (false) {',
	],
	[
		'name' => 'wh_guard_userinfo_allowed',
		'file' => 'lib/Agent/WebhookUrlGuard.php',
		'search' => "if (isset(\$parts['user']) || isset(\$parts['pass'])) {",
		'replace' => 'if (false) {',
	],
	[
		'name' => 'wh_guard_private_ip_allowed',
		'file' => 'lib/Agent/WebhookUrlGuard.php',
		'search' => 'filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false',
		'replace' => 'false',
	],
	[
		'name' => 'wh_cap_ignored',
		'file' => 'lib/Controller/AgentWebhookController.php',
		'search' => '>= self::MAX_SUBSCRIPTIONS_PER_USER',
		'replace' => '>= 9999',
	],
	[
		'name' => 'wh_foreign_sub_403_oracle',
		'file' => 'lib/Controller/AgentWebhookController.php',
		'search' => "if (\$webhook === null || \$webhook->getUserId() !== \$uid) {\n\t\t\tthrow new AgentException('not_found', 'Webhook not found.', 404);",
		'replace' => "if (\$webhook === null || \$webhook->getUserId() !== \$uid) {\n\t\t\tthrow new AgentException('forbidden', 'Not your webhook.', 403);",
	],
	[
		'name' => 'wh_serialize_leaks_secret',
		'file' => 'lib/Controller/AgentWebhookController.php',
		'search' => "'events' => \$w->eventList(),",
		'replace' => "'events' => \$w->eventList(), 'secret' => \$w->getSecret(),",
	],
	[
		'name' => 'wh_wildcard_broken',
		'file' => 'lib/Agent/AgentWebhook.php',
		'search' => 'in_array(self::ALL_EVENTS, $events, true) || ',
		'replace' => '',
	],
	[
		'name' => 'wh_job_allows_redirects',
		'file' => 'lib/BackgroundJob/AgentWebhookDeliverJob.php',
		'search' => "'allow_redirects' => false,",
		'replace' => "'allow_redirects' => true,",
	],
	[
		'name' => 'wh_job_never_disables',
		'file' => 'lib/BackgroundJob/AgentWebhookDeliverJob.php',
		'search' => '$disable = !$ok && ($failCount >= self::MAX_FAILURES || $attempt + 1 >= self::MAX_ATTEMPTS);',
		'replace' => '$disable = false;',
	],
	[
		'name' => 'wh_job_delivers_to_inactive',
		'file' => 'lib/BackgroundJob/AgentWebhookDeliverJob.php',
		'search' => 'if ($webhook === null || !$webhook->isActive()) {',
		'replace' => 'if ($webhook === null) {',
	],
	[
		'name' => 'mw_emit_on_failed_write',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if ($status < 400 && !$this->auditedDryRun && $this->webhookDispatcher !== null) {',
		'replace' => 'if ($status < 400 || !$this->auditedDryRun) {',
	],
	[
		'name' => 'wh_job_drop_on_lock',
		'file' => 'lib/BackgroundJob/AgentWebhookDeliverJob.php',
		'search' => "\t\t\t\$this->jobList->add(self::class, \$argument);\n\t\t\treturn;\n\t\t}",
		'replace' => "\t\t\treturn;\n\t\t}",
	],
	[
		'name' => 'mw_emit_on_dryrun',
		'file' => 'lib/Middleware/AgentAuthMiddleware.php',
		'search' => 'if ($status < 400 && !$this->auditedDryRun && $this->webhookDispatcher !== null) {',
		'replace' => 'if ($status < 400 && true && $this->webhookDispatcher !== null) {',
	],
];

runMutations($appRoot, 'Agent', $mutants);
