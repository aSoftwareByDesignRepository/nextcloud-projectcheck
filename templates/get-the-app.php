<?php

/**
 * Get the App — official ProjectCheck Mobile companion.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCA\ProjectCheck\Service\IconCatalog;
use OCA\ProjectCheck\Support\MobileAppLinks;
use OCP\Util;

Util::addStyle('projectcheck', 'get-the-app');
Util::addStyle('projectcheck', 'navigation');

$urls = is_array($_['urls'] ?? null) ? $_['urls'] : [];
$playStore = (string)($urls['playStore'] ?? MobileAppLinks::PLAY_STORE_URL);
if (!str_starts_with($playStore, 'https://play.google.com/')) {
	$playStore = MobileAppLinks::PLAY_STORE_URL;
}
$productPage = (string)($urls['mobileProductPage'] ?? '');
$privacyPage = (string)($urls['mobilePrivacyPage'] ?? '');
if ($productPage !== '' && !str_starts_with($productPage, 'https://')) {
	$productPage = '';
}
if ($privacyPage !== '' && !str_starts_with($privacyPage, 'https://')) {
	$privacyPage = '';
}

$features = [
	[
		'icon' => 'clock',
		'title' => $l->t('Log time from your phone'),
		'hint' => $l->t('Add time entries on site. Projects, customers, and budgets stay on this Nextcloud.'),
	],
	[
		'icon' => 'folder',
		'title' => $l->t('See your projects'),
		'hint' => $l->t('Open the same projects you use in the browser — status and remaining budget travel with you.'),
	],
	[
		'icon' => 'wallet',
		'title' => $l->t('Watch budgets'),
		'hint' => $l->t('Spot overruns before you get back to the desk.'),
	],
	[
		'icon' => 'lock',
		'title' => $l->t('Sign in safely'),
		'hint' => $l->t('Uses Nextcloud Login Flow — your main password is never stored in the app.'),
	],
];

include __DIR__ . '/common/navigation.php';

$pageId = 'get-the-app';
$pageTitle = $l->t('Get the App');
$pageHelp = $l->t('Official Android app — features and Google Play download.');
include __DIR__ . '/common/page-start.php';
?>
<div class="pc-get-app-page">
	<section class="pc-get-app__hero" aria-labelledby="pc-get-app-intro-title">
		<p class="pc-get-app__eyebrow"><?php p($l->t('Official Android companion')); ?></p>
		<h2 id="pc-get-app-intro-title" class="pc-get-app__title"><?php p($l->t('ProjectCheck Mobile')); ?></h2>
		<p class="pc-get-app__lead">
			<?php p($l->t('The official Android app connects to this Nextcloud. Log time and check project progress from your phone — work stays on your server.')); ?>
		</p>
		<div class="pc-get-app__cta">
			<a class="pc-btn pc-btn--primary pc-get-app__play" href="<?php p($playStore); ?>" target="_blank" rel="noopener noreferrer">
				<span class="pc-get-app__play-icon" aria-hidden="true"><?php print_unescaped(IconCatalog::render('smartphone')); ?></span>
				<span class="pc-get-app__play-label"><?php p($l->t('Get it on Google Play')); ?></span>
			</a>
			<p class="pc-get-app__price-hint">
				<?php p($l->t('On Google Play. Your organisation licenses official mobile seats — the web app stays free.')); ?>
			</p>
		</div>
	</section>

	<section class="pc-get-app__features-block" aria-labelledby="pc-get-app-features-title">
		<h2 id="pc-get-app-features-title" class="pc-get-app__section-title"><?php p($l->t('What you can do')); ?></h2>
		<ul class="pc-get-app__features">
			<?php foreach ($features as $feature): ?>
				<li class="pc-get-app__feature">
					<span class="pc-get-app__icon-well pc-get-app__icon-well--feature" aria-hidden="true">
						<?php print_unescaped(IconCatalog::render((string)$feature['icon'])); ?>
					</span>
					<div class="pc-get-app__feature-copy">
						<span class="pc-get-app__feature-title"><?php p((string)$feature['title']); ?></span>
						<span class="pc-get-app__feature-hint"><?php p((string)$feature['hint']); ?></span>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php
	$actionRows = [];
	if ($productPage !== '') {
		$actionRows[] = ['href' => $productPage, 'label' => $l->t('Product page')];
	}
	if ($privacyPage !== '') {
		$actionRows[] = ['href' => $privacyPage, 'label' => $l->t('Privacy policy for the mobile app')];
	}
	?>
	<?php if ($actionRows !== []): ?>
		<nav class="pc-get-app__actions" aria-label="<?php p($l->t('More information')); ?>">
			<?php foreach ($actionRows as $row): ?>
				<a class="pc-get-app__action" href="<?php p((string)$row['href']); ?>" target="_blank" rel="noopener noreferrer">
					<span class="pc-get-app__action-label"><?php p((string)$row['label']); ?></span>
					<span class="pc-get-app__action-external" aria-hidden="true">↗</span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</div>
<?php include __DIR__ . '/common/page-end.php'; ?>
