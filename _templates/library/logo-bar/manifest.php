<?php return [
	'name' => 'Partners — Logo bar',
	'description' => 'A quiet row of customer or partner logos, greyscaled until hovered.',
	'category' => 'Content',
	'tags' => [ 'logos', 'partners', 'customers', 'trust', 'references', 'elements' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'row',
		'frame' => [ 'background' => 'alt', 'container' => 'default', 'padding' => 'small', 'margin' => 'none' ],
	],
	'layouts' => [
		'row' => [ 'label' => 'Caption above the row', 'template' => 'section-row.tpl' ],
		'aside' => [ 'label' => 'Caption beside the row', 'template' => 'section-aside.tpl' ],
	],
	'areas' => [
		'heading' => [
			'label' => 'Caption',
			'help' => 'The short line introducing the logos, for example "Trusted by".',
			'source' => 'single',
			'allowed' => [ 'subtitle', 'title' ],
			'container' => [ 'class' => 'nino-grid-100 nino-mb-3 nino-mb-3' ],
			'styles' => [
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [
				[ 'id' => 'caption', 'type' => 'subtitle', 'bindings' => [ 'text' => 'caption' ] ],
			] ],
			'render' => [ 'subtitle' => [ 'class' => 'nino-section-subtitle' ], 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ] ],
		],
		'logos' => [
			'label' => 'Logos',
			'help' => 'One entry per partner. Use the Image component for real logo files, or the Title component for plain names.',
			'source' => 'elements',
			'allowed' => [ 'image', 'title' ],
			'item' => [ 'tag' => 'span', 'class' => 'nino-logos-item' ],
			'typeTitle' => 'Partner logos',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 120 ],
				'image' => [ 'type' => 'image', 'width' => 360, 'height' => 140 ],
			],
			'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 6, 'query' => '' ],
			'render' => [
				'image' => [ 'class' => '', 'width' => 360, 'height' => 140 ],
				'title' => [ 'tag' => 'span', 'class' => '' ],
			],
			'recommend' => [ 'components' => [
				[ 'id' => 'logo', 'type' => 'image', 'bindings' => [ 'src' => 'image', 'alt' => 'title' ] ],
			] ],
		],
	],
];
