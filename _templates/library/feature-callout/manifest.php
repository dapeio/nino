<?php return [
	'name' => 'Feature — Callout',
	'description' => 'One focused promise with an icon, concise copy and primary action.',
	'category' => 'CTA',
	'tags' => [ 'feature', 'callout', 'icon', 'single', 'conversion' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'text', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'narrow', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'action' => [ 'none', 'link', 'button' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'compact', 'default', 'generous' ],
	],
];
