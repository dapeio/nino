[template /templates/.demo/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim js-cover-center ui-text-center" data-cover-height="100">
	<img src="[[/nino/dir]]/images/.demo/demo-01.jpg">
	<div class="js-cover-content">
		<div class="ui-grid-row">
			<div class="ui-grid-100 js-vpa js-vpa--speed-slow">
				<h2 class="ui-atf-title">[[/page-home/hero/title]]</h2>
				<p class="ui-atf-subtitle">[[/page-home/hero/subtitle]]</p>
				<a href="[[/webpage/work/uri]]" class="ui-btn ui-btn--primary">[[/page-home/hero/cta/work]]</a>
				<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--outline ui-mt-2">[[/page-home/hero/cta/contact]]</a>
			</div>
		</div>
	</div>
	<button type="button" class="ui-atf-arrowdown" data-arrow-target="#page-home-about" aria-label="[[/global/scrolldown]]"></button>
</section>

<!-- Trust / logo strip -->
<section class="ui-section ui-section--alt ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-mb-2">
			<p class="ui-section-subtitle">[[/page-home/trust/title]]</p>
		</div>
		<div class="ui-grid-100">
			<div class="ui-logos">
				<span class="ui-logos-item">Nordwind</span>
				<span class="ui-logos-item">Bergmann &amp; Co</span>
				<span class="ui-logos-item">Studio Elf</span>
				<span class="ui-logos-item">Kleinstadt AG</span>
				<span class="ui-logos-item">Rautenberg &amp; Partner</span>
				<span class="ui-logos-item">Hafenlicht</span>
			</div>
		</div>
	</div>
</section>

<!-- About teaser: image left, text right -->
<section class="ui-section" id="page-home-about">
	<div class="ui-grid-row ui-grid-middle">
		<div class="ui-grid-100 ui-grid-m-50 ui-img-cover">
			<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-06.jpg" style="height:400px;">
		</div>
		<div class="ui-grid-100 ui-grid-m-50">
			<article class="ui-article">
				<div class="ui-article-content">
					<h3 class="ui-article-title">[[/page-home/about/title]]</h3>
					<p class="ui-article-descr">[[/page-home/about/text]]</p>
					<a href="[[/webpage/about/uri]]" class="ui-btn ui-btn--primary">[[/global/cta/learnmore]]</a>
				</div>
			</article>
		</div>
	</div>
</section>

<!-- Services teaser -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/services/title]]</h3>
			<p class="ui-section-subtitle">[[/page-home/services/subtitle]]</p>
		</div>
		[elements /services limit="3"]
		<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
			<article class="ui-article ui-article--alt">
				<img class="ui-article-img ui-article-img--dim" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
				<div class="ui-article-content">
					<h4 class="ui-article-title">[[title]]</h4>
					<p class="ui-article-descr">[[description]]</p>
				</div>
			</article>
		</div>
		[/elements]
		<div class="ui-grid-100 ui-text-center ui-mt-2">
			<a href="[[/webpage/services/uri]]" class="ui-btn ui-btn--outline">[[/global/cta/allservices]]</a>
		</div>
	</div>
</section>

<!-- Stats -->
<section class="ui-section ui-section--dark">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/stats/title]]</h3>
		</div>
		<div class="ui-grid-100 ui-grid-m-25 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="240" data-stat-counter-suffix="+">0</div>
			<p>[[/page-home/stats/projects]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-25 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="97" data-stat-counter-suffix="%">0</div>
			<p>[[/page-home/stats/satisfaction]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-25 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="12" data-stat-counter-suffix=" ">0</div>
			<p>[[/page-home/stats/years]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-25 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="18" data-stat-counter-suffix="+">0</div>
			<p>[[/page-home/stats/awards]]</p>
		</div>
	</div>
</section>

<!-- Work teaser -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/work/title]]</h3>
			<p class="ui-section-subtitle">[[/page-home/work/subtitle]]</p>
		</div>
		[elements /portfolio limit="3"]
		<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
			<article class="ui-article ui-article--alt ui-article--wide">
				<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
				<div class="ui-article-content">
					<span class="ui-badge ui-badge--primary">[[cat]]</span>
					<h4 class="ui-article-title">[[title]]</h4>
					<p class="ui-article-descr">[[description]]</p>
				</div>
			</article>
		</div>
		[/elements]
		<div class="ui-grid-100 ui-text-center ui-mt-2">
			<a href="[[/webpage/work/uri]]" class="ui-btn ui-btn--outline">[[/global/cta/allwork]]</a>
		</div>
	</div>
</section>

<!-- Process timeline -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/process/title]]</h3>
			<p class="ui-section-subtitle">[[/page-home/process/subtitle]]</p>
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

<!-- Testimonials slider -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/testimonials/title]]</h3>
		</div>
	</div>
	<div class="js-slider" data-slider-pos="0" data-slider-width="60%" data-slider-min="280px">
		<ul>
			[elements /testimonials]
			<li>
				<article class="ui-article ui-text-center">
					<div class="ui-article-content">
						<p class="ui-article-title">&bdquo;[[quote]]&ldquo;</p>
						<strong>[[name]]</strong>, [[role]]
					</div>
				</article>
			</li>
			[/elements]
		</ul>
	</div>
</section>

<!-- Video section -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/video/title]]</h3>
			<p class="ui-section-subtitle">[[/page-home/video/subtitle]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-75" style="margin:0 auto;">
			<button type="button" class="ui-video-poster js-modal-trigger" data-modal-target="home-video-modal" aria-label="[[/page-home/video/title]]">
				<img src="[[/nino/dir]]/images/.demo/demo-04.jpg">
				<svg class="ui-video-play" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"></path></svg>
			</button>
		</div>
	</div>
	<dialog class="js-modal js-modal--video" id="home-video-modal">
		<button type="button" class="js-modal-close" aria-label="Close">&times;</button>
		<div class="ui-video">
			<iframe src="about:blank" allowfullscreen></iframe>
		</div>
	</dialog>
</section>

<!-- FAQ teaser -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-home/faq/title]]</h3>
		</div>
		<div class="ui-grid-100 ui-grid-m-66" style="margin:0 auto;">
			[elements /faq limit="3"]
			<details class="ui-accordion" name="home-faq">
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

<!-- CTA banner -->
<section class="ui-section ui-section--black ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">[[/page-home/cta/title]]</h3>
			<p class="ui-section-subtitle">[[/page-home/cta/subtitle]]</p>
			<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary ui-btn--big">[[/page-home/cta/button]]</a>
		</div>
	</div>
</section>

<!-- Newsletter -->
<section class="ui-section ui-section--dark ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-m-66" style="margin:0 auto;">
			<h3 class="ui-section-title">[[/page-home/newsletter/title]]</h3>
			<p class="ui-section-subtitle">[[/page-home/newsletter/subtitle]]</p>
			<form class="ui-form js-newsletter-form" action="/.newsletter" style="display:flex; gap:var(--space-1); flex-wrap:wrap; justify-content:center; align-items:flex-start;">
				[csrf]
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px;">
				<label for="home-newsletter-email" class="ui-sr-only">[[/newsletter/label/email]]</label>
				<input type="email" id="home-newsletter-email" name="email" class="ui-form-input" placeholder="[[/newsletter/label/email]]" required>
				<button type="submit" class="ui-btn ui-btn--primary ui-form-submit">[[/newsletter/label/submit]]</button>
				<p class="ui-form-message ui-grid-100"></p>
			</form>
		</div>
	</div>
</section>

[template /templates/.demo/html-footer]
