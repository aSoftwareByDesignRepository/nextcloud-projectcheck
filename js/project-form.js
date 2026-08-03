/**
 * Project form: native date inputs + inline customer quick-add (stay on page).
 */
(function () {
	'use strict';

	function tPc(msg) {
		return typeof t === 'function' ? t('projectcheck', msg) : msg;
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
		select.dispatchEvent(new Event('change', { bubbles: true }));
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
				nameInput.focus();
				return;
			}

			createBtn.disabled = true;
			nameInput.disabled = true;
			setQuickCustomerStatus(statusEl, tPc('Creating customer…'), false);

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
				const okMsg = tPc('Customer added and selected.');
				setQuickCustomerStatus(statusEl, okMsg, false);
				announce(okMsg);
				select.focus();
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
