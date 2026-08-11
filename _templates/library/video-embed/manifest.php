<?php return [
	'name' => 'Media — Video embed',
	'description' => 'A responsive inline video embed without a poster dialog or extra click.',
	'category' => 'Media',
	'tags' => [ 'video', 'embed', 'iframe', 'media', 'inline' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'video-embed', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'wide', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'narrow', 'wide', '4-3' ],
	],
];
