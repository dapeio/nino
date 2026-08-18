<?php return [
	'name' => 'Table — Static block',
	'description' => 'A real table between an editable intro and outro. Insert it, then shape the rows in HTML+.',
	'category' => 'Static',
	'tags' => [ 'table', 'rows', 'static', 'html', 'prices', 'opening hours', 'specs' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'default-demo',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'default-demo' => [ 'label' => 'Plain table · demo rows', 'template' => 'section-default-demo.tpl' ],
		'default-elements' => [ 'label' => 'Plain table · elements loop', 'template' => 'section-default-elements.tpl' ],
		'striped-demo' => [ 'label' => 'Striped table · demo rows', 'template' => 'section-striped-demo.tpl' ],
		'striped-elements' => [ 'label' => 'Striped table · elements loop', 'template' => 'section-striped-elements.tpl' ],
	],
	'areas' => [
		'intro' => [
			'label' => 'Intro',
			'help' => 'The title and supporting line above the block. Ordinary textfills, editable without touching the source.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'ui-grid-100 ui-mb-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'ui-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'ui-text-center' ],
			],
			'recommend' => [ 'style' => 'left', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'ui-section-title' ], 'subtitle' => [ 'class' => 'ui-section-subtitle' ] ],
		],
		'outro' => [
			'label' => 'Outro',
			'help' => 'Deliberately empty. Add a button, a note or a reusable template here when the block needs a closing line.',
			'source' => 'single',
			'allowed' => [ 'button', 'description', 'text', 'template' ],
			'container' => [ 'class' => 'ui-grid-100 ui-mt-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'ui-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'ui-text-center' ],
				'right' => [ 'label' => 'Right', 'class' => 'ui-text-right' ],
			],
			'recommend' => [ 'style' => 'left', 'components' => [] ],
		],
	],
];
