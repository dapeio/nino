<?php return [
	'category' 	=> 'Shortcodes',
	'tag' 			=> 'include',
	'name' 			=> 'Template Include',
	'match' 		=> [
		'tag' 	=> 'nino-sc',
		'attrs' => [ 'name' => 'template' ],
	],
	'settings' 	=> [
		'args' => [ 'label' => 'Template path', 'type' => 'attr', 'attr' => 'args' ],
	],
];
