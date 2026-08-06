<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'text',
	'name' 			=> 'Paragraph',
	'match' 		=> [
		'tag' 	=> 'p',
		'not' 	=> [ 'ui-atf-subtitle', 'ui-article-descr', 'ui-form-message', 'ui-pricing-price' ],
	],
	'use' 			=> [ 'align', 'spacing' ],
	'settings' 	=> [
		'text' => [ 'label' => 'Text', 'type' => 'text' ],
	],
];
