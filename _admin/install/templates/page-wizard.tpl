<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Install</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/install/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
	</head>
	<body>
		[csrf]
		<div id="install-page-wrap" class="nino-admin nino-admin-shell nino-admin-shell--rail show-checks">
			<aside id="install-shell-rail" class="nino-admin-rail" aria-label="Installation progress">
				<div id="install-bar-wrap" class="nino-admin-rail-head">
					<div id="install-bar-title">
						<span class="nino-admin-brand-mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
						<span class="nino-admin-brand-copy"><strong>Nino</strong><small>Setup</small></span>
					</div>
				</div>

				<!-- Progress display only, not a jump-menu - each step's own
				     Back/Next controls the wizard's flow, see script.js -->
				<div id="install-nav-wrap" class="nino-admin-nav">
					<span id="install-nav-checks" class="active">1. Environment</span>
					<span id="install-nav-setup">2. Languages</span>
					<span id="install-nav-webpages">3. Routes</span>
					<span id="install-nav-personalinfos">4. Personal Infos</span>
					<span id="install-nav-accounts">5. Accounts</span>
					<span id="install-nav-finish">6. Finish</span>
				</div>
			</aside>

			<main id="install-content-wrap" class="nino-admin-pane">

				<div id="install-content-checks">
					<p class="nino-admin-hint nino-admin-hint-lead">PHP version, extensions and file/folder permissions Nino needs to run.</p>
					<div id="checks-results"></div>
					<button type="button" id="checks-refresh">Recheck</button>
				</div>

				<div id="install-content-setup">
					<p class="nino-admin-hint nino-admin-hint-lead">Pick the available languages and Nino's default language. You can change this at any time in _admin/.</p>
					<div class="nino-admin-card">
						<h3>Available Locales</h3>
						<div id="setup-locales" class="nino-admin-checklist"></div>
					</div>
					<div class="nino-admin-card">
						<h3>Native Locale</h3>
						<div id="setup-native-locale"></div>
					</div>
					<div class="nino-admin-card install-hidden" id="setup-modules-card">
						<h3>Modules</h3>
						<div id="setup-modules" class="nino-admin-checklist"></div>
					</div>
				</div>

				<div id="install-content-webpages">
					<p class="nino-admin-hint nino-admin-hint-lead">Build the project's actual routes: click a row to open it, or "New Route" to add one - an Element URI (a stable identifier, eg. <code>/home</code>), the real Http URI it's reachable at (eg. <code>/</code>), a starting template from <code>_admin/install/library/pages</code>, and each active locale's name/title/description - name is also what shows up in the main menu, if the Navigation module (step 2) is active and its "Show in main navigation" box is checked. Click ↑/↓ to reorder, "Next" batch-generates routes/templates/text/blacklist from the list below.</p>
					<div id="webpages-list"></div>
					<div id="webpages-form" class="install-hidden"></div>
				</div>

				<div id="install-content-personalinfos">
					<p class="nino-admin-hint nino-admin-hint-lead">Fill in the site's company/website info (contact details, author, hosting) - the handful of keys that are always there no matter what step 2 picked. Everything else is fine as the library's generic default; edit it in the Text panel (or Text Keys for technical keys) afterward if it isn't.</p>
					<div id="personalinfos-list"></div>
				</div>

				<div id="install-content-accounts">
					<p class="nino-admin-hint nino-admin-hint-lead">Create the root account: full access to everything, the one you sign in to <code>/_admin</code> with. Submit again for a second one, then continue. Editors with fewer rights are created later, in the Users panel.</p>
					<div id="accounts-list"></div>
					<form id="accounts-add-form" class="nino-admin-card">
						<label class="nino-admin-field" for="accounts-add-mail"><span>Email</span><input id="accounts-add-mail" type="email" autocomplete="off" required></label>
						<label class="nino-admin-field" for="accounts-add-pw"><span>Password</span><input id="accounts-add-pw" type="password" autocomplete="new-password" required></label>
						<button type="submit">Create admin</button>
					</form>
				</div>

				<div id="install-content-finish">
					<p class="nino-admin-hint nino-admin-hint-lead">Set the recovery password. It is not a login: <code>/_admin/recovery.php</code> asks for it when the accounts themselves are what is broken - to restore a backup or reset a password. This is the last step - once set, the wizard locks itself out for good (no way back short of clearing <code>/nino/install/completed</code> in <code>config.php</code> and removing the stored secret).</p>
					<form id="finish-form" class="nino-admin-card">
						<label class="nino-admin-field" for="finish-pw"><span>New recovery password</span><input id="finish-pw" type="password" autocomplete="new-password" required></label>
						<label class="nino-admin-field" for="finish-pw2"><span>Repeat password</span><input id="finish-pw2" type="password" autocomplete="new-password" required></label>
						<button type="submit">Finish installation</button>
					</form>
					<div id="finish-done" class="install-hidden">
						<div class="install-finish-intro">
							<span class="install-finish-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
							<div>
								<span class="nino-admin-eyebrow install-finish-eyebrow">Ready to go</span>
								<h1>Installation complete</h1>
								<p>Choose where you would like to continue.</p>
							</div>
						</div>
						<div class="install-next-steps" aria-label="Next steps">
							<a class="install-next-step install-next-step--frontend" href="[[/nino/dir]]/" target="_blank">
								<span class="install-next-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/></svg></span>
								<span class="install-next-step-copy"><strong>View the Frontend</strong><small>Open the new website and see the result.</small></span>
								<svg class="install-next-step-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
							<a class="install-next-step install-next-step--editor" href="[[/nino/dir]]/_admin/" target="_blank">
								<span class="install-next-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15h6M9 11h1"/></svg></span>
								<span class="install-next-step-copy"><strong>Build your website</strong><small>Open _admin, create routes, build templates and fill your content.</small></span>
								<svg class="install-next-step-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
						</div>
						<p class="install-cleanup-note">The wizard is now locked. It is safe and recommended to delete its folder: <code>rm -rf _admin/install</code>.</p>
					</div>
				</div>

			</main>

			<!-- Shared Back/Next bar - hidden on the finish step (its own
			     form replaces "Next" entirely), Back hidden on the first -->
			<div id="install-actions-wrap" class="nino-admin-actionbar">
				<!--	Every step's message, in the one place a message belongs: beside
							the button that acts on it. They used to sit at the foot of
							their own pane, which on the taller steps is a scroll away from
							Next - so the step said "Applying …" or named what went wrong
							somewhere the operator was not looking.

							Each keeps the id its module writes to, and the pane class on
							#install-page-wrap decides which one is on screen; see
							install/assets/style.css. install-actions-msg is the wizard's
							own and is always there.	-->
				<div id="install-actions-status" class="nino-admin-actionbar-status">
					<p id="install-actions-msg"></p>
					<p id="setup-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="webpages-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="personalinfos-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="accounts-add-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="finish-msg" class="install-step-msg" role="status" aria-live="polite"></p>
				</div>
				<button type="button" id="install-back" class="nino-admin-btn-secondary">Back</button>
				<button type="button" id="install-next" class="nino-admin-btn-primary">Next</button>
			</div>
		</div>

		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/Nino.admin.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/script.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/checks.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/setup.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/webpages.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/personalinfos.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/accounts.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/finish.js"></script>
	</body>
</html>
