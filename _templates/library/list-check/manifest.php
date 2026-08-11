<?php return [
	'name' => 'Lists — Checklist',
	'description' => 'A concise repeatable benefits list in one or two responsive columns.',
	'category' => 'Content',
	'tags' => [ 'list', 'checklist', 'benefits', 'bullets', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'left', 'content' => 'lists', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'check-2', 'limit' => 8,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'check', 'check-2' ],
	],
];
