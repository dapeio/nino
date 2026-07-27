<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_dev/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_dev/assets/favicon.ico">
		<title>Dev</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_dev/assets/style.css">
	</head>
	<body>
		[csrf]
		<div id="dev-page-wrap" class="show-dashboard">
			<div id="dev-bar-wrap">
				<div id="dev-bar-title">Dev-Tools</div>
				<a href="#" id="dev-logout">Logout</a>
			</div>

			<!-- One tab + content pane per registered module (see Dev::MODULES) -->
			<div id="dev-nav-wrap">
				<a href="#" id="dev-nav-dashboard" class="active">Dashboard</a>
				<a href="#" id="dev-nav-types">Element Types</a>
				<a href="#" id="dev-nav-text">Text</a>
				<a href="#" id="dev-nav-images">Images</a>
				<a href="#" id="dev-nav-users">Users</a>
				<a href="#" id="dev-nav-restore">Restore</a>
				<a href="#" id="dev-nav-config">Config</a>
			</div>

			<div id="dev-content-wrap">
				<div id="dev-content-dashboard"></div>
				<div id="dev-content-types">
					<div id="types-list"></div>
					<div id="types-form"></div>
				</div>
				<div id="dev-content-text">
					<div id="text-list"></div>
					<div id="text-form"></div>
				</div>
				<div id="dev-content-images">
					<div id="images-list"></div>
					<div id="images-form"></div>
				</div>
				<div id="dev-content-users">
					<div id="users-list"></div>
				</div>
				<div id="dev-content-restore">
					<div id="restore-list"></div>
				</div>
				<div id="dev-content-config">
					<div id="config-list"></div>
					<div id="config-form"></div>
				</div>
			</div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/html-editor.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/script.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/dashboard.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/elementtypes.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/text.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/images.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/users.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/restore.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/config.js"></script>
	</body>
</html>
