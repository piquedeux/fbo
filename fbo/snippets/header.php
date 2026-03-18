<header class="hero">
	<div class="hero-head">
		<a href="<?= htmlspecialchars(blog_self_url(), ENT_QUOTES, 'UTF-8') ?>" class="logo logo-link" id="siteTitleDisplay"><?= htmlspecialchars($siteNameDisplay, ENT_QUOTES, 'UTF-8') ?></a>
		<div class="hero-right">
		<div class="fbo"><a href="/fbo/fbo" class="fbo-link" title="FBO Project stands for Fuck Being Online">FBO</a></div>
	<div class="hero-actions">
				<?php if ($adminAuthed): ?>
					<a href="?<?= $blogQ ?>compose=1&view=<?= $view ?>&page=<?= $page ?>" class="ui-btn <?= $composeMode ? 'active' : '' ?>">compose</a>
					<a href="?<?= $blogQ ?>edit=1&view=<?= $view ?>&page=<?= $page ?>" class="ui-btn <?= $editMode ? 'active' : '' ?>">edit</a>
						<form method="post" class="inline-form">
						<button type="submit" name="admin_logout" value="1" class="ui-btn">logout</button>
					</form>
				<?php else: ?>
					<a href="?<?= $blogQ ?>compose=1&view=<?= $view ?>&page=<?= $page ?>" class="ui-btn <?= ($composeMode || $editMode) ? 'active' : '' ?>">login</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ($heroSubtitle !== ''): ?>
		<div class="subtitle-line"><?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<?php if ($editMode): ?>
		<?php include __DIR__ . '/header-edit.php'; ?>
	<?php endif; ?>

	<?php if ($composeMode): ?>
		<?php include __DIR__ . '/header-compose.php'; ?>
	<?php endif; ?>
</header>
