[template /_editor/templates/html-header]

		<form id="form-login">
			[csrf]
			<div class="editor-auth-brand" aria-hidden="true"><span>N</span>Nino</div>
			<h1 class="editor-auth-title">Editor</h1>
			<p id="form-message">[[/_editor/login/msg/welcome]]</p>
			<label class="editor-field" for="input-user">
				<span>[[/_editor/login/label/user]]</span>
				<input id="input-user" type="email" autocomplete="username">
			</label>
			<label class="editor-field" for="input-pw">
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
