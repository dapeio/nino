<?php return [
	'name' => 'Metrics — Counter row',
	'description' => 'Animated key figures in two, three or four balanced columns.',
	'category' => 'Metrics',
	'tags' => [ 'stats', 'metrics', 'numbers', 'counter', 'proof' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'dark', 'background' => 'none', 'header' => 'none',
		'align' => 'center', 'content' => 'stats', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => '4', 'limit' => 4,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ '2', '3', '4' ],
	],
];
