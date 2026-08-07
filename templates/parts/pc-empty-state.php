<?php

declare(strict_types=1);

/**
 * Centered empty state with optional primary call-to-action.
 *
 * The CTA renders as either an anchor (`a`, default), a file-picker label
 * (`label`, paired with a hidden file input via `ctaFor`) or a real
 * `button` element bound by the host page (e.g. to open a modal). When
 * `ctaTag === 'button'`, extra `ctaData` entries are emitted as
 * `data-*` attributes so the host can wire a single click handler
 * via event delegation – no inline JavaScript ever leaks into the
 * markup.
 *
 * Every dynamic attribute is escaped via {@see p()} or
 * {@see htmlspecialchars()}; nothing here interpolates raw HTML.
 *
 * @var string $iconLucide lucide icon name for the hero graphic
 * @var string $title translated heading
 * @var string $description translated lead paragraph
 * @var string|null $ctaHref link target for &lt;a&gt; CTA
 * @var string|null $ctaLabel translated CTA label
 * @var string|null $ctaFor id of associated control when $ctaTag is label
 * @var string $ctaTag a|label|button (default a)
 * @var string $ctaIconLucide icon on the CTA (default plus)
 * @var array<string,string|int|bool>|null $ctaData data-* attributes for button CTAs
 * @var string|null $ctaId optional id for the CTA element (button only)
 * @var string|null $ctaAriaControls value for aria-controls (button only)
 * @var string|null $ctaAriaHasPopup value for aria-haspopup (button only)
 * @var string|null $hint translated hint when no CTA
 * @var string $role ARIA role (default status)
 * @var string $ariaLive optional aria-live value (e.g. polite)
 */

$iconLucide = (string) ($iconLucide ?? 'folder');
$title = (string) ($title ?? '');
$description = (string) ($description ?? '');
$ctaHref = isset($ctaHref) ? (string) $ctaHref : '';
$ctaLabel = isset($ctaLabel) ? (string) $ctaLabel : '';
$ctaFor = isset($ctaFor) ? (string) $ctaFor : '';
$ctaTag = (string) ($ctaTag ?? 'a');
if (!in_array($ctaTag, ['a', 'label', 'button'], true)) {
	$ctaTag = 'a';
}
$ctaIconLucide = (string) ($ctaIconLucide ?? 'plus');
$ctaData = (isset($ctaData) && is_array($ctaData)) ? $ctaData : [];
$ctaId = isset($ctaId) ? (string) $ctaId : '';
$ctaAriaControls = isset($ctaAriaControls) ? (string) $ctaAriaControls : '';
$ctaAriaHasPopup = isset($ctaAriaHasPopup) ? (string) $ctaAriaHasPopup : '';
$hint = isset($hint) ? (string) $hint : '';
$role = (string) ($role ?? 'status');
$ariaLive = (string) ($ariaLive ?? '');

$hasCta = $ctaLabel !== ''
	&& (
		($ctaTag === 'a' && $ctaHref !== '')
		|| ($ctaTag === 'label' && $ctaFor !== '')
		|| ($ctaTag === 'button')
	);
?>
<div class="pc-empty-state" role="<?php p($role); ?>"<?php if ($ariaLive !== '') {
	echo ' aria-live="' . htmlspecialchars($ariaLive, ENT_QUOTES, 'UTF-8') . '"';
} ?>>
	<div class="pc-empty-state__icon" aria-hidden="true">
		<span data-lucide="<?php p($iconLucide); ?>" class="lucide-icon"></span>
	</div>
	<?php if ($title !== '') { ?>
		<h3 class="pc-empty-state__title"><?php p($title); ?></h3>
	<?php } ?>
	<?php if ($description !== '') { ?>
		<p class="pc-empty-state__lead"><?php p($description); ?></p>
	<?php } ?>
	<?php if ($hasCta) { ?>
		<div class="pc-empty-state__actions">
			<?php if ($ctaTag === 'label') { ?>
				<label for="<?php p($ctaFor); ?>" class="button primary pc-empty-state__cta">
					<span data-lucide="<?php p($ctaIconLucide); ?>" class="lucide-icon" aria-hidden="true"></span>
					<span class="pc-empty-state__cta-label"><?php p($ctaLabel); ?></span>
				</label>
			<?php } elseif ($ctaTag === 'button') { ?>
				<button type="button" class="button primary pc-empty-state__cta"
					<?php if ($ctaId !== '') {
						echo 'id="' . htmlspecialchars($ctaId, ENT_QUOTES, 'UTF-8') . '" ';
					} ?>
					<?php if ($ctaAriaControls !== '') {
						echo 'aria-controls="' . htmlspecialchars($ctaAriaControls, ENT_QUOTES, 'UTF-8') . '" ';
					} ?>
					<?php if ($ctaAriaHasPopup !== '') {
						echo 'aria-haspopup="' . htmlspecialchars($ctaAriaHasPopup, ENT_QUOTES, 'UTF-8') . '" ';
					} ?>
					<?php
					foreach ($ctaData as $key => $value) {
						if (!is_string($key) || $key === '') {
							continue;
						}
						// Allow safe subset only: [a-z0-9-] so we never emit
						// attribute-injection vectors when callers pass keys.
						$safeKey = preg_replace('/[^a-z0-9-]/i', '', $key) ?? '';
						if ($safeKey === '') {
							continue;
						}
						echo 'data-' . htmlspecialchars($safeKey, ENT_QUOTES, 'UTF-8')
							. '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '" ';
					}
					?>>
					<span data-lucide="<?php p($ctaIconLucide); ?>" class="lucide-icon" aria-hidden="true"></span>
					<span class="pc-empty-state__cta-label"><?php p($ctaLabel); ?></span>
				</button>
			<?php } else { ?>
				<a href="<?php p($ctaHref); ?>" class="button primary pc-empty-state__cta">
					<span data-lucide="<?php p($ctaIconLucide); ?>" class="lucide-icon" aria-hidden="true"></span>
					<span class="pc-empty-state__cta-label"><?php p($ctaLabel); ?></span>
				</a>
			<?php } ?>
		</div>
	<?php } elseif ($hint !== '') { ?>
		<p class="pc-empty-state__hint"><?php p($hint); ?></p>
	<?php } ?>
</div>
