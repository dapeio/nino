<?php return [
	'name' => 'Gallery — Grid lightbox',
	'description' => 'A balanced responsive image grid with accessible lightbox dialogs.',
	'category' => 'Media',
	'tags' => [ 'gallery', 'images', 'grid', 'lightbox', 'portfolio' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'gallery', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'grid', 'limit' => 12,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
