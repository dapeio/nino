<?php return [
	'label' 		=> 'Demo: Catalogue',
	// The two form presets on the page are the working forms, with the labels
	// and messages their modules ship - without them the specimens would
	// render their textfill keys instead of German labels
	'requiresModules' => [ 'forms', 'newsletter' ],
	'routes' 		=> [
		'GET://.demo-catalogue' => [ 'uri' => '/.demo-catalogue', 'body' => '[template /templates/.demo-catalogue]' ],
	],
	// The second file is what the "template-include" preset points at: that
	// preset only works with a template to include, and one the unit ships
	// itself is the only one guaranteed to be there
	'templates' => [ '.demo-catalogue.tpl', 'demo-catalogue-include.tpl' ],
	// The stand-in photography every image slot on the page is filled with
	'files' 		=> [ 'images' ],
];
