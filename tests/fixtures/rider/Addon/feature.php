<?php
// tests/fixtures/rider/Addon/feature.php - the second test feature of the kernel's suites: a feature that stands on the first. It
// requires the test feature Kit, calls its components in its demo and in its block, and has one component of its own.
return [
	'key'					=> 'addon',
	'name'				=> [ 'en_US' => 'Addon', 'de_DE' => 'Zusatz' ],
	'description'	=> [ 'en_US' => 'A note below a title: a feature that is built on the test feature Kit.', 'de_DE' => 'Eine Anmerkung unter einem Titel: ein Feature, das auf dem Test-Feature Kit aufbaut.' ],
	'category'		=> 'content',
	'version'			=> '1.0.0',
	'requires'		=> [ 'kit' ],
];
