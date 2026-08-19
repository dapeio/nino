<?php return [
	'name' => 'Articles — Responsive grid',
	'description' => 'Repeatable image cards for services, offers, news or feature overviews.',
	'category' => 'Cards',
	'tags' => [ 'articles', 'cards', 'elements', 'grid', 'services', 'features' ],
	'version' => 3,
	'recommend' => [
		'frame' => [ 'background' => 'alt', 'container' => 'wide', 'padding' => 'default', 'margin' => 'none' ],
		'layout' => 'default',
	],
	'layouts' => [
		'default' => [ 'label' => 'Heading, articles and action', 'template' => 'section.tpl' ],
	],
	'areas' => [
		'heading' => [
			'label' => 'Title area',
			'help' => 'The non-repeating introduction above the collection.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'nino-grid-100 nino-mb-3' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
				'right' => [ 'label' => 'Right', 'class' => 'nino-text-right' ],
			],
			'recommend' => [
				'style' => 'center',
				'components' => [
					[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
					[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
					[ 'id' => 'description', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
				],
			],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ] ],
		],
		'articles' => [
			'label' => 'Articles',
			'help' => 'A repeatable collection. Add, reorder or remove the fields rendered for every article.',
			'source' => 'elements',
			'allowed' => [ 'image', 'title', 'description', 'button' ],
			'item' => [ 'tag' => 'article', 'class' => 'nino-article nino-article--alt nino-mb-3 nino-article--grid' ],
			'styles' => [
				'two-columns' => [ 'label' => '2 columns', 'class' => 'nino-grid-m-50' ],
				'three-columns' => [ 'label' => '3 columns', 'class' => 'nino-grid-m-33' ],
				'four-columns' => [ 'label' => '4 columns', 'class' => 'nino-grid-m-25' ],
				'borderless' => [ 'label' => 'Borderless · 3 columns', 'class' => 'nino-grid-m-33 nino-article--borderless' ],
			],
			'recommend' => [
				'style' => 'three-columns',
				'components' => [
					[ 'id' => 'image', 'type' => 'image', 'bindings' => [ 'src' => 'image', 'alt' => 'title' ] ],
					[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
					[ 'id' => 'description', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
					[ 'id' => 'action', 'type' => 'button', 'style' => 'link', 'bindings' => [ 'label' => 'linkLabel', 'href' => 'link' ] ],
				],
			],
			'typeTitle' => 'Articles',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 180 ],
				'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 2000 ],
				'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
				'link' => [ 'type' => 'string', 'maxlength' => 500 ],
				'image' => [ 'type' => 'image', 'width' => 1200, 'height' => 800 ],
			],
			'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 6, 'query' => '' ],
			'render' => [
				'image' => [ 'class' => 'nino-article-img nino-article-img--maxheight' ],
				'title' => [
					'tag' => 'h3', 'class' => 'nino-article-title nino-autoheight',
					'data' => [ 'autoheight-group' => 'article-title-[[section:id]]', 'autoheight-mobile' => 'skip' ],
				],
				'description' => [
					'class' => 'nino-article-descr nino-autoheight',
					'data' => [ 'autoheight-group' => 'article-descr-[[section:id]]', 'autoheight-mobile' => 'skip' ],
				],
			],
		],
		'action' => [
			'label' => 'Action area',
			'help' => 'Optional non-repeating text and calls to action below the collection.',
			'source' => 'single',
			'allowed' => [ 'title', 'description', 'button', 'template' ],
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
