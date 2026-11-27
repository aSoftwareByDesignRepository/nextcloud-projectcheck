/* "AI access (MCP)" settings section — pack JS.
 *
 * Wires: connect dialog (label + preset choice-cards + custom chips),
 * one-time secret dialog (reveal/copy token + copy MCP config), revoke
 * and rotate confirm dialogs, admin self-mint toggle, aria-live
 * announcements. Session + CSRF only — agent credentials can never reach
 * these endpoints (server-side guard).
 */
(function () {
	'use strict';

	var prefix = 'pc';
	var appId = 'projectcheck';
	var root = document.getElementById(prefix + '-agent-access');
	if (!root) {
		return;
	}

	function t(text) {
		if (window.OC && window.OC.L10N && typeof window.OC.L10N.translate === 'function') {
			return window.OC.L10N.translate(appId, text);
		}
		return text;
	}

	function requestToken() {
		if (window.OC && window.OC.requestToken) {
			return window.OC.requestToken;
		}
		var meta = document.querySelector('meta[name="requesttoken"]');
		if (meta) {
			return meta.getAttribute('content') || '';
		}
		var input = document.querySelector('input[name="requesttoken"]');
		return input ? input.value : '';
	}

	function url(path) {
		if (window.OC && typeof window.OC.generateUrl === 'function') {
			return window.OC.generateUrl('/apps/' + appId + path);
		}
		return OC.filePath(appId, '', '') + path;
	}

	function api(path, options) {
		options = options || {};
		var headers = { 'requesttoken': requestToken() };
		if (options.body !== undefined) {
			headers['Content-Type'] = 'application/json';
		}
		return fetch(url(path), {
			method: options.method || 'GET',
			headers: headers,
			body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
			credentials: 'same-origin'
		}).then(function (resp) {
			return resp.json().catch(function () { return {}; }).then(function (data) {
				if (!resp.ok) {
					var err = new Error((data.error && data.error.message) || resp.statusText || 'error');
					err.code = data.error && data.error.code;
					err.fields = data.error && data.error.details && data.error.details.fields;
					throw err;
				}
				return data;
			});
		});
	}

	var stateEl = document.getElementById(prefix + '-agent-state');
	var state = { tokens: [], presets: { read: [], act: [] }, allScopes: [], scopeLabels: {}, canMint: false, isAdmin: false };
	try {
		state = Object.assign(state, JSON.parse(stateEl.textContent || '{}'));
	} catch (e) { /* keep defaults */ }

	var live = document.getElementById(prefix + '-agent-live');
	function announce(msg) {
		if (live) {
			live.textContent = msg;
		}
	}

	function fmtTs(ts) {
		if (!ts) {
			return '';
		}
		var d = new Date(Number(ts) * 1000);
		if (isNaN(d.getTime())) {
			return '';
		}
		return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	// Fill server-rendered timestamp cells.
	Array.prototype.forEach.call(root.querySelectorAll('td[data-ts]'), function (td) {
		var ts = td.getAttribute('data-ts');
		if (ts && !td.textContent.trim()) {
			td.textContent = fmtTs(ts);
		}
	});

	// ── dialogs ──────────────────────────────────────────────────────

	var mintDialog = document.getElementById(prefix + '-agent-mint-dialog');
	var secretDialog = document.getElementById(prefix + '-agent-secret-dialog');
	var revokeDialog = document.getElementById(prefix + '-agent-revoke-dialog');

	function show(d) {
		if (d && typeof d.showModal === 'function') {
			d.showModal();
		}
	}
	function close(d) {
		if (d && d.open) {
			d.close();
		}
	}
	// NC notification handlers can preventDefault the global Escape
	// keydown, suppressing the native <dialog> cancel event — a dialog
	// opened via showModal() would then ignore Escape entirely. Bind a
	// dialog-level close ourselves (host_app_escape_preventdefault).
	function armEscape(dlg) {
		if (dlg) {
			dlg.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					e.preventDefault();
					e.stopPropagation();
					dlg.close();
				}
			});
		}
	}
	armEscape(mintDialog);
	armEscape(secretDialog);
	armEscape(revokeDialog);

	var connectBtn = document.getElementById(prefix + '-agent-connect');
	var labelInput = document.getElementById(prefix + '-agent-label');
	var labelError = document.getElementById(prefix + '-agent-label-error');
	var customWrap = document.getElementById(prefix + '-agent-custom-wrap');
	var chipsBox = document.getElementById(prefix + '-agent-chips');
	var scopesError = document.getElementById(prefix + '-agent-scopes-error');

	if (connectBtn) {
		connectBtn.addEventListener('click', function () {
			buildChips();
			show(mintDialog);
			if (labelInput) {
				labelInput.focus();
			}
		});
	}

	function currentPreset() {
		var r = root.querySelector('input[name="preset"]:checked');
		return r ? r.value : 'read';
	}

	Array.prototype.forEach.call(root.querySelectorAll('input[name="preset"]'), function (radio) {
		radio.addEventListener('change', function () {
			if (customWrap) {
				customWrap.hidden = currentPreset() !== 'custom';
			}
		});
	});

	var chipsBuilt = false;
	function buildChips() {
		if (chipsBuilt || !chipsBox) {
			return;
		}
		chipsBuilt = true;
		chipsBox.textContent = '';
		state.allScopes.forEach(function (scope) {
			var label = document.createElement('label');
			label.className = prefix + '-agent-chip';
			var cb = document.createElement('input');
			cb.type = 'checkbox';
			cb.name = 'scope';
			cb.value = scope;
			var span = document.createElement('span');
			span.textContent = state.scopeLabels[scope] || scope;
			label.appendChild(cb);
			label.appendChild(span);
			chipsBox.appendChild(label);
		});
		if (!state.allScopes.length) {
			var p = document.createElement('p');
			p.className = prefix + '-form-row__hint';
			p.textContent = t('This app exposes no individual permissions.');
			chipsBox.appendChild(p);
		}
	}

	function customScopes() {
		return Array.prototype.map.call(
			chipsBox.querySelectorAll('input[name="scope"]:checked'),
			function (cb) { return cb.value; }
		);
	}

	function fieldError(el, input, msg) {
		if (!el) {
			return;
		}
		if (msg) {
			el.textContent = msg;
			el.hidden = false;
			if (input) {
				input.setAttribute('aria-invalid', 'true');
				input.setAttribute('aria-describedby', el.id);
			}
		} else {
			el.hidden = true;
			if (input) {
				input.removeAttribute('aria-invalid');
			}
		}
	}

	var mintForm = document.getElementById(prefix + '-agent-mint-form');
	var mintSubmit = document.getElementById(prefix + '-agent-mint-submit');
	if (mintForm) {
		mintForm.addEventListener('submit', function (e) {
			e.preventDefault();
			fieldError(labelError, labelInput, '');
			fieldError(scopesError, null, '');

			var label = (labelInput && labelInput.value || '').trim();
			if (!label) {
				fieldError(labelError, labelInput, t('Enter a label.'));
				if (labelInput) {
					labelInput.focus();
				}
				return;
			}
			var preset = currentPreset();
			var body = { label: label, preset: preset };
			if (preset === 'custom') {
				var scopes = customScopes();
				if (!scopes.length) {
					fieldError(scopesError, null, t('Pick at least one permission.'));
					return;
				}
				body.scopes = scopes;
			}

			if (mintSubmit) {
				mintSubmit.disabled = true;
			}
			api('/agent/v1/tokens', { method: 'POST', body: body }).then(function (data) {
				close(mintDialog);
				showSecret(data.secret, data.mcpConfig);
				reloadTokens();
				announce(t('Token created.'));
			}).catch(function (err) {
				var msg = err && err.fields && err.fields.label ? t('Check the label.') : (err.message || t('Could not create the token.'));
				fieldError(labelError, labelInput, msg);
				announce(msg);
			}).finally(function () {
				if (mintSubmit) {
					mintSubmit.disabled = false;
				}
			});
		});
	}

	var mintCancel = document.getElementById(prefix + '-agent-mint-cancel');
	if (mintCancel) {
		mintCancel.addEventListener('click', function () { close(mintDialog); });
	}

	// ── secret dialog ────────────────────────────────────────────────

	var secretInput = document.getElementById(prefix + '-agent-secret');
	var configArea = document.getElementById(prefix + '-agent-config');
	var revealBtn = document.getElementById(prefix + '-agent-secret-reveal');
	var secretClose = document.getElementById(prefix + '-agent-secret-close');

	function showSecret(secret, mcpConfig) {
		if (secretInput) {
			secretInput.type = 'password';
			secretInput.value = secret || '';
		}
		if (revealBtn) {
			revealBtn.setAttribute('aria-pressed', 'false');
			revealBtn.textContent = t('Show');
		}
		if (configArea) {
			configArea.value = JSON.stringify(mcpConfig || {}, null, 2);
		}
		show(secretDialog);
	}

	if (revealBtn) {
		revealBtn.addEventListener('click', function () {
			var showing = secretInput && secretInput.type === 'text';
			if (secretInput) {
				secretInput.type = showing ? 'password' : 'text';
			}
			revealBtn.setAttribute('aria-pressed', showing ? 'false' : 'true');
			revealBtn.textContent = showing ? t('Show') : t('Hide');
		});
	}
	if (secretClose) {
		secretClose.addEventListener('click', function () {
			if (secretInput) {
				secretInput.value = '';
			}
			if (configArea) {
				configArea.value = '';
			}
			close(secretDialog);
		});
	}
	if (secretDialog) {
		secretDialog.addEventListener('close', function () {
			if (pendingReload) {
				window.location.reload();
			}
		});
	}

	function copyText(text, doneMsg) {
		var done = function () { announce(doneMsg); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () {
				announce(t('Copy failed — select the text and copy it manually.'));
			});
			return;
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
			done();
		} catch (e) {
			announce(t('Copy failed — select the text and copy it manually.'));
		}
		document.body.removeChild(ta);
	}

	var secretCopy = document.getElementById(prefix + '-agent-secret-copy');
	if (secretCopy) {
		secretCopy.addEventListener('click', function () {
			copyText(secretInput ? secretInput.value : '', t('Token copied.'));
		});
	}
	var configCopy = document.getElementById(prefix + '-agent-config-copy');
	if (configCopy) {
		configCopy.addEventListener('click', function () {
			copyText(configArea ? configArea.value : '', t('Config copied.'));
		});
	}

	// ── revoke / rotate ──────────────────────────────────────────────

	var revokeText = document.getElementById(prefix + '-agent-revoke-text');
	var revokeConfirm = document.getElementById(prefix + '-agent-revoke-confirm');
	var revokeCancel = document.getElementById(prefix + '-agent-revoke-cancel');
	var pendingAction = null;
	// First-ever mint on a previously-empty account: the server rendered
	// no table-wrap at all, so rebuildTable() cannot inject the new row —
	// reload once the secret dialog closes so canonical markup renders it.
	var pendingReload = false;

	function askConfirm(kind, tokenId, label) {
		pendingAction = { kind: kind, tokenId: tokenId };
		if (revokeText) {
			revokeText.textContent = kind === 'webhook'
				? t('Stop notifications to') + ' "' + label + '"? ' + t('Pending events are dropped — your AI can register a new endpoint any time.')
				: (kind === 'rotate'
					? t('Rotate') + ' "' + label + '"? ' + t('The old token stops working within a minute — you will get a new one.')
					: t('Revoke') + ' "' + label + '"? ' + t('AI clients using it stop working within a minute.'));
		}
		if (revokeConfirm) {
			revokeConfirm.textContent = kind === 'rotate' ? t('Rotate') : t('Revoke');
		}
		show(revokeDialog);
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('.' + prefix + '-agent-revoke');
		if (btn) {
			askConfirm('revoke', btn.getAttribute('data-token-id'), btn.getAttribute('data-token-label') || '');
			return;
		}
		var rot = e.target.closest('.' + prefix + '-agent-rotate');
		if (rot) {
			askConfirm('rotate', rot.getAttribute('data-token-id'), (rot.closest('tr') || {}).getAttribute ? rot.closest('tr').getAttribute('data-token-label') : '');
			return;
		}
		var wt = e.target.closest('.' + prefix + '-agent-webhook-test');
		if (wt) {
			var wtr = wt.closest('tr');
			testWebhook(wtr ? wtr.getAttribute('data-webhook-id') : '', wt);
			return;
		}
		var wr = e.target.closest('.' + prefix + '-agent-webhook-revoke');
		if (wr) {
			var wrr = wr.closest('tr');
			askConfirm('webhook', wrr ? wrr.getAttribute('data-webhook-id') : '', wrr ? wrr.getAttribute('data-webhook-url') : '');
		}
	});

	if (revokeCancel) {
		revokeCancel.addEventListener('click', function () {
			pendingAction = null;
			close(revokeDialog);
		});
	}
	if (revokeConfirm) {
		revokeConfirm.addEventListener('click', function () {
			if (!pendingAction) {
				return;
			}
			var action = pendingAction;
			pendingAction = null;
			revokeConfirm.disabled = true;
			var path = action.kind === 'webhook'
				? '/agent/v1/webhooks/' + encodeURIComponent(action.tokenId)
				: '/agent/v1/tokens/' + encodeURIComponent(action.tokenId) + (action.kind === 'rotate' ? '/rotate' : '/revoke');
			api(path, action.kind === 'webhook' ? { method: 'DELETE' } : { method: 'POST' }).then(function (data) {
				close(revokeDialog);
				if (action.kind === 'webhook') {
					announce(t('Endpoint revoked.'));
					reloadWebhooks();
				} else if (action.kind === 'rotate' && data.secret) {
					showSecret(data.secret, data.mcpConfig);
					announce(t('Token rotated.'));
				} else {
					announce(t('Token revoked.'));
				}
				reloadTokens();
			}).catch(function (err) {
				announce(err.message || t('Action failed.'));
			}).finally(function () {
				revokeConfirm.disabled = false;
			});
		});
	}

	// ── admin: self-mint toggle ─────────────────────────────────────

	var selfMintCb = document.getElementById(prefix + '-agent-selfmint-cb');
	if (selfMintCb) {
		selfMintCb.addEventListener('change', function () {
			var next = selfMintCb.checked;
			selfMintCb.disabled = true;
			api('/agent/v1/policy', { method: 'POST', body: { selfMint: next } }).then(function () {
				state.selfMintEnabled = next;
				announce(next ? t('Users can now create their own AI tokens.') : t('Only administrators can connect AI assistants.'));
			}).catch(function (err) {
				selfMintCb.checked = !next;
				announce(err.message || t('Could not save.'));
			}).finally(function () {
				selfMintCb.disabled = false;
			});
		});
	}

	// Refresh the token table after mutations — cheap full reload of the
	// section's data (index returns tokens + policy).
	function reloadTokens() {
		api('/agent/v1/tokens', { method: 'GET' }).then(function (data) {
			state.tokens = data.tokens || [];
			// Server-rendered tbody is authoritative at load; after a
			// mutation the simplest honest refresh is a page reload only
			// when the table DOM can't be rebuilt — rebuild it instead.
			rebuildTable();
		}).catch(function () {
			/* table stays as-is; the live region already announced */
		});
	}

	function rebuildTable() {
		var tbody = document.getElementById(prefix + '-agent-tbody');
		if (!tbody) {
			if (state.tokens.length) {
				pendingReload = true;
				var staleEmpty = document.getElementById(prefix + '-agent-empty');
				if (staleEmpty) {
					staleEmpty.hidden = true;
				}
			}
			return;
		}
		tbody.textContent = '';
		state.tokens.forEach(function (tok) {
			var tr = document.createElement('tr');
			tr.setAttribute('data-token-id', String(tok.id));
			tr.setAttribute('data-token-label', tok.label || '');
			if (tok.state !== 'active') {
				tr.className = prefix + '-agent-row--inactive';
			}
			tr.appendChild(cell(t('Label'), labelCellHtml(tok), prefix + '-agent-label-cell'));
			tr.appendChild(cell(t('Scopes'), scopeCellHtml(tok), prefix + '-agent-scopes-cell'));
			tr.appendChild(cell(t('Created'), fmtTs(tok.createdAt)));
			tr.appendChild(cell(t('Expires'), fmtTs(tok.expiresAt)));
			tr.appendChild(cell(t('Last used'), tok.lastUsedAt ? fmtTs(tok.lastUsedAt) : t('never')));
			var actions = document.createElement('td');
			actions.className = prefix + '-row-actions';
			if (tok.state === 'active') {
				var rb = document.createElement('button');
				rb.type = 'button';
				rb.className = prefix + '-btn ' + prefix + '-btn--ghost ' + prefix + '-agent-rotate';
				rb.setAttribute('data-token-id', String(tok.id));
				rb.textContent = t('Rotate');
				var db = document.createElement('button');
				db.type = 'button';
				db.className = prefix + '-btn ' + prefix + '-btn--danger ' + prefix + '-agent-revoke';
				db.setAttribute('data-token-id', String(tok.id));
				db.setAttribute('data-token-label', tok.label || '');
				db.textContent = t('Revoke');
				actions.appendChild(rb);
				actions.appendChild(db);
			}
			tr.appendChild(actions);
			tbody.appendChild(tr);
		});
		var empty = document.getElementById(prefix + '-agent-empty');
		var wrap = root.querySelector('.' + prefix + '-agent-tablewrap');
		if (!state.tokens.length) {
			if (wrap) {
				wrap.hidden = true;
			}
			if (!empty && state.canMint) {
				var div = document.createElement('div');
				div.className = prefix + '-agent-empty';
				div.id = prefix + '-agent-empty';
				var p = document.createElement('p');
				p.className = prefix + '-agent-empty__text';
				p.textContent = t('No AI access yet.');
				var b = document.createElement('button');
				b.type = 'button';
				b.className = prefix + '-btn ' + prefix + '-btn--primary';
				b.id = prefix + '-agent-connect';
				b.textContent = t('Connect your AI');
				b.addEventListener('click', function () {
					buildChips();
					show(mintDialog);
				});
				div.appendChild(p);
				div.appendChild(b);
				root.insertBefore(div, root.querySelector('.' + prefix + '-agent-selfmint') || null);
			} else if (empty) {
				empty.hidden = false;
			}
		} else {
			if (wrap) {
				wrap.hidden = false;
			}
			if (empty) {
				empty.hidden = true;
			}
		}
	}

	// ── webhook endpoints ───────────────────────────────────────────
	// Transparency surface: the user sees every endpoint their AI
	// registered — and can test or kill it. Creation itself stays
	// agent-driven (the signed secret is shown once, to the caller).

	var webhookTbody = document.getElementById(prefix + '-agent-webhook-tbody');
	var webhooks = [];

	function testWebhook(id, btn) {
		if (!id) {
			return;
		}
		btn.disabled = true;
		api('/agent/v1/webhooks/' + encodeURIComponent(id) + '/test', { method: 'POST', body: {} }).then(function () {
			announce(t('Test notification queued — check your endpoint.'));
		}).catch(function (err) {
			announce(err.message || t('Could not queue the test.'));
		}).finally(function () {
			btn.disabled = false;
		});
	}

	function reloadWebhooks() {
		if (!webhookTbody) {
			return;
		}
		api('/agent/v1/webhooks', { method: 'GET' }).then(function (data) {
			webhooks = data.webhooks || [];
			rebuildWebhookTable();
		}).catch(function () {
			webhookTbody.textContent = '';
			var tr = document.createElement('tr');
			var td = document.createElement('td');
			td.colSpan = 5;
			td.className = prefix + '-agent-never';
			td.textContent = t('Endpoints could not be loaded.');
			tr.appendChild(td);
			webhookTbody.appendChild(tr);
		});
	}

	function rebuildWebhookTable() {
		webhookTbody.textContent = '';
		if (!webhooks.length) {
			var emptyTr = document.createElement('tr');
			var emptyTd = document.createElement('td');
			emptyTd.colSpan = 5;
			emptyTd.className = prefix + '-agent-never';
			emptyTd.textContent = t('No webhook endpoints yet.');
			emptyTr.appendChild(emptyTd);
			webhookTbody.appendChild(emptyTr);
			return;
		}
		webhooks.forEach(function (w) {
			var tr = document.createElement('tr');
			tr.setAttribute('data-webhook-id', String(w.id));
			tr.setAttribute('data-webhook-url', w.url || '');
			if (w.state !== 'active') {
				tr.className = prefix + '-agent-row--inactive';
			}
			var urlSpan = document.createElement('span');
			urlSpan.className = prefix + '-agent-label';
			urlSpan.textContent = w.url || '';
			urlSpan.title = w.url || '';
			tr.appendChild(cell(t('Endpoint'), urlSpan, prefix + '-agent-label-cell'));
			var evSpan = document.createElement('span');
			evSpan.className = prefix + '-agent-scopes';
			(w.events || []).forEach(function (ev) {
				var b = document.createElement('span');
				b.className = prefix + '-badge ' + prefix + '-badge--neutral ' + prefix + '-agent-scope';
				b.textContent = ev === '*' ? t('All events') : ev;
				evSpan.appendChild(b);
			});
			tr.appendChild(cell(t('Events'), evSpan, prefix + '-agent-scopes-cell'));
			var stateSpan = document.createElement('span');
			stateSpan.className = prefix + '-badge ' + (w.state === 'active' ? prefix + '-badge--info' : prefix + '-badge--neutral');
			stateSpan.textContent = w.state === 'active' ? t('Active') : (w.state === 'disabled' ? t('Disabled') : t('Revoked'));
			tr.appendChild(cell(t('State'), stateSpan));
			var last = w.lastDeliveryAt ? fmtTs(w.lastDeliveryAt) : t('never');
			if (w.lastStatus === 'failed') {
				last += ' — ' + t('failed');
			}
			tr.appendChild(cell(t('Last delivery'), last));
			var actions = document.createElement('td');
			actions.className = prefix + '-row-actions';
			if (w.state === 'active') {
				var tb = document.createElement('button');
				tb.type = 'button';
				tb.className = prefix + '-btn ' + prefix + '-btn--ghost ' + prefix + '-agent-webhook-test';
				tb.textContent = t('Test');
				var rb = document.createElement('button');
				rb.type = 'button';
				rb.className = prefix + '-btn ' + prefix + '-btn--danger ' + prefix + '-agent-webhook-revoke';
				rb.textContent = t('Revoke');
				actions.appendChild(tb);
				actions.appendChild(rb);
			}
			tr.appendChild(actions);
			webhookTbody.appendChild(tr);
		});
	}

	reloadWebhooks();

	function cell(label, inner, cls) {
		var td = document.createElement('td');
		td.setAttribute('data-cell', label);
		if (cls) {
			td.className = cls;
		}
		if (inner instanceof Node) {
			td.appendChild(inner);
		} else {
			td.innerHTML = inner;
		}
		return td;
	}

	function labelCellHtml(tok) {
		// meta_pill_overlap (dc-vis-r62): the wrapper is a flex container
		// (.pc-agent-labelcell) — the td's max-width caps the painted box
		// only, and Blink lays the cell's inline content out at max-content
		// width, so label+badge+owner overflowed ~390px and the scopes
		// cell's opaque pills painted over badge + owner. flex-wrap breaks
		// lines BETWEEN items against the clamped cell width.
		var frag = document.createElement('span');
		frag.className = prefix + '-agent-labelcell';
		var name = document.createElement('span');
		name.className = prefix + '-agent-label';
		name.textContent = tok.label || '';
		name.title = tok.label || '';
		frag.appendChild(name);
		var badge = document.createElement('span');
		badge.className = prefix + '-badge ' + (tok.state === 'active' ? prefix + '-badge--info' : prefix + '-badge--neutral');
		badge.textContent = tok.state === 'active' ? t('Active') : (tok.state === 'revoked' ? t('Revoked') : t('Expired'));
		frag.appendChild(badge);
		if (state.isAdmin && tok.userId) {
			var owner = document.createElement('span');
			owner.className = prefix + '-agent-owner';
			owner.textContent = tok.userId;
			frag.appendChild(owner);
		}
		return frag;
	}

	function scopeCellHtml(tok) {
		var frag = document.createElement('span');
		frag.className = prefix + '-agent-scopes';
		(tok.scopes || []).forEach(function (scope) {
			var badge = document.createElement('span');
			badge.className = prefix + '-badge ' + prefix + '-badge--neutral ' + prefix + '-agent-scope';
			badge.title = scope;
			badge.textContent = state.scopeLabels[scope] || scope;
			frag.appendChild(badge);
		});
		return frag;
	}
})();
