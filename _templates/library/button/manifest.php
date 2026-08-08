<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'link',
	'name' 			=> 'Button',
	'match' 		=> [
		'tags' 			=> [ 'a', 'button' ],
		'classes' 	=> [ 'ui-btn' ],
		// Behavioural buttons have their own, more useful inspector. They
		// often carry ui-btn for styling too, so class-count scoring alone
		// would otherwise leave a tie decided by key order.
		'not' 			=> [ 'js-modal-trigger', 'js-toast-trigger', 'ui-video-poster' ],
	],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'text' 		=> [ 'label' => 'Label', 	'type' => 'text' ],
		'href' 		=> [ 'label' => 'Link', 	'type' => 'attr', 'attr' => 'href' ],
		'variant' => [
			'label' 	=> 'Style',
			'type' 		=> 'classgroup',
			'options' => [
				'' 									=> 'Default',
				'ui-btn--primary' 	=> 'Primary',
				'ui-btn--outline' 	=> 'Outline',
				'ui-btn--light' 		=> 'Light',
				'ui-btn--dark' 			=> 'Dark',
			],
		],
		'size' => [
			'label' 	=> 'Size',
			'type' 		=> 'classgroup',
			'options' => [
				'' 								=> 'Default',
				'ui-btn--small' 	=> 'Small',
				'ui-btn--big' 		=> 'Big',
			],
		],
	],
];
