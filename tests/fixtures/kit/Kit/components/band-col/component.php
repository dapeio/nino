<?php
declare(strict_types=1);

return [
	'group'      => 'band',
	'label'      => [ 'de_DE' => 'Spalte', 'en_US' => 'Column' ],
	'icon'       => 'columns-3',
	'in'         => [ 'band-row' ],
	'offers'     => [ 'flow' ],
	'variants'   => [ 'card' ],
	'labels'     => [ 'card' => [ 'de_DE' => 'Karte', 'en_US' => 'Card' ] ],
	'attributes' => [
		'id' => [ 'type' => 'id', 'default' => '', 'label' => 'Id' ],
		'size'   => [ 'type' => 'choice', 'options' => [ '16', '20', '25', '33', '50', '66', '75', '100', 'auto', 'fill' ], 'labels' => [ '16' => [ 'de_DE' => '16 %', 'en_US' => '16 %' ], '20' => [ 'de_DE' => '20 %', 'en_US' => '20 %' ], '25' => [ 'de_DE' => '25 %', 'en_US' => '25 %' ], '33' => [ 'de_DE' => '33 %', 'en_US' => '33 %' ], '50' => [ 'de_DE' => '50 %', 'en_US' => '50 %' ], '66' => [ 'de_DE' => '66 %', 'en_US' => '66 %' ], '75' => [ 'de_DE' => '75 %', 'en_US' => '75 %' ], '100' => [ 'de_DE' => '100 %', 'en_US' => '100 %' ], 'auto' => [ 'de_DE' => 'Inhaltsbreit', 'en_US' => 'As wide as its content' ], 'fill' => [ 'de_DE' => 'Rest der Zeile', 'en_US' => 'The rest of the row' ] ], 'default' => '100', 'responsive' => true, 'label' => [ 'de_DE' => 'Breite', 'en_US' => 'Width' ] ],
		'align'  => [ 'type' => 'choice', 'options' => [ 'start', 'center', 'end' ], 'labels' => [ 'start' => [ 'de_DE' => 'Links', 'en_US' => 'Left' ], 'center' => [ 'de_DE' => 'Mitte', 'en_US' => 'Center' ], 'end' => [ 'de_DE' => 'Rechts', 'en_US' => 'Right' ] ], 'default' => 'start', 'responsive' => true, 'label' => [ 'de_DE' => 'Ausrichtung', 'en_US' => 'Alignment' ] ],
		'hide'   => [ 'type' => 'choice', 'options' => [ 'off', 'on' ], 'labels' => [ 'off' => [ 'de_DE' => 'Aus', 'en_US' => 'Off' ], 'on' => [ 'de_DE' => 'An', 'en_US' => 'On' ] ], 'default' => 'off', 'responsive' => true, 'label' => [ 'de_DE' => 'Ausblenden', 'en_US' => 'Hide' ] ],
		'ground' => [ 'type' => 'choice', 'options' => [ 'none', 'base', 'alt', 'tint', 'primary', 'dark' ], 'labels' => [ 'none' => [ 'de_DE' => 'Ohne', 'en_US' => 'None' ], 'base' => [ 'de_DE' => 'Standard', 'en_US' => 'Default' ], 'alt' => [ 'de_DE' => 'Abgesetzt', 'en_US' => 'Offset' ], 'tint' => [ 'de_DE' => 'Getönt', 'en_US' => 'Tinted' ], 'primary' => [ 'de_DE' => 'Markenfarbe', 'en_US' => 'Brand color' ], 'dark' => [ 'de_DE' => 'Dunkel', 'en_US' => 'Dark' ] ], 'default' => 'none', 'label' => [ 'de_DE' => 'Grund', 'en_US' => 'Ground' ] ],
		'pad'    => [ 'type' => 'choice', 'options' => [ 'auto', '0', '1', '2', '3', '4' ], 'labels' => [ 'auto' => [ 'de_DE' => 'Automatisch', 'en_US' => 'Automatic' ], '0' => [ 'de_DE' => 'Keiner', 'en_US' => 'None' ], '1' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], '2' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], '3' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ], '4' => [ 'de_DE' => 'Sehr groß', 'en_US' => 'Very large' ] ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Innenabstand', 'en_US' => 'Padding' ] ],
		'bleed'  => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Bild bis an den Rand', 'en_US' => 'Picture to the edge' ] ],
		'link'   => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Ganze Fläche ist der Link', 'en_US' => 'The whole surface is the link' ] ],
	],
];
