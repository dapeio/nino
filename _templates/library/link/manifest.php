<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'link',
	'name' 			=> 'Link',
	'match' 		=> [ 'tag' => 'a' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'text' 		=> [ 'label' => 'Label', 'type' => 'text' ],
		'href' 		=> [ 'label' => 'Link', 'type' => 'attr', 'attr' => 'href' ],
		'target' 	=> [ 'label' => 'Target', 'type' => 'attr', 'attr' => 'target', 'values' => [ '', '_self', '_blank' ] ],
		'label' 		=> [ 'label' => 'ARIA label', 'type' => 'attr', 'attr' => 'aria-label' ],
	],
];
