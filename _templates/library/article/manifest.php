<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'wrap',
	'name' 			=> 'Article',
	'match' 		=> [
		'tag' 			=> 'article',
		'classes' 	=> [ 'ui-article' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing', 'vpa' ],
	'settings' 	=> [
		'variant' => [
			'label' 	=> 'Style',
			'type' 		=> 'classgroup',
			'options' => [
				'' 											=> 'Default',
				'ui-article--alt' 			=> 'Alt',
				'ui-article--fullwidth' => 'Full width',
			],
		],
	],
];
