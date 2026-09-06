[template /templates/mail-header]
<h1>[[/mail/user/title]]</h1>
<p>[[/mail/user/greeting]] [[name]],<br>
[[/mail/user/intro]]</p>
<p>[[/mail/user/summary]]</p>
<table>
	<tr><th>[[/form/label/name]]</th><td>[[name]]</td></tr>
	<tr><th>[[/form/label/email]]</th><td>[[email]]</td></tr>
	<tr><th>[[/form/label/message]]</th><td>[[message]]</td></tr>
	<tr><th>[[/form/label/date]]</th><td>[[date]]</td></tr>
</table>
<p class="mail-note">[[/mail/user/notice]]</p>
<table>
	<tr><th>[[/global/email]]</th><td>[[/company/email]]</td></tr>
	<tr><th>[[/global/phone]]</th><td>[[/company/phone]]</td></tr>
</table>
<p>[[/mail/user/closing]]<br>
[[/company/name]]</p>
[template /templates/mail-footer]
