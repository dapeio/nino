<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
	[[area:intro]]
</div>
<div class="nino-grid-100 nino-grid-m-50 nino-p-2">
	<form class="nino-form nino-newsletter-form nino-form--inline" action="/.newsletter">
		[csrf]
		<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="nino-form-trap">
		<label for="[[section:id]]-email" class="nino-sr-only">[[/newsletter/label/email]]</label>
		<input type="email" id="[[section:id]]-email" name="email" class="nino-form-input" placeholder="[[/newsletter/label/email]]" required>
		<button type="submit" class="nino-btn nino-btn--primary nino-form-submit">[[/newsletter/label/submit]]</button>
		<p class="nino-form-message nino-grid-100"></p>
	</form>
	[[area:outro]]
</div>
