<?php
/**
 * Settings sub-page: Transfer configuration (test → production).
 * Two cards — Export (passphrase → .nctransfer download) and Import
 * (mode → file → passphrase → mandatory dry-run → confirm) — plus the
 * "Last transfer" receipt line. The report dialog is a native <dialog>.
 *
 * Synced from apps/_shared/config-transfer — do not edit app-side.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */
$isSystemAdmin = !empty($_['isSystemAdmin']);
$lastReceipt = is_array($_['transferLastReceipt'] ?? null) ? $_['transferLastReceipt'] : null;
?>
<section class="pc-card pc-section" aria-labelledby="pc-xfer-export-h" id="pc-transfer-export-card">
	<header class="pc-section__header">
		<div>
			<h2 id="pc-xfer-export-h"><?php p($l->t('Export configuration')); ?></h2>
			<p class="pc-section__sub"><?php p($l->t('Download all settings, catalogues and business data as one encrypted file — then import it on your production instance.')); ?></p>
		</div>
	</header>
	<div class="pc-callout pc-callout--info" role="note">
		<p><strong><?php p($l->t('Secrets are never included.')); ?></strong>
		<?php p($l->t('Passwords, tokens and salts stay on this instance — the file is encrypted and still does not carry them. It does contain user and group names: handle it like personal data.')); ?></p>
	</div>
	<form id="pc-transfer-export-form" class="pc-form" novalidate>
		<div class="pc-form-row">
			<label for="pc-transfer-passphrase"><?php p($l->t('Encryption passphrase')); ?></label>
			<div class="pc-input-group">
				<input type="password" id="pc-transfer-passphrase" name="passphrase" autocomplete="new-password" required minlength="12" aria-describedby="pc-transfer-passphrase-hint pc-transfer-passphrase-strength">
				<button type="button" class="pc-btn pc-btn--ghost" id="pc-transfer-passphrase-toggle" aria-pressed="false"><?php p($l->t('Show')); ?></button>
				<button type="button" class="pc-btn pc-btn--secondary" id="pc-transfer-passphrase-generate"><?php p($l->t('Generate')); ?></button>
			</div>
			<p class="pc-form-row__hint" id="pc-transfer-passphrase-hint"><?php p($l->t('At least 12 characters. Without this passphrase the file cannot be restored — store it safely.')); ?></p>
			<p class="pc-form-row__hint pc-transfer-strength" id="pc-transfer-passphrase-strength" role="status" aria-live="polite"></p>
		</div>
		<div class="pc-actions">
			<button type="submit" class="pc-btn pc-btn--primary" id="pc-transfer-export-btn">
				<?php p($l->t('Download encrypted transfer file')); ?>
			</button>
		</div>
	</form>
</section>

<section class="pc-card pc-section" aria-labelledby="pc-xfer-import-h" id="pc-transfer-import-card">
	<header class="pc-section__header">
		<div>
			<h2 id="pc-xfer-import-h"><?php p($l->t('Import configuration')); ?></h2>
			<p class="pc-section__sub"><?php p($l->t('Pick a transfer file, enter its passphrase, review the preview — then confirm. Nothing is written before you confirm.')); ?></p>
		</div>
	</header>
	<?php if ($lastReceipt !== null): ?>
	<p class="pc-transfer-last" id="pc-transfer-last">
		<?php p($l->t('Last transfer')); ?>:
		<strong><?php p((string)($lastReceipt['at'] ?? '—')); ?></strong>
		· <?php
			$transferMode = (string)($lastReceipt['mode'] ?? '');
			if ($transferMode === 'merge') {
				p($l->t('Merge'));
			} elseif ($transferMode === 'replace') {
				p($l->t('Replace'));
			} else {
				p($transferMode !== '' ? $transferMode : '—');
			}
		?>
		· <?php p(($lastReceipt['result'] ?? '') === 'applied' ? $l->t('applied') : $l->t('failed')); ?>
		<?php if (($lastReceipt['snapshotId'] ?? null) !== null): ?>
			· <?php p($l->t('undo via snapshot')); ?> <code><?php p((string)$lastReceipt['snapshotId']); ?></code>
		<?php endif; ?>
	</p>
	<?php endif; ?>
	<form id="pc-transfer-import-form" class="pc-form" novalidate>
		<fieldset class="pc-form-row pc-fieldset" aria-describedby="pc-transfer-replace-hint">
			<legend><?php p($l->t('Mode')); ?></legend>
			<div class="pc-choice-cards">
				<label class="pc-choice-card">
					<input type="radio" name="mode" value="merge" checked>
					<span class="pc-choice-card__copy">
						<span class="pc-choice-card__title"><?php p($l->t('Merge')); ?></span>
						<span class="pc-choice-card__hint"><?php p($l->t('add and update; nothing is deleted')); ?></span>
					</span>
				</label>
				<label class="pc-choice-card pc-choice-card--danger">
					<input type="radio" name="mode" value="replace" <?php if (!$isSystemAdmin): ?>disabled<?php endif; ?>>
					<span class="pc-choice-card__copy">
						<span class="pc-choice-card__title"><?php p($l->t('Replace')); ?></span>
						<span class="pc-choice-card__hint"><?php p($l->t('make identical to the file; prod-only rows are deleted')); ?></span>
					</span>
				</label>
			</div>
			<p class="pc-form-row__hint" id="pc-transfer-replace-hint">
				<?php if ($isSystemAdmin): ?>
					<?php p($l->t('Replace needs maintenance mode ON (occ maintenance:mode --on) — you will type REPLACE in the preview to confirm.')); ?>
				<?php else: ?>
					<?php p($l->t('Replace is available to Nextcloud system administrators only.')); ?>
				<?php endif; ?>
			</p>
		</fieldset>
		<div class="pc-form-row">
			<label for="pc-transfer-file"><?php p($l->t('Transfer file')); ?></label>
			<input type="file" id="pc-transfer-file" name="file" accept=".nctransfer,application/json" class="pc-file-input">
		</div>
		<div class="pc-form-row">
			<label for="pc-transfer-import-passphrase"><?php p($l->t('File passphrase')); ?></label>
			<div class="pc-input-group">
				<input type="password" id="pc-transfer-import-passphrase" name="import_passphrase" autocomplete="off">
				<button type="button" class="pc-btn pc-btn--ghost" id="pc-transfer-import-passphrase-toggle" aria-pressed="false"><?php p($l->t('Show')); ?></button>
			</div>
		</div>
		<div id="pc-transfer-import-status" class="pc-form-row__hint" role="status" aria-live="polite"></div>
	</form>
</section>

<dialog id="pc-transfer-report" class="pc-dialog pc-transfer-report" aria-labelledby="pc-transfer-report-h">
	<div class="pc-dialog__body">
		<h3 id="pc-transfer-report-h"><?php p($l->t('Import preview')); ?></h3>
		<p id="pc-transfer-report-verdict" class="pc-transfer-report__verdict"></p>
		<p id="pc-transfer-report-file" class="pc-form-row__hint"></p>
		<div id="pc-transfer-report-groups"></div>
	</div>
	<footer class="pc-dialog__footer">
		<div id="pc-transfer-replace-confirm" class="pc-form-row" hidden>
			<label for="pc-transfer-replace-token"><?php p($l->t('Type REPLACE to confirm')); ?></label>
			<input type="text" id="pc-transfer-replace-token" autocomplete="off" spellcheck="false" placeholder="REPLACE">
		</div>
		<div class="pc-actions">
			<button type="button" class="pc-btn pc-btn--secondary" id="pc-transfer-cancel" value="cancel"><?php p($l->t('Cancel')); ?></button>
			<button type="button" class="pc-btn pc-btn--primary" id="pc-transfer-confirm"><?php p($l->t('Apply import')); ?></button>
		</div>
	</footer>
</dialog>

<dialog id="pc-transfer-progress" class="pc-dialog" aria-labelledby="pc-transfer-progress-h" aria-live="polite">
	<div class="pc-dialog__body">
		<h3 id="pc-transfer-progress-h"><?php p($l->t('Applying import')); ?></h3>
		<progress id="pc-transfer-progress-bar" max="100" value="0"></progress>
		<p id="pc-transfer-progress-text"><?php p($l->t('Starting…')); ?></p>
		<p class="pc-form-row__hint"><?php p($l->t('You can close this — the result is saved and shown under “Last transfer”.')); ?></p>
	</div>
</dialog>

<div id="pc-transfer-result-live" class="pc-visually-hidden" role="status" aria-live="polite"></div>
