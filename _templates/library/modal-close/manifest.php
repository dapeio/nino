<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'link',
	'name' 			=> 'Modal Close',
	'match' 		=> [
		'tag' 			=> 'button',
		'classes' 	=> [ 'js-modal-close' ],
	],
	'palette' 	=> false,
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Label', 'type' => 'text' ],
		'label' 	=> [ 'label' => 'ARIA label', 'type' => 'attr', 'attr' => 'aria-label' ],
	],
];
