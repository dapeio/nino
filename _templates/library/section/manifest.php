<?php return [
	'category' 	=> 'Sections',
	'tag' 			=> 'wrap',
	'name' 			=> 'Section',
	'match' 		=> [
		'tag' 			=> 'section',
		'classes' 	=> [ 'ui-section' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing', 'align', 'vpa' ],
	'settings' 	=> [
		'variant' => [
			'label' 	=> 'Colour',
			'type' 		=> 'classgroup',
			'options' => [
				'' 									=> 'Default',
				'ui-section--alt' 	=> 'Alt',
				'ui-section--dark' 	=> 'Dark',
				'ui-section--black' => 'Black',
				'ui-section--primary' => 'Primary',
			],
		],
		'fullwidth' => [ 'label' => 'Full width', 'type' => 'classtoggle', 'class' => 'ui-section--fullwidth' ],
		'border' 		=> [ 'label' => 'Border', 'type' => 'classenum', 'pattern' => 'ui-section--border-%s', 'values' => [ '1', '2', '3' ] ],
	],
];
