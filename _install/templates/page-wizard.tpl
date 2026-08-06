<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Install</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_editor/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_install/assets/style.css">
	</head>
	<body>
		[csrf]
		<div id="install-page-wrap" class="show-checks">
			<div id="install-bar-wrap">
				<div id="install-bar-title">Nino Setup</div>
			</div>

			<!-- Progress display only, not a jump-menu - each step's own
			     Back/Next controls the wizard's flow, see script.js -->
			<div id="install-nav-wrap">
				<span id="install-nav-checks" class="active">1. Environment</span>
				<span id="install-nav-setup">2. Setup</span>
				<span id="install-nav-themes">3. Themes</span>
				<span id="install-nav-webpages">4. Webpages</span>
				<span id="install-nav-personalinfos">5. Personal Infos</span>
				<span id="install-nav-admin">6. Admins</span>
				<span id="install-nav-finish">7. Finish</span>
			</div>

			<div id="install-content-wrap">

				<div id="install-content-checks">
					<p class="admin-hint">PHP version, extensions and file/folder permissions Nino needs to run.</p>
					<div id="checks-results"></div>
					<button type="button" id="checks-refresh">Recheck</button>
				</div>

				<div id="install-content-setup">
					<p class="admin-hint">Pick locales and modules - assembles routes, templates and text from <code>_install/library</code>. Whatever's checked when you hit "Next" is the whole picture: unchecking something and coming back here replaces the previous selection, it doesn't add to it - though a route/template/text file already written for something you un-pick still has to be removed by hand, see <code>docs/_install.md</code>.</p>
					<div class="install-setup-group">
						<h3>Available Locales</h3>
						<div id="setup-locales"></div>
					</div>
					<div class="install-setup-group">
						<h3>Native Locale</h3>
						<div id="setup-native-locale"></div>
					</div>
					<div class="install-setup-group">
						<h3>Modules</h3>
						<div id="setup-modules"></div>
					</div>
					<p id="setup-msg"></p>
				</div>

				<div id="install-content-themes">
					<p class="admin-hint">Pick the site's look - one complete theme from <code>_install/library/themes</code>. Applying copies whatever its manifest lists (its stylesheet, the webfonts that stylesheet uses, any images it ships) into the project and points <code>config.php</code>'s css bundle at it. Click a tile's preview to enlarge it. Exactly one theme is active at a time: picking a different one later overwrites its files rather than adding to them.</p>
					<div id="themes-grid"></div>
					<p id="themes-msg"></p>
				</div>

				<div id="install-content-webpages">
					<p class="admin-hint">Build the project's actual pages: click a row to open it, or "New Webpage" to add one - an Element URI (a stable identifier, eg. <code>/home</code>), the real Http URI it's reachable at (eg. <code>/</code>), a starting template from <code>_install/library/pages</code>, and each active locale's name/title/description - name is also what shows up in the main menu, if the Navigation module (step 2) is active and its "Show in main navigation" box is checked. Click ↑/↓ to reorder, "Next" batch-generates routes/templates/text/blacklist from the list below.</p>
					<div id="webpages-list"></div>
					<div id="webpages-form" class="admin-hidden"></div>
					<p id="webpages-msg"></p>
				</div>

				<div id="install-content-personalinfos">
					<p class="admin-hint">Fill in the site's company/website info (contact details, author, hosting) - the handful of keys that are always there no matter what steps 2/3 picked. Everything else is fine as the library's generic default; edit it via <code>/_editor</code>'s Text panel (or <code>/_admin</code> for technical keys) afterward if it isn't.</p>
					<div id="personalinfos-list"></div>
					<p id="personalinfos-msg"></p>
				</div>

				<div id="install-content-admin">
					<p class="admin-hint">Create at least one <code>/_editor</code> account with full permissions. Submit again for additional admins, then continue.</p>
					<div id="editor-list"></div>
					<form id="editor-add-form">
						<label for="editor-add-mail"><span>Email</span><input id="editor-add-mail" type="email" autocomplete="off" required></label>
						<label for="editor-add-pw"><span>Password</span><input id="editor-add-pw" type="password" autocomplete="new-password" required></label>
						<p id="editor-add-msg"></p>
						<button type="submit">Create admin</button>
					</form>
				</div>

				<div id="install-content-finish">
					<p class="admin-hint">Set the real <code>/_admin</code> password. This is the last step - once set, <code>/_install</code> locks itself out for good (no way back short of hand-editing <code>_admin/Admin.php</code>).</p>
					<p class="admin-error" id="finish-warning">Make sure an admin account was created in the previous step first - without one, <code>/_editor</code> login won't be possible afterwards.</p>
					<form id="finish-form">
						<label class="editor-field" for="finish-pw"><span>New _admin password</span><input id="finish-pw" type="password" autocomplete="new-password" required></label>
						<label class="editor-field" for="finish-pw2"><span>Repeat password</span><input id="finish-pw2" type="password" autocomplete="new-password" required></label>
						<p id="finish-msg"></p>
						<button type="submit">Finish installation</button>
					</form>
					<div id="finish-done" class="admin-hidden">
						<p>Installation complete. You can now sign in at <a href="[[/nino/dir]]/_admin/">/_admin</a> and <a href="[[/nino/dir]]/_editor/">/_editor</a>.</p>
						<p class="admin-hint"><code>/_install</code> is now locked. It's safe (and recommended) to delete this folder: <code>rm -rf _install</code>.</p>
					</div>
				</div>

			</div>

			<!-- Shared Back/Next bar - hidden on the finish step (its own
			     form replaces "Next" entirely), Back hidden on the first -->
			<div id="install-actions-wrap">
				<p id="install-actions-msg"></p>
				<button type="button" id="install-back">Back</button>
				<button type="button" id="install-next">Next</button>
			</div>
		</div>

		<!-- Theme preview lightbox - a single, reused overlay filled by
		     themes.js rather than one per tile, see its _openLightbox() -->
		<div id="themes-lightbox" class="admin-hidden">
			<img id="themes-lightbox-image" src="" alt="">
			<p id="themes-lightbox-caption"></p>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_install/assets/script.js"></script>
		<script src="[[/nino/dir]]/_install/assets/checks.js"></script>
		<script src="[[/nino/dir]]/_install/assets/setup.js"></script>
		<script src="[[/nino/dir]]/_install/assets/themes.js"></script>
		<script src="[[/nino/dir]]/_install/assets/webpages.js"></script>
		<script src="[[/nino/dir]]/_install/assets/personalinfos.js"></script>
		<script src="[[/nino/dir]]/_install/assets/admin.js"></script>
		<script src="[[/nino/dir]]/_install/assets/finish.js"></script>
	</body>
</html>
