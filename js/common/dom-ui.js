/**
 * Shared DOM builders for feedback UI (no innerHTML with dynamic data).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
(function (root) {
	'use strict';

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
	 * @param {unknown} text
	 * @returns {Text}
	 */
	function textNode(text) {
		return document.createTextNode(text == null ? '' : String(text));
	}

	/**
	 * @param {Record<string, string>|undefined} icons
	 * @param {string} type
	 * @returns {string} Lucide catalog key (or legacy emoji fallback)
	 */
	function iconFor(icons, type) {
		if (icons && icons[type]) {
			return icons[type];
		}
		if (window.ProjectCheckIcons && typeof window.ProjectCheckIcons.forStatus === 'function') {
			return window.ProjectCheckIcons.forStatus(type);
		}
		return (icons && icons.info) || 'info';
	}

	/**
	 * @param {HTMLElement} host
	 * @param {string} nameOrGlyph
	 */
	function appendStatusIcon(host, nameOrGlyph) {
		const Icons = window.ProjectCheckIcons;
		// Catalog keys are short kebab / snake tokens; emoji glyphs are not.
		const looksLikeCatalogKey = typeof nameOrGlyph === 'string'
			&& /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(nameOrGlyph);
		if (looksLikeCatalogKey && Icons && typeof Icons.mount === 'function') {
			const icon = createEl('span', 'lucide-icon');
			Icons.mount(icon, nameOrGlyph);
			host.appendChild(icon);
			return;
		}
		if (looksLikeCatalogKey && Icons && typeof Icons.svg === 'function') {
			const icon = createEl('span', 'lucide-icon-host');
			icon.setAttribute('aria-hidden', 'true');
			icon.innerHTML = Icons.svg(nameOrGlyph);
			host.appendChild(icon);
			return;
		}
		host.appendChild(textNode(nameOrGlyph || ''));
	}

	/**
	 * @param {HTMLElement} alert
	 * @param {{ type: string, title?: string, message: string, dismissible?: boolean, dismissLabel: string, icons: Record<string, string> }} opts
	 */
	function populateInlineAlert(alert, opts) {
		const iconWrap = createEl('div', 'alert-icon');
		iconWrap.setAttribute('aria-hidden', 'true');
		appendStatusIcon(iconWrap, iconFor(opts.icons, opts.type));

		const content = createEl('div', 'alert-content');
		if (opts.title) {
			const titleEl = createEl('div', 'alert-title');
			titleEl.appendChild(textNode(opts.title));
			content.appendChild(titleEl);
		}
		const messageEl = createEl('div', 'alert-message');
		messageEl.appendChild(textNode(opts.message));
		content.appendChild(messageEl);

		alert.appendChild(iconWrap);
		alert.appendChild(content);

		if (opts.dismissible !== false) {
			const closeBtn = createEl('button', 'alert-close');
			closeBtn.type = 'button';
			closeBtn.setAttribute('aria-label', opts.dismissLabel);
			const x = createEl('span', 'lucide-icon');
			x.setAttribute('aria-hidden', 'true');
			if (window.ProjectCheckIcons && typeof window.ProjectCheckIcons.mount === 'function') {
				window.ProjectCheckIcons.mount(x, 'x');
			} else {
				x.appendChild(textNode('\u00D7'));
			}
			closeBtn.appendChild(x);
			alert.appendChild(closeBtn);
		}
	}

	/**
	 * @param {{ type: string, title?: string, message: string, dismissible?: boolean, dismissLabel: string, icons: Record<string, string> }} opts
	 * @returns {HTMLDivElement}
	 */
	function buildInlineAlert(opts) {
		const alert = createEl('div', 'alert alert--' + opts.type);
		const liveRole = opts.type === 'error' || opts.type === 'warning' ? 'alert' : 'status';
		alert.setAttribute('role', liveRole);
		alert.setAttribute('aria-live', opts.type === 'error' || opts.type === 'warning' ? 'assertive' : 'polite');
		alert.setAttribute('aria-atomic', 'true');
		populateInlineAlert(alert, opts);
		return alert;
	}

	/**
	 * @param {HTMLElement} toast
	 * @param {{ type: string, title?: string, message: string, dismissible?: boolean, dismissLabel: string, icons: Record<string, string>, actions?: Array<{name:string,label:string,primary?:boolean}> }} opts
	 * @param {(actionsWrap: HTMLElement, actions: Array<{name:string,label:string,primary?:boolean}>) => void} [onActions]
	 */
	function populateToast(toast, opts, onActions) {
		const iconSpan = createEl('span', 'toast-icon');
		iconSpan.setAttribute('aria-hidden', 'true');
		appendStatusIcon(iconSpan, iconFor(opts.icons, opts.type));

		const content = createEl('div', 'toast-content');
		if (opts.title) {
			const titleEl = createEl('div', 'toast-title');
			titleEl.appendChild(textNode(opts.title));
			content.appendChild(titleEl);
		}
		const messageEl = createEl('div', 'toast-message');
		messageEl.appendChild(textNode(opts.message));
		content.appendChild(messageEl);

		if (opts.actions && opts.actions.length > 0 && typeof onActions === 'function') {
			const actionsWrap = createEl('div', 'toast-actions');
			onActions(actionsWrap, opts.actions);
			content.appendChild(actionsWrap);
		}

		toast.appendChild(iconSpan);
		toast.appendChild(content);

		if (opts.dismissible !== false) {
			const closeBtn = createEl('button', 'toast-close');
			closeBtn.type = 'button';
			closeBtn.setAttribute('aria-label', opts.dismissLabel);
			const x = createEl('span');
			x.setAttribute('aria-hidden', 'true');
			x.appendChild(textNode('\u00D7'));
			closeBtn.appendChild(x);
			toast.appendChild(closeBtn);
		}
	}

	/**
	 * @param {string} titleId
	 * @param {{ title: string, message: string, confirmText: string, cancelText: string, type: string }} opts
	 * @returns {HTMLDivElement}
	 */
	function buildConfirmModal(titleId, opts) {
		const modal = createEl('div', 'modal modal--sm modal--' + opts.type);
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-labelledby', titleId);

		const header = createEl('div', 'modal-header');
		const title = createEl('h2', 'modal-title');
		title.id = titleId;
		title.appendChild(textNode(opts.title));
		header.appendChild(title);

		const body = createEl('div', 'modal-body');
		const p = createEl('p');
		p.appendChild(textNode(opts.message));
		body.appendChild(p);

		const footer = createEl('div', 'modal-footer');
		const cancelBtn = createEl('button', 'btn btn--secondary modal-cancel');
		cancelBtn.type = 'button';
		cancelBtn.appendChild(textNode(opts.cancelText));
		const confirmBtn = createEl('button', 'btn btn--' + opts.type + ' modal-confirm');
		confirmBtn.type = 'button';
		confirmBtn.appendChild(textNode(opts.confirmText));
		footer.appendChild(cancelBtn);
		footer.appendChild(confirmBtn);

		modal.appendChild(header);
		modal.appendChild(body);
		modal.appendChild(footer);
		return modal;
	}

	/**
	 * @param {string} titleId
	 * @param {Array<Record<string, unknown>>} history
	 * @param {{ title: string, clearLabel: string, exportLabel: string, closeLabel: string, emptyLabel: string, acknowledgedLabel: string, pendingLabel: string, icons: Record<string, string> }} labels
	 * @returns {HTMLDivElement}
	 */
	function buildHistoryModal(titleId, history, labels) {
		const modal = createEl('div', 'modal modal--lg');
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-labelledby', titleId);

		const header = createEl('div', 'modal-header');
		const title = createEl('h2', 'modal-title');
		title.id = titleId;
		title.appendChild(textNode(labels.title));
		header.appendChild(title);

		const body = createEl('div', 'modal-body');
		const list = createEl('div', 'history-list');

		if (!history.length) {
			const empty = createEl('div', 'history-empty');
			empty.appendChild(textNode(labels.emptyLabel));
			list.appendChild(empty);
		} else {
			history.forEach(function (msg) {
				const item = createEl('div', 'history-item history-item--' + (msg.type || 'info'));
				if (msg.acknowledged) {
					item.classList.add('history-item--acknowledged');
				}
				const icon = createEl('div', 'history-item__icon');
				icon.appendChild(textNode(iconFor(labels.icons, String(msg.type || 'info'))));
				const content = createEl('div', 'history-item__content');
				const itemTitle = createEl('div', 'history-item__title');
				itemTitle.appendChild(textNode(msg.title || ''));
				const itemMsg = createEl('div', 'history-item__message');
				itemMsg.appendChild(textNode(msg.message || ''));
				const ts = createEl('div', 'history-item__timestamp');
				ts.appendChild(textNode(new Date(String(msg.timestamp)).toLocaleString()));
				content.appendChild(itemTitle);
				content.appendChild(itemMsg);
				content.appendChild(ts);
				const status = createEl('div', 'history-item__status');
				status.appendChild(textNode(
					msg.acknowledged
						? '\u2713 ' + labels.acknowledgedLabel
						: '\u26A0 ' + labels.pendingLabel
				));
				item.appendChild(icon);
				item.appendChild(content);
				item.appendChild(status);
				list.appendChild(item);
			});
		}

		body.appendChild(list);

		const footer = createEl('div', 'modal-footer');
		const clearBtn = createEl('button', 'btn btn--secondary modal-clear-history');
		clearBtn.type = 'button';
		clearBtn.appendChild(textNode(labels.clearLabel));
		const exportBtn = createEl('button', 'btn btn--primary modal-export-history');
		exportBtn.type = 'button';
		exportBtn.appendChild(textNode(labels.exportLabel));
		const closeBtn = createEl('button', 'btn btn--secondary modal-cancel');
		closeBtn.type = 'button';
		closeBtn.appendChild(textNode(labels.closeLabel));
		footer.appendChild(clearBtn);
		footer.appendChild(exportBtn);
		footer.appendChild(closeBtn);

		modal.appendChild(header);
		modal.appendChild(body);
		modal.appendChild(footer);
		return modal;
	}

	root.ProjectCheckDom = {
		createEl: createEl,
		textNode: textNode,
		iconFor: iconFor,
		buildInlineAlert: buildInlineAlert,
		populateToast: populateToast,
		buildConfirmModal: buildConfirmModal,
		buildHistoryModal: buildHistoryModal,
	};
})(typeof window !== 'undefined' ? window : globalThis);
