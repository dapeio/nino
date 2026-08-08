<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'title',
	'name' 			=> 'Heading',
	// No required class at all - this is the fallback any plain heading
	// falls into. 'not' keeps it from swallowing the styled ones, which
	// carry their own block and would otherwise both match (the more
	// specific block wins on score, but an explicit exclusion documents
	// the intent rather than relying on the tie-break)
	'match' 		=> [
		'tags' 	=> [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		'not' 	=> [ 'ui-atf-title', 'ui-section-title', 'ui-article-title', 'ui-pricing-title', 'ui-footer-title' ],
	],
	'use' 			=> [ 'align', 'spacing' ],
	'settings' 	=> [
		'text' 	=> [ 'label' => 'Text', 	'type' => 'text' ],
		'level' => [ 'label' => 'Level', 	'type' => 'tag', 'values' => [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] ],
	],
];
