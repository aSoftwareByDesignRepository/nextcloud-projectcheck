<?php

/**
 * Base Layout Template for ProjectControl App
 * 
 * This template provides the foundation HTML structure that all pages extend.
 * It includes common meta tags, CSS, JavaScript, and semantic HTML structure.
 */

// Ensure this file is being included within Nextcloud
if (!defined('OCP\AppFramework\App::class')) {
    die('Direct access not allowed');
}

// Get the current user and app context
$user = \OC::$server->getUserSession()->getUser();
$appName = 'projectcheck';
$appVersion = \OC::$server->getAppManager()->getAppVersion($appName);

// Get the page title and meta information
$pageTitle = isset($pageTitle) ? $pageTitle : $l->t('ProjectControl');
$pageDescription = isset($pageDescription) ? $pageDescription : $l->t('Project and time management for Nextcloud');
$pageKeywords = isset($pageKeywords) ? $pageKeywords : $l->t('project, time, management, nextcloud');

// Inherit theme from Nextcloud without app overrides; fallback to light
$theme = 'light';
try {
    $theming = \OC::$server->getThemingDefaults();
    $primary = $theming->getColorPrimary();
    // Compute simple luminance heuristic from hex color to decide dark vs light
    if (is_string($primary) && preg_match('/^#?([0-9a-fA-F]{6})$/', $primary, $m)) {
        $hex = $m[1];
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        $theme = ($luminance < 96) ? 'dark' : 'light';
    }
    // Optional admin-only QA override via app config and query param
    try {
        /** @var \OCP\IConfig $config */
        $config = \OC::$server->getConfig();
        $allowOverride = $config->getAppValue($appName, 'theme_dev_override', 'no') === 'yes';
        if ($allowOverride && isset($_GET['theme'])) {
            $q = $_GET['theme'];
            if ($q === 'dark' || $q === 'light') {
                $theme = $q;
            }
        }
    } catch (\Throwable $ie) {
        // ignore
    }
} catch (\Throwable $e) {
    $theme = 'light';
}
?>
<!DOCTYPE html>
<html lang="<?php p($_['language']); ?>" data-theme="<?php p($theme); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php p($pageDescription); ?>">
    <meta name="keywords" content="<?php p($pageKeywords); ?>">
    <meta name="author" content="Nextcloud">
    <meta name="robots" content="noindex, nofollow">

    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">

    <!-- Theme Color -->
    <meta name="theme-color" content="#0082c9">
    <meta name="msapplication-TileColor" content="#0082c9">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php print_unescaped(image_path($appName, 'favicon.png')); ?>">
    <link rel="apple-touch-icon" href="<?php print_unescaped(image_path($appName, 'apple-touch-icon.png')); ?>">

    <!-- Page Title -->
    <title><?php p($pageTitle); ?> - <?php p($l->t('ProjectControl')); ?></title>

    <!-- Nextcloud Core Styles -->
    <?php foreach ($_['cssfiles'] as $cssfile): ?>
        <link rel="stylesheet" href="<?php print_unescaped($cssfile); ?>">
    <?php endforeach; ?>

    <!-- ProjectControl Common Styles -->
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/colors.css')); ?>">
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/typography.css')); ?>">
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/utilities.css')); ?>">
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/base.css')); ?>">
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/layout.css')); ?>">
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/components.css')); ?>">
    <link rel="stylesheet" href="<?php print_unescaped(link_to($appName, 'css/common/accessibility.css')); ?>">

    <!-- Page Specific Styles -->
    <?php if (isset($pageStyles)): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link rel="stylesheet" href="<?php print_unescaped($style); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Nextcloud Core Scripts -->
    <?php foreach ($_['jsfiles'] as $jsfile): ?>
        <script src="<?php print_unescaped($jsfile); ?>"></script>
    <?php endforeach; ?>

    <!-- ProjectControl Common Scripts -->
    <script src="<?php print_unescaped(link_to($appName, 'js/common/layout.js')); ?>"></script>
    <script src="<?php print_unescaped(link_to($appName, 'js/common/components.js')); ?>"></script>
    <script src="<?php print_unescaped(link_to($appName, 'js/common/utils.js')); ?>"></script>
    <script src="<?php print_unescaped(link_to($appName, 'js/common/theme.js')); ?>"></script>
    <script src="<?php print_unescaped(link_to($appName, 'js/common/validation.js')); ?>"></script>
    <script src="<?php print_unescaped(link_to($appName, 'js/common/messaging.js')); ?>"></script>

    <!-- Page Specific Scripts -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php print_unescaped($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline Styles for Critical CSS -->
    <style nonce="<?php p($_['cspNonce'] ?? '') ?>">
        /* Critical CSS for above-the-fold content */
        .page-layout {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .page-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .page-content {
            flex: 1;
            padding: 1.5rem 0;
        }

        .container {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        @media (min-width: 640px) {
            .container {
                max-width: 640px;
            }
        }

        @media (min-width: 768px) {
            .container {
                max-width: 768px;
            }
        }

        @media (min-width: 1024px) {
            .container {
                max-width: 1024px;
            }
        }

        @media (min-width: 1280px) {
            .container {
                max-width: 1280px;
            }
        }
    </style>
</head>

<body id="<?php p($_['bodyid']); ?>" class="<?php p($_['bodyclass']); ?>">
    <!-- Skip Link for Accessibility -->
    <a href="#main-content" class="skip-link"><?php p($l->t('Skip to main content')); ?></a>

    <!-- Page Layout Container -->
    <div class="page-layout">
        <!-- Header -->
        <header class="page-header">
            <?php include 'header.php'; ?>
        </header>

        <!-- Main Content -->
        <main id="main-content" class="page-main">
            <div class="page-content">
                <div class="container">
                    <div id="app-content"></div>
                    <!-- Page Header -->
                    <?php if (isset($pageHeader)): ?>
                        <?php include 'page-header.php'; ?>
                    <?php endif; ?>

                    <!-- Main Content Area -->
                    <div class="content-layout">
                        <div class="content-main">
                            <?php if (isset($content)): ?>
                                <?php print_unescaped($content); ?>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar (if needed) -->
                        <?php if (isset($sidebar)): ?>
                            <aside class="content-sidebar">
                                <?php print_unescaped($sidebar); ?>
                            </aside>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="page-footer">
            <?php include 'footer.php'; ?>
        </footer>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <!-- Toast Container for Messages -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Modal Container -->
    <div id="modal-container" class="modal-container"></div>

    <!-- Inline Scripts -->
    <script nonce="<?php p($_['cspNonce'] ?? '') ?>">
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize common components
            if (typeof ProjectControlLayout !== 'undefined') {
                ProjectControlLayout.init();
            }

            if (typeof ProjectControlTheme !== 'undefined') {
                ProjectControlTheme.init();
            }

            if (typeof ProjectControlValidation !== 'undefined') {
                ProjectControlValidation.init();
            }

            if (typeof ProjectControlMessaging !== 'undefined') {
                ProjectControlMessaging.init();
            }

            // Page-specific initialization
            <?php if (isset($pageInitScript)): ?>
                <?php print_unescaped($pageInitScript); ?>
            <?php endif; ?>
        });

        // Handle theme changes
        window.addEventListener('theme-changed', function(event) {
            document.documentElement.setAttribute('data-theme', event.detail.theme);
        });

        // Handle loading states
        window.addEventListener('loading-start', function() {
            document.getElementById('loading-overlay').style.display = 'flex';
        });

        window.addEventListener('loading-end', function() {
            document.getElementById('loading-overlay').style.display = 'none';
        });

        // Handle form submissions
        document.addEventListener('submit', function(event) {
            const form = event.target;
            if (form.classList.contains('ajax-form')) {
                event.preventDefault();

                // Dispatch loading event
                window.dispatchEvent(new CustomEvent('loading-start'));

                // Handle form submission via AJAX
                const formData = new FormData(form);
                const url = form.action || window.location.href;

                fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            if (typeof ProjectControlMessaging !== 'undefined') {
                                ProjectControlMessaging.show('success', data.message || 'Operation completed successfully');
                            }

                            // Redirect if specified
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        } else {
                            // Show error message
                            if (typeof ProjectControlMessaging !== 'undefined') {
                                ProjectControlMessaging.show('error', data.message || 'An error occurred');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Form submission error:', error);
                        if (typeof ProjectControlMessaging !== 'undefined') {
                            ProjectControlMessaging.show('error', 'An unexpected error occurred');
                        }
                    })
                    .finally(() => {
                        // Dispatch loading end event
                        window.dispatchEvent(new CustomEvent('loading-end'));
                    });
            }
        });
    </script>

    <!-- Print Styles -->
    <style nonce="<?php p($_['cspNonce'] ?? '') ?>" media="print">
        .page-header,
        .page-footer,
        .content-sidebar,
        .loading-overlay,
        .toast-container,
        .modal-container {
            display: none !important;
        }

        .page-layout {
            display: block;
            min-height: auto;
        }

        .page-main {
            display: block;
        }

        .page-content {
            padding: 0;
        }

        .content-layout {
            display: block;
        }

        .content-main {
            width: 100%;
        }
    </style>
</body>

</html>