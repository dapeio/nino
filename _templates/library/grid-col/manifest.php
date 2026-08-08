<?php return [
	'category' 	=> 'Grid',
	'tag' 			=> 'wrap',
	'name' 			=> 'Grid Column',
	'match' 		=> [
		'tag' 				=> 'div',
		'classesAny' 	=> [ 'ui-grid-25', 'ui-grid-33', 'ui-grid-50', 'ui-grid-66', 'ui-grid-75', 'ui-grid-100' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing', 'align', 'vpa' ],
	'settings' 	=> [
		// The one setting the canvas actually draws rather than just lists -
		// see docs/_templates.md's "What the canvas shows" section
		'width' => [
			'label' 			=> 'Width',
			'type' 				=> 'classenum',
			'pattern' 		=> 'ui-grid-%s',
			'bpPattern' 	=> 'ui-grid-%b-%s',
			'breakpoints' => [ 's', 'm', 'l', 'xl' ],
			'values' 			=> [ '25', '33', '50', '66', '75', '100' ],
			'default' 		=> '100',
		],
		'imgcover' => [ 'label' => 'Image cover', 'type' => 'classtoggle', 'class' => 'ui-img-cover' ],
	],
];
