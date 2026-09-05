<?php return [
	'name' => 'FAQ — Static accordion',
	'description' => 'Questions and answers as native details elements, no JavaScript. Insert it, then write the entries in HTML+.',
	'category' => 'Static',
	'tags' => [ 'faq', 'accordion', 'questions', 'details', 'static', 'html', 'support' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'demo',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'demo' => [ 'label' => 'Three demo questions', 'template' => 'section-demo.tpl' ],
		'elements' => [ 'label' => 'Elements loop', 'template' => 'section-elements.tpl' ],
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
