<?php return [
	'name' => 'Media — Video lightbox',
	'description' => 'A cinematic poster image that opens an embedded video in a modal.',
	'category' => 'Media',
	'tags' => [ 'video', 'poster', 'modal', 'lightbox', 'media' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'black', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'video', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'wide', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'narrow', 'wide' ],
	],
];
