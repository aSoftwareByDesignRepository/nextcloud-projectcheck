<?php

/**
 * Embeds server-translated strings for pc-l10n.js (EnrichTemplateNavigationContext → jsL10n).
 * Include once per page (see common/navigation.php and admin-settings.php).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var array<string, string> $jsL10n
 */
$jsL10n = $jsL10n ?? ($_['jsL10n'] ?? null);
if (!is_array($jsL10n) || $jsL10n === []) {
	return;
}
$pcL10nJson = json_encode($jsL10n, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

?>
<textarea id="pc-js-l10n-raw" class="pc-js-l10n-raw" hidden readonly tabindex="-1" aria-hidden="true"><?php print_unescaped($pcL10nJson); ?></textarea>
