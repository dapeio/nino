[template /_admin/templates/html-header]

		<main>
		<form id="form-login" class="nino-admin nino-admin-auth nino-admin-auth--form nino-admin-auth-card" novalidate>
			[csrf]
			<div class="nino-admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
			<h1 class="nino-admin-auth-title">Admin</h1>
			<fieldset id="login-fields"></fieldset>
			[[/_admin/localepicker]]
		</form>
		</main>
		[jstext]
		[assets /_admin/.cache/login.js]
	</body>
</html>
