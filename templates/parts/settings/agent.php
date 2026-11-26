<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

/**
 * "AI access (MCP)" settings section — pack partial.
 *
 * Expects $agentAccess from AgentSettingsPayload::payload(). Renders
 * nothing when the user may neither mint nor see tokens (nav-gate, not a
 * disabled state). All IDs/classes use the app prefix (pc).
 *
 * @var \OCP\IL10N $l
 * @var array $agentAccess
 */

$agentAccess = is_array($agentAccess ?? null) ? $agentAccess : [];
if (($agentAccess['visible'] ?? false) !== true) {
	return;
}

$pc_state = [
	'appId' => (string)($agentAccess['appId'] ?? ''),
	'agentHeader' => (string)($agentAccess['agentHeader'] ?? ''),
	'ncUrl' => (string)($agentAccess['ncUrl'] ?? ''),
	'isAdmin' => (bool)($agentAccess['isAdmin'] ?? false),
	'canMint' => (bool)($agentAccess['canMint'] ?? false),
	'selfMintEnabled' => (bool)($agentAccess['selfMintEnabled'] ?? false),
	'tokens' => $agentAccess['tokens'] ?? [],
	'presets' => $agentAccess['presets'] ?? ['read' => [], 'act' => []],
	'allScopes' => $agentAccess['allScopes'] ?? [],
	'scopeLabels' => $agentAccess['scopeLabels'] ?? [],
];
$pc_tokens = is_array($pc_state['tokens']) ? $pc_state['tokens'] : [];
?>
<section class="pc-section pc-agent" id="pc-agent-access" aria-labelledby="pc-agent-h">
	<h2 class="pc-section-title" id="pc-agent-h"><?php p($l->t('AI access (MCP)')); ?></h2>
	<p class="pc-section-intro">
		<?php p($l->t('Connect an AI assistant to this app with a scoped token. The token works only for this app — never for your files, mail, or other apps.')); ?>
	</p>
	<div id="pc-agent-live" class="pc-visually-hidden" aria-live="polite"></div>

	<?php if ($pc_tokens === []): ?>
	<div class="pc-agent-empty" id="pc-agent-empty">
		<p class="pc-agent-empty__text"><?php p($l->t('No AI access yet.')); ?></p>
		<?php if ($pc_state['canMint']): ?>
		<button type="button" class="pc-btn pc-btn--primary" id="pc-agent-connect"><?php p($l->t('Connect your AI')); ?></button>
		<?php endif; ?>
	</div>
	<?php else: ?>
	<div class="pc-table-wrap pc-agent-tablewrap">
		<table class="pc-table pc-table--cards pc-agent-table" id="pc-agent-table">
			<thead>
				<tr>
					<th scope="col"><?php p($l->t('Label')); ?></th>
					<th scope="col"><?php p($l->t('Scopes')); ?></th>
					<th scope="col"><?php p($l->t('Created')); ?></th>
					<th scope="col"><?php p($l->t('Expires')); ?></th>
					<th scope="col"><?php p($l->t('Last used')); ?></th>
					<th scope="col"><span class="pc-visually-hidden"><?php p($l->t('Actions')); ?></span></th>
				</tr>
			</thead>
			<tbody id="pc-agent-tbody">
				<?php foreach ($pc_tokens as $pc_tok): ?>
				<tr data-token-id="<?php p((string)($pc_tok['id'] ?? '')); ?>" data-token-label="<?php p((string)($pc_tok['label'] ?? '')); ?>"<?php if (($pc_tok['state'] ?? '') !== 'active'): ?> class="pc-agent-row--inactive"<?php endif; ?>>
					<td data-cell="<?php p($l->t('Label')); ?>">
						<span class="pc-agent-label"><?php p((string)($pc_tok['label'] ?? '')); ?></span>
						<?php if (($pc_tok['state'] ?? '') === 'revoked'): ?>
							<span class="pc-badge pc-badge--neutral"><?php p($l->t('Revoked')); ?></span>
						<?php elseif (($pc_tok['state'] ?? '') === 'expired'): ?>
							<span class="pc-badge pc-badge--neutral"><?php p($l->t('Expired')); ?></span>
						<?php else: ?>
							<span class="pc-badge pc-badge--info"><?php p($l->t('Active')); ?></span>
						<?php endif; ?>
						<?php if ($pc_state['isAdmin'] && ($pc_tok['userId'] ?? '') !== ''): ?>
							<span class="pc-agent-owner"><?php p((string)$pc_tok['userId']); ?></span>
						<?php endif; ?>
					</td>
					<td data-cell="<?php p($l->t('Scopes')); ?>">
						<span class="pc-agent-scopes">
						<?php foreach (($pc_tok['scopes'] ?? []) as $pc_scope): ?>
							<span class="pc-badge pc-badge--neutral pc-agent-scope" title="<?php p((string)$pc_scope); ?>"><?php p((string)($pc_state['scopeLabels'][$pc_scope] ?? $pc_scope)); ?></span>
						<?php endforeach; ?>
						</span>
					</td>
					<td data-cell="<?php p($l->t('Created')); ?>" data-ts="<?php p((string)($pc_tok['createdAt'] ?? '')); ?>"></td>
					<td data-cell="<?php p($l->t('Expires')); ?>" data-ts="<?php p((string)($pc_tok['expiresAt'] ?? '')); ?>"></td>
					<td data-cell="<?php p($l->t('Last used')); ?>"<?php if (($pc_tok['lastUsedAt'] ?? null) === null): ?> class="pc-agent-never"<?php endif; ?> data-ts="<?php p((string)($pc_tok['lastUsedAt'] ?? '')); ?>"><?php if (($pc_tok['lastUsedAt'] ?? null) === null): ?><?php p($l->t('never')); ?><?php endif; ?></td>
					<td class="pc-row-actions">
						<?php if (($pc_tok['state'] ?? '') === 'active'): ?>
						<button type="button" class="pc-btn pc-btn--ghost pc-agent-rotate" data-token-id="<?php p((string)$pc_tok['id']); ?>"><?php p($l->t('Rotate')); ?></button>
						<button type="button" class="pc-btn pc-btn--danger pc-agent-revoke" data-token-id="<?php p((string)$pc_tok['id']); ?>" data-token-label="<?php p((string)($pc_tok['label'] ?? '')); ?>"><?php p($l->t('Revoke')); ?></button>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ($pc_state['canMint']): ?>
		<div class="pc-agent-actions">
			<button type="button" class="pc-btn pc-btn--primary" id="pc-agent-connect"><?php p($l->t('Connect your AI')); ?></button>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ($pc_state['isAdmin']): ?>
	<div class="pc-form-row pc-agent-selfmint">
		<input type="hidden" name="agent_self_mint" value="0">
		<label class="pc-checkbox-label" for="pc-agent-selfmint-cb">
			<input type="checkbox" id="pc-agent-selfmint-cb" <?php if ($pc_state['selfMintEnabled']): ?>checked<?php endif; ?>>
			<?php p($l->t('Let app users create their own AI tokens')); ?>
		</label>
		<p class="pc-form-row__hint" id="pc-agent-selfmint-hint"><?php p($l->t('When off, only app administrators can connect AI assistants.')); ?></p>
	</div>
	<?php endif; ?>

	<dialog id="pc-agent-mint-dialog" class="pc-dialog pc-agent-mint-dialog" aria-labelledby="pc-agent-mint-h">
		<div class="pc-dialog__body">
			<h3 id="pc-agent-mint-h"><?php p($l->t('Connect your AI')); ?></h3>
			<form id="pc-agent-mint-form" novalidate>
				<div class="pc-form-row">
					<label for="pc-agent-label"><?php p($l->t('Label')); ?></label>
					<input type="text" id="pc-agent-label" name="label" maxlength="190" required autocomplete="off" placeholder="<?php p($l->t('Claude on my laptop')); ?>">
					<p class="pc-field-error" id="pc-agent-label-error" hidden></p>
				</div>
				<fieldset class="pc-form-row pc-fieldset">
					<legend><?php p($l->t('What may the AI do?')); ?></legend>
					<div class="pc-choice-cards">
						<label class="pc-choice-card">
							<input type="radio" name="preset" value="read" checked>
							<span class="pc-choice-card__copy">
								<span class="pc-choice-card__title"><?php p($l->t('Read only')); ?></span>
								<span class="pc-choice-card__hint"><?php p($l->t('see your data, never change it')); ?></span>
							</span>
						</label>
						<label class="pc-choice-card">
							<input type="radio" name="preset" value="act">
							<span class="pc-choice-card__copy">
								<span class="pc-choice-card__title"><?php p($l->t('Read + act on my behalf')); ?></span>
								<span class="pc-choice-card__hint"><?php p($l->t('read and make changes — payments and legal stays with you')); ?></span>
							</span>
						</label>
						<label class="pc-choice-card">
							<input type="radio" name="preset" value="custom">
							<span class="pc-choice-card__copy">
								<span class="pc-choice-card__title"><?php p($l->t('Choose exactly')); ?></span>
								<span class="pc-choice-card__hint"><?php p($l->t('pick each permission yourself')); ?></span>
							</span>
						</label>
					</div>
				</fieldset>
				<fieldset class="pc-form-row pc-fieldset" id="pc-agent-custom-wrap" hidden>
					<legend><?php p($l->t('Permissions')); ?></legend>
					<div class="pc-agent-chips" id="pc-agent-chips"></div>
					<p class="pc-field-error" id="pc-agent-scopes-error" hidden></p>
				</fieldset>
				<p class="pc-form-row__hint"><?php p($l->t('Valid for 1 year. The token only reaches this app.')); ?></p>
			</form>
		</div>
		<footer class="pc-dialog__footer">
			<div class="pc-actions">
				<button type="button" class="pc-btn pc-btn--secondary" id="pc-agent-mint-cancel"><?php p($l->t('Cancel')); ?></button>
				<button type="submit" form="pc-agent-mint-form" class="pc-btn pc-btn--primary" id="pc-agent-mint-submit"><?php p($l->t('Create token')); ?></button>
			</div>
		</footer>
	</dialog>

	<dialog id="pc-agent-secret-dialog" class="pc-dialog pc-agent-secret-dialog" aria-labelledby="pc-agent-secret-h">
		<div class="pc-dialog__body">
			<h3 id="pc-agent-secret-h"><?php p($l->t('Your AI is connected')); ?></h3>
			<p class="pc-form-row__hint"><?php p($l->t('Copy this config into your AI client now — the token is shown only once.')); ?></p>
			<div class="pc-form-row">
				<label for="pc-agent-secret"><?php p($l->t('Token')); ?></label>
				<div class="pc-input-group">
					<input type="password" id="pc-agent-secret" readonly autocomplete="off" spellcheck="false">
					<button type="button" class="pc-btn pc-btn--ghost" id="pc-agent-secret-reveal" aria-pressed="false"><?php p($l->t('Show')); ?></button>
					<button type="button" class="pc-btn pc-btn--ghost" id="pc-agent-secret-copy"><?php p($l->t('Copy')); ?></button>
				</div>
			</div>
			<div class="pc-form-row">
				<label for="pc-agent-config"><?php p($l->t('Client config')); ?></label>
				<textarea id="pc-agent-config" class="pc-agent-config" readonly rows="10" spellcheck="false"></textarea>
			</div>
		</div>
		<footer class="pc-dialog__footer">
			<div class="pc-actions">
				<button type="button" class="pc-btn pc-btn--secondary" id="pc-agent-secret-close"><?php p($l->t('Done')); ?></button>
				<button type="button" class="pc-btn pc-btn--primary" id="pc-agent-config-copy"><?php p($l->t('Copy config')); ?></button>
			</div>
		</footer>
	</dialog>

	<dialog id="pc-agent-revoke-dialog" class="pc-dialog" aria-labelledby="pc-agent-revoke-h">
		<div class="pc-dialog__body">
			<h3 id="pc-agent-revoke-h"><?php p($l->t('Revoke token?')); ?></h3>
			<p id="pc-agent-revoke-text"></p>
		</div>
		<footer class="pc-dialog__footer">
			<div class="pc-actions">
				<button type="button" class="pc-btn pc-btn--secondary" id="pc-agent-revoke-cancel"><?php p($l->t('Cancel')); ?></button>
				<button type="button" class="pc-btn pc-btn--danger" id="pc-agent-revoke-confirm"><?php p($l->t('Revoke')); ?></button>
			</div>
		</footer>
	</dialog>

	<script type="application/json" id="pc-agent-state"><?php
		print_unescaped(json_encode($pc_state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
	?></script>
</section>
