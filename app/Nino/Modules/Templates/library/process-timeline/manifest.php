<?php return [
	'name' => 'Process — Numbered steps',
	'description' => 'An ordered process: connected, numbered steps with one explaining line each.',
	'category' => 'Content',
	'tags' => [ 'process', 'timeline', 'steps', 'how it works', 'onboarding', 'elements' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'timeline',
		'frame' => [ 'background' => 'default', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'timeline' => [ 'label' => 'Connected timeline', 'template' => 'section-timeline.tpl' ],
		'stacked' => [ 'label' => 'Stacked steps', 'template' => 'section-stacked.tpl' ],
	],
	'areas' => [
		'heading' => [
			'label' => 'Title area',
			'help' => 'The introduction above the steps.',
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
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ] ],
		],
		'steps' => [
			'label' => 'Steps',
			'help' => 'One entry per step. The step number is drawn by the ordered list itself, so it is not part of the content.',
			'source' => 'elements',
			'allowed' => [ 'title', 'description', 'text' ],
			'item' => [ 'tag' => 'li', 'class' => 'nino-timeline-step' ],
			'typeTitle' => 'Process steps',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 120 ],
				'description' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 400 ],
			],
			'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 4, 'query' => '' ],
			'render' => [
				'title' => [ 'tag' => 'h4', 'class' => '' ],
				'description' => [ 'tag' => 'p', 'class' => '' ],
			],
			'recommend' => [ 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'description', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
			] ],
		],
	],
];
