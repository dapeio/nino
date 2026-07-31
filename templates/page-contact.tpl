[template /templates/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim ui-text-center" data-cover-height="50">
	<img src="[[/nino/dir]]/images/.demo/demo-05.jpg">
	<div class="js-cover-content">
		<div class="ui-grid-row">
			<div class="ui-grid-100">
				<h2 class="ui-atf-title">[[/page-contact/hero/title]]</h2>
				<p class="ui-atf-subtitle">[[/page-contact/hero/subtitle]]</p>
			</div>
		</div>
	</div>
</section>

[template /templates/section-contact]

<!-- Mini-FAQ before contact -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-contact/faq/title]]</h3>
		</div>
		<div class="ui-grid-100 ui-grid-m-66" style="margin:0 auto;">
			[elements /faq limit="3"]
			<details class="ui-accordion" name="contact-faq">
				<summary class="ui-accordion-trigger">[[question]]</summary>
				<div class="ui-accordion-panel"><p>[[answer]]</p></div>
			</details>
			[/elements]
			<div class="ui-text-center ui-mt-2">
				<a href="[[/webpage/faq/uri]]" class="ui-btn ui-btn--outline">[[/global/cta/allfaq]]</a>
			</div>
		</div>
	</div>
</section>

[template /templates/html-footer]
