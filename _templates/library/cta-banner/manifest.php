<?php return [
	'name' => 'Call to action — Banner',
	'description' => 'One clear next step: a short message and the buttons that follow from it.',
	'category' => 'Action',
	'tags' => [ 'cta', 'call to action', 'banner', 'contact', 'conversion', 'buttons' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'centered',
		'frame' => [ 'background' => 'dark', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'centered' => [ 'label' => 'Message above the actions', 'template' => 'section-centered.tpl' ],
		'split' => [ 'label' => 'Message beside the actions', 'template' => 'section-split.tpl' ],
	],
	'areas' => [
		'content' => [
			'label' => 'Message',
			'help' => 'The reason to act, in one or two lines.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'nino-grid-100' ],
			'styles' => [
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ], 'subtitle' => [ 'class' => 'nino-section-subtitle' ] ],
		],
		'actions' => [
			'label' => 'Actions',
			'help' => 'One primary button, optionally a quieter second one.',
			'source' => 'single',
			'allowed' => [ 'button', 'description', 'template' ],
			'container' => [ 'class' => 'nino-grid-100 nino-mt-3' ],
			'styles' => [
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
				'right' => [ 'label' => 'Right', 'class' => 'nino-text-right' ],
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [
				[ 'id' => 'action', 'type' => 'button', 'style' => 'primary', 'bindings' => [ 'label' => 'cta-label', 'href' => 'cta-uri' ] ],
				[ 'id' => 'secondary', 'type' => 'button', 'style' => 'outline', 'bindings' => [ 'label' => 'secondary-cta-label', 'href' => 'secondary-cta-uri' ] ],
			] ],
		],
	],
];
