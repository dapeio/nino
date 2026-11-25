<!doctype html>
<html lang="de">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title data-text="/_admin/page/title"></title>
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/install/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
	</head>
	<body class="nino-admin">
		[csrf]
		<!-- Every sentence of this page is a key of the table in install/assets/text.js (data-text, data-label, data-title) or is
		     drawn by the scripts of the steps from the same table, in German or in English: the project the wizard makes has no
		     text fills yet -->
		<div id="install-page-wrap" class="nino-admin nino-admin-shell nino-admin-shell--rail show-checks" data-dir="[[/nino/dir]]" data-min-password="[[/_admin/install/min-password]]" data-max-password="[[/_admin/install/max-password]]" data-account="[[/_admin/install/account]]">
			<aside id="install-shell-rail" class="nino-admin-rail" data-label="/_admin/install/rail/label">
				<div id="install-bar-wrap" class="nino-admin-rail-head">
					<div id="install-bar-title">
						<span class="nino-admin-brand-mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
						<span class="nino-admin-brand-copy"><strong>Nino</strong><small data-text="/_admin/install/rail/setup"></small></span>
					</div>
				</div>

				<!-- Progress display only, not a jump-menu - each step's own
				     Back/Next controls the wizard's flow, see script.js. On a phone the
				     steps wrap into rows: nothing is cut off and nothing scrolls -->
				<div id="install-nav-wrap" class="nino-admin-nav">
					<span id="install-nav-checks" class="active" data-text="/_admin/install/step/checks"></span>
					<span id="install-nav-setup" data-text="/_admin/install/step/setup"></span>
					<span id="install-nav-personalinfos" data-text="/_admin/install/step/personalinfos"></span>
					<span id="install-nav-accounts" data-text="/_admin/install/step/accounts"></span>
					<span id="install-nav-applications" data-text="/_admin/install/step/applications"></span>
					<span id="install-nav-finish" data-text="/_admin/install/step/finish"></span>
				</div>
			</aside>

			<main id="install-content-wrap" class="nino-admin-pane">

				<div class="nino-admin-pagelang" id="page-language-mount"></div>

				<!-- The page's heading. A step opens with its lead sentence and
				     a heading a screen reader goes to, the rail beside it already
				     says which step that is, so there is nowhere a visible <h1>
				     would belong - and a page without one is a page a screen
				     reader has no way of summarising -->
				<h1 class="nino-admin-sr-only" data-text="/_admin/install/title/page"></h1>

				<div id="install-content-checks">
					<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/checks"></h2>
					<p class="nino-admin-hint nino-admin-hint-lead" data-text="/_admin/install/lead/checks"></p>
					<div id="checks-results"></div>
					<div id="checks-recheck"></div>
				</div>

				<div id="install-content-setup">
					<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/setup"></h2>
					<p class="nino-admin-hint nino-admin-hint-lead" data-text="/_admin/install/lead/setup"></p>
					<div id="setup-content"></div>
				</div>

				<div id="install-content-personalinfos">
					<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/personalinfos"></h2>
					<p class="nino-admin-hint nino-admin-hint-lead" data-text="/_admin/install/lead/personalinfos"></p>
					<div id="personalinfos-list"></div>
				</div>

				<div id="install-content-accounts">
					<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/accounts"></h2>
					<p class="nino-admin-hint nino-admin-hint-lead" data-text="/_admin/install/lead/accounts"></p>
					<div id="accounts-content"></div>
				</div>

				<div id="install-content-applications">
					<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/applications"></h2>
					<p class="nino-admin-hint nino-admin-hint-lead" data-text="/_admin/install/lead/applications"></p>
					<div id="applications-content"></div>
				</div>

				<div id="install-content-finish">
					<h2 class="nino-admin-sr-only" data-text="/_admin/install/step/finish"></h2>
					<p class="nino-admin-hint nino-admin-hint-lead" data-text="/_admin/install/lead/finish"></p>
					<div id="finish-content"></div>
					<div id="finish-key-wrap"></div>
					<div id="finish-done" class="install-hidden">
						<div class="install-finish-intro">
							<span class="install-finish-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
							<div>
								<span class="nino-admin-eyebrow install-finish-eyebrow" data-text="/_admin/install/finish/ready"></span>
								<h2 data-text="/_admin/install/finish/complete"></h2>
								<p data-text="/_admin/install/finish/choose"></p>
							</div>
						</div>
						<div class="install-next-steps" id="finish-next-steps" data-label="/_admin/install/finish/next-steps">
							<a class="install-next-step install-next-step--frontend" href="[[/nino/dir]]/" target="_blank">
								<span class="install-next-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/></svg></span>
								<span class="install-next-step-copy"><strong data-text="/_admin/install/finish/frontend"></strong><small data-text="/_admin/install/finish/frontend-note"></small></span>
								<svg class="install-next-step-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
							<a class="install-next-step install-next-step--editor" href="[[/nino/dir]]/_admin/" target="_blank">
								<span class="install-next-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15h6M9 11h1"/></svg></span>
								<span class="install-next-step-copy"><strong data-text="/_admin/install/finish/editor"></strong><small data-text="/_admin/install/finish/editor-note"></small></span>
								<svg class="install-next-step-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
						</div>
						<p class="install-cleanup-note" data-text="/_admin/install/finish/cleanup"></p>
					</div>
				</div>

			</main>

			<!-- The bar at the foot - the one block of the workbench, with the line that says how
			     the last action went, Back beside the one main button - is drawn by script.js -->
		</div>

		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/Nino.admin.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/pagetext.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/text.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/script.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/session.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/checks.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/setup.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/personalinfos.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/accounts.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/applications.js"></script>
		<script src="[[/nino/dir]]/_admin/install/assets/finish.js"></script>
	</body>
</html>
