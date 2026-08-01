<?php
/** App defaults fieldset (shared). */
?>
<section class="projectcheck-panel projectcheck-panel--defaults" aria-labelledby="pc-defaults-heading">
			<?php if (!empty($hidePanelTitles)): ?>
			<h2 class="visually-hidden" id="pc-defaults-heading"><?php p($l->t('App defaults')); ?></h2>
			<?php else: ?>
			<h2 class="projectcheck-panel__title" id="pc-defaults-heading"><?php p($l->t('App defaults')); ?></h2>
			<?php endif; ?>
			<div class="projectcheck-defaults-grid" role="group" aria-label="<?php p($l->t('App defaults')); ?>">
			<div class="projectcheck-form-group">
				<?php
				$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
				if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
					$currencyCode = 'EUR';
				}
				?>
				<label for="pc_currency"><?php p($l->t('Default currency')); ?></label>
				<?php
				$currencyOptions = [
					'EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD', 'NZD', 'JPY', 'CNY', 'HKD', 'SGD',
					'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'TRY', 'ILS', 'AED',
					'SAR', 'ZAR', 'INR', 'KRW', 'THB', 'MYR', 'IDR', 'PHP', 'VND', 'MXN', 'BRL',
					'ARS', 'CLP', 'COP', 'PEN',
				];
				if (!in_array($currencyCode, $currencyOptions, true)) {
					array_unshift($currencyOptions, $currencyCode);
				}
				?>
				<select id="pc_currency" name="currency" class="projectcheck-select" aria-describedby="pc_currency_hint">
					<?php foreach ($currencyOptions as $currencyOption): ?>
						<option value="<?php p($currencyOption); ?>" <?php if ($currencyCode === $currencyOption) { p('selected'); } ?>>
							<?php p($currencyOption); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="projectcheck-hint" id="pc_currency_hint"><?php p($l->t('Used across budgets, rates, totals, and exports.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label
					for="pc_def_rate"
					id="pc_def_rate_label"
					data-currency-label-template="<?php p($l->t('Default hourly rate (%s)', ['%s'])); ?>"
				><?php p($l->t('Default hourly rate (%s)', [$currencyCode])); ?></label>
				<input type="text" id="pc_def_rate" name="default_hourly_rate" inputmode="decimal" class="projectcheck-input" value="<?php p($default_hourly_rate); ?>" autocomplete="off" aria-describedby="pc_def_rate_hint">
				<p class="projectcheck-hint" id="pc_def_rate_hint"><?php p($l->t('Default hourly rate for new projects in “One rate for the whole project” pricing mode. Employee-wide and per-person project rates use their own settings.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_def_status"><?php p($l->t('Default project status')); ?></label>
				<select id="pc_def_status" name="default_project_status" class="projectcheck-select" aria-describedby="pc_def_status_hint">
					<option value="Active" <?php if (($default_project_status ?? 'Active') === 'Active') { p('selected'); } ?>><?php p($l->t('Active')); ?></option>
					<option value="On Hold" <?php if (($default_project_status ?? '') === 'On Hold') { p('selected'); } ?>><?php p($l->t('On Hold')); ?></option>
					<option value="Completed" <?php if (($default_project_status ?? '') === 'Completed') { p('selected'); } ?>><?php p($l->t('Completed')); ?></option>
					<option value="Cancelled" <?php if (($default_project_status ?? '') === 'Cancelled') { p('selected'); } ?>><?php p($l->t('Cancelled')); ?></option>
				</select>
				<p class="projectcheck-hint" id="pc_def_status_hint"><?php p($l->t('Default status used when creating a project.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_def_priority"><?php p($l->t('Default project priority')); ?></label>
				<select id="pc_def_priority" name="default_project_priority" class="projectcheck-select" aria-describedby="pc_def_priority_hint">
					<option value="Low" <?php if (($default_project_priority ?? '') === 'Low') { p('selected'); } ?>><?php p($l->t('Low')); ?></option>
					<option value="Medium" <?php if (($default_project_priority ?? 'Medium') === 'Medium') { p('selected'); } ?>><?php p($l->t('Medium')); ?></option>
					<option value="High" <?php if (($default_project_priority ?? '') === 'High') { p('selected'); } ?>><?php p($l->t('High')); ?></option>
					<option value="Critical" <?php if (($default_project_priority ?? '') === 'Critical') { p('selected'); } ?>><?php p($l->t('Critical')); ?></option>
				</select>
				<p class="projectcheck-hint" id="pc_def_priority_hint"><?php p($l->t('Default priority used when creating a project.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_budget_th"><?php p($l->t('Budget Warning Threshold (%)')); ?></label>
				<input type="number" id="pc_budget_th" name="budget_warning_threshold" class="projectcheck-input" value="<?php p($budget_warning_threshold); ?>" min="0" max="100" inputmode="numeric" aria-describedby="pc_budget_th_hint">
				<p class="projectcheck-hint" id="pc_budget_th_hint"><?php p($l->t('Show warnings when this percentage of the budget is used.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_budget_crit"><?php p($l->t('Budget Critical Threshold (%)')); ?></label>
				<input type="number" id="pc_budget_crit" name="budget_critical_threshold" class="projectcheck-input" value="<?php p($budget_critical_threshold ?? '90'); ?>" min="0" max="100" inputmode="numeric" aria-describedby="pc_budget_crit_hint">
				<p class="projectcheck-hint" id="pc_budget_crit_hint"><?php p($l->t('Show critical alerts when this percentage of the budget is used.')); ?></p>
			</div>
			<div class="projectcheck-form-group">
				<label for="pc_items_page"><?php p($l->t('Items per page')); ?></label>
				<input type="number" id="pc_items_page" name="items_per_page" class="projectcheck-input" value="<?php p($items_per_page ?? '20'); ?>" min="5" max="100" inputmode="numeric" aria-describedby="pc_items_page_hint">
				<p class="projectcheck-hint" id="pc_items_page_hint"><?php p($l->t('Default list size for projects, customers, and employees when users have no personal override.')); ?></p>
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
