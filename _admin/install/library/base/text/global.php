<?php return [

	'[[/company/name]]' => 'Your Company',
	'[[/company/adress]]' => 'Street 1, 12345 City',
	'[[/company/phone]]' => '+49 (0) 170 1234567',
	'[[/company/email]]' => 'contact@example.com',

	/*	The address every mail this project sends goes out as, and the one a
		contact form answers to. A fill rather than a literal, and it lives here
		rather than with the Form module: \Nino\Mail::_getSender() reads it for
		the From header and the envelope sender of every mail the framework
		sends, so a project that did not pick that module had neither - and a
		mail without a From goes out as the webserver user, which is the most
		reliable way there is to land in a spam folder.

		[[/company/email]] as the value, so the normal case is the address the
		project already gave: one answer, in one place, and changing it changes
		both. An operator who needs a different one - a no-reply, a mailbox on
		another domain - overwrites this key in the Text panel and the company
		address stays what it is. Where the sending host may not send for the
		address at all (spf/dmarc), '/nino/mail/sender' in config.php is the
		envelope sender and this stays the mailbox replies reach	*/
	'[[/form/email/owner]]' => '[[/company/email]]',
	'[[/company/instagram]]' => 'https://www.instagram.com/your-company',
	'[[/company/facebook]]' => 'https://www.faceboook.com/your-company',
	'[[/company/youtube]]' => 'https://www.youtube.com/your-company',
	'[[/company/telegram]]' => 'https://t.me/your-company',

	'[[/website/url]]' => 'www.example.com',
	'[[/website/charset]]' => 'UTF-8',
	'[[/website/author]]' => 'Your Company',
	'[[/website/host]]' => 'Your Hosting Provider, Street 99, 12345 City',
];
