<?php return [

	'[[/project/company/general/name]]' => 'Your Company',
	'[[/project/company/contact/address]]' => 'Street 1, 12345 City',
	'[[/project/company/contact/phone]]' => '+49 (0) 170 1234567',
	'[[/project/company/contact/email]]' => 'contact@example.com',

	/*	The address every mail this project sends goes out as, and the one a
		contact form answers to. A fill rather than a literal, and it lives here
		rather than with the Form module: \Nino\Mail::_getSender() reads it for
		the From header and the envelope sender of every mail the framework
		sends, so a project that did not pick that module had neither - and a
		mail without a From goes out as the webserver user, which is the most
		reliable way there is to land in a spam folder.

		[[/project/company/contact/email]] as the value, so the normal case is
		the address the project already gave: one answer, in one place, and
		changing it changes both. An operator who needs a different one - a
		no-reply, a mailbox on another domain - overwrites this key in the Text
		panel and the company address stays what it is. Where the sending host
		may not send for the address at all (spf/dmarc),
		[[/project/mail/address/envelope]] below is the envelope sender and this
		stays the mailbox replies reach	*/
	'[[/project/mail/address/owner]]' => '[[/project/company/contact/email]]',

	/*	The envelope sender, where it has to differ from the address above:
		SPF and DMARC are checked against it, so it has to be one the sending
		host may send for. Empty means "the same as the address above", which
		is the normal case; set in the Text panel like the rest, and a value
		that is no address falls back to the address above, with a line in
		the log	*/
	'[[/project/mail/address/envelope]]' => '',

	'[[/project/website/general/url]]' => 'www.example.com',
	'[[/project/website/general/author]]' => 'Your Company',
	'[[/project/website/general/host]]' => 'Your Hosting Provider, Street 99, 12345 City',
	'[[/project/website/html/charset]]' => 'UTF-8',

	/*	The look of every mail the framework sends, read by mail-header.tpl.
		Here rather than with the Form module, as the address above is: the
		Newsletter feature sends in the same dress, and a module a project may
		decline is the wrong place for it	*/
	'[[/project/mail/color/primary]]' => '#4faae8',
	'[[/project/mail/color/text]]' => '#333333',
	'[[/project/mail/color/background]]' => '#ffffff',
	'[[/project/mail/color/border]]' => '#dddddd',
	'[[/project/mail/color/backdrop]]' => '#eeeeee',
	'[[/project/mail/font/line-height]]' => '1.5',
	'[[/project/mail/font/small]]' => '.75em',
	'[[/project/mail/font/large]]' => '1.25em',
	'[[/project/mail/spacing/small]]' => '.5rem',
	'[[/project/mail/spacing/medium]]' => '1rem',
	'[[/project/mail/spacing/large]]' => '2rem',
];
