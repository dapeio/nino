[template /templates/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim ui-text-center" data-cover-height="60">
	<img src="[[/nino/dir]]/images/.demo/demo-06.jpg">
	<div class="js-cover-content">
		<div class="ui-grid-row">
			<div class="ui-grid-100">
				<h2 class="ui-atf-title">[[/page-about/hero/title]]</h2>
				<p class="ui-atf-subtitle">[[/page-about/hero/subtitle]]</p>
			</div>
		</div>
	</div>
</section>

<!-- Story: text left, image right -->
<section class="ui-section">
	<div class="ui-grid-row ui-grid-middle">
		<div class="ui-grid-100 ui-grid-m-50">
			<article class="ui-article">
				<div class="ui-article-content">
					<h3 class="ui-article-title">[[/page-about/story/title]]</h3>
					<p class="ui-article-descr">[[/page-about/story/text]]</p>
				</div>
			</article>
		</div>
		<div class="ui-grid-100 ui-grid-m-50 ui-img-cover">
			<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-07.jpg" style="height:400px;">
		</div>
	</div>
</section>

<!-- Mission / values -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-about/mission/title]]</h3>
			<p class="ui-section-subtitle">[[/page-about/mission/subtitle]]</p>
		</div>
		<div class="ui-grid-100">
			<ul class="ui-feature-list">
				<li class="ui-feature-item">
					<svg class="ui-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
					<div>
						<h4>[[/page-about/mission/item1/title]]</h4>
						<p>[[/page-about/mission/item1/text]]</p>
					</div>
				</li>
				<li class="ui-feature-item">
					<svg class="ui-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1.98 15L6 12l1.41-1.41 2.61 2.6 5.57-5.58L17 9l-6.98 7z"></path></svg>
					<div>
						<h4>[[/page-about/mission/item2/title]]</h4>
						<p>[[/page-about/mission/item2/text]]</p>
					</div>
				</li>
				<li class="ui-feature-item">
					<svg class="ui-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"></path></svg>
					<div>
						<h4>[[/page-about/mission/item3/title]]</h4>
						<p>[[/page-about/mission/item3/text]]</p>
					</div>
				</li>
			</ul>
		</div>
	</div>
</section>

<!-- Stats -->
<section class="ui-section ui-section--dark">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-about/stats/title]]</h3>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="240" data-stat-counter-suffix="+">0</div>
			<p>[[/page-home/stats/projects]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="12" data-stat-counter-suffix=" ">0</div>
			<p>[[/page-home/stats/years]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-33 ui-text-center">
			<div class="js-stat-counter" data-stat-counter-to="18" data-stat-counter-suffix="+">0</div>
			<p>[[/page-home/stats/awards]]</p>
		</div>
	</div>
</section>

<!-- Team -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-about/team/title]]</h3>
			<p class="ui-section-subtitle">[[/page-about/team/subtitle]]</p>
		</div>
		[elements /team]
		<div class="ui-grid-100 ui-grid-m-25 ui-mb-3">
			<article class="ui-article ui-text-center">
				<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg" style="border-radius:50%; width:8rem; height:8rem; margin:0 auto;">
				<div class="ui-article-content">
					<h4 class="ui-article-title">[[name]]</h4>
					<p class="ui-article-subtitle">[[role]]</p>
					<p class="ui-article-descr">[[bio]]</p>
				</div>
			</article>
		</div>
		[/elements]
	</div>
</section>

<!-- CTA banner -->
<section class="ui-section ui-section--black ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">[[/page-about/cta/title]]</h3>
			<p class="ui-section-subtitle">[[/page-about/cta/subtitle]]</p>
			<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary ui-btn--big">[[/global/cta/getintouch]]</a>
		</div>
	</div>
</section>

[template /templates/html-footer]
