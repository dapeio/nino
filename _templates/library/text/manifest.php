<?php return [
	'category' 	=> 'Content',
	'tag' 			=> 'text',
	'name' 			=> 'Paragraph',
	'match' 		=> [
		'tag' 	=> 'p',
		'not' 	=> [ 'ui-atf-subtitle', 'ui-section-subtitle', 'ui-article-subtitle', 'ui-article-descr', 'ui-article-price', 'ui-form-message', 'ui-pricing-price' ],
	],
	'use' 			=> [ 'align', 'spacing' ],
	'settings' 	=> [
		'text' => [ 'label' => 'Text', 'type' => 'text' ],
	],
];
