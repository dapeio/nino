<?php return [
	'category' 	=> 'Grid',
	'tag' 			=> 'wrap',
	'name' 			=> 'Grid Row',
	'match' 		=> [
		'tag' 			=> 'div',
		'classes' 	=> [ 'ui-grid-row' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'valign' => [
			'label' 	=> 'Vertical align',
			'type' 		=> 'classgroup',
			'options' => [
				'' 								=> 'Default',
				'ui-grid-top' 		=> 'Top',
				'ui-grid-middle' 	=> 'Middle',
				'ui-grid-bottom' 	=> 'Bottom',
			],
		],
		'center' 		=> [ 'label' => 'Center columns', 	'type' => 'classtoggle', 'class' => 'ui-grid-center' ],
		'fullwidth' => [ 'label' => 'Full width', 			'type' => 'classtoggle', 'class' => 'ui-grid--fullwidth' ],
	],
];
