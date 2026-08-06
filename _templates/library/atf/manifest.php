<?php return [
	'category' 	=> 'Sections',
	'tag' 			=> 'wrap',
	'name' 			=> 'ATF / Hero',
	'match' 		=> [
		'tag' 			=> 'section',
		'classes' 	=> [ 'ui-atf' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'align' ],
	'settings' 	=> [
		'fullscreen' 	=> [ 'label' => 'Fullscreen (css)', 'type' => 'classtoggle', 'class' => 'ui-atf--fullscreen' ],
		'fullwidth' 	=> [ 'label' => 'Full width', 'type' => 'classtoggle', 'class' => 'ui-section--fullwidth' ],
		'variant' 		=> [
			'label' 	=> 'Colour',
			'type' 		=> 'classgroup',
			'options' => [
				'' 										=> 'Default',
				'ui-section--dark' 		=> 'Dark',
				'ui-section--black' 	=> 'Black',
				'ui-section--primary' => 'Primary',
			],
		],
		'cover' 			=> [ 'label' => 'Image cover (js)', 	'type' => 'classtoggle', 'class' => 'js-cover' ],
		'parallax' 		=> [ 'label' => 'Parallax (js)', 		'type' => 'classtoggle', 'class' => 'js-parallex' ],
		'dim' 				=> [ 'label' => 'Dim scrim', 				'type' => 'classtoggle', 'class' => 'js-cover--dim' ],
		'coverHeight' => [ 'label' => 'Cover height (%)', 'type' => 'attr', 'attr' => 'data-cover-height' ],
	],
];
