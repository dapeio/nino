<?php return [
	'label' 		=> 'Demo: Catalogue',
	// The contact form preset on the page is the working form, with the
	// messages its module ships - without it the specimen would render its
	// textfill keys instead of messages. The newsletter specimen's one
	// label travels in this unit's own text/ instead: Newsletter is a feature
	// a project activates in the Features panel after setup, not a unit the
	// wizard can pull in, and its form only answers once that is done
	'requiresModules' => [ 'forms' ],
	'routes' 		=> [
		'GET://.demo-catalogue' => [ 'uri' => '/.demo-catalogue', 'body' => '[template /templates/.demo-catalogue]' ],
	],
	// The second file is what the "template-include" preset points at: that
	// preset only works with a template to include, and one the unit ships
	// itself is the only one guaranteed to be there
	'templates' => [ '.demo-catalogue.tpl', 'demo-catalogue-include.tpl' ],
	// The stand-in photography every image slot on the page is filled with
	'files' 		=> [ 'images' ],
	'suggest' => [
		'uri' 				=> '/.demo-catalogue',
		'name' 				=> [ 'en_US' => 'Catalogue', 'de_DE' => 'Katalog' ],
		'title' 			=> [ 'en_US' => 'Catalogue: presets and building blocks', 'de_DE' => 'Katalog: Presets und Bausteine' ],
		'description' => [
			'en_US' => 'Every section preset the Template Builder ships and every building block in Nino.css, in this project\'s own design.',
			'de_DE' => 'Jedes Section-Preset des Template Builders und jeder Baustein aus Nino.css, im Design dieses Projekts.',
		],
	],
];
