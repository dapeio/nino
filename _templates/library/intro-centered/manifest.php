<?php return [
	'name' => 'Intro — Centered',
	'description' => 'A calm title, subtitle and description block for the start of a topic.',
	'category' => 'Intro',
	'tags' => [ 'intro', 'title', 'subtitle', 'description', 'centered' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'center', 'content' => 'none', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'auto', 'limit' => 3,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center', 'right' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'default', 'compact', 'generous' ],
		'border' => [ 'none', '1', '2', '3' ],
	],
];
