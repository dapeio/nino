<?php
declare(strict_types=1);

return [
	'label' => [ 'de_DE' => 'Textblöcke', 'en_US' => 'Text blocks' ],
	'knobs' => [
		'text.size' => [ 'token' => '--text-size', 'label' => [ 'de_DE' => 'Schriftgröße', 'en_US' => 'Font size' ],
			'ladder' => [ 'var(--page-text-1)', 'calc( ( var(--page-text-1) + var(--page-text-2) ) / 2 )', 'var(--page-text-2)', 'calc( ( var(--page-text-2) + var(--page-text-3) ) / 2 )', 'var(--page-text-3)' ] ],
		'text.gap' => [ 'token' => '--text-gap', 'label' => [ 'de_DE' => 'Abstand zwischen Absätzen', 'en_US' => 'Gap between paragraphs' ],
			'ladder' => [ 'var(--page-space-1)', 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)' ] ],
	],
];
