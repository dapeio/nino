<?php return [
	'category' 	=> 'Data',
	'tag' 			=> 'title',
	'name' 			=> 'Pricing Title',
	'match' 		=> [
		'tags' 			=> [ 'h3', 'h4', 'h5' ],
		'classes' 	=> [ 'ui-pricing-title' ],
	],
	'palette' 	=> false,
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Text', 'type' => 'text' ],
		'level' => [ 'label' => 'Level', 'type' => 'tag', 'values' => [ 'h3', 'h4', 'h5' ] ],
	],
];
