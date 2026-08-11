<?php return [
	'name' => 'FAQ — Accordion',
	'description' => 'Repeatable questions and answers in a focused accordion column.',
	'category' => 'Content',
	'tags' => [ 'faq', 'accordion', 'questions', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'center', 'content' => 'accordion', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'narrow', 'limit' => 12,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'narrow', 'wide' ],
	],
];
