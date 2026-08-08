<?php return [
	'category' 	=> 'Data',
	'tag' 			=> 'wrap',
	'name' 			=> 'Pricing Item',
	'match' 		=> [
		'tag' 			=> 'div',
		'classes' 	=> [ 'ui-pricing-item' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing', 'vpa' ],
	'settings' 	=> [
		'featured' => [ 'label' => 'Featured', 'type' => 'classtoggle', 'class' => 'ui-pricing-item--featured' ],
	],
];
