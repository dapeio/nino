<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Bilder', 'en_US' => 'Images' ],
	'knobs' => [
		'pic.radius' => [ 'token' => '--pic-radius', 'label' => [ 'de_DE' => 'Rundung', 'en_US' => 'Radius' ],
			'ladder' => [ '0px', 'calc( var(--page-radius) / 2 )', 'var(--page-radius)', 'calc( var(--page-radius) * 2 )', 'calc( var(--page-radius) * 4 )' ] ],
		'pic.height' => [ 'token' => '--pic-height', 'label' => [ 'de_DE' => 'Feste Höhe', 'en_US' => 'Fixed height' ],
			'ladder' => [ 'var(--page-space-5)', 'calc( var(--page-space-6) * .75 )', 'var(--page-space-6)', 'calc( var(--page-space-6) * 1.5 )', 'calc( var(--page-space-6) * 2 )' ] ],
	],
];
