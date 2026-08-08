<?php return [
	'category' 	=> 'Data',
	'tag' 			=> 'wrap',
	'name' 			=> 'Table Grid',
	'match' 		=> [
		'tag' 			=> 'table',
		'classes' 	=> [ 'ui-table' ],
	],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'striped' 	=> [ 'label' => 'Striped', 'type' => 'classtoggle', 'class' => 'ui-table--striped' ],
		'bordered' => [ 'label' => 'Bordered', 'type' => 'classtoggle', 'class' => 'ui-table--bordered' ],
	],
];
