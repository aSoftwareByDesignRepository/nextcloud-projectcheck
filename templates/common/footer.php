<?php

/**
 * Common Footer Template for ProjectCheck App
 * 
 * This template provides the footer section with links, copyright information,
 * and additional navigation options.
 */

// Ensure this file is being included within Nextcloud
if (!defined('OCP\AppFramework\App::class')) {
    die('Direct access not allowed');
}

$appName = 'projectcheck';
$appVersion = \OCP\Server::get(\OCP\IAppManager::class)->getAppVersion($appName);
$currentYear = date('Y');
?>
<footer class="footer">
    <div class="footer__content">
        <div class="container">
            <div class="footer__grid">
                <!-- App Information -->
                <div class="footer__section">
                    <div class="footer__logo">
                        <img src="<?php print_unescaped(image_path($appName, 'logo.svg')); ?>"
                            alt="<?php p($l->t('ProjectCheck')); ?>"
                            class="footer__logo-image">
                        <span class="footer__logo-text"><?php p($l->t('ProjectCheck')); ?></span>
                    </div>
                    <p class="footer__description">
                        <?php p($l->t('Professional project and time management for Nextcloud.')); ?>
                        <?php p($l->t('Track projects, manage customers, and monitor time entries efficiently.')); ?>
                    </p>
                    <div class="footer__version">
                        <span class="footer__version-label"><?php p($l->t('Version:')); ?></span>
                        <span class="footer__version-number"><?php p($appVersion); ?></span>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer__section">
                    <h3 class="footer__section-title"><?php p($l->t('Quick Links')); ?></h3>
                    <ul class="footer__link-list">
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Dashboard')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'projects.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Projects')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'customers.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Customers')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'time-entries.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Time Entries')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'settings.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Settings')); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Support -->
                <div class="footer__section">
                    <h3 class="footer__section-title"><?php p($l->t('Support')); ?></h3>
                    <ul class="footer__link-list">
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'help.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Help & Documentation')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'faq.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('FAQ')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="https://github.com/nextcloud/projectcheck/issues"
                                class="footer__link"
                                target="_blank"
                                rel="noopener noreferrer">
                                <?php p($l->t('Report Issues')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="https://github.com/nextcloud/projectcheck"
                                class="footer__link"
                                target="_blank"
                                rel="noopener noreferrer">
                                <?php p($l->t('Source Code')); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Legal -->
                <div class="footer__section">
                    <h3 class="footer__section-title"><?php p($l->t('Legal')); ?></h3>
                    <ul class="footer__link-list">
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'privacy.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Privacy Policy')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'terms.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('Terms of Service')); ?>
                            </a>
                        </li>
                        <li class="footer__link-item">
                            <a href="<?php print_unescaped(link_to($appName, 'license.php')); ?>"
                                class="footer__link">
                                <?php p($l->t('License')); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer__bottom">
                <div class="footer__bottom-content">
                    <div class="footer__copyright">
                        <p class="footer__copyright-text">
                            &copy; <?php p($currentYear); ?> <?php p($l->t('ProjectCheck')); ?>.
                            <?php p($l->t('Built for Nextcloud.')); ?>
                        </p>
                    </div>

                    <div class="footer__bottom-links">
                        <a href="<?php print_unescaped(link_to($appName, 'changelog.php')); ?>"
                            class="footer__bottom-link">
                            <?php p($l->t('Changelog')); ?>
                        </a>
                        <span class="footer__bottom-separator">•</span>
                        <a href="<?php print_unescaped(link_to($appName, 'credits.php')); ?>"
                            class="footer__bottom-link">
                            <?php p($l->t('Credits')); ?>
                        </a>
                        <span class="footer__bottom-separator">•</span>
                        <a href="https://nextcloud.com"
                            class="footer__bottom-link"
                            target="_blank"
                            rel="noopener noreferrer">
                            <?php p($l->t('Nextcloud')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button type="button"
        class="footer__back-to-top"
        aria-label="<?php p($l->t('Back to top')); ?>"
        title="<?php p($l->t('Back to top')); ?>">
        <span class="footer__back-to-top-icon">↑</span>
    </button>
</footer>

<script nonce="<?php p($_['cspNonce'] ?? '') ?>">
    // Footer functionality
    document.addEventListener('DOMContentLoaded', function() {
        const backToTopBtn = document.querySelector('.footer__back-to-top');

        // Show/hide back to top button based on scroll position
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('footer__back-to-top--visible');
            } else {
                backToTopBtn.classList.remove('footer__back-to-top--visible');
            }
        });

        // Smooth scroll to top when back to top button is clicked
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Add smooth scrolling to all footer links that point to same page
        const footerLinks = document.querySelectorAll('.footer__link');
        footerLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const targetId = href.substring(1);
                    const targetElement = document.getElementById(targetId);

                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            }
        });
    });
</script>