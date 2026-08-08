<?php return [
	'category' 	=> 'Forms',
	'tag' 			=> 'meta',
	'name' 			=> 'Form Input',
	'match' 		=> [
		'tag' 			=> 'input',
		'classes' 	=> [ 'ui-form-input' ],
	],
	'settings' 	=> [
		'type' 		=> [ 'label' => 'Type', 'type' => 'attr', 'attr' => 'type', 'values' => [ 'text', 'email', 'tel', 'url', 'number', 'date', 'password' ] ],
		'name' 		=> [ 'label' => 'Name', 'type' => 'attr', 'attr' => 'name' ],
		'id' 			=> [ 'label' => 'ID', 'type' => 'attr', 'attr' => 'id' ],
		'placeholder' => [ 'label' => 'Placeholder', 'type' => 'attr', 'attr' => 'placeholder' ],
		'required' => [ 'label' => 'Required', 'type' => 'attrtoggle', 'attr' => 'required' ],
	],
];
