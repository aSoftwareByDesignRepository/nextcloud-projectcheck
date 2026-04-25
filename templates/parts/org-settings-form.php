<?php
/**
 * Shared org / server admin form for ProjectCheck access and defaults
 *
 * @var \OCP\IL10N $l
 * @var array $formUiStrings from SavePolicyUiStrings::forForm (PHP l10n for the save script; never from JS t()).
 * @var string $saveUrl
 * @var string $allowedUserLines
 * @var string $allowedGroupLines
 * @var string $appAdminLines
 * @var string $default_hourly_rate
 * @var string $budget_warning_threshold
 * @var string $max_projects_per_user
 * @var string $enable_time_tracking
 * @var string $enable_customer_management
 * @var string $enable_budget_tracking
 * @var bool $restrictOn
 * @var string $formId HTML id for the form element
 * @var string $orgSearchUsersUrl optional, for user/group search (delegated org admins)
 * @var string $orgSearchGroupsUrl
 */
$orgSearchUsersUrl = $orgSearchUsersUrl ?? ($_['orgSearchUsersUrl'] ?? '');
$orgSearchGroupsUrl = $orgSearchGroupsUrl ?? ($_['orgSearchGroupsUrl'] ?? '');
$showSectionNav = (bool) ($showSectionNav ?? false);
if (!isset($restrictOn)) {
	$restrictOn = !empty($policy['restrictionEnabled']);
}
$formId = $formId ?? 'projectcheck-admin-form';
$statusId = $formId . '-status';
$saveId = $formId . '-save';
if (!isset($formUiStrings) && isset($l)) {
	$formUiStrings = \OCA\ProjectCheck\Service\SavePolicyUiStrings::forForm($l);
}
if (!is_array($formUiStrings)) {
	$formUiStrings = [];
}
try {
	$formUiJson = (string) json_encode($formUiStrings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (\JsonException) {
	$formUiJson = '{}';
}
?>
	<form
		class="projectcheck-admin-form"
		id="<?php p($formId); ?>"
		data-pc-form-strings="<?php p($formUiJson); ?>"
		<?php if ($orgSearchUsersUrl !== '') { ?>
		data-pc-search-users-url="<?php p($orgSearchUsersUrl); ?>"
		<?php } ?>
		<?php if ($orgSearchGroupsUrl !== '') { ?>
		data-pc-search-groups-url="<?php p($orgSearchGroupsUrl); ?>"
		<?php } ?>
		method="post"
		action="<?php p($saveUrl); ?>"
		novalidate
		aria-label="<?php p($l->t('ProjectCheck access and defaults')); ?>"
	>
		<?php if ($showSectionNav) { ?>
		<nav class="projectcheck-section-nav" aria-labelledby="pc-onpage-label">
			<p class="projectcheck-section-nav__kicker" id="pc-onpage-label"><?php p($l->t('On this page')) ?></p>
			<ol class="projectcheck-section-nav__list" role="list">
				<li>
					<a class="projectcheck-section-nav__link" href="#pc-access-heading"><?php p($l->t('Access and visibility')); ?></a>
				</li>
				<li>
					<a class="projectcheck-section-nav__link" href="#pc-admins-heading"><?php p($l->t('App administrators')); ?></a>
				</li>
				<li>
					<a class="projectcheck-section-nav__link" href="#pc-defaults-heading"><?php p($l->t('App defaults')); ?></a>
				</li>
			</ol>
		</nav>
		<?php } ?>
		<section class="projectcheck-panel" aria-labelledby="pc-access-heading">
			<h2 class="projectcheck-panel__title" id="pc-access-heading"><?php p($l->t('Access and visibility')); ?></h2>
			<?php if ($restrictOn) { ?>
			<div class="projectcheck-callout projectcheck-callout--caution" id="pc_restriction_active_notice">
				<p class="projectcheck-callout__p"><?php p($l->t('Access restriction is currently turned on. Users who are not listed in the allowlists (and are not system administrators) cannot use ProjectCheck, including this page if your account is affected.')); ?></p>
			</div>
			<?php } ?>
			<fieldset class="projectcheck-fieldset">
				<legend class="visually-hidden"><?php p($l->t('Access restriction')); ?></legend>
				<div class="projectcheck-form-row projectcheck-form-row--checkbox">
					<input type="checkbox" name="access_restriction_enabled" id="pc_access_restriction" value="1" <?php if ($restrictOn) {
						p('checked');
					} ?> aria-describedby="pc_access_restriction_desc">
					<label for="pc_access_restriction"><?php p($l->t('Limit who can use ProjectCheck')); ?></label>
				</div>
				<p class="projectcheck-hint" id="pc_access_restriction_desc"><?php p($l->t('When enabled, only the users and group members listed below (plus system administrators) can use the app. The app is hidden from the top bar for everyone else.')); ?></p>
			</fieldset>

			<div
				class="projectcheck-form-group projectcheck-form-group--with-picker"
				data-pc-entity="users"
				role="group"
				aria-labelledby="pc_allowed_users_legend"
			>
				<div id="pc_allowed_users_legend" class="projectcheck-entity-legend"><span class="projectcheck-entity-legend__num" aria-hidden="true">1</span><?php p($l->t('People who may use the app (when restriction is on)')); ?></div>
				<div class="projectcheck-entity-field" data-pc-field="users">
					<div class="projectcheck-entity-picker" data-pc-target="pc_allowed_users">
						<ul class="projectcheck-entity-picker__chips" data-pc-chips="pc_allowed_users" id="pc_allowed_users_chips" role="list" aria-label="<?php p($l->t('Selected users (login names)')); ?>"></ul>
						<?php if ($orgSearchUsersUrl !== '') { ?>
						<div class="projectcheck-entity-picker__search" data-pc-open="false">
							<label for="pc_allowed_users_q" class="visually-hidden"><?php p($l->t('Search users to add')); ?></label>
							<input
								type="search"
								id="pc_allowed_users_q"
								class="projectcheck-entity-picker__q"
								autocomplete="off"
								spellcheck="false"
								aria-autocomplete="list"
								aria-controls="pc_allowed_users_suggest"
								aria-haspopup="listbox"
								aria-expanded="false"
								placeholder="<?php p($l->t('Type at least 2 characters…')); ?>"
							>
							<div class="projectcheck-entity-picker__suggest" id="pc_allowed_users_suggest" hidden data-pc-suggest="users" aria-live="polite"></div>
						</div>
						<?php } ?>
						<details class="projectcheck-entity-picker__manual"<?php if ($orgSearchUsersUrl === '') {
							p(' open');
						} ?>>
							<summary class="projectcheck-entity-picker__summary"><?php p($l->t('Manual entry: one user ID per line')); ?></summary>
							<label for="pc_allowed_users" class="visually-hidden"><?php p($l->t('Allowed user IDs, one per line')); ?></label>
							<textarea
								id="pc_allowed_users"
								name="access_allowed_user_ids"
								class="projectcheck-textarea"
								rows="4"
								cols="60"
								aria-describedby="pc_allowed_users_hint"
								autocapitalize="off"
								spellcheck="false"
								autocomplete="off"
							><?php p($allowedUserLines); ?></textarea>
						</details>
					</div>
				</div>
				<p class="projectcheck-hint" id="pc_allowed_users_hint"><?php
				if ($orgSearchUsersUrl !== '') {
					p($l->t('These are account login names (not display names). Search, pick people, or open “Manual entry” to type or paste one ID per line.'));
				} else {
					p($l->t('Type one account login name per line (not the display name).'));
				}
				?></p>
			</div>
			<div
				class="projectcheck-form-group projectcheck-form-group--with-picker"
				data-pc-entity="groups"
				role="group"
				aria-labelledby="pc_allowed_groups_legend"
			>
				<div id="pc_allowed_groups_legend" class="projectcheck-entity-legend"><span class="projectcheck-entity-legend__num" aria-hidden="true">2</span><?php p($l->t('Groups that may use the app (when restriction is on)')); ?></div>
				<div class="projectcheck-entity-field" data-pc-field="groups">
					<div class="projectcheck-entity-picker" data-pc-target="pc_allowed_groups">
						<ul class="projectcheck-entity-picker__chips" data-pc-chips="pc_allowed_groups" id="pc_allowed_groups_chips" role="list" aria-label="<?php p($l->t('Selected group IDs')); ?>"></ul>
						<?php if ($orgSearchGroupsUrl !== '') { ?>
						<div class="projectcheck-entity-picker__search">
							<label for="pc_allowed_groups_q" class="visually-hidden"><?php p($l->t('Search groups to add')); ?></label>
							<input
								type="search"
								id="pc_allowed_groups_q"
								class="projectcheck-entity-picker__q"
								autocomplete="off"
								spellcheck="false"
								aria-autocomplete="list"
								aria-controls="pc_allowed_groups_suggest"
								aria-haspopup="listbox"
								aria-expanded="false"
								placeholder="<?php p($l->t('Type at least 2 characters…')); ?>"
							>
							<div class="projectcheck-entity-picker__suggest" id="pc_allowed_groups_suggest" hidden data-pc-suggest="groups" aria-live="polite"></div>
						</div>
						<?php } ?>
						<details class="projectcheck-entity-picker__manual"<?php if ($orgSearchGroupsUrl === '') {
							p(' open');
						} ?>>
							<summary class="projectcheck-entity-picker__summary"><?php p($l->t('Manual entry: one group ID per line')); ?></summary>
							<label for="pc_allowed_groups" class="visually-hidden"><?php p($l->t('Allowed group IDs, one per line')); ?></label>
							<textarea
								id="pc_allowed_groups"
								name="access_allowed_group_ids"
								class="projectcheck-textarea"
								rows="3"
								cols="60"
								aria-describedby="pc_allowed_groups_hint"
								autocapitalize="off"
								spellcheck="false"
								autocomplete="off"
							><?php p($allowedGroupLines); ?></textarea>
						</details>
					</div>
				</div>
				<p class="projectcheck-hint" id="pc_allowed_groups_hint"><?php
				if ($orgSearchGroupsUrl !== '') {
					p($l->t('Group identifiers are shown in Nextcloud user management. Search, pick, or use manual entry to type or paste one ID per line.'));
				} else {
					p($l->t('Type one group ID per line (as shown in the group management in Nextcloud).'));
				}
				?></p>
			</div>
		</section>

		<section class="projectcheck-panel" aria-labelledby="pc-admins-heading">
			<h2 class="projectcheck-panel__title" id="pc-admins-heading"><?php p($l->t('App administrators')); ?></h2>
			<div
				class="projectcheck-form-group projectcheck-form-group--with-picker"
				data-pc-entity="appadmins"
				role="group"
				aria-labelledby="pc_app_admins_legend"
			>
				<div id="pc_app_admins_legend" class="projectcheck-entity-legend"><span class="projectcheck-entity-legend__num" aria-hidden="true">3</span><?php p($l->t('Extra people who can manage this app’s settings')); ?></div>
				<div class="projectcheck-entity-field" data-pc-field="appadmins">
					<div class="projectcheck-entity-picker" data-pc-target="pc_app_admins">
						<ul class="projectcheck-entity-picker__chips" data-pc-chips="pc_app_admins" id="pc_app_admins_chips" role="list" aria-label="<?php p($l->t('Selected app administrators (login names)')); ?>"></ul>
						<?php if ($orgSearchUsersUrl !== '') { ?>
						<div class="projectcheck-entity-picker__search">
							<label for="pc_app_admins_q" class="visually-hidden"><?php p($l->t('Search users to add as app administrator')); ?></label>
							<input
								type="search"
								id="pc_app_admins_q"
								class="projectcheck-entity-picker__q"
								autocomplete="off"
								spellcheck="false"
								aria-autocomplete="list"
								aria-controls="pc_app_admins_suggest"
								aria-haspopup="listbox"
								aria-expanded="false"
								placeholder="<?php p($l->t('Type at least 2 characters…')); ?>"
							>
							<div class="projectcheck-entity-picker__suggest" id="pc_app_admins_suggest" hidden data-pc-suggest="appadmins" aria-live="polite"></div>
						</div>
						<?php } ?>
						<details class="projectcheck-entity-picker__manual"<?php if ($orgSearchUsersUrl === '') {
							p(' open');
						} ?>>
							<summary class="projectcheck-entity-picker__summary"><?php p($l->t('Manual entry: one user ID per line (app administrators)')); ?></summary>
							<label for="pc_app_admins" class="visually-hidden"><?php p($l->t('Delegated app admin user IDs, one per line')); ?></label>
							<textarea
								id="pc_app_admins"
								name="app_admin_user_ids"
								class="projectcheck-textarea"
								rows="3"
								cols="60"
								aria-describedby="pc_app_admins_hint"
								autocapitalize="off"
								spellcheck="false"
								autocomplete="off"
							><?php p($appAdminLines); ?></textarea>
						</details>
					</div>
				</div>
				<p class="projectcheck-hint" id="pc_app_admins_hint"><?php
				if ($orgSearchUsersUrl !== '') {
					p($l->t('These people can open ProjectCheck settings and this page. System administrators are always included. Search, add, or use manual entry; one login name per line.'));
				} else {
					p($l->t('Type one account login per line. System administrators always have this access; they are not required here.'));
				}
				?></p>
			</div>
		</section>

		<section class="projectcheck-panel projectcheck-panel--defaults" aria-labelledby="pc-defaults-heading">
			<h2 class="projectcheck-panel__title" id="pc-defaults-heading"><?php p($l->t('App defaults')); ?></h2>
			<div class="projectcheck-defaults-grid" role="group" aria-label="<?php p($l->t('App defaults')); ?>">
			<div class="projectcheck-form-group">
				<label for="pc_def_rate"><?php p($l->t('Default hourly rate (€)')); ?></label>
				<input type="text" id="pc_def_rate" name="default_hourly_rate" inputmode="decimal" class="projectcheck-input" value="<?php p($default_hourly_rate); ?>" autocomplete="off" aria-describedby="pc_def_rate_hint">
				<p class="projectcheck-hint" id="pc_def_rate_hint"><?php p($l->t('Default for new projects when the app has no per-user override.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_budget_th"><?php p($l->t('Budget Warning Threshold (%)')); ?></label>
				<input type="number" id="pc_budget_th" name="budget_warning_threshold" class="projectcheck-input" value="<?php p($budget_warning_threshold); ?>" min="0" max="100" inputmode="numeric" aria-describedby="pc_budget_th_hint">
				<p class="projectcheck-hint" id="pc_budget_th_hint"><?php p($l->t('Show warnings when this percentage of the budget is used.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_max_proj"><?php p($l->t('Maximum projects per user')); ?></label>
				<input type="number" id="pc_max_proj" name="max_projects_per_user" class="projectcheck-input" value="<?php p($max_projects_per_user); ?>" min="1" inputmode="numeric" aria-describedby="pc_max_proj_hint">
				<p class="projectcheck-hint" id="pc_max_proj_hint"><?php p($l->t('Upper limit for new projects a single user can create, where enforced by the app.')); ?></p>
			</div>
			<div class="projectcheck-form-group projectcheck-form-group--span">
				<label for="pc_timetrack"><?php p($l->t('Enable time tracking')); ?></label>
				<select id="pc_timetrack" name="enable_time_tracking" class="projectcheck-select" aria-describedby="pc_timetrack_hint">
					<option value="yes" <?php if ($enable_time_tracking === 'yes') { p('selected'); } ?>><?php p($l->t('Yes')); ?></option>
					<option value="no" <?php if ($enable_time_tracking === 'no') { p('selected'); } ?>><?php p($l->t('No')); ?></option>
				</select>
				<p class="projectcheck-hint" id="pc_timetrack_hint"><?php p($l->t('Applies to time tracking features across the app.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_custman"><?php p($l->t('Enable customer management')); ?></label>
				<select id="pc_custman" name="enable_customer_management" class="projectcheck-select" aria-describedby="pc_custman_hint">
					<option value="yes" <?php if ($enable_customer_management === 'yes') { p('selected'); } ?>><?php p($l->t('Yes')); ?></option>
					<option value="no" <?php if ($enable_customer_management === 'no') { p('selected'); } ?>><?php p($l->t('No')); ?></option>
				</select>
				<p class="projectcheck-hint" id="pc_custman_hint"><?php p($l->t('Shows or hides customer features where the app enforces it.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_budg"><?php p($l->t('Enable budget tracking')); ?></label>
				<select id="pc_budg" name="enable_budget_tracking" class="projectcheck-select" aria-describedby="pc_budg_hint">
					<option value="yes" <?php if ($enable_budget_tracking === 'yes') { p('selected'); } ?>><?php p($l->t('Yes')); ?></option>
					<option value="no" <?php if ($enable_budget_tracking === 'no') { p('selected'); } ?>><?php p($l->t('No')); ?></option>
				</select>
				<p class="projectcheck-hint" id="pc_budg_hint"><?php p($l->t('Enables budget consumption and related warnings.')); ?></p>
			</div>
			</div>
		</section>

		<div class="projectcheck-form-actions">
			<button type="submit" class="button primary projectcheck-save-button" id="<?php p($saveId); ?>"><?php p($l->t('Save settings')); ?></button>
		</div>
		<p class="projectcheck-form-status" id="<?php p($statusId); ?>" role="status" aria-live="polite" tabindex="-1" hidden></p>
	</form>
