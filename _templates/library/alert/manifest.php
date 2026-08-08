<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'text',
	'name' 			=> 'Alert',
	'match' 		=> [
		'tags' 			=> [ 'div', 'p' ],
		'classes' 	=> [ 'ui-alert' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'text' => [ 'label' => 'Text', 'type' => 'text' ],
		'variant' => [
			'label' 	=> 'Type',
			'type' 		=> 'classgroup',
			'options' => [
				'' 						=> 'Default',
				'ui-alert--info' 	=> 'Info',
				'ui-alert--success' => 'Success',
				'ui-alert--error' 	=> 'Error',
			],
		],
	],
];
