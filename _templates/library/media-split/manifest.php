<?php return [
	'name' => 'Media / Text — Split',
	'description' => 'A responsive 50–50 composition with image and native text content.',
	'category' => 'Content',
	'tags' => [ 'media', 'image', 'text', '50-50', 'split' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'none',
		'align' => 'left', 'content' => 'media-split', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'media-left', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'media-left', 'media-right', 'media-left-full', 'media-right-full' ],
	],
];
