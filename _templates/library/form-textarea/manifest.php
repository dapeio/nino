<?php return [
	'category' 	=> 'Forms',
	'tag' 			=> 'text',
	'name' 			=> 'Form Textarea',
	'match' 		=> [
		'tag' 			=> 'textarea',
		'classes' 	=> [ 'ui-form-textarea' ],
	],
	'settings' 	=> [
		'name' 		=> [ 'label' => 'Name', 'type' => 'attr', 'attr' => 'name' ],
		'id' 			=> [ 'label' => 'ID', 'type' => 'attr', 'attr' => 'id' ],
		'placeholder' => [ 'label' => 'Placeholder', 'type' => 'attr', 'attr' => 'placeholder' ],
		'required' => [ 'label' => 'Required', 'type' => 'attrtoggle', 'attr' => 'required' ],
		'text' 		=> [ 'label' => 'Default text', 'type' => 'text' ],
	],
];
