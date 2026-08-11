<?php return [
	'name' => 'Trust — Logo cloud',
	'description' => 'A restrained row of client, partner or press logos.',
	'category' => 'Trust',
	'tags' => [ 'logos', 'clients', 'partners', 'press', 'trust' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title',
		'align' => 'center', 'content' => 'logos', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'compact',
		'margin' => 'none', 'border' => 'none', 'layout' => 'wide', 'limit' => 8,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'compact', 'default' ],
	],
];
