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
		<div id="dev-login-wrap">
			<form id="dev-login-form">
				[csrf]
				<p id="dev-login-msg">Dev-Tools</p>
				<label class="admin-field" for="dev-input-pw">
					<span>Password</span>
					<input id="dev-input-pw" type="password" autocomplete="current-password">
				</label>
				<button type="submit">Login</button>
			</form>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_dev/assets/script.js"></script>
	</body>
</html>
