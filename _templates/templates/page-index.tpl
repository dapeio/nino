<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<link rel="icon" type="image/png" sizes="32x32" href="[[/nino/dir]]/_admin/assets/favicon-32x32.png">
		<link rel="icon" href="[[/nino/dir]]/_admin/assets/favicon.ico">
		<title>Template Builder</title>
		<link rel="stylesheet" href="[[/nino/dir]]/_templates/assets/style.css">
		<link rel="stylesheet" href="[[/nino/dir]]/_nino/Nino.admin.css">
	</head>
	<body>
		[csrf]
		<div id="pd-app" class="nino-admin nino-admin-shell" data-dir="[[/nino/dir]]" data-public="[[/nino/public]]">
			<header id="pd-topbar">
				<div class="pd-head-rail">
					<a class="pd-brand" href="[[/nino/dir]]/_templates/" aria-label="Templates home">
						<span class="nino-admin-brand-mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
						<span class="nino-admin-brand-copy"><strong>Nino</strong><small>Templates</small></span>
					</a>
					<a href="[[/nino/dir]]/_admin/" class="pd-back-admin"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/><path d="M21 12H9"/></svg><span>Back to Admin</span></a>
				</div>
				<div id="pd-document-meta" aria-live="polite">
					<strong id="pd-document-title">No template selected</strong>
					<span id="pd-document-detail">Choose or create a page template to begin.</span>
				</div>
				<div id="pd-top-actions">
					<span id="pd-save-state" class="nino-admin-actionbar-status" role="status"></span>
					<button type="button" id="pd-delete-template" class="nino-admin-btn-danger" disabled>Delete</button>
					<button type="button" id="pd-save" class="nino-admin-btn-primary" disabled>Save template</button>
				</div>
			</header>

			<div id="pd-shell">
				<aside id="pd-pages" aria-label="Page templates">
					<div class="pd-panel-heading">
						<div><span class="pd-eyebrow">Project</span><h2>Templates</h2></div>
						<div class="pd-heading-actions">
							<button type="button" class="pd-icon-button" id="pd-new-template" title="Create a page template" aria-label="Create a page template">＋</button>
							<button type="button" class="pd-icon-button" id="pd-reload-pages" title="Reload templates" aria-label="Reload templates">↻</button>
						</div>
					</div>
					<label class="pd-search" for="pd-page-search">
						<span aria-hidden="true">⌕</span>
						<input id="pd-page-search" type="search" placeholder="Find a page…" autocomplete="off">
					</label>
					<nav id="pd-page-list" aria-label="Available page templates"></nav>
					<div class="pd-sidebar-note">
						<strong>Focused by design</strong>
						<span>Only <code>page-*.tpl</code> files are shown. Content sections stay movable; the page header and footer live safely in Template Settings.</span>
					</div>
				</aside>

				<main id="pd-workspace">
					<div id="pd-page-toolbar" class="pd-hidden">
						<div class="pd-template-settings">
							<span class="pd-eyebrow">Template settings</span>
							<div class="pd-template-settings-row">
								<label class="pd-slot-setting pd-name-setting" for="pd-template-name"><span>Name</span><input id="pd-template-name" type="text" maxlength="160" aria-label="Template name"></label>
								<label class="pd-slot-setting" for="pd-header-template"><span>Header</span><select id="pd-header-template" aria-label="Header template"></select></label>
								<label class="pd-slot-setting" for="pd-footer-template"><span>Footer</span><select id="pd-footer-template" aria-label="Footer template"></select></label>
								<div class="pd-slot-setting pd-vpa-setting">
									<span>VPA</span>
									<div class="pd-segmented" id="pd-page-motion" aria-label="Default viewport motion">
										<button type="button" data-value="off">Off</button>
										<button type="button" data-value="on">On</button>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div id="pd-notice" aria-live="polite"></div>
					<div id="pd-empty" class="pd-empty-state">
						<div class="pd-empty-illustration" aria-hidden="true"><span></span><span></span><span></span></div>
						<h1>Build page templates from complete sections</h1>
						<p>Select or create a template on the left. Then combine curated HTML sections with reusable template sections and fill native content without leaving the flow.</p>
					</div>
					<div id="pd-canvas" class="pd-hidden" aria-label="Page sections"></div>
					<button type="button" id="pd-add-section" class="nino-admin-btn-primary pd-workspace-add pd-hidden"><span aria-hidden="true">＋</span> Add section</button>
				</main>

				<aside id="pd-inspector" aria-label="Selected section">
					<div id="pd-inspector-empty" class="pd-inspector-empty">
						<span class="pd-inspector-icon" aria-hidden="true">§</span>
						<h2>Section details</h2>
						<p>Select a section to edit its structure and native content.</p>
					</div>
					<div id="pd-inspector-content" class="pd-hidden"></div>
				</aside>
			</div>

		<dialog id="pd-composer" class="pd-dialog pd-composer-dialog">
			<form method="dialog" class="pd-dialog-shell" id="pd-composer-form">
				<header class="pd-dialog-header">
					<div class="pd-composer-heading"><div><span class="pd-eyebrow">Section composer</span><h2 id="pd-composer-title">Add section</h2></div><ol class="pd-stepper" aria-label="Composer progress"><li id="pd-step-library" class="is-active"><span>1</span>Choose</li><li id="pd-step-config"><span>2</span>Configure &amp; fill</li></ol></div>
					<button type="button" class="pd-icon-button pd-dialog-close" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
				</header>
				<div class="pd-composer-body">
					<section id="pd-composer-library-step" class="pd-composer-step pd-library-step" aria-label="Choose a section">
						<div class="pd-library-toolbar">
							<div><span class="pd-eyebrow">Section library</span><h3>What should this part of the page do?</h3><p>Choose a real visual preset. Its named content areas stay flexible in the next step.</p></div>
							<label class="pd-search" for="pd-library-search"><span aria-hidden="true">⌕</span><input id="pd-library-search" type="search" placeholder="Search hero, FAQ, image left, template…" autocomplete="off"></label>
						</div>
						<div id="pd-library-categories" class="pd-chip-row"></div>
						<div id="pd-library-list" aria-live="polite"></div>
					</section>
					<section id="pd-composer-config-step" class="pd-composer-step pd-config-step pd-hidden" aria-label="Configure and fill the section">
						<div class="pd-config-pane"><div id="pd-selected-preset"></div><div id="pd-composer-settings"></div></div>
						<aside class="pd-preview-pane" aria-label="Live section preview">
							<div class="pd-preview-sticky">
								<div class="pd-preview-heading"><span><span class="pd-eyebrow">Current project design</span><strong>Live preview</strong></span><small id="pd-preview-status" role="status">Ready</small></div>
								<div id="pd-composer-preview" class="pd-real-preview is-detail"><iframe title="Section preview" sandbox="allow-scripts" tabindex="-1"></iframe></div>
								<div id="pd-composer-summary"></div>
							</div>
						</aside>
					</section>
				</div>
				<footer class="pd-dialog-footer">
					<span id="pd-composer-error" class="pd-dialog-message" role="alert"></span>
					<button type="button" class="pd-dialog-close">Cancel</button>
					<button type="button" id="pd-compose-back" class="pd-hidden">Back to library</button>
					<button type="button" id="pd-compose-next" class="nino-admin-btn-primary">Configure section</button>
					<button type="submit" id="pd-compose-submit" class="nino-admin-btn-primary pd-hidden">Insert section</button>
				</footer>
			</form>
		</dialog>

		<dialog id="pd-create-dialog" class="pd-dialog pd-small-dialog">
			<form method="dialog" class="pd-dialog-shell" id="pd-create-form">
				<header class="pd-dialog-header">
					<div><span class="pd-eyebrow">Template Builder</span><h2>New page template</h2></div>
					<button type="button" class="pd-icon-button pd-create-close" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
				</header>
				<div class="pd-simple-dialog-body pd-create-body">
					<div class="pd-create-grid">
						<label class="pd-form-field is-wide" for="pd-create-filename">
							<span>Filename</span>
							<input id="pd-create-filename" name="filename" type="text" required pattern="page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl" autocomplete="off" placeholder="page-services.tpl">
							<small>The real file in <code>templates/</code>, including <code>page-</code> and <code>.tpl</code>.</small>
						</label>
						<label class="pd-form-field is-wide" for="pd-create-name">
							<span>Name</span>
							<input id="pd-create-name" name="displayName" type="text" required maxlength="160" autocomplete="off" placeholder="Services">
							<small>Shown in the Template Builder and stored as <code>&lt;!-- nino:template-name … --&gt;</code>.</small>
						</label>
						<label class="pd-form-field" for="pd-create-header"><span>Header</span><select id="pd-create-header" name="header"></select></label>
						<label class="pd-form-field" for="pd-create-footer"><span>Footer</span><select id="pd-create-footer" name="footer"></select></label>
						<label class="pd-form-field is-wide" for="pd-create-vpa">
							<span>Default VPA</span>
							<select id="pd-create-vpa" name="pageMotion"><option value="off">Off</option><option value="on">On</option></select>
							<small>Automatically adds <code>js-vpa</code> to compatible sections created for this template.</small>
						</label>
					</div>
				</div>
				<footer class="pd-dialog-footer">
					<span id="pd-create-error" class="pd-dialog-message" role="alert"></span>
					<button type="button" class="pd-create-close">Cancel</button>
					<button type="submit" class="nino-admin-btn-primary">Create template</button>
				</footer>
			</form>
		</dialog>

		<dialog id="pd-include-dialog" class="pd-dialog pd-include-dialog">
			<div class="pd-dialog-shell">
				<header class="pd-dialog-header">
					<div><span class="pd-eyebrow">[template] shortcode</span><h2 id="pd-include-title">Insert template section</h2></div>
					<button type="button" class="pd-icon-button pd-include-close" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
				</header>
				<div class="pd-include-body">
					<p>Insert a reusable non-page template as a first-class canvas item. The page header and footer are managed separately in Template Settings.</p>
					<label class="pd-search" for="pd-include-search"><span aria-hidden="true">⌕</span><input id="pd-include-search" type="search" placeholder="Find a template section…" autocomplete="off"></label>
					<div id="pd-include-list"></div>
				</div>
				<footer class="pd-dialog-footer">
					<button type="button" class="pd-include-close">Cancel</button>
				</footer>
			</div>
		</dialog>

		<dialog id="pd-code-dialog" class="pd-dialog pd-code-dialog">
			<form method="dialog" class="pd-dialog-shell" id="pd-code-form">
				<header class="pd-dialog-header">
					<div><span class="pd-eyebrow">HTML+ escape hatch</span><h2 id="pd-code-title">Edit section source</h2></div>
					<button type="button" class="pd-icon-button pd-code-close" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
				</header>
				<div class="pd-code-body">
					<p id="pd-code-note">Exactly one complete <code>&lt;section&gt;</code> is accepted. Other page source remains locked.</p>
					<label for="pd-code-source">Section source</label>
					<textarea id="pd-code-source" spellcheck="false"></textarea>
				</div>
				<footer class="pd-dialog-footer">
					<span id="pd-code-error" class="pd-dialog-message" role="alert"></span>
					<button type="button" class="pd-code-close">Cancel</button>
					<button type="submit" class="nino-admin-btn-primary">Use section</button>
				</footer>
			</form>
		</dialog>

		<div id="pd-toast" role="status" aria-live="polite"></div>
		</div>
		<script src="[[/nino/dir]]/_nino/Nino.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/script.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/sections.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/composer.js"></script>
		<script src="[[/nino/dir]]/_templates/assets/area-composer.js"></script>
	</body>
</html>
