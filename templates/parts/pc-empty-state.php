<?php

declare(strict_types=1);

/**
 * Centered empty state with optional primary call-to-action.
 *
 * @var string $iconLucide lucide icon name for the hero graphic
 * @var string $title translated heading
 * @var string $description translated lead paragraph
 * @var string|null $ctaHref link target for &lt;a&gt; CTA
 * @var string|null $ctaLabel translated CTA label
 * @var string|null $ctaFor id of associated control when $ctaTag is label
 * @var string $ctaTag a|label (default a)
 * @var string $ctaIconLucide icon on the CTA (default plus)
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
$ctaIconLucide = (string) ($ctaIconLucide ?? 'plus');
$hint = isset($hint) ? (string) $hint : '';
$role = (string) ($role ?? 'status');
$ariaLive = (string) ($ariaLive ?? '');
$hasCta = $ctaLabel !== '' && ($ctaHref !== '' || ($ctaTag === 'label' && $ctaFor !== ''));
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
			<?php if ($ctaTag === 'label' && $ctaFor !== '') { ?>
				<label for="<?php p($ctaFor); ?>" class="button primary pc-empty-state__cta">
					<span data-lucide="<?php p($ctaIconLucide); ?>" class="lucide-icon" aria-hidden="true"></span>
					<span class="pc-empty-state__cta-label"><?php p($ctaLabel); ?></span>
				</label>
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
