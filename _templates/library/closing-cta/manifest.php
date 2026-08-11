<?php return [
	'name' => 'Closing CTA',
	'description' => 'A strong final title, subtitle and button before the footer.',
	'category' => 'CTA',
	'tags' => [ 'cta', 'closing', 'button', 'conversion' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'black', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'none', 'contentStyle' => 'auto',
		'action' => 'button', 'motion' => 'page', 'padding' => 'generous',
		'margin' => 'none', 'border' => 'none', 'layout' => 'auto', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'background' => [ 'none', 'image-cover', 'image-static', 'parallax' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'action' => [ 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'default', 'generous' ],
	],
];
