<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'wrap',
	'name' 			=> 'List',
	'match' 		=> [ 'tags' => [ 'ul', 'ol' ] ],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'tag' => [ 'label' => 'Type', 'type' => 'tag', 'values' => [ 'ul', 'ol' ] ],
	],
];
