[template /templates/html-header]
<form class="nino-form nino-newsletter-form" action="[[/nino/dir]]/.newsletter">
	<label for="newsletter-email">[[/template/common/form/email]]</label>
	<input type="email" id="newsletter-email" name="email" required>
	<button type="submit">[[/feature/newsletter/label/submit]]</button>
</form>
[template /templates/html-footer]
