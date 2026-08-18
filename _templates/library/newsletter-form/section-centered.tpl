[[area:intro]]
<div class="ui-grid-100 ui-grid-m-66 ui-mx-auto">
	<form class="ui-form js-newsletter-form ui-form--inline" action="/.newsletter">
		[csrf]
		<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="ui-form-trap">
		<label for="[[section:id]]-email" class="ui-sr-only">[[/newsletter/label/email]]</label>
		<input type="email" id="[[section:id]]-email" name="email" class="ui-form-input" placeholder="[[/newsletter/label/email]]" required>
		<button type="submit" class="ui-btn ui-btn--primary ui-form-submit">[[/newsletter/label/submit]]</button>
		<p class="ui-form-message ui-grid-100"></p>
	</form>
</div>
[[area:outro]]
