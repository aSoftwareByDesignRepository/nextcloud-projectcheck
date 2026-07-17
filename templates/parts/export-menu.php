<?php

declare(strict_types=1);

/**
 * Accessible CSV/JSON export disclosure for list pages.
 *
 * Required template vars (set before include):
 *   $exportUrl          Absolute or app-relative export endpoint (required)
 *   $exportEntityLabel  Short English basename used in fallback filenames (projects, …)
 *   $exportFilterKeys   Comma-separated request keys mirrored from filter controls
 *   $exportSuccessMsg   l10n msgid with {count} placeholder
 *   $exportIncludeSort  (optional) truthy → forward sort/direction from the page URL
 *   $exportMenuId       (optional) unique id for aria-controls
 *
 * Renders nothing when $exportUrl is empty so a missing route never produces a
 * broken control that posts to the current page.
 *
 * @var \OCP\IL10N $l
 * @var string $exportUrl
 * @var string $exportEntityLabel
 * @var string $exportFilterKeys
 * @var string $exportSuccessMsg
 * @var bool|int|string|null $exportIncludeSort
 * @var string|null $exportMenuId
 */

$exportUrl = trim((string)($exportUrl ?? ''));
if ($exportUrl === '') {
	return;
}

$exportEntityLabel = (string)($exportEntityLabel ?? 'items');
$exportFilterKeys = (string)($exportFilterKeys ?? '');
$exportSuccessMsg = (string)($exportSuccessMsg ?? 'Exported {count} items');
$menuId = !empty($exportMenuId)
	? (string)$exportMenuId
	: ('pc-export-menu-' . preg_replace('/[^a-z0-9_-]+/i', '-', $exportEntityLabel));
$includeSort = !empty($exportIncludeSort);
?>
<div class="pc-export"
	data-pc-export
	data-export-url="<?php p($exportUrl); ?>"
	data-entity-label="<?php p($exportEntityLabel); ?>"
	data-filter-keys="<?php p($exportFilterKeys); ?>"
	data-success-message="<?php p($exportSuccessMsg); ?>"
	<?php if ($includeSort): ?>data-include-sort="1"<?php endif; ?>>
	<button type="button"
		class="button secondary pc-export__toggle"
		aria-expanded="false"
		aria-haspopup="menu"
		aria-controls="<?php p($menuId); ?>"
		aria-busy="false">
		<span data-lucide="download" class="lucide-icon" aria-hidden="true"></span>
		<span class="pc-export__label"><?php p($l->t('Export')); ?></span>
		<span data-lucide="chevron-down" class="lucide-icon pc-export__chevron" aria-hidden="true"></span>
	</button>
	<div class="pc-export__menu" role="menu" id="<?php p($menuId); ?>" hidden
		aria-label="<?php p($l->t('Export format')); ?>">
		<button type="button" class="pc-export__item" role="menuitem" data-format="csv">
			<span class="pc-export__item-title"><?php p($l->t('CSV (for Excel)')); ?></span>
			<span class="pc-export__item-hint"><?php p($l->t('Opens in Excel and other spreadsheet programs')); ?></span>
		</button>
		<button type="button" class="pc-export__item" role="menuitem" data-format="json">
			<span class="pc-export__item-title"><?php p($l->t('JSON (for programs)')); ?></span>
			<span class="pc-export__item-hint"><?php p($l->t('Machine-readable data for other systems')); ?></span>
		</button>
	</div>
</div>
