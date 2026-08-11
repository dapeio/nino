<?php return [
	'name' => 'Process — Timeline',
	'description' => 'Three or four ordered steps backed by an Elements collection.',
	'category' => 'Process',
	'tags' => [ 'timeline', 'process', 'steps', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'timeline', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => '4', 'limit' => 4,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ '3', '4' ],
	],
];
