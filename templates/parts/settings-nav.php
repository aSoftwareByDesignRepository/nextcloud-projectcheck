<?php
/**
 * In-page settings sub-navigation (DeskCheck parity).
 *
 * Complements the sidebar sub-list: Nextcloud collapses #app-navigation below
 * ~1024px, so without this chip bar admins cannot reach sibling settings pages
 * on phones/tablets. Labels and URLs come from the controller
 * (SettingsSectionCatalog) — never hardcoded here.
 *
 * @var array $_
 * @var \OCP\IL10N $l
 * @var string $pcRequestedSection
 */

$pcNavLabels = (array) ($_['settingsSectionLabels'] ?? []);
$pcNavUrls = (array) (($_['urls']['settingsSections'] ?? []) ?: ($_['settingsSections'] ?? []));
if ($pcNavLabels === []) {
	return;
}
?>
<nav class="pc-settings-nav projectcheck-settings-nav" id="pc-settings-pages" aria-label="<?php p($l->t('Settings pages')); ?>">
	<?php foreach ($pcNavLabels as $sectionId => $sectionLabel):
		$sectionId = (string) $sectionId;
		$href = (string) ($pcNavUrls[$sectionId] ?? '');
		if ($href === '' || $href === '#') {
			continue;
		}
		$active = $pcRequestedSection === $sectionId;
		?>
		<a class="pc-settings-nav__link<?php p($active ? ' is-active' : ''); ?>"
			href="<?php p($href); ?>"
			<?php if ($active): ?>aria-current="page"<?php endif; ?>>
			<?php p((string) $sectionLabel); ?>
		</a>
	<?php endforeach; ?>
</nav>
