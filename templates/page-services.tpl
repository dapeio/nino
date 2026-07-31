[template /templates/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim ui-text-center" data-cover-height="60">
	<img src="[[/nino/dir]]/images/.demo/demo-03.jpg">
	<div class="js-cover-content">
		<div class="ui-grid-row">
			<div class="ui-grid-100">
				<h2 class="ui-atf-title">[[/page-services/hero/title]]</h2>
				<p class="ui-atf-subtitle">[[/page-services/hero/subtitle]]</p>
			</div>
		</div>
	</div>
</section>

<!-- Intro -->
<section class="ui-section ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-66" style="margin:0 auto;">
			<h3 class="ui-section-title">[[/page-services/intro/title]]</h3>
			<p class="ui-section-subtitle">[[/page-services/intro/text]]</p>
		</div>
	</div>
</section>

<!-- Services grid -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		[elements /services]
		<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
			<article class="ui-article ui-article--alt ui-article--wide">
				<img class="ui-article-img ui-article-img--dim" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
				<div class="ui-article-content">
					<h4 class="ui-article-title">[[title]]</h4>
					<p class="ui-article-descr">[[description]]</p>
					<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--light">[[/global/cta/getquote]]</a>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>

<!-- Process timeline -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-services/process/title]]</h3>
		</div>
		<div class="ui-grid-100">
			<ol class="ui-timeline">
				<li class="ui-timeline-step">
					<div class="ui-timeline-number">1</div>
					<h4>[[/page-home/process/step1/title]]</h4>
					<p>[[/page-home/process/step1/text]]</p>
				</li>
				<li class="ui-timeline-step">
					<div class="ui-timeline-number">2</div>
					<h4>[[/page-home/process/step2/title]]</h4>
					<p>[[/page-home/process/step2/text]]</p>
				</li>
				<li class="ui-timeline-step">
					<div class="ui-timeline-number">3</div>
					<h4>[[/page-home/process/step3/title]]</h4>
					<p>[[/page-home/process/step3/text]]</p>
				</li>
				<li class="ui-timeline-step">
					<div class="ui-timeline-number">4</div>
					<h4>[[/page-home/process/step4/title]]</h4>
					<p>[[/page-home/process/step4/text]]</p>
				</li>
			</ol>
		</div>
	</div>
</section>

<!-- Pricing -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-services/pricing/title]]</h3>
			<p class="ui-section-subtitle">[[/page-services/pricing/subtitle]]</p>
		</div>
		<div class="ui-grid-100">
			<div class="ui-pricing-row">
				[elements /services query="cat=standard"]
				<div class="ui-pricing-item">
					<h4 class="ui-pricing-title">[[title]]</h4>
					<p>[[/page-services/pricing/starting]]</p>
					<p class="ui-pricing-price">[[price]] &euro;</p>
					<span class="ui-badge">[[cat]]</span>
				</div>
				[/elements]
				[elements /services query="cat=premium"]
				<div class="ui-pricing-item ui-pricing-item--featured">
					<h4 class="ui-pricing-title">[[title]]</h4>
					<p>[[/page-services/pricing/starting]]</p>
					<p class="ui-pricing-price">[[price]] &euro;</p>
					<span class="ui-badge ui-badge--primary">[[cat]]</span>
				</div>
				[/elements]
			</div>
		</div>
	</div>
</section>

<!-- FAQ -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-services/faq/title]]</h3>
		</div>
		<div class="ui-grid-100 ui-grid-m-66" style="margin:0 auto;">
			[elements /faq limit="3"]
			<details class="ui-accordion" name="services-faq">
				<summary class="ui-accordion-trigger">[[question]]</summary>
				<div class="ui-accordion-panel"><p>[[answer]]</p></div>
			</details>
			[/elements]
		</div>
	</div>
</section>

<!-- CTA banner -->
<section class="ui-section ui-section--black ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">[[/page-services/cta/title]]</h3>
			<p class="ui-section-subtitle">[[/page-services/cta/subtitle]]</p>
			<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary ui-btn--big">[[/global/cta/getintouch]]</a>
		</div>
	</div>
</section>

[template /templates/html-footer]
