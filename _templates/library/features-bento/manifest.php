<?php return [
	'name' => 'Features — Bento',
	'description' => 'A contemporary asymmetric feature composition for varied product benefits.',
	'category' => 'Features',
	'tags' => [ 'features', 'bento', 'modern', 'asymmetric', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'left', 'content' => 'features', 'contentStyle' => 'default',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'bento', 'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
