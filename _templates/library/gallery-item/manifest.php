<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'wrap',
	'name' 			=> 'Gallery Item',
	'match' 		=> [
		'tags' 			=> [ 'div', 'li' ],
		'classes' 	=> [ 'ui-gallery-item' ],
	],
	'children' 	=> [ '*' ],
	'settings' 	=> [
		'wide' => [ 'label' => 'Wide', 'type' => 'classtoggle', 'class' => 'ui-gallery-item--wide' ],
		'tall' => [ 'label' => 'Tall', 'type' => 'classtoggle', 'class' => 'ui-gallery-item--tall' ],
	],
];
