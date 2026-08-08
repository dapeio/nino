<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'text',
	'name' 			=> 'Logo Item',
	'match' 		=> [
		'tags' 			=> [ 'div', 'li', 'span', 'a' ],
		'classes' 	=> [ 'ui-logos-item' ],
	],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'text' => [ 'label' => 'Text', 'type' => 'text' ],
	],
];
