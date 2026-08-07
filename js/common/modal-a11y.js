/**
 * Shared accessibility helper for in-app modals.
 *
 * Audit reference: AUDIT-FINDINGS.md C13 (inconsistent modal behavior) and
 * D17 (incomplete focus management). The pre-existing modal code in
 * `messaging.js` and `components.js` opened/closed modals but did not trap
 * focus, did not restore focus, and only inconsistently bound Escape and
 * backdrop dismissal.
 *
 * This module owns one cross-cutting concern: making every modal that opts
 * in (by registering with `attach()`) WCAG 2.1 AA compliant for keyboard
 * users. It is deliberately small and side-effect-free until `attach()` is
 * called.
 *
 * Public API: `window.ProjectCheckModalA11y`.
 */

/* global document, window */
(function (root) {
	'use strict';

	const FOCUSABLE_SELECTOR = [
		'a[href]:not([tabindex="-1"])',
		'area[href]:not([tabindex="-1"])',
		'input:not([disabled]):not([type="hidden"]):not([tabindex="-1"])',
		'select:not([disabled]):not([tabindex="-1"])',
		'textarea:not([disabled]):not([tabindex="-1"])',
		'button:not([disabled]):not([tabindex="-1"])',
		'iframe:not([tabindex="-1"])',
		'details:not([tabindex="-1"])',
		'summary:not([tabindex="-1"])',
		'[contenteditable="true"]:not([tabindex="-1"])',
		'[tabindex]:not([tabindex="-1"])',
	].join(',');

	const stack = [];

	function isVisible(el) {
		if (!(el instanceof HTMLElement)) {
			return false;
		}
		if (el.hidden) {
			return false;
		}
		const style = window.getComputedStyle(el);
		if (style.display === 'none' || style.visibility === 'hidden') {
			return false;
		}
		const rect = el.getBoundingClientRect();
		return rect.width > 0 && rect.height > 0;
	}

	function focusables(container) {
		const list = container.querySelectorAll(FOCUSABLE_SELECTOR);
		const result = [];
		for (let i = 0; i < list.length; i++) {
			if (isVisible(list[i])) {
				result.push(list[i]);
			}
		}
		return result;
	}

	function topOfStack() {
		return stack.length ? stack[stack.length - 1] : null;
	}

	function focusElement(el) {
		if (!el) {
			return;
		}
		try {
			el.focus({ preventScroll: false });
		} catch (e) {
			el.focus();
		}
	}

	function onKeyDown(event) {
		const top = topOfStack();
		if (!top) {
			return;
		}
		if (event.key === 'Escape') {
			if (top.options.dismissOnEscape !== false) {
				event.preventDefault();
				event.stopPropagation();
				detach(top.modal, { reason: 'escape' });
			}
			return;
		}
		if (event.key === 'Tab') {
			const items = focusables(top.modal);
			if (items.length === 0) {
				event.preventDefault();
				focusElement(top.modal);
				return;
			}
			const first = items[0];
			const last = items[items.length - 1];
			if (event.shiftKey) {
				if (document.activeElement === first || !top.modal.contains(document.activeElement)) {
					event.preventDefault();
					focusElement(last);
				}
			} else if (document.activeElement === last) {
				event.preventDefault();
				focusElement(first);
			}
		}
	}

	function onFocusIn(event) {
		const top = topOfStack();
		if (!top) {
			return;
		}
		if (top.modal.contains(event.target)) {
			return;
		}
		// Re-trap focus inside the modal.
		const items = focusables(top.modal);
		focusElement(items[0] || top.modal);
	}

	function onBackdropClick(event) {
		const top = topOfStack();
		if (!top || !top.options.dismissOnBackdrop) {
			return;
		}
		// Only dismiss when the click is on the backdrop itself.
		const backdrop = top.modal.closest('.modal-backdrop');
		if (event.target === backdrop) {
			event.preventDefault();
			detach(top.modal, { reason: 'backdrop' });
		}
	}

	function attach(modal, options) {
		if (!(modal instanceof HTMLElement)) {
			return null;
		}
		const opts = Object.assign({
			dismissOnEscape: true,
			dismissOnBackdrop: true,
			restoreFocus: true,
			onDismiss: null,
			initialFocus: null,
		}, options || {});

		// Ensure proper ARIA semantics.
		if (!modal.hasAttribute('role')) {
			modal.setAttribute('role', 'dialog');
		}
		modal.setAttribute('aria-modal', 'true');
		if (!modal.hasAttribute('tabindex')) {
			modal.setAttribute('tabindex', '-1');
		}

		const restoreTo = opts.restoreFocus
			? (document.activeElement instanceof HTMLElement ? document.activeElement : null)
			: null;

		const entry = { modal: modal, options: opts, restoreTo: restoreTo };

		if (stack.length === 0) {
			document.addEventListener('keydown', onKeyDown, true);
			document.addEventListener('focusin', onFocusIn, true);
			document.addEventListener('mousedown', onBackdropClick, true);
		}

		stack.push(entry);

		// Move initial focus inside the modal (use either an explicit selector
		// or the first focusable element; otherwise focus the modal itself).
		setTimeout(function () {
			let target = null;
			if (typeof opts.initialFocus === 'string') {
				target = modal.querySelector(opts.initialFocus);
			} else if (opts.initialFocus instanceof HTMLElement) {
				target = opts.initialFocus;
			}
			if (!target) {
				const items = focusables(modal);
				target = items[0] || modal;
			}
			focusElement(target);
		}, 0);

		return entry;
	}

	function detach(modal, info) {
		const idx = stack.findIndex(function (e) { return e.modal === modal; });
		if (idx === -1) {
			return;
		}
		const entry = stack.splice(idx, 1)[0];

		if (stack.length === 0) {
			document.removeEventListener('keydown', onKeyDown, true);
			document.removeEventListener('focusin', onFocusIn, true);
			document.removeEventListener('mousedown', onBackdropClick, true);
		}

		if (typeof entry.options.onDismiss === 'function') {
			try {
				entry.options.onDismiss(info || {});
			} catch (e) { /* swallow callback errors */ }
		}

		if (entry.options.restoreFocus && entry.restoreTo) {
			focusElement(entry.restoreTo);
		}
	}

	root.ProjectCheckModalA11y = {
		attach: attach,
		detach: detach,
		focusables: focusables,
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = root.ProjectCheckModalA11y;
	}
})(typeof window !== 'undefined' ? window : globalThis);
