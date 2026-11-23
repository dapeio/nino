<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Buttons', 'en_US' => 'Buttons' ],
	'knobs' => [
		'lever.space' => [ 'token' => '--lever-space', 'label' => [ 'de_DE' => 'Innenabstand', 'en_US' => 'Padding' ],
			'ladder' => [ 'var(--page-space-1)', 'calc( var(--page-space-1) * 1.5 )', 'var(--page-space-2)', 'calc( var(--page-space-2) * 1.5 )', 'var(--page-space-3)' ] ],
		'lever.radius' => [ 'token' => '--lever-radius', 'label' => [ 'de_DE' => 'Rundung', 'en_US' => 'Radius' ],
			'ladder' => [ '0px', 'calc( var(--page-radius) / 2 )', 'var(--page-radius)', 'calc( var(--page-radius) * 2 )', '999px' ] ],
		'lever.size' => [ 'token' => '--lever-size', 'label' => [ 'de_DE' => 'Schriftgröße', 'en_US' => 'Font size' ],
			'ladder' => [ 'var(--page-text-1)', 'calc( ( var(--page-text-1) + var(--page-text-2) ) / 2 )', 'var(--page-text-2)', 'calc( ( var(--page-text-2) + var(--page-text-3) ) / 2 )', 'var(--page-text-3)' ] ],
	],
];
