[template /_admin/templates/html-header]

		<form id="form-login" class="nino-admin nino-admin-auth nino-admin-auth-card">
			[csrf]
			<div class="nino-admin-auth-brand" aria-hidden="true"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>Nino</div>
			<h1 class="nino-admin-auth-title">Admin</h1>
			<p id="form-message">[[/_admin/login/msg/welcome]]</p>
			<label class="nino-admin-field" for="input-user">
				<span>[[/_admin/login/label/user]]</span>
				<input id="input-user" type="email" autocomplete="username">
			</label>
			<label class="nino-admin-field" for="input-pw">
				<span>[[/_admin/login/label/pw]]</span>
				<input id="input-pw" type="password" autocomplete="current-password">
			</label>
			<button id="submit" type="submit">[[/_admin/login/label/submit]]</button>
			[[/_admin/localepicker]]
		</form>
		[jstext]
		[assets /_admin/.cache/login.js]
	</body>
</html>
