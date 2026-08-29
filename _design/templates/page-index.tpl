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
					<a href="#" id="theme-nav-header">Header</a>
					<a href="#" id="theme-nav-footer">Footer</a>
					<a href="#" id="theme-nav-design">Design</a>
				</nav>
			</aside>

			<main class="nino-admin-pane" id="theme-pane">
				<section id="theme-content-theme">
					<div id="theme-grid" aria-live="polite"></div>
					<p id="theme-empty" class="nino-admin-hint theme-hidden"><strong>No theme variants are available.</strong><br>The directory /_install/library must exist.</p>
				</section>

				<section id="theme-content-design">

					<!--	Two columns, and the split is the point: every knob is
								visible at once on the left, and one page on the right shows
								what all of them together produce. Colour chips and size
								specimens showed each setting in isolation, which is exactly
								where a design decision cannot be judged.	-->
					<div class="theme-design-split">

						<div class="theme-design-controls" id="theme-design-controls">
							<div class="nino-admin-tabs" role="tablist" aria-label="Design settings">
								<button type="button" role="tab" id="theme-design-tab-colour" class="nino-admin-tab is-active" aria-selected="true" aria-controls="theme-design-panel-colour">Colour</button>
								<button type="button" role="tab" id="theme-design-tab-raster" class="nino-admin-tab" aria-selected="false" aria-controls="theme-design-panel-raster">Raster</button>
							</div>

							<div class="nino-admin-tabpanel theme-design-panel" role="tabpanel" id="theme-design-panel-colour" aria-labelledby="theme-design-tab-colour">
								<div id="theme-design-colours">
									<label class="theme-field" id="theme-design-primary-field">
										<span>Primary</span>
										<span class="theme-color-control">
											<input type="color" id="theme-design-primary" class="theme-color-swatch">
											<input type="text" id="theme-design-primary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7">
										</span>
									</label>
									<label class="theme-field" id="theme-design-secondary-field">
										<span>Secondary</span>
										<span class="theme-color-control">
											<input type="color" id="theme-design-secondary" class="theme-color-swatch">
											<input type="text" id="theme-design-secondary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7" placeholder="auto">
										</span>
										<small>overrides Harmony</small>
									</label>
								</div>
								<div id="theme-design-knobs-colour"></div>
							</div>

							<div class="nino-admin-tabpanel theme-design-panel" role="tabpanel" id="theme-design-panel-raster" aria-labelledby="theme-design-tab-raster" hidden>
								<div id="theme-design-knobs-raster"></div>
							</div>
						</div>

						<div class="theme-design-stage">
							<div class="theme-design-modes" role="group" aria-label="Preview mode">
								<button type="button" id="theme-design-mode-light" class="theme-design-mode is-active" aria-pressed="true">Light</button>
								<button type="button" id="theme-design-mode-dark" class="theme-design-mode" aria-pressed="false">Dark</button>
								<!--	The way back out, at the top of the settings it undoes
											rather than at the foot of the pane - the same place
											/_install keeps it. Present only while there is
											something to discard, see design.js's _updateAction	-->
								<button type="button" id="theme-design-reset" class="nino-admin-btn-secondary" hidden>Back to the theme&rsquo;s values</button>
							</div>
							<!--	Sandboxed, and delivered as a document rather than
										spliced in: the example styles bare element selectors
										and sets :root variables that would otherwise land on
										this tool's own shell	-->
							<div class="theme-design-port" id="theme-design-example-port">
								<iframe id="theme-design-example" class="theme-design-view" title="Live preview of the current design" sandbox="" loading="lazy"></iframe>
							</div>
						</div>
					</div>
				</section>

				<section id="theme-content-header">
					<p id="theme-frame-header-empty" class="nino-admin-hint theme-hidden"><strong>No Header variants are available.</strong><br>The directory /_install/library must exist.</p>
					<div id="theme-frame-header-panel" class="theme-frame-panel theme-hidden">
						<label class="theme-field theme-frame-select"><span>Header variant</span><select id="theme-frame-header" class="nino-admin-input"></select></label>
						<div class="theme-frame-stage"><iframe id="theme-frame-header-preview" class="theme-frame-view" title="Header preview" sandbox="" loading="lazy"></iframe></div>
					</div>
				</section>

				<section id="theme-content-footer">
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

		<!-- Theme preview lightbox - a single, reused overlay filled by
		     design.js rather than one per tile, see its _openLightbox() -->
		<div id="theme-lightbox" class="theme-hidden">
			<img id="theme-lightbox-image" src="" alt="">
			<p id="theme-lightbox-caption"></p>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_nino/Nino.admin.js"></script>
		<script src="[[/nino/dir]]/_design/assets/design.js"></script>
	</body>
</html>
