<?php return [
	'category' 	=> 'Data',
	'tag' 			=> 'text',
	'name' 			=> 'Table Cell',
	'match' 		=> [ 'tags' => [ 'th', 'td' ] ],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Text', 'type' => 'text' ],
		'tag' 		=> [ 'label' => 'Type', 'type' => 'tag', 'values' => [ 'th', 'td' ] ],
		'colspan' => [ 'label' => 'Column span', 'type' => 'attr', 'attr' => 'colspan' ],
		'rowspan' => [ 'label' => 'Row span', 'type' => 'attr', 'attr' => 'rowspan' ],
	],
];
