/**
 * ProjectCheck mobile navigation: in-page Menu button + slide-in drawer.
 *
 * No full-screen backdrop — close via Menu, Escape, or tap outside the drawer.
 *
 * @license AGPL-3.0-or-later
 */

/* global document, window, t */
(function () {
	'use strict';

	var APP_ID = 'projectcheck';
	var DESKTOP_MQ = '(min-width: 1024px)';

	var FOCUSABLE_SELECTOR = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join(', ');

	function translate(key, fallback) {
		if (typeof t === 'function') {
			var value = t(APP_ID, key);
			if (value && value !== key) {
				return value;
			}
		}
		return fallback;
	}

	function removeLegacyBackdrop() {
		var legacy = document.getElementById('pc-nav-backdrop');
		if (legacy) {
			legacy.remove();
		}
	}

	function init() {
		removeLegacyBackdrop();

		var toggle = document.querySelector('[data-pc-nav-toggle]');
		var nav = document.getElementById('app-navigation');
		if (!toggle || !nav) {
			return;
		}

		document.body.classList.add('pc-has-app-nav');
		document.body.classList.remove('snapjs-left');

		var coreToggle = document.getElementById('app-navigation-toggle');
		if (coreToggle) {
			coreToggle.setAttribute('aria-hidden', 'true');
			coreToggle.setAttribute('tabindex', '-1');
		}

		var openLabel =
			toggle.getAttribute('data-aria-label-open') ||
			translate('Toggle mobile menu', 'Toggle mobile menu');
		var closeLabel =
			toggle.getAttribute('data-aria-label-close') ||
			translate('Close navigation menu', 'Close navigation menu');

		var trapHandler = null;
		var pointerCloseHandler = null;

		function getFocusableNavItems() {
			return Array.prototype.slice.call(nav.querySelectorAll(FOCUSABLE_SELECTOR));
		}

		function isInsideNavOrToggle(target) {
			if (!(target instanceof Node)) {
				return false;
			}
			return nav.contains(target) || toggle.contains(target);
		}

		function setOpen(open) {
			nav.classList.toggle('pc-nav--open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.setAttribute('aria-label', open ? closeLabel : openLabel);
			document.body.classList.toggle('pc-nav-open', open);
			document.body.classList.remove('snapjs-left');

			if (pointerCloseHandler) {
				document.removeEventListener('click', pointerCloseHandler, true);
				pointerCloseHandler = null;
			}

			if (open) {
				var items = getFocusableNavItems();
				if (items.length > 0) {
					items[0].focus();
				}
				trapHandler = function (event) {
					if (!nav.classList.contains('pc-nav--open') || event.key !== 'Tab') {
						return;
					}
					var focusables = getFocusableNavItems();
					if (focusables.length === 0) {
						return;
					}
					var first = focusables[0];
					var last = focusables[focusables.length - 1];
					if (event.shiftKey && document.activeElement === first) {
						event.preventDefault();
						last.focus();
					} else if (!event.shiftKey && document.activeElement === last) {
						event.preventDefault();
						first.focus();
					}
				};
				document.addEventListener('keydown', trapHandler);

				pointerCloseHandler = function (event) {
					if (!nav.classList.contains('pc-nav--open')) {
						return;
					}
					if (isInsideNavOrToggle(event.target)) {
						return;
					}
					setOpen(false);
					toggle.focus();
				};
				document.addEventListener('click', pointerCloseHandler, true);
			} else if (trapHandler) {
				document.removeEventListener('keydown', trapHandler);
				trapHandler = null;
			}
		}

		toggle.addEventListener('click', function (event) {
			event.stopPropagation();
			setOpen(!nav.classList.contains('pc-nav--open'));
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && nav.classList.contains('pc-nav--open')) {
				setOpen(false);
				toggle.focus();
			}
		});

		var desktopMq = window.matchMedia(DESKTOP_MQ);
		function onViewportChange() {
			if (desktopMq.matches) {
				setOpen(false);
			}
		}
		if (typeof desktopMq.addEventListener === 'function') {
			desktopMq.addEventListener('change', onViewportChange);
		} else if (typeof desktopMq.addListener === 'function') {
			desktopMq.addListener(onViewportChange);
		}
		onViewportChange();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
