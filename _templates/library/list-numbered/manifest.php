<?php return [
	'name' => 'Lists — Numbered',
	'description' => 'A straightforward ordered list for instructions, requirements or short steps.',
	'category' => 'Process',
	'tags' => [ 'list', 'numbered', 'ordered', 'steps', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'left', 'content' => 'lists', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'numbered', 'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'numbered', 'numbered-2' ],
	],
];
