<?php return [
	'name' => 'Features — Checklist and image',
	'description' => 'A checked list of what is included, next to one supporting image.',
	'category' => 'Content',
	'tags' => [ 'features', 'checklist', 'benefits', 'services', 'split', 'elements' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'media-right',
		'frame' => [ 'background' => 'alt', 'container' => 'wide', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'media-right' => [ 'label' => 'Image right', 'template' => 'section-media-right.tpl' ],
		'media-left' => [ 'label' => 'Image left', 'template' => 'section-media-left.tpl' ],
	],
	'areas' => [
		'content' => [
			'label' => 'Content',
			'help' => 'The copy above the checked list.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
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
		'features' => [
			'label' => 'Checklist',
			'help' => 'One entry per line. The check mark comes from the list, not from the content.',
			'source' => 'elements',
			'allowed' => [ 'text', 'title' ],
			'item' => [ 'tag' => 'li', 'class' => '' ],
			'typeTitle' => 'Feature list',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 200 ],
			],
			'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 5, 'query' => '' ],
			'render' => [ 'text' => [ 'tag' => 'span', 'class' => '' ], 'title' => [ 'tag' => 'strong', 'class' => '' ] ],
			'recommend' => [ 'components' => [
				[ 'id' => 'feature', 'type' => 'text', 'bindings' => [ 'text' => 'title' ] ],
			] ],
		],
		'media' => [
			'label' => 'Image',
			'help' => 'The image column. Layout controls which side it occupies.',
			'source' => 'single',
			'allowed' => [ 'image' ],
			'container' => [ 'class' => 'nino-grid-100 nino-grid-m-50 nino-img-cover' ],
			'recommend' => [ 'components' => [
				[ 'id' => 'image', 'type' => 'image', 'bindings' => [ 'src' => 'image', 'alt' => 'image-alt' ] ],
			] ],
			'render' => [ 'image' => [ 'class' => 'nino-img-cover', 'width' => 1200, 'height' => 900 ] ],
		],
	],
];
