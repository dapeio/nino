<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'wrap',
	'name' 			=> 'Video Embed',
	'match' 		=> [
		'tag' 			=> 'div',
		'classes' 	=> [ 'ui-video' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'ratio43' => [ 'label' => '4:3 ratio', 'type' => 'classtoggle', 'class' => 'ui-video--4-3' ],
	],
];
