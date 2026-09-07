<?php return [
	'label'			=> 'Sample',
	'routes'		=> [
		'GET://sample' => [ 'uri' => '/sample', 'body' => '[template /templates/page-sample]' ],
		'GET://sample-de' => [ 'uri' => '/beispiel', 'locale' => 'de_DE', 'body' => '[template /templates/page-sample]' ],
		'GET://sample-fr' => [ 'uri' => '/exemple', 'locale' => 'fr_FR', 'body' => '[template /templates/page-sample]' ],
	],
	'templates'	=> [ 'page-sample.tpl' ],
	'blacklist'	=> [ '/sample/hidden' ],
	'config'		=> [ '/sample/config' => 'unit-default' ],
];
