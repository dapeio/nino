<div class="ui-grid-100 ui-grid-m-50 ui-p-2">
	[[area:intro]]
	<ul class="ui-list">
		<li><strong>[[/company/name]]</strong></li>
		<li>[[/company/adress]]</li>
		<li><a href="mailto:[[/company/email]]">[[/company/email]]</a></li>
		<li><a href="tel:[[/company/phone]]">[[/company/phone]]</a></li>
	</ul>
	[[area:outro]]
</div>
<div class="ui-grid-100 ui-grid-m-50 ui-p-2">
	<form class="ui-form">
		[csrf]
		<label for="[[section:id]]-name">[[/form/label/name]]</label>
		<input type="text" id="[[section:id]]-name" name="name" class="ui-form-input" required>

		<label for="[[section:id]]-email">[[/form/label/email]]</label>
		<input type="email" id="[[section:id]]-email" name="email" class="ui-form-input" required>

		<label for="[[section:id]]-message">[[/form/label/message]]</label>
		<textarea id="[[section:id]]-message" name="message" class="ui-form-textarea" required></textarea>

		<input type="text" name="location" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="ui-form-trap">

		<p class="ui-form-message"></p>
		<p><small>[[/form/required]]</small></p>
		<button type="submit" class="ui-btn ui-btn--primary ui-form-submit">[[/form/label/submit]]</button>
	</form>
</div>
