<section class="ui-section ui-section--alt" id="section-contact">
	<div class="ui-grid-row">
		<div class="ui-grid-100 ui-mb-3">
			<h3 class="ui-section-title">[[/page-contact/info/title]]</h3>
			<p class="ui-section-subtitle">[[/form/info/welcome]]</p>
		</div>
		<div class="ui-grid-100 ui-grid-m-50">
			<h4>[[/global/adress]]</h4>
			<p>[[/company/adress]]<br>[[/company/country]]</p>

			<h4 class="ui-mt-2">[[/global/phone]]</h4>
			<p><a href="tel:[[/company/phone]]">[[/company/phone]]</a></p>

			<h4 class="ui-mt-2">[[/global/email]]</h4>
			<p><a href="mailto:[[/company/email]]">[[/company/email]]</a></p>

			<h4 class="ui-mt-2">[[/page-contact/info/hours/title]]</h4>
			<p>[[/page-contact/info/hours/text]]</p>

			<div class="ui-mt-3">
				<a href="tel:[[/company/phone]]" class="ui-btn ui-btn--outline">[[/global/cta/call]]</a>
			</div>
		</div>
		<div class="ui-grid-100 ui-grid-m-50">
			<form class="ui-form" action="/" method="post">
				[csrf]
				<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px;">

				<label for="contact-name">[[/form/label/name]]</label>
				<input type="text" id="contact-name" name="name" class="ui-form-input" placeholder="[[/form/label/name]]" required>

				<label for="contact-email">[[/form/label/email]]</label>
				<input type="email" id="contact-email" name="email" class="ui-form-input" placeholder="[[/form/label/email]]" required>

				<label for="contact-cat">[[/form/label/cat]]</label>
				<select id="contact-cat" name="cat" class="ui-form-select">
					<option value="services">[[/webpage/services/name]]</option>
					<option value="work">[[/webpage/work/name]]</option>
					<option value="other">[[/global/cta/getintouch]]</option>
				</select>

				<label for="contact-message">[[/form/label/message]]</label>
				<textarea id="contact-message" name="message" class="ui-form-textarea" placeholder="[[/form/label/message]]" required></textarea>

				<p class="ui-form-message"></p>

				<button type="submit" class="ui-btn ui-btn--primary ui-form-submit">[[/form/label/submit]]</button>
			</form>
		</div>
	</div>
</section>
