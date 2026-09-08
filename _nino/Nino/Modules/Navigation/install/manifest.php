<?php return [
	// One of \Nino\Install\Setup::ALWAYS_MODULES now: applied on every setup
	// run, never a picker choice
	'label' 			=> 'Navigation',
	'moduleClass' => '\\Nino\\Modules\\Navigation',
	// The menus the setup wizard and the Routes panel offer a checkbox for. Only an editing
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
