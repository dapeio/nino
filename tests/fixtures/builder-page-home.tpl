<!-- nino:template-name Home -->
[template /templates/html-header]

<section id="hero" class="nino-section nino-section--fullwidth nino-section--black nino-cover nino-cover--dim nino-vpa" data-cover-height="100">
	<div class="nino-section-bg nino-img-focus--5">[image /template/page-home/hero/background alt=""]</div>
	<div class="nino-grid-row nino-grid-row--wide nino-grid-middle">
		<div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-66 nino-text-left">
			[title /template/page-home/hero/title level="1" style="loud"]
			[subtitle /template/page-home/hero/subtitle]
			[button /_nino/webpage/contact/name href="/_nino/webpage/contact/uri" style="primary"]
		</div>
	</div>
</section>

<section id="services" class="nino-section nino-vpa">
	<div class="nino-grid-row">
		<div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-50">
			[title /template/page-home/services/title level="2"]
			[text /template/page-home/services/text style="quiet"]
		</div>
		<div class="nino-grid-s-100 nino-grid-m-100 nino-grid-l-50">
			[stack /services sort="title" limit="6" cols="100 50 50" gap="2" autoheight="1"]
				[image image focus="5"]
				[title title level="3"]
				[text summary]
				[button .uri text="Mehr"]
			[/stack]
		</div>
	</div>
</section>

<!-- nino:html -->
<section id="map" class="nino-section nino-section--alt">
	<div class="nino-grid-row"><div class="nino-grid-100">[osm lat="48.1" lon="11.5" zoom="12"]</div></div>
</section>
<!-- /nino:html -->

<section id="contact" class="nino-section nino-section--primary nino-text-center">
	<div class="nino-grid-row nino-grid-row--narrow">
		<div class="nino-grid-100">
			[title /template/page-home/contact/title level="2"]
			[button /_nino/webpage/contact/name href="/_nino/webpage/contact/uri" style="light"]
		</div>
	</div>
</section>

[template /templates/html-footer]
