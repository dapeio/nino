<?php
// The reference feature of tests/features-smoke.php: every settings type,
// a requirement, an install unit, an upgrade hook - the complete contract
// docs/features.md describes, in one directory
return [
	'key'					=> 'sample',
	'name'				=> [ 'en_US' => 'Sample feature', 'de_DE' => 'Beispiel-Feature' ],
	'description'	=> [ 'en_US' => 'Exercises the whole feature contract.', 'de_DE' => 'Prüft den ganzen Feature-Vertrag.' ],
	'version'			=> '1.2.0',
	'nino'				=> '^1.0',
	'php'					=> [ 'ext' => [ 'json' ] ],
	'requires'		=> [ 'helper' ],
	'data'				=> [ '/data/sample.php' ],
	'settings'		=> [
		'enabled'	=> [ 'type' => 'bool', 'label' => [ 'en_US' => 'Enabled', 'de_DE' => 'Aktiv' ], 'default' => true ],
		'limit'		=> [ 'type' => 'int', 'label' => 'Limit', 'hint' => 'Items per page', 'min' => 1, 'max' => 50, 'unit' => 'items', 'default' => 10 ],
		'title'		=> [ 'type' => 'string', 'label' => 'Title', 'maxlength' => 40, 'required' => true, 'default' => 'Hello' ],
		'slug'		=> [ 'type' => 'string', 'label' => 'Slug', 'pattern' => '/^[a-z-]+$/' ],
		'notes'		=> [ 'type' => 'text', 'label' => 'Notes' ],
		'contact'	=> [ 'type' => 'email', 'label' => 'Contact' ],
		'site'		=> [ 'type' => 'url', 'label' => 'Site' ],
		'mode'		=> [ 'type' => 'select', 'label' => 'Mode', 'options' => [ 'fast' => 'Fast', 'safe' => [ 'en_US' => 'Safe', 'de_DE' => 'Sicher' ] ], 'default' => 'fast' ],
		'apiKey'	=> [ 'type' => 'secret', 'label' => 'API key' ],
		'hosts'		=> [ 'type' => 'lines', 'label' => 'Hosts' ],
	],
];
