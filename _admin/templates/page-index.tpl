<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Admin</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_editor/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
	</head>
	<body>
		[csrf]
		<div id="admin-page-wrap" class="show-dashboard">
			<aside id="admin-shell-rail" aria-label="Admin navigation">
				<div id="admin-bar-wrap">
					<div id="admin-bar-title">
						<span class="admin-brand-mark" aria-hidden="true">N</span>
						<span class="admin-brand-copy"><strong>Nino</strong><small>Admin</small></span>
					</div>
					<div id="admin-bar-actions">
						<a href="#" id="admin-logout">Logout</a>
					</div>
				</div>

				<!-- One tab + content pane per registered module (see Admin::MODULES) -->
				<nav id="admin-nav-wrap" aria-label="Admin sections">
					<a href="#" id="admin-nav-dashboard" class="active">Dashboard</a>
					<a href="#" id="admin-nav-types">Element Types</a>
					<a href="#" id="admin-nav-text">Text</a>
					<a href="#" id="admin-nav-pages">Pages</a>
					<a href="#" id="admin-nav-images">Images</a>
					<a href="#" id="admin-nav-users">Users</a>
					<a href="#" id="admin-nav-restore">Restore</a>
					<a href="#" id="admin-nav-config">Config</a>
				</nav>
			</aside>

			<main id="admin-content-wrap">
				<div id="admin-content-dashboard"></div>
				<div id="admin-content-types">
					<div id="types-list"></div>
					<div id="types-form"></div>
				</div>
				<div id="admin-content-text">
					<div id="text-list"></div>
					<div id="text-form"></div>
				</div>
				<div id="admin-content-pages">
					<div id="pages-list"></div>
					<div id="pages-form"></div>
				</div>
				<div id="admin-content-images">
					<div id="images-list"></div>
					<div id="images-form"></div>
				</div>
				<div id="admin-content-users">
					<div id="users-list"></div>
				</div>
				<div id="admin-content-restore">
					<div id="restore-list"></div>
				</div>
				<div id="admin-content-config">
					<div id="config-list"></div>
					<div id="config-form"></div>
				</div>
			</main>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_editor/assets/html-editor.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/script.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/dashboard.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/elementtypes.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/text.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/pages.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/images.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/users.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/restore.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/config.js"></script>
	</body>
</html>
