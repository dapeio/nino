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
		// The site's header and footer markup. html-header.tpl includes them as
		// [template /templates/theme.header] rather than carrying them inline,
		// so a project can rewrite either file without touching the page frame
		// around it - and a missing include resolves to an empty string, which
		// is exactly the silent no-header a delivery must never ship
		'theme.header.tpl',
		'theme.footer.tpl',
		// Not included by theme.footer.tpl itself, but by a footer that
		// replaces it: [template /templates/html-socialmedia]. It travels
		// with the base unit so that include resolves in any project -
		// without the file, it silently resolves to an empty string, and a
		// footer bringing its own copy is a footer a project cannot swap
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
		// The three webfaces theme.css declares. They came with the theme unit
		// while a theme was something the wizard asked about; the look is
		// fixed now, so they come with everything else that is
		'fonts',
		'images',
		'favicon'
	],
];
