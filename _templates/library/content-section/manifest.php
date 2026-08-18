<?php return [
	'name' => 'Content — Flexible section',
	'description' => 'A flexible heading, body and action section for editorial page content.',
	'category' => 'Content',
	'tags' => [ 'content', 'text', 'intro', 'heading', 'cta', 'template', 'flexible' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'default',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'default' => [ 'label' => 'Heading, body and action', 'template' => 'section.tpl' ],
	],
	'areas' => [
		'heading' => [
			'label' => 'Heading',
			'help' => 'The optional introduction above the main content.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'ui-grid-100 ui-mb-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'ui-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'ui-text-center' ],
				'right' => [ 'label' => 'Right', 'class' => 'ui-text-right' ],
			],
			'recommend' => [ 'style' => 'left', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'ui-section-title' ] ],
		],
		'body' => [
			'label' => 'Body',
			'help' => 'Ordered text, images or reusable templates.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description', 'text', 'image', 'template' ],
			'container' => [ 'class' => 'ui-grid-100' ],
			'recommend' => [ 'components' => [
				[ 'id' => 'content', 'type' => 'text', 'style' => 'loud', 'bindings' => [ 'text' => 'content' ] ],
			] ],
		],
		'action' => [
			'label' => 'Action',
			'help' => 'Optional calls to action below the content.',
			'source' => 'single',
			'allowed' => [ 'description', 'button', 'template' ],
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
