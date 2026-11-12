<?php
// A feature whose component draws a link of its own and says that this link may be the
// link of a linked card: its manifest is read, the component registered, and the schema
// the registry issues carries the key (tests/features-smoke.php)
return [
	'key'					=> 'boxy',
	'name'				=> 'Boxy',
	'version'			=> '0.1.0',
	'nino'				=> '^1.0',
	// A stylesheet of its own: tests/features-smoke.php lists the feature after Sample and reads where the file lands
	'assets'			=> [ '/features/Boxy/assets/boxy.css' ],
	'components'	=> [
		'boxy-link'		=> [
			'label'				=> 'Link',
			'source'			=> 'text',
			'in'					=> [ 'card' ],
			'stretched'		=> true,
		],
	],
];
