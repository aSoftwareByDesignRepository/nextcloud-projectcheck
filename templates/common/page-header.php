<?php

/**
 * Page Header Component for ProjectControl App
 * 
 * This template provides a reusable page header component with title, subtitle,
 * breadcrumbs, and action buttons.
 */

// Ensure this file is being included within Nextcloud
if (!defined('OCP\AppFramework\App::class')) {
    die('Direct access not allowed');
}

// Get page header data
$pageTitle = isset($pageTitle) ? $pageTitle : $l->t('Page Title');
$pageSubtitle = isset($pageSubtitle) ? $pageSubtitle : '';
$breadcrumbs = isset($breadcrumbs) ? $breadcrumbs : [];
$actions = isset($actions) ? $actions : [];
$showBreadcrumbs = isset($showBreadcrumbs) ? $showBreadcrumbs : true;
$showActions = isset($showActions) ? $showActions : true;

$appName = 'projectcheck';
?>
<div class="page-header">
    <div class="page-header__content">
        <!-- Breadcrumbs -->
        <?php if ($showBreadcrumbs && !empty($breadcrumbs)): ?>
            <nav class="page-header__breadcrumbs" aria-label="Breadcrumb navigation">
                <ol class="page-header__breadcrumb-list">
                    <li class="page-header__breadcrumb-item">
                        <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>"
                            class="page-header__breadcrumb-link">
                            <span class="page-header__breadcrumb-icon">🏠</span>
                            <span class="page-header__breadcrumb-text"><?php p($l->t('Home')); ?></span>
                        </a>
                    </li>

                    <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
                        <li class="page-header__breadcrumb-item">
                            <?php if ($index === count($breadcrumbs) - 1): ?>
                                <!-- Current page -->
                                <span class="page-header__breadcrumb-current">
                                    <?php if (isset($breadcrumb['icon'])): ?>
                                        <span class="page-header__breadcrumb-icon"><?php p($breadcrumb['icon']); ?></span>
                                    <?php endif; ?>
                                    <span class="page-header__breadcrumb-text"><?php p($breadcrumb['text']); ?></span>
                                </span>
                            <?php else: ?>
                                <!-- Link to previous page -->
                                <a href="<?php print_unescaped($breadcrumb['url']); ?>"
                                    class="page-header__breadcrumb-link">
                                    <?php if (isset($breadcrumb['icon'])): ?>
                                        <span class="page-header__breadcrumb-icon"><?php p($breadcrumb['icon']); ?></span>
                                    <?php endif; ?>
                                    <span class="page-header__breadcrumb-text"><?php p($breadcrumb['text']); ?></span>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <!-- Page Title and Subtitle -->
        <div class="page-header__title-section">
            <h1 class="page-header__title">
                <?php if (isset($pageIcon)): ?>
                    <span class="page-header__title-icon"><?php p($pageIcon); ?></span>
                <?php endif; ?>
                <span class="page-header__title-text"><?php p($pageTitle); ?></span>
            </h1>

            <?php if ($pageSubtitle): ?>
                <p class="page-header__subtitle"><?php p($pageSubtitle); ?></p>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <?php if ($showActions && !empty($actions)): ?>
            <div class="page-header__actions">
                <?php foreach ($actions as $action): ?>
                    <?php if (isset($action['type']) && $action['type'] === 'button'): ?>
                        <button type="button"
                            class="page-header__action-btn <?php echo isset($action['variant']) ? 'page-header__action-btn--' . $action['variant'] : ''; ?>"
                            <?php echo isset($action['onclick']) ? 'onclick="' . $action['onclick'] . '"' : ''; ?>
                            <?php echo isset($action['disabled']) && $action['disabled'] ? 'disabled' : ''; ?>
                            title="<?php echo isset($action['title']) ? $action['title'] : $action['text']; ?>">
                            <?php if (isset($action['icon'])): ?>
                                <span class="page-header__action-icon"><?php p($action['icon']); ?></span>
                            <?php endif; ?>
                            <span class="page-header__action-text"><?php p($action['text']); ?></span>
                        </button>
                    <?php elseif (isset($action['type']) && $action['type'] === 'link'): ?>
                        <a href="<?php print_unescaped($action['url']); ?>"
                            class="page-header__action-btn <?php echo isset($action['variant']) ? 'page-header__action-btn--' . $action['variant'] : ''; ?>"
                            <?php echo isset($action['target']) ? 'target="' . $action['target'] . '"' : ''; ?>
                            <?php echo isset($action['rel']) ? 'rel="' . $action['rel'] . '"' : ''; ?>
                            title="<?php echo isset($action['title']) ? $action['title'] : $action['text']; ?>">
                            <?php if (isset($action['icon'])): ?>
                                <span class="page-header__action-icon"><?php p($action['icon']); ?></span>
                            <?php endif; ?>
                            <span class="page-header__action-text"><?php p($action['text']); ?></span>
                        </a>
                    <?php elseif (isset($action['type']) && $action['type'] === 'dropdown'): ?>
                        <div class="page-header__dropdown">
                            <button type="button"
                                class="page-header__action-btn page-header__action-btn--dropdown"
                                aria-expanded="false"
                                aria-haspopup="true"
                                title="<?php echo isset($action['title']) ? $action['title'] : $action['text']; ?>">
                                <?php if (isset($action['icon'])): ?>
                                    <span class="page-header__action-icon"><?php p($action['icon']); ?></span>
                                <?php endif; ?>
                                <span class="page-header__action-text"><?php p($action['text']); ?></span>
                                <span class="page-header__action-arrow">▼</span>
                            </button>

                            <?php if (isset($action['items']) && !empty($action['items'])): ?>
                                <div class="page-header__dropdown-menu" style="display: none;">
                                    <ul class="page-header__dropdown-list">
                                        <?php foreach ($action['items'] as $item): ?>
                                            <li class="page-header__dropdown-item">
                                                <?php if (isset($item['type']) && $item['type'] === 'link'): ?>
                                                    <a href="<?php print_unescaped($item['url']); ?>"
                                                        class="page-header__dropdown-link"
                                                        <?php echo isset($item['target']) ? 'target="' . $item['target'] . '"' : ''; ?>
                                                        <?php echo isset($item['rel']) ? 'rel="' . $item['rel'] . '"' : ''; ?>>
                                                        <?php if (isset($item['icon'])): ?>
                                                            <span class="page-header__dropdown-icon"><?php p($item['icon']); ?></span>
                                                        <?php endif; ?>
                                                        <span class="page-header__dropdown-text"><?php p($item['text']); ?></span>
                                                    </a>
                                                <?php elseif (isset($item['type']) && $item['type'] === 'button'): ?>
                                                    <button type="button"
                                                        class="page-header__dropdown-link"
                                                        <?php echo isset($item['onclick']) ? 'onclick="' . $item['onclick'] . '"' : ''; ?>>
                                                        <?php if (isset($item['icon'])): ?>
                                                            <span class="page-header__dropdown-icon"><?php p($item['icon']); ?></span>
                                                        <?php endif; ?>
                                                        <span class="page-header__dropdown-text"><?php p($item['text']); ?></span>
                                                    </button>
                                                <?php elseif (isset($item['type']) && $item['type'] === 'divider'): ?>
                                                    <hr class="page-header__dropdown-divider">
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Page Header Meta Information -->
    <?php if (isset($pageMeta) && !empty($pageMeta)): ?>
        <div class="page-header__meta">
            <div class="page-header__meta-content">
                <?php foreach ($pageMeta as $meta): ?>
                    <div class="page-header__meta-item">
                        <?php if (isset($meta['icon'])): ?>
                            <span class="page-header__meta-icon"><?php p($meta['icon']); ?></span>
                        <?php endif; ?>
                        <span class="page-header__meta-label"><?php p($meta['label']); ?>:</span>
                        <span class="page-header__meta-value"><?php p($meta['value']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?php p($_['cspNonce'] ?? '') ?>">
    // Page header functionality
    document.addEventListener('DOMContentLoaded', function() {
        const pageHeader = document.querySelector('.page-header');

        if (pageHeader) {
            const dropdowns = pageHeader.querySelectorAll('.page-header__dropdown');

            // Handle dropdown toggles
            dropdowns.forEach(dropdown => {
                const button = dropdown.querySelector('.page-header__action-btn--dropdown');
                const menu = dropdown.querySelector('.page-header__dropdown-menu');

                if (button && menu) {
                    button.addEventListener('click', function(event) {
                        event.stopPropagation();

                        const isExpanded = this.getAttribute('aria-expanded') === 'true';
                        this.setAttribute('aria-expanded', !isExpanded);
                        menu.style.display = isExpanded ? 'none' : 'block';
                    });
                }
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                if (!pageHeader.contains(event.target)) {
                    dropdowns.forEach(dropdown => {
                        const button = dropdown.querySelector('.page-header__action-btn--dropdown');
                        const menu = dropdown.querySelector('.page-header__dropdown-menu');

                        if (button && menu) {
                            button.setAttribute('aria-expanded', 'false');
                            menu.style.display = 'none';
                        }
                    });
                }
            });

            // Handle keyboard navigation for dropdowns
            dropdowns.forEach(dropdown => {
                const button = dropdown.querySelector('.page-header__action-btn--dropdown');
                const menu = dropdown.querySelector('.page-header__dropdown-menu');
                const links = menu ? menu.querySelectorAll('.page-header__dropdown-link') : [];

                if (button && menu) {
                    button.addEventListener('keydown', function(event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            this.click();
                        } else if (event.key === 'Escape') {
                            this.setAttribute('aria-expanded', 'false');
                            menu.style.display = 'none';
                        }
                    });

                    // Handle keyboard navigation within dropdown menu
                    links.forEach((link, index) => {
                        link.addEventListener('keydown', function(event) {
                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                const nextLink = links[index + 1];
                                if (nextLink) {
                                    nextLink.focus();
                                }
                            } else if (event.key === 'ArrowUp') {
                                event.preventDefault();
                                const prevLink = links[index - 1];
                                if (prevLink) {
                                    prevLink.focus();
                                } else {
                                    button.focus();
                                }
                            } else if (event.key === 'Escape') {
                                button.setAttribute('aria-expanded', 'false');
                                menu.style.display = 'none';
                                button.focus();
                            }
                        });
                    });
                }
            });
        }
    });
</script>