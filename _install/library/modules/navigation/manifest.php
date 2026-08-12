<?php return [
	'label' 			=> 'Navigation',
	'moduleClass' => '\\Nino\\Modules\\Navigation',
	// The menus /_install and /_admin offer a checkbox for. Only an editing
	// affordance - [navigation nav="..."] renders whatever key a template
	// asks for, listed here or not - so a project is free to add, rename or
	// drop entries without breaking anything that already renders
	'config' => [
		'/nino/html/navs' => [ 'main', 'footer' ],
	],
	'templates' => [
		'html-header-nav.tpl',
		'html-footer-nav.tpl',
	],
];
