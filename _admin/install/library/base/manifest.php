<?php return [
	'label' 		=> 'Base',
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
		// Not included by the base templates themselves, but by the frames
		// that can replace them: footer/v2 renders it through
		// [template /templates/html-socialmedia]. Without it here nothing
		// ever copies the file into the project and that include silently
		// resolves to an empty string
		'html-socialmedia.tpl',
		'robots.tpl',
		'sitemap-xml.tpl',
		'llms-txt.tpl',
	],
	'blacklist' => [
		'/website/lang',
		'/website/charset',
		'/website/url',
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
		'images',
		'favicon'
	],
];
