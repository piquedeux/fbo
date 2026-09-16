<div class="visitor-symbols" aria-label="Symbols left by visitors">
<?php foreach ($currentSymbols as $symbol): ?>
<button type="button" class="visitor-symbol-button" data-visitor-symbol="<?= (int) $symbol ?>" aria-label="<?= htmlspecialchars(FBO_SYMBOLS[$symbol], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(FBO_SYMBOLS[$symbol], ENT_QUOTES, 'UTF-8') ?>"><?= fbo_symbol_svg((int) $symbol) ?></button>
<?php endforeach; ?>
</div>
<script src="<?= local_asset_url('assets/js/visitor-symbols.js') ?>" defer></script>
