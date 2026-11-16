<?php
$GLOBALS['ninoRegisterGroupReads'][] = 'probe';

return [
	'label' => [ 'en_US' => 'Probe', 'de_DE' => 'Probe' ],
	'knobs' => [
		'probe-box.space' => [ 'token' => '--probe-box-space', 'label' => [ 'en_US' => 'Space', 'de_DE' => 'Abstand' ],
			'ladder' => [ 'var(--page-space-1)', 'var(--page-space-2)', 'var(--page-space-3)', 'var(--page-space-4)', 'var(--page-space-5)' ] ],
		'probe-line.size' => [ 'token' => '--probe-line-size', 'label' => 'Size',
			'ladder' => [ '.875rem', '1rem', '1.125rem', 'calc( 1rem * 1.5 )', '2rem' ] ],
	],
];
