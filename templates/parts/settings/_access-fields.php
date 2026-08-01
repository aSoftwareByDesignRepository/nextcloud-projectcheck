<?php
/** Access and visibility fieldsets (shared by admin mega-form and in-app Access page). */
?>
<section class="projectcheck-panel" aria-labelledby="pc-access-heading">
			<?php if (!empty($hidePanelTitles)): ?>
			<h2 class="visually-hidden" id="pc-access-heading"><?php p($l->t('Access and visibility')); ?></h2>
			<?php else: ?>
			<h2 class="projectcheck-panel__title" id="pc-access-heading"><?php p($l->t('Access and visibility')); ?></h2>
			<?php endif; ?>
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
								role="combobox"
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
						<?php } else { ?>
						<p class="projectcheck-callout projectcheck-callout--caution" role="status"><?php p($l->t('Directory search is unavailable. Existing selections are kept; contact a Nextcloud administrator if you need to change who may use the app.')); ?></p>
						<?php } ?>
						<textarea
							id="pc_allowed_users"
							name="access_allowed_user_ids"
							class="visually-hidden"
							rows="1"
							cols="60"
							aria-hidden="true"
							tabindex="-1"
						><?php p($allowedUserLines); ?></textarea>
					</div>
				</div>
				<p class="projectcheck-hint" id="pc_allowed_users_hint"><?php
				if ($orgSearchUsersUrl !== '') {
					p($l->t('Search by name or login, then pick. Never type a raw user id.'));
				} else {
					p($l->t('Never type a raw user id. Directory search must be available to change this list.'));
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
						<ul class="projectcheck-entity-picker__chips" data-pc-chips="pc_allowed_groups" id="pc_allowed_groups_chips" role="list" aria-label="<?php p($l->t('Selected groups')); ?>"></ul>
						<?php if ($orgSearchGroupsUrl !== '') { ?>
						<div class="projectcheck-entity-picker__search">
							<label for="pc_allowed_groups_q" class="visually-hidden"><?php p($l->t('Search groups to add')); ?></label>
							<input
								type="search"
								id="pc_allowed_groups_q"
								class="projectcheck-entity-picker__q"
								role="combobox"
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
						<?php } else { ?>
						<p class="projectcheck-callout projectcheck-callout--caution" role="status"><?php p($l->t('Directory search is unavailable. Existing selections are kept; contact a Nextcloud administrator if you need to change allowed groups.')); ?></p>
						<?php } ?>
						<textarea
							id="pc_allowed_groups"
							name="access_allowed_group_ids"
							class="visually-hidden"
							rows="1"
							cols="60"
							aria-hidden="true"
							tabindex="-1"
						><?php p($allowedGroupLines); ?></textarea>
					</div>
				</div>
				<p class="projectcheck-hint" id="pc_allowed_groups_hint"><?php
				if ($orgSearchGroupsUrl !== '') {
					p($l->t('Search and pick groups. Never type a raw group id.'));
				} else {
					p($l->t('Never type a raw group id. Directory search must be available to change this list.'));
				}
				?></p>
			</div>
		</section>
