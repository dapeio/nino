<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Sektion', 'en_US' => 'Section' ],
	'knobs' => [
		'section-wrap.space' => [ 'token' => '--section-wrap-space', 'label' => [ 'de_DE' => 'Abstand oben und unten', 'en_US' => 'Space above and below' ],
			'ladder' => [ 'var(--page-space-4)', 'var(--page-space-5)', 'var(--page-space-6)', 'calc( var(--page-space-6) * 1.5 )', 'calc( var(--page-space-6) * 2 )' ] ],
		'section-row.gap' => [ 'token' => '--section-row-gap', 'label' => [ 'de_DE' => 'Lücke zwischen den Spalten', 'en_US' => 'Gap between the columns' ],
			'ladder' => [ 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)', 'var(--page-space-6)' ] ],
		'section-col.pad' => [ 'token' => '--section-col-pad', 'label' => [ 'de_DE' => 'Innenabstand der Karte', 'en_US' => 'Padding of the card' ],
			'ladder' => [ 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)', 'var(--page-space-6)' ] ],
		'section-col.radius' => [ 'token' => '--section-col-radius', 'label' => [ 'de_DE' => 'Rundung der Karte', 'en_US' => 'Radius of the card' ],
			'ladder' => [ '0px', 'calc( var(--page-radius) / 2 )', 'var(--page-radius)', 'calc( var(--page-radius) * 2 )', 'calc( var(--page-radius) * 4 )' ] ],
		'section-title.space' => [ 'token' => '--section-title-space', 'label' => [ 'de_DE' => 'Abstand unter Titel und Text', 'en_US' => 'Space below title and text' ],
			'ladder' => [ 'var(--page-space-1)', 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)' ] ],
	],
];
