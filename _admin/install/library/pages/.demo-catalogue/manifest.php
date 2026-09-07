<?php return [
	'label' 		=> 'Demo: Catalogue',
	// The contact form preset on the page is the working form, with the labels
	// and messages its module ships - without it the specimen would render
	// its textfill keys instead of labels. The newsletter specimen's two
	// labels travel in this unit's own text/ instead: Newsletter is a feature
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
];
