/**
 * Project form: native date inputs + inline customer quick-add (stay on page).
 *
 * UX contract (Bachus):
 * - Exactly one primary “finish” action for the form: Save / Create / Update.
 * - “Add to list” is secondary — it only puts the customer in the dropdown.
 * - After quick-add, a local Save affordance appears next to the field so users
 *   never hunt for the footer button.
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
	 * Readonly capacity fields submit "" when hours are zero — coerce before POST
	 * so MariaDB DECIMAL columns never receive an empty string.
	 */
	function normalizeCapacityFieldsForSubmit(form) {
		if (!form) {
			return;
		}
		['available_hours', 'total_budget', 'hourly_rate'].forEach(function (name) {
			const el = form.elements.namedItem(name);
			if (!el || typeof el.value !== 'string') {
				return;
			}
			if (String(el.value).trim() === '') {
				el.value = '0';
			}
		});
	}

	function showSaveNextStep(wrap, statusEl, customerName) {
		const next = document.getElementById('pc-quick-customer-next');
		const nextText = document.getElementById('pc-quick-customer-next-text');
		const saveHere = document.getElementById('pc-quick-customer-save');
		const shortOk = tPc('Added to the list and selected.');
		setQuickCustomerStatus(statusEl, shortOk, false);
		announce(shortOk + ' ' + tPc('Save the project to finish.'));

		if (nextText) {
			const label = customerName
				? tPc('"{name}" is selected. One more step:', { name: customerName })
				: tPc('Customer is selected. One more step:');
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
		if (saveHere) {
			window.setTimeout(function () {
				saveHere.focus();
			}, 50);
		}
		if (wrap) {
			wrap.classList.add('pc-quick-customer--ready');
		}
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

		async function createCustomer() {
			const name = String(nameInput.value || '').trim();
			if (!name) {
				setQuickCustomerStatus(statusEl, tPc('Enter a customer name.'), true);
				hideSaveNextStep(wrap);
				nameInput.focus();
				return;
			}

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
			form.addEventListener('submit', function () {
				if (startDateInput && startDateInput.value) {
					startDateInput.value = normalizeDateToIso(startDateInput.value);
				}
				if (endDateInput && endDateInput.value) {
					endDateInput.value = normalizeDateToIso(endDateInput.value);
				}
				normalizeCapacityFieldsForSubmit(form);
			});
		}

		initializeQuickCustomer();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeProjectForm);
	} else {
		initializeProjectForm();
	}
})();
