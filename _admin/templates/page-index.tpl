[template /_admin/templates/html-header]
		[csrf]
		<div id="admin-page-wrap" class="nino-admin nino-admin-shell nino-admin-shell--rail" data-dir="[[/nino/dir]]" data-public="[[/nino/public]]">
			<aside id="admin-bar-wrap" class="nino-admin-rail" aria-label="[[/_admin/label/rail]]">
				<!-- Brand and account sit on one row on a phone and stack in the
				     sidebar on a desktop. The fold button only exists on the
				     desktop (see style.css): a workspace panel folds the rail
				     to a column of icons, and this is the way to pin it either
				     way (see Nino.admin.rail in script.js) -->
				<div id="admin-bar-head" class="nino-admin-rail-head">
					<div class="admin-brand">
						<span class="nino-admin-brand-mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-box-icon lucide-box"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
						<span class="nino-admin-brand-copy"><strong>Nino</strong><small>Admin</small></span>
						<button type="button" id="admin-rail-toggle" class="nino-admin-rail-toggle" aria-label="[[/_admin/user/rail]]" title="[[/_admin/user/rail]]" aria-pressed="false"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg></button>
					</div>
					<div id="admin-user" class="nino-admin-rail-user">
						<span id="admin-user-email">[[/nino/auth/user]]</span>
						<a href="#" id="admin-user-logout" class="nino-admin-rail-danger">[[/_admin/user/logout]]</a>
						<div id="admin-nav-ui">
							<button type="button" id="admin-nav-ui-toggle" aria-haspopup="true" aria-expanded="false" aria-label="[[/_admin/user/settings]]"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings-icon lucide-settings"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg></button>
							<div id="admin-nav-ui-menu" class="admin-hidden">
								<button type="button" id="admin-theme-toggle" aria-label="[[/_admin/user/theme]]">
									<svg class="admin-theme-toggle-system" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun-moon-icon lucide-sun-moon"><path d="M12 2v2"/><path d="M14.837 16.385a6 6 0 1 1-7.223-7.222c.624-.147.97.66.715 1.248a4 4 0 0 0 5.26 5.259c.589-.255 1.396.09 1.248.715"/><path d="M16 12a4 4 0 0 0-4-4"/><path d="m19 5-1.256 1.256"/><path d="M20 12h2"/></svg>
									<svg class="admin-theme-toggle-dark" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon-icon lucide-moon"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>
									<svg class="admin-theme-toggle-light" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun-icon lucide-sun"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
								</button>
								[[/_admin/localepicker]]
							</div>
						</div>
					</div>
				</div>

				<!-- One link and one pane per panel, core and module panels alike,
				     rendered from the registry (see Admin::panels()) - a module's
				     panel appears here without a change to this file, and only the
				     panels this account may use are rendered at all -->
				<nav id="admin-nav-wrap" class="nino-admin-nav" aria-label="[[/_admin/label/nav]]">
					[[/_admin/nav]]
				</nav>
			</aside>
			<main id="admin-content-wrap" class="nino-admin-pane">
				[[/_admin/panes]]
			</main>
			[jstext]
			[assets /_admin/.cache/script.js]
		</div>
	</body>
</html>
