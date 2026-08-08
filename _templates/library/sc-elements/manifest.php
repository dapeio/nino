<?php return [
	'category' 	=> 'Shortcodes',
	'tag' 			=> 'loop',
	'name' 			=> 'Elements Loop',
	'match' 		=> [
		'tag' 	=> 'nino-sc',
		'attrs' => [ 'name' => 'elements' ],
	],
	'children' 	=> [ '*' ],
	'settings' 	=> [
		// The whole shortcode argument string as written, eg.
		// '/services limit="3"' - deliberately one field rather than a
		// parsed uri/limit/query/locale form: the argument syntax is the
		// kernel's (see Html::_doShortcode()), and re-implementing its
		// quoting rules here would be a second parser to keep in sync
		'args' => [ 'label' => 'Arguments', 'type' => 'attr', 'attr' => 'args' ],
	],
];
