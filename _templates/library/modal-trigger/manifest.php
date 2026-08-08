<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'link',
	'name' 			=> 'Modal Trigger',
	'match' 		=> [
		'tags' 			=> [ 'button', 'a' ],
		'classes' 	=> [ 'js-modal-trigger' ],
	],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'text' 		=> [ 'label' => 'Label', 'type' => 'text' ],
		'target' 	=> [ 'label' => 'Modal ID', 'type' => 'attr', 'attr' => 'data-modal-target' ],
		'label' 		=> [ 'label' => 'ARIA label', 'type' => 'attr', 'attr' => 'aria-label' ],
	],
];
