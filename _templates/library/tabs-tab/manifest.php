<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'link',
	'name' 			=> 'Tab Button',
	'match' 		=> [
		'tags' 			=> [ 'button', 'a' ],
		'classes' 	=> [ 'js-tabs-tab' ],
	],
	'palette' 	=> false,
	'settings' 	=> [
		'text' 		=> [ 'label' => 'Label', 'type' => 'text' ],
		'target' 	=> [ 'label' => 'Panel ID', 'type' => 'attr', 'attr' => 'data-tabs-target' ],
		'active' 	=> [ 'label' => 'Initially active', 'type' => 'classtoggle', 'class' => 'active' ],
	],
];
