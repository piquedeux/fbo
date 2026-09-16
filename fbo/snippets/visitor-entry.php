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
<fieldset class="visitor-selection"><legend>Choose three symbols — your obulus to enter.</legend>
<?php foreach (FBO_SYMBOLS as $index => $symbol): ?><label title="<?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?>"><input type="checkbox" name="symbols[]" value="<?= $index ?>" aria-label="<?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?>"><?= fbo_symbol_svg($index) ?></label><?php endforeach; ?>
</fieldset><button class="ui-btn" type="submit">leave symbols & enter</button>
</form></main>
<?php include __DIR__ . '/cookie-banner.php'; ?>
<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
</body></html>
