<!-- The Templates panel - the Template Builder: the page templates on the
     left, the section canvas in the middle, the inspector on the right, and
     the composer dialogs below (inside #pd-app, so they share its scope).
     Rendered whole into the workbench's pane (see \Nino\Admin\Panels::panesHtml())
     as a workspace: the shell, the rail and the account chrome are the
     workbench's, this file owns only what is inside the panel. Every id and
     class here is the panel's own (pd-*), styled from assets/style.css; the
     buttons and fields are the design system's. -->
<div id="pd-app" data-dir="[[/nino/dir]]" data-public="[[/nino/public]]">
	<header id="pd-topbar">
		<div id="pd-document-meta" aria-live="polite">
			<strong id="pd-document-title">[[/_admin/templates/empty/title]]</strong>
			<span id="pd-document-detail">[[/_admin/templates/empty/detail]]</span>
		</div>
		<div id="pd-top-actions">
			<span id="pd-save-state" class="nino-admin-actionbar-status" role="status"></span>
			<button type="button" id="pd-delete-template" class="nino-admin-btn-danger" disabled>[[/_admin/templates/label/delete]]</button>
			<button type="button" id="pd-save" class="nino-admin-btn-primary" disabled>[[/_admin/templates/label/save-template]]</button>
		</div>
	</header>

	<div id="pd-shell">
		<aside id="pd-pages" aria-label="[[/_admin/templates/label/page-templates]]">
			<div class="pd-panel-heading">
				<div><span class="pd-eyebrow">[[/_admin/templates/label/project]]</span><h2>[[/_admin/templates/label/templates]]</h2></div>
				<div class="pd-heading-actions">
					<button type="button" class="pd-icon-button" id="pd-new-template" title="[[/_admin/templates/label/new-template]]" aria-label="[[/_admin/templates/label/new-template]]">＋</button>
					<button type="button" class="pd-icon-button" id="pd-reload-pages" title="[[/_admin/templates/label/reload]]" aria-label="[[/_admin/templates/label/reload]]">↻</button>
				</div>
			</div>
			<label class="pd-search" for="pd-page-search">
				<span aria-hidden="true">⌕</span>
				<input id="pd-page-search" type="search" placeholder="[[/_admin/templates/label/find-page]]" autocomplete="off">
			</label>
			<nav id="pd-page-list" aria-label="[[/_admin/templates/label/available-templates]]"></nav>
			<div class="pd-sidebar-note">
				<strong>[[/_admin/templates/label/focused]]</strong>
				<span>[[/_admin/templates/hint/focused]]</span>
			</div>
		</aside>

		<main id="pd-workspace">
			<div id="pd-page-toolbar" class="pd-hidden">
				<div class="pd-template-settings">
					<span class="pd-eyebrow">[[/_admin/templates/label/template-settings]]</span>
					<div class="pd-template-settings-row">
						<label class="pd-slot-setting pd-name-setting" for="pd-template-name"><span>[[/_admin/templates/label/name]]</span><input id="pd-template-name" type="text" maxlength="160" aria-label="[[/_admin/templates/label/template-name]]"></label>
						<label class="pd-slot-setting" for="pd-header-template"><span>[[/_admin/templates/label/header]]</span><select id="pd-header-template" aria-label="[[/_admin/templates/label/header-template]]"></select></label>
						<label class="pd-slot-setting" for="pd-footer-template"><span>[[/_admin/templates/label/footer]]</span><select id="pd-footer-template" aria-label="[[/_admin/templates/label/footer-template]]"></select></label>
						<div class="pd-slot-setting pd-vpa-setting">
							<span>VPA</span>
							<div class="pd-segmented" id="pd-page-motion" aria-label="[[/_admin/templates/label/default-motion]]">
								<button type="button" data-value="off">[[/_admin/templates/label/off]]</button>
								<button type="button" data-value="on">[[/_admin/templates/label/on]]</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="pd-notice" aria-live="polite"></div>
			<div id="pd-empty" class="pd-empty-state">
				<div class="pd-empty-illustration" aria-hidden="true"><span></span><span></span><span></span></div>
				<h1>[[/_admin/templates/empty/headline]]</h1>
				<p>[[/_admin/templates/empty/lead]]</p>
			</div>
			<div id="pd-canvas" class="pd-hidden" aria-label="[[/_admin/templates/label/page-sections]]"></div>
			<button type="button" id="pd-add-section" class="nino-admin-btn-primary pd-workspace-add pd-hidden"><span aria-hidden="true">＋</span> [[/_admin/templates/label/composer-add]]</button>
		</main>

		<aside id="pd-inspector" aria-label="[[/_admin/templates/label/selected-section]]">
			<div id="pd-inspector-empty" class="pd-inspector-empty">
				<span class="pd-inspector-icon" aria-hidden="true">§</span>
				<h2>[[/_admin/templates/label/section-details]]</h2>
				<p>[[/_admin/templates/hint/section-details]]</p>
			</div>
			<div id="pd-inspector-content" class="pd-hidden"></div>
		</aside>
	</div>

<dialog id="pd-composer" class="pd-dialog pd-composer-dialog">
	<form method="dialog" class="pd-dialog-shell" id="pd-composer-form">
		<header class="pd-dialog-header">
			<div class="pd-composer-heading"><div><span class="pd-eyebrow">[[/_admin/templates/label/section-composer]]</span><h2 id="pd-composer-title">[[/_admin/templates/label/composer-add]]</h2></div><ol class="pd-stepper" aria-label="[[/_admin/templates/label/composer-progress]]"><li id="pd-step-library" class="is-active"><span>1</span>[[/_admin/templates/step/choose]]</li><li id="pd-step-config"><span>2</span>[[/_admin/templates/step/configure]]</li></ol></div>
			<button type="button" class="pd-icon-button pd-dialog-close" aria-label="[[/_admin/templates/label/close]]"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
		</header>
		<div class="pd-composer-body">
			<section id="pd-composer-library-step" class="pd-composer-step pd-library-step" aria-label="[[/_admin/templates/label/choose-section]]">
				<div class="pd-library-toolbar">
					<div><span class="pd-eyebrow">[[/_admin/templates/label/section-library]]</span><h3>[[/_admin/templates/label/library-question]]</h3><p>[[/_admin/templates/hint/library]]</p></div>
					<label class="pd-search" for="pd-library-search"><span aria-hidden="true">⌕</span><input id="pd-library-search" type="search" placeholder="[[/_admin/templates/label/library-search]]" autocomplete="off"></label>
				</div>
				<div id="pd-library-categories" class="pd-chip-row"></div>
				<div id="pd-library-list" aria-live="polite"></div>
			</section>
			<section id="pd-composer-config-step" class="pd-composer-step pd-config-step pd-hidden" aria-label="[[/_admin/templates/label/configure-section]]">
				<div class="pd-config-pane"><div id="pd-selected-preset"></div><div id="pd-composer-settings"></div></div>
				<aside class="pd-preview-pane" aria-label="[[/_admin/templates/label/live-section-preview]]">
					<div class="pd-preview-sticky">
						<div class="pd-preview-heading"><span><span class="pd-eyebrow">[[/_admin/templates/label/current-design]]</span><strong>[[/_admin/templates/label/live-preview-short]]</strong></span><small id="pd-preview-status" role="status">[[/_admin/templates/msg/ready]]</small></div>
						<div id="pd-composer-preview" class="pd-real-preview is-detail"><iframe title="[[/_admin/templates/label/preview]]" sandbox="allow-scripts" tabindex="-1"></iframe></div>
						<div id="pd-composer-summary"></div>
					</div>
				</aside>
			</section>
		</div>
		<footer class="pd-dialog-footer">
			<span id="pd-composer-error" class="pd-dialog-message" role="alert"></span>
			<button type="button" class="pd-dialog-close">[[/_admin/templates/label/cancel]]</button>
			<button type="button" id="pd-compose-back" class="pd-hidden">[[/_admin/templates/label/back-to-library]]</button>
			<button type="button" id="pd-compose-next" class="nino-admin-btn-primary">[[/_admin/templates/label/next-config]]</button>
			<button type="submit" id="pd-compose-submit" class="nino-admin-btn-primary pd-hidden">[[/_admin/templates/label/composer-insert]]</button>
		</footer>
	</form>
</dialog>

<dialog id="pd-create-dialog" class="pd-dialog pd-small-dialog">
	<form method="dialog" class="pd-dialog-shell" id="pd-create-form">
		<header class="pd-dialog-header">
			<div><span class="pd-eyebrow">[[/_admin/templates/label/builder]]</span><h2>[[/_admin/templates/label/new-page-template]]</h2></div>
			<button type="button" class="pd-icon-button pd-create-close" aria-label="[[/_admin/templates/label/close]]"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
		</header>
		<div class="pd-simple-dialog-body pd-create-body">
			<div class="pd-create-grid">
				<label class="pd-form-field is-wide" for="pd-create-filename">
					<span>[[/_admin/templates/label/filename]]</span>
					<input id="pd-create-filename" name="filename" type="text" required pattern="page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl" autocomplete="off" placeholder="page-services.tpl">
					<small>[[/_admin/templates/hint/filename]]</small>
				</label>
				<label class="pd-form-field is-wide" for="pd-create-name">
					<span>[[/_admin/templates/label/name]]</span>
					<input id="pd-create-name" name="displayName" type="text" required maxlength="160" autocomplete="off" placeholder="[[/_admin/templates/label/name-example]]">
					<small>[[/_admin/templates/hint/displayname]]</small>
				</label>
				<label class="pd-form-field" for="pd-create-header"><span>[[/_admin/templates/label/header]]</span><select id="pd-create-header" name="header"></select></label>
				<label class="pd-form-field" for="pd-create-footer"><span>[[/_admin/templates/label/footer]]</span><select id="pd-create-footer" name="footer"></select></label>
				<label class="pd-form-field is-wide" for="pd-create-vpa">
					<span>[[/_admin/templates/label/default-vpa]]</span>
					<select id="pd-create-vpa" name="pageMotion"><option value="off">[[/_admin/templates/label/off]]</option><option value="on">[[/_admin/templates/label/on]]</option></select>
					<small>[[/_admin/templates/hint/default-vpa]]</small>
				</label>
			</div>
		</div>
		<footer class="pd-dialog-footer">
			<span id="pd-create-error" class="pd-dialog-message" role="alert"></span>
			<button type="button" class="pd-create-close">[[/_admin/templates/label/cancel]]</button>
			<button type="submit" class="nino-admin-btn-primary">[[/_admin/templates/label/create-template]]</button>
		</footer>
	</form>
</dialog>

<dialog id="pd-include-dialog" class="pd-dialog pd-include-dialog">
	<div class="pd-dialog-shell">
		<header class="pd-dialog-header">
			<div><span class="pd-eyebrow">[[/_admin/templates/label/shortcode]]</span><h2 id="pd-include-title">[[/_admin/templates/label/include-insert]]</h2></div>
			<button type="button" class="pd-icon-button pd-include-close" aria-label="[[/_admin/templates/label/close]]"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
		</header>
		<div class="pd-include-body">
			<p>[[/_admin/templates/hint/include-dialog]]</p>
			<label class="pd-search" for="pd-include-search"><span aria-hidden="true">⌕</span><input id="pd-include-search" type="search" placeholder="[[/_admin/templates/label/find-include]]" autocomplete="off"></label>
			<div id="pd-include-list"></div>
		</div>
		<footer class="pd-dialog-footer">
			<button type="button" class="pd-include-close">[[/_admin/templates/label/cancel]]</button>
		</footer>
	</div>
</dialog>

<dialog id="pd-code-dialog" class="pd-dialog pd-code-dialog">
	<form method="dialog" class="pd-dialog-shell" id="pd-code-form">
		<header class="pd-dialog-header">
			<div><span class="pd-eyebrow">[[/_admin/templates/label/escape-hatch]]</span><h2 id="pd-code-title">[[/_admin/templates/label/edit-source]]</h2></div>
			<button type="button" class="pd-icon-button pd-code-close" aria-label="[[/_admin/templates/label/close]]"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
		</header>
		<div class="pd-code-body">
			<p id="pd-code-note">[[/_admin/templates/hint/one-section-html]]</p>
			<label for="pd-code-source">[[/_admin/templates/label/section-source]]</label>
			<textarea id="pd-code-source" spellcheck="false"></textarea>
		</div>
		<footer class="pd-dialog-footer">
			<span id="pd-code-error" class="pd-dialog-message" role="alert"></span>
			<button type="button" class="pd-code-close">[[/_admin/templates/label/cancel]]</button>
			<button type="submit" class="nino-admin-btn-primary">[[/_admin/templates/label/use-section]]</button>
		</footer>
	</form>
</dialog>

<div id="pd-toast" role="status" aria-live="polite"></div>
</div>
