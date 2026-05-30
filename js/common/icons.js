/**
 * Centralized SVG icon catalog for ProjectCheck.
 *
 * Audit reference: AUDIT-FINDINGS H22 (icon-dedup) - the same lucide-style
 * icons were inlined into six different page templates, drifting in size,
 * stroke and naming. This module is the single source of truth.
 *
 * Usage in templates: simply place a placeholder element with a
 * `data-lucide="icon-name"` attribute, e.g.
 *     <span data-lucide="users" aria-hidden="true"></span>
 * The catalog auto-hydrates these placeholders on DOMContentLoaded and
 * keeps them up-to-date when new nodes are added (MutationObserver).
 *
 * Programmatic API:
 *     window.ProjectCheckIcons.svg('users')   // returns the SVG markup
 *     window.ProjectCheckIcons.hydrate(root?) // re-hydrate a subtree
 *     window.ProjectCheckIcons.list()         // array of available names
 *
 * Security: every entry is a trusted, audited literal. We deliberately
 * do not accept user-supplied SVG markup anywhere; `hydrate()` only ever
 * looks up icons by name from this map.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
(function (global) {
    'use strict';

    if (global.ProjectCheckIcons && typeof global.ProjectCheckIcons.hydrate === 'function') {
        return;
    }

    // Shared SVG attributes - kept identical across the catalog so visual
    // density is consistent. Width/height are 1em so the icon scales with
    // surrounding text; aria-hidden defaults to true because callers add
    // a wrapping element with the accessible name.
    const SVG_OPEN = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide-icon" aria-hidden="true" focusable="false">';
    const SVG_CLOSE = '</svg>';

    const PATHS = {
        activity: '<polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/>',
        'alert-circle': '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'arrow-left': '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
        'bar-chart-3': '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/>',
        'chevron-down': '<polyline points="6,9 12,15 18,9"/>',
        clock: '<circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>',
        'dollar-sign': '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        edit: '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        euro: '<path d="M4 10h12"/><path d="M4 14h9"/><path d="M19 6a7.7 7.7 0 0 0-5.2-2A7.9 7.9 0 0 0 6 12c0 4.4 3.5 8 7.8 8 2 0 3.8-.8 5.2-2"/>',
        eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        folder: '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
        'file-text': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        tag: '<path d="M20.6 13.4 13 21l-9-9V4h8l8 8a2.8 2.8 0 0 1 .6 1.4Z"/><circle cx="7" cy="7" r="1"/>',
        home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>',
        info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'loader-2': '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
        mail: '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        percent: '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'pie-chart': '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        play: '<polygon points="5,3 19,12 5,21"/>',
        plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        save: '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/>',
        search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'search-x': '<path d="m13.5 8.5-5 5"/><path d="m8.5 8.5 5 5"/><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'rotate-ccw': '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'shield-check': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'shield-alert': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'trending-up': '<polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline points="16,7 22,7 22,13"/>',
        trophy: '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 1 0 5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21l-1.5.5A2 2 0 0 1 5 17v-2.34"/><path d="M14 14.66V17c0 .55.47.98.97 1.21l1.5.5A2 2 0 0 0 19 17v-2.34"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        wallet: '<path d="M19 7H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>',
        x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'trash-2': '<polyline points="3,6 5,6 21,6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'upload-cloud': '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><polyline points="16,16 12,12 8,16"/><line x1="12" y1="12" x2="12" y2="21"/>',
        download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'file-plus': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14,2 14,8 20,8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
    };

    /** Legacy template class names → catalog keys (M6 icon migration). */
    const LEGACY_CLASS_ICONS = {
        'icon-calendar-custom': 'calendar',
        'icon-time-custom': 'clock',
        'icon-money-custom': 'euro',
        'icon-user-custom': 'user',
        'icon-chart-custom': 'bar-chart-3',
        'icon-add-custom': 'plus',
        'icon-info-custom': 'info',
        'icon-tag-custom': 'tag',
        'icon-edit-custom': 'edit',
        'icon-delete-custom': 'trash-2',
        'icon-folder-custom': 'folder',
        'icon-file-custom': 'file-text',
    };

    Object.keys(LEGACY_CLASS_ICONS).forEach(function (legacyClass) {
        const key = LEGACY_CLASS_ICONS[legacyClass];
        if (PATHS[key]) {
            PATHS[legacyClass] = PATHS[key];
        }
    });

    function svg(name) {
        const body = PATHS[name];
        if (!body) {
            return '';
        }
        return SVG_OPEN + body + SVG_CLOSE;
    }

    function hydrateLegacyClassIcons(scope) {
        Object.keys(LEGACY_CLASS_ICONS).forEach(function (legacyClass) {
            scope.querySelectorAll('.' + legacyClass).forEach(function (el) {
                if (el.getAttribute('data-lucide-hydrated') === '1') {
                    return;
                }
                const markup = svg(legacyClass);
                if (markup === '') {
                    return;
                }
                el.innerHTML = markup;
                el.classList.add('lucide-icon-host');
                el.setAttribute('data-lucide-hydrated', '1');
            });
        });
    }

    function nodeHasLegacyIconClass(node) {
        if (!node || !node.classList) {
            return false;
        }
        return Object.keys(LEGACY_CLASS_ICONS).some(function (legacyClass) {
            return node.classList.contains(legacyClass);
        });
    }

    function ensureIconHostClass(scope) {
        scope.querySelectorAll('[data-lucide-hydrated="1"]:not(.lucide-icon-host)').forEach(function (el) {
            el.classList.add('lucide-icon-host');
        });
    }

    function hydrate(root) {
        const scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        ensureIconHostClass(scope);
        hydrateLegacyClassIcons(scope);
        scope.querySelectorAll('[data-lucide]').forEach(function (el) {
            // Idempotent: skip already-hydrated nodes so MutationObserver
            // re-runs are cheap.
            if (el.getAttribute('data-lucide-hydrated') === '1') {
                return;
            }
            const name = el.getAttribute('data-lucide');
            const markup = svg(name);
            if (markup === '') {
                return;
            }
            el.innerHTML = markup;
            el.classList.add('lucide-icon-host');
            el.setAttribute('data-lucide-hydrated', '1');
        });
    }

    function list() {
        return Object.keys(PATHS).slice();
    }

    global.ProjectCheckIcons = {
        svg: svg,
        hydrate: hydrate,
        list: list,
    };

    function init() {
        hydrate(document);

        // Hydrate icons inserted by client-side rendering (modals, AJAX,
        // etc.) without each page needing to call hydrate() manually.
        if (typeof global.MutationObserver === 'function') {
            const observer = new MutationObserver(function (mutations) {
                for (let i = 0; i < mutations.length; i++) {
                    const m = mutations[i];
                    if (!m.addedNodes) continue;
                    for (let j = 0; j < m.addedNodes.length; j++) {
                        const node = m.addedNodes[j];
                        if (node && node.nodeType === 1) {
                            if (
                                node.matches
                                && (node.matches('[data-lucide]') || nodeHasLegacyIconClass(node))
                            ) {
                                hydrate(node.parentNode || node);
                            } else if (node.querySelectorAll) {
                                hydrate(node);
                            }
                        }
                    }
                }
            });
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
