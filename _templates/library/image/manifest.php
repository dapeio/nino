<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'image',
	'name' 			=> 'Image',
	'match' 		=> [ 'tag' => 'img' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'src' 		=> [ 'label' => 'Source', 	'type' => 'attr', 'attr' => 'src' ],
		'alt' 		=> [ 'label' => 'Alt text', 'type' => 'attr', 'attr' => 'alt' ],
		'loading' => [
			'label' 	=> 'Loading',
			'type' 		=> 'attr',
			'attr' 		=> 'loading',
			'values' 	=> [ '', 'lazy', 'eager' ],
		],
	],
];
