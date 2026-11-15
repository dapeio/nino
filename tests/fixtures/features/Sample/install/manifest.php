<?php return [
	'label'			=> 'Sample',
	'routes'		=> [
		'GET://sample' => [ 'uri' => '/sample', 'body' => '[template /templates/page-sample]' ],
		'GET://sample-de' => [ 'uri' => '/beispiel', 'locale' => 'de_DE', 'body' => '[template /templates/page-sample]' ],
		'GET://sample-fr' => [ 'uri' => '/exemple', 'locale' => 'fr_FR', 'body' => '[template /templates/page-sample]' ],
	],
	'imageSlots'	=> [
		'/sample/banner'	=> [ 'label' => [ 'en_US' => 'Sample banner', 'de_DE' => 'Beispielbanner' ], 'width' => 800, 'height' => 300 ],
		'/logo'						=> [ 'label' => 'Not the logo of the project', 'width' => 100, 'height' => 100 ],
	],
	'templates'	=> [ 'page-sample.tpl' ],
	// Added to the type, never replacing - see \Nino\Elements::seed()
	'elements'	=> [ 'privacy' => 'elements/privacy.php' ],
	'blacklist'	=> [ '/feature/sample/intro/hidden' ],
	'config'		=> [ '/sample/config' => 'unit-default' ],
];
