<?php return [
	'label' 			=> 'Gallery',
	'description' => 'A wall, not a page: greys with no colour cast at all, an overlay menu behind one mark, wide rows and airy space. The brand appears only where it is put. For photographers, portfolios, exhibitions and anything where the picture is the content.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.gallery.css',
	'header' 			=> 'v4',
	'footer' 			=> 'v1',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#3f4a52',
		'secondary'		=> '',
		'harmony'		=> 1,
		'temperature'	=> 1,
		'saturation'	=> 1,
		'contrast'		=> 2,
		'depth'			=> 1,
		'scale'			=> 2,
		'volume'		=> 1,
		'spacing'		=> 3,
		'shaping'		=> 1,
		'measure'		=> 3,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
