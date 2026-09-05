<?php return [
	'name' => 'Fullscreen image',
	'description' => 'A focused image stage with optional title, supporting copy and actions.',
	'category' => 'Hero',
	'tags' => [ 'hero', 'fullscreen', 'image', 'cover', 'parallax', 'title', 'cta' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'cover',
		'frame' => [ 'container' => 'wide', 'padding' => 'default', 'margin' => 'none', 'focus' => '5', 'overlay' => 'dim' ],
	],
	'layouts' => [
		'cover' => [
			'label' => 'Static cover image',
			'template' => 'section-cover.tpl',
			'frame' => [ 'screen' => '100', 'vertical' => 'middle', 'background' => 'cover' ],
		],
		'parallax' => [
			'label' => 'Parallax image',
			'template' => 'section-parallax.tpl',
			'frame' => [ 'screen' => '100', 'vertical' => 'middle', 'background' => 'parallax' ],
		],
	],
	'areas' => [
		'content' => [
			'label' => 'Title content',
			'help' => 'The ordered content displayed over the image.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description', 'button', 'template' ],
			'container' => [ 'class' => 'nino-grid-100' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
				'right' => [ 'label' => 'Right', 'class' => 'nino-text-right' ],
			],
			'recommend' => [
				'style' => 'center',
				'components' => [
					[ 'id' => 'title', 'type' => 'title', 'style' => 'loud', 'bindings' => [ 'text' => 'title' ] ],
					[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
					[ 'id' => 'description', 'type' => 'description', 'style' => 'loud', 'bindings' => [ 'text' => 'description' ] ],
					[ 'id' => 'action', 'type' => 'button', 'style' => 'primary', 'bindings' => [ 'label' => 'cta-label', 'href' => 'cta-uri' ] ],
				],
			],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-atf-title' ], 'subtitle' => [ 'class' => 'nino-atf-subtitle' ] ],
		],
	],
];
