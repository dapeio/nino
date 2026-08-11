<?php return [
	'name' => 'Data — Table',
	'description' => 'A responsive three-column table populated from an Elements collection.',
	'category' => 'Content',
	'tags' => [ 'table', 'data', 'rows', 'comparison', 'elements' ],
	'version' => 1,
	'defaults' => [
		'surface' => 'default', 'background' => 'none', 'header' => 'title-subtitle',
		'align' => 'left', 'content' => 'data-table', 'contentStyle' => 'auto',
		'action' => 'none', 'motion' => 'page', 'padding' => 'default',
		'margin' => 'none', 'border' => 'none', 'layout' => 'striped-bordered', 'limit' => 12,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'none', 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'action' => [ 'none', 'link', 'button', 'dual-buttons' ],
		'motion' => [ 'page', 'on', 'off' ],
		'layout' => [ 'plain', 'striped', 'bordered', 'striped-bordered' ],
	],
];
