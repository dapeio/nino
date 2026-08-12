<?php return [
	'name' => 'Hero — Full',
	'description' => 'Fullscreen introduction with optional cover image and call to action.',
	'category' => 'Hero',
	'tags' => [ 'hero', 'atf', 'cover', 'cta' ],
	'version' => 1,
	'shell' => 'hero',
	'defaults' => [
		'surface' => 'black', 'background' => 'image-cover', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'none', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'auto', 'limit' => 3,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'align' => [ 'left', 'center', 'right' ],
		'background' => [ 'none', 'image-cover', 'image-static', 'parallax' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
