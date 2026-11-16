<?php
declare(strict_types=1);

return [
	'group'      => 'section',
	'label'      => [ 'de_DE' => 'Spalte', 'en_US' => 'Column' ],
	'in'         => [ 'section-row' ],
	'offers'     => [ 'flow' ],
	'variants'   => [ 'card' ],
	'attributes' => [
		'id' => [ 'type' => 'id', 'default' => '', 'label' => 'Id' ],
		'size'   => [ 'type' => 'choice', 'options' => [ '25', '33', '50', '66', '75', '100' ], 'default' => '100', 'responsive' => true, 'label' => [ 'de_DE' => 'Breite', 'en_US' => 'Width' ] ],
		'align'  => [ 'type' => 'choice', 'options' => [ 'start', 'center', 'end' ], 'default' => 'start', 'responsive' => true, 'label' => [ 'de_DE' => 'Ausrichtung', 'en_US' => 'Alignment' ] ],
		'hide'   => [ 'type' => 'choice', 'options' => [ 'off', 'on' ], 'default' => 'off', 'responsive' => true, 'label' => [ 'de_DE' => 'Ausblenden', 'en_US' => 'Hide' ] ],
		'ground' => [ 'type' => 'choice', 'options' => [ 'none', 'base', 'alt', 'tint', 'primary', 'dark' ], 'default' => 'none', 'label' => [ 'de_DE' => 'Grund', 'en_US' => 'Ground' ] ],
		'pad'    => [ 'type' => 'choice', 'options' => [ 'auto', '0', '1', '2', '3', '4' ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Innenabstand', 'en_US' => 'Padding' ] ],
		'bleed'  => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Bild bis an den Rand', 'en_US' => 'Picture to the edge' ] ],
		'link'   => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Ganze Fläche ist der Link', 'en_US' => 'The whole surface is the link' ] ],
	],
];
