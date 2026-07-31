[template /templates/html-header]

<section class="ui-atf ui-atf--fullscreen ui-section--fullwidth ui-section--dark ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100 js-vpa js-vpa--speed-slow">
			<h2 class="ui-atf-title">[[/page-faq/hero/title]]</h2>
			<p class="ui-atf-subtitle">[[/page-faq/hero/subtitle]]</p>
		</div>
	</div>
	<button type="button" class="ui-atf-arrowdown" data-arrow-target="#page-faq-list" aria-label="[[/global/scrolldown]]"></button>
</section>

<!-- Full FAQ accordion -->
<section class="ui-section" id="page-faq-list">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-66" style="margin:0 auto;">
			[elements /faq]
			<details class="ui-accordion" name="page-faq">
				<summary class="ui-accordion-trigger">[[question]]</summary>
				<div class="ui-accordion-panel"><p>[[answer]]</p></div>
			</details>
			[/elements]
		</div>
	</div>
</section>

<!-- Contact-Callout -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-66 ui-text-center" style="margin:0 auto;">
			<h3 class="ui-section-title">[[/page-faq/intro/title]]</h3>
			<p class="ui-section-subtitle">[[/page-faq/intro/text]]</p>
			<a href="mailto:[[/company/email]]" class="ui-btn ui-btn--outline">[[/company/email]]</a>
		</div>
	</div>
</section>

<!-- CTA banner -->
<section class="ui-section ui-section--black ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">[[/page-faq/cta/title]]</h3>
			<p class="ui-section-subtitle">[[/page-faq/cta/subtitle]]</p>
			<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary ui-btn--big">[[/global/cta/getintouch]]</a>
		</div>
	</div>
</section>

[template /templates/html-footer]
