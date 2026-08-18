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
			'container' => [ 'class' => 'ui-grid-100 nino-area nino-area--heading' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-area--left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-area--center' ],
				'right' => [ 'label' => 'Right', 'class' => 'nino-area--right' ],
			],
			'recommend' => [
				'style' => 'center',
				'components' => [
					[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
					[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
					[ 'id' => 'description', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
				],
			],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'ui-section-title' ] ],
		],
		'articles' => [
			'label' => 'Articles',
			'help' => 'A repeatable collection. Add, reorder or remove the fields rendered for every article.',
			'source' => 'elements',
			'allowed' => [ 'image', 'title', 'description', 'button' ],
			'item' => [ 'tag' => 'article', 'class' => 'ui-article ui-article--alt ui-mb-3 nino-article-grid-item' ],
			'styles' => [
				'two-columns' => [ 'label' => '2 columns', 'class' => 'ui-grid-m-50' ],
				'three-columns' => [ 'label' => '3 columns', 'class' => 'ui-grid-m-33' ],
				'four-columns' => [ 'label' => '4 columns', 'class' => 'ui-grid-m-25' ],
				'borderless' => [ 'label' => 'Borderless · 3 columns', 'class' => 'ui-grid-m-33 nino-article--borderless' ],
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
				'image' => [ 'class' => 'ui-article-img ui-article-img--maxheight' ],
				'title' => [
					'tag' => 'h3', 'class' => 'ui-article-title ui-autoheight',
					'data' => [ 'autoheight-group' => 'nino-article-title-[[section:id]]', 'autoheight-mobile' => 'skip' ],
				],
				'description' => [
					'class' => 'ui-article-descr ui-autoheight',
					'data' => [ 'autoheight-group' => 'nino-article-descr-[[section:id]]', 'autoheight-mobile' => 'skip' ],
				],
			],
		],
		'action' => [
			'label' => 'Action area',
			'help' => 'Optional non-repeating text and calls to action below the collection.',
			'source' => 'single',
			'allowed' => [ 'title', 'description', 'button', 'template' ],
			'container' => [ 'class' => 'ui-grid-100 nino-area nino-area--action' ],
			'styles' => [
				'left' => [ 'label' => 'Left', 'class' => 'nino-area--left' ],
				'center' => [ 'label' => 'Centered', 'class' => 'nino-area--center' ],
				'right' => [ 'label' => 'Right', 'class' => 'nino-area--right' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [] ],
		],
	],
];
