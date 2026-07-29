/**
 * ProjectCheck — License settings panel (PC2 mobile seats).
 *
 * Seats-only: ProjectCheck has no room-display/signage product. The web app always stays
 * free; this script only manages the PC2 license key and named mobile seats. It has no
 * dependency on any cross-app notification bus — it talks directly to the
 * license#* JSON endpoints registered in appinfo/routes.php.
 *
 * Security notes:
 * - Seat assignment on the server uses an exclusive lock; a 409 here means "try again or
 *   free a seat", not a bug — it is surfaced to the user, not silently retried forever.
 * - Every user-supplied or server-supplied name/id is rendered with `textContent` (or the
 *   DOM text-node APIs), never `innerHTML`, to avoid stored/reflected XSS via display names.
 * - `assignedAt` from the API is UNIX seconds, not ISO — always multiply by 1000 before
 *   constructing a `Date`.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	var root = document.getElementById('pc-license-panel');
	if (!root) {
		return;
	}

	// ---- Config from data attributes -------------------------------------------------
	var api = {
		license: root.dataset.apiLicense || '',
		clearLicense: root.dataset.apiClearLicense || root.dataset.apiLicense || '',
		seats: root.dataset.apiSeats || '',
		assignSeat: root.dataset.apiAssignSeat || root.dataset.apiSeats || '',
		removeSeatBase: root.dataset.apiRemoveSeatBase || '',
		searchUsers: root.dataset.apiSearchUsers || ''
	};
	if (!api.removeSeatBase && api.seats) {
		api.removeSeatBase = /\/$/.test(api.seats) ? api.seats : api.seats + '/';
	}

	var i18n = {};
	try {
		i18n = JSON.parse(root.dataset.i18n || '{}') || {};
	} catch (e) {
		i18n = {};
	}

	function tr(key, fallback) {
		var v = i18n[key];
		return typeof v === 'string' && v !== '' ? v : (fallback || '');
	}

	function fmt() {
		var args = Array.prototype.slice.call(arguments);
		var tpl = args.shift();
		var i = 0;
		return String(tpl).replace(/%s/g, function () {
			var v = args[i];
			i += 1;
			return v === undefined ? '' : String(v);
		});
	}

	function requestToken() {
		if (root.dataset.requesttoken) {
			return root.dataset.requesttoken;
		}
		if (typeof window.OC !== 'undefined' && window.OC.requestToken) {
			return window.OC.requestToken;
		}
		var meta = document.querySelector('meta[name="requesttoken"]');
		return meta ? meta.getAttribute('content') || '' : '';
	}

	// ---- DOM references ---------------------------------------------------------------
	var liveEl = document.getElementById('pc-license-live');
	var alertLiveEl = document.getElementById('pc-license-alert');
	var feedbackEl = document.getElementById('pc-license-feedback');

	var badgeEl = document.getElementById('pc-license-badge');
	var validUntilEl = document.getElementById('pc-license-valid-until');
	var meterEl = document.getElementById('pc-license-meter');
	var meterFillEl = document.getElementById('pc-license-meter-fill');
	var meterTextEl = document.getElementById('pc-license-meter-text');
	var expiryCalloutEl = document.getElementById('pc-license-expiry-callout');
	var expiryBodyEl = document.getElementById('pc-license-expiry-body');

	var applyForm = document.getElementById('pc-license-form');
	var keyInput = document.getElementById('pc-license-key');
	var saveBtn = document.getElementById('pc-license-save');
	var removeBtn = document.getElementById('pc-license-remove');

	var seatSearchInput = document.getElementById('pc-license-seat-search-input');
	var seatSuggestEl = document.getElementById('pc-license-seat-search-suggest');
	var seatTbody = document.getElementById('pc-license-seat-tbody');

	var modalEl = document.getElementById('pc-license-confirm-modal');
	var modalCancelBtn = document.getElementById('pc-license-confirm-cancel');
	var modalConfirmBtn = document.getElementById('pc-license-confirm-ok');

	// ---- Small state kept in memory (avoids extra fetches on every render) ------------
	/** @type {Array<{uid:string, displayName:string, assignedAt:number, withinLimit:boolean}>} */
	var seatRows = [];
	var modalReturnFocusEl = null;

	// ---- Networking ---------------------------------------------------------------------
	function request(url, options) {
		if (!url) {
			return Promise.resolve({ ok: false, status: 0, data: null });
		}
		var opts = options || {};
		opts.credentials = 'same-origin';
		opts.headers = Object.assign(
			{
				requesttoken: requestToken(),
				Accept: 'application/json'
			},
			opts.headers || {}
		);
		return fetch(url, opts)
			.then(function (res) {
				return res
					.text()
					.then(function (raw) {
						var data = null;
						if (raw) {
							try {
								data = JSON.parse(raw);
							} catch (e) {
								data = null;
							}
						}
						return { ok: res.ok, status: res.status, data: data };
					});
			})
			.catch(function () {
				return { ok: false, status: 0, data: null };
			});
	}

	// ---- Live regions + feedback banner -------------------------------------------------
	function announce(message, assertive) {
		var el = assertive ? alertLiveEl : liveEl;
		if (!el || !message) {
			return;
		}
		el.textContent = '';
		window.setTimeout(function () {
			el.textContent = message;
		}, 30);
	}

	function showFeedback(message, type) {
		if (!feedbackEl) {
			return;
		}
		if (!message) {
			feedbackEl.hidden = true;
			feedbackEl.textContent = '';
			return;
		}
		feedbackEl.hidden = false;
		feedbackEl.textContent = message;
		feedbackEl.className = 'pc-license-feedback' + (type ? ' pc-license-feedback--' + type : '');
		announce(message, type === 'error');
	}

	// ---- Formatting helpers --------------------------------------------------------------
	function formatIsoDate(ymd) {
		if (!ymd || typeof ymd !== 'string') {
			return '';
		}
		var d = new Date(ymd + 'T00:00:00Z');
		if (isNaN(d.getTime())) {
			return ymd;
		}
		try {
			return d.toLocaleDateString();
		} catch (e) {
			return ymd;
		}
	}

	function formatUnixDate(seconds) {
		var n = Number(seconds);
		if (!n || n <= 0) {
			return '';
		}
		// assignedAt is UNIX *seconds*, Date() needs milliseconds.
		var d = new Date(n * 1000);
		if (isNaN(d.getTime())) {
			return '';
		}
		try {
			return d.toLocaleDateString();
		} catch (e) {
			return '';
		}
	}

	// ---- Status rendering ------------------------------------------------------------------
	function renderStatus(status) {
		var state = status && status.state;
		var seats = (status && status.seats) || { assigned: 0, limit: 0 };
		var assigned = Number(seats.assigned) || 0;
		var limit = Number(seats.limit) || 0;

		var hasState = !!state;
		var valid = hasState && !!state.valid;
		var expiresSoon = hasState && !!state.expiresSoon;

		if (badgeEl) {
			var badgeText;
			var badgeClass;
			if (!hasState) {
				badgeText = tr('badgeNotConfigured', 'Not configured');
				badgeClass = 'pc-license-badge--none';
			} else if (valid && expiresSoon) {
				badgeText = tr('badgeActiveSoon', 'Active — renew soon');
				badgeClass = 'pc-license-badge--warning';
			} else if (valid) {
				badgeText = tr('badgeActive', 'Active');
				badgeClass = 'pc-license-badge--active';
			} else {
				badgeText = tr('badgeExpired', 'Expired');
				badgeClass = 'pc-license-badge--expired';
			}
			badgeEl.textContent = badgeText;
			badgeEl.className = 'pc-license-badge ' + badgeClass;
		}

		if (validUntilEl) {
			validUntilEl.textContent = '';
			if (hasState && state.validUntil) {
				validUntilEl.appendChild(document.createTextNode(tr('validUntilLabel', 'Valid until') + ' '));
				var strong = document.createElement('strong');
				strong.textContent = formatIsoDate(state.validUntil);
				validUntilEl.appendChild(strong);
			} else {
				validUntilEl.textContent = tr('validUntilNone', 'No license applied');
			}
		}

		var usedText = fmt(tr('seatsUsedText', '%s of %s seats used'), assigned, limit);
		// seatsUsedText uses numbered placeholders (%1$s/%2$s) from the PHP catalog;
		// normalise both styles so this renders correctly either way.
		usedText = usedText
			.replace('%1$s', String(assigned))
			.replace('%2$s', String(limit));
		if (meterEl) {
			meterEl.setAttribute('aria-valuenow', String(assigned));
			meterEl.setAttribute('aria-valuemax', String(Math.max(limit, assigned, 1)));
			meterEl.setAttribute('aria-valuetext', usedText);
		}
		if (meterFillEl) {
			var pct = limit > 0 ? Math.min(100, Math.round((assigned / limit) * 100)) : 0;
			meterFillEl.style.width = pct + '%';
		}
		if (meterTextEl) {
			meterTextEl.textContent = usedText;
		}

		if (expiryCalloutEl) {
			var showExpiry = valid && expiresSoon;
			expiryCalloutEl.hidden = !showExpiry;
			if (showExpiry && expiryBodyEl) {
				var days = hasState && typeof state.daysRemaining === 'number' ? Math.max(0, state.daysRemaining) : null;
				var body = tr('expirySoonBody', 'This license expires in {days} day(s). Renew soon to avoid an interruption for your mobile seats.');
				expiryBodyEl.textContent = body.replace('{days}', days === null ? '' : String(days));
			}
		}
	}

	// ---- Seats table rendering (textContent only — never innerHTML with server data) ------
	function buildSeatRow(seat) {
		var tr2 = document.createElement('tr');
		tr2.className = 'pc-license-seat-row';
		tr2.setAttribute('data-uid', seat.uid);

		var personTd = document.createElement('td');
		personTd.className = 'pc-license-seat-row__person';
		var nameSpan = document.createElement('span');
		nameSpan.className = 'pc-license-seat-row__name';
		nameSpan.textContent = seat.displayName || seat.uid;
		personTd.appendChild(nameSpan);
		if (seat.displayName && seat.displayName !== seat.uid) {
			var uidSpan = document.createElement('span');
			uidSpan.className = 'pc-license-seat-row__uid';
			uidSpan.textContent = seat.uid;
			personTd.appendChild(uidSpan);
		}
		if (seat.withinLimit === false) {
			var overBadge = document.createElement('span');
			overBadge.className = 'pc-license-badge pc-license-badge--warning pc-license-seat-row__over';
			overBadge.textContent = tr('seatOverLimitBadge', 'Over limit');
			personTd.appendChild(overBadge);
		}
		tr2.appendChild(personTd);

		var assignedTd = document.createElement('td');
		assignedTd.className = 'pc-license-seat-row__assigned';
		assignedTd.textContent = formatUnixDate(seat.assignedAt);
		tr2.appendChild(assignedTd);

		var actionsTd = document.createElement('td');
		actionsTd.className = 'pc-license-seat-row__actions';
		var removeSeatBtn = document.createElement('button');
		removeSeatBtn.type = 'button';
		removeSeatBtn.className = 'button pc-license-seat-remove';
		removeSeatBtn.setAttribute('data-uid', seat.uid);
		removeSeatBtn.setAttribute(
			'aria-label',
			fmt(tr('seatRemoveAria', 'Remove seat for %s'), seat.displayName || seat.uid)
		);
		removeSeatBtn.textContent = tr('seatRemoveButton', 'Remove');
		removeSeatBtn.addEventListener('click', function () {
			handleRemoveSeat(seat.uid, seat.displayName || seat.uid, removeSeatBtn);
		});
		actionsTd.appendChild(removeSeatBtn);
		tr2.appendChild(actionsTd);

		return tr2;
	}

	function renderSeatsTable(rows) {
		if (!seatTbody) {
			return;
		}
		while (seatTbody.firstChild) {
			seatTbody.removeChild(seatTbody.firstChild);
		}
		if (!rows || rows.length === 0) {
			var emptyRow = document.createElement('tr');
			emptyRow.className = 'pc-license-seat-row pc-license-seat-row--empty';
			emptyRow.id = 'pc-license-seat-empty-row';
			var emptyTd = document.createElement('td');
			emptyTd.colSpan = 3;
			emptyTd.textContent = tr('seatsEmpty', 'No seats assigned yet.');
			emptyRow.appendChild(emptyTd);
			seatTbody.appendChild(emptyRow);
			return;
		}
		rows.forEach(function (seat) {
			seatTbody.appendChild(buildSeatRow(seat));
		});
	}

	// ---- Data loading --------------------------------------------------------------------
	function loadStatus() {
		return request(api.license, { method: 'GET' }).then(function (res) {
			if (res.ok && res.data) {
				renderStatus(res.data);
			}
			return res;
		});
	}

	function loadSeats() {
		var url = api.seats;
		if (url) {
			url += (url.indexOf('?') === -1 ? '?' : '&') + 'limit=200&offset=0';
		}
		return request(url, { method: 'GET' }).then(function (res) {
			if (res.ok && res.data && Array.isArray(res.data.data)) {
				seatRows = res.data.data;
				renderSeatsTable(seatRows);
			}
			return res;
		});
	}

	function refreshAll() {
		return Promise.all([loadStatus(), loadSeats()]);
	}

	// ---- Error message mapping -------------------------------------------------------------
	function messageForError(data, fallbackKey, fallbackDefault) {
		if (data && typeof data.message === 'string' && data.message !== '') {
			return data.message;
		}
		var code = data && data.error;
		var byCode = {
			access_denied: tr('accessDenied'),
			license_invalid: tr('saveFailedGeneric'),
			license_busy: tr('genericError'),
			unknown_user: tr('seatAssignUnknownUser'),
			seat_limit_reached: tr('seatAssignLimitReached'),
			seat_busy: tr('seatAssignBusy'),
			seat_not_found: tr('seatRemoveFailedGeneric')
		};
		if (code && byCode[code]) {
			return byCode[code];
		}
		return tr(fallbackKey, fallbackDefault);
	}

	// ---- Flash message across a reload (sessionStorage) -------------------------------------
	var FLASH_KEY = 'pcLicensePanelFlash';

	function setFlash(type, message) {
		try {
			window.sessionStorage.setItem(FLASH_KEY, JSON.stringify({ type: type, message: message }));
		} catch (e) {
			// sessionStorage unavailable (private mode / disabled) — fall back to in-page feedback.
			showFeedback(message, type);
		}
	}

	function consumeFlash() {
		var raw = null;
		try {
			raw = window.sessionStorage.getItem(FLASH_KEY);
			if (raw) {
				window.sessionStorage.removeItem(FLASH_KEY);
			}
		} catch (e) {
			raw = null;
		}
		if (!raw) {
			return;
		}
		try {
			var flash = JSON.parse(raw);
			if (flash && flash.message) {
				showFeedback(flash.message, flash.type);
			}
		} catch (e) {
			// ignore malformed flash payloads
		}
	}

	// ---- Apply license ---------------------------------------------------------------------
	function setBusy(button, busy, busyLabel) {
		if (!button) {
			return;
		}
		button.disabled = busy;
		if (busy) {
			button.setAttribute('data-pc-original-label', button.textContent);
			button.textContent = busyLabel;
		} else {
			var original = button.getAttribute('data-pc-original-label');
			if (original) {
				button.textContent = original;
			}
		}
	}

	if (applyForm) {
		applyForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var key = (keyInput && keyInput.value ? keyInput.value : '').trim();
			if (!key) {
				showFeedback(tr('keyRequired', 'Paste a PC2 license key.'), 'error');
				if (keyInput) {
					keyInput.focus();
				}
				return;
			}
			setBusy(saveBtn, true, tr('saving', 'Saving…'));
			request(api.license, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ key: key })
			})
				.then(function (res) {
					setBusy(saveBtn, false);
					if (!res.ok) {
						showFeedback(messageForError(res.data, 'saveFailedGeneric', 'Could not apply this license key.'), 'error');
						return;
					}
					setFlash('success', tr('saveSuccess', 'License saved.'));
					window.location.reload();
				})
				.catch(function () {
					setBusy(saveBtn, false);
					showFeedback(tr('networkError', 'Network error. Please try again.'), 'error');
				});
		});
	}

	// ---- Remove license (confirm modal + focus trap) ----------------------------------------
	function focusableModalElements() {
		if (!modalEl) {
			return [];
		}
		return Array.prototype.slice.call(
			modalEl.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
		).filter(function (el) {
			return !el.hidden && el.offsetParent !== null;
		});
	}

	function onModalKeydown(e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			closeModal();
			return;
		}
		if (e.key !== 'Tab') {
			return;
		}
		var focusable = focusableModalElements();
		if (focusable.length === 0) {
			return;
		}
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function openModal() {
		if (!modalEl) {
			return;
		}
		modalReturnFocusEl = document.activeElement;
		modalEl.hidden = false;
		document.addEventListener('keydown', onModalKeydown, true);
		if (modalCancelBtn) {
			modalCancelBtn.focus();
		}
	}

	function closeModal() {
		if (!modalEl) {
			return;
		}
		modalEl.hidden = true;
		document.removeEventListener('keydown', onModalKeydown, true);
		if (modalReturnFocusEl && typeof modalReturnFocusEl.focus === 'function') {
			modalReturnFocusEl.focus();
		}
		modalReturnFocusEl = null;
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', function () {
			openModal();
		});
	}
	if (modalCancelBtn) {
		modalCancelBtn.addEventListener('click', closeModal);
	}
	if (modalEl) {
		modalEl.addEventListener('click', function (e) {
			if (e.target && e.target.getAttribute && e.target.getAttribute('data-pc-license-modal-dismiss') === '1') {
				closeModal();
			}
		});
	}
	if (modalConfirmBtn) {
		modalConfirmBtn.addEventListener('click', function () {
			setBusy(modalConfirmBtn, true, tr('removing', 'Removing…'));
			request(api.clearLicense, { method: 'DELETE' })
				.then(function (res) {
					setBusy(modalConfirmBtn, false);
					closeModal();
					if (!res.ok) {
						showFeedback(messageForError(res.data, 'removeFailedGeneric', 'Could not remove the license.'), 'error');
						return;
					}
					setFlash('success', tr('removeSuccess', 'License removed.'));
					window.location.reload();
				})
				.catch(function () {
					setBusy(modalConfirmBtn, false);
					closeModal();
					showFeedback(tr('networkError', 'Network error. Please try again.'), 'error');
				});
		});
	}

	// ---- Seat removal (no reload — live refresh) ---------------------------------------------
	function handleRemoveSeat(uid, displayName, triggerBtn) {
		if (!api.removeSeatBase) {
			return;
		}
		if (triggerBtn) {
			triggerBtn.disabled = true;
		}
		var url = api.removeSeatBase + encodeURIComponent(uid);
		request(url, { method: 'DELETE' })
			.then(function (res) {
				if (!res.ok) {
					if (triggerBtn) {
						triggerBtn.disabled = false;
					}
					showFeedback(messageForError(res.data, 'seatRemoveFailedGeneric', 'Could not remove the seat.'), 'error');
					return;
				}
				showFeedback(tr('seatRemoveSuccess', 'Seat removed.'), 'success');
				refreshAll();
			})
			.catch(function () {
				if (triggerBtn) {
					triggerBtn.disabled = false;
				}
				showFeedback(tr('networkError', 'Network error. Please try again.'), 'error');
			});
	}

	// ---- Seat search combobox ------------------------------------------------------------------
	var searchDebounce = 0;
	var searchGeneration = 0;
	var activeOptionIndex = -1;

	function isAlreadySeated(uid) {
		return seatRows.some(function (s) {
			return s.uid === uid;
		});
	}

	function closeSuggest() {
		if (!seatSuggestEl) {
			return;
		}
		while (seatSuggestEl.firstChild) {
			seatSuggestEl.removeChild(seatSuggestEl.firstChild);
		}
		seatSuggestEl.hidden = true;
		if (seatSearchInput) {
			seatSearchInput.setAttribute('aria-expanded', 'false');
			seatSearchInput.removeAttribute('aria-activedescendant');
		}
		activeOptionIndex = -1;
	}

	function setActiveOption(options, idx) {
		if (!options.length) {
			return;
		}
		if (idx < 0) {
			idx = options.length - 1;
		}
		if (idx >= options.length) {
			idx = 0;
		}
		activeOptionIndex = idx;
		options.forEach(function (opt, i) {
			opt.setAttribute('aria-selected', i === idx ? 'true' : 'false');
			if (i === idx && seatSearchInput && opt.id) {
				seatSearchInput.setAttribute('aria-activedescendant', opt.id);
			}
		});
	}

	function assignSeatFromSearch(item) {
		if (!item || !item.id) {
			return;
		}
		closeSuggest();
		if (seatSearchInput) {
			seatSearchInput.value = '';
			seatSearchInput.disabled = true;
		}
		request(api.assignSeat, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ userId: item.id })
		})
			.then(function (res) {
				if (seatSearchInput) {
					seatSearchInput.disabled = false;
					seatSearchInput.focus();
				}
				if (res.status === 409) {
					// Distinguish capacity full vs exclusive-lock contention (both are 409).
					var conflictFallback = (res.data && res.data.error === 'seat_busy')
						? 'seatAssignBusy'
						: 'seatAssignLimitReached';
					var conflictDefault = conflictFallback === 'seatAssignBusy'
						? 'Another seat update is in progress. Try again in a moment.'
						: 'All licensed seats are assigned. Remove a seat or upgrade the license.';
					showFeedback(messageForError(res.data, conflictFallback, conflictDefault), 'error');
					return;
				}
				if (!res.ok) {
					showFeedback(messageForError(res.data, 'seatAssignFailedGeneric', 'Could not assign the seat.'), 'error');
					return;
				}
				showFeedback(tr('seatAssignSuccess', 'Seat assigned.'), 'success');
				refreshAll();
			})
			.catch(function () {
				if (seatSearchInput) {
					seatSearchInput.disabled = false;
				}
				showFeedback(tr('networkError', 'Network error. Please try again.'), 'error');
			});
	}

	function renderSuggestions(items, errMessage) {
		if (!seatSuggestEl) {
			return;
		}
		while (seatSuggestEl.firstChild) {
			seatSuggestEl.removeChild(seatSuggestEl.firstChild);
		}
		if (errMessage) {
			var errP = document.createElement('p');
			errP.className = 'pc-license-seat-search__note pc-license-seat-search__note--err';
			errP.setAttribute('role', 'alert');
			errP.textContent = errMessage;
			seatSuggestEl.appendChild(errP);
			seatSuggestEl.hidden = false;
			if (seatSearchInput) {
				seatSearchInput.setAttribute('aria-expanded', 'true');
			}
			return;
		}
		var filtered = (items || []).filter(function (it) {
			return it && it.id && !isAlreadySeated(it.id);
		});
		if (filtered.length === 0) {
			var noneP = document.createElement('p');
			noneP.className = 'pc-license-seat-search__note';
			noneP.setAttribute('role', 'status');
			noneP.textContent = tr('seatSearchNoResults', 'No matching users, or everyone found already has a seat.');
			seatSuggestEl.appendChild(noneP);
			seatSuggestEl.hidden = false;
			if (seatSearchInput) {
				seatSearchInput.setAttribute('aria-expanded', 'true');
			}
			return;
		}
		var listbox = document.createElement('ul');
		listbox.className = 'pc-license-seat-search__listbox';
		listbox.setAttribute('role', 'listbox');
		listbox.id = 'pc-license-seat-search-listbox';
		filtered.forEach(function (item, idx) {
			var li = document.createElement('li');
			li.setAttribute('role', 'option');
			li.id = 'pc-license-seat-search-option-' + idx;
			li.className = 'pc-license-seat-search__option';

			var nameLine = document.createElement('div');
			nameLine.className = 'pc-license-seat-search__option-name';
			nameLine.textContent = item.displayName || item.id;
			li.appendChild(nameLine);
			if (item.displayName && item.displayName !== item.id) {
				var idLine = document.createElement('div');
				idLine.className = 'pc-license-seat-search__option-id';
				idLine.textContent = item.id;
				li.appendChild(idLine);
			}
			li.addEventListener('mousedown', function (ev) {
				ev.preventDefault();
			});
			li.addEventListener('click', function () {
				assignSeatFromSearch(item);
			});
			listbox.appendChild(li);
		});
		seatSuggestEl.appendChild(listbox);
		seatSuggestEl.hidden = false;
		if (seatSearchInput) {
			seatSearchInput.setAttribute('aria-expanded', 'true');
			seatSearchInput.setAttribute('aria-controls', listbox.id);
		}
		setActiveOption(Array.prototype.slice.call(listbox.querySelectorAll('li[role="option"]')), 0);
	}

	if (seatSearchInput) {
		seatSearchInput.addEventListener('input', function () {
			window.clearTimeout(searchDebounce);
			var q = seatSearchInput.value.trim();
			if (q.length < 2) {
				closeSuggest();
				return;
			}
			searchGeneration += 1;
			var myGeneration = searchGeneration;
			searchDebounce = window.setTimeout(function () {
				if (!api.searchUsers) {
					return;
				}
				var url = new URL(api.searchUsers, window.location.origin);
				url.searchParams.set('q', q);
				request(url.toString(), { method: 'GET' }).then(function (res) {
					if (myGeneration !== searchGeneration) {
						return;
					}
					if (res.status === 401) {
						renderSuggestions([], tr('seatSearchErrorAuth', 'Search could not run. Sign in again, then return to this page.'));
						return;
					}
					if (res.status === 403) {
						renderSuggestions([], tr('seatSearchErrorPermission', 'You are not allowed to search the directory.'));
						return;
					}
					if (!res.ok || !res.data) {
						renderSuggestions([], tr('seatSearchErrorServer', 'The server could not run the search. Try again in a moment.'));
						return;
					}
					if (res.data.ok === false) {
						renderSuggestions([], tr('seatSearchErrorServer', 'The server could not run the search. Try again in a moment.'));
						return;
					}
					renderSuggestions(Array.isArray(res.data.items) ? res.data.items : [], null);
				});
			}, 300);
		});

		seatSearchInput.addEventListener('keydown', function (e) {
			var options = seatSuggestEl && !seatSuggestEl.hidden
				? Array.prototype.slice.call(seatSuggestEl.querySelectorAll('li[role="option"]'))
				: [];
			if (e.key === 'Escape') {
				e.preventDefault();
				closeSuggest();
				return;
			}
			if (e.key === 'Enter') {
				if (options.length && activeOptionIndex >= 0) {
					e.preventDefault();
					options[activeOptionIndex].click();
				}
				return;
			}
			if (!options.length) {
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				setActiveOption(options, activeOptionIndex + 1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				setActiveOption(options, activeOptionIndex - 1);
			}
		});

		document.addEventListener('click', function (e) {
			if (!seatSuggestEl || seatSuggestEl.hidden) {
				return;
			}
			if (e.target === seatSearchInput || seatSuggestEl.contains(e.target)) {
				return;
			}
			closeSuggest();
		});
	}

	// ---- Init ------------------------------------------------------------------------------
	consumeFlash();
	refreshAll();
})();
