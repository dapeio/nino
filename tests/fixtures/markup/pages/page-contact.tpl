<!-- nino:template-name Contact -->
[template /templates/html-header]
<div class="nino-wrap nino-wrap--vpa">

<section id="hero" class="nino-section nino-section--dark nino-cover nino-mt-0 nino-mb-0 nino-pt-6 nino-pb-6" data-cover-height="50">
	<div class="nino-grid-row nino-vpa">
		<div class="nino-grid-100 nino-text-left">
			[title /template/page-contact/hero/title tag="h2" style="hero"]
			[subtitle /template/page-contact/hero/subtitle tag="p" style="hero"]
		</div>
	</div>
</section>

<!-- nino:html -->
<section id="contact-form" class="nino-section nino-mt-0 nino-mb-0" aria-labelledby="contact-form-title">
	<div class="nino-grid-row nino-vpa">
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<div class="nino-mb-3 nino-text-left"><h2 class="nino-section-title" id="contact-form-title">[[/project/company/general/name]]</h2><p class="nino-section-subtitle">[[/project/company/general/description]]</p></div>
			<ul class="nino-list">
				<li><strong>[[/project/company/general/name]]</strong></li>
				<li>[[/project/company/contact/address]]</li>
				<li><a href="mailto:[[/project/company/contact/email]]">[[/project/company/contact/email]]</a></li>
				<li><a href="tel:[[/project/company/contact/phone]]">[[/project/company/contact/phone]]</a></li>
			</ul>
		</div>
		<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
			<form class="nino-form" action="[[/nino/dir]]/.form" method="post" novalidate>
				[csrf]
				<label for="contact-form-name">[[/template/common/form/name]] *</label>
				<input type="text" id="contact-form-name" name="name" class="nino-form-input" autocomplete="name" required>
		
				<label for="contact-form-email">[[/template/common/form/email]] *</label>
				<input type="email" id="contact-form-email" name="email" class="nino-form-input" autocomplete="email" required>
		
				<label for="contact-form-message">[[/template/common/form/message]] *</label>
				<textarea id="contact-form-message" name="message" class="nino-form-textarea" required></textarea>
		
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
		
				<p class="nino-form-message" aria-live="polite"></p>
				<p class="nino-form-note">* [[/template/common/form/required]]</p>
				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">[[/template/common/form/submit]]</button>
			</form>
		</div>
	</div>
</section>
<!-- /nino:html -->

</div>
[template /templates/html-footer]
