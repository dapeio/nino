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
				<div class="admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
				<h1 class="admin-auth-title">Template Builder</h1>
				<p id="admin-login-msg">Sign in with the admin password.</p>
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
