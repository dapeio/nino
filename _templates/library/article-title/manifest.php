<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'title',
	'name' 			=> 'Article Title',
	'match' 		=> [
		'tags' 			=> [ 'h3', 'h4', 'h5' ],
		'classes' 	=> [ 'ui-article-title' ],
	],
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Text', 	'type' => 'text' ],
		'level' => [ 'label' => 'Level', 	'type' => 'tag', 'values' => [ 'h3', 'h4', 'h5' ] ],
	],
];
