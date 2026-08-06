<?php

declare(strict_types=1);

/**
 * Theme-safe project type chip: Lucide icon + accessible label (no emoji).
 *
 * Expected locals: $projectType (string key), $displayName (string), optional $showLabel (bool).
 *
 * @var string $projectType
 * @var string $displayName
 * @var bool $showLabel
 */

use OCA\ProjectCheck\Util\ProjectTypeIcon;

$projectType = strtolower(trim((string)($projectType ?? 'other')));
$displayName = (string)($displayName ?? $projectType);
$showLabel = !empty($showLabel);
$lucide = ProjectTypeIcon::lucideName($projectType);
?>
<span class="project-type-chip"
	data-project-type="<?php p($projectType); ?>"
	title="<?php p($displayName); ?>">
	<span class="project-type-icon"
		data-project-type="<?php p($projectType); ?>"
		aria-hidden="true">
		<span data-lucide="<?php p($lucide); ?>" class="lucide-icon"></span>
	</span>
	<?php if ($showLabel): ?>
		<span class="project-type-chip__label"><?php p($displayName); ?></span>
	<?php else: ?>
	<span class="hidden-visually sr-only"><?php p($displayName); ?></span>
	<?php endif; ?>
</span>
