/**
 * ProjectCheck server and in-app organization settings (access policy + app defaults).
 * User-visible copy comes from the server: JSON responses and data-pc-form-strings (PHP l10N).
 * Do not rely on global t() / OC.L10N in this file — auditors and CSP prefer that contract.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	/**
	 * @param {HTMLFormElement} form
	 * @returns {Object<string, *>&{errors: Object<string, string>}}
	 */
	function readFormStrings(form) {
		var base = { errors: {} };
		if (!form) {
			return base;
		}
		var raw = form.getAttribute('data-pc-form-strings') || '';
		if (!raw) {
			return base;
		}
		try {
			var o = JSON.parse(raw);
			if (o && typeof o === 'object' && o.errors && typeof o.errors === 'object') {
				return o;
			}
			if (o && typeof o === 'object') {
				o.errors = o.errors && typeof o.errors === 'object' ? o.errors : {};
				return o;
			}
		} catch (ignore) {
		}
		return base;
	}

	function getRequestToken() {
		if (typeof OC !== 'undefined' && OC.requestToken) {
			return OC.requestToken;
		}
		var meta = document.querySelector('meta[name="requesttoken"]');
		return meta ? (meta.getAttribute('content') || '') : '';
	}

	function formToObject(form) {
		var data = new FormData(form);
		var o = {};
		data.forEach(function (value, key) {
			o[key] = value;
		});
		var restrict = form.querySelector('#pc_access_restriction');
		if (restrict) {
			o.access_restriction_enabled = restrict.checked ? '1' : '0';
		}
		return o;
	}

	/**
	 * @param {HTMLElement | null} el
	 * @param {string} text
	 * @param {boolean} isError
	 */
	function setStatus(el, text, isError) {
		if (!el) {
			return;
		}
		var next = text || '';
		if (el.textContent !== next) {
			el.textContent = next;
		}
		if (next) {
			el.hidden = false;
		} else {
			el.hidden = true;
		}
		if (isError) {
			el.setAttribute('data-state', 'error');
		} else {
			el.setAttribute('data-state', 'success');
		}
	}

	/**
	 * 1) Prefer the server "message" field. 2) Map "error" code to data-pc-form-strings.errors (PHP l10N). 3) Status fallbacks.
	 *
	 * @param {object | null} body
	 * @param {number} status
	 * @param {ReturnType<typeof readFormStrings>} s
	 */
	function parseErrorMessage(body, status, s) {
		if (body && typeof body.message === 'string' && body.message) {
			return body.message;
		}
		var E = s.errors && typeof s.errors === 'object' ? s.errors : {};
		if (body && body.error) {
			var be = E[body.error];
			if (typeof be === 'string' && be) {
				return be;
			}
		}
		if (status === 404 && typeof E.notFound === 'string' && E.notFound) {
			return E.notFound;
		}
		if (status === 400 && typeof E.badRequest === 'string' && E.badRequest) {
			return E.badRequest;
		}
		if (status >= 500 && typeof E.server === 'string' && E.server) {
			return E.server;
		}
		if (typeof s.genericSaveFailed === 'string' && s.genericSaveFailed) {
			return s.genericSaveFailed;
		}
		return 'save failed';
	}

	/**
	 * @param {HTMLFormElement} form
	 * @param {function(): void} done
	 * @param {string|undefined} msg
	 */
	function setFormBusy(form, done, msg) {
		if (!form) {
			return;
		}
		var wasBusy = form.getAttribute('aria-busy') === 'true';
		if (!done) {
			if (!wasBusy) {
				form.setAttribute('aria-busy', 'true');
			}
			var btn = form.querySelector('button.projectcheck-save-button, button[type="submit"]');
			if (btn) {
				if (!btn.dataset._origLabel) {
					btn.dataset._origLabel = btn.textContent || '';
				}
				if (msg) {
					btn.textContent = msg;
				}
				btn.disabled = true;
			}
		} else {
			form.setAttribute('aria-busy', 'false');
			var b = form.querySelector('button.projectcheck-save-button, button[type="submit"]');
			if (b && b.dataset._origLabel) {
				b.textContent = b.dataset._origLabel;
				b.disabled = false;
			} else if (b) {
				b.disabled = false;
			}
		}
	}

	/**
	 * @param {HTMLFormElement} form
	 */
	function initForm(form) {
		if (!form || form.tagName !== 'FORM') {
			return;
		}
		var action = form.getAttribute('action');
		if (!action) {
			return;
		}
		var s = readFormStrings(form);
		var statusEl = form.querySelector('p[role="status"].projectcheck-form-status');

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			s = readFormStrings(form);
			var noTok = s.noRequestToken;
			if (!getRequestToken()) {
				setStatus(statusEl, typeof noTok === 'string' ? noTok : '', true);
				if (statusEl) {
					statusEl.focus();
				}
				return;
			}
			var saving = s.saving;
			setFormBusy(form, false, typeof saving === 'string' ? saving : '…');
			setStatus(statusEl, '', false);

			var payload = formToObject(form);
			var token = getRequestToken();
			payload.requesttoken = token;

			fetch(action, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					requesttoken: token
				},
				credentials: 'same-origin',
				body: JSON.stringify(payload)
			})
				.then(function (r) {
					return r.text().then(function (raw) {
						var j = null;
						if (raw) {
							try {
								j = JSON.parse(raw);
							} catch (parseE) {
								j = null;
							}
						}
						return { ok: r.ok, status: r.status, body: j, hadJson: j !== null };
					});
				})
				.then(function (res) {
					setFormBusy(form, true);
					s = readFormStrings(form);
					var settingsSaved = s.settingsSaved;
					if (res.hadJson && res.body && res.ok && res.body.success) {
						setStatus(
							statusEl,
							res.body.message && typeof res.body.message === 'string' ? res.body.message : (typeof settingsSaved === 'string' ? settingsSaved : ''),
							false
						);
						if (typeof OC !== 'undefined' && OC.Notification && OC.Notification.showTemporary) {
							var m = res.body && res.body.message;
							OC.Notification.showTemporary(typeof m === 'string' && m ? m : (typeof settingsSaved === 'string' ? settingsSaved : ''));
						}
						if (statusEl) {
							statusEl.focus();
						}
					} else {
						var errText;
						if (res.hadJson) {
							errText = parseErrorMessage(res.body, res.status, s);
						} else {
							errText =
								typeof s.invalidResponse === 'string' ? s.invalidResponse : 'invalid';
						}
						if (!res.hadJson && res.status >= 200 && res.status < 300) {
							errText =
								typeof s.unexpectedResponse === 'string' ? s.unexpectedResponse : errText;
						}
						setStatus(statusEl, errText, true);
						if (statusEl) {
							statusEl.focus();
						}
					}
				})
				.catch(function () {
					setFormBusy(form, true);
					s = readFormStrings(form);
					var net = s.networkError;
					setStatus(statusEl, typeof net === 'string' ? net : '', true);
					if (statusEl) {
						statusEl.focus();
					}
				});
		});
	}

	function boot() {
		var org = document.getElementById('projectcheck-org-form');
		var admin = document.getElementById('projectcheck-admin-form');
		var currencySelect = document.getElementById('pc_currency');
		var hourlyRateLabel = document.getElementById('pc_def_rate_label');
		if (currencySelect && hourlyRateLabel) {
			var applyCurrencyToRateLabel = function () {
				var code = (currencySelect.value || 'EUR').toUpperCase().trim();
				if (!/^[A-Z]{3}$/.test(code)) {
					code = 'EUR';
				}
				var tpl = hourlyRateLabel.getAttribute('data-currency-label-template') || 'Default hourly rate (%s)';
				hourlyRateLabel.textContent = tpl.replace('%s', code);
			};
			currencySelect.addEventListener('change', applyCurrencyToRateLabel);
			applyCurrencyToRateLabel();
		}
		if (org) {
			initForm(/** @type {HTMLFormElement} */ (org));
		}
		if (admin) {
			initForm(/** @type {HTMLFormElement} */ (admin));
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
