/**
 * Deletion Modal — DOM-built confirmation with dependency analysis (no innerHTML).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	let currentModal = null;
	let currentDialog = null;
	let modalA11yEntry = null;
	let currentEntity = null;
	let currentCallbacks = {
		onSuccess: null,
		onCancel: null
	};

	/**
	 * @param {string} tag
	 * @param {string} [className]
	 * @returns {HTMLElement}
	 */
	function createEl(tag, className) {
		const el = document.createElement(tag);
		if (className) {
			el.className = className;
		}
		return el;
	}

	/**
	 * @param {string} text
	 * @returns {Text}
	 */
	function textNode(text) {
		return document.createTextNode(text == null ? '' : String(text));
	}

	/**
	 * Append "Delete … %s …" message with the entity name emphasised (text only).
	 *
	 * @param {HTMLElement} parent
	 * @param {string} entityName
	 */
	function appendDeleteConfirmMessage(parent, entityName) {
		const template = t('projectcheck', 'Are you sure you want to delete %s? This action cannot be undone.');
		const parts = template.split('%s');
		if (parts.length === 2) {
			parent.appendChild(textNode(parts[0]));
			const strong = createEl('strong');
			strong.textContent = entityName;
			parent.appendChild(strong);
			parent.appendChild(textNode(parts[1]));
			return;
		}
		parent.textContent = template.replace('%s', entityName);
	}

	function createCloseIconSvg() {
		const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('width', '20');
		svg.setAttribute('height', '20');
		svg.setAttribute('viewBox', '0 0 20 20');
		svg.setAttribute('fill', 'currentColor');
		svg.setAttribute('aria-hidden', 'true');
		const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		path.setAttribute(
			'd',
			'M15.898,4.045c-0.271-0.272-0.713-0.272-0.986,0l-4.71,4.711L5.493,4.045c-0.272-0.272-0.714-0.272-0.986,0s-0.272,0.714,0,0.986l4.709,4.711l-4.71,4.711c-0.272,0.271-0.272,0.713,0,0.986c0.136,0.136,0.314,0.203,0.492,0.203c0.179,0,0.357-0.067,0.493-0.203l4.711-4.711l4.71,4.711c0.136,0.136,0.314,0.203,0.494,0.203c0.18,0,0.357-0.067,0.493-0.203c0.272-0.273,0.272-0.715,0-0.986L10.187,9.742l4.711-4.711C16.17,4.759,16.17,4.317,15.898,4.045z'
		);
		svg.appendChild(path);
		return svg;
	}

	/**
	 * Build modal shell and append to document.body.
	 */
	function mountModalShell() {
		const root = createEl('div', 'projectcheck-deletion-modal');
		root.id = 'projectcheck-deletion-modal';
		root.style.display = 'none';

		const backdrop = createEl('div', 'projectcheck-deletion-modal__backdrop');
		backdrop.setAttribute('aria-hidden', 'true');

		const container = createEl('div', 'projectcheck-deletion-modal__container');
		container.setAttribute('role', 'dialog');
		container.setAttribute('aria-modal', 'true');
		container.setAttribute('aria-labelledby', 'deletion-modal-title');

		const header = createEl('div', 'projectcheck-deletion-modal__header');
		const title = createEl('h2', 'projectcheck-deletion-modal__title');
		title.id = 'deletion-modal-title';
		title.appendChild(textNode(t('projectcheck', 'Delete')));

		const closeBtn = createEl('button', 'projectcheck-deletion-modal__close');
		closeBtn.type = 'button';
		closeBtn.setAttribute('aria-label', t('projectcheck', 'Close modal'));
		closeBtn.appendChild(createCloseIconSvg());

		header.appendChild(title);
		header.appendChild(closeBtn);

		const body = createEl('div', 'projectcheck-deletion-modal__body');
		body.setAttribute('aria-live', 'polite');
		appendLoadingState(body);

		container.appendChild(header);
		container.appendChild(body);
		root.appendChild(backdrop);
		root.appendChild(container);
		document.body.appendChild(root);

		return { root: root, container: container, body: body, closeBtn: closeBtn, backdrop: backdrop };
	}

	function appendLoadingState(body) {
		body.replaceChildren();
		const loading = createEl('div', 'projectcheck-deletion-modal__loading');
		loading.setAttribute('aria-busy', 'true');
		loading.appendChild(createEl('div', 'projectcheck-deletion-modal__spinner'));
		loading.appendChild(textNode(t('projectcheck', 'Analyzing dependencies...')));
		body.appendChild(loading);
	}

	/**
	 * @param {HTMLElement} parent
	 * @returns {{ cancel: HTMLButtonElement, delete: HTMLButtonElement }}
	 */
	function appendActionButtons(parent) {
		const actions = createEl('div', 'projectcheck-deletion-modal__actions');
		const cancelBtn = createEl('button', 'projectcheck-deletion-modal__btn projectcheck-deletion-modal__btn--cancel');
		cancelBtn.type = 'button';
		cancelBtn.appendChild(textNode(t('projectcheck', 'Cancel')));
		const deleteBtn = createEl('button', 'projectcheck-deletion-modal__btn projectcheck-deletion-modal__btn--delete');
		deleteBtn.type = 'button';
		deleteBtn.appendChild(textNode(t('projectcheck', 'Delete')));
		actions.appendChild(cancelBtn);
		actions.appendChild(deleteBtn);
		parent.appendChild(actions);
		labelDeleteButton(deleteBtn);
		return { cancel: cancelBtn, delete: deleteBtn };
	}

	/**
	 * @param {HTMLButtonElement} deleteBtn
	 */
	function labelDeleteButton(deleteBtn) {
		if (!deleteBtn || !currentEntity) {
			return;
		}
		const name = currentEntity.name || t('projectcheck', 'this item');
		const label = t('projectcheck', 'Confirm delete of %s').replace('%s', String(name));
		deleteBtn.setAttribute('aria-label', label);
	}

	/**
	 * @param {HTMLElement} parent
	 * @param {string} entityName
	 * @param {string} [customMessage]
	 * @returns {HTMLElement}
	 */
	function appendWarningBlock(parent, entityName, customMessage) {
		const warning = createEl('div', 'projectcheck-deletion-modal__warning');
		const warningTitle = createEl('h3', 'projectcheck-deletion-modal__warning-title');
		warningTitle.id = 'deletion-modal-warning-title';
		warningTitle.appendChild(textNode(t('projectcheck', 'Warning')));
		const message = createEl('p', 'projectcheck-deletion-modal__warning-message');
		message.id = 'deletion-modal-warning-message';
		if (customMessage) {
			message.textContent = customMessage;
		} else {
			appendDeleteConfirmMessage(message, entityName);
		}
		warning.appendChild(warningTitle);
		warning.appendChild(message);
		parent.appendChild(warning);
		return warning;
	}

	/**
	 * @param {HTMLElement} listHost
	 * @param {Record<string, number>} impact
	 */
	function appendImpactList(listHost, impact) {
		const items = [];
		switch (currentEntity.type) {
			case 'project':
				if (impact.time_entries > 0) {
					items.push(impact.time_entries + ' ' + t('projectcheck', 'time entries will be deleted'));
				}
				if (impact.project_members > 0) {
					items.push(impact.project_members + ' ' + t('projectcheck', 'team members will be removed'));
				}
				break;
			case 'customer':
				if (impact.projects > 0) {
					items.push(impact.projects + ' ' + t('projectcheck', 'projects are associated'));
				}
				if (impact.time_entries > 0) {
					items.push(impact.time_entries + ' ' + t('projectcheck', 'time entries across all projects'));
				}
				if (impact.project_members > 0) {
					items.push(impact.project_members + ' ' + t('projectcheck', 'team members across all projects'));
				}
				break;
			case 'member':
				if (impact.time_entries > 0) {
					items.push(impact.time_entries + ' ' + t('projectcheck', 'time entries will remain (unchanged)'));
				}
				break;
			default:
				break;
		}

		listHost.replaceChildren();
		if (!items.length) {
			const p = createEl('p');
			p.appendChild(textNode(t('projectcheck', 'No dependencies found.')));
			listHost.appendChild(p);
			return;
		}
		const ul = createEl('ul');
		items.forEach(function (line) {
			const li = createEl('li');
			li.appendChild(textNode(line));
			ul.appendChild(li);
		});
		listHost.appendChild(ul);
	}

	/**
	 * @param {HTMLElement} parent
	 * @param {Record<string, number>} impact
	 */
	function appendImpactSection(parent, impact) {
		const section = createEl('div', 'projectcheck-deletion-modal__impact');
		const impactTitle = createEl('h3', 'projectcheck-deletion-modal__impact-title');
		impactTitle.appendChild(textNode(t('projectcheck', 'Impact Analysis')));
		const listHost = createEl('div', 'projectcheck-deletion-modal__impact-list');
		appendImpactList(listHost, impact);
		const summary = createEl('p', 'projectcheck-deletion-modal__impact-summary');
		summary.appendChild(textNode(t('projectcheck', 'Total items affected') + ': '));
		const total = createEl('span', 'projectcheck-deletion-modal__impact-total');
		total.appendChild(textNode(String(getTotalImpact(impact))));
		summary.appendChild(total);
		section.appendChild(impactTitle);
		section.appendChild(listHost);
		section.appendChild(summary);
		parent.appendChild(section);
	}

	/**
	 * @param {HTMLElement} parent
	 */
	function appendStrategySection(parent) {
		const section = createEl('div', 'projectcheck-deletion-modal__strategy');
		const title = createEl('h3', 'projectcheck-deletion-modal__strategy-title');
		title.id = 'deletion-modal-strategy-title';
		title.appendChild(textNode(t('projectcheck', 'Deletion Strategy')));
		const fieldset = document.createElement('fieldset');
		fieldset.className = 'projectcheck-deletion-modal__strategy-fieldset';
		const legend = document.createElement('legend');
		legend.className = 'projectcheck-deletion-modal__strategy-legend';
		legend.appendChild(textNode(t('projectcheck', 'Choose how to handle associated projects')));
		fieldset.appendChild(legend);
		const options = createEl('div', 'projectcheck-deletion-modal__strategy-options');

		const strategies = [
			{
				value: 'restrict',
				checked: true,
				labelStrong: t('projectcheck', 'Restrict'),
				labelRest: t('projectcheck', 'Only delete if no projects exist'),
			},
			{
				value: 'cascade',
				checked: false,
				labelStrong: t('projectcheck', 'Cascade'),
				labelRest: t('projectcheck', 'Delete customer and all associated projects'),
			},
			{
				value: 'reassign',
				checked: false,
				labelStrong: t('projectcheck', 'Reassign'),
				labelRest: t('projectcheck', 'Move projects to another customer'),
			},
		];

		strategies.forEach(function (s) {
			const label = createEl('label', 'projectcheck-deletion-modal__strategy-option');
			const input = document.createElement('input');
			input.type = 'radio';
			input.name = 'deletion-strategy';
			input.value = s.value;
			if (s.checked) {
				input.checked = true;
			}
			const span = createEl('span', 'projectcheck-deletion-modal__strategy-label');
			const strong = createEl('strong');
			strong.appendChild(textNode(s.labelStrong));
			span.appendChild(strong);
			span.appendChild(textNode(' - ' + s.labelRest));
			label.appendChild(input);
			label.appendChild(span);
			options.appendChild(label);
		});

		const reassign = createEl('div', 'projectcheck-deletion-modal__reassign-options');
		reassign.hidden = true;
		const reassignLabel = createEl('label');
		reassignLabel.setAttribute('for', 'reassign-customer');
		reassignLabel.appendChild(textNode(t('projectcheck', 'Reassign to customer:')));
		const select = document.createElement('select');
		select.id = 'reassign-customer';
		select.name = 'reassign-customer-id';
		const emptyOpt = document.createElement('option');
		emptyOpt.value = '';
		emptyOpt.appendChild(textNode(t('projectcheck', 'Select customer...')));
		select.appendChild(emptyOpt);
		reassign.appendChild(reassignLabel);
		reassign.appendChild(select);

		fieldset.appendChild(options);
		fieldset.appendChild(reassign);
		section.appendChild(title);
		section.appendChild(fieldset);
		parent.appendChild(section);
	}

	function showDeletionModal(options) {
		if (currentModal) {
			closeDeletionModal();
		}

		currentEntity = {
			type: options.entityType,
			id: options.entityId,
			name: options.entityName,
			deleteUrl: options.deleteUrl,
			impactUrl: options.impactUrl || null,
			simpleConfirm: options.simpleConfirm === true,
			confirmMessage: options.confirmMessage || null
		};
		currentCallbacks = {
			onSuccess: typeof options.onSuccess === 'function' ? options.onSuccess : null,
			onCancel: typeof options.onCancel === 'function' ? options.onCancel : null
		};

		const shell = mountModalShell();
		currentModal = shell.root;
		currentDialog = shell.container;

		addModalEventListeners(shell);

		currentModal.style.display = 'flex';
		document.body.style.overflow = 'hidden';

		if (window.ProjectCheckModalA11y && typeof window.ProjectCheckModalA11y.attach === 'function') {
			modalA11yEntry = window.ProjectCheckModalA11y.attach(shell.container, {
				dismissOnEscape: true,
				dismissOnBackdrop: false,
				initialFocus: shell.closeBtn,
				onDismiss: function () {
					dismissDeletionModal('a11y');
				}
			});
		} else {
			shell.closeBtn.focus();
		}

		if (currentEntity.simpleConfirm) {
			displaySimpleConfirm();
			return;
		}

		loadDependencies();
	}

	function addModalEventListeners(shell) {
		if (!currentModal) {
			return;
		}

		shell.closeBtn.addEventListener('click', function () {
			dismissDeletionModal('close');
		});

		shell.backdrop.addEventListener('click', function () {
			dismissDeletionModal('backdrop');
		});
	}

	/**
	 * Close modal and optionally invoke cancel callback (Escape, Cancel, backdrop).
	 *
	 * @param {string} [reason]
	 */
	function dismissDeletionModal(reason) {
		const cancelCb = typeof currentCallbacks.onCancel === 'function' ? currentCallbacks.onCancel : null;
		closeDeletionModal();
		if (cancelCb && reason !== 'programmatic') {
			cancelCb();
		}
	}

	function loadDependencies() {
		if (!currentEntity || !currentModal) {
			return;
		}

		let impactUrl;
		try {
			impactUrl = getImpactUrl(currentEntity.type, currentEntity.id);
		} catch (e) {
			console.error(e);
			displayError(t('projectcheck', 'Failed to load dependency information'));
			return;
		}

		fetch(impactUrl, {
			method: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				requesttoken: getRequestToken(),
				Accept: 'application/json'
			},
			credentials: 'same-origin'
		})
			.then(function (response) {
				return parseJsonResponse(response).then(function (data) {
					return { response: response, data: data };
				});
			})
			.then(function (result) {
				if (!currentModal) {
					return;
				}
				if (!result.response.ok) {
					const msg = result.data.error || result.data.message
						|| t('projectcheck', 'Failed to load dependency information');
					displayError(msg);
					return;
				}
				if (result.data.success) {
					displayDependencyInfo(result.data.impact || {});
				} else {
					displayError(result.data.error || t('projectcheck', 'Failed to load dependency information'));
				}
			})
			.catch(function (error) {
				console.error('Error loading dependencies:', error);
				if (currentModal) {
					displayError(t('projectcheck', 'Failed to load dependency information'));
				}
			});
	}

	function getImpactUrl(entityType, entityId) {
		if (currentEntity && typeof currentEntity.impactUrl === 'string' && currentEntity.impactUrl !== '') {
			return resolvePlaceholderInUrl(currentEntity.impactUrl, entityId);
		}
		if (typeof OC === 'undefined' || !OC.generateUrl) {
			throw new Error('OC.generateUrl unavailable');
		}
		switch (entityType) {
			case 'project':
				return OC.generateUrl('/apps/projectcheck/api/projects/{id}/deletion-impact', { id: entityId });
			case 'customer':
				return OC.generateUrl('/apps/projectcheck/customers/{id}/deletion-impact', { id: entityId });
			case 'time_entry':
				return OC.generateUrl('/apps/projectcheck/api/time-entries/{id}/deletion-impact', { id: entityId });
			case 'member':
				return OC.generateUrl('/apps/projectcheck/api/project-members/{id}/deletion-impact', { id: entityId });
			default:
				throw new Error('Unknown entity type: ' + entityType);
		}
	}

	function displayDependencyInfo(impact) {
		if (!currentModal || !currentEntity) {
			return;
		}
		const body = currentModal.querySelector('.projectcheck-deletion-modal__body');
		if (!body) {
			return;
		}

		body.replaceChildren();
		currentEntity.impact = impact || {};
		const entityName = currentEntity.name || t('projectcheck', 'this item');
		appendWarningBlock(body, entityName);

		if (hasDependencies(impact)) {
			appendImpactSection(body, impact);
		}
		if (currentEntity.type === 'customer' && hasDependencies(impact)) {
			appendStrategySection(body);
		}
		appendActionButtons(body);
		addActionEventListeners();
	}

	function displaySimpleConfirm() {
		if (!currentModal || !currentEntity) {
			return;
		}
		const body = currentModal.querySelector('.projectcheck-deletion-modal__body');
		if (!body) {
			return;
		}

		body.replaceChildren();
		const entityName = currentEntity.name || t('projectcheck', 'this item');
		const customMessage = currentEntity.confirmMessage
			? String(currentEntity.confirmMessage)
			: null;
		appendWarningBlock(body, entityName, customMessage);
		appendActionButtons(body);
		addActionEventListeners();
	}

	function hasDependencies(impact) {
		if (!impact || !currentEntity) {
			return false;
		}
		switch (currentEntity.type) {
			case 'project':
				return (impact.time_entries > 0) || (impact.project_members > 0);
			case 'customer':
				return (impact.projects > 0) || (impact.time_entries > 0) || (impact.project_members > 0);
			case 'time_entry':
				return false;
			case 'member':
				return impact.time_entries > 0;
			default:
				return false;
		}
	}

	function getTotalImpact(impact) {
		if (!impact || !currentEntity) {
			return 0;
		}
		switch (currentEntity.type) {
			case 'project':
				return (impact.time_entries || 0) + (impact.project_members || 0);
			case 'customer':
				return (impact.projects || 0) + (impact.time_entries || 0) + (impact.project_members || 0);
			case 'member':
				return 0;
			default:
				return 0;
		}
	}

	function addActionEventListeners() {
		if (!currentModal) {
			return;
		}

		const cancelBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--cancel');
		if (cancelBtn) {
			cancelBtn.addEventListener('click', function () {
				dismissDeletionModal('cancel');
			});
		}

		const deleteBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--delete');
		if (deleteBtn) {
			deleteBtn.addEventListener('click', performDeletion);
		}

		if (currentEntity && currentEntity.type === 'customer') {
			setupStrategySelection();
		}
	}

	function setupStrategySelection() {
		if (!currentModal) {
			return;
		}
		const strategyOptions = currentModal.querySelectorAll('input[name="deletion-strategy"]');
		const reassignOptions = currentModal.querySelector('.projectcheck-deletion-modal__reassign-options');

		strategyOptions.forEach(function (option) {
			option.addEventListener('change', function () {
				if (reassignOptions) {
					reassignOptions.hidden = option.value !== 'reassign';
				}
			});
		});

		loadCustomerOptions();
	}

	function loadCustomerOptions() {
		if (!currentModal) {
			return;
		}
		const select = currentModal.querySelector('#reassign-customer');
		if (!select || typeof OC === 'undefined' || !OC.generateUrl) {
			return;
		}

		const token = getRequestToken();
		const excludeId = currentEntity && currentEntity.id != null ? String(currentEntity.id) : '';
		const base = OC.generateUrl('/apps/projectcheck/api/customers/select');
		const url = excludeId
			? base + (base.indexOf('?') === -1 ? '?' : '&') + 'exclude=' + encodeURIComponent(excludeId)
			: base;

		fetch(url, {
			method: 'GET',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				requesttoken: token,
				Accept: 'application/json'
			},
			credentials: 'same-origin'
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!data || !data.success || !Array.isArray(data.customers)) {
					return;
				}
				while (select.firstChild) {
					select.removeChild(select.firstChild);
				}
				const empty = document.createElement('option');
				empty.value = '';
				empty.appendChild(textNode(t('projectcheck', 'Select customer...')));
				select.appendChild(empty);
				data.customers.forEach(function (c) {
					if (!c || c.id == null) {
						return;
					}
					const opt = document.createElement('option');
					opt.value = String(c.id);
					const label = c.name && String(c.name) !== '' ? c.name : ('#' + c.id);
					opt.appendChild(textNode(label));
					select.appendChild(opt);
				});
			})
			.catch(function (err) {
				console.error('loadCustomerOptions', err);
			});
	}

	function performDeletion() {
		if (!currentEntity || !currentModal) {
			return;
		}

		const deleteBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--delete');
		if (deleteBtn) {
			deleteBtn.disabled = true;
			deleteBtn.textContent = t('projectcheck', 'Deleting…');
		}

		const isCustomer = currentEntity.type === 'customer';
		const requestToken = getRequestToken();
		let deleteUrl = normalizeDeleteUrl(currentEntity.type, currentEntity.deleteUrl);
		deleteUrl = resolvePlaceholderInUrl(deleteUrl, currentEntity.id);
		const formData = new FormData();
		formData.append('requesttoken', requestToken);

		if (isCustomer) {
			const strategyInput = currentModal.querySelector('input[name="deletion-strategy"]:checked');
			const strategyValue = strategyInput ? strategyInput.value : 'restrict';
			const impact = currentEntity.impact || null;

			if (strategyValue === 'restrict' && hasDependencies(impact)) {
				showErrorMessage(t('projectcheck', 'This customer has associated projects. Choose Cascade or Reassign to continue.'));
				resetDeleteButton();
				return;
			}

			if (strategyValue === 'reassign') {
				const reassignCustomer = currentModal.querySelector('#reassign-customer');
				if (!reassignCustomer || !reassignCustomer.value) {
					showErrorMessage(t('projectcheck', 'Select a customer to reassign projects to.'));
					resetDeleteButton();
					return;
				}
				formData.append('reassign_customer_id', reassignCustomer.value);
			}

			formData.append('strategy', strategyValue);
		}

		const fetchOptions = {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				requesttoken: requestToken
			},
			credentials: 'same-origin'
		};

		fetch(deleteUrl, fetchOptions)
			.then(function (response) {
				return parseJsonResponse(response).then(function (data) {
					return { response: response, data: data };
				});
			})
			.then(function (result) {
				const response = result.response;
				const data = result.data;
				if (!response.ok) {
					showErrorMessage(data.error || data.message || t('projectcheck', 'Failed to delete item'));
					resetDeleteButton();
					return;
				}
				if (data.success) {
					const onSuccessCallback = currentCallbacks.onSuccess;
					const deletedEntity = currentEntity ? Object.assign({}, currentEntity) : null;
					closeDeletionModal();
					showSuccessMessage(data.message || t('projectcheck', 'Item deleted successfully'));
					if (typeof onSuccessCallback === 'function') {
						onSuccessCallback(deletedEntity);
					} else {
						window.location.reload();
					}
					return;
				}
				showErrorMessage(data.error || data.message || t('projectcheck', 'Failed to delete item'));
				resetDeleteButton();
			})
			.catch(function (error) {
				console.error('Deletion error:', error);
				showErrorMessage(t('projectcheck', 'An error occurred while deleting the item'));
				resetDeleteButton();
			});
	}

	function resetDeleteButton() {
		if (!currentModal) {
			return;
		}
		const deleteBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--delete');
		if (deleteBtn) {
			deleteBtn.disabled = false;
			deleteBtn.textContent = t('projectcheck', 'Delete');
		}
	}

	function displayError(message) {
		if (!currentModal) {
			return;
		}
		const body = currentModal.querySelector('.projectcheck-deletion-modal__body');
		if (!body) {
			return;
		}

		body.replaceChildren();
		const err = createEl('div', 'projectcheck-deletion-modal__error');
		const errTitle = createEl('h3');
		errTitle.appendChild(textNode(t('projectcheck', 'Error')));
		const errMsg = createEl('p');
		errMsg.textContent = message;
		err.appendChild(errTitle);
		err.appendChild(errMsg);
		const actions = createEl('div', 'projectcheck-deletion-modal__actions');
		const closeBtn = createEl('button', 'projectcheck-deletion-modal__btn projectcheck-deletion-modal__btn--cancel');
		closeBtn.type = 'button';
		closeBtn.appendChild(textNode(t('projectcheck', 'Close')));
		closeBtn.addEventListener('click', function () {
			dismissDeletionModal('error');
		});
		actions.appendChild(closeBtn);
		err.appendChild(actions);
		body.appendChild(err);
	}

	function showSuccessMessage(message) {
		if (typeof OC !== 'undefined' && OC.Notification) {
			OC.Notification.showTemporary(message);
		} else {
			alert(message);
		}
	}

	function showErrorMessage(message) {
		if (typeof OC !== 'undefined' && OC.Notification) {
			OC.Notification.showTemporary(message, { type: 'error' });
		} else {
			alert(t('projectcheck', 'Error: %s').replace('%s', String(message)));
		}
	}

	function closeDeletionModal() {
		if (!currentModal) {
			return;
		}

		const modalEl = currentModal;
		const dialogEl = currentDialog;

		currentModal = null;
		currentDialog = null;
		modalA11yEntry = null;
		currentEntity = null;
		currentCallbacks = {
			onSuccess: null,
			onCancel: null
		};

		if (window.ProjectCheckModalA11y && dialogEl) {
			window.ProjectCheckModalA11y.detach(dialogEl, { reason: 'close' });
		}

		modalEl.style.display = 'none';
		document.body.style.overflow = '';
		if (modalEl.parentNode) {
			modalEl.parentNode.removeChild(modalEl);
		}
	}

	/**
	 * Prefer POST + FormData for mutating deletes (Nextcloud CSRF; DELETE + query token is unreliable).
	 *
	 * @param {string} entityType
	 * @param {string} deleteUrl
	 * @returns {string}
	 */
	function normalizeDeleteUrl(entityType, deleteUrl) {
		if (!deleteUrl || typeof deleteUrl !== 'string') {
			return deleteUrl;
		}
		let url = deleteUrl.split('?')[0];
		if ((entityType === 'project' || entityType === 'customer' || entityType === 'time_entry' || entityType === 'file')
			&& url.indexOf('/delete') === -1) {
			url = url.replace(/\/?$/, '/delete');
		}
		if (entityType === 'member' || entityType === 'employee_unassign') {
			if (url.indexOf('/employees/') !== -1 && url.indexOf('/projects/') !== -1 && url.indexOf('/remove') === -1) {
				url = url.replace(/\/?$/, '/remove');
			} else if (url.indexOf('/api/project-members/') !== -1 && url.indexOf('/remove') === -1) {
				url = url.replace(/\/?$/, '/remove');
			} else if (url.indexOf('/members/') !== -1 && url.indexOf('/remove') === -1) {
				url = url.replace(/\/?$/, '/remove');
			}
		}
		return url;
	}

	/**
	 * @param {Response} response
	 * @returns {Promise<{ success?: boolean, error?: string, message?: string }>}
	 */
	function parseJsonResponse(response) {
		return response.text().then(function (text) {
			if (!text) {
				return {};
			}
			try {
				return JSON.parse(text);
			} catch (e) {
				return {};
			}
		});
	}

	function getRequestToken() {
		if (typeof OC !== 'undefined' && OC.requestToken) {
			return OC.requestToken;
		}
		const tokenInput = document.querySelector('input[name="requesttoken"]');
		return tokenInput ? tokenInput.value : '';
	}

	/**
	 * Replace route placeholder tokens left in generated URLs.
	 *
	 * @param {string} url
	 * @param {string|number|null|undefined} entityId
	 * @returns {string}
	 */
	function resolvePlaceholderInUrl(url, entityId) {
		if (!url || entityId == null || entityId === '') {
			return url;
		}
		const id = encodeURIComponent(String(entityId));
		return url
			.split('CUSTOMER_ID').join(id)
			.split('PROJECT_ID').join(id);
	}

	window.projectcheckDeletionModal = {
		show: showDeletionModal,
		close: closeDeletionModal,
		onSuccess: null,
		onCancel: null
	};
})();
