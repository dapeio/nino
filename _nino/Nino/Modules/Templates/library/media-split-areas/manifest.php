<?php return [
	'name' => 'Media / Text — Flexible split',
	'description' => 'A two-column image and content section with switchable visual order.',
	'category' => 'Content',
	'tags' => [ 'media', 'image', 'text', 'split', 'columns', 'cta' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'media-left',
		'frame' => [ 'background' => 'default', 'container' => 'wide', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'media-left' => [ 'label' => 'Image left', 'template' => 'section-media-left.tpl' ],
		'media-right' => [ 'label' => 'Image right', 'template' => 'section-media-right.tpl' ],
	],
	'areas' => [
		'media' => [
			'label' => 'Media',
			'help' => 'The image column. Layout controls which side it occupies.',
			'source' => 'single',
			'allowed' => [ 'image' ],
			'container' => [ 'class' => 'nino-grid-100 nino-grid-m-50 nino-img-cover' ],
			'recommend' => [ 'components' => [
				[ 'id' => 'image', 'type' => 'image', 'bindings' => [ 'src' => 'image', 'alt' => 'image-alt' ] ],
			] ],
			'render' => [ 'image' => [ 'class' => 'nino-img-cover', 'width' => 1200, 'height' => 900 ] ],
		],
		'content' => [
			'label' => 'Content',
			'help' => 'The ordered copy and actions beside the image.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description', 'text', 'button', 'template' ],
			'container' => [ 'class' => 'nino-grid-100 nino-grid-m-50 nino-p-2' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
			],
			'recommend' => [ 'style' => 'left', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'description', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
				[ 'id' => 'action', 'type' => 'button', 'style' => 'primary', 'bindings' => [ 'label' => 'cta-label', 'href' => 'cta-uri' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ] ],
		],
	],
];
