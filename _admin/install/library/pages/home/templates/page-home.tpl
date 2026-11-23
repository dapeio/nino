<!-- nino:template-name Home -->
[template /templates/html-header]
<section class="hero hero--tall">
	[imagevalues /template/page-home/hero/background]<img src="[[src]]" width="[[width]]" height="[[height]]" alt="" decoding="async">[/imagevalues]
	<div class="wrap">
		<h1>[[/template/page-home/hero/title]]</h1>
		<p class="lead">[[/template/page-home/hero/subtitle]]</p>
		[link /_nino/webpage/contact/uri class="button"][[/_nino/webpage/contact/name]][/link]
	</div>
</section>
<section class="section section--alt" id="start">
	<div class="wrap">
		<div class="intro">
			<h2>[[/template/page-home/start/title]]</h2>
			<p>[[/template/page-home/start/text]]</p>
		</div>
		<div class="cards">
			<article class="card" id="start-1">
				<h3>[[/template/page-home/start-1/title]]</h3>
				<p>[[/template/page-home/start-1/text]]</p>
			</article>
			<article class="card" id="start-2">
				<h3>[[/template/page-home/start-2/title]]</h3>
				<p>[[/template/page-home/start-2/text]]</p>
			</article>
			<article class="card" id="start-3">
				<h3>[link /_nino/webpage/contact/uri][[/template/page-home/start-3/title]][/link]</h3>
				<p>[[/template/page-home/start-3/text]]</p>
			</article>
		</div>
	</div>
</section>
[template /templates/html-footer]
