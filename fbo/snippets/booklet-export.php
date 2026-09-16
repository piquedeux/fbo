	<div class="blog-modal-overlay" id="bookletExportModal" hidden>
		<div class="blog-modal" role="dialog" aria-modal="true" aria-labelledby="bookletExportModalTitle">
			<button type="button" class="blog-modal-close" id="bookletExportModalClose" aria-label="Close dialog">×</button>
			<div class="blog-modal-kicker">PRINT EXPORT</div>
			<h2 class="blog-modal-title" id="bookletExportModalTitle">Export A5 booklet?</h2>
			<div class="blog-modal-body">
				<p>This downloads a self-contained HTML print master with all printable images embedded.</p>
				<p><strong>Estimated download size:</strong> <?= htmlspecialchars(fbo_booklet_format_size((int) ($bookletEstimateBytes ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
				<p><strong>Export and duplex print</strong></p>
				<ol>
					<li>Choose <strong>Download HTML</strong> and open the downloaded file in your browser.</li>
					<li>Open the browser print dialog with <strong>Cmd+P</strong> (macOS) or <strong>Ctrl+P</strong> (Windows/Linux).</li>
					<li>Set paper to <strong>A4</strong>, orientation to <strong>Landscape</strong>, scale to <strong>100%</strong> or actual size, and disable browser headers and footers.</li>
					<li>Enable <strong>two-sided / duplex</strong> printing and choose <strong>flip on short edge</strong>. This keeps the A5 pages correctly oriented for folding.</li>
					<li>For a PDF first choose <strong>Save as PDF</strong>. For the physical copy choose the duplex printer, then fold each A4 sheet in the centre and stack the sheets.</li>
				</ol>
				<p class="upload-note">The size is a rough estimate based on source files. Resizing and browser serialization can change the download size. Browser PDF printing does not guarantee CMYK; use a print-prepress workflow for CMYK production.</p>
			</div>
			<div class="blog-modal-actions">
				<a href="?<?= $blogQ ?>export_booklet=1" class="ui-btn ui-btn-strong" id="confirmBookletExport">Download HTML</a>
				<button type="button" class="ui-btn" id="cancelBookletExport">Cancel</button>
			</div>
		</div>
	</div>
	<script>
	(function () {
		const modal = document.getElementById('bookletExportModal');
		const open = document.getElementById('exportBookletBtn');
		const close = document.getElementById('bookletExportModalClose');
		const cancel = document.getElementById('cancelBookletExport');
		if (!modal || !open || !close || !cancel) return;
		const hide = function () { modal.hidden = true; };
		open.addEventListener('click', function () { modal.hidden = false; });
		close.addEventListener('click', hide);
		cancel.addEventListener('click', hide);
		modal.addEventListener('click', function (event) { if (event.target === modal) hide(); });
	})();
	</script>
