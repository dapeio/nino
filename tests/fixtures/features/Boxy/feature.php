<?php
// A feature whose manifest still carries the keys 'components' and 'stacks': the feature is usable, the key is
// not read, and the Features panel says so once (tests/features-smoke.php)
return [
	'key'					=> 'boxy',
	'name'				=> 'Boxy',
	'version'			=> '0.1.0',
	'nino'				=> '^2.0',
	// A stylesheet of its own: tests/features-smoke.php lists the feature after Sample and reads where the file lands
	'assets'			=> [ '/features/Boxy/assets/boxy.css' ],
	'components'	=> [
		'boxy-link'		=> [
			'label'				=> 'Link',
			'source'			=> 'text',
		],
	],
	'stacks'			=> [
		'boxy-rows'		=> [ 'label' => 'Rows' ],
	],
];
