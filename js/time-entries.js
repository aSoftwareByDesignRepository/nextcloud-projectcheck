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
		applyFiltersBtn: document.getElementById('apply-filters'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		exportCsvBtn: document.getElementById('export-csv'),
		timeEntriesTable: document.getElementById('time-entries-table'),
	};

	/** @type {boolean} */
	let exportInFlight = false;

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

		if (elements.exportCsvBtn) {
			elements.exportCsvBtn.addEventListener('click', exportToCsv);
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

		const url = new URL(window.location.href);
		searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
		projectFilter ? url.searchParams.set('project_id', projectFilter) : url.searchParams.delete('project_id');
		userFilter ? url.searchParams.set('user_id', userFilter) : url.searchParams.delete('user_id');
		projectTypeFilter ? url.searchParams.set('project_type', projectTypeFilter) : url.searchParams.delete('project_type');
		dateFrom ? url.searchParams.set('date_from', dateFrom) : url.searchParams.delete('date_from');
		dateTo ? url.searchParams.set('date_to', dateTo) : url.searchParams.delete('date_to');
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

		let iconClass = 'icon-info';
		if (level === 'success') {
			iconClass = 'icon-checkmark';
		} else if (level === 'error') {
			iconClass = 'icon-error';
		}

		const icon = document.createElement('i');
		icon.className = 'icon ' + iconClass;
		icon.setAttribute('aria-hidden', 'true');
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

	function setExportButtonBusy(exportBtn, busy) {
		if (!exportBtn) {
			return;
		}
		exportBtn.disabled = busy;
		exportBtn.setAttribute('aria-busy', busy ? 'true' : 'false');
		const label = exportBtn.querySelector('.export-csv__label');
		if (label) {
			label.textContent = busy
				? t('projectcheck', 'Exporting…')
				: t('projectcheck', 'Export');
		}
	}

	function exportToCsv() {
		if (exportInFlight) {
			return;
		}
		exportInFlight = true;

		const searchTerm = elements.searchInput ? elements.searchInput.value.trim() : '';
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
			exportInFlight = false;
			return;
		}

		const exportUrl = OC.generateUrl('/apps/projectcheck/time-entries/export');
		const url = new URL(exportUrl, window.location.origin);

		if (searchTerm) url.searchParams.set('search', searchTerm);
		if (projectFilter) url.searchParams.set('project_id', projectFilter);
		if (userFilter) url.searchParams.set('user_id', userFilter);
		if (projectTypeFilter) url.searchParams.set('project_type', projectTypeFilter);
		if (dateFrom) url.searchParams.set('date_from', dateFrom);
		if (dateTo) url.searchParams.set('date_to', dateTo);

		const exportBtn = elements.exportCsvBtn;
		setExportButtonBusy(exportBtn, true);

		fetch(url.toString(), {
			method: 'GET',
			headers: {
				'requesttoken': OC.requestToken
			}
		})
			.then(response => {
				if (!response.ok) {
					return response.json().then(data => {
						throw new Error(data.error != null && data.error !== ''
							? data.error
							: t('projectcheck', 'Export failed'));
					});
				}
				return response.json();
			})
			.then(data => {
				if (data.error) {
					throw new Error(data.error);
				}

				const bom = '\uFEFF';
				const blob = new Blob([bom + data.csv_data], { type: 'text/csv;charset=utf-8;' });

				const link = document.createElement('a');
				const downloadUrl = URL.createObjectURL(blob);
				const filename = data.filename || 'time_entries_' + new Date().toISOString().slice(0, 10) + '.csv';

				link.setAttribute('href', downloadUrl);
				link.setAttribute('download', filename);
				link.style.visibility = 'hidden';
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);

				URL.revokeObjectURL(downloadUrl);

				const entryCount = (data.csv_data.match(/\n/g) || []).length;
				showMessage(t('projectcheck', 'Exported') + ' ' + entryCount + ' ' + t('projectcheck', 'entries'), 'success');
			})
			.catch(error => {
				console.error('Export error:', error);
				showMessage(t('projectcheck', 'Export failed:') + ' ' + error.message, 'error');
			})
			.finally(() => {
				exportInFlight = false;
				setExportButtonBusy(exportBtn, false);
			});
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
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
