<?php return [
	'name' => 'Media — Content slider',
	'description' => 'Image-led slides with title, description and an optional individual link.',
	'category' => 'Media',
	'tags' => [ 'slider', 'carousel', 'images', 'content', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'black', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'media-slider', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'wide', 'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'narrow', 'wide' ],
	],
];
