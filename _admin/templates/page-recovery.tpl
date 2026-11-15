<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Recovery</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
	</head>
	<body>
		<div id="recovery-wrap" class="nino-admin nino-admin-auth" data-dir="[[/nino/dir]]">
			<form id="recovery-login" class="nino-admin-auth-card">
				[csrf]
				<div class="nino-admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
				<h1 class="nino-admin-auth-title">Recovery</h1>
				<p id="recovery-login-msg" role="status" aria-live="polite">Enter the recovery password set during setup.</p>
				<label class="nino-admin-field" for="recovery-input-pw">
					<span>Recovery password</span>
					<input id="recovery-input-pw" type="password" autocomplete="current-password">
				</label>
				<button type="submit">Continue</button>
			</form>
			<div id="recovery-tools" class="nino-admin-auth-card admin-hidden" data-open="[[/_admin/recovery/open]]">
				<div class="nino-admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
				<h1 class="nino-admin-auth-title">Recovery</h1>
				<section>
					<h2>Restore a backup</h2>
					<p class="nino-admin-hint">Every restore first snapshots the current state, so a wrong pick can itself be undone.</p>
					<label class="nino-admin-field" for="recovery-key"><span>Recovery key of the archive (only for an archive of another project)</span><input id="recovery-key" type="password" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="100"></label>
					<label class="nino-admin-checkbox-field" for="recovery-key-show"><input type="checkbox" id="recovery-key-show"><span>Show the key</span></label>
					<p class="nino-admin-hint">The key is the one the setup wizard showed when that project was installed. It is used for this one restore: it is not stored and not logged. An archive of this project needs none.</p>
					<h3>Archives on this server</h3>
					<ul id="recovery-dates" class="nino-admin-list"></ul>
					<h3>An archive from a file</h3>
					<form id="recovery-file">
						<label class="nino-admin-field" for="recovery-file-input"><span>Archive file</span><input id="recovery-file-input" type="file" accept=".php,application/octet-stream"></label>
						<p id="recovery-file-hint" class="nino-admin-hint">Needs the recovery key above. The file is used for this restore alone and is deleted afterwards. A file larger than the server takes in an upload goes into private/.backups by SFTP, under its date as the name (2026-11-15-120000-manual.php), and is restored from the list above.</p>
						<button type="submit" id="recovery-file-submit" class="nino-admin-btn-danger">Restore from the file</button>
					</form>
					<p id="recovery-restore-msg" role="status" aria-live="polite"></p>
				</section>
				<section>
					<form id="recovery-reset">
						<h2>Set a password</h2>
						<p class="nino-admin-hint">The account gets the new password, is logged out everywhere, and is unlocked and activated if it was locked or deactivated.</p>
						<label class="nino-admin-field" for="recovery-reset-mail"><span>Account</span><select id="recovery-reset-mail" required></select></label>
						<label class="nino-admin-field" for="recovery-reset-pw"><span>New password (at least 8 characters)</span><input id="recovery-reset-pw" type="password" autocomplete="new-password" minlength="8" required></label>
						<p id="recovery-reset-msg" role="status" aria-live="polite"></p>
						<button type="submit" id="recovery-reset-submit">Set password</button>
					</form>
				</section>
				<section>
					<form id="recovery-create">
						<h2>Create a full-access account</h2>
						<p class="nino-admin-hint">For when no account is left to log in with. The new account can do everything, and this cannot be undone from here.</p>
						<label class="nino-admin-field" for="recovery-create-mail"><span>Email</span><input id="recovery-create-mail" type="email" autocomplete="off" required></label>
						<label class="nino-admin-field" for="recovery-create-pw"><span>Password (at least 8 characters)</span><input id="recovery-create-pw" type="password" autocomplete="new-password" minlength="8" required></label>
						<label class="nino-admin-field" for="recovery-create-repeat"><span>Repeat the password</span><input id="recovery-create-repeat" type="password" autocomplete="new-password" minlength="8" required></label>
						<p id="recovery-create-msg" role="status" aria-live="polite"></p>
						<button type="submit">Create account</button>
					</form>
				</section>
				<p class="nino-admin-hint"><a href="[[/nino/dir]]/_admin/">Back to /_admin</a> · <button type="button" id="recovery-logout" class="nino-admin-linkbutton">Close recovery</button></p>
			</div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/recovery.js"></script>
	</body>
</html>
