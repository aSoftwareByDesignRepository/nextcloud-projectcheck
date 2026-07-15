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

<?php
$pageId = $isEdit ? 'customer-edit' : 'customer-create';
$pageTitle = $isEdit ? $l->t('Edit Customer') : $l->t('Add Customer');
$pageHelp = $isEdit ? $l->t('Update customer information') : $l->t('Create a new customer with complete contact details');
ob_start(); ?>
					<a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.index')); ?>" class="button secondary">
						<span data-lucide="arrow-left" class="lucide-icon" aria-hidden="true"></span>
						<?php p($l->t('Cancel')); ?>
					</a>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Page actions');
include __DIR__ . '/common/page-start.php';
?>
		<div class="customer-form-container pc-section">

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

<?php include __DIR__ . '/common/page-end.php'; ?>