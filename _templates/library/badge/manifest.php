<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'text',
	'name' 			=> 'Badge',
	'match' 		=> [
		'tags' 			=> [ 'span', 'strong' ],
		'classes' 	=> [ 'ui-badge' ],
	],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'text' => [ 'label' => 'Text', 'type' => 'text' ],
		'variant' => [
			'label' 	=> 'Colour',
			'type' 		=> 'classgroup',
			'options' => [
				'' 							=> 'Default',
				'ui-badge--primary' => 'Primary',
				'ui-badge--success' => 'Success',
				'ui-badge--error' 	=> 'Error',
			],
		],
		'pill' => [ 'label' => 'Pill', 'type' => 'classtoggle', 'class' => 'ui-badge--pill' ],
	],
];
