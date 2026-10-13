<?php
// A feature whose component cannot be rendered: its class has neither
// componentChime() nor doShortcode() - tests/features-smoke.php reads the
// warning the kernel raises and that the component is not registered
return [
	'key'					=> 'mute',
	'name'				=> 'Mute',
	'version'			=> '0.1.0',
	'components'	=> [
		'chime'		=> [ 'label' => 'Chime', 'source' => 'none' ],
	],
];
