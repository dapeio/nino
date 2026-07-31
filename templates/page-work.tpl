[template /templates/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim ui-text-center" data-cover-height="60">
	<img src="[[/nino/dir]]/images/.demo/demo-02.jpg">
	<div class="js-cover-content">
		<div class="ui-grid-row">
			<div class="ui-grid-100">
				<h2 class="ui-atf-title">[[/page-work/hero/title]]</h2>
				<p class="ui-atf-subtitle">[[/page-work/hero/subtitle]]</p>
			</div>
		</div>
	</div>
</section>

<!-- Portfolio, filterable via js-tabs -->
<section class="ui-section">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-work/intro/title]]</h3>
		</div>
		<div class="ui-grid-100">
			<div class="js-tabs">
				<div class="js-tabs-nav">
					<button type="button" class="js-tabs-tab active" data-tabs-target="work-tab-all">[[/page-work/tabs/all]]</button>
					<button type="button" class="js-tabs-tab" data-tabs-target="work-tab-branding">[[/page-work/tabs/branding]]</button>
					<button type="button" class="js-tabs-tab" data-tabs-target="work-tab-video">[[/page-work/tabs/video]]</button>
					<button type="button" class="js-tabs-tab" data-tabs-target="work-tab-social">[[/page-work/tabs/social]]</button>
				</div>

				<div class="js-tabs-panel active" id="work-tab-all">
					<div class="ui-grid-row">
						[elements /portfolio]
						<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
							<article class="ui-article ui-article--alt ui-article--wide">
								<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
								<div class="ui-article-content">
									<span class="ui-badge ui-badge--primary">[[cat]]</span>
									<h4 class="ui-article-title">[[title]]</h4>
									<p class="ui-article-descr">[[description]]</p>
									<p class="ui-font-small">[[client]] &middot; [[year]]</p>
								</div>
							</article>
						</div>
						[/elements]
					</div>
				</div>

				<div class="js-tabs-panel" id="work-tab-branding">
					<div class="ui-grid-row">
						[elements /portfolio query="cat=branding"]
						<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
							<article class="ui-article ui-article--alt ui-article--wide">
								<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
								<div class="ui-article-content">
									<h4 class="ui-article-title">[[title]]</h4>
									<p class="ui-article-descr">[[description]]</p>
									<p class="ui-font-small">[[client]] &middot; [[year]]</p>
								</div>
							</article>
						</div>
						[/elements]
					</div>
				</div>

				<div class="js-tabs-panel" id="work-tab-video">
					<div class="ui-grid-row">
						[elements /portfolio query="cat=video"]
						<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
							<article class="ui-article ui-article--alt ui-article--wide">
								<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
								<div class="ui-article-content">
									<h4 class="ui-article-title">[[title]]</h4>
									<p class="ui-article-descr">[[description]]</p>
									<p class="ui-font-small">[[client]] &middot; [[year]]</p>
								</div>
							</article>
						</div>
						[/elements]
					</div>
				</div>

				<div class="js-tabs-panel" id="work-tab-social">
					<div class="ui-grid-row">
						[elements /portfolio query="cat=social"]
						<div class="ui-grid-100 ui-grid-m-33 ui-mb-3">
							<article class="ui-article ui-article--alt ui-article--wide">
								<img class="ui-article-img" src="[[/nino/dir]]/images/.demo/demo-0[[.id]].jpg">
								<div class="ui-article-content">
									<h4 class="ui-article-title">[[title]]</h4>
									<p class="ui-article-descr">[[description]]</p>
									<p class="ui-font-small">[[client]] &middot; [[year]]</p>
								</div>
							</article>
						</div>
						[/elements]
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Behind the scenes gallery -->
<section class="ui-section ui-section--alt">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-text-center ui-mb-3">
			<h3 class="ui-section-title">[[/page-work/gallery/title]]</h3>
			<p class="ui-section-subtitle">[[/page-work/gallery/subtitle]]</p>
		</div>
	</div>
	<div class="ui-gallery">
		<div class="ui-gallery-item ui-gallery-item--wide ui-gallery-item--tall">
			<button type="button" class="js-modal-trigger" data-modal-target="work-gallery-1" aria-label="Enlarge image">
				<img src="[[/nino/dir]]/images/.demo/demo-01.jpg" loading="lazy">
			</button>
		</div>
		<div class="ui-gallery-item">
			<button type="button" class="js-modal-trigger" data-modal-target="work-gallery-2" aria-label="Enlarge image">
				<img src="[[/nino/dir]]/images/.demo/demo-05.jpg" loading="lazy">
			</button>
		</div>
		<div class="ui-gallery-item">
			<button type="button" class="js-modal-trigger" data-modal-target="work-gallery-3" aria-label="Enlarge image">
				<img src="[[/nino/dir]]/images/.demo/demo-06.jpg" loading="lazy">
			</button>
		</div>
		<div class="ui-gallery-item ui-gallery-item--wide">
			<button type="button" class="js-modal-trigger" data-modal-target="work-gallery-4" aria-label="Enlarge image">
				<img src="[[/nino/dir]]/images/.demo/demo-02.jpg" loading="lazy">
			</button>
		</div>
		<div class="ui-gallery-item">
			<button type="button" class="js-modal-trigger" data-modal-target="work-gallery-5" aria-label="Enlarge image">
				<img src="[[/nino/dir]]/images/.demo/demo-07.jpg" loading="lazy">
			</button>
		</div>
	</div>
	<dialog class="js-modal js-modal--lightbox" id="work-gallery-1">
		<button type="button" class="js-modal-close" aria-label="Close">&times;</button>
		<img src="[[/nino/dir]]/images/.demo/demo-01.jpg">
	</dialog>
	<dialog class="js-modal js-modal--lightbox" id="work-gallery-2">
		<button type="button" class="js-modal-close" aria-label="Close">&times;</button>
		<img src="[[/nino/dir]]/images/.demo/demo-05.jpg">
	</dialog>
	<dialog class="js-modal js-modal--lightbox" id="work-gallery-3">
		<button type="button" class="js-modal-close" aria-label="Close">&times;</button>
		<img src="[[/nino/dir]]/images/.demo/demo-06.jpg">
	</dialog>
	<dialog class="js-modal js-modal--lightbox" id="work-gallery-4">
		<button type="button" class="js-modal-close" aria-label="Close">&times;</button>
		<img src="[[/nino/dir]]/images/.demo/demo-02.jpg">
	</dialog>
	<dialog class="js-modal js-modal--lightbox" id="work-gallery-5">
		<button type="button" class="js-modal-close" aria-label="Close">&times;</button>
		<img src="[[/nino/dir]]/images/.demo/demo-07.jpg">
	</dialog>
</section>

<!-- CTA banner -->
<section class="ui-section ui-section--black ui-text-center">
	<div class="ui-grid-row">
		<div class="ui-grid-100">
			<h3 class="ui-section-title">[[/page-work/cta/title]]</h3>
			<p class="ui-section-subtitle">[[/page-work/cta/subtitle]]</p>
			<a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary ui-btn--big">[[/global/cta/getintouch]]</a>
		</div>
	</div>
</section>

[template /templates/html-footer]
