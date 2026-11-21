<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Kopfbereich', 'en_US' => 'Header' ],
	'knobs' => [
		'header-wrap.space' => [ 'token' => '--header-wrap-space', 'label' => [ 'de_DE' => 'Abstand oben und unten', 'en_US' => 'Space above and below' ],
			'ladder' => [ 'var(--page-space-1)', 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)' ] ],
		'header-logo.height' => [ 'token' => '--header-logo-height', 'label' => [ 'de_DE' => 'Höhe des Logos', 'en_US' => 'Height of the logo' ],
			'ladder' => [ '1.5rem', '1.875rem', '2.25rem', '2.75rem', '3.5rem' ] ],
		'header-nav.gap' => [ 'token' => '--header-nav-gap', 'label' => [ 'de_DE' => 'Abstand der Einträge', 'en_US' => 'Gap between the entries' ],
			'ladder' => [ 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)', 'var(--page-space-6)' ] ],
	],
];
