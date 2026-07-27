[template /_admin/templates/html-header]
		[csrf]
		<div id="admin-page-wrap" class="show-dashboard" data-dir="[[/nino/dir]]">
			<div id="admin-bar-wrap">
				<div id="admin-user">
					<span id="admin-user-email">[[/nino/auth/user]]</span>
					<a href="#" id="admin-user-logout">[[/_admin/user/logout]]</a>
					<div id="admin-nav-ui">
						<button type="button" id="admin-nav-ui-toggle" aria-haspopup="true" aria-expanded="false" aria-label="[[/_admin/user/settings]]">⚙️</button>
						<div id="admin-nav-ui-menu" class="admin-hidden">
							<button type="button" id="admin-theme-toggle" aria-label="[[/_admin/user/theme]]"></button>
							[[/_admin/localepicker]]
						</div>
					</div>
				</div>

				<div id="admin-nav-wrap">
					<a href="#" id="admin-nav-dashboard">[[/_admin/nav/dashboard]]</a>
					<a href="#" id="admin-nav-elements">[[/_admin/nav/elements]]</a>
					<a href="#" id="admin-nav-text">[[/_admin/nav/text]]</a>
					<a href="#" id="admin-nav-images">[[/_admin/nav/images]]</a>
					<a href="#" id="admin-nav-user">[[/_admin/nav/user]]</a>
					<a href="#" id="admin-nav-submissions">[[/_admin/nav/submissions]]</a>
					<a href="#" id="admin-nav-newsletter">[[/_admin/nav/newsletter]]</a>
					<a href="#" id="admin-nav-logs">[[/_admin/nav/logs]]</a>
				</div>
			</div>
			<div id="admin-content-wrap">
				<div id="admin-content-dashboard"></div>
				<div id="admin-content-elements">
					<div id="elements-types"></div>
					<div id="elements-list"></div>
					<div id="elements-form"></div>
				</div>
				<div id="admin-content-text">
					<div id="text-list"></div>
					<div id="text-form"></div>
				</div>
				<div id="admin-content-images">
					<div id="images-list"></div>
					<div id="images-form"></div>
				</div>
				<div id="admin-content-user">
					<div id="users-list"></div>
					<div id="users-form"></div>
				</div>
				<div id="admin-content-submissions">
					<div id="submissions-list"></div>
				</div>
				<div id="admin-content-newsletter">
					<div id="newsletter-list"></div>
				</div>
				<div id="admin-content-logs">
					<div id="logs-list"></div>
				</div>
			</div>
			[jstext]
			[assets /_admin/.cache/script.js]
		</div>
	</body>
</html>