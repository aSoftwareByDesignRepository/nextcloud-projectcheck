<?php

/**
 * Embeds server-translated strings for pc-l10n.js (EnrichTemplateNavigationContext → jsL10n).
 * Also exposes the org-configured currency code so js/common/format.js can pick
 * it up without a round-trip (audit ref. AUDIT-FINDINGS B10).
 * Include once per page (see common/navigation.php and admin-settings.php).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var array<string, string> $jsL10n
 */
$jsL10n = $jsL10n ?? ($_['jsL10n'] ?? null);
$orgCurrency = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? $_['orgCurrency'] : 'EUR';
$orgCurrency = preg_match('/^[A-Z]{3}$/', $orgCurrency) === 1 ? $orgCurrency : 'EUR';

if (is_array($jsL10n) && $jsL10n !== []) {
	$pcL10nJson = json_encode($jsL10n, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
	?>
	<textarea id="pc-js-l10n-raw" class="pc-js-l10n-raw" hidden readonly tabindex="-1" aria-hidden="true"><?php print_unescaped($pcL10nJson); ?></textarea>
	<?php
}
?>
<meta name="pc-currency" content="<?php p($orgCurrency); ?>">
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
	(function () {
		'use strict';
		var cfg = (window.ProjectCheckConfig = window.ProjectCheckConfig || {});
		if (!cfg.currency) {
			cfg.currency = <?php print_unescaped(json_encode($orgCurrency, JSON_THROW_ON_ERROR)); ?>;
		}
	})();
</script>
