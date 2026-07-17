<?php

/**
 * Customer detail template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

style('projectcheck', 'dashboard');
style('projectcheck', 'projects');
style('projectcheck', 'customers');
style('projectcheck', 'budget-alerts');
style('projectcheck', 'navigation');
style('projectcheck', 'common/progress-bars');
style('projectcheck', 'customer-statistics');
style('projectcheck', 'common/list-table');
style('projectcheck', 'common/detail-layout');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>
<?php
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Pass PHP variables to JavaScript
	window.projectControlData = {
		requestToken: '<?php p($_['requesttoken']) ?>',
		customerId: <?php p($customer->getId()); ?>
	};
</script>

<?php
$canEditCustomer = !empty($_['canEditCustomer']);
$canCreateProject = !empty($_['canCreateProject']);
$pageId = 'customer-detail';
$pageTitle = $customer->getName();
$pageHelp = $l->t('Customer details and associated projects');
ob_start(); ?>
						<div class="customer-meta">
							<?php if ($customer->getEmail()): ?>
								<span class="meta-item">
									<span data-lucide="mail" class="lucide-icon" aria-hidden="true"></span>
									<a href="mailto:<?php p($customer->getEmail()); ?>"><?php p($customer->getEmail()); ?></a>
								</span>
							<?php endif; ?>
							<?php if ($customer->getPhone()): ?>
								<span class="meta-item">
									<span data-lucide="phone" class="lucide-icon" aria-hidden="true"></span>
									<a href="tel:<?php p($customer->getPhone()); ?>"><?php p($customer->getPhone()); ?></a>
								</span>
							<?php endif; ?>
						</div>
<?php
$pageHeaderMetaHtml = ob_get_clean();
ob_start(); ?>
					<?php if ($canEditCustomer): ?>
					<a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.edit', ['id' => $customer->getId()])); ?>"
						class="button secondary">
						<span data-lucide="edit" class="lucide-icon" aria-hidden="true"></span>
						<?php p($l->t('Edit Customer')); ?>
					</a>
					<?php endif; ?>
					<?php if ($canCreateProject): ?>
					<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create', ['customer_id' => $customer->getId()])); ?>"
						class="button primary">
						<span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
						<?php p($l->t('New Project')); ?>
					</a>
					<?php endif; ?>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Customer actions');
include __DIR__ . '/common/page-start.php';
?>
		<!-- Breadcrumb Navigation -->
		<div class="breadcrumb-container">
			<nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
				<ol>
					<li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.index')); ?>"><?php p($l->t('Customers')); ?></a></li>
					<li aria-current="page"><?php p($customer->getName()); ?></li>
				</ol>
			</nav>
		</div>

		<div class="section">
			<div class="section-content">
				<div class="pc-scope-banner" role="status" aria-live="polite">
					<div class="pc-scope-banner__icon">
						<i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i>
					</div>
					<div class="pc-scope-banner__content">
						<h3><?php p($l->t('Visible data only')); ?></h3>
						<p><?php p($l->t('This page only includes projects and statistics you are allowed to access for this customer.')); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Customer Statistics -->
		<section class="section stats-section pc-stats-panel pc-section" aria-labelledby="customer-detail-stats-title">
			<div class="section-header">
				<h3 id="customer-detail-stats-title"><i data-lucide="bar-chart-3" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Key figures')); ?></h3>
				<p><?php p($l->t('Customer status at a glance')); ?></p>
			</div>
			<div class="section-content">
			<div class="stats-container">
				<!-- Primary Statistics Row -->
				<div class="stats-row primary">
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="folder" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="total-projects">-</div>
							<div class="stat-label"><?php p($l->t('Total Projects')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="play" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="active-projects">-</div>
							<div class="stat-label"><?php p($l->t('Active Projects')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="check-circle" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="completed-projects">-</div>
							<div class="stat-label"><?php p($l->t('Completed Projects')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="clock" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="total-hours">-</div>
							<div class="stat-label"><?php p($l->t('Total Hours')); ?></div>
						</div>
					</div>
				</div>

				<!-- Financial Statistics Row -->
				<div class="stats-row financial">
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="euro" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="total-budget">-</div>
							<div class="stat-label"><?php p($l->t('Total Budget')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="trending-up" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="budget-earned">-</div>
							<div class="stat-label"><?php p($l->t('Budget Earned')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="wallet" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="budget-remaining">-</div>
							<div class="stat-label"><?php p($l->t('Budget Remaining')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="percent" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="budget-utilization">-</div>
							<div class="stat-label"><?php p($l->t('Budget Utilization')); ?></div>
						</div>
					</div>
				</div>

				<!-- Performance Statistics Row -->
				<div class="stats-row performance">
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="bar-chart-3" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="average-hours-per-project">-</div>
							<div class="stat-label"><?php p($l->t('Avg Hours/Project')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="dollar-sign" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="average-revenue-per-project">-</div>
							<div class="stat-label"><?php p($l->t('Avg Revenue/Project')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="target" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="project-completion-rate">-</div>
							<div class="stat-label"><?php p($l->t('Completion Rate')); ?></div>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">
							<i data-lucide="activity" class="lucide-icon white"></i>
						</div>
						<div class="stat-content">
							<div class="stat-number" id="total-time-entries">-</div>
							<div class="stat-label"><?php p($l->t('Time Entries')); ?></div>
						</div>
					</div>
				</div>
			</div>
			</div>
		</section>

		<!-- Yearly Performance Dashboard -->
		<?php if (!empty($yearlyStats)): ?>
			<section class="section yearly-stats-section pc-section" aria-labelledby="pc-customer-yearly-heading">
				<div class="section-header">
					<h3 id="pc-customer-yearly-heading"><i data-lucide="calendar" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Year by year')); ?></h3>
					<p><?php p($l->t('Track hours and costs across all projects for this customer')); ?></p>
				</div>
				<div class="section-content">
					<div class="yearly-stats-container">
						<?php
						// Calculate totals for progress bars
						$totalHours = array_sum(array_column($yearlyStats, 'total_hours'));
						$totalCost = array_sum(array_column($yearlyStats, 'total_cost'));
						?>
						<?php foreach ($yearlyStats as $index => $yearData): ?>
							<div class="yearly-stat-card">
								<div class="yearly-stat-header">
									<h4><?php p($yearData['year']); ?></h4>
									<div class="yearly-stat-badge">
										<?php p($yearData['entry_count']); ?> <?php p($l->t('entries')); ?>
									</div>
								</div>
								<div class="yearly-stat-content">
									<div class="yearly-stat-item">
										<div class="stat-icon">
											<i class="icon-time-custom"></i>
										</div>
										<div class="stat-details">
											<div class="stat-value"><?php p(number_format($yearData['total_hours'], 1)); ?>h</div>
											<div class="stat-label"><?php p($l->t('Total Hours')); ?></div>
										</div>
									</div>
									<div class="yearly-stat-item">
										<div class="stat-icon">
											<i class="icon-money-custom"></i>
										</div>
										<div class="stat-details">
											<div class="stat-value"><?php p($fmt ? $fmt->currency((float)$yearData['total_cost']) : $currencyCode . ' ' . number_format((float)$yearData['total_cost'], 2)); ?></div>
											<div class="stat-label"><?php p($l->t('Total Cost')); ?></div>
										</div>
									</div>
								</div>

								<!-- Progress indicators -->
								<?php
								$hoursSharePct = $totalHours > 0 ? ($yearData['total_hours'] / $totalHours) * 100 : 0;
								$costSharePct = $totalCost > 0 ? ($yearData['total_cost'] / $totalCost) * 100 : 0;
								?>
								<div class="yearly-progress">
									<div class="yearly-progress-item">
										<div class="yearly-progress-label"><?php p($l->t('Hours Share')); ?></div>
										<div class="yearly-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php p(round($hoursSharePct, 1)); ?>" aria-label="<?php p($l->t('Hours Share')); ?>">
											<div class="yearly-progress-fill" style="width: <?php p($hoursSharePct); ?>%"></div>
										</div>
										<div class="yearly-progress-percentage"><?php p(round($hoursSharePct, 1)); ?>%</div>
									</div>
									<div class="yearly-progress-item">
										<div class="yearly-progress-label"><?php p($l->t('Cost Share')); ?></div>
										<div class="yearly-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php p(round($costSharePct, 1)); ?>" aria-label="<?php p($l->t('Cost Share')); ?>">
											<div class="yearly-progress-fill" style="width: <?php p($costSharePct); ?>%"></div>
										</div>
										<div class="yearly-progress-percentage"><?php p(round($costSharePct, 1)); ?>%</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- Project Type Analysis Dashboard -->
		<?php if (!empty($_['projectTypeStats'])): ?>
			<section class="section project-type-stats-section pc-section" aria-labelledby="pc-customer-type-heading">
				<div class="section-header">
					<h3 id="pc-customer-type-heading"><i data-lucide="pie-chart" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Project Type Analysis')); ?></h3>
					<p><?php p($l->t('Analyze productivity by project type to identify billable vs overhead work')); ?></p>
				</div>
				<div class="section-content">
					<div class="project-type-stats-container">
						<?php foreach ($_['projectTypeStats'] as $year => $yearData): ?>
							<div class="year-section">
								<div class="year-header">
									<h4><?php p($year); ?></h4>
									<?php
									$yearTotalHours = array_sum(array_column($yearData, 'total_hours'));
									$yearTotalCost = array_sum(array_column($yearData, 'total_cost'));
									?>
									<div class="year-summary">
										<span class="summary-item">
											<i data-lucide="clock" class="lucide-icon"></i>
											<?php p(number_format($yearTotalHours, 1)); ?>h
										</span>
										<span class="summary-item">
											<i data-lucide="euro" class="lucide-icon"></i>
											<?php p($fmt ? $fmt->currency((float)$yearTotalCost) : $currencyCode . ' ' . number_format((float)$yearTotalCost, 2)); ?>
										</span>
									</div>
								</div>
								<div class="project-types-container">
									<?php foreach ($yearData as $projectType => $typeData): ?>
										<div class="project-type-card">
											<div class="type-header">
												<h5 class="type-name"><?php p($typeData['project_type']); ?></h5>
												<div class="type-stats">
													<div class="stat-item">
														<span class="stat-value"><?php p(number_format($typeData['total_hours'], 1)); ?>h</span>
														<span class="stat-label"><?php p($l->t('Hours')); ?></span>
													</div>
													<div class="stat-item">
														<span class="stat-value"><?php p($fmt ? $fmt->currency((float)$typeData['total_cost']) : $currencyCode . ' ' . number_format((float)$typeData['total_cost'], 2)); ?></span>
														<span class="stat-label"><?php p($l->t('Cost')); ?></span>
													</div>
													<div class="stat-item">
														<span class="stat-value"><?php p($typeData['entry_count']); ?></span>
														<span class="stat-label"><?php p($l->t('Entries')); ?></span>
													</div>
												</div>
											</div>
											<div class="type-progress">
												<div class="progress-item">
													<div class="progress-label"><?php p($l->t('Hours Share')); ?></div>
													<div class="progress-bar">
														<div class="progress-fill" style="width: <?php p($yearTotalHours > 0 ? ($typeData['total_hours'] / $yearTotalHours) * 100 : 0); ?>%"></div>
													</div>
													<div class="progress-percentage"><?php p($yearTotalHours > 0 ? round(($typeData['total_hours'] / $yearTotalHours) * 100, 1) : 0); ?>%</div>
												</div>
												<div class="progress-item">
													<div class="progress-label"><?php p($l->t('Cost Share')); ?></div>
													<div class="progress-bar">
														<div class="progress-fill" style="width: <?php p($yearTotalCost > 0 ? ($typeData['total_cost'] / $yearTotalCost) * 100 : 0); ?>%"></div>
													</div>
													<div class="progress-percentage"><?php p($yearTotalCost > 0 ? round(($typeData['total_cost'] / $yearTotalCost) * 100, 1) : 0); ?>%</div>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- Main Content Grid -->
		<div class="content-grid">
			<!-- Customer Information -->
			<div class="section info-section pc-section" aria-labelledby="pc-customer-info-heading">
				<div class="section-header">
					<h3 id="pc-customer-info-heading"><i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Customer Information')); ?></h3>
					<p><?php p($l->t('Name, contact, and other details for this customer.')); ?></p>
				</div>
				<div class="section-content">
					<div class="info-grid">
						<div class="info-item">
							<label><?php p($l->t('Customer Name')); ?></label>
							<span><?php p($customer->getName()); ?></span>
						</div>

						<?php if ($customer->getContactPerson()): ?>
							<div class="info-item">
								<label><?php p($l->t('Contact Person')); ?></label>
								<span><?php p($customer->getContactPerson()); ?></span>
							</div>
						<?php endif; ?>

						<?php if ($customer->getEmail()): ?>
							<div class="info-item">
								<label><?php p($l->t('Email')); ?></label>
								<span><a href="mailto:<?php p($customer->getEmail()); ?>"><?php p($customer->getEmail()); ?></a></span>
							</div>
						<?php endif; ?>

						<?php if ($customer->getPhone()): ?>
							<div class="info-item">
								<label><?php p($l->t('Phone')); ?></label>
								<span><a href="tel:<?php p($customer->getPhone()); ?>"><?php p($customer->getPhone()); ?></a></span>
							</div>
						<?php endif; ?>

						<?php if ($customer->getAddress()): ?>
							<div class="info-item full-width">
								<label><?php p($l->t('Address')); ?></label>
								<span><?php p($customer->getAddress()); ?></span>
							</div>
						<?php endif; ?>

						<div class="info-item">
							<label><?php p($l->t('Created')); ?></label>
							<span><?php p($customer->getCreatedAt()->format('d.m.Y H:i')); ?></span>
						</div>

						<div class="info-item">
							<label><?php p($l->t('Last Updated')); ?></label>
							<span><?php p($customer->getUpdatedAt()->format('d.m.Y H:i')); ?></span>
						</div>
					</div>
				</div>
			</div>

			<?php
			$customerSettlement = $_['customerSettlement'] ?? null;
			$settlementInfoByProject = $_['settlementInfoByProject'] ?? [];
			?>
			<!-- Invoicing overview (derived from project counters — never hand-edited) -->
			<section class="section pc-section pc-invoicing-section" aria-labelledby="customer-invoicing-title">
				<div class="section-header">
					<h3 id="customer-invoicing-title">
						<span data-lucide="receipt" class="lucide-icon primary" aria-hidden="true"></span>
						<?php p($l->t('Invoicing overview')); ?>
					</h3>
					<p><?php p($l->t('How much work for this customer is still open or waiting to be paid.')); ?></p>
				</div>
				<div class="section-content">
					<?php if ($customerSettlement): ?>
						<div class="pc-invoicing-summary">
							<div class="pc-invoicing-summary__chip">
								<?php
								$chipKind = 'posture';
								$chipValue = (string)($customerSettlement['posture'] ?? 'n_a');
								include __DIR__ . '/parts/settlement-chip.php';
								?>
							</div>
							<?php
							$progress = is_array($customerSettlement['progress'] ?? null) ? $customerSettlement['progress'] : [];
							$progressVariant = 'full';
							$progressId = 'pc-customer-stl-progress';
							include __DIR__ . '/parts/settlement-progress.php';
							?>
							<?php if ((float)($customerSettlement['outstanding_hours'] ?? 0) > 0): ?>
								<p class="pc-invoicing-summary__outstanding">
									<?php p($l->t('Not yet paid: %1$s h · %2$s', [
										number_format((float)$customerSettlement['outstanding_hours'], 2),
										$fmt ? $fmt->currency((float)$customerSettlement['outstanding_amount']) : $currencyCode . ' ' . number_format((float)$customerSettlement['outstanding_amount'], 2),
									])); ?>
								</p>
							<?php else: ?>
								<p class="pc-invoicing-summary__empty">
									<?php p($l->t('Nothing outstanding — all chargeable hours on this customer’s projects are paid, or there are no chargeable hours yet.')); ?>
								</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<!-- Associated Projects -->
			<div class="section projects-section pc-cd-span-full" aria-labelledby="pc-customer-projects-heading">
				<div class="section-header">
					<h3 id="pc-customer-projects-heading"><i data-lucide="folder" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Associated Projects')); ?></h3>
					<p><?php p($l->t('Projects for this customer')); ?></p>
					<?php if ($canCreateProject): ?>
					<div class="section-header-actions">
						<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create', ['customer_id' => $customer->getId()])); ?>"
							class="button primary">
							<i data-lucide="plus" class="lucide-icon" aria-hidden="true"></i>
							<?php p($l->t('Create New Project')); ?>
						</a>
					</div>
					<?php endif; ?>
				</div>
				<div class="section-content">
					<?php if (empty($_['projects'])): ?>
						<div class="emptycontent" role="status">
							<span class="emptycontent__icon" aria-hidden="true"><i data-lucide="folder" class="lucide-icon"></i></span>
							<h2><?php p($l->t('No projects found')); ?></h2>
							<p><?php p($l->t('This customer doesn\'t have any projects yet.')); ?></p>
							<?php if ($canCreateProject): ?>
							<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create', ['customer_id' => $customer->getId()])); ?>" class="button primary">
								<i data-lucide="plus" class="lucide-icon"></i>
								<?php p($l->t('Create First Project')); ?>
							</a>
							<?php endif; ?>
						</div>
					<?php else: ?>
						<?php
						$colName = $l->t('Name');
						$colType = $l->t('Type');
						$colStatus = $l->t('Status');
						$colBudget = $l->t('Budget');
						$colProgress = $l->t('Progress');
						$colInvoicing = $l->t('Settlement');
						$colActions = $l->t('Actions');
						$iconMapping = [
							'client' => '👥',
							'admin' => '⚙️',
							'sales' => '📈',
							'customer' => '🎧',
							'product' => '💻',
							'meeting' => '🤝',
							'internal' => '🏢',
							'research' => '🔬',
							'training' => '🎓',
							'other' => '📋',
						];
						?>
						<div class="pc-list-table-wrap pc-customer-projects-table-wrap" tabindex="0" role="region" aria-label="<?php p($l->t('Associated Projects')); ?>">
							<table class="grid projects-table pc-data-table pc-customer-projects-table">
								<caption class="pc-sr-only"><?php p($l->t('Projects for this customer')); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php p($colName); ?></th>
										<th scope="col"><?php p($colType); ?></th>
										<th scope="col"><?php p($colStatus); ?></th>
										<th scope="col"><?php p($colBudget); ?></th>
										<th scope="col"><?php p($colProgress); ?></th>
										<th scope="col" class="col-invoicing"><?php p($colInvoicing); ?></th>
										<th scope="col" class="col-actions"><?php p($colActions); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($_['projects'] as $projectData): ?>
										<?php
										$project = $projectData['project'] ?? $projectData;
										$budgetInfo = $projectData['budgetInfo'] ?? null;
										$canEditRow = !empty($projectData['canEdit']);
										$projectSettlement = $settlementInfoByProject[(int)$project->getId()] ?? null;
										$showUrl = $urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $project->getId()]);
										$editUrl = $urlGenerator->linkToRoute('projectcheck.project.edit', ['id' => $project->getId()]);
										$projectType = strtolower((string)$project->getProjectType());
										$icon = $iconMapping[$projectType] ?? '📋';
										$displayName = $project->getProjectTypeDisplayName();
										$warningLevel = is_array($budgetInfo) ? (string)($budgetInfo['warning_level'] ?? 'none') : 'none';
										$consumption = is_array($budgetInfo) ? (float)($budgetInfo['consumption_percentage'] ?? 0) : 0.0;
										?>
										<tr class="project-row<?php if ($warningLevel !== '' && $warningLevel !== 'none'): ?> budget-status-<?php p($warningLevel); ?><?php endif; ?>"
											data-project-id="<?php p($project->getId()); ?>">
											<th scope="row" class="project-name-cell" data-label="<?php p($colName); ?>">
												<div class="project-name-content">
													<a href="<?php p($showUrl); ?>" class="project-title"><?php p($project->getName()); ?></a>
													<div class="project-badges">
														<span class="priority-badge priority-<?php p(strtolower((string)$project->getPriority())); ?>">
															<?php p($l->t((string)$project->getPriority())); ?>
														</span>
														<?php if ($consumption >= 100): ?>
															<span class="budget-warning-badge critical" title="<?php p($l->t('Budget Exceeded')); ?>">
																⚠️ <?php p($l->t('Over Budget')); ?>
															</span>
														<?php elseif ($warningLevel === 'critical'): ?>
															<span class="budget-warning-badge critical" title="<?php p($l->t('Budget Critical')); ?>">
																⚠️ <?php p($l->t('Critical')); ?>
															</span>
														<?php elseif ($warningLevel === 'warning'): ?>
															<span class="budget-warning-badge warning" title="<?php p($l->t('Budget Warning')); ?>">
																⚠️ <?php p($l->t('Warning')); ?>
															</span>
														<?php endif; ?>
													</div>
												</div>
											</th>
											<td data-label="<?php p($colType); ?>">
												<span class="project-type-icon"
													data-project-type="<?php p($projectType); ?>"
													title="<?php p($displayName); ?>">
													<?php p($icon); ?>
												</span>
												<span class="pc-sr-only"><?php p($l->t((string)$displayName)); ?></span>
											</td>
											<td data-label="<?php p($colStatus); ?>">
												<span class="status-badge status-<?php p(strtolower(str_replace(' ', '-', (string)$project->getStatus()))); ?>">
													<?php p($l->t((string)$project->getStatus())); ?>
												</span>
											</td>
											<td class="budget-cell" data-label="<?php p($colBudget); ?>">
												<?php if ($budgetInfo): ?>
													<div class="budget-info-compact">
														<div class="budget-main">
															<div class="budget-line">
																<span class="budget-label"><?php p($l->t('Total Budget:')); ?></span>
																<span class="budget-total"><?php p($fmt ? $fmt->currency((float)$budgetInfo['total_budget']) : $currencyCode . ' ' . number_format((float)$budgetInfo['total_budget'], 2)); ?></span>
															</div>
															<div class="budget-line">
																<span class="budget-label"><?php p($l->t('Used:')); ?></span>
																<span class="budget-used"><?php p($fmt ? $fmt->currency((float)$budgetInfo['used_budget']) : $currencyCode . ' ' . number_format((float)$budgetInfo['used_budget'], 2)); ?></span>
															</div>
															<div class="budget-line">
																<span class="budget-label"><?php p($l->t('Remaining:')); ?></span>
																<span class="budget-remaining <?php p($warningLevel); ?>">
																	<?php p($fmt ? $fmt->currency((float)$budgetInfo['remaining_budget']) : $currencyCode . ' ' . number_format((float)$budgetInfo['remaining_budget'], 2)); ?>
																</span>
															</div>
														</div>
														<div class="budget-secondary">
															<span class="budget-percentage <?php p($warningLevel); ?>">
																<?php p(round($consumption)); ?>% <?php p($l->t('used')); ?>
															</span>
														</div>
													</div>
												<?php else: ?>
													<div class="budget-info-compact">
														<div class="budget-main">
															<div class="budget-line">
																<span class="budget-label"><?php p($l->t('Total Budget:')); ?></span>
																<span class="budget-total"><?php p($fmt ? $fmt->currency((float)($project->getTotalBudget() ?? 0)) : $currencyCode . ' ' . number_format((float)($project->getTotalBudget() ?? 0), 2)); ?></span>
															</div>
														</div>
													</div>
												<?php endif; ?>
											</td>
											<td class="progress-cell" data-label="<?php p($colProgress); ?>">
												<?php if ($budgetInfo): ?>
													<div class="progress-info">
														<div class="budget-progress-bar compact">
															<div class="budget-progress-fill <?php p($warningLevel); ?>"
																style="width: <?php p(min(100, $consumption)); ?>%"></div>
														</div>
														<span class="hours-logged">
															<?php p(number_format((float)($budgetInfo['used_hours'] ?? 0), 1)); ?>h <?php p($l->t('logged')); ?>
															<?php if (!empty($budgetInfo['hours_estimated']) && ($budgetInfo['available_hours'] ?? 0) > 0): ?>
																<span class="hours-capacity-estimate" title="<?php p($l->t('Estimated capacity based on planning or project rate')); ?>">
																	· <?php p($l->t('%sh remaining (estimate)', [number_format((float)$budgetInfo['remaining_hours'], 1, '.', '')])); ?>
																</span>
															<?php endif; ?>
														</span>
													</div>
												<?php else: ?>
													<div class="progress-info">
														<div class="budget-progress-bar compact">
															<div class="budget-progress-fill" style="width: 0%"></div>
														</div>
														<span class="hours-logged">0h <?php p($l->t('logged')); ?></span>
													</div>
												<?php endif; ?>
											</td>
											<td class="col-invoicing" data-label="<?php p($colInvoicing); ?>">
												<?php if ($projectSettlement !== null): ?>
													<div class="pc-invoicing-cell">
														<?php
														$chipKind = 'posture';
														$chipValue = (string)($projectSettlement['posture'] ?? 'n_a');
														include __DIR__ . '/parts/settlement-chip.php';
														?>
														<?php
														$progress = is_array($projectSettlement['progress'] ?? null) ? $projectSettlement['progress'] : [];
														$progressVariant = 'compact';
														$progressId = 'pc-cust-proj-stl-' . (int)$project->getId();
														include __DIR__ . '/parts/settlement-progress.php';
														?>
														<?php if ((float)($projectSettlement['outstanding_hours'] ?? 0) > 0): ?>
															<span class="pc-invoicing-cell__outstanding">
																<?php p($l->t('Not yet paid: %1$s h · %2$s', [
																	number_format((float)$projectSettlement['outstanding_hours'], 2),
																	$fmt ? $fmt->currency((float)$projectSettlement['outstanding_amount']) : $currencyCode . ' ' . number_format((float)$projectSettlement['outstanding_amount'], 2),
																])); ?>
															</span>
														<?php endif; ?>
													</div>
												<?php else: ?>
													<span class="pc-muted">—</span>
												<?php endif; ?>
											</td>
											<td class="col-actions" data-label="<?php p($colActions); ?>">
												<div class="action-items" role="group" aria-label="<?php p($l->t('Project actions')); ?>">
													<a href="<?php p($showUrl); ?>"
														class="action-item action-item--view"
														title="<?php p($l->t('View project')); ?>"
														aria-label="<?php p($l->t('View project %s', [$project->getName()])); ?>">
														<span data-lucide="eye" class="lucide-icon" aria-hidden="true"></span>
													</a>
													<?php if ($canEditRow): ?>
													<a href="<?php p($editUrl); ?>"
														class="action-item action-item--edit"
														title="<?php p($l->t('Edit project')); ?>"
														aria-label="<?php p($l->t('Edit project %s', [$project->getName()])); ?>">
														<span data-lucide="edit" class="lucide-icon" aria-hidden="true"></span>
													</a>
													<?php endif; ?>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

<?php include __DIR__ . '/common/page-end.php'; ?>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Load customer statistics only (projects are now rendered server-side)
	document.addEventListener('DOMContentLoaded', function() {
		// Set widths for progress bars using data attributes
		document.querySelectorAll('[data-width]').forEach(function(el) {
			el.style.width = el.getAttribute('data-width') + '%';
		});
		
		loadCustomerStats();
	});

	function loadCustomerStats() {
		fetch(`/index.php/apps/projectcheck/api/customers/stats?customer_id=${window.projectControlData.customerId}`, {
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'requesttoken': window.projectControlData.requestToken
				}
			})
			.then(response => response.json())
			.then(data => {
				if (data.success && data.stats) {
					displayStats(data.stats);
				}
			})
			.catch(error => {
				console.error('Error loading stats:', error);
			});
	}

	function displayStats(stats) {
		// Primary statistics
		document.getElementById('total-projects').textContent = stats.total_projects || 0;
		document.getElementById('active-projects').textContent = stats.active_projects || 0;
		document.getElementById('completed-projects').textContent = stats.completed_projects || 0;
		document.getElementById('total-hours').textContent = parseFloat(stats.used_hours || 0).toFixed(1) + 'h';

		// Financial statistics
		const code = (window.ProjectCheckConfig && typeof window.ProjectCheckConfig.currency === 'string' && /^[A-Z]{3}$/i.test(window.ProjectCheckConfig.currency))
			? window.ProjectCheckConfig.currency.toUpperCase()
			: 'EUR';
		document.getElementById('total-budget').textContent = window.ProjectCheckFormat ? window.ProjectCheckFormat.currencyFmt(stats.total_budget || 0) : (code + ' ' + parseFloat(stats.total_budget || 0).toFixed(2));
		document.getElementById('budget-earned').textContent = window.ProjectCheckFormat ? window.ProjectCheckFormat.currencyFmt(stats.budget_earned || 0) : (code + ' ' + parseFloat(stats.budget_earned || 0).toFixed(2));
		document.getElementById('budget-remaining').textContent = window.ProjectCheckFormat ? window.ProjectCheckFormat.currencyFmt(stats.budget_remaining || 0) : (code + ' ' + parseFloat(stats.budget_remaining || 0).toFixed(2));
		document.getElementById('budget-utilization').textContent = parseFloat(stats.budget_utilization_percentage || 0).toFixed(1) + '%';

		// Performance statistics
		document.getElementById('average-hours-per-project').textContent = parseFloat(stats.average_hours_per_project || 0).toFixed(1) + 'h';
		document.getElementById('average-revenue-per-project').textContent = window.ProjectCheckFormat ? window.ProjectCheckFormat.currencyFmt(stats.average_revenue_per_project || 0) : (code + ' ' + parseFloat(stats.average_revenue_per_project || 0).toFixed(2));
		document.getElementById('project-completion-rate').textContent = parseFloat(stats.project_completion_rate || 0).toFixed(1) + '%';
		document.getElementById('total-time-entries').textContent = stats.total_time_entries || 0;
	}
</script>