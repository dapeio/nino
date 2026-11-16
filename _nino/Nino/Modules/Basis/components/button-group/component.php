<?php
declare(strict_types=1);

return [
	'group'      => 'button',
	'label'      => [ 'de_DE' => 'Button-Gruppe', 'en_US' => 'Button group' ],
	'in'         => [ 'flow' ],
	'offers'     => [],
	'attributes' => [
		'align' => [ 'type' => 'choice', 'options' => [ 'start', 'center', 'end' ], 'default' => 'start', 'label' => [ 'de_DE' => 'Ausrichtung', 'en_US' => 'Alignment' ] ],
	],
];
