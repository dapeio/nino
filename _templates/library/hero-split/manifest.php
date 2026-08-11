<?php return [
	'name' => 'Hero — Split',
	'description' => 'A modern editorial hero with copy, two actions and a strong side image.',
	'category' => 'Hero',
	'tags' => [ 'hero', 'split', 'image', 'editorial', 'two buttons', 'modern' ],
	'version' => 1,
	'shell' => 'hero',
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'left', 'content' => 'media-split', 'contentStyle' => 'auto',
		'action' => 'dual-buttons', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'media-right', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
