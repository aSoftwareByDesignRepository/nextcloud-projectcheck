<?php

/**
 * Customer form template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

script('projectcheck', 'customer-form');
style('projectcheck', 'customers');
style('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
	<div id="app-content-wrapper">
		<div class="customer-form-container">
			<div class="customer-form-header">
				<h1><?php if ($isEdit): ?><?php p($l->t('Edit Customer')); ?><?php else: ?><?php p($l->t('Add Customer')); ?><?php endif; ?></h1>
				<p><?php if ($isEdit): ?><?php p($l->t('Update customer information')); ?><?php else: ?><?php p($l->t('Create a new customer with complete contact details')); ?><?php endif; ?></p>
			</div>

			<!-- Form Messages -->
			<div id="form-message" class="form-message" style="display: none;"></div>

			<form id="customer-form" class="customer-form" method="POST"
				action="<?php if ($isEdit): ?><?php p($urlGenerator->linkToRoute('projectcheck.customer.updatePost', ['id' => $customer->getId()])); ?><?php else: ?><?php p($urlGenerator->linkToRoute('projectcheck.customer.store')); ?><?php endif; ?>"
				novalidate>
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
				<?php if ($isEdit): ?>
					<input type="hidden" name="_method" value="PUT">
				<?php endif; ?>

				<div class="form-section">
					<h3><?php p($l->t('Basic Information')); ?></h3>
					<div class="form-grid">
						<div class="form-group">
							<label for="name" class="required"><?php p($l->t('Customer Name')); ?></label>
							<input type="text" id="name" name="name"
								value="<?php if ($customer): p($customer->getName());
										endif; ?>"
								required class="form-control"
								placeholder="<?php p($l->t('Enter customer or company name')); ?>"
								maxlength="100"
								autocomplete="organization">
							<small><?php p($l->t('The name of the customer or company (required)')); ?></small>
							<div class="error-message" id="name-error"></div>
						</div>

						<div class="form-group">
							<label for="contact_person"><?php p($l->t('Contact Person')); ?></label>
							<input type="text" id="contact_person" name="contact_person"
								value="<?php if ($customer): p($customer->getContactPerson());
										endif; ?>"
								class="form-control"
								placeholder="<?php p($l->t('Enter primary contact person name')); ?>"
								maxlength="100"
								autocomplete="name">
							<small><?php p($l->t('Primary contact person for this customer')); ?></small>
							<div class="error-message" id="contact_person-error"></div>
						</div>
					</div>
				</div>

				<div class="form-section">
					<h3><i data-lucide="mail" class="lucide-icon primary"></i><?php p($l->t('Contact Information')); ?></h3>
					<div class="form-grid">
						<div class="form-group">
							<label for="email"><?php p($l->t('Email Address')); ?></label>
							<input type="text" id="email" name="email"
								value="<?php if ($customer): p($customer->getEmail());
										endif; ?>"
								class="form-control"
								placeholder="<?php p($l->t('Enter email address')); ?>"
								maxlength="254"
								autocomplete="email">
							<small><?php p($l->t('Primary email address for communication')); ?></small>
							<div class="error-message" id="email-error"></div>
						</div>

						<div class="form-group">
							<label for="phone"><?php p($l->t('Phone Number')); ?></label>
							<input type="tel" id="phone" name="phone"
								value="<?php if ($customer): p($customer->getPhone());
										endif; ?>"
								class="form-control"
								placeholder="<?php p($l->t('Enter phone number')); ?>"
								maxlength="50"
								autocomplete="tel">
							<small><?php p($l->t('Primary phone number for contact')); ?></small>
							<div class="error-message" id="phone-error"></div>
						</div>

						<div class="form-group full-width">
							<label for="address"><?php p($l->t('Address')); ?></label>
							<textarea id="address" name="address" rows="4" class="form-control"
								placeholder="<?php p($l->t('Enter full address')); ?>"
								maxlength="500"
								autocomplete="street-address"><?php if ($customer): p($customer->getAddress());
																endif; ?></textarea>
							<small><?php p($l->t('Full address of the customer')); ?></small>
							<div class="error-message" id="address-error"></div>
						</div>
					</div>
				</div>

				<div class="form-actions">
					<a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.index')); ?>"
						class="button" role="button">
						<i data-lucide="x" class="lucide-icon"></i>
						<?php p($l->t('Cancel')); ?>
					</a>
					<button type="submit" class="button primary">
						<i data-lucide="<?php echo $isEdit ? 'save' : 'plus'; ?>" class="lucide-icon"></i>
						<?php if ($isEdit): ?><?php p($l->t('Update Customer')); ?><?php else: ?><?php p($l->t('Create Customer')); ?><?php endif; ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Local SVG icon library
	const svgIcons = {
		user: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		mail: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
		x: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
		save: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg>',
		plus: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		edit: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
		folder: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
		home: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>',
		users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
		clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
		settings: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.39a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>'
	};

	// Initialize icons immediatelyich h
	document.addEventListener('DOMContentLoaded', function() {
		console.log('Initializing local SVG icons...');

		document.querySelectorAll('[data-lucide]').forEach(function(el) {
			const iconName = el.getAttribute('data-lucide');
			if (svgIcons[iconName]) {
				el.innerHTML = svgIcons[iconName];
				console.log('Added icon:', iconName);
			} else {
				console.warn('Icon not found:', iconName);
			}
		});

		console.log('Icons initialization complete');
	});
</script>