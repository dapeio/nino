<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'title',
	'name' 			=> 'ATF Title',
	'match' 		=> [
		'tags' 			=> [ 'h1', 'h2', 'h3' ],
		'classes' 	=> [ 'ui-atf-title' ],
	],
	'use' 			=> [ 'align', 'spacing' ],
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Text', 'type' => 'text' ],
		'level' => [ 'label' => 'Level', 'type' => 'tag', 'values' => [ 'h1', 'h2', 'h3' ] ],
	],
];
