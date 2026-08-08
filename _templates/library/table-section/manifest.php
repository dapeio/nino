<?php return [
	'category' 	=> 'Data',
	'tag' 			=> 'wrap',
	'name' 			=> 'Table Section',
	'match' 		=> [ 'tags' => [ 'thead', 'tbody', 'tfoot' ] ],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'tag' => [ 'label' => 'Type', 'type' => 'tag', 'values' => [ 'thead', 'tbody', 'tfoot' ] ],
	],
];
