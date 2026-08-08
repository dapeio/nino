<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'text',
	'name' 			=> 'Icon',
	'match' 		=> [
		'tags' 			=> [ 'span', 'i' ],
		'classes' 	=> [ 'ui-icon' ],
	],
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Symbol', 'type' => 'text' ],
		'small' => [ 'label' => 'Small', 'type' => 'classtoggle', 'class' => 'small' ],
		'label' => [ 'label' => 'ARIA label', 'type' => 'attr', 'attr' => 'aria-label' ],
	],
];
