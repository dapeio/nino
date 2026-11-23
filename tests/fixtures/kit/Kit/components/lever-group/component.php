<?php
declare(strict_types=1);

return [
	'group'      => 'lever',
	'label'      => [ 'de_DE' => 'Button-Gruppe', 'en_US' => 'Button group' ],
	'icon'       => 'mouse-pointer-click',
	'in'         => [ 'flow' ],
	'offers'     => [],
	'attributes' => [
		'align' => [ 'type' => 'choice', 'options' => [ 'start', 'center', 'end' ], 'labels' => [ 'start' => [ 'de_DE' => 'Links', 'en_US' => 'Left' ], 'center' => [ 'de_DE' => 'Mitte', 'en_US' => 'Center' ], 'end' => [ 'de_DE' => 'Rechts', 'en_US' => 'Right' ] ], 'default' => 'start', 'label' => [ 'de_DE' => 'Ausrichtung', 'en_US' => 'Alignment' ] ],
	],
];
