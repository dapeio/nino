<!-- nino:template-name Contact -->
<!-- nino:template-vpa on -->
<!-- nino:template-slot header -->
[template /templates/html-header]
<section id="hero" class="nino-section nino-section--dark nino-cover nino-pt-6 nino-pb-6 nino-mt-0 nino-mb-0" data-cover-height="50" aria-labelledby="hero-title">
	<!-- nino:section {"version":3,"preset":"image-banner","pageId":"contact","id":"hero","pageMotion":"on","layout":"plain","frame":{"screen":"50","vertical":"auto","background":"dark","container":"auto","padding":"auto","margin":"auto","focus":"auto","overlay":"auto","backgroundImage":"/page-contact/hero/background","backgroundImageSource":"new"},"areas":{"content":{"style":"left","components":[{"id":"title","type":"title","style":"auto","settings":{"target":"same"},"bindings":{"text":"/page-contact/hero/title"},"bindingSources":{"text":"new"}},{"id":"subtitle","type":"subtitle","style":"auto","settings":{"target":"same"},"bindings":{"text":"/page-contact/hero/subtitle"},"bindingSources":{"text":"new"}}],"source":{"elementMode":"new","elementType":"preview-preview-image-banner-content","shortcode":{"locale":"","callback":"","limit":6,"query":""}}}}} -->
	<div class="nino-cover-content">
		<div class="nino-grid-row nino-vpa">
			<div class="nino-grid-100 nino-text-left"><h2 class="nino-atf-title" id="hero-title">[[/page-contact/hero/title]]</h2><p class="nino-atf-subtitle">[[/page-contact/hero/subtitle]]</p></div>
		</div>
	</div>
</section><section id="contact-form" class="nino-section nino-mt-0 nino-mb-0" aria-labelledby="contact-form-title">
	<!-- nino:section {"version":3,"preset":"contact-form","pageId":"contact","id":"contact-form","pageMotion":"on","layout":"auto","frame":{"screen":"auto","vertical":"auto","background":"auto","container":"auto","padding":"auto","margin":"auto","focus":"auto","overlay":"auto","backgroundImage":"/page-contact/contact-form/background","backgroundImageSource":"new"},"areas":{"intro":{"style":"auto","components":[{"id":"title","type":"title","style":"auto","settings":{"target":"same"},"bindings":{"text":"/company/name"},"bindingSources":{"text":"textfill"}},{"id":"subtitle","type":"subtitle","style":"auto","settings":{"target":"same"},"bindings":{"text":"/company/description"},"bindingSources":{"text":"textfill"}}],"source":{"elementMode":"new","elementType":"preview-preview-contact-form-intro","shortcode":{"locale":"","callback":"","limit":6,"query":""}}},"outro":{"style":"auto","components":[],"source":{"elementMode":"new","elementType":"preview-preview-contact-form-outro","shortcode":{"locale":"","callback":"","limit":6,"query":""}}}}} -->
	<div class="nino-grid-row nino-vpa">
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<div class="nino-grid-100 nino-mb-3 nino-text-left"><h2 class="nino-section-title" id="contact-form-title">[[/company/name]]</h2><p class="nino-section-subtitle">[[/company/description]]</p></div>
			<ul class="nino-list">
				<li><strong>[[/company/name]]</strong></li>
				<li>[[/company/adress]]</li>
				<li><a href="mailto:[[/company/email]]">[[/company/email]]</a></li>
				<li><a href="tel:[[/company/phone]]">[[/company/phone]]</a></li>
			</ul>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<form class="nino-form">
				[csrf]
				<label for="contact-form-name">[[/form/label/name]] *</label>
				<input type="text" id="contact-form-name" name="name" class="nino-form-input" required>
		
				<label for="contact-form-email">[[/form/label/email]] *</label>
				<input type="email" id="contact-form-email" name="email" class="nino-form-input" required>
		
				<label for="contact-form-message">[[/form/label/message]] *</label>
				<textarea id="contact-form-message" name="message" class="nino-form-textarea" required></textarea>
		
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
		
				<p class="nino-form-message"></p>
				<p><small>* [[/form/required]]</small></p>
				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">[[/form/label/submit]]</button>
			</form>
		</div>
	</div>
</section><!-- nino:template-slot footer -->
[template /templates/html-footer]
