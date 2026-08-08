<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'image',
	'name' 			=> 'Article Image',
	'match' 		=> [
		'tag' 			=> 'img',
		'classes' 	=> [ 'ui-article-img' ],
	],
	'palette' 	=> false,
	'settings' 	=> [
		'src' 		=> [ 'label' => 'Source', 'type' => 'attr', 'attr' => 'src' ],
		'alt' 		=> [ 'label' => 'Alt text', 'type' => 'attr', 'attr' => 'alt' ],
		'loading' => [ 'label' => 'Loading', 'type' => 'attr', 'attr' => 'loading', 'values' => [ '', 'lazy', 'eager' ] ],
		'maxheight' => [ 'label' => 'Max height', 'type' => 'classtoggle', 'class' => 'ui-article-img--maxheight' ],
	],
];
