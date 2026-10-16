/**
 * Shared accessible confirm dialog — the design-system §3.9 replacement for
 * window.confirm (native dialogs are unstyled, unlocalised and blocking).
 *
 * Renders the .pc-settle-modal chrome from css/projects.css (loaded on every
 * app page via Application::boot) and delegates focus trapping, Escape and
 * focus restoration to common/modal-a11y.js (ProjectCheckModalA11y) when
 * available. Single-instance: a second show() while one is open returns false.
 *
 * API: window.ProjectCheckConfirmDialog.show({
 *   title:        string   — dialog heading (aria-labelledby)
 *   message:      string   — body text (aria-describedby)
 *   confirmLabel: string   — confirm button label
 *   trigger:      HTMLElement? — disabled while open, re-enabled + refocused on close
 *   onConfirm:    function(done) — called on confirm; call done() to close
 *   onCancel:     function()?  — called on cancel/escape/backdrop (not on done())
 * })
 * Returns true when the dialog opened.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	let currentRoot = null;

	function isOpen() {
		return currentRoot !== null;
	}

	function show(options) {
		if (currentRoot !== null || !options || typeof options.onConfirm !== 'function') {
			return false;
		}

		if (options.trigger) {
			options.trigger.disabled = true;
		}

		const root = document.createElement('div');
		root.className = 'pc-settle-modal';
		root.style.display = 'flex';

		const backdrop = document.createElement('div');
		backdrop.className = 'pc-settle-modal__backdrop';

		const dialog = document.createElement('div');
		dialog.className = 'pc-settle-modal__dialog';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', 'pc-settle-modal-title');
		dialog.setAttribute('aria-describedby', 'pc-settle-modal-message');

		const title = document.createElement('h2');
		title.id = 'pc-settle-modal-title';
		title.className = 'pc-settle-modal__title';
		title.textContent = options.title || '';

		const message = document.createElement('p');
		message.id = 'pc-settle-modal-message';
		message.className = 'pc-settle-modal__message';
		message.textContent = options.message || '';

		const actions = document.createElement('div');
		actions.className = 'pc-settle-modal__actions';

		const cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button secondary';
		cancelBtn.textContent = options.cancelLabel || t('projectcheck', 'Cancel');

		const confirmBtn = document.createElement('button');
		confirmBtn.type = 'button';
		confirmBtn.className = 'button primary';
		confirmBtn.textContent = options.confirmLabel || t('projectcheck', 'Confirm');

		actions.appendChild(cancelBtn);
		actions.appendChild(confirmBtn);
		dialog.appendChild(title);
		dialog.appendChild(message);
		dialog.appendChild(actions);
		root.appendChild(backdrop);
		root.appendChild(dialog);
		document.body.appendChild(root);
		document.body.style.overflow = 'hidden';
		currentRoot = root;

		let a11yAttached = false;
		let closed = false;

		function closeModal(reason) {
			if (closed) {
				return;
			}
			closed = true;
			currentRoot = null;
			if (a11yAttached && window.ProjectCheckModalA11y && typeof window.ProjectCheckModalA11y.detach === 'function') {
				window.ProjectCheckModalA11y.detach(dialog);
			}
			root.remove();
			document.body.style.overflow = '';
			if (options.trigger) {
				options.trigger.disabled = false;
				options.trigger.focus();
			}
			if (reason !== 'confirm' && typeof options.onCancel === 'function') {
				options.onCancel();
			}
			if (typeof options.onClosed === 'function') {
				options.onClosed();
			}
		}

		if (window.ProjectCheckModalA11y && typeof window.ProjectCheckModalA11y.attach === 'function') {
			a11yAttached = window.ProjectCheckModalA11y.attach(dialog, {
				dismissOnEscape: true,
				dismissOnBackdrop: false,
				initialFocus: cancelBtn,
				onDismiss: function () {
					closeModal('dismiss');
				},
			}) !== null;
		} else {
			cancelBtn.focus();
			dialog.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					closeModal('dismiss');
				}
			});
		}

		backdrop.addEventListener('click', function () {
			closeModal('backdrop');
		});
		cancelBtn.addEventListener('click', function () {
			closeModal('cancel');
		});
		confirmBtn.addEventListener('click', function () {
			confirmBtn.disabled = true;
			cancelBtn.disabled = true;
			options.onConfirm(function () {
				closeModal('confirm');
			});
		});
		return true;
	}

	window.ProjectCheckConfirmDialog = {
		show: show,
		isOpen: isOpen,
	};
})();
