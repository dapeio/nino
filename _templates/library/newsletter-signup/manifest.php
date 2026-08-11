<?php return [
	'name' => 'Newsletter — Inline signup',
	'description' => 'A compact conversion block with headline and inline email form.',
	'category' => 'Forms',
	'tags' => [ 'newsletter', 'signup', 'email', 'form', 'conversion' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'primary', 'background' => 'none', 'header' => 'title-subtitle-description',
		'align' => 'center', 'content' => 'newsletter', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'narrow', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'narrow', 'wide' ],
	],
];
