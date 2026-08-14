[template /_editor/templates/html-header]

		<form id="form-login" class="nino-admin nino-admin-auth nino-admin-auth-card">
			[csrf]
			<div class="nino-admin-auth-brand" aria-hidden="true"><span>N</span>Nino</div>
			<h1 class="nino-admin-auth-title">Editor</h1>
			<p id="form-message">[[/_editor/login/msg/welcome]]</p>
			<label class="nino-admin-field" for="input-user">
				<span>[[/_editor/login/label/user]]</span>
				<input id="input-user" type="email" autocomplete="username">
			</label>
			<label class="nino-admin-field" for="input-pw">
				<span>[[/_editor/login/label/pw]]</span>
				<input id="input-pw" type="password" autocomplete="current-password">
			</label>
			<button id="submit" type="submit">[[/_editor/login/label/submit]]</button>
			[[/_editor/localepicker]]
		</form>
		[jstext]
		[assets /_editor/.cache/login.js]
	</body>
</html>
