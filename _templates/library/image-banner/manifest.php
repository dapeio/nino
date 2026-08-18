<?php return [
	'name' => 'Banner — Text over a background image',
	'description' => 'A calm full-bleed image with a dark scrim and one message on top. No scroll effect.',
	'category' => 'Hero',
	'tags' => [ 'banner', 'image', 'background', 'statement', 'cta', 'quote' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'plain',
		'frame' => [ 'screen' => 'off', 'background' => 'cover', 'container' => 'default', 'padding' => 'big', 'margin' => 'none', 'focus' => '5', 'overlay' => 'medium' ],
	],
	'layouts' => [
		'plain' => [ 'label' => 'Text directly on the image', 'template' => 'section-plain.tpl' ],
		'card' => [ 'label' => 'Text inside a card', 'template' => 'section-card.tpl' ],
	],
	'areas' => [
		'content' => [
			'label' => 'Banner content',
			'help' => 'The ordered content displayed over the image.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description', 'button', 'template' ],
			'container' => [ 'class' => 'ui-grid-100' ],
			'styles' => [
				'center' => [ 'label' => 'Centered', 'class' => 'ui-text-center' ],
				'left' => [ 'label' => 'Left', 'class' => 'ui-text-left' ],
				'right' => [ 'label' => 'Right', 'class' => 'ui-text-right' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
				[ 'id' => 'action', 'type' => 'button', 'style' => 'primary', 'bindings' => [ 'label' => 'cta-label', 'href' => 'cta-uri' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'ui-atf-title' ], 'subtitle' => [ 'class' => 'ui-atf-subtitle' ] ],
		],
	],
];
