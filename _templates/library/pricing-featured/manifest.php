<?php return [
	'name' => 'Pricing — Featured plan',
	'description' => 'A three-plan row with an elevated middle recommendation.',
	'category' => 'Commerce',
	'tags' => [ 'pricing', 'featured', 'recommended', 'plans', 'comparison' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'center', 'content' => 'pricing', 'contentStyle' => 'default',
		'action' => 'none', 'motion' => 'page', 'padding' => 'generous',
		'margin' => 'none', 'border' => 'none', 'layout' => 'featured', 'limit' => 3,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'default', 'generous' ],
	],
];
