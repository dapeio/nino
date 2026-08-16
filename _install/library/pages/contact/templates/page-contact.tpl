[template /templates/html-header]
<section class="ui-section ui-section--fullwidth">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-grid-l-50">
			<h2 class="ui-section-title">[[/form/title]]</h2>
			<p>[[/form/info/welcome]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-l-50">
			<form class="ui-form">
				[csrf]
				<label for="contact-name">[[/form/label/name]]</label>
				<input type="text" id="contact-name" name="name" class="ui-form-input" required>

				<label for="contact-email">[[/form/label/email]]</label>
				<input type="email" id="contact-email" name="email" class="ui-form-input" required>

				<label for="contact-message">[[/form/label/message]]</label>
				<textarea id="contact-message" name="message" class="ui-form-textarea" required></textarea>

				<!-- Honeypot - stays hidden/empty for real visitors, see Form.php.
				     Hidden from assistive technology as well, same as the newsletter
				     signup in .demo-sections.tpl: .ui-sr-only is screen-reader-*only*,
				     ie. the exact opposite, and an unlabeled field a screen reader
				     announced and a visitor filled in came back as Form.php's silent
				     418 with nothing to explain it -->
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px;">

				<p class="ui-form-message"></p>
				<p><small>[[/form/required]]</small></p>
				<button type="submit" class="ui-btn ui-btn--primary ui-form-submit">[[/form/label/submit]]</button>
			</form>
		</div>
	</div>
</section>
[template /templates/html-footer]
