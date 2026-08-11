<?php return [
	'name' => 'Contact — Split form',
	'description' => 'Contact copy and details beside a complete accessible form.',
	'category' => 'Forms',
	'tags' => [ 'contact', 'form', 'address', 'email', 'split' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'left', 'content' => 'contact', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'split', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
