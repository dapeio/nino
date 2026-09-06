<!-- The Design panel: four appearance editors under one tab strip - Theme
     establishes the complete baseline, Header, Footer and Design remain
     independently editable (see assets/design.js). Rendered whole into the
     workbench's pane (see \Nino\Admin\Panels::panesHtml()): the shell, the rail
     and the account chrome are the workbench's, this file owns only what is
     inside the panel. Every id and class here is the panel's own, styled from
     assets/style.css; the components are the design system's. The words are
     fills from text/<locale>.php beside this module, resolved by the same
     pass that fills the shell (see the panel's text()). -->
<div id="theme-page-wrap" class="show-theme">
	<div class="nino-admin-tabs nino-admin-tabs--bar theme-tabs" role="tablist" aria-label="[[/_admin/design/label/appearance]]">
		<button type="button" role="tab" id="theme-nav-theme" class="nino-admin-tab is-active" aria-selected="true">[[/_admin/design/label/theme]]</button>
		<button type="button" role="tab" id="theme-nav-header" class="nino-admin-tab" aria-selected="false">[[/_admin/design/label/header]]</button>
		<button type="button" role="tab" id="theme-nav-footer" class="nino-admin-tab" aria-selected="false">[[/_admin/design/label/footer]]</button>
		<button type="button" role="tab" id="theme-nav-design" class="nino-admin-tab" aria-selected="false">[[/_admin/nav/design]]</button>
	</div>

	<div id="theme-pane">
		<section id="theme-content-theme">
			<div id="theme-grid" aria-live="polite"></div>
			<p id="theme-empty" class="nino-admin-empty theme-hidden"><strong>[[/_admin/design/empty/theme]]</strong><br>[[/_admin/design/empty/library]]</p>
		</section>

		<section id="theme-content-design">

			<!--	Two columns, and the split is the point: every knob is
						visible at once on the left, and one page on the right shows
						what all of them together produce. Colour chips and size
						specimens showed each setting in isolation, which is exactly
						where a design decision cannot be judged.	-->
			<div class="theme-design-split">

				<div class="theme-design-controls" id="theme-design-controls">
					<div class="nino-admin-tabs" role="tablist" aria-label="[[/_admin/design/label/settings]]">
						<button type="button" role="tab" id="theme-design-tab-colour" class="nino-admin-tab is-active" aria-selected="true" aria-controls="theme-design-panel-colour">[[/_admin/design/label/colour]]</button>
						<button type="button" role="tab" id="theme-design-tab-raster" class="nino-admin-tab" aria-selected="false" aria-controls="theme-design-panel-raster">[[/_admin/design/label/raster]]</button>
					</div>

					<div class="nino-admin-tabpanel theme-design-panel" role="tabpanel" id="theme-design-panel-colour" aria-labelledby="theme-design-tab-colour">
						<div id="theme-design-colours">
							<label class="theme-field" id="theme-design-primary-field">
								<span>[[/_admin/design/label/primary]]</span>
								<span class="theme-color-control">
									<input type="color" id="theme-design-primary" class="theme-color-swatch">
									<input type="text" id="theme-design-primary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7">
								</span>
							</label>
							<label class="theme-field" id="theme-design-secondary-field">
								<span>[[/_admin/design/label/secondary]]</span>
								<span class="theme-color-control">
									<input type="color" id="theme-design-secondary" class="theme-color-swatch">
									<input type="text" id="theme-design-secondary-hex" class="nino-admin-input" inputmode="text" spellcheck="false" autocomplete="off" maxlength="7" placeholder="[[/_admin/design/label/auto]]">
								</span>
								<small>[[/_admin/design/hint/secondary]]</small>
							</label>
						</div>
						<div id="theme-design-knobs-colour"></div>
					</div>

					<div class="nino-admin-tabpanel theme-design-panel" role="tabpanel" id="theme-design-panel-raster" aria-labelledby="theme-design-tab-raster" hidden>
						<div id="theme-design-knobs-raster"></div>
					</div>
				</div>

				<div class="theme-design-stage">
					<div class="theme-design-modes" role="group" aria-label="[[/_admin/design/label/mode]]">
						<button type="button" id="theme-design-mode-light" class="theme-design-mode is-active" aria-pressed="true">[[/_admin/design/label/light]]</button>
						<button type="button" id="theme-design-mode-dark" class="theme-design-mode" aria-pressed="false">[[/_admin/design/label/dark]]</button>
						<!--	The way back out, at the top of the settings it undoes
									rather than at the foot of the pane - the same place
									the wizard keeps it. Present only while there is
									something to discard, see design.js's _updateAction	-->
						<button type="button" id="theme-design-reset" class="nino-admin-btn-secondary" hidden>[[/_admin/design/label/reset]]</button>
					</div>
					<!--	Sandboxed, and delivered as a document rather than
								spliced in: the example styles bare element selectors
								and sets :root variables that would otherwise land on
								this tool's own shell	-->
					<div class="theme-design-port" id="theme-design-example-port">
						<iframe id="theme-design-example" class="theme-design-view" title="[[/_admin/design/label/example]]" sandbox="" loading="lazy"></iframe>
					</div>
				</div>
			</div>
		</section>

		<section id="theme-content-header">
			<p id="theme-frame-header-empty" class="nino-admin-empty theme-hidden"><strong>[[/_admin/design/empty/header]]</strong><br>[[/_admin/design/empty/library]]</p>
			<div id="theme-frame-header-panel" class="theme-frame-panel theme-hidden">
				<label class="theme-field theme-frame-select"><span>[[/_admin/design/label/header-variant]]</span><select id="theme-frame-header" class="nino-admin-input"></select></label>
				<div class="theme-frame-stage"><iframe id="theme-frame-header-preview" class="theme-frame-view" title="[[/_admin/design/label/header-preview]]" sandbox="" loading="lazy"></iframe></div>
			</div>
		</section>

		<section id="theme-content-footer">
			<p id="theme-frame-footer-empty" class="nino-admin-empty theme-hidden"><strong>[[/_admin/design/empty/footer]]</strong><br>[[/_admin/design/empty/library]]</p>
			<div id="theme-frame-footer-panel" class="theme-frame-panel theme-hidden">
				<label class="theme-field theme-frame-select"><span>[[/_admin/design/label/footer-variant]]</span><select id="theme-frame-footer" class="nino-admin-input"></select></label>
				<div class="theme-frame-stage"><iframe id="theme-frame-footer-preview" class="theme-frame-view" title="[[/_admin/design/label/footer-preview]]" sandbox="" loading="lazy"></iframe></div>
			</div>
		</section>
	</div>

	<div class="nino-admin-actionbar">
		<p id="theme-action-status" class="nino-admin-actionbar-status" role="status" aria-live="polite"></p>
		<button type="button" class="nino-admin-btn-primary" id="theme-action-save">[[/_admin/design/label/apply-theme]]</button>
	</div>

	<!-- Theme preview lightbox - a single, reused overlay filled by
	     design.js rather than one per tile, see its _openLightbox() -->
	<div id="theme-lightbox" class="theme-hidden">
		<img id="theme-lightbox-image" src="" alt="">
		<p id="theme-lightbox-caption"></p>
	</div>
</div>
