/**
 * Mobile menu and user dropdown for `templates/common/header.php` (legacy
 * layout). Theming: OCA\Theming and core CSS variables only — no local
 * data-theme or localStorage overrides.
 */
(function () {
	'use strict';
	var root = document.querySelector('.pcl-header');
	if (!root) {
		return;
	}
	var hamburger = root.querySelector('.pcl-header__hamburger');
	var mobilePanel = root.querySelector('.pcl-header__mobile-panel');
	var userBtn = root.querySelector('.pcl-header__user-menu-btn');
	var userDrop = root.querySelector('.pcl-header__dropdown--user');

	function setHidden(el, isHidden) {
		if (!el) {
			return;
		}
		if (isHidden) {
			el.setAttribute('hidden', 'hidden');
		} else {
			el.removeAttribute('hidden');
		}
	}

	function closeAll() {
		if (hamburger) {
			hamburger.setAttribute('aria-expanded', 'false');
		}
		if (userBtn) {
			userBtn.setAttribute('aria-expanded', 'false');
		}
		if (userDrop) {
			setHidden(userDrop, true);
			userDrop.setAttribute('aria-hidden', 'true');
		}
		if (mobilePanel) {
			setHidden(mobilePanel, true);
		}
	}

	/* Mobile panel */
	if (hamburger && mobilePanel) {
		mobilePanel.setAttribute('id', mobilePanel.getAttribute('id') || 'pcl-header-mobile');
		if (!hamburger.getAttribute('aria-controls')) {
			hamburger.setAttribute('aria-controls', mobilePanel.getAttribute('id'));
		}
		hamburger.addEventListener('click', function (ev) {
			ev.stopPropagation();
			if (userBtn) {
				userBtn.setAttribute('aria-expanded', 'false');
			}
			if (userDrop) {
				setHidden(userDrop, true);
			}
			if (userDrop) {
				userDrop.setAttribute('aria-hidden', 'true');
			}
			var open = hamburger.getAttribute('aria-expanded') === 'true';
			var next = !open;
			hamburger.setAttribute('aria-expanded', next ? 'true' : 'false');
			setHidden(mobilePanel, !next);
		});
	}

	/* User menu */
	if (userBtn && userDrop) {
		var dropId = userDrop.getAttribute('id') || 'pcl-header-usermenu';
		userDrop.setAttribute('id', dropId);
		if (!userBtn.getAttribute('aria-controls')) {
			userBtn.setAttribute('aria-controls', dropId);
		}
		userDrop.setAttribute('aria-hidden', 'true');
		setHidden(userDrop, true);
		userBtn.addEventListener('click', function (ev) {
			ev.stopPropagation();
			if (hamburger) {
				hamburger.setAttribute('aria-expanded', 'false');
			}
			if (mobilePanel) {
				setHidden(mobilePanel, true);
			}
			var open = userBtn.getAttribute('aria-expanded') === 'true';
			var next = !open;
			userBtn.setAttribute('aria-expanded', next ? 'true' : 'false');
			setHidden(userDrop, !next);
			userDrop.setAttribute('aria-hidden', next ? 'false' : 'true');
		});
	}

	/* Clicks on links inside close menus */
	if (userDrop) {
		userDrop.addEventListener('click', function (ev) {
			if (ev.target && ev.target.tagName === 'A') {
				closeAll();
			}
		});
	}
	if (mobilePanel) {
		mobilePanel.addEventListener('click', function (ev) {
			if (ev.target && ev.target.tagName === 'A') {
				if (hamburger) {
					hamburger.setAttribute('aria-expanded', 'false');
				}
				setHidden(mobilePanel, true);
			}
		});
	}

	/* Click outside */
	document.addEventListener('click', function (e) {
		if (root.contains(e.target)) {
			return;
		}
		closeAll();
	});

	/* Escape (when focus is inside the header) */
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape' || !root.contains(document.activeElement)) {
			return;
		}
		closeAll();
		if (e.target && e.target === userBtn) {
			/* no-op: focus stays on button */
		}
	});
}());
