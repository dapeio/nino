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
				<p id="recovery-login-msg">Enter the recovery password set during setup.</p>
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
					<ul id="recovery-dates" class="nino-admin-list"></ul>
					<p id="recovery-restore-msg" role="status" aria-live="polite"></p>
				</section>
				<section>
					<form id="recovery-reset">
						<h2>Reset an account</h2>
						<p class="nino-admin-hint">An existing account gets the new password and is logged out everywhere. An address that has no account yet becomes one with full access.</p>
						<label class="nino-admin-field" for="recovery-reset-mail"><span>Email</span><input id="recovery-reset-mail" type="email" list="recovery-users" autocomplete="off" required></label>
						<datalist id="recovery-users"></datalist>
						<label class="nino-admin-field" for="recovery-reset-pw"><span>New password (at least 8 characters)</span><input id="recovery-reset-pw" type="password" autocomplete="new-password" minlength="8" required></label>
						<p id="recovery-reset-msg" role="status" aria-live="polite"></p>
						<button type="submit">Set password</button>
					</form>
				</section>
				<p class="nino-admin-hint"><a href="[[/nino/dir]]/_admin/">Back to /_admin</a> · <a href="#" id="recovery-logout">Close recovery</a></p>
			</div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/recovery.js"></script>
	</body>
</html>
