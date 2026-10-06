<?php return [
	'routes' 		=> [
		'GET://robots.txt' => [
			'uri' 		=> '/robots.txt',
			'body' 		=> '[template /templates/robots]',
			'header' 	=> [ 'Content-Type' => 'text/plain; charset=utf-8' ],
		],
		'GET://sitemap.xml' => [
			'uri' 		=> '/sitemap.xml',
			'body' 		=> '[template /templates/sitemap-xml]',
			'header' 	=> [ 'Content-Type' => 'application/xml; charset=utf-8' ],
		],
		'GET://llms.txt' => [
			'uri' 		=> '/llms.txt',
			'body' 		=> '[template /templates/llms-txt]',
			'header' 	=> [ 'Content-Type' => 'text/plain; charset=utf-8' ],
		],
	],
	'templates' => [
		'html-header.tpl',
		'html-footer.tpl',
		// The site's header and footer markup. html-header.tpl includes them as
		// [template /templates/frame-header] rather than carrying them inline,
		// so a project can rewrite either file without touching the page frame
		// around it - and a missing include resolves to an empty string, which
		// is exactly the silent no-header a delivery must never ship
		'frame-header.tpl',
		'frame-footer.tpl',
		'robots.tpl',
		'sitemap-xml.tpl',
		'llms-txt.tpl',
	],
	'blacklist' => [
		'/project/website/html/lang',
		'/project/website/html/charset',
		'/project/mail/color/primary',
		'/project/mail/color/text',
		'/project/mail/color/background',
		'/project/mail/color/border',
		'/project/mail/color/backdrop',
		'/project/mail/font/line-height',
		'/project/mail/font/small',
		'/project/mail/font/large',
		'/project/mail/spacing/small',
		'/project/mail/spacing/medium',
		'/project/mail/spacing/large',
	],
	/*	Copied wherever this project keeps that kind of file, so each entry
		follows \Nino\Filesystem::path() rather than a literal directory.

		'private' is the deny rule for the private tree itself. It ships here
		rather than in the repository because a checkout has no private/ at
		all - the wizard creates it, so the wizard has to bring the rule that
		protects it, and Setup is the first step that writes anything.	*/
	'files' => [
		'private',
		'assets',
		// The three webfaces theme.css declares. They came with the theme unit
		// while a theme was something the wizard asked about; the look is
		// fixed now, so they come with everything else that is
		'fonts',
		'favicon'
	],
	/*	The logo is an image slot, not a file the base unit ships: the frames,
		the navigation and the mails show it with [image /logo], and until
		somebody uploads one - Images, in the workbench - they show nothing.
		The size is the ratio of a wordmark; an upload is cut to it, so a
		project whose logo is shaped otherwise changes it under Image Slots	*/
	'imageSlots' => [
		'/logo' => [ 'label' => 'Logo', 'width' => 500, 'height' => 100 ],
	],
];
