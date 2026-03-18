<?php if ($adminAuthed): ?>

    <div class="subtitle-line">Edit subtitle and manage your blog</div>

	<form method="post" class="subtitle-form">
		<input type="text" class="upload-auth-input" name="hero_subtitle" maxlength="180"
			value="<?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="Subtitle">
		<button type="submit" name="save_settings" value="1" class="ui-btn">Save</button>
	</form>

	<div class="subtitle-line"><?= $recoveryEmail !== '' ? 'Recovery email: ' . htmlspecialchars(preg_replace('/(?<=.{2}).(?=.*@)/u', '*', $recoveryEmail) ?? $recoveryEmail, ENT_QUOTES, 'UTF-8') : 'No recovery email set. Add one now so you can reset your password by email.' ?></div>
	<form method="post" class="subtitle-form">
		<input type="email" class="upload-auth-input" name="recovery_email" maxlength="254"
			value="<?= htmlspecialchars($recoveryEmail, ENT_QUOTES, 'UTF-8') ?>"
			placeholder="Recovery email">
		<button type="submit" name="save_recovery_email" value="1" class="ui-btn">Save email</button>
	</form>

	<div class="hero-actions">
		<a href="?<?= $blogQ ?>download_backup=1" class="ui-btn">Download your whole blog! All imaes and data as a .zip
			file.</a>
	</div>

	<form method="post" class="upload-panel" id="deleteBlogForm">
		<input type="hidden" name="delete_blog" value="1">
		<input type="hidden" name="delete_blog_confirm_compose" id="deleteBlogConfirmCompose" value="0">
		<input type="hidden" name="delete_blog_confirm_irreversible" id="deleteBlogConfirmIrreversible" value="0">
		<div class="subtitle-line danger-note">Danger zone: permanently delete this blog, all media files, and all backend
			data.</div>
		<input type="password" class="upload-auth-input" name="delete_blog_password" maxlength="120"
			placeholder="Type your current admin password" autocomplete="off" required>
		<div class="hero-actions">
			<button type="submit" class="ui-btn danger-btn">Delete blog permanently</button>
		</div>
	</form>
	<div class="subtitle-line danger-note">This cannot be undone and cannot be restored.</div>
	<?php if ($flashMessage !== ''): ?>
		<div class="subtitle-line"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<div class="hero-actions">
		<button type="button" class="ui-btn ui-btn-strong" id="saveCloseUploadBtn"
			data-close-url="?<?= $blogQ ?>view=<?= $view ?>&page=<?= $page ?>">Close</button>
	</div>
	<form method="post" class="upload-panel pending-delete-actions" id="pendingDeleteForm">
		<input type="hidden" name="delete_page_media" value="1">
		<input type="hidden" name="close_after_save" id="closeAfterSaveInput" value="0">
		<div class="hero-actions">
			<button type="submit" class="ui-btn ui-btn-strong" id="saveDeleteBtn">Save delete</button>
			<button type="button" class="ui-btn" id="cancelDeleteBtn">Cancel delete</button>
		</div>
		<div class="upload-note" id="pendingDeleteCount">0 selected for delete.</div>
		<div id="pendingDeleteInputs"></div>
	</form>

<?php else: ?>
	<?php if (!empty($isOtpReset)): ?>
		<?php if ($onboardingError !== ''): ?>
			<div class="subtitle-line auth-error"><?= htmlspecialchars($onboardingError, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>
		<div class="subtitle-line">Set a new password. Your posts and media are untouched.</div>
		<form method="post" class="subtitle-form">
			<input type="password" class="upload-auth-input" name="admin_password" maxlength="120"
				placeholder="New password (min 6 chars)" required>
			<input type="password" class="upload-auth-input" name="admin_password_confirm" maxlength="120"
				placeholder="Confirm new password" required>
			<button type="submit" name="complete_onboarding" value="1" class="ui-btn ui-btn-strong">Set new password</button>
		</form>
	<?php else: ?>
		<?php if (!empty($authError)): ?>
			<div class="subtitle-line auth-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>
		<?php if ($flashMessage !== ''): ?>
			<div class="subtitle-line"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>

		<?php $hasActiveOtp = (function_exists('load_otp') && load_otp() !== null); ?>

		<?php if ($hasActiveOtp): ?>
			<?php if (!empty($otpLoginError)): ?>
				<div class="subtitle-line auth-error"><?= htmlspecialchars($otpLoginError, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>
			<div class="subtitle-line">Enter the one-time password from your email.</div>
			<form method="post" class="subtitle-form">
				<input type="text" class="upload-auth-input" name="otp_password" maxlength="32"
					placeholder="One-time password" autocomplete="off" spellcheck="false" inputmode="text" required>
				<button type="submit" name="otp_login" value="1" class="ui-btn ui-btn-strong">Continue</button>
				<a href="?<?= $blogQ ?>edit=1" class="ui-btn">Back</a>
			</form>
		<?php else: ?>
			<form method="post" class="subtitle-form">
				<input type="hidden" name="login_target" value="edit">
				<input type="password" class="upload-auth-input" name="admin_login_password" maxlength="120" placeholder="Password"
					required>
				<button type="submit" class="ui-btn ui-btn-strong">Unlock edit</button>
				<a href="?<?= $blogQ ?>view=<?= $view ?>&page=<?= $page ?>" class="ui-btn">Cancel</a>
			</form>
			<?php if ($recoveryEmail !== ''): ?>
				<form method="post" class="subtitle-form">
					<button type="submit" name="generate_otp" value="1" class="ui-btn">Forgot password</button>
				</form>
			<?php else: ?>
				<div class="subtitle-line">No recovery email configured yet. After logging in, add one in edit settings.</div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>