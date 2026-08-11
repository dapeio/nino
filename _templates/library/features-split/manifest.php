<?php return [
	'name' => 'Features — Media checklist',
	'description' => 'A product image paired with a repeatable checklist of benefits.',
	'category' => 'Features',
	'tags' => [ 'features', 'checklist', 'media', 'image', 'split' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'left', 'content' => 'feature-list', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'media-left', 'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'media-left', 'media-right' ],
	],
];
