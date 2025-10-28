<?php

/**
 * Customer detail template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

script('projectcheck', 'customer-detail');
style('projectcheck', 'dashboard');
style('projectcheck', 'projects');
style('projectcheck', 'budget-alerts');
style('projectcheck', 'custom-icons');
style('projectcheck', 'navigation');
style('projectcheck', 'customer-statistics');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Pass PHP variables to JavaScript
	window.projectControlData = {
		requestToken: '<?php p($_['requesttoken']) ?>',
		customerId: <?php p($customer->getId()); ?>
	};
</script>

<div id="app-content">
	<div id="app-content-wrapper">
		<!-- Breadcrumb Navigation -->
		<div class="breadcrumb-container">
			<nav class="breadcrumb" aria-label="Breadcrumb">
				<ol>
					<li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.index')); ?>"><?php p($l->t('Customers')); ?></a></li>
					<li aria-current="page"><?php p($customer->getName()); ?></li>
				</ol>
			</nav>
		</div>

		<!-- Page Header -->
		<div class="section page-header-section">
			<div class="header-content">
				<div class="header-text">
					<div class="header-details">
						<h2><?php p($customer->getName()); ?></h2>
						<p><?php p($l->t('Customer details and associated projects')); ?></p>
						<div class="customer-meta">
							<?php if ($customer->getEmail()): ?>
								<span class="meta-item">
									<i data-lucide="mail" class="lucide-icon primary"></i>
									<a href="mailto:<?php p($customer->getEmail()); ?>"><?php p($customer->getEmail()); ?></a>
								</span>
							<?php endif; ?>
							<?php if ($customer->getPhone()): ?>
								<span class="meta-item">
									<i data-lucide="phone" class="lucide-icon primary"></i>
									<a href="tel:<?php p($customer->getPhone()); ?>"><?php p($customer->getPhone()); ?></a>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="header-actions">
					<a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.edit', ['id' => $customer->getId()])); ?>"
						class="button secondary" role="button">
						<i data-lucide="edit" class="lucide-icon"></i>
						<?php p($l->t('Edit Customer')); ?>
					</a>
					<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create', ['customer_id' => $customer->getId()])); ?>"
						class="button primary" role="button">
						<i data-lucide="plus" class="lucide-icon"></i>
						<?php p($l->t('New Project')); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Customer Statistics -->
		<div class="section stats-section">
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

		<!-- Yearly Performance Dashboard -->
		<?php if (!empty($yearlyStats)): ?>
			<div class="section yearly-stats-section">
				<div class="section-header">
					<h3><i data-lucide="calendar" class="lucide-icon primary"></i> <?php p($l->t('Yearly Performance Dashboard')); ?></h3>
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
											<div class="stat-value">€<?php p(number_format($yearData['total_cost'], 2)); ?></div>
											<div class="stat-label"><?php p($l->t('Total Cost')); ?></div>
										</div>
									</div>
								</div>

								<!-- Progress indicators -->
								<div class="yearly-progress">
									<div class="yearly-progress-item">
										<div class="yearly-progress-label"><?php p($l->t('Hours Share')); ?></div>
										<div class="yearly-progress-bar">
											<div class="yearly-progress-fill" style="width: <?php p($totalHours > 0 ? ($yearData['total_hours'] / $totalHours) * 100 : 0); ?>%"></div>
										</div>
										<div class="yearly-progress-percentage"><?php p($totalHours > 0 ? round(($yearData['total_hours'] / $totalHours) * 100, 1) : 0); ?>%</div>
									</div>
									<div class="yearly-progress-item">
										<div class="yearly-progress-label"><?php p($l->t('Cost Share')); ?></div>
										<div class="yearly-progress-bar">
											<div class="yearly-progress-fill" style="width: <?php p($totalCost > 0 ? ($yearData['total_cost'] / $totalCost) * 100 : 0); ?>%"></div>
										</div>
										<div class="yearly-progress-percentage"><?php p($totalCost > 0 ? round(($yearData['total_cost'] / $totalCost) * 100, 1) : 0); ?>%</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Project Type Analysis Dashboard -->
		<?php if (!empty($_['projectTypeStats'])): ?>
			<div class="section project-type-stats-section">
				<div class="section-header">
					<h3><i data-lucide="pie-chart" class="lucide-icon primary"></i> <?php p($l->t('Project Type Analysis')); ?></h3>
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
											€<?php p(number_format($yearTotalCost, 2)); ?>
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
														<span class="stat-value">€<?php p(number_format($typeData['total_cost'], 2)); ?></span>
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
			</div>
		<?php endif; ?>

		<!-- Main Content Grid -->
		<div class="content-grid">
			<!-- Customer Information -->
			<div class="section info-section">
				<div class="section-header">
					<h3><i data-lucide="info" class="lucide-icon primary"></i> <?php p($l->t('Customer Information')); ?></h3>
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

			<!-- Associated Projects -->
			<div class="section projects-section">
				<div class="section-header">
					<h3><i data-lucide="folder" class="lucide-icon primary"></i> <?php p($l->t('Associated Projects')); ?></h3>
					<div class="section-header-actions">
						<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create', ['customer_id' => $customer->getId()])); ?>"
							class="button primary" role="button">
							<i data-lucide="plus" class="lucide-icon"></i>
							<?php p($l->t('Create New Project')); ?>
						</a>
					</div>
				</div>
				<div class="section-content">
					<?php if (empty($_['projects'])): ?>
						<div class="empty-state">
							<div class="empty-icon">
								<i data-lucide="folder" class="lucide-icon"></i>
							</div>
							<h4><?php p($l->t('No projects found')); ?></h4>
							<p><?php p($l->t('This customer doesn\'t have any projects yet.')); ?></p>
							<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create', ['customer_id' => $customer->getId()])); ?>" class="button primary">
								<i data-lucide="plus" class="lucide-icon"></i>
								<?php p($l->t('Create First Project')); ?>
							</a>
						</div>
					<?php else: ?>
						<div class="projects-grid">
							<?php foreach ($_['projects'] as $projectData): ?>
								<?php
								$project = $projectData['project'] ?? $projectData;
								$budgetInfo = $projectData['budgetInfo'] ?? null;
								?>
								<div class="project-card dashboard-card <?php if ($budgetInfo): ?>budget-status-<?php p($budgetInfo['warning_level']); ?><?php endif; ?>">
									<div class="card-header">
										<div class="card-title-section">
											<h4 class="project-name">
												<a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $project->getId()])); ?>">
													<?php p($project->getName()); ?>
												</a>
											</h4>
											<div class="project-status-badges">
												<?php
												// Icon mapping for project types
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
													'other' => '📋'
												];
												$projectType = strtolower($project->getProjectType());
												$icon = $iconMapping[$projectType] ?? '📋';
												$displayName = $project->getProjectTypeDisplayName();
												?>
												<span class="project-type-icon"
													data-project-type="<?php p($projectType); ?>"
													title="<?php p($displayName); ?>">
													<?php p($icon); ?>
												</span>
												<span class="status-badge status-<?php p(strtolower(str_replace(' ', '-', $project->getStatus()))); ?>">
													<?php p($project->getStatus()); ?>
												</span>
												<?php if ($budgetInfo): ?>
													<?php if ($budgetInfo['consumption_percentage'] >= 100): ?>
														<span class="budget-status-badge critical">⚠️ <?php p($l->t('Over Budget')); ?></span>
													<?php elseif ($budgetInfo['warning_level'] === 'critical'): ?>
														<span class="budget-status-badge critical">⚠️ <?php p($l->t('Critical')); ?></span>
													<?php elseif ($budgetInfo['warning_level'] === 'warning'): ?>
														<span class="budget-status-badge warning">⚠️ <?php p($l->t('Warning')); ?></span>
													<?php else: ?>
														<span class="budget-status-badge safe">✅ <?php p($l->t('On Track')); ?></span>
													<?php endif; ?>
												<?php else: ?>
													<span class="budget-status-badge safe">✅ On Track</span>
												<?php endif; ?>
											</div>
										</div>
									</div>
									<div class="card-content">
										<div class="project-details">
											<div class="detail-row">
												<span class="detail-label"><?php p($l->t('Customer:')); ?></span>
												<span class="detail-value"><?php p($customer->getName()); ?></span>
											</div>
											<?php if ($budgetInfo): ?>
												<div class="detail-row budget-detail">
													<span class="detail-label"><?php p($l->t('Budget:')); ?></span>
													<span class="detail-value budget-info">
														<span class="budget-remaining <?php p($budgetInfo['warning_level']); ?>">
															€<?php p(number_format($budgetInfo['remaining_budget'], 2)); ?>
														</span>
														<span class="budget-separator"><?php p($l->t('remaining of')); ?></span>
														<span class="budget-total">€<?php p(number_format($budgetInfo['total_budget'], 2)); ?></span>
													</span>
												</div>
												<div class="detail-row">
													<span class="detail-label"><?php p($l->t('Progress:')); ?></span>
													<span class="detail-value progress-info">
														<span class="usage-stats">
															<?php p(round($budgetInfo['consumption_percentage'])); ?>% <?php p($l->t('used')); ?>
															• <?php p(number_format($budgetInfo['used_hours'], 1)); ?>h <?php p($l->t('logged')); ?>
														</span>
													</span>
												</div>
											<?php else: ?>
												<div class="detail-row">
													<span class="detail-label"><?php p($l->t('Budget:')); ?></span>
													<span class="detail-value">€<?php p(number_format($project->getTotalBudget() ?? 0, 2)); ?></span>
												</div>
											<?php endif; ?>
										</div>

										<?php if ($budgetInfo): ?>
											<div class="card-progress-section">
												<div class="budget-progress-bar dashboard">
													<div class="budget-progress-fill <?php p($budgetInfo['warning_level']); ?>"
														style="width: <?php p(min(100, $budgetInfo['consumption_percentage'])); ?>%"></div>
												</div>
												<div class="progress-labels">
													<span class="progress-label-left">€0</span>
													<span class="progress-label-center <?php p($budgetInfo['warning_level']); ?>">
														<?php p(round($budgetInfo['consumption_percentage'])); ?>%
													</span>
													<span class="progress-label-right">€<?php p(number_format($budgetInfo['total_budget'], 0)); ?></span>
												</div>
											</div>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Local SVG icon library
	const svgIcons = {
		mail: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
		phone: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
		edit: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
		plus: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		folder: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
		play: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polygon points="5,3 19,12 5,21"/></svg>',
		euro: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>',
		clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
		info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
		'loader-2': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
		'alert-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
		calendar: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
		'check-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>',
		'trending-up': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline points="16,7 22,7 22,13"/></svg>',
		wallet: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M19 7H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
		percent: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>',
		'bar-chart-3': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',
		'dollar-sign': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
		target: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
		activity: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>',
		'icon-time-custom': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
		'icon-money-custom': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>'
	};

	// Initialize icons
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('[data-lucide]').forEach(function(el) {
			const iconName = el.getAttribute('data-lucide');
			if (svgIcons[iconName]) {
				el.innerHTML = svgIcons[iconName];
			}
		});

		// Initialize custom icons
		document.querySelectorAll('.icon-time-custom, .icon-money-custom').forEach(function(el) {
			const className = el.className;
			if (svgIcons[className]) {
				el.innerHTML = svgIcons[className];
			}
		});
	});
</script>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Load customer statistics only (projects are now rendered server-side)
	document.addEventListener('DOMContentLoaded', function() {
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
		document.getElementById('total-budget').textContent = '€' + parseFloat(stats.total_budget || 0).toFixed(2);
		document.getElementById('budget-earned').textContent = '€' + parseFloat(stats.budget_earned || 0).toFixed(2);
		document.getElementById('budget-remaining').textContent = '€' + parseFloat(stats.budget_remaining || 0).toFixed(2);
		document.getElementById('budget-utilization').textContent = parseFloat(stats.budget_utilization_percentage || 0).toFixed(1) + '%';

		// Performance statistics
		document.getElementById('average-hours-per-project').textContent = parseFloat(stats.average_hours_per_project || 0).toFixed(1) + 'h';
		document.getElementById('average-revenue-per-project').textContent = '€' + parseFloat(stats.average_revenue_per_project || 0).toFixed(2);
		document.getElementById('project-completion-rate').textContent = parseFloat(stats.project_completion_rate || 0).toFixed(1) + '%';
		document.getElementById('total-time-entries').textContent = stats.total_time_entries || 0;
	}
</script>