[template /templates/html-header]
<section class="nino-section nino-section--fullwidth">
	<div class="nino-grid-row">
		<div class="nino-grid-100 nino-grid-l-50">
			<h2 class="nino-section-title">[[/form/title]]</h2>
			<p>[[/form/info/welcome]]</p>
		</div>
		<div class="nino-grid-100 nino-grid-l-50">
			<form class="nino-form">
				[csrf]
				<label for="contact-name">[[/form/label/name]]</label>
				<input type="text" id="contact-name" name="name" class="nino-form-input" required>

				<label for="contact-email">[[/form/label/email]]</label>
				<input type="email" id="contact-email" name="email" class="nino-form-input" required>

				<label for="contact-message">[[/form/label/message]]</label>
				<textarea id="contact-message" name="message" class="nino-form-textarea" required></textarea>

				<!-- Honeypot - stays hidden/empty for real visitors, see Form.php.
				     Hidden from assistive technology as well, same as the newsletter
				     signup in .demo-sections.tpl: .nino-sr-only is screen-reader-*only*,
				     ie. the exact opposite, and an unlabeled field a screen reader
				     announced and a visitor filled in came back as Form.php's silent
				     418 with nothing to explain it -->
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px;">

				<p class="nino-form-message"></p>
				<p><small>[[/form/required]]</small></p>
				<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">[[/form/label/submit]]</button>
			</form>
		</div>
	</div>
</section>
[template /templates/html-footer]
