<?php return [
	'name' => 'List — Static block',
	'description' => 'A checked or numbered list between an editable intro and outro. Insert it, then shape the items in HTML+.',
	'category' => 'Static',
	'tags' => [ 'list', 'checklist', 'numbered', 'static', 'html', 'features', 'steps' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'check-demo',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'check-demo' => [ 'label' => 'Checked list · demo items', 'template' => 'section-check-demo.tpl' ],
		'check-elements' => [ 'label' => 'Checked list · elements loop', 'template' => 'section-check-elements.tpl' ],
		'numbered-demo' => [ 'label' => 'Numbered list · demo items', 'template' => 'section-numbered-demo.tpl' ],
		'numbered-elements' => [ 'label' => 'Numbered list · elements loop', 'template' => 'section-numbered-elements.tpl' ],
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
