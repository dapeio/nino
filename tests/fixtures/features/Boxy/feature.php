<?php
// A feature whose holder names a component to stretch that does not do - the title is
// offered in a section, the box holds a card: its manifest is read as it is, since a
// manifest cannot know the other components, and the holder is registered without the
// key, with a warning (tests/features-smoke.php)
return [
	'key'					=> 'boxy',
	'name'				=> 'Boxy',
	'version'			=> '0.1.0',
	'nino'				=> '^1.0',
	'components'	=> [
		'boxy-title'	=> [
			'label'				=> 'Title',
			'source'			=> 'text',
			'link'				=> true,
			'in'					=> [ 'section' ],
		],
		'boxy-box'		=> [
			'label'				=> 'Box',
			'source'			=> 'content',
			'holds'				=> 'card',
			'stretches'		=> 'boxy-title',
		],
	],
];
