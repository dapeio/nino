<?php return [
	'name' => 'Newsletter — Signup form',
	'description' => 'The working double-opt-in signup form, with its own submit handler and honeypot.',
	'category' => 'Forms',
	'tags' => [ 'newsletter', 'signup', 'form', 'email', 'subscribe', 'static' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'centered',
		'frame' => [ 'background' => 'dark', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'centered' => [ 'label' => 'Form below the intro', 'template' => 'section-centered.tpl' ],
		'split' => [ 'label' => 'Intro beside the form', 'template' => 'section-split.tpl' ],
	],
	'areas' => [
		'intro' => [
			'label' => 'Intro',
			'help' => 'The title and supporting line above the block. Ordinary textfills, editable without touching the source.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'nino-grid-100 nino-mb-3' ],
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
			'recommend' => [ 'style' => 'center', 'components' => [] ],
		],
	],
];
