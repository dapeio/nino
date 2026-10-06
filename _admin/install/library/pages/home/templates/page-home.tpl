<!-- nino:template-name Home -->
<!-- nino:template-vpa on -->
<!-- nino:template-slot header -->
[template /templates/html-header]
<section id="fullscreen-image" class="nino-section nino-section--fullwidth nino-section--black nino-cover nino-cover--dim nino-img-focus--5 nino-mt-0 nino-mb-0" data-cover-height="100" aria-labelledby="fullscreen-image-title">
	<!-- nino:section {"version":3,"preset":"hero-fullscreen-image","pageId":"page-home","id":"fullscreen-image","pageMotion":"on","layout":"auto","frame":{"screen":"auto","vertical":"auto","background":"auto","container":"auto","padding":"auto","margin":"auto","focus":"auto","overlay":"auto","backgroundImage":"/template/page-home/fullscreen-image/background","backgroundImageSource":"new"},"areas":{"content":{"style":"left","components":[{"id":"title","type":"title","style":"loud","settings":{"target":"same"},"bindings":{"text":"/template/page-home/welcome/title"},"bindingSources":{"text":"textfill"}},{"id":"subtitle","type":"subtitle","style":"auto","settings":{"target":"same"},"bindings":{"text":"/template/page-home/welcome/subtitle"},"bindingSources":{"text":"textfill"}},{"id":"action","type":"button","style":"primary","settings":{"target":"same"},"bindings":{"label":"/_nino/webpage/contact/name","href":"/_nino/webpage/contact/uri"},"bindingSources":{"label":"textfill","href":"textfill"}}],"source":{"elementMode":"new","elementType":"preview-preview-fullscreen-image-content","shortcode":{"locale":"","callback":"","limit":6,"query":""}}}}} -->
	[image /template/page-home/fullscreen-image/background alt=""]
	<div class="nino-cover-content">
		<div class="nino-grid-row nino-grid-row--wide nino-vpa">
			<div class="nino-grid-100 nino-text-left"><h2 class="nino-atf-title nino-atf-title--loud" id="fullscreen-image-title">[[/template/page-home/welcome/title]]</h2><p class="nino-atf-subtitle">[[/template/page-home/welcome/subtitle]]</p><a href="[[/_nino/webpage/contact/uri]]" class="nino-btn nino-btn--primary">[[/_nino/webpage/contact/name]]</a></div>
		</div>
	</div>
</section><!-- nino:template-slot footer -->
[template /templates/html-footer]
