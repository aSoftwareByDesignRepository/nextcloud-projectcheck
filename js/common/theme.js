/**
 * ProjectCheck theme helper — read-only sync with OCA\Theming and the server-rendered
 * document. Does not set localStorage, does not override :root variables, and does
 * not create in-app “theme toggles” (use Nextcloud → Appearance and accessibility).
 *
 * The layout sets <html data-theme> (light|dark) for legacy selectors, <html data-themes>
 * and <body data-themes> + data-theme-* from \OCA\Theming\Service\ThemesService. Visuals
 * come from core + theming stylesheets, not from hardcoded palettes here.
 */

const ProjectCheckTheme = {
	currentTheme: 'light',

	/**
	 * Call once on DOMContentLoaded. Syncs theme-light / theme-dark on the root element
	 * and keeps <html data-theme> aligned; listens for `theme-changed` (detail.theme).
	 */
	init() {
		this.currentTheme = this.readThemeFromDocument();
		this.applyTheme(this.currentTheme);
		var self = this;
		window.addEventListener(
			'theme-changed',
			function (ev) {
				var t = ev && ev.detail && ev.detail.theme;
				if (t === 'dark' || t === 'light') {
					self.currentTheme = t;
					self.applyTheme(t);
				}
			},
			false
		);
	},

	/**
	 * Resolves a single light|dark for legacy app code. Prefer OCA\Theming’s `data-themes`
	 * list, then <html data-theme>, then 'light'.
	 */
	readThemeFromDocument() {
		var fromList = function (str) {
			if (!str || typeof str !== 'string') {
				return null;
			}
			var p = str.split(/,/).map(function (s) {
				return s.replace(/^\s+|\s+$/g, '');
			}).filter(Boolean);
			if (p.indexOf('dark') !== -1 || p.indexOf('dark-highcontrast') !== -1) {
				return 'dark';
			}
			if (p.length) {
				return 'light';
			}
			return null;
		};
		var h = document.documentElement.getAttribute('data-themes');
		var b = document.body && document.body.getAttribute('data-themes');
		var fromH = fromList(h);
		if (fromH) {
			return fromH;
		}
		var fromB = fromList(b);
		if (fromB) {
			return fromB;
		}
		var attr = document.documentElement.getAttribute('data-theme');
		if (attr === 'dark' || attr === 'light') {
			return attr;
		}
		return 'light';
	},

	getCurrentTheme() {
		return this.readThemeFromDocument() || this.currentTheme || 'light';
	},

	getSystemTheme() {
		if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
			return 'dark';
		}
		return 'light';
	},

	/**
	 * Only updates classes and <html data-theme> for ProjectCheck legacy CSS/JS.
	 * Intentionally does not touch <meta name="theme-color"> (Nextcloud + layout own that).
	 */
	applyTheme(theme) {
		if (theme !== 'dark' && theme !== 'light') {
			theme = 'light';
		}
		this.currentTheme = theme;
		var root = document.documentElement;
		root.classList.remove('theme-light', 'theme-dark');
		root.classList.add('theme-' + theme);
		root.setAttribute('data-theme', theme);
	},

	isDarkTheme() {
		return this.getCurrentTheme() === 'dark';
	},

	isLightTheme() {
		return this.getCurrentTheme() === 'light';
	},

	/**
	 * @returns {object} Debug/telemetry — no `projectcheck-theme` localStorage; theme is
	 *  server-driven. `legacyOverride` is only set if an old value exists (migration clarity).
	 */
	getThemeStats() {
		var legacy = null;
		try {
			legacy = localStorage.getItem('projectcheck-theme');
		} catch (e) {
			legacy = null;
		}
		var htmlList = document.documentElement.getAttribute('data-themes');
		var bodyList = document.body && document.body.getAttribute('data-themes');
		return {
			source: 'nextcloud',
			dataThemes: htmlList || bodyList || null,
			currentTheme: this.getCurrentTheme(),
			systemPrefersDark: this.getSystemTheme() === 'dark',
			/** @type {string|null} stale key from a removed client-side toggle; safe to clear */
			legacyLocalStorage: legacy
		};
	},

	/**
	 * @returns {object} Snapshot for debugging; does not include hardcoded palettes.
	 */
	exportThemeConfig() {
		return {
			currentTheme: this.getCurrentTheme(),
			prefersReducedMotion:
				typeof window.matchMedia === 'function' &&
				window.matchMedia('(prefers-reduced-motion: reduce)').matches,
			dataThemes: document.documentElement.getAttribute('data-themes') || null
		};
	},

	prefersReducedMotion() {
		return (
			typeof window.matchMedia === 'function' &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches
		);
	},

	/** @deprecated Do not add app-level theme UI; use OCA\Theming / personal settings. */
	createThemeToggle() {
		if (typeof console !== 'undefined' && console.warn) {
			console.warn('ProjectCheckTheme.createThemeToggle is not supported; use Nextcloud Appearance settings.');
		}
		return null;
	}
};

if (typeof module !== 'undefined' && module.exports) {
	module.exports = ProjectCheckTheme;
	module.exports.default = ProjectCheckTheme;
}
if (typeof window !== 'undefined') {
	window.ProjectCheckTheme = ProjectCheckTheme;
	window.ProjectControlTheme = ProjectCheckTheme;
}
