<?php return [
	'name' => 'Content — Badge cloud',
	'description' => 'A compact cluster of repeatable tags, technologies, categories or credentials.',
	'category' => 'Content',
	'tags' => [ 'badges', 'pills', 'tags', 'skills', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'badges', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'compact',
		'margin' => 'none', 'border' => 'none', 'layout' => 'pill', 'limit' => 12,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center', 'right' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'compact', 'default' ],
		'layout' => [ 'plain', 'pill' ],
	],
];
