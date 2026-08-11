<?php return [
	'name' => 'Articles — Grid',
	'description' => 'Two, three or four repeatable articles, optionally with images and CTA.',
	'category' => 'Features',
	'tags' => [ 'articles', 'features', 'grid', 'elements', 'cards' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'articles', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => '3', 'limit' => 3,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center', 'right' ],
		'content' => [ 'articles', 'articles-image' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ '2', '3', '4' ],
	],
];
