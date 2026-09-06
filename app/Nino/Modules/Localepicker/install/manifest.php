<?php return [
	// Checked when the Setup step opens on a project that has decided
	// nothing yet. Only the opening position - once Setup has applied once,
	// the operator's own answer is the only one that counts
	'preset' 		=> true,
	'label' 			=> 'Locale Picker',
	'moduleClass' => '\\Nino\\Modules\\Localepicker',
	'templates' => [
		'html-footer-localepicker.tpl',
	],
];
