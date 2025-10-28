<?php

/**
 * Error State Component for ProjectControl App
 * 
 * This template provides various error states and empty states
 * for different scenarios.
 */

// Ensure this file is being included within Nextcloud
if (!defined('OCP\AppFramework\App::class')) {
    die('Direct access not allowed');
}

// Get error configuration
$errorType = isset($errorType) ? $errorType : 'general';
$errorTitle = isset($errorTitle) ? $errorTitle : $l->t('An error occurred');
$errorMessage = isset($errorMessage) ? $errorMessage : $l->t('Something went wrong. Please try again.');
$errorCode = isset($errorCode) ? $errorCode : '';
$showRetry = isset($showRetry) ? $showRetry : true;
$retryAction = isset($retryAction) ? $retryAction : '';
$showBackButton = isset($showBackButton) ? $showBackButton : true;
$backUrl = isset($backUrl) ? $backUrl : '';
$actions = isset($actions) ? $actions : [];

$appName = 'projectcheck';
?>

<?php if ($errorType === 'empty'): ?>
    <!-- Empty State -->
    <div class="error-state error-state--empty">
        <div class="error-state__icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
            </svg>
        </div>
        <div class="error-state__content">
            <h3 class="error-state__title"><?php p($errorTitle); ?></h3>
            <p class="error-state__message"><?php p($errorMessage); ?></p>
            <?php if (!empty($actions)): ?>
                <div class="error-state__actions">
                    <?php foreach ($actions as $action): ?>
                        <?php if (isset($action['type']) && $action['type'] === 'button'): ?>
                            <button type="button"
                                class="btn <?php echo isset($action['variant']) ? 'btn--' . $action['variant'] : 'btn--primary'; ?>"
                                <?php echo isset($action['onclick']) ? 'onclick="' . $action['onclick'] . '"' : ''; ?>>
                                <?php if (isset($action['icon'])): ?>
                                    <span class="btn__icon"><?php p($action['icon']); ?></span>
                                <?php endif; ?>
                                <?php p($action['text']); ?>
                            </button>
                        <?php elseif (isset($action['type']) && $action['type'] === 'link'): ?>
                            <a href="<?php print_unescaped($action['url']); ?>"
                                class="btn <?php echo isset($action['variant']) ? 'btn--' . $action['variant'] : 'btn--primary'; ?>">
                                <?php if (isset($action['icon'])): ?>
                                    <span class="btn__icon"><?php p($action['icon']); ?></span>
                                <?php endif; ?>
                                <?php p($action['text']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($errorType === 'not_found'): ?>
    <!-- Not Found Error -->
    <div class="error-state error-state--not-found">
        <div class="error-state__icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
            </svg>
        </div>
        <div class="error-state__content">
            <h3 class="error-state__title"><?php p($errorTitle); ?></h3>
            <p class="error-state__message"><?php p($errorMessage); ?></p>
            <?php if ($errorCode): ?>
                <div class="error-state__code"><?php p($l->t('Error Code:')); ?> <?php p($errorCode); ?></div>
            <?php endif; ?>
            <div class="error-state__actions">
                <?php if ($showBackButton && $backUrl): ?>
                    <a href="<?php print_unescaped($backUrl); ?>" class="btn btn--secondary">
                        <span class="btn__icon">←</span>
                        <?php p($l->t('Go Back')); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>" class="btn btn--primary">
                    <span class="btn__icon">🏠</span>
                    <?php p($l->t('Go Home')); ?>
                </a>
            </div>
        </div>
    </div>

<?php elseif ($errorType === 'permission'): ?>
    <!-- Permission Error -->
    <div class="error-state error-state--permission">
        <div class="error-state__icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" fill="currentColor" />
            </svg>
        </div>
        <div class="error-state__content">
            <h3 class="error-state__title"><?php p($errorTitle); ?></h3>
            <p class="error-state__message"><?php p($errorMessage); ?></p>
            <div class="error-state__actions">
                <?php if ($showBackButton && $backUrl): ?>
                    <a href="<?php print_unescaped($backUrl); ?>" class="btn btn--secondary">
                        <span class="btn__icon">←</span>
                        <?php p($l->t('Go Back')); ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn--primary" onclick="contactAdmin()">
                    <span class="btn__icon">📧</span>
                    <?php p($l->t('Contact Administrator')); ?>
                </button>
            </div>
        </div>
    </div>

<?php elseif ($errorType === 'network'): ?>
    <!-- Network Error -->
    <div class="error-state error-state--network">
        <div class="error-state__icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
            </svg>
        </div>
        <div class="error-state__content">
            <h3 class="error-state__title"><?php p($errorTitle); ?></h3>
            <p class="error-state__message"><?php p($errorMessage); ?></p>
            <div class="error-state__actions">
                <?php if ($showRetry && $retryAction): ?>
                    <button type="button" class="btn btn--primary" onclick="<?php p($retryAction); ?>">
                        <span class="btn__icon">🔄</span>
                        <?php p($l->t('Try Again')); ?>
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn--secondary" onclick="window.location.reload()">
                    <span class="btn__icon">🔄</span>
                    <?php p($l->t('Refresh Page')); ?>
                </button>
            </div>
        </div>
    </div>

<?php elseif ($errorType === 'validation'): ?>
    <!-- Validation Error -->
    <div class="error-state error-state--validation">
        <div class="error-state__icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
            </svg>
        </div>
        <div class="error-state__content">
            <h3 class="error-state__title"><?php p($errorTitle); ?></h3>
            <p class="error-state__message"><?php p($errorMessage); ?></p>
            <div class="error-state__actions">
                <button type="button" class="btn btn--primary" onclick="fixErrors()">
                    <span class="btn__icon">🔧</span>
                    <?php p($l->t('Fix Errors')); ?>
                </button>
                <?php if ($showBackButton && $backUrl): ?>
                    <a href="<?php print_unescaped($backUrl); ?>" class="btn btn--secondary">
                        <span class="btn__icon">←</span>
                        <?php p($l->t('Go Back')); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- General Error -->
    <div class="error-state error-state--general">
        <div class="error-state__icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor" />
            </svg>
        </div>
        <div class="error-state__content">
            <h3 class="error-state__title"><?php p($errorTitle); ?></h3>
            <p class="error-state__message"><?php p($errorMessage); ?></p>
            <?php if ($errorCode): ?>
                <div class="error-state__code"><?php p($l->t('Error Code:')); ?> <?php p($errorCode); ?></div>
            <?php endif; ?>
            <div class="error-state__actions">
                <?php if ($showRetry && $retryAction): ?>
                    <button type="button" class="btn btn--primary" onclick="<?php p($retryAction); ?>">
                        <span class="btn__icon">🔄</span>
                        <?php p($l->t('Try Again')); ?>
                    </button>
                <?php endif; ?>
                <?php if ($showBackButton && $backUrl): ?>
                    <a href="<?php print_unescaped($backUrl); ?>" class="btn btn--secondary">
                        <span class="btn__icon">←</span>
                        <?php p($l->t('Go Back')); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>" class="btn btn--tertiary">
                    <span class="btn__icon">🏠</span>
                    <?php p($l->t('Go Home')); ?>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Error State Styles -->
<style nonce="<?php p($_['cspNonce'] ?? '') ?>">
    .error-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 2rem;
        text-align: center;
        min-height: 400px;
    }

    .error-state__icon {
        margin-bottom: 2rem;
        color: var(--color-text-muted);
    }

    .error-state__content {
        max-width: 500px;
    }

    .error-state__title {
        font-size: var(--font-size-2xl);
        font-weight: var(--font-weight-semibold);
        color: var(--color-text);
        margin: 0 0 1rem 0;
    }

    .error-state__message {
        font-size: var(--font-size-base);
        color: var(--color-text-muted);
        margin: 0 0 2rem 0;
        line-height: var(--line-height-relaxed);
    }

    .error-state__code {
        font-family: var(--font-family-mono);
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        background-color: var(--color-background-secondary);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-base);
        margin-bottom: 2rem;
        display: inline-block;
    }

    .error-state__actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Error State Variants */
    .error-state--empty .error-state__icon {
        color: var(--color-success);
    }

    .error-state--not-found .error-state__icon {
        color: var(--color-warning);
    }

    .error-state--permission .error-state__icon {
        color: var(--color-error);
    }

    .error-state--network .error-state__icon {
        color: var(--color-warning);
    }

    .error-state--validation .error-state__icon {
        color: var(--color-error);
    }

    .error-state--general .error-state__icon {
        color: var(--color-text-muted);
    }

    /* Responsive Design */
    @media (max-width: 640px) {
        .error-state {
            padding: 2rem 1rem;
            min-height: 300px;
        }

        .error-state__title {
            font-size: var(--font-size-xl);
        }

        .error-state__actions {
            flex-direction: column;
            align-items: center;
        }

        .error-state__actions .btn {
            width: 100%;
            max-width: 200px;
        }
    }
</style>

<script nonce="<?php p($_['cspNonce'] ?? '') ?>">
    function contactAdmin() {
        // Open contact form or email client
        const email = 'admin@example.com';
        const subject = '<?php p($l->t('Permission Issue - ProjectControl')); ?>';
        const body = '<?php p($l->t('I am experiencing a permission issue in ProjectControl. Please help.')); ?>';

        window.open(`mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`);
    }

    function fixErrors() {
        // Scroll to first error field
        const firstError = document.querySelector('.form-input--error');
        if (firstError) {
            firstError.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            firstError.focus();
        }
    }
</script>