<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'wrap',
	'name' 			=> 'Slider',
	'match' 		=> [
		'tag' 			=> 'div',
		'classes' 	=> [ 'js-slider' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'width' 	=> [ 'label' => 'Slide width', 'type' => 'attr', 'attr' => 'data-slider-width' ],
		'min' 		=> [ 'label' => 'Min width', 'type' => 'attr', 'attr' => 'data-slider-min' ],
		'position' => [ 'label' => 'Start slide', 'type' => 'attr', 'attr' => 'data-slider-pos' ],
		'touch' 	=> [ 'label' => 'Touch', 'type' => 'attr', 'attr' => 'data-slider-touch', 'values' => [ '', 'true', 'false' ] ],
	],
];
