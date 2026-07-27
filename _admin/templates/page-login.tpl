[template /_admin/templates/html-header]

		<form id="form-login">
			[csrf]
			<p id="form-message">[[/_admin/login/msg/welcome]]</p>
			<label class="admin-field" for="input-user">
				<span>[[/_admin/login/label/user]]</span>
				<input id="input-user" type="email" autocomplete="username">
			</label>
			<label class="admin-field" for="input-pw">
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