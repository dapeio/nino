<!-- nino:template-name Home -->
<!-- nino:template-vpa on -->
<!-- nino:template-slot header -->
[template /templates/html-header]
<section id="fullscreen-image" class="ui-section ui-section--fullwidth ui-section--black js-cover js-cover--dim ui-img-focus--5 ui-mt-0 ui-mb-0" data-cover-height="100" aria-labelledby="fullscreen-image-title">
	<!-- nino:section {"version":3,"preset":"fullscreen-image","pageId":"home","id":"fullscreen-image","pageMotion":"on","layout":"auto","frame":{"screen":"auto","vertical":"auto","background":"auto","container":"auto","padding":"auto","margin":"auto","focus":"auto","overlay":"auto","backgroundImage":"[[/nino/public]]/images/demo.jpg","backgroundImageSource":"fixed"},"areas":{"content":{"style":"left","components":[{"id":"title","type":"title","style":"loud","settings":{"target":"same"},"bindings":{"text":"/page-home/welcome/title"},"bindingSources":{"text":"textfill"}},{"id":"subtitle","type":"subtitle","style":"auto","settings":{"target":"same"},"bindings":{"text":"/page-home/welcome/subtitle"},"bindingSources":{"text":"textfill"}},{"id":"action","type":"button","style":"primary","settings":{"target":"same"},"bindings":{"label":"/webpage/contact/name","href":"/webpage/contact/uri"},"bindingSources":{"label":"textfill","href":"textfill"}}],"source":{"elementMode":"new","elementType":"preview-preview-fullscreen-image-content","shortcode":{"locale":"","callback":"","limit":6,"query":""}}}}} -->
	<img src="[[/nino/public]]/images/demo.jpg" alt="">
	<div class="js-cover-content">
		<div class="ui-grid-row ui-grid-row--wide js-vpa">
			<div class="ui-grid-100 ui-text-left"><h2 class="ui-atf-title ui-atf-title--loud" id="fullscreen-image-title">[[/page-home/welcome/title]]</h2><p class="ui-atf-subtitle">[[/page-home/welcome/subtitle]]</p><a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary">[[/webpage/contact/name]]</a></div>
		</div>
	</div>
</section><!-- nino:template-slot footer -->
[template /templates/html-footer]
