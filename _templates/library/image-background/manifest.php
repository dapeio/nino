<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'wrap',
	'name' 			=> 'Image Background',
	'match' 		=> [
		'tag' 			=> 'div',
		'classes' 	=> [ 'ui-img-background' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing', 'align' ],
	'settings' 	=> [
		'dim' => [ 'label' => 'Dim scrim', 'type' => 'classtoggle', 'class' => 'ui-img-background--dim' ],
	],
];
