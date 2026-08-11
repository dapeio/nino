<?php return [
	'name' => 'Image banner — Static',
	'description' => 'A quiet full-width image banner with headline, subtitle and optional actions.',
	'category' => 'CTA',
	'tags' => [ 'banner', 'image', 'static', 'full width', 'cta' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'black', 'background' => 'image-static', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'none', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'generous',
		'margin' => 'none', 'border' => 'none', 'layout' => 'auto', 'limit' => 1,
	],
	'allow' => [
		'background' => [ 'image-cover', 'image-static', 'parallax' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'default', 'generous' ],
	],
];
