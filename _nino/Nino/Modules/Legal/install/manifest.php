<?php return [
	// One of \Nino\Install\Setup::ALWAYS_MODULES: applied on every setup run,
	// never a picker choice. After setup nothing applies it again - a language
	// added later gets its share from \Nino\Modules\Legal::addLocale()
	'key'					=> 'legal',
	'label' 			=> 'Legal texts',
	'moduleClass' => '\\Nino\\Modules\\Legal',
	'templates' 	=> [
		'page-legal-imprint.tpl',
		'page-legal-privacy.tpl',
	],
	// Added to the types, never replacing one, whatever the wizard's overwrite
	// says - the elements are the editors' content (see \Nino\Elements::seed())
	'elements' 		=> [
		'legal' 		=> 'elements/legal.php',
		'privacy' 	=> 'elements/privacy.php',
	],
	// A menu the project does not have yet, with its first entries by
	// Element-URI - read by the setup wizard only, see Setup::apiApply()
	'navs' 				=> [
		'legal' => [ '/legal/imprint', '/legal/privacy' ],
	],
	'config' 			=> [
		'/nino/legal/paths' => [
			'imprint' => [ 'de_DE' => '/impressum', 'en_US' => '/imprint' ],
			'privacy' => [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ],
		],
	],
	// The Elements panel's own words for the two types - the workbench's, not
	// the site's, so the Text panel does not offer them
	'blacklist' 	=> [
		'/_admin/elements/field/legal/title',
		'/_admin/elements/field/legal/text',
		'/_admin/elements/field/legal/order',
		'/_admin/elements/field/legal/hidden',
		'/_admin/elements/field/privacy/title',
		'/_admin/elements/field/privacy/text',
		'/_admin/elements/field/privacy/order',
		'/_admin/elements/field/privacy/hidden',
		'/_admin/elements/type/legal/hint',
		'/_admin/elements/type/privacy/hint',
	],
];
