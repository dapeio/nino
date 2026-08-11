<?php return [
	'name' => 'Quote — Parallax banner',
	'description' => 'A full-width statement over a moving image with a calm attribution line.',
	'category' => 'Social proof',
	'tags' => [ 'quote', 'testimonial', 'parallax', 'banner', 'image' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'black', 'background' => 'parallax', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'none', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'generous',
		'margin' => 'none', 'border' => 'none', 'layout' => 'auto', 'limit' => 1,
	],
	'allow' => [
		'background' => [ 'image-cover', 'image-static', 'parallax' ],
		'action' => [ 'none', 'link', 'button' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
