/**
 * Time Entries Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	function normalizeDateToIso(dateString) {
		if (!dateString) {
			return '';
		}
		const s = String(dateString).trim();
		if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
			return s;
		}
		if (/^\d{2}\.\d{2}\.\d{4}$/.test(s)) {
			const parts = s.split('.');
			return `${parts[2]}-${parts[1]}-${parts[0]}`;
		}
		return '';
	}

	const elements = {
		searchInput: document.getElementById('time-entry-search'),
		projectFilter: document.getElementById('project-filter'),
		userFilter: document.getElementById('user-filter'),
		projectTypeFilter: document.getElementById('time-entry-project-type-filter'),
		billingStatusFilter: document.getElementById('billing-status-filter'),
		applyFiltersBtn: document.getElementById('apply-filters'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		timeEntriesTable: document.getElementById('time-entries-table'),
	};

	/** @type {boolean} */

	/** @type {boolean} */
	let deletionModalOpen = false;

	function parseHoursValue(value) {
		const n = parseFloat(value);
		return Number.isFinite(n) ? n : 0;
	}

	function roundHoursValue(value) {
		if (typeof window.ProjectCheckFormat !== 'undefined' && typeof window.ProjectCheckFormat.roundHours === 'function') {
			return window.ProjectCheckFormat.roundHours(value);
		}
		const n = parseHoursValue(value);
		return Math.round(n * 100) / 100;
	}

	function subtractHoursValues(total, amount) {
		if (typeof window.ProjectCheckFormat !== 'undefined' && typeof window.ProjectCheckFormat.subtractHours === 'function') {
			return window.ProjectCheckFormat.subtractHours(total, amount);
		}
		return roundHoursValue(Math.max(0, parseHoursValue(total) - parseHoursValue(amount)));
	}

	function formatHoursDisplay(hours) {
		if (typeof window.ProjectCheckFormat !== 'undefined' && typeof window.ProjectCheckFormat.hours === 'function') {
			return window.ProjectCheckFormat.hours(hours);
		}
		return parseHoursValue(hours).toFixed(2) + '\u00A0h';
	}

	function formatMatchingEntryCount(count) {
		const safeCount = Math.max(0, parseInt(String(count), 10) || 0);
		return n('projectcheck', '%n matching entry', '%n matching entries', safeCount);
	}

	function init() {
		bindEvents();
		initMessageAutoHide();
		hydrateRowActionIcons();
		initSettlement();
	}

	// ---------------------------------------------------------------
	// Settlement bulk actions (feature spec §12.2)
	// ---------------------------------------------------------------

	/** Mirror of BillingStatus::TRANSITIONS — display logic only; the server
	 *  re-validates every transition. */
	const BILLING_TRANSITIONS = {
		open: ['invoiced', 'excluded'],
		invoiced: ['paid', 'open'],
		paid: ['invoiced'],
		excluded: ['open'],
	};

	const BILLING_TARGET_LABELS = {
		open: t('projectcheck', 'Reopen'),
		invoiced: t('projectcheck', 'Mark invoiced'),
		paid: t('projectcheck', 'Mark paid'),
		excluded: t('projectcheck', 'Mark not billable'),
	};

	let settlementModalOpen = false;

	function initSettlement() {
		const bar = document.getElementById('pc-billing-bar');
		const table = getTimeEntriesTable();
		if (!bar || !table) {
			return;
		}

		const selectAll = document.getElementById('pc-billing-select-all');
		if (selectAll) {
			selectAll.addEventListener('change', function () {
				getSelectableCheckboxes().forEach(function (box) {
					box.checked = selectAll.checked;
				});
				updateBillingBar();
			});
		}

		table.addEventListener('change', function (e) {
			if (e.target && e.target.classList.contains('pc-billing-select')) {
				updateBillingBar();
			}
		});

		bar.querySelectorAll('.pc-billing-action').forEach(function (button) {
			button.addEventListener('click', function () {
				const target = button.getAttribute('data-billing-target');
				openSettlementConfirm(target, button);
			});
		});

		bar.querySelectorAll('.pc-billing-filter-action').forEach(function (button) {
			button.addEventListener('click', function () {
				const target = button.getAttribute('data-billing-target');
				const source = button.getAttribute('data-billing-source');
				openFilterModeConfirm(source, target, button);
			});
		});

		updateBillingBar();
	}

	function getSelectableCheckboxes() {
		const table = getTimeEntriesTable();
		return table ? Array.prototype.slice.call(table.querySelectorAll('.pc-billing-select')) : [];
	}

	function getSelectedEntries() {
		return getSelectableCheckboxes()
			.filter(function (box) { return box.checked; })
			.map(function (box) {
				const row = box.closest('tr[data-entry-id]');
				return {
					id: parseInt(box.value, 10),
					status: row ? String(row.getAttribute('data-billing-status') || 'open') : 'open',
					hours: row ? parseHoursValue(row.getAttribute('data-entry-hours')) : 0,
				};
			})
			.filter(function (item) { return Number.isFinite(item.id) && item.id > 0; });
	}

	function countEligible(selected, target) {
		return selected.filter(function (item) {
			return (BILLING_TRANSITIONS[item.status] || []).indexOf(target) !== -1;
		}).length;
	}

	function updateBillingBar() {
		const bar = document.getElementById('pc-billing-bar');
		if (!bar) {
			return;
		}
		const selected = getSelectedEntries();
		const countEl = document.getElementById('pc-billing-bar-count');
		if (countEl) {
			countEl.textContent = selected.length === 0
				? t('projectcheck', 'No entries selected')
				: n('projectcheck', '%n entry selected', '%n entries selected', selected.length);
		}

		bar.querySelectorAll('.pc-billing-action').forEach(function (button) {
			const target = button.getAttribute('data-billing-target');
			button.disabled = selected.length === 0 || countEligible(selected, target) === 0;
		});

		const selectAll = document.getElementById('pc-billing-select-all');
		if (selectAll) {
			const boxes = getSelectableCheckboxes();
			selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
			selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
		}
	}

	function openSettlementConfirm(target, triggerButton) {
		if (settlementModalOpen || !BILLING_TARGET_LABELS[target]) {
			return;
		}
		const selected = getSelectedEntries();
		if (selected.length === 0) {
			return;
		}
		const eligible = countEligible(selected, target);
		if (eligible === 0) {
			showMessage(t('projectcheck', 'None of the selected entries can change to this status.'), 'error');
			return;
		}

		const skipped = selected.length - eligible;
		const totalHours = selected
			.filter(function (item) { return (BILLING_TRANSITIONS[item.status] || []).indexOf(target) !== -1; })
			.reduce(function (sum, item) { return sum + item.hours; }, 0);

		let message = n(
			'projectcheck',
			'Change %n entry to "{target}"?',
			'Change %n entries to "{target}"?',
			eligible,
			{ target: BILLING_TARGET_LABELS[target] }
		);
		message += ' ' + t('projectcheck', 'Total: {hours}', { hours: formatHoursDisplay(roundHoursValue(totalHours)) });
		if (skipped > 0) {
			message += ' ' + n(
				'projectcheck',
				'%n selected entry will be skipped because this change is not allowed from its current status.',
				'%n selected entries will be skipped because this change is not allowed from their current status.',
				skipped
			);
		}

		showSettlementModal({
			title: BILLING_TARGET_LABELS[target],
			message: message,
			confirmLabel: BILLING_TARGET_LABELS[target],
			onConfirm: function (done) {
				submitBulkBilling(selected.map(function (item) { return item.id; }), target, done);
			},
			trigger: triggerButton,
		});
	}

	function submitBulkBilling(ids, target, done) {
		const table = getTimeEntriesTable();
		const bar = document.getElementById('pc-billing-bar');
		const url = (table && table.getAttribute('data-billing-bulk-url'))
			|| (bar && bar.getAttribute('data-billing-bulk-url'))
			|| '';
		if (!url) {
			done();
			showMessage(t('projectcheck', 'The settlement action failed. Please try again.'), 'error');
			return;
		}

		fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: getRequestTokenValue(),
			},
			body: JSON.stringify({ ids: ids, target: target }),
		})
			.then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (payload) {
					return { ok: response.ok, payload: payload };
				});
			})
			.then(function (result) {
				done();
				if (result.ok && result.payload && result.payload.success) {
					// Server-rendered strips/chips must refresh: reload keeps every
					// number (buckets, counters, chips) consistent in one step.
					sessionStorage.setItem('pcSettlementMessage', result.payload.message || '');
					window.location.reload();
					return;
				}
				const errorMessage = (result.payload && result.payload.error)
					|| t('projectcheck', 'The settlement action failed. Please try again.');
				showMessage(errorMessage, 'error');
			})
			.catch(function () {
				done();
				showMessage(t('projectcheck', 'The settlement action failed. Please try again.'), 'error');
			});
	}

	/**
	 * Collect the same filters the list page uses so filter-mode settle
	 * applies to exactly what the settler sees (all pages, capped at 500).
	 */
	function collectListFilters(sourceStatus) {
		const filters = { billing_status: sourceStatus };
		const projectFilter = elements.projectFilter ? elements.projectFilter.value : '';
		const userFilter = elements.userFilter ? elements.userFilter.value : '';
		const projectTypeFilter = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';
		const searchTerm = elements.searchInput ? elements.searchInput.value.trim() : '';
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');
		let dateFrom = dateFromInput ? dateFromInput.value.trim() : '';
		let dateTo = dateToInput ? dateToInput.value.trim() : '';
		if (dateFrom) {
			dateFrom = normalizeDateToIso(dateFrom);
		}
		if (dateTo) {
			dateTo = normalizeDateToIso(dateTo);
		}
		if (projectFilter) {
			filters.project_id = parseInt(projectFilter, 10) || projectFilter;
		}
		if (userFilter) {
			filters.user_id = userFilter;
		}
		if (projectTypeFilter) {
			filters.project_type = projectTypeFilter;
		}
		if (dateFrom) {
			filters.date_from = dateFrom;
		}
		if (dateTo) {
			filters.date_to = dateTo;
		}
		if (searchTerm) {
			filters.search = searchTerm;
		}
		return filters;
	}

	function getBillingEndpoints() {
		const table = getTimeEntriesTable();
		const bar = document.getElementById('pc-billing-bar');
		return {
			preview: (table && table.getAttribute('data-billing-preview-url'))
				|| (bar && bar.getAttribute('data-billing-preview-url'))
				|| '',
			bulk: (table && table.getAttribute('data-billing-bulk-url'))
				|| (bar && bar.getAttribute('data-billing-bulk-url'))
				|| '',
		};
	}

	/**
	 * Filter-mode settle (spec D11 / §12.2): preview token → confirm → apply.
	 * Only offered when Settlement filter is an exact status (server requires that).
	 */
	function openFilterModeConfirm(source, target, triggerButton) {
		if (settlementModalOpen || !BILLING_TARGET_LABELS[target] || !source) {
			return;
		}
		if ((BILLING_TRANSITIONS[source] || []).indexOf(target) === -1) {
			showMessage(t('projectcheck', 'This status change is not allowed.'), 'error');
			return;
		}

		const endpoints = getBillingEndpoints();
		if (!endpoints.preview || !endpoints.bulk) {
			showMessage(t('projectcheck', 'The settlement action failed. Please try again.'), 'error');
			return;
		}

		const filters = collectListFilters(source);
		if (triggerButton) {
			triggerButton.disabled = true;
		}

		fetch(endpoints.preview, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: getRequestTokenValue(),
			},
			body: JSON.stringify({ filters: filters, target: target }),
		})
			.then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (payload) {
					return { ok: response.ok, payload: payload };
				});
			})
			.then(function (result) {
				if (triggerButton) {
					triggerButton.disabled = false;
				}
				if (!result.ok || !result.payload || result.payload.success !== true) {
					showMessage(
						(result.payload && result.payload.error)
							|| t('projectcheck', 'Could not load the preview. Please try again.'),
						'error'
					);
					return;
				}
				if (result.payload.capExceeded) {
					showMessage(
						t('projectcheck', 'Too many entries at once (more than {cap}). Narrow the date range and repeat the action for the rest.', {
							cap: String(result.payload.cap || 500),
						}),
						'error'
					);
					return;
				}
				const count = Number(result.payload.count || 0);
				if (count <= 0) {
					showMessage(t('projectcheck', 'No matching hours found — there is nothing to change.'), 'error');
					return;
				}
				const token = result.payload.token;
				if (!token) {
					showMessage(t('projectcheck', 'Could not load the preview. Please try again.'), 'error');
					return;
				}

				const hours = formatHoursDisplay(roundHoursValue(Number(result.payload.hours || 0)));
				const amount = Number(result.payload.amount || 0).toFixed(2);
				let message = n(
					'projectcheck',
					'Change %n matching entry to "{target}"?',
					'Change %n matching entries to "{target}"?',
					count,
					{ target: BILLING_TARGET_LABELS[target] }
				);
				message += ' ' + t('projectcheck', 'Total: {hours} · {amount}', {
					hours: hours,
					amount: amount,
				});
				message += ' ' + t('projectcheck', 'This applies to every entry matching the current filters (all pages), not only this page.');

				showSettlementModal({
					title: BILLING_TARGET_LABELS[target],
					message: message,
					confirmLabel: BILLING_TARGET_LABELS[target],
					onConfirm: function (done) {
						submitFilterModeBilling(filters, target, token, done);
					},
					trigger: triggerButton,
				});
			})
			.catch(function () {
				if (triggerButton) {
					triggerButton.disabled = false;
				}
				showMessage(t('projectcheck', 'Could not load the preview. Please try again.'), 'error');
			});
	}

	function submitFilterModeBilling(filters, target, token, done) {
		const endpoints = getBillingEndpoints();
		if (!endpoints.bulk) {
			done();
			showMessage(t('projectcheck', 'The settlement action failed. Please try again.'), 'error');
			return;
		}

		fetch(endpoints.bulk, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: getRequestTokenValue(),
			},
			body: JSON.stringify({ filters: filters, target: target, token: token }),
		})
			.then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (payload) {
					return { ok: response.ok, status: response.status, payload: payload };
				});
			})
			.then(function (result) {
				done();
				if (result.ok && result.payload && result.payload.success) {
					sessionStorage.setItem('pcSettlementMessage', result.payload.message || '');
					window.location.reload();
					return;
				}
				if (result.status === 409) {
					showMessage(
						(result.payload && result.payload.error)
							|| t('projectcheck', 'The entries changed since the preview. Review the numbers and confirm again.'),
						'error'
					);
					return;
				}
				showMessage(
					(result.payload && result.payload.error)
						|| t('projectcheck', 'The settlement action failed. Please try again.'),
					'error'
				);
			})
			.catch(function () {
				done();
				showMessage(t('projectcheck', 'The settlement action failed. Please try again.'), 'error');
			});
	}

	function getRequestTokenValue() {
		if (typeof OC !== 'undefined' && OC.requestToken) {
			return OC.requestToken;
		}
		const tokenInput = document.querySelector('input[name="requesttoken"]');
		return tokenInput ? tokenInput.value : '';
	}

	/**
	 * Minimal accessible confirm dialog for settlement actions: role=dialog,
	 * focus handled by ProjectCheckModalA11y when available, Escape closes.
	 */
	function showSettlementModal(options) {
		settlementModalOpen = true;
		if (options.trigger) {
			options.trigger.disabled = true;
		}

		const root = document.createElement('div');
		root.className = 'pc-settle-modal';
		root.style.display = 'flex';

		const backdrop = document.createElement('div');
		backdrop.className = 'pc-settle-modal__backdrop';

		const dialog = document.createElement('div');
		dialog.className = 'pc-settle-modal__dialog';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', 'pc-settle-modal-title');
		dialog.setAttribute('aria-describedby', 'pc-settle-modal-message');

		const title = document.createElement('h2');
		title.id = 'pc-settle-modal-title';
		title.className = 'pc-settle-modal__title';
		title.textContent = options.title;

		const message = document.createElement('p');
		message.id = 'pc-settle-modal-message';
		message.className = 'pc-settle-modal__message';
		message.textContent = options.message;

		const actions = document.createElement('div');
		actions.className = 'pc-settle-modal__actions';

		const cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button secondary';
		cancelBtn.textContent = t('projectcheck', 'Cancel');

		const confirmBtn = document.createElement('button');
		confirmBtn.type = 'button';
		confirmBtn.className = 'button primary';
		confirmBtn.textContent = options.confirmLabel;

		actions.appendChild(cancelBtn);
		actions.appendChild(confirmBtn);
		dialog.appendChild(title);
		dialog.appendChild(message);
		dialog.appendChild(actions);
		root.appendChild(backdrop);
		root.appendChild(dialog);
		document.body.appendChild(root);
		document.body.style.overflow = 'hidden';

		let a11yAttached = false;
		let closed = false;

		function closeModal() {
			if (closed) {
				return;
			}
			closed = true;
			settlementModalOpen = false;
			if (a11yAttached && window.ProjectCheckModalA11y && typeof window.ProjectCheckModalA11y.detach === 'function') {
				window.ProjectCheckModalA11y.detach(dialog);
			}
			root.remove();
			document.body.style.overflow = '';
			if (options.trigger) {
				options.trigger.disabled = false;
				options.trigger.focus();
			}
		}

		if (window.ProjectCheckModalA11y && typeof window.ProjectCheckModalA11y.attach === 'function') {
			a11yAttached = window.ProjectCheckModalA11y.attach(dialog, {
				dismissOnEscape: true,
				dismissOnBackdrop: false,
				initialFocus: cancelBtn,
				onDismiss: closeModal,
			}) !== null;
		} else {
			cancelBtn.focus();
			dialog.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					closeModal();
				}
			});
		}

		backdrop.addEventListener('click', closeModal);
		cancelBtn.addEventListener('click', closeModal);
		confirmBtn.addEventListener('click', function () {
			confirmBtn.disabled = true;
			cancelBtn.disabled = true;
			options.onConfirm(function () {
				closeModal();
			});
		});
	}

	function showStoredSettlementMessage() {
		let stored = '';
		try {
			stored = sessionStorage.getItem('pcSettlementMessage') || '';
			sessionStorage.removeItem('pcSettlementMessage');
		} catch (e) {
			stored = '';
		}
		if (stored) {
			showMessage(stored, 'success');
		}
	}

	function hydrateRowActionIcons() {
		if (window.ProjectCheckIcons && typeof window.ProjectCheckIcons.hydrate === 'function') {
			const table = elements.timeEntriesTable;
			if (table) {
				window.ProjectCheckIcons.hydrate(table);
			}
		}
	}

	function bindEvents() {
		if (elements.applyFiltersBtn) {
			elements.applyFiltersBtn.addEventListener('click', applyFilters);
		}

		if (elements.searchInput) {
			elements.searchInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					applyFilters();
				}
			});
		}

		if (elements.clearFiltersBtn) {
			elements.clearFiltersBtn.addEventListener('click', clearFilters);
		}


		document.addEventListener('click', function (e) {
			if (!e.target.closest('.delete-entry-btn')) {
				return;
			}
			if (deletionModalOpen) {
				return;
			}
			const button = e.target.closest('.delete-entry-btn');
			if (button.disabled) {
				return;
			}
			const entryId = button.getAttribute('data-entry-id');
			const entryDescription = button.getAttribute('data-entry-description');
			const deleteUrl = button.getAttribute('data-delete-url');
			showTimeEntryDeletionModal(entryId, entryDescription, deleteUrl, button);
		});
	}

	function applyFilters() {
		const searchTerm = elements.searchInput ? elements.searchInput.value : '';
		const projectFilter = elements.projectFilter ? elements.projectFilter.value : '';
		const userFilter = elements.userFilter ? elements.userFilter.value : '';
		const projectTypeFilter = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');
		let dateFrom = dateFromInput ? dateFromInput.value.trim() : '';
		let dateTo = dateToInput ? dateToInput.value.trim() : '';

		if (dateFrom) {
			dateFrom = normalizeDateToIso(dateFrom);
		}
		if (dateTo) {
			dateTo = normalizeDateToIso(dateTo);
		}

		if (dateFrom && dateTo && dateFrom > dateTo) {
			showMessage(t('projectcheck', 'The start date must be on or before the end date.'), 'error');
			return;
		}

		const billingStatusFilter = elements.billingStatusFilter ? elements.billingStatusFilter.value : '';

		const url = new URL(window.location.href);
		searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
		projectFilter ? url.searchParams.set('project_id', projectFilter) : url.searchParams.delete('project_id');
		userFilter ? url.searchParams.set('user_id', userFilter) : url.searchParams.delete('user_id');
		projectTypeFilter ? url.searchParams.set('project_type', projectTypeFilter) : url.searchParams.delete('project_type');
		dateFrom ? url.searchParams.set('date_from', dateFrom) : url.searchParams.delete('date_from');
		dateTo ? url.searchParams.set('date_to', dateTo) : url.searchParams.delete('date_to');
		billingStatusFilter ? url.searchParams.set('billing_status', billingStatusFilter) : url.searchParams.delete('billing_status');
		url.searchParams.set('page', '1');
		window.location.href = url.toString();
	}

	function clearFilters() {
		const url = new URL(window.location.href);
		url.searchParams.delete('search');
		url.searchParams.delete('project_id');
		url.searchParams.delete('user_id');
		url.searchParams.delete('project_type');
		url.searchParams.delete('date_from');
		url.searchParams.delete('date_to');
		url.searchParams.delete('billing_status');
		url.searchParams.set('page', '1');
		window.location.href = url.toString();
	}

	function updateEmptyState() {
		const table = getTimeEntriesTable();
		if (!table) {
			return;
		}
		const remaining = table.querySelectorAll('tbody tr[data-entry-id]').length;
		if (remaining === 0) {
			window.location.reload();
		}
	}

	function getTimeEntriesTable() {
		return document.getElementById('time-entries-table') || elements.timeEntriesTable;
	}

	function updateHoursSummaryAfterDelete(deletedHours) {
		const table = getTimeEntriesTable();
		if (!table || deletedHours <= 0) {
			return;
		}

		const selectionHours = subtractHoursValues(
			table.getAttribute('data-selection-hours'),
			deletedHours
		);
		const pageHours = subtractHoursValues(
			table.getAttribute('data-page-hours'),
			deletedHours
		);
		const selectionCount = Math.max(0, (parseInt(table.getAttribute('data-selection-count'), 10) || 0) - 1);

		table.setAttribute('data-selection-hours', String(selectionHours));
		table.setAttribute('data-page-hours', String(pageHours));
		table.setAttribute('data-selection-count', String(selectionCount));
		table.setAttribute('data-page-count', String(Math.max(0, (parseInt(table.getAttribute('data-page-count'), 10) || 0) - 1)));

		const selectionHoursEl = document.getElementById('time-entries-selection-hours');
		const pageHoursEl = document.getElementById('time-entries-page-hours');
		const countEl = document.getElementById('time-entries-selection-count');
		const liveEl = document.getElementById('time-entries-summary-live');

		if (selectionHoursEl) {
			selectionHoursEl.textContent = formatHoursDisplay(selectionHours);
		}
		if (pageHoursEl) {
			pageHoursEl.textContent = formatHoursDisplay(pageHours);
		}
		if (countEl) {
			countEl.textContent = formatMatchingEntryCount(selectionCount);
		}
		if (liveEl) {
			liveEl.textContent = t('projectcheck', 'Total hours (matching filters)') + ': ' + formatHoursDisplay(selectionHours);
		}
	}

	function showTimeEntryDeletionModal(entryId, entryDescription, deleteUrl, triggerButton) {
		if (typeof window.projectcheckDeletionModal === 'undefined') {
			showMessage(t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'), 'error');
			return;
		}

		if (deletionModalOpen) {
			return;
		}
		deletionModalOpen = true;
		if (triggerButton) {
			triggerButton.disabled = true;
		}

		const resolvedDeleteUrl = deleteUrl
			|| OC.generateUrl('/apps/projectcheck/time-entries/{id}/delete', { id: entryId });
		const confirmMessage = t('projectcheck', 'Are you sure you want to delete this time entry? This action cannot be undone.');
		const entityLabel = (entryDescription && String(entryDescription).trim() !== '')
			? String(entryDescription).trim()
			: t('projectcheck', 'Time entry');

		function releaseDeletionTrigger() {
			deletionModalOpen = false;
			if (triggerButton) {
				triggerButton.disabled = false;
			}
		}

		window.projectcheckDeletionModal.show({
			entityType: 'time_entry',
			entityId: entryId,
			entityName: entityLabel,
			deleteUrl: resolvedDeleteUrl,
			simpleConfirm: true,
			confirmMessage: confirmMessage,
			onSuccess: function (entity) {
				releaseDeletionTrigger();
				try {
					const entryId = entity && entity.id != null ? String(entity.id) : '';
					const row = entryId
						? document.querySelector('tr[data-entry-id="' + entryId + '"]')
						: null;
					if (row) {
						const deletedHours = parseHoursValue(row.getAttribute('data-entry-hours'));
						row.remove();
						updateHoursSummaryAfterDelete(deletedHours);
						updateEmptyState();
					}
					showMessage(t('projectcheck', 'Time entry was deleted successfully!'), 'success');
				} catch (uiError) {
					console.error('Time entry delete UI update failed:', uiError);
					showMessage(t('projectcheck', 'Time entry was deleted successfully!'), 'success');
					window.setTimeout(function () {
						window.location.reload();
					}, 600);
				}
			},
			onCancel: function () {
				releaseDeletionTrigger();
			},
			onRelease: releaseDeletionTrigger
		});
	}

	function showMessage(message, type) {
		const level = type || 'info';
		const existingMessages = document.querySelectorAll('.pc-page-notice, .notice');
		existingMessages.forEach(msg => msg.remove());

		const messageDiv = document.createElement('div');
		messageDiv.className = 'notice notice-' + level + ' pc-page-notice';
		messageDiv.setAttribute('role', level === 'error' ? 'alert' : 'status');
		messageDiv.setAttribute('aria-live', level === 'error' ? 'assertive' : 'polite');
		messageDiv.setAttribute('aria-atomic', 'true');

		const icon = document.createElement('span');
		icon.className = 'lucide-icon';
		icon.setAttribute('aria-hidden', 'true');
		const Icons = window.ProjectCheckIcons;
		const iconName = Icons && typeof Icons.forStatus === 'function'
			? Icons.forStatus(level)
			: (level === 'success' ? 'circle-check' : (level === 'error' ? 'alert-circle' : 'info'));
		if (Icons && typeof Icons.mount === 'function') {
			Icons.mount(icon, iconName);
		} else {
			icon.setAttribute('data-lucide', iconName);
		}
		messageDiv.appendChild(icon);

		const messageSpan = document.createElement('span');
		messageSpan.textContent = message;
		messageDiv.appendChild(messageSpan);

		const main = document.getElementById('pc-main-content') || document.querySelector('.pc-main');
		if (main) {
			main.insertBefore(messageDiv, main.firstChild);
		} else {
			const pageHeader = document.querySelector('.pc-page-header');
			if (pageHeader && pageHeader.parentNode) {
				pageHeader.parentNode.insertBefore(messageDiv, pageHeader.nextSibling);
			} else {
				document.body.insertBefore(messageDiv, document.body.firstChild);
			}
		}

		const liveRegion = document.getElementById('pc-live-region');
		if (liveRegion) {
			liveRegion.textContent = message;
		}

		requestAnimationFrame(function () {
			messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		});

		const hideDelay = level === 'info' ? 3000 : 5000;
		setTimeout(() => {
			if (messageDiv.parentNode) {
				messageDiv.remove();
			}
		}, hideDelay);
	}

	function initMessageAutoHide() {
		const messages = document.querySelectorAll('.notice');
		messages.forEach(message => {
			setTimeout(() => {
				if (message.parentNode) {
					message.remove();
				}
			}, 5000);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			init();
			showStoredSettlementMessage();
		});
	} else {
		init();
		showStoredSettlementMessage();
	}

})();
