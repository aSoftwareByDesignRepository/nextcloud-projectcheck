<?php
/**
 * Opens app content shell (skip link, live regions, main) without page h1.
 *
 * @var string $pageId
 */
$pageId = isset($pageId) ? (string)$pageId : (string)($_['pageId'] ?? 'page');
?>
<div id="app-content" class="pc-app pc-app--<?php p($pageId); ?>" role="main">
	<a class="pc-skip-link" href="#pc-main-content"><?php p($l->t('Skip to main content')); ?></a>
	<div id="pc-live-region" class="pc-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
	<div id="pc-alert-region" class="pc-sr-only" role="alert" aria-live="assertive" aria-atomic="true"></div>
	<div id="app-content-wrapper" class="pc-shell pc-app-shell-stack">
		<main id="pc-main-content" class="pc-main" tabindex="-1">
