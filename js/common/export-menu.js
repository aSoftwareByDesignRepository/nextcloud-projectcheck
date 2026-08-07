/**
 * Shared list-export menu for ProjectCheck.
 *
 * Renders as a disclosure next to list filters. Choosing CSV or JSON fetches
 * the export endpoint with the active filters and triggers a Blob download.
 * Keyboard: Enter/Space/ArrowDown on the toggle, Escape closes (focus returns
 * to the toggle), ArrowUp/Down/Home/End move between menu items.
 *
 * Expected markup (see templates/parts/export-menu.php):
 *   <div class="pc-export" data-pc-export data-export-url="..." data-entity-label="projects">
 *     <button type="button" class="pc-export__toggle" aria-expanded="false" aria-haspopup="menu" aria-controls="...">
 *     <div class="pc-export__menu" role="menu" id="..." hidden>
 *       <button type="button" role="menuitem" data-format="csv">...</button>
 *       <button type="button" role="menuitem" data-format="json">...</button>
 *     </div>
 *   </div>
 *
 * Optional: data-filter-keys="search,status,..." — query params read from
 * matching form controls (id = key with underscores → dashes, or name=key).
 * Optional: data-include-sort="1" — also forwards sort/direction from the URL.
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

/* global OC, t */
(function (global) {
	'use strict';

	/** @type {WeakMap<Element, { close: Function }>} */
	const instances = new WeakMap();

	/**
	 * Non-blocking user feedback. Prefer ProjectCheckNotify (same contract as
	 * the rest of the app: show(message, type)), then OC.Notification, then
	 * the page live regions so screen-reader users still get the result.
	 *
	 * @param {string} message
	 * @param {'success'|'error'|'info'} [type]
	 */
	function notify(message, type) {
		const text = String(message || '').trim();
		if (text === '') {
			return;
		}
		const kind = type || 'info';

		if (global.ProjectCheckNotify) {
			if (kind === 'error' && typeof global.ProjectCheckNotify.error === 'function') {
				global.ProjectCheckNotify.error(text);
			} else if (typeof global.ProjectCheckNotify.show === 'function') {
				global.ProjectCheckNotify.show(text, kind);
			}
			return;
		}

		// ProjectControlMessaging.show(type, message) — opposite arg order.
		if (global.ProjectControlMessaging && typeof global.ProjectControlMessaging.show === 'function') {
			global.ProjectControlMessaging.show(kind, text);
			return;
		}

		if (typeof global.OC !== 'undefined' && OC.Notification && typeof OC.Notification.showTemporary === 'function') {
			OC.Notification.showTemporary(text, kind === 'error' ? { type: 'error' } : undefined);
			return;
		}

		const regionId = kind === 'error' ? 'pc-alert-region' : 'pc-live-region';
		const region = document.getElementById(regionId);
		if (region) {
			region.textContent = text;
			return;
		}
		console[kind === 'error' ? 'error' : 'log'](text);
	}

	/**
	 * Resolve a filter control value for a query key.
	 * Prefers #[id] with dashes (status-filter), then known aliases, then [name=key].
	 *
	 * @param {string} key
	 * @returns {string}
	 */
	function readFilterValue(key) {
		const dashed = key.replace(/_/g, '-');
		/** @type {Record<string, string[]>} */
		const aliases = {
			search: ['search-input', 'project-search', 'customer-search', 'employee-search', 'time-entry-search'],
			customer_id: ['customer-filter'],
			project_id: ['project-filter'],
			user_id: ['user-filter'],
			date_from: ['date-from-filter'],
			date_to: ['date-to-filter'],
			project_type: ['project-type-filter', 'time-entry-project-type-filter'],
			status: ['status-filter'],
			priority: ['priority-filter']
		};
		const extraIds = aliases[key] || [];
		const candidates = [
			document.getElementById(dashed + '-filter'),
			document.getElementById(dashed),
			document.querySelector('[name="' + key + '"]'),
			document.getElementById(key)
		].concat(extraIds.map(function (id) { return document.getElementById(id); }));

		for (let i = 0; i < candidates.length; i++) {
			const el = candidates[i];
			if (el && 'value' in el) {
				return String(el.value || '').trim();
			}
		}
		return '';
	}

	/**
	 * Keep only a safe basename for the download attribute (no path segments).
	 *
	 * @param {string} name
	 * @param {string} fallback
	 * @returns {string}
	 */
	function sanitizeFilename(name, fallback) {
		const raw = String(name || '').trim();
		const base = raw.split(/[/\\]/).pop() || '';
		const cleaned = base.replace(/[^\w.\-()+ ]+/g, '_').replace(/^\.+/, '');
		return cleaned !== '' ? cleaned : fallback;
	}

	/**
	 * @param {HTMLElement} root
	 * @param {string} format
	 * @returns {URL|null}
	 */
	function buildExportUrl(root, format) {
		const exportPath = String(root.getAttribute('data-export-url') || '').trim();
		if (exportPath === '') {
			return null;
		}

		let url;
		try {
			url = new URL(exportPath, window.location.origin);
		} catch (e) {
			return null;
		}

		// Same-origin only — never follow an absolute URL that left the instance.
		if (url.origin !== window.location.origin) {
			return null;
		}

		url.searchParams.set('format', format === 'json' ? 'json' : 'csv');

		const filterKeys = (root.getAttribute('data-filter-keys') || '')
			.split(',')
			.map(function (k) { return k.trim(); })
			.filter(Boolean);

		filterKeys.forEach(function (key) {
			const value = readFilterValue(key);
			if (value !== '') {
				url.searchParams.set(key, value);
			}
		});

		if (root.getAttribute('data-include-sort') === '1') {
			const pageParams = new URL(window.location.href).searchParams;
			const sort = pageParams.get('sort');
			const direction = pageParams.get('direction');
			if (sort) url.searchParams.set('sort', sort);
			if (direction) url.searchParams.set('direction', direction);
		}

		return url;
	}

	/**
	 * @param {BlobPart|Blob} content
	 * @param {string} filename
	 * @param {string} mime
	 * @param {boolean} addBom Unused for server-BOM CSV; kept for API stability
	 */
	function triggerDownload(content, filename, mime, addBom) {
		const parts = (addBom && !(content instanceof Blob)) ? ['\uFEFF', content] : [content];
		const blob = content instanceof Blob && !addBom
			? content
			: new Blob(parts, { type: mime || (content instanceof Blob ? content.type : 'application/octet-stream') });
		const link = document.createElement('a');
		const downloadUrl = URL.createObjectURL(blob);
		link.href = downloadUrl;
		link.download = filename;
		link.rel = 'noopener';
		link.style.visibility = 'hidden';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		// Revoke on next tick so slow browsers finish the download hand-off.
		window.setTimeout(function () {
			URL.revokeObjectURL(downloadUrl);
		}, 0);
	}

	/**
	 * Parse filename from a Content-Disposition header.
	 *
	 * @param {string|null} header
	 * @param {string} fallback
	 * @returns {string}
	 */
	function filenameFromContentDisposition(header, fallback) {
		if (!header) {
			return fallback;
		}
		// RFC 5987: filename*=UTF-8''...
		const star = /filename\*\s*=\s*(?:UTF-8''|utf-8'')([^;]+)/i.exec(header);
		if (star && star[1]) {
			try {
				return sanitizeFilename(decodeURIComponent(star[1].trim().replace(/^["']|["']$/g, '')), fallback);
			} catch (e) {
				// fall through
			}
		}
		const plain = /filename\s*=\s*([^;]+)/i.exec(header);
		if (plain && plain[1]) {
			return sanitizeFilename(plain[1].trim().replace(/^["']|["']$/g, ''), fallback);
		}
		return fallback;
	}

	/**
	 * @param {HTMLElement} root
	 * @param {string} format
	 * @returns {Promise<void>}
	 */
	function runExport(root, format) {
		if (root.getAttribute('data-export-busy') === '1') {
			return Promise.resolve();
		}

		const toggle = root.querySelector('.pc-export__toggle');
		const label = root.querySelector('.pc-export__label');
		const idleLabel = label ? label.textContent : '';
		const entity = root.getAttribute('data-entity-label') || 'items';
		const url = buildExportUrl(root, format);

		if (!url) {
			notify(t('projectcheck', 'Export failed'), 'error');
			return Promise.resolve();
		}

		root.setAttribute('data-export-busy', '1');
		if (toggle) {
			toggle.disabled = true;
			toggle.setAttribute('aria-busy', 'true');
		}
		if (label) {
			label.textContent = t('projectcheck', 'Exporting…');
		}

		return fetch(url.toString(), {
			method: 'GET',
			headers: {
				requesttoken: (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '',
				Accept: 'text/csv, application/json;q=0.9, */*;q=0.1'
			},
			credentials: 'same-origin'
		})
			.then(function (response) {
				const contentType = (response.headers.get('content-type') || '').toLowerCase();
				const disposition = response.headers.get('Content-Disposition') || '';
				const isJson = contentType.indexOf('application/json') !== -1;
				const isAttachment = /attachment/i.test(disposition);

				if (response.status === 429) {
					throw new Error(t('projectcheck', 'Too many export requests. Please wait a moment and try again.'));
				}

				// Errors are JSON DataResponses without Content-Disposition: attachment.
				// Successful JSON exports are also application/json but carry attachment.
				if (!response.ok || (isJson && !isAttachment)) {
					return response.json().catch(function () { return {}; }).then(function (data) {
						throw new Error(
							data && data.error
								? data.error
								: t('projectcheck', 'Export failed')
						);
					});
				}

				const rowCountHeader = response.headers.get('X-ProjectCheck-Export-Row-Count')
					|| response.headers.get('x-projectcheck-export-row-count');
				const formatHeader = response.headers.get('X-ProjectCheck-Export-Format')
					|| response.headers.get('x-projectcheck-export-format');
				const resolvedFormat = formatHeader === 'json' || format === 'json' ? 'json' : 'csv';
				const fallbackName = entity + '_' + new Date().toISOString().slice(0, 10)
					+ (resolvedFormat === 'json' ? '.json' : '.csv');
				const filename = filenameFromContentDisposition(disposition, fallbackName);
				const mime = resolvedFormat === 'json'
					? 'application/json;charset=utf-8'
					: 'text/csv;charset=utf-8';

				let count = Number.parseInt(rowCountHeader || '0', 10);
				if (!Number.isFinite(count) || count < 0) {
					count = 0;
				}

				return response.blob().then(function (blob) {
					// Server already embeds the UTF-8 BOM for CSV — do not add another.
					triggerDownload(blob, filename, mime, false);
					const successKey = root.getAttribute('data-success-message') || 'Exported {count} items';
					notify(t('projectcheck', successKey, { count: count }), 'success');
				});
			})
			.catch(function (error) {
				console.error('Export error:', error);
				const detail = error && error.message ? String(error.message).trim() : '';
				notify(detail !== '' ? detail : t('projectcheck', 'Export failed'), 'error');
			})
			.finally(function () {
				root.removeAttribute('data-export-busy');
				if (toggle) {
					toggle.disabled = false;
					toggle.setAttribute('aria-busy', 'false');
				}
				if (label) {
					label.textContent = idleLabel || t('projectcheck', 'Export');
				}
				if (toggle) {
					toggle.focus();
				}
			});
	}

	/**
	 * @param {HTMLElement} root
	 */
	function bindExportMenu(root) {
		if (instances.has(root)) {
			return;
		}

		const toggle = root.querySelector('.pc-export__toggle');
		const menu = root.querySelector('.pc-export__menu');
		if (!toggle || !menu) {
			return;
		}

		const items = Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]'));

		function setOpen(open) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				menu.hidden = false;
				root.classList.add('pc-export--open');
			} else {
				menu.hidden = true;
				root.classList.remove('pc-export--open');
			}
		}

		function close() {
			setOpen(false);
		}

		function open(focusIndex) {
			setOpen(true);
			const target = items[typeof focusIndex === 'number' ? focusIndex : 0];
			if (target) {
				target.focus();
			}
		}

		function isOpen() {
			return toggle.getAttribute('aria-expanded') === 'true';
		}

		toggle.addEventListener('click', function (event) {
			event.preventDefault();
			if (isOpen()) {
				close();
			} else {
				open(0);
			}
		});

		toggle.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				if (!isOpen()) {
					open(0);
				} else if (event.key === 'ArrowDown' && items[0]) {
					items[0].focus();
				}
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				if (!isOpen()) {
					open(items.length - 1);
				} else if (items[items.length - 1]) {
					items[items.length - 1].focus();
				}
			} else if (event.key === 'Escape' && isOpen()) {
				event.preventDefault();
				close();
				toggle.focus();
			}
		});

		items.forEach(function (item, index) {
			item.addEventListener('click', function (event) {
				event.preventDefault();
				const format = item.getAttribute('data-format') || 'csv';
				close();
				// Return focus to the toggle before the async export starts so
				// keyboard users are not stranded when the menu disappears.
				toggle.focus();
				runExport(root, format);
			});

			item.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					close();
					toggle.focus();
					return;
				}
				if (event.key === 'Tab') {
					// Leave the menu naturally; keep aria-expanded in sync.
					close();
					return;
				}
				if (event.key === 'ArrowDown') {
					event.preventDefault();
					const next = items[(index + 1) % items.length];
					if (next) next.focus();
					return;
				}
				if (event.key === 'ArrowUp') {
					event.preventDefault();
					const prev = items[(index - 1 + items.length) % items.length];
					if (prev) prev.focus();
					return;
				}
				if (event.key === 'Home') {
					event.preventDefault();
					if (items[0]) items[0].focus();
					return;
				}
				if (event.key === 'End') {
					event.preventDefault();
					if (items[items.length - 1]) items[items.length - 1].focus();
					return;
				}
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					item.click();
				}
			});
		});

		document.addEventListener('pointerdown', function (event) {
			if (!root.contains(event.target) && isOpen()) {
				close();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && isOpen()) {
				close();
				toggle.focus();
			}
		});

		instances.set(root, { close: close });
	}

	/**
	 * Initialise every [data-pc-export] on the page.
	 */
	function initAll() {
		const nodes = document.querySelectorAll('[data-pc-export]');
		for (let i = 0; i < nodes.length; i++) {
			bindExportMenu(nodes[i]);
		}
	}

	global.ProjectCheckExportMenu = {
		initAll: initAll,
		bind: bindExportMenu,
		runExport: runExport
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})(typeof window !== 'undefined' ? window : this);
