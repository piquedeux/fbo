<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= htmlspecialchars($siteNameDisplay, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>"><link rel="stylesheet" href="<?= local_asset_url('assets/css/upload.css') ?>"><link rel="stylesheet" href="<?= local_asset_url('assets/css/visitor-interactions.css') ?>"></head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="visitor-entry">
<form method="post">
<p>Leave three symbols to enter.</p>
<?php if ($interactionError !== ''): ?><p role="alert"><?= htmlspecialchars($interactionError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<input type="hidden" name="visitor_action" value="symbols"><input type="hidden" name="interaction_token" value="<?= $interactionToken ?>">
<fieldset class="visitor-selection"><legend>Choose three symbols — your obolus to enter.</legend>
<?php
$entryImages = [];
foreach (load_posts() as $entryPost) {
    if (($entryPost['type'] ?? '') === 'image' && !empty($entryPost['path'])) $entryImages[] = asset_url((string) $entryPost['path']);
    if (count($entryImages) >= 24) break;
}
?>
<?php foreach (FBO_SYMBOLS as $index => $symbol): ?><button type="button" class="visitor-choice" data-symbol-choice="<?= $index ?>" data-symbol-label="<?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false"><?= fbo_symbol_svg($index, $entryImages ? $entryImages[$index % count($entryImages)] : '') ?><span class="visitor-choice-count" aria-hidden="true"></span></button><?php endforeach; ?>
</fieldset><div data-symbol-values></div><p class="visitor-selection-status" role="status">0 / 3 · Tap again for repeats. At three, tap a selected symbol to clear it.</p><button class="ui-btn" type="submit" disabled>leave symbols & enter</button>
<noscript><p>Enable JavaScript to select your three symbols.</p></noscript>
</form></main>
<?php include __DIR__ . '/cookie-banner.php'; ?>
<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
</body></html>
