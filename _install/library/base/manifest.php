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
		'robots.tpl',
		'sitemap-xml.tpl',
		'llms-txt.tpl',
	],
	'blacklist' => [
		'/website/lang',
		'/website/charset',
		'/website/url',
	],
	'files' => [
		'assets'
	],
];
