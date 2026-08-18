<?php return [
	'name' => 'Pricing — Plan cards',
	'description' => 'One card per plan: name, price and what it includes, from an Elements collection.',
	'category' => 'Content',
	'tags' => [ 'pricing', 'plans', 'packages', 'price', 'cards', 'elements' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'equal',
		'frame' => [ 'background' => 'alt', 'container' => 'default', 'padding' => 'default', 'margin' => 'none' ],
	],
	'layouts' => [
		'equal' => [ 'label' => 'Equal cards', 'template' => 'section-equal.tpl' ],
		'feature-middle' => [ 'label' => 'Three cards, middle one highlighted', 'template' => 'section-feature-middle.tpl' ],
		'four' => [ 'label' => 'Four equal cards', 'template' => 'section-four.tpl' ],
		'four-feature-first' => [ 'label' => 'Four cards below one full-width card', 'template' => 'section-four-feature-first.tpl' ],
		'four-feature-last' => [ 'label' => 'Four cards above one full-width card', 'template' => 'section-four-feature-last.tpl' ],
	],
	'areas' => [
		'heading' => [
			'label' => 'Title area',
			'help' => 'The introduction above the plans.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [ 'class' => 'ui-grid-100 ui-mb-3' ],
			'styles' => [
				'center' => [ 'label' => 'Centered', 'class' => 'ui-text-center' ],
				'left' => [ 'label' => 'Left', 'class' => 'ui-text-left' ],
			],
			'recommend' => [ 'style' => 'center', 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'subtitle', 'type' => 'subtitle', 'bindings' => [ 'text' => 'subtitle' ] ],
			] ],
			'render' => [ 'title' => [ 'tag' => 'h2', 'class' => 'ui-section-title' ] ],
		],
		'plans' => [
			'label' => 'Plans',
			'help' => 'One entry per plan. Which card is emphasized is a Layout choice, not content — the four-column Layouts read the first or the last entry of this collection as the wide one, so raise the limit to 5 for those.',
			'source' => 'elements',
			'allowed' => [ 'title', 'price', 'description', 'button' ],
			'item' => [ 'tag' => 'div', 'class' => 'ui-pricing-item' ],
			'typeTitle' => 'Pricing plans',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 120 ],
				'price' => [ 'type' => 'string', 'maxlength' => 40 ],
				'suffix' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 40 ],
				'description' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'maxlength' => 1200 ],
				'linkLabel' => [ 'type' => 'string', 'locale' => true, 'maxlength' => 120 ],
				'link' => [ 'type' => 'string', 'maxlength' => 500 ],
			],
			'shortcode' => [ 'locale' => '', 'callback' => '', 'limit' => 3, 'query' => '' ],
			'render' => [
				'title' => [ 'tag' => 'h3', 'class' => 'ui-pricing-title' ],
				'price' => [ 'class' => 'ui-pricing-price' ],
				'description' => [ 'tag' => 'div', 'class' => '' ],
			],
			'recommend' => [ 'components' => [
				[ 'id' => 'title', 'type' => 'title', 'bindings' => [ 'text' => 'title' ] ],
				[ 'id' => 'price', 'type' => 'price', 'bindings' => [ 'value' => 'price', 'suffix' => 'suffix' ] ],
				[ 'id' => 'description', 'type' => 'description', 'bindings' => [ 'text' => 'description' ] ],
				[ 'id' => 'action', 'type' => 'button', 'style' => 'primary', 'bindings' => [ 'label' => 'linkLabel', 'href' => 'link' ] ],
			] ],
		],
	],
];
