<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Bild', 'en_US' => 'Image' ],
	'knobs' => [
		'image.radius' => [ 'token' => '--image-radius', 'label' => [ 'de_DE' => 'Rundung', 'en_US' => 'Radius' ],
			'ladder' => [ '0px', 'calc( var(--page-radius) / 2 )', 'var(--page-radius)', 'calc( var(--page-radius) * 2 )', 'calc( var(--page-radius) * 4 )' ] ],
	],
];
