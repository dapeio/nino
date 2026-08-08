<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'media',
	'name' 			=> 'Embedded Media',
	'match' 		=> [ 'tags' => [ 'iframe', 'video' ] ],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'src' 			=> [ 'label' => 'Source', 'type' => 'attr', 'attr' => 'src' ],
		'title' 		=> [ 'label' => 'Title', 'type' => 'attr', 'attr' => 'title' ],
		'loading' 	=> [ 'label' => 'Loading', 'type' => 'attr', 'attr' => 'loading', 'values' => [ '', 'lazy', 'eager' ] ],
		'controls' 	=> [ 'label' => 'Controls', 'type' => 'attrtoggle', 'attr' => 'controls' ],
		'fullscreen' => [ 'label' => 'Allow fullscreen', 'type' => 'attrtoggle', 'attr' => 'allowfullscreen' ],
	],
];
