<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Appearance</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_nino/Nino.admin.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_design/assets/style.css">
	</head>
	<body>
		[csrf]
		<div id="theme-page-wrap" class="nino-admin nino-admin-shell nino-admin-shell--rail show-theme">
			<aside class="nino-admin-rail" aria-label="Appearance navigation">
				<div class="nino-admin-rail-head">
					<div class="theme-rail-brand">
						<div class="nino-admin-brand-mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></div>
						<span class="nino-admin-brand-copy"><strong>Nino</strong><small>Appearance</small></span>
					</div>
					[admin-tools design]
				</div>
				<nav class="nino-admin-nav" id="theme-nav">
					<a href="#" id="theme-nav-theme" class="active">Theme</a>
					<a href="#" id="theme-nav-design">Design</a>
					<a href="#" id="theme-nav-header">Header</a>
					<a href="#" id="theme-nav-footer">Footer</a>
				</nav>
			</aside>

			<main class="nino-admin-pane" id="theme-pane">
				<section id="theme-content-theme">
					<h1>Theme</h1>
					<p class="nino-admin-hint nino-admin-hint-lead">Choose the component mapping, typography and visual character. Applying a Theme establishes its complete recommended baseline: its Design values plus its Header and Footer. Each of those remains independently editable in the following dialogs.</p>
					<div id="theme-grid" aria-live="polite"></div>
					<p id="theme-empty" class="nino-admin-hint theme-hidden"><strong>No theme variants are available.</strong><br>The directory /_install/library must exist.</p>
				</section>

				<section id="theme-content-design">
					<h1>Design</h1>
					<p class="nino-admin-hint nino-admin-hint-lead">Set the values every stylesheet reads from. Colours are generated as measured background/text pairs; Volume, Spacing and Shaping produce the shared size raster.</p>

					<div id="theme-design-controls">
						<section class="theme-design-section">
							<h2>Colour</h2>
							<div class="theme-design-grid">
								<label class="theme-field">
									<span>Primary</span>
									<span class="theme-color-control">
										<input type="color" id="theme-design-primary" class="theme-color-swatch">
										<input type="text" id="theme-design-primary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7">
									</span>
								</label>
								<label class="theme-field">
									<span>Secondary <small>optional</small></span>
									<span class="theme-color-control">
										<input type="color" id="theme-design-secondary" class="theme-color-swatch">
										<input type="text" id="theme-design-secondary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7" placeholder="follows Primary">
									</span>
								</label>
								<label class="theme-field"><span>Contrast <small>text strength</small></span><select id="theme-design-contrast" class="nino-admin-input"></select></label>
								<label class="theme-field"><span>Colours <small>saturation</small></span><select id="theme-design-colors" class="nino-admin-input"></select></label>
							</div>
							<div class="theme-design-specimen">
								<div>
									<h3>Light</h3>
									<div id="theme-design-preview-light" class="theme-design-surfaces" aria-label="Light generated surfaces"></div>
								</div>
								<div>
									<h3>Dark</h3>
									<div id="theme-design-preview-dark" class="theme-design-surfaces" aria-label="Dark generated surfaces"></div>
								</div>
							</div>
						</section>

						<section class="theme-design-section">
							<h2>Size</h2>
							<div class="theme-design-grid">
								<label class="theme-field"><span>Volume <small>type scale</small></span><select id="theme-design-volume" class="nino-admin-input"></select></label>
								<label class="theme-field"><span>Spacing <small>gaps and line height</small></span><select id="theme-design-spacing" class="nino-admin-input"></select></label>
								<label class="theme-field"><span>Shaping <small>corner radius</small></span><select id="theme-design-shaping" class="nino-admin-input"></select></label>
							</div>
							<div id="theme-design-sizes" class="theme-design-specimen" aria-label="Generated size raster"></div>
						</section>
					</div>
				</section>

				<section id="theme-content-header">
					<h1>Header</h1>
					<p class="nino-admin-hint nino-admin-hint-lead">Choose the site's <code>&lt;header&gt;</code> independently. The preview combines the current Theme and saved Design with the selected frame.</p>
					<p id="theme-frame-header-empty" class="nino-admin-hint theme-hidden"><strong>No Header variants are available.</strong><br>The directory /_install/library must exist.</p>
					<div id="theme-frame-header-panel" class="theme-frame-panel theme-hidden">
						<label class="theme-field theme-frame-select"><span>Header variant</span><select id="theme-frame-header" class="nino-admin-input"></select></label>
						<div class="theme-frame-stage"><iframe id="theme-frame-header-preview" class="theme-frame-view" title="Header preview" sandbox="" loading="lazy"></iframe></div>
					</div>
				</section>

				<section id="theme-content-footer">
					<h1>Footer</h1>
					<p class="nino-admin-hint nino-admin-hint-lead">Choose the site's <code>&lt;footer&gt;</code> without changing Theme, Design or Header. Its own template and stylesheet are replaced together.</p>
					<p id="theme-frame-footer-empty" class="nino-admin-hint theme-hidden"><strong>No Footer variants are available.</strong><br>The directory /_install/library must exist.</p>
					<div id="theme-frame-footer-panel" class="theme-frame-panel theme-hidden">
						<label class="theme-field theme-frame-select"><span>Footer variant</span><select id="theme-frame-footer" class="nino-admin-input"></select></label>
						<div class="theme-frame-stage"><iframe id="theme-frame-footer-preview" class="theme-frame-view" title="Footer preview" sandbox="" loading="lazy"></iframe></div>
					</div>
				</section>
			</main>

			<div class="nino-admin-actionbar">
				<p id="theme-action-status" class="nino-admin-actionbar-status" role="status" aria-live="polite"></p>
				<button type="button" class="nino-admin-btn-primary" id="theme-action-save">Apply Theme</button>
			</div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_design/assets/design.js"></script>
	</body>
</html>
