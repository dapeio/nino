[template /_editor/templates/html-header]
		[csrf]
		<div id="editor-page-wrap" class="nino-admin nino-admin-shell nino-admin-shell--rail show-dashboard" data-dir="[[/nino/dir]]" data-public="[[/nino/public]]" data-panels="[[/_editor/panels]]">
			<aside id="editor-bar-wrap" class="nino-admin-rail" aria-label="Editor navigation">
				<!-- Same rail-head shape /_admin uses: brand and account sit on one
				     row on a phone and stack in the sidebar on a desktop -->
				<div id="editor-bar-head" class="nino-admin-rail-head">
					<div class="editor-brand">
						<span class="nino-admin-brand-mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
						<span class="nino-admin-brand-copy"><strong>Nino</strong><small>Editor</small></span>
					</div>
					<div id="editor-user" class="nino-admin-rail-user">
						<span id="editor-user-email">[[/nino/auth/user]]</span>
						<a href="#" id="editor-user-logout" class="nino-admin-rail-danger">[[/_editor/user/logout]]</a>
						<div id="editor-nav-ui">
							<button type="button" id="editor-nav-ui-toggle" aria-haspopup="true" aria-expanded="false" aria-label="[[/_editor/user/settings]]">⚙️</button>
							<div id="editor-nav-ui-menu" class="editor-hidden">
								<button type="button" id="editor-theme-toggle" aria-label="[[/_editor/user/theme]]"></button>
								[[/_editor/localepicker]]
							</div>
						</div>
					</div>
				</div>

				<nav id="editor-nav-wrap" class="nino-admin-nav" aria-label="Editor sections">
					<a href="#" id="editor-nav-dashboard">[[/_editor/nav/dashboard]]</a>
					<a href="#" id="editor-nav-elements">[[/_editor/nav/elements]]</a>
					<a href="#" id="editor-nav-text">[[/_editor/nav/text]]</a>
					<a href="#" id="editor-nav-images">[[/_editor/nav/images]]</a>
					<a href="#" id="editor-nav-user">[[/_editor/nav/user]]</a>
					<a href="#" id="editor-nav-submissions">[[/_editor/nav/submissions]]</a>
					<a href="#" id="editor-nav-newsletter">[[/_editor/nav/newsletter]]</a>
					<a href="#" id="editor-nav-logs">[[/_editor/nav/logs]]</a>
				</nav>
			</aside>
			<main id="editor-content-wrap" class="nino-admin-pane">
				<div id="editor-content-dashboard"></div>
				<div id="editor-content-elements">
					<div id="elements-types"></div>
					<div id="elements-list"></div>
					<div id="elements-form"></div>
				</div>
				<div id="editor-content-text">
					<div id="text-list"></div>
					<div id="text-form"></div>
				</div>
				<div id="editor-content-images">
					<div id="images-list"></div>
					<div id="images-form"></div>
				</div>
				<div id="editor-content-user">
					<div id="users-list"></div>
					<div id="users-form"></div>
				</div>
				<div id="editor-content-submissions">
					<div id="submissions-list"></div>
				</div>
				<div id="editor-content-newsletter">
					<div id="newsletter-list"></div>
				</div>
				<div id="editor-content-logs">
					<div id="logs-list"></div>
				</div>
			</main>
			[jstext]
			[assets /_editor/.cache/script.js]
		</div>
	</body>
</html>
