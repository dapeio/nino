<?php
// A feature whose attributes take their options from methods of its class: the list of
// the forms it knows, which only the project can say, and the ways such a method fails
// (tests/components-smoke.php). Not a directory of tests/fixtures/features, the one the
// feature suites list
return [
	'key'					=> 'picker',
	'name'				=> 'Picker',
	'version'			=> '0.1.0',
	'nino'				=> '^1.0',
	'components'	=> [
		'picker-form'		=> [
			'label'				=> 'Form',
			'source'			=> 'none',
			'attributes'	=> [
				'form'				=> [ 'type' => 'select', 'options' => '\Nino\Modules\Picker::forms', 'default' => '', 'label' => 'Form' ],
				'tone'				=> [ 'type' => 'variant', 'options' => 'Nino\Modules\Picker::tones', 'default' => '', 'prefix' => 'picker-form--' ],
				'size'				=> [ 'type' => 'select', 'options' => [ '', 'big' ], 'default' => '' ],
			],
		],
		'picker-broken'	=> [
			'label'				=> 'Broken',
			'source'			=> 'none',
			'attributes'	=> [
				'flat'				=> [ 'type' => 'select', 'options' => '\Nino\Modules\Picker::flat', 'default' => 'a', 'input' => 'segment' ],
				'thrown'			=> [ 'type' => 'select', 'options' => '\Nino\Modules\Picker::thrown', 'default' => 'a' ],
				'label'				=> [ 'type' => 'select', 'options' => '\Nino\Modules\Picker::unlabelled', 'default' => 'a' ],
				'word'				=> [ 'type' => 'variant', 'options' => '\Nino\Modules\Picker::notWords', 'default' => '', 'prefix' => 'picker-broken--' ],
				'again'				=> [ 'type' => 'select', 'options' => '\Nino\Modules\Picker::again', 'default' => '' ],
			],
		],
	],
	'stacks'			=> [
		'picker-rows'		=> [
			'label'				=> 'Rows',
			'attributes'	=> [
				'form'				=> [ 'type' => 'select', 'options' => '\Nino\Modules\Picker::forms', 'default' => '' ],
			],
		],
	],
];
