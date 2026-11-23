<!-- nino:template-name Contact -->
[template /templates/html-header]
[section-wrap id="hero" ground="dark" height="60"]
	[section-row]
		[section-col]
			[section-title ~title tag="h1" size="xl"]
			[section-subtitle ~subtitle]
		[/section-col]
	[/section-row]
[/section-wrap]
[section-wrap id="details"]
	[section-row gap="3"]
		[section-col size="100 m:50"]
			[section-title /project/company/general/name]
			[section-subtitle /project/company/general/description]
			[text /project/company/contact/address format="lines"]
			[text /project/company/contact/phone format="inline"]
			[button-group]
				[button /project/company/contact/email href="mailto:[[/project/company/contact/email]]" tone="quiet"]
			[/button-group]
		[/section-col]
		[section-col size="100 m:50"]
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
		[/section-col]
	[/section-row]
[/section-wrap]
[template /templates/html-footer]
