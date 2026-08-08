<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'text',
	'name' 			=> 'Stat Counter',
	'match' 		=> [
		'tags' 			=> [ 'span', 'strong', 'div' ],
		'classes' 	=> [ 'js-stat-counter' ],
	],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'to' 			=> [ 'label' => 'Count to', 'type' => 'attr', 'attr' => 'data-stat-counter-to' ],
		'suffix' 	=> [ 'label' => 'Suffix', 'type' => 'attr', 'attr' => 'data-stat-counter-suffix' ],
		'duration' => [ 'label' => 'Duration (ms)', 'type' => 'attr', 'attr' => 'data-stat-counter-duration' ],
		'text' 		=> [ 'label' => 'Fallback text', 'type' => 'text' ],
	],
];
