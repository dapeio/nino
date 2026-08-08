<?php return [
	'category' 	=> 'Shortcodes',
	'tag' 			=> 'meta',
	'name' 			=> 'CSRF Field',
	'match' 		=> [
		'tag' 	=> 'nino-sc',
		'attrs' => [ 'name' => 'csrf' ],
	],
	'actions' 	=> [ 'remove', 'moveup', 'movedown' ],
	'settings' 	=> [],
];
