<?php return [
	'category' 	=> 'Media',
	'tag' 			=> 'link',
	'name' 			=> 'Video Poster',
	'match' 		=> [
		'tags' 			=> [ 'button', 'a' ],
		'classes' 	=> [ 'ui-video-poster', 'js-modal-trigger' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'target' => [ 'label' => 'Modal ID', 'type' => 'attr', 'attr' => 'data-modal-target' ],
		'label' 	=> [ 'label' => 'ARIA label', 'type' => 'attr', 'attr' => 'aria-label' ],
	],
];
