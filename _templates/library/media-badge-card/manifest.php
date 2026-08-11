<?php return [
	'name' => 'Offer — Image badge spotlight',
	'description' => 'One visual offer with badge, price, supporting copy and action.',
	'category' => 'Commerce',
	'tags' => [ 'offer', 'badge', 'image', 'price', 'spotlight', 'card' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'cards', 'contentStyle' => 'default',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'spotlight', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
