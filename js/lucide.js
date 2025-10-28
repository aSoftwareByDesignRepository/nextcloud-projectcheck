/**
 * Lucide Icons initialization for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

import { createIcons, icons } from 'lucide';

// Function to initialize Lucide icons
function initializeLucideIcons() {
    // Create all Lucide icons
    createIcons(icons, {
        attrs: {
            class: 'lucide-icon',
            'stroke-width': '1.5',
            'stroke': 'currentColor',
            'fill': 'none'
        }
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLucideIcons);
} else {
    // DOM is already ready
    initializeLucideIcons();
}

// Also initialize on window load as fallback
window.addEventListener('load', initializeLucideIcons);

// Export for global access
window.LucideIcons = {
    createIcons,
    icons,
    initialize: initializeLucideIcons
};
