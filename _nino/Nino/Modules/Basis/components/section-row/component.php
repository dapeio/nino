<?php
declare(strict_types=1);

return [
	'group'      => 'section',
	'label'      => [ 'de_DE' => 'Zeile', 'en_US' => 'Row' ],
	'in'         => [ 'section-wrap' ],
	'offers'     => [],
	'attributes' => [
		'width'   => [ 'type' => 'choice', 'options' => [ 'regular', 'narrow', 'wide', 'full' ], 'default' => 'regular', 'label' => [ 'de_DE' => 'Breite', 'en_US' => 'Width' ] ],
		'gap'     => [ 'type' => 'choice', 'options' => [ '0', '1', '2', '3', '4' ], 'default' => '2', 'label' => [ 'de_DE' => 'Lücke', 'en_US' => 'Gap' ] ],
		'valign'  => [ 'type' => 'choice', 'options' => [ 'stretch', 'start', 'center', 'end' ], 'default' => 'stretch', 'label' => [ 'de_DE' => 'Vertikale Ausrichtung', 'en_US' => 'Vertical alignment' ] ],
		'reverse' => [ 'type' => 'choice', 'options' => [ 'off', 'on' ], 'default' => 'off', 'responsive' => true, 'label' => [ 'de_DE' => 'Reihenfolge umkehren', 'en_US' => 'Reverse the order' ] ],
	],
];
