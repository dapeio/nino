<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Footer', 'en_US' => 'Footer' ],
	'knobs' => [
		'footer-wrap.space' => [ 'token' => '--footer-wrap-space', 'label' => [ 'de_DE' => 'Abstand oben und unten', 'en_US' => 'Space above and below' ],
			'ladder' => [ 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)', 'var(--page-space-6)', 'calc( var(--page-space-6) * 1.5 )' ] ],
		'footer-col.gap' => [ 'token' => '--footer-col-gap', 'label' => [ 'de_DE' => 'Lücke zwischen den Spalten', 'en_US' => 'Gap between the columns' ],
			'ladder' => [ 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)', 'var(--page-space-6)' ] ],
	],
];
