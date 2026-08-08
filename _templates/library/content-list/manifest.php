<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'wrap',
	'name' 			=> 'Content List',
	'match' 		=> [
		'tags' 			=> [ 'ul', 'ol' ],
		'classes' 	=> [ 'ui-list' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'tag' => [ 'label' => 'Type', 'type' => 'tag', 'values' => [ 'ul', 'ol' ] ],
		'variant' => [
			'label' 	=> 'Style',
			'type' 		=> 'classgroup',
			'options' => [
				'' 							=> 'Default',
				'ui-list--check' 	=> 'Check marks',
				'ui-list--numbered' => 'Numbered',
			],
		],
	],
];
