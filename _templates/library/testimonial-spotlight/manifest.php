<?php return [
	'name' => 'Testimonial — Spotlight',
	'description' => 'One generous quote with portrait, author and role.',
	'category' => 'Social proof',
	'tags' => [ 'testimonial', 'quote', 'portrait', 'author', 'spotlight' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'alt', 'background' => 'none', 'header' => 'none',
		'align' => 'center', 'content' => 'testimonials', 'contentStyle' => 'default',
		'action' => 'none', 'motion' => 'page', 'padding' => 'generous',
		'margin' => 'none', 'border' => 'none', 'layout' => 'spotlight', 'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'default', 'generous' ],
	],
];
