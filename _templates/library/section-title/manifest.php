<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'title',
	'name' 			=> 'Section Title',
	'match' 		=> [
		'tags' 			=> [ 'h2', 'h3', 'h4' ],
		'classes' 	=> [ 'ui-section-title' ],
	],
	'use' 			=> [ 'align', 'spacing' ],
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Text', 	'type' => 'text' ],
		'level' => [ 'label' => 'Level', 	'type' => 'tag', 'values' => [ 'h2', 'h3', 'h4' ] ],
	],
];
