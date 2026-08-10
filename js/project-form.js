/**
 * Project form: native date inputs + inline customer quick-add (stay on page).
 *
 * UX contract (Bachus):
 * - Exactly one primary finish action: #pc-project-save.
 * - “Add to list” is secondary — it only puts the customer in the dropdown.
 * - After quick-add, pulse + focus footer Save (one finish action; Go to Save remains a nearby helper).
 */
(function () {
	'use strict';

	function tPc(msg, vars) {
		if (typeof t === 'function') {
			return vars ? t('projectcheck', msg, vars) : t('projectcheck', msg);
		}
		if (vars && typeof vars === 'object') {
			return String(msg).replace(/\{(\w+)\}/g, function (_, key) {
				return Object.prototype.hasOwnProperty.call(vars, key) ? String(vars[key]) : '{' + key + '}';
			});
		}
		return msg;
	}

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

	function parseIsoDateLocal(iso) {
		const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
		if (!m) {
			return null;
		}
		const year = parseInt(m[1], 10);
		const month = parseInt(m[2], 10);
		const day = parseInt(m[3], 10);
		const date = new Date(year, month - 1, day);
		if (
			isNaN(date.getTime())
			|| date.getDate() !== day
			|| date.getMonth() !== month - 1
			|| date.getFullYear() !== year
		) {
			return null;
		}
		return date;
	}

	function validateDateRange(startInput, endInput) {
		if (!startInput || !endInput) {
			return true;
		}
		const startIso = normalizeDateToIso(startInput.value);
		const endIso = normalizeDateToIso(endInput.value);
		if (!startIso || !endIso) {
			endInput.setCustomValidity('');
			return true;
		}
		const start = parseIsoDateLocal(startIso);
		const end = parseIsoDateLocal(endIso);
		if (!start || !end) {
			endInput.setCustomValidity('');
			return true;
		}
		if (end < start) {
			endInput.setCustomValidity(tPc('End date must be on or after the start date.'));
			return false;
		}
		endInput.setCustomValidity('');
		return true;
	}

	function setQuickCustomerStatus(el, message, isError) {
		if (!el) {
			return;
		}
		el.textContent = message || '';
		el.classList.toggle('is-error', !!isError);
		el.classList.toggle('is-success', !!message && !isError);
	}

	function announce(message) {
		const live = document.getElementById('pc-live-region');
		if (live) {
			live.textContent = '';
			window.setTimeout(function () {
				live.textContent = message;
			}, 20);
		}
		if (window.ProjectCheckNotify && typeof window.ProjectCheckNotify.show === 'function') {
			window.ProjectCheckNotify.show(message, 'success');
		}
	}

	function selectCustomerOption(select, customer) {
		const id = String(customer.id);
		const name = String(customer.name || '');
		let option = null;
		for (let i = 0; i < select.options.length; i++) {
			if (select.options[i].value === id) {
				option = select.options[i];
				break;
			}
		}
		if (!option) {
			option = document.createElement('option');
			option.value = id;
			option.textContent = name;
			select.appendChild(option);
		} else {
			option.textContent = name;
		}
		select.value = id;
		select.classList.add('pc-customer-selected');
		select.dispatchEvent(new Event('change', { bubbles: true }));
	}

	/**
	 * Readonly capacity is display-only (no name attribute). Keep a numeric
	 * value in the DOM for a11y; never leave "".
	 */
	function normalizeCapacityFieldsForSubmit(form) {
		if (!form) {
			return;
		}
		['total_budget', 'hourly_rate'].forEach(function (name) {
			const el = form.elements.namedItem(name);
			if (!el || typeof el.value !== 'string') {
				return;
			}
			if (String(el.value).trim() === '') {
				el.value = '0';
			}
		});
		const hours = document.getElementById('available_hours');
		if (hours && typeof hours.value === 'string' && String(hours.value).trim() === '') {
			hours.value = '0';
		}
	}

	/** If short description is blank, copy the project name (server mirrors this). */
	function fillShortDescriptionFromName(form) {
		if (!form) {
			return;
		}
		const nameEl = form.elements.namedItem('name');
		const shortEl = form.elements.namedItem('short_description');
		if (!nameEl || !shortEl || typeof nameEl.value !== 'string' || typeof shortEl.value !== 'string') {
			return;
		}
		if (String(shortEl.value).trim() !== '') {
			return;
		}
		const name = String(nameEl.value).trim();
		if (name !== '') {
			shortEl.value = name.slice(0, 500);
		}
	}

	function focusPrimarySave() {
		const saveBtn = document.getElementById('pc-project-save');
		const actions = document.getElementById('pc-project-form-actions');
		if (actions) {
			actions.classList.add('pc-form-actions--pulse');
			window.setTimeout(function () {
				actions.classList.remove('pc-form-actions--pulse');
			}, 1800);
		}
		if (saveBtn) {
			try {
				saveBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			} catch (e) {
				saveBtn.scrollIntoView(true);
			}
			window.setTimeout(function () {
				saveBtn.focus({ preventScroll: true });
			}, 50);
		}
	}

	function showSaveNextStep(wrap, statusEl, customerName) {
		const next = document.getElementById('pc-quick-customer-next');
		const nextText = document.getElementById('pc-quick-customer-next-text');
		const gotoSave = document.getElementById('pc-quick-customer-goto-save');
		const shortOk = tPc('Added to the list and selected.');
		setQuickCustomerStatus(statusEl, shortOk, false);
		announce(shortOk + ' ' + tPc('Save the project to finish.'));

		if (nextText) {
			const label = customerName
				? tPc('"{name}" is selected. Press Save at the bottom when you are done.', { name: customerName })
				: tPc('Customer is selected. Press Save at the bottom when you are done.');
			nextText.textContent = label;
		}
		if (next) {
			next.hidden = false;
			next.classList.add('is-visible');
			try {
				next.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			} catch (e) {
				next.scrollIntoView(true);
			}
		}
		if (gotoSave && !gotoSave._pcBound) {
			gotoSave._pcBound = true;
			gotoSave.addEventListener('click', function (e) {
				e.preventDefault();
				focusPrimarySave();
			});
		}
		if (wrap) {
			wrap.classList.add('pc-quick-customer--ready');
		}
		// Next logical control is the single primary finish action (Bachus).
		// "Go to Save" stays available if the user scrolled away from the footer.
		focusPrimarySave();
	}

	function hideSaveNextStep(wrap) {
		const next = document.getElementById('pc-quick-customer-next');
		if (next) {
			next.hidden = true;
			next.classList.remove('is-visible');
		}
		if (wrap) {
			wrap.classList.remove('pc-quick-customer--ready');
		}
	}

	function initializeQuickCustomer() {
		const wrap = document.querySelector('.pc-quick-customer');
		const select = document.getElementById('customer_id');
		const nameInput = document.getElementById('pc-quick-customer-name');
		const createBtn = document.getElementById('pc-quick-customer-create');
		const statusEl = document.getElementById('pc-quick-customer-status');
		if (!wrap || !select || !nameInput || !createBtn) {
			return;
		}

		const storeUrl = wrap.getAttribute('data-store-url') || '';
		if (!storeUrl) {
			return;
		}

		let creating = false;

		async function createCustomer() {
			if (creating) {
				return;
			}
			const name = String(nameInput.value || '').trim();
			if (!name) {
				setQuickCustomerStatus(statusEl, tPc('Enter a customer name.'), true);
				hideSaveNextStep(wrap);
				nameInput.focus();
				return;
			}

			creating = true;
			createBtn.disabled = true;
			nameInput.disabled = true;
			setQuickCustomerStatus(statusEl, tPc('Creating customer…'), false);
			hideSaveNextStep(wrap);

			const token = (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '';
			try {
				const response = await fetch(storeUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
						requesttoken: token,
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: new URLSearchParams({ name: name }).toString(),
					credentials: 'same-origin',
				});
				const result = await response.json().catch(function () {
					return {};
				});

				if (!response.ok || !result.success || !result.customer || !result.customer.id) {
					let err = result.error || tPc('Could not create customer. Please check your input.');
					if (result.errors && result.errors.name) {
						err = Array.isArray(result.errors.name) ? result.errors.name.join(' ') : String(result.errors.name);
					}
					setQuickCustomerStatus(statusEl, err, true);
					nameInput.focus();
					return;
				}

				selectCustomerOption(select, result.customer);
				nameInput.value = '';
				showSaveNextStep(wrap, statusEl, String(result.customer.name || name));
			} catch (e) {
				setQuickCustomerStatus(statusEl, tPc('Could not create customer. Please check your input.'), true);
				nameInput.focus();
			} finally {
				creating = false;
				createBtn.disabled = false;
				nameInput.disabled = false;
			}
		}

		createBtn.addEventListener('click', function (e) {
			e.preventDefault();
			createCustomer();
		});
		nameInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				createCustomer();
			}
		});
	}

	function initializeProjectForm() {
		const startDateInput = document.getElementById('start_date');
		const endDateInput = document.getElementById('end_date');
		const form = document.getElementById('project-form');
		const saveBtn = document.getElementById('pc-project-save');
		let submitting = false;

		function onDateChange() {
			validateDateRange(startDateInput, endDateInput);
		}
		if (startDateInput) {
			startDateInput.addEventListener('change', onDateChange);
		}
		if (endDateInput) {
			endDateInput.addEventListener('change', onDateChange);
		}

		if (form) {
			form.addEventListener('submit', function (e) {
				if (submitting) {
					e.preventDefault();
					return;
				}
				if (!validateDateRange(startDateInput, endDateInput)) {
					e.preventDefault();
					if (endDateInput && typeof endDateInput.reportValidity === 'function') {
						endDateInput.reportValidity();
					}
					return;
				}
				if (startDateInput && startDateInput.value) {
					startDateInput.value = normalizeDateToIso(startDateInput.value);
				}
				if (endDateInput && endDateInput.value) {
					endDateInput.value = normalizeDateToIso(endDateInput.value);
				}
				fillShortDescriptionFromName(form);
				normalizeCapacityFieldsForSubmit(form);
				submitting = true;
				if (saveBtn) {
					saveBtn.disabled = true;
					saveBtn.setAttribute('aria-busy', 'true');
				}
			});

			// If HTML5 validation fails inside a closed <details>, open it so the user sees why.
			form.addEventListener('invalid', function (e) {
				const target = e.target;
				if (!target || typeof target.closest !== 'function') {
					return;
				}
				const details = target.closest('details.pc-advanced-details');
				if (details && !details.open) {
					details.open = true;
				}
			}, true);
		}

		initializeQuickCustomer();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeProjectForm);
	} else {
		initializeProjectForm();
	}
})();
