<?php return [
	'name' => 'Insert reusable template',
	'description' => 'Place one reusable .tpl include inside a normal managed section.',
	'category' => 'Structure',
	'tags' => [ 'template', 'include', 'shortcode', 'reusable', 'form', 'navigation' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'default',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'none', 'margin' => 'none' ],
	],
	'layouts' => [
		'default' => [ 'label' => 'Reusable template', 'template' => 'section.tpl' ],
	],
	'areas' => [
		'include' => [
			'label' => 'Template',
			'help' => 'Choose the reusable .tpl rendered at this position.',
			'source' => 'single',
			'allowed' => [ 'template' ],
			'maxComponents' => 1,
			'container' => [ 'class' => 'nino-grid-100' ],
			'recommend' => [ 'components' => [
				[ 'id' => 'template', 'type' => 'template', 'bindings' => [ 'path' => '' ] ],
			] ],
		],
	],
];
