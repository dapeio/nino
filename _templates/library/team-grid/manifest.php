<?php return [
	'name' => 'Team — Portrait grid',
	'description' => 'Three or four people with portrait, role and short biography.',
	'category' => 'Team',
	'tags' => [ 'team', 'people', 'portraits', 'profiles', 'grid' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'center', 'content' => 'profiles', 'contentStyle' => 'default',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => '3', 'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ '3', '4' ],
	],
];
