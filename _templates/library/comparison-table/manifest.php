<?php return [
	'name' => 'Comparison — Table',
	'description' => 'A clear two-column comparison for plans, products or approaches.',
	'category' => 'Commerce',
	'tags' => [ 'comparison', 'table', 'plans', 'products', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'center', 'content' => 'comparison', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'wide', 'limit' => 12,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
