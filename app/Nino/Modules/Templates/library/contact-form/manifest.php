<?php return [
	'name' => 'Contact — Form',
	'description' => 'The project contact form, either centered on its own or beside the company details.',
	'category' => 'Forms',
	'tags' => [ 'contact', 'form', 'message', 'email', 'address', 'static' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'split',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'centered' => [ 'label' => 'Centered form', 'template' => 'section-centered.tpl' ],
		'split' => [ 'label' => 'Details beside the form', 'template' => 'section-split.tpl' ],
	],
	'areas' => [
		'intro' => [
			'label' => 'Intro',
			'help' => 'The title and supporting line above the block. Ordinary textfills, editable without touching the source.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'nino-grid-100 nino-mb-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
			],
			'recommend' => [ 'style' => 'left', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ], 'subtitle' => [ 'class' => 'nino-section-subtitle' ] ],
		],
		'outro' => [
			'label' => 'Outro',
			'help' => 'Deliberately empty. Add a button, a note or a reusable template here when the block needs a closing line.',
			'source' => 'single',
			'allowed' => [ 'button', 'description', 'text', 'template' ],
			'container' => [ 'class' => 'nino-grid-100 nino-mt-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
				'right' => [ 'label' => 'Right', 'class' => 'nino-text-right' ],
			],
			'recommend' => [ 'style' => 'left', 'components' => [] ],
		],
	],
];
