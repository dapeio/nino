<?php return [
	// One of \Nino\Install\Setup::ALWAYS_MODULES now: applied on every setup
	// run, never a picker choice
	'label' 			=> 'Locale Picker',
	'moduleClass' => '\\Nino\\Modules\\Localepicker',
	'templates' => [
		'html-footer-localepicker.tpl',
	],
];
