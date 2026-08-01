<?php
/** App administrators fieldset (shared). */
?>
<section class="projectcheck-panel" aria-labelledby="pc-admins-heading">
			<?php if (!empty($hidePanelTitles)): ?>
			<h2 class="visually-hidden" id="pc-admins-heading"><?php p($l->t('App administrators')); ?></h2>
			<?php else: ?>
			<h2 class="projectcheck-panel__title" id="pc-admins-heading"><?php p($l->t('App administrators')); ?></h2>
			<?php endif; ?>
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
								role="combobox"
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
						<?php } else { ?>
						<p class="projectcheck-callout projectcheck-callout--caution" role="status"><?php p($l->t('Directory search is unavailable. Existing app administrators are kept; contact a Nextcloud administrator if you need to change this list.')); ?></p>
						<?php } ?>
						<textarea
							id="pc_app_admins"
							name="app_admin_user_ids"
							class="visually-hidden"
							rows="1"
							cols="60"
							aria-hidden="true"
							tabindex="-1"
						><?php p($appAdminLines); ?></textarea>
					</div>
				</div>
				<p class="projectcheck-hint" id="pc_app_admins_hint"><?php
				if ($orgSearchUsersUrl !== '') {
					p($l->t('These people can open ProjectCheck settings. System administrators always keep access. Search and pick — never type a raw user id.'));
				} else {
					p($l->t('Never type a raw user id. System administrators always keep access.'));
				}
				?></p>
			</div>
		</section>
