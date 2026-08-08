<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'link',
	'name' 			=> 'Toast Trigger',
	'match' 		=> [
		'tags' 			=> [ 'button', 'a' ],
		'classes' 	=> [ 'js-toast-trigger' ],
	],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'text' 		=> [ 'label' => 'Label', 'type' => 'text' ],
		'message' 	=> [ 'label' => 'Message', 'type' => 'attr', 'attr' => 'data-toast-message' ],
		'type' 		=> [ 'label' => 'Type', 'type' => 'attr', 'attr' => 'data-toast-type', 'values' => [ '', 'success', 'error' ] ],
	],
];
