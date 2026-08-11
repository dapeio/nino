<?php return [
	'name' => 'Content — Notice',
	'description' => 'A focused informational, success or warning message from one native textfill.',
	'category' => 'Content',
	'tags' => [ 'notice', 'alert', 'message', 'callout', 'info' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'none',
		'align' => 'left', 'content' => 'notice', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'compact',
		'margin' => 'none', 'border' => 'none', 'layout' => 'info', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle' ],
		'action' => [ 'none', 'link', 'button' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'compact', 'default' ],
		'layout' => [ 'info', 'success', 'error' ],
	],
];
