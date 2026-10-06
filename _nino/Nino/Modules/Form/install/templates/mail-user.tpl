[template /templates/mail-header]
<h1>[[/template/mail-user/intro/title]]</h1>
<p>[[/template/mail-user/intro/greeting]] [[name]],<br>
[[/template/mail-user/intro/text]]</p>
<p>[[/template/mail-user/summary/title]]</p>
[[fields]]
<table>
	<tr><th>[[/template/common/form/date]]</th><td>[[date]]</td></tr>
</table>
<p class="mail-note">[[/template/mail-user/outro/notice]]</p>
<table>
	<tr><th>[[/template/common/label/email]]</th><td>[[/project/company/contact/email]]</td></tr>
	<tr><th>[[/template/common/label/phone]]</th><td>[[/project/company/contact/phone]]</td></tr>
</table>
<p>[[/template/mail-user/outro/closing]]<br>
[[/project/company/general/name]]</p>
[template /templates/mail-footer]
