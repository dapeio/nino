<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Install</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_install/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_nino/Nino.admin.css">
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
					<span id="install-nav-setup">2. Setup</span>
					<span id="install-nav-themes">3. Themes</span>
					<span id="install-nav-header">4. Header</span>
					<span id="install-nav-footer">5. Footer</span>
					<span id="install-nav-design">6. Design</span>
					<span id="install-nav-webpages">7. Routes</span>
					<span id="install-nav-personalinfos">8. Personal Infos</span>
					<span id="install-nav-admin">9. Admins</span>
					<span id="install-nav-finish">10. Finish</span>
				</div>
			</aside>

			<main id="install-content-wrap" class="nino-admin-pane">

				<div id="install-content-checks">
					<p class="nino-admin-hint nino-admin-hint-lead">PHP version, extensions and file/folder permissions Nino needs to run.</p>
					<div id="checks-results"></div>
					<button type="button" id="checks-refresh">Recheck</button>
				</div>

				<div id="install-content-setup">
					<p class="nino-admin-hint nino-admin-hint-lead">Pick locales and modules - assembles routes, templates and text from <code>_install/library</code>. Whatever's checked when you hit "Next" is the whole picture: unchecking something and coming back here replaces the previous selection, it doesn't add to it - though a route/template/text file already written for something you un-pick still has to be removed by hand, see <code>docs/_install.md</code>.</p>
					<div class="nino-admin-card">
						<h3>Available Locales</h3>
						<div id="setup-locales" class="nino-admin-checklist"></div>
					</div>
					<div class="nino-admin-card">
						<h3>Native Locale</h3>
						<div id="setup-native-locale"></div>
					</div>
					<div class="nino-admin-card">
						<h3>Modules</h3>
						<div id="setup-modules" class="nino-admin-checklist"></div>
					</div>
				</div>

				<div id="install-content-themes">
					<p class="nino-admin-hint nino-admin-hint-lead">Pick the site's look - one complete theme from <code>library/themes</code>. Applying copies whatever its manifest lists (its stylesheet, the webfonts that stylesheet uses, any images it ships) into the project and points <code>config.php</code>'s css bundle at it. Click a tile's preview to enlarge it. Exactly one theme is active at a time: picking a different one later overwrites its files rather than adding to them.</p>
					<div id="themes-grid"></div>
				</div>

				<div id="install-content-header">
					<p class="nino-admin-hint nino-admin-hint-lead">Pick the site's <code>&lt;header&gt;</code>. The theme chooses the version it was drawn against; this separate step lets you replace it before the Design values are settled against it. The base page templates include the installed copy through <code>&#91;template /templates/theme.header&#93;</code>.</p>
					<p id="themes-frame-header-unavailable" class="nino-admin-hint install-hidden">This delivery ships no header variants. Press "Next" to continue.</p>
					<!-- A version number says nothing about what a frame looks
					     like, so themes/frame renders the real template into an
					     isolated document rather than splicing its broad CSS into
					     the installer page. -->
					<div id="themes-frame-header-panel" class="install-theme-panel install-hidden">
						<div class="install-frame-pick">
							<label class="install-theme-field">
								<span>Header variant</span>
								<select id="themes-frame-header" class="nino-admin-input"></select>
							</label>
							<div class="install-frame-stage">
								<iframe id="themes-frame-header-preview" class="install-frame-view" title="Header preview" sandbox="" loading="lazy"></iframe>
							</div>
						</div>
					</div>
				</div>

				<div id="install-content-footer">
					<p class="nino-admin-hint nino-admin-hint-lead">Pick the site's <code>&lt;footer&gt;</code>. It is installed independently from the Header, on the theme chosen in the preceding step. The base page templates include the installed copy through <code>&#91;template /templates/theme.footer&#93;</code>.</p>
					<p id="themes-frame-footer-unavailable" class="nino-admin-hint install-hidden">This delivery ships no footer variants. Press "Next" to continue.</p>
					<div id="themes-frame-footer-panel" class="install-theme-panel install-hidden">
						<div class="install-frame-pick">
							<label class="install-theme-field">
								<span>Footer variant</span>
								<select id="themes-frame-footer" class="nino-admin-input"></select>
							</label>
							<div class="install-frame-stage">
								<iframe id="themes-frame-footer-preview" class="install-frame-view" title="Footer preview" sandbox="" loading="lazy"></iframe>
							</div>
						</div>
					</div>
				</div>

				<div id="install-content-design">
					<p id="design-unavailable" class="nino-admin-hint install-hidden">This delivery ships without <code>/_design</code>, so there is nothing to generate here - the theme's own stylesheet decides the colours instead. Press "Next" to continue.</p>

					<!--	Same split as /_design: every setting visible at once on
								the left, one page on the right showing what all of them
								together produce. The example itself is built by /_design -
								see Install.php's apiDesignRead.	-->
					<div id="design-controls" class="install-design-split">

						<div class="install-design-controls">
							<div class="nino-admin-tabs" role="tablist" aria-label="Design settings">
								<button type="button" role="tab" id="themes-design-tab-colour" class="nino-admin-tab is-active" aria-selected="true" aria-controls="themes-design-panel-colour">Colour</button>
								<button type="button" role="tab" id="themes-design-tab-raster" class="nino-admin-tab" aria-selected="false" aria-controls="themes-design-panel-raster">Raster</button>
							</div>

							<div class="nino-admin-tabpanel install-design-panel" role="tabpanel" id="themes-design-panel-colour" aria-labelledby="themes-design-tab-colour">
								<div id="themes-design-colours">
									<label class="install-theme-field" id="themes-design-primary-field">
										<span>Primary</span>
										<span class="install-theme-color">
											<input type="color" id="themes-design-primary" class="install-theme-swatch">
											<input type="text" id="themes-design-primary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7">
										</span>
									</label>
									<label class="install-theme-field" id="themes-design-secondary-field">
										<span>Secondary</span>
										<span class="install-theme-color">
											<input type="color" id="themes-design-secondary" class="install-theme-swatch">
											<input type="text" id="themes-design-secondary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7" placeholder="auto">
										</span>
										<small>overrides Harmony</small>
									</label>
								</div>
								<div id="themes-design-knobs-colour"></div>
							</div>

							<div class="nino-admin-tabpanel install-design-panel" role="tabpanel" id="themes-design-panel-raster" aria-labelledby="themes-design-tab-raster" hidden>
								<div id="themes-design-knobs-raster"></div>
							</div>
						</div>

						<div class="install-design-stage">
							<div class="install-design-modes" role="group" aria-label="Preview mode">
								<button type="button" id="themes-design-mode-light" class="install-design-mode is-active" aria-pressed="true">Light</button>
								<button type="button" id="themes-design-mode-dark" class="install-design-mode" aria-pressed="false">Dark</button>
								<button type="button" id="design-reset" class="nino-admin-btn-secondary" hidden>Back to the theme&rsquo;s values</button>
							</div>
							<!--	The frame renders at a desktop's layout width and is
										scaled into this box - see Nino.adminUi.scaleFrame().
										Without it the panel is narrower than the narrowest
										content ceiling Width offers, and all three settings
										for it look the same	-->
							<div class="install-design-port" id="themes-design-example-port">
								<iframe id="themes-design-example" class="install-design-view" title="Live preview of the current design" sandbox="" loading="lazy"></iframe>
							</div>
						</div>
					</div>

					<!--	Nothing here is written until Next, so the position to
								offer a way back to is the one the theme handed over -
								see design.js's _reset. Shown only while the two differ. -->
					<div class="install-design-footer">
					</div>
				</div>

				<div id="install-content-webpages">
					<p class="nino-admin-hint nino-admin-hint-lead">Build the project's actual routes: click a row to open it, or "New Route" to add one - an Element URI (a stable identifier, eg. <code>/home</code>), the real Http URI it's reachable at (eg. <code>/</code>), a starting template from <code>_install/library/pages</code>, and each active locale's name/title/description - name is also what shows up in the main menu, if the Navigation module (step 2) is active and its "Show in main navigation" box is checked. Click ↑/↓ to reorder, "Next" batch-generates routes/templates/text/blacklist from the list below.</p>
					<div id="webpages-list"></div>
					<div id="webpages-form" class="install-hidden"></div>
				</div>

				<div id="install-content-personalinfos">
					<p class="nino-admin-hint nino-admin-hint-lead">Fill in the site's company/website info (contact details, author, hosting) - the handful of keys that are always there no matter what steps 2/3 picked. Everything else is fine as the library's generic default; edit it via <code>/_editor</code>'s Text panel (or <code>/_admin</code> for technical keys) afterward if it isn't.</p>
					<div id="personalinfos-list"></div>
				</div>

				<div id="install-content-admin">
					<p class="nino-admin-hint nino-admin-hint-lead">Create at least one <code>/_editor</code> account with full permissions. Submit again for additional admins, then continue.</p>
					<div id="editor-list"></div>
					<form id="editor-add-form" class="nino-admin-card">
						<label class="nino-admin-field" for="editor-add-mail"><span>Email</span><input id="editor-add-mail" type="email" autocomplete="off" required></label>
						<label class="nino-admin-field" for="editor-add-pw"><span>Password</span><input id="editor-add-pw" type="password" autocomplete="new-password" required></label>
						<button type="submit">Create admin</button>
					</form>
				</div>

				<div id="install-content-finish">
					<p class="nino-admin-hint nino-admin-hint-lead">Set the real <code>/_admin</code> password. This is the last step - once set, <code>/_install</code> locks itself out for good (no way back short of clearing <code>/nino/install/completed</code> in <code>config.php</code> and removing the stored password).</p>
					<p class="nino-admin-error" id="finish-warning">Make sure an admin account was created in the previous step first - without one, <code>/_editor</code> login won't be possible afterwards.</p>
					<form id="finish-form" class="nino-admin-card">
						<label class="nino-admin-field" for="finish-pw"><span>New _admin password</span><input id="finish-pw" type="password" autocomplete="new-password" required></label>
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
							<a class="install-next-step install-next-step--admin" href="[[/nino/dir]]/_admin/" target="_blank">
								<span class="install-next-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></span>
								<span class="install-next-step-copy"><strong>Create content in _admin</strong><small>Manage routes, templates and native content.</small></span>
								<svg class="install-next-step-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
							<a class="install-next-step install-next-step--editor" href="[[/nino/dir]]/_editor/" target="_blank">
								<span class="install-next-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15h6M9 11h1"/></svg></span>
								<span class="install-next-step-copy"><strong>Edit content in _editor</strong><small>Update individual texts, elements and images.</small></span>
								<svg class="install-next-step-arrow" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
						</div>
						<p class="install-cleanup-note"><code>/_install</code> is now locked. It is safe and recommended to delete this folder: <code>rm -rf _install</code>.</p>
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
					<p id="themes-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="design-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="header-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="footer-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="webpages-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="personalinfos-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="editor-add-msg" class="install-step-msg" role="status" aria-live="polite"></p>
					<p id="finish-msg" class="install-step-msg" role="status" aria-live="polite"></p>
				</div>
				<button type="button" id="install-back" class="nino-admin-btn-secondary">Back</button>
				<button type="button" id="install-next" class="nino-admin-btn-primary">Next</button>
			</div>
		</div>

		<!-- Theme preview lightbox - a single, reused overlay filled by
		     themes.js rather than one per tile, see its _openLightbox() -->
		<div id="themes-lightbox" class="install-hidden">
			<img id="themes-lightbox-image" src="" alt="">
			<p id="themes-lightbox-caption"></p>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_nino/Nino.admin.js"></script>
		<script src="[[/nino/dir]]/_install/assets/script.js"></script>
		<script src="[[/nino/dir]]/_install/assets/checks.js"></script>
		<script src="[[/nino/dir]]/_install/assets/setup.js"></script>
		<script src="[[/nino/dir]]/_install/assets/themes.js"></script>
		<script src="[[/nino/dir]]/_install/assets/design.js"></script>
		<script src="[[/nino/dir]]/_install/assets/webpages.js"></script>
		<script src="[[/nino/dir]]/_install/assets/personalinfos.js"></script>
		<script src="[[/nino/dir]]/_install/assets/admin.js"></script>
		<script src="[[/nino/dir]]/_install/assets/finish.js"></script>
	</body>
</html>
