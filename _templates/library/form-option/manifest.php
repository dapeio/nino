<?php return [
	'category' 	=> 'Forms',
	'tag' 			=> 'text',
	'name' 			=> 'Select Option',
	'match' 		=> [ 'tag' => 'option' ],
	'palette' 	=> false,
	'settings' 	=> [
		'text' 		=> [ 'label' => 'Label', 'type' => 'text' ],
		'value' 		=> [ 'label' => 'Value', 'type' => 'attr', 'attr' => 'value' ],
		'selected' => [ 'label' => 'Selected', 'type' => 'attrtoggle', 'attr' => 'selected' ],
		'disabled' => [ 'label' => 'Disabled', 'type' => 'attrtoggle', 'attr' => 'disabled' ],
	],
];
