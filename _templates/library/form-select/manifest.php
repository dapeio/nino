<?php return [
	'category' 	=> 'Forms',
	'tag' 			=> 'wrap',
	'name' 			=> 'Form Select',
	'match' 		=> [
		'tag' 			=> 'select',
		'classes' 	=> [ 'ui-form-select' ],
	],
	'children' 	=> [ '*' ],
	'settings' 	=> [
		'name' 		=> [ 'label' => 'Name', 'type' => 'attr', 'attr' => 'name' ],
		'id' 			=> [ 'label' => 'ID', 'type' => 'attr', 'attr' => 'id' ],
		'required' => [ 'label' => 'Required', 'type' => 'attrtoggle', 'attr' => 'required' ],
		'multiple' => [ 'label' => 'Multiple', 'type' => 'attrtoggle', 'attr' => 'multiple' ],
	],
];
