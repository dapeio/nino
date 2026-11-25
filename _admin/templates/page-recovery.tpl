<!doctype html>
<html lang="de">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title data-text="/_admin/page/title"></title>
		<link rel="stylesheet" href="[[/nino/dir]]/_admin/assets/style.css">
	</head>
	<body class="nino-admin">
		<!-- Every sentence of this page is a key of the table in recovery-text.js (data-text, data-label, data-title) or is
		     drawn by recovery.js from the same table: the page has no text fills of the project, which may be what is broken -->
		<div class="nino-admin-pagelang" id="page-language-mount"></div>
		<div id="recovery-wrap" class="nino-admin nino-admin-auth nino-admin-auth--form" data-dir="[[/nino/dir]]" data-min-password="[[/_admin/recovery/min-password]]" data-max-password="[[/_admin/recovery/max-password]]">
			<form id="recovery-login" class="nino-admin-auth-card" novalidate>
				[csrf]
				<div class="nino-admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
				<h1 class="nino-admin-auth-title" data-text="/_admin/recovery/title/page"></h1>
				<fieldset id="recovery-login-fields"></fieldset>
			</form>
			<div id="recovery-tools" class="admin-hidden" data-open="[[/_admin/recovery/open]]">
				<div class="nino-admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
				<h1 class="nino-admin-auth-title" tabindex="-1" data-text="/_admin/recovery/title/page"></h1>
				<div id="recovery-sections"></div>
				<div id="recovery-foot" class="nino-admin-actions nino-admin-actions--between"></div>
			</div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/Nino.admin.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/pagetext.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/recovery-text.js"></script>
		<script src="[[/nino/dir]]/_admin/assets/recovery.js"></script>
	</body>
</html>
