<?php if (!$visitorGate): ?>
<?php if ($interactionError !== ''): ?><p role="alert"><?= htmlspecialchars($interactionError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($commentSent): ?><p role="status">Note sent privately to the blog owner.</p><?php endif; ?>
<?php endif; ?>
