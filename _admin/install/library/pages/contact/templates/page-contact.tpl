<!-- nino:template-name Contact -->
[template /templates/html-header]
<section class="hero">
	<div class="wrap">
		<h1>[[/template/page-contact/hero/title]]</h1>
		<p class="lead">[[/template/page-contact/hero/subtitle]]</p>
	</div>
</section>
<section class="section" id="details">
	<div class="wrap split">
		<div>
			<h2>[[/project/company/general/name]]</h2>
			<p class="lead">[[/project/company/general/description]]</p>
			<address>[[/project/company/contact/address]]</address>
			<p>[[/project/company/contact/phone]]</p>
			<p><a class="button button--quiet" href="mailto:[[/project/company/contact/email]]">[[/project/company/contact/email]]</a></p>
		</div>
		<form class="nino-form" action="[[/nino/dir]]/.form" method="post">
			[csrf]
			<label for="contact-form-name">[[/template/common/form/name]] *</label>
			<input type="text" id="contact-form-name" name="name" autocomplete="name" required>
			<label for="contact-form-email">[[/template/common/form/email]] *</label>
			<input type="email" id="contact-form-email" name="email" autocomplete="email" required>
			<label for="contact-form-message">[[/template/common/form/message]] *</label>
			<textarea id="contact-form-message" name="message" required></textarea>
			<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
			<p class="nino-form-message" aria-live="polite"></p>
			<p class="nino-form-note">* [[/template/common/form/required]]</p>
			<button type="submit" class="nino-form-submit">[[/template/common/form/submit]]</button>
		</form>
	</div>
</section>
[template /templates/html-footer]
