<?php
// A page whose file name has no category, read by the tests of the kernel like a unit of the library: it carries
// no key of its own and reads the keys of /template/common and of a feature, never delivered, never applied by the
// wizard or a panel
return [
	'label' 		=> 'Unit without a category',
	// The contact form's words are the ones the unit of the module Form ships
	'requiresModules' => [ 'forms' ],
	'routes' 		=> [
		'GET://.uncategorized' => [ 'uri' => '/.uncategorized', 'body' => '[template /templates/.uncategorized]' ],
	],
	'templates' => [ '.uncategorized.tpl' ],
	'suggest' => [
		'uri' 				=> '/.uncategorized',
		'name' 				=> [ 'en_US' => 'Catalogue', 'de_DE' => 'Katalog' ],
		'title' 			=> [ 'en_US' => 'Catalogue', 'de_DE' => 'Katalog' ],
		'description' => [ 'en_US' => 'A page that no template category belongs to.', 'de_DE' => 'Eine Seite, zu der keine Vorlagen-Kategorie gehört.' ],
	],
];
