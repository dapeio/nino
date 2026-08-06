<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Template Builder</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_editor/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_templates/assets/style.css">
	</head>
	<body>
		<!-- Posts to /_admin, not here: the session flag is _admin's and
		     /_templates only ever reads it (see Templates::handleGet()) -->
		<div id="admin-login-wrap">
			<form id="admin-login-form">
				[csrf]
				<p id="admin-login-msg">Template Builder</p>
				<label class="editor-field" for="admin-input-pw">
					<span>Admin password</span>
					<input id="admin-input-pw" type="password" autocomplete="current-password">
				</label>
				<button type="submit">Login</button>
			</form>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/script.js"></script>
	</body>
</html>
