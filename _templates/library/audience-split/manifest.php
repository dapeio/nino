<?php return [
	'name' => 'CTA — Audience split',
	'description' => 'Two prominent paths for distinct audiences, products or use cases.',
	'category' => 'CTA',
	'tags' => [ 'audience', 'split', 'two paths', 'cards', 'cta' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'cards', 'contentStyle' => 'default',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => '2', 'limit' => 2,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
