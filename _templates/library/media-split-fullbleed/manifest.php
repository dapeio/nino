<?php return [
	'name' => 'Media / Text — Full bleed',
	'description' => 'An edge-to-edge image and text composition, reversible without editing code.',
	'category' => 'Content',
	'tags' => [ 'media', 'image', 'text', '50-50', 'full bleed', 'editorial' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'none',
		'align' => 'left', 'content' => 'media-split', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'none',
		'margin' => 'none', 'border' => 'none', 'layout' => 'media-left-full', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'media-left-full', 'media-right-full' ],
	],
];
