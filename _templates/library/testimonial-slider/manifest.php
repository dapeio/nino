<?php return [
	'name' => 'Testimonials — Slider',
	'description' => 'A touch-enabled slider backed by an Elements collection.',
	'category' => 'Social proof',
	'tags' => [ 'testimonials', 'slider', 'quotes', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'testimonials', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'slider', 'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'slider' ],
	],
];
