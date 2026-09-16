<?php if ($adminAuthed && ($editMode || $composeMode)): ?>
<details class="visitor-panel"><summary>last 10 symbol changes</summary>
<?php if (!$symbolHistory): ?><p>No changes yet.</p><?php endif; ?>
<?php foreach ($symbolHistory as $change): ?>
<div class="visitor-history-row"><span class="visitor-contribution"><?php foreach ($change['symbols'] as $symbol): ?><?= fbo_symbol_svg((int) $symbol) ?><?php endforeach; ?></span><time><?= date('d.m.Y H:i:s', (int) $change['time']) ?></time><?php if (!empty($change['author'])): ?><span><?= htmlspecialchars($change['author'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div>
<?php endforeach; ?>
</details>
<?php endif; ?>
<?php if (!$visitorGate): ?>
<?php if ($interactionError !== ''): ?><p role="alert"><?= htmlspecialchars($interactionError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($adminAuthed): ?>
<details class="visitor-panel"><summary>private notes (<?= count($privateComments) ?>)</summary>
<?php if (!$privateComments): ?><p>No notes yet.</p><?php endif; ?>
<?php foreach ($privateComments as $comment): ?>
<div class="visitor-comment"><small><?= htmlspecialchars($comment['author'], ENT_QUOTES, 'UTF-8') ?> · <?= date('d.m.Y H:i', $comment['time']) ?></small>
<?php if (!empty($comment['post_id'])): ?><div><a href="<?= htmlspecialchars(blog_share_url((string) $comment['post_id']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($comment['post_label'] ?? 'View post'), ENT_QUOTES, 'UTF-8') ?></a></div><?php else: ?><div>Earlier note — no post recorded.</div><?php endif; ?>
<p><?= htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8') ?></p></div>
<?php endforeach; ?>
</details>
<?php endif; ?>
<?php if ($commentSent): ?><p role="status">Note sent privately to the blog owner.</p><?php endif; ?>
<?php endif; ?>
