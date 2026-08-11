<?php return [
	'name' => 'Gallery — Editorial mosaic',
	'description' => 'A modern mixed-size gallery with a lightbox for every image.',
	'category' => 'Media',
	'tags' => [ 'gallery', 'mosaic', 'images', 'editorial', 'lightbox' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'black', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'left', 'content' => 'gallery', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'mosaic', 'limit' => 10,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
