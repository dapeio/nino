<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'wrap',
	'name' 			=> 'Modal',
	'match' 		=> [
		'tag' 			=> 'dialog',
		'classes' 	=> [ 'js-modal' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'id' 		=> [ 'label' => 'Modal ID', 'type' => 'attr', 'attr' => 'id' ],
		'open' 	=> [ 'label' => 'Open by default', 'type' => 'attrtoggle', 'attr' => 'open' ],
		'variant' => [
			'label' 	=> 'Type',
			'type' 		=> 'classgroup',
			'options' => [
				'' 							=> 'Default',
				'js-modal--lightbox' => 'Lightbox',
				'js-modal--video' 	=> 'Video',
			],
		],
	],
];
