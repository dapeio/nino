<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
	[[area:intro]]
	<ul class="nino-list">
		<li><strong>[[/company/name]]</strong></li>
		<li>[[/company/adress]]</li>
		<li><a href="mailto:[[/company/email]]">[[/company/email]]</a></li>
		<li><a href="tel:[[/company/phone]]">[[/company/phone]]</a></li>
	</ul>
	[[area:outro]]
</div>
<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
	<form class="nino-form">
		[csrf]
		<label for="[[section:id]]-name">[[/form/label/name]]</label>
		<input type="text" id="[[section:id]]-name" name="name" class="nino-form-input" required>

		<label for="[[section:id]]-email">[[/form/label/email]]</label>
		<input type="email" id="[[section:id]]-email" name="email" class="nino-form-input" required>

		<label for="[[section:id]]-message">[[/form/label/message]]</label>
		<textarea id="[[section:id]]-message" name="message" class="nino-form-textarea" required></textarea>

		<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">

		<p class="nino-form-message"></p>
		<p><small>[[/form/required]]</small></p>
		<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">[[/form/label/submit]]</button>
	</form>
</div>
