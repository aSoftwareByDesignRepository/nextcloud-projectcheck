<?php

/**
 * Loading State Component for ProjectControl App
 * 
 * This template provides various loading states and skeleton screens
 * for different content types.
 */

// Ensure this file is being included within Nextcloud
if (!defined('OCP\AppFramework\App::class')) {
    die('Direct access not allowed');
}

// Get loading configuration
$loadingType = isset($loadingType) ? $loadingType : 'spinner';
$loadingText = isset($loadingText) ? $loadingText : 'Loading...';
$loadingSize = isset($loadingSize) ? $loadingSize : 'medium';
$showText = isset($showText) ? $showText : true;
$skeletonType = isset($skeletonType) ? $skeletonType : 'default';
$skeletonCount = isset($skeletonCount) ? $skeletonCount : 3;

$appName = 'projectcheck';
?>

<?php if ($loadingType === 'spinner'): ?>
    <!-- Spinner Loading State -->
    <div class="loading-state loading-state--spinner loading-state--<?php p($loadingSize); ?>">
        <div class="loading-spinner">
            <div class="loading-spinner__circle"></div>
        </div>
        <?php if ($showText): ?>
            <div class="loading-text"><?php p($loadingText); ?></div>
        <?php endif; ?>
    </div>

<?php elseif ($loadingType === 'skeleton'): ?>
    <!-- Skeleton Loading State -->
    <div class="loading-state loading-state--skeleton">
        <?php if ($skeletonType === 'table'): ?>
            <!-- Table Skeleton -->
            <div class="skeleton-table">
                <div class="skeleton-table__header">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="skeleton-table__header-cell"></div>
                    <?php endfor; ?>
                </div>
                <div class="skeleton-table__body">
                    <?php for ($row = 0; $row < $skeletonCount; $row++): ?>
                        <div class="skeleton-table__row">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <div class="skeleton-table__cell"></div>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

        <?php elseif ($skeletonType === 'card'): ?>
            <!-- Card Skeleton -->
            <div class="skeleton-cards">
                <?php for ($i = 0; $i < $skeletonCount; $i++): ?>
                    <div class="skeleton-card">
                        <div class="skeleton-card__header">
                            <div class="skeleton-card__title"></div>
                            <div class="skeleton-card__subtitle"></div>
                        </div>
                        <div class="skeleton-card__content">
                            <div class="skeleton-card__line"></div>
                            <div class="skeleton-card__line"></div>
                            <div class="skeleton-card__line skeleton-card__line--short"></div>
                        </div>
                        <div class="skeleton-card__footer">
                            <div class="skeleton-card__button"></div>
                            <div class="skeleton-card__button"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

        <?php elseif ($skeletonType === 'list'): ?>
            <!-- List Skeleton -->
            <div class="skeleton-list">
                <?php for ($i = 0; $i < $skeletonCount; $i++): ?>
                    <div class="skeleton-list__item">
                        <div class="skeleton-list__avatar"></div>
                        <div class="skeleton-list__content">
                            <div class="skeleton-list__title"></div>
                            <div class="skeleton-list__subtitle"></div>
                        </div>
                        <div class="skeleton-list__action"></div>
                    </div>
                <?php endfor; ?>
            </div>

        <?php elseif ($skeletonType === 'form'): ?>
            <!-- Form Skeleton -->
            <div class="skeleton-form">
                <div class="skeleton-form__field">
                    <div class="skeleton-form__label"></div>
                    <div class="skeleton-form__input"></div>
                </div>
                <div class="skeleton-form__field">
                    <div class="skeleton-form__label"></div>
                    <div class="skeleton-form__input"></div>
                </div>
                <div class="skeleton-form__field">
                    <div class="skeleton-form__label"></div>
                    <div class="skeleton-form__textarea"></div>
                </div>
                <div class="skeleton-form__actions">
                    <div class="skeleton-form__button"></div>
                    <div class="skeleton-form__button skeleton-form__button--secondary"></div>
                </div>
            </div>

        <?php else: ?>
            <!-- Default Skeleton -->
            <div class="skeleton-default">
                <?php for ($i = 0; $i < $skeletonCount; $i++): ?>
                    <div class="skeleton-default__item">
                        <div class="skeleton-default__line"></div>
                        <div class="skeleton-default__line skeleton-default__line--short"></div>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($loadingType === 'progress'): ?>
    <!-- Progress Loading State -->
    <div class="loading-state loading-state--progress">
        <div class="loading-progress">
            <div class="loading-progress__bar">
                <div class="loading-progress__fill" style="width: 0%"></div>
            </div>
            <?php if ($showText): ?>
                <div class="loading-progress__text"><?php p($loadingText); ?></div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($loadingType === 'dots'): ?>
    <!-- Dots Loading State -->
    <div class="loading-state loading-state--dots">
        <div class="loading-dots">
            <div class="loading-dots__dot"></div>
            <div class="loading-dots__dot"></div>
            <div class="loading-dots__dot"></div>
        </div>
        <?php if ($showText): ?>
            <div class="loading-text"><?php p($loadingText); ?></div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Default Loading State -->
    <div class="loading-state loading-state--default">
        <div class="loading-default">
            <div class="loading-default__spinner"></div>
            <?php if ($showText): ?>
                <div class="loading-text"><?php p($loadingText); ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Loading State Styles -->
<style nonce="<?php p($_['cspNonce'] ?? '') ?>">
    /* Loading State Base */
    .loading-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        text-align: center;
    }

    .loading-state--small {
        padding: 1rem;
    }

    .loading-state--large {
        padding: 4rem;
    }

    /* Spinner Loading */
    .loading-spinner {
        position: relative;
        width: 40px;
        height: 40px;
    }

    .loading-spinner--small {
        width: 24px;
        height: 24px;
    }

    .loading-spinner--large {
        width: 60px;
        height: 60px;
    }

    .loading-spinner__circle {
        width: 100%;
        height: 100%;
        border: 3px solid var(--color-border);
        border-top: 3px solid var(--color-primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .loading-text {
        margin-top: 1rem;
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }

    /* Progress Loading */
    .loading-progress {
        width: 100%;
        max-width: 300px;
    }

    .loading-progress__bar {
        width: 100%;
        height: 8px;
        background-color: var(--color-border);
        border-radius: 4px;
        overflow: hidden;
    }

    .loading-progress__fill {
        height: 100%;
        background-color: var(--color-primary);
        border-radius: 4px;
        transition: width 0.3s ease;
        animation: progress-pulse 2s ease-in-out infinite;
    }

    @keyframes progress-pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.7;
        }
    }

    .loading-progress__text {
        margin-top: 0.5rem;
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }

    /* Dots Loading */
    .loading-dots {
        display: flex;
        gap: 0.5rem;
    }

    .loading-dots__dot {
        width: 8px;
        height: 8px;
        background-color: var(--color-primary);
        border-radius: 50%;
        animation: dots-bounce 1.4s ease-in-out infinite both;
    }

    .loading-dots__dot:nth-child(1) {
        animation-delay: -0.32s;
    }

    .loading-dots__dot:nth-child(2) {
        animation-delay: -0.16s;
    }

    @keyframes dots-bounce {

        0%,
        80%,
        100% {
            transform: scale(0);
        }

        40% {
            transform: scale(1);
        }
    }

    /* Skeleton Loading */
    .skeleton-table {
        width: 100%;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-base);
        overflow: hidden;
    }

    .skeleton-table__header {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        background-color: var(--color-background-secondary);
        border-bottom: 1px solid var(--color-border);
    }

    .skeleton-table__header-cell {
        height: 48px;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
    }

    .skeleton-table__row {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        border-bottom: 1px solid var(--color-border);
    }

    .skeleton-table__row:last-child {
        border-bottom: none;
    }

    .skeleton-table__cell {
        height: 56px;
        padding: 1rem;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
    }

    .skeleton-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .skeleton-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        background-color: var(--color-background-elevated);
    }

    .skeleton-card__header {
        margin-bottom: 1rem;
    }

    .skeleton-card__title {
        height: 24px;
        width: 70%;
        margin-bottom: 0.5rem;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-card__subtitle {
        height: 16px;
        width: 50%;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-card__content {
        margin-bottom: 1rem;
    }

    .skeleton-card__line {
        height: 16px;
        width: 100%;
        margin-bottom: 0.5rem;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-card__line--short {
        width: 60%;
    }

    .skeleton-card__footer {
        display: flex;
        gap: 0.5rem;
    }

    .skeleton-card__button {
        height: 36px;
        width: 80px;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-base);
    }

    .skeleton-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .skeleton-list__item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-base);
        background-color: var(--color-background-elevated);
    }

    .skeleton-list__avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
    }

    .skeleton-list__content {
        flex: 1;
    }

    .skeleton-list__title {
        height: 20px;
        width: 60%;
        margin-bottom: 0.5rem;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-list__subtitle {
        height: 16px;
        width: 40%;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-list__action {
        width: 24px;
        height: 24px;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-form {
        max-width: 500px;
    }

    .skeleton-form__field {
        margin-bottom: 1.5rem;
    }

    .skeleton-form__label {
        height: 20px;
        width: 30%;
        margin-bottom: 0.5rem;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-form__input {
        height: 40px;
        width: 100%;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-base);
    }

    .skeleton-form__textarea {
        height: 100px;
        width: 100%;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-base);
    }

    .skeleton-form__actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }

    .skeleton-form__button {
        height: 40px;
        width: 100px;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-base);
    }

    .skeleton-form__button--secondary {
        width: 80px;
    }

    .skeleton-default {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .skeleton-default__item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .skeleton-default__line {
        height: 16px;
        width: 100%;
        background: linear-gradient(90deg, var(--color-border) 25%, var(--color-background) 50%, var(--color-border) 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    .skeleton-default__line--short {
        width: 60%;
    }

    @keyframes skeleton-shimmer {
        0% {
            background-position: -200% 0;
        }

        100% {
            background-position: 200% 0;
        }
    }
</style>