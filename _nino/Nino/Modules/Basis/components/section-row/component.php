<?php
declare(strict_types=1);

return [
	'group'      => 'section',
	'label'      => [ 'de_DE' => 'Zeile', 'en_US' => 'Row' ],
	'icon'       => 'rows-2',
	'in'         => [ 'section-wrap', 'header-wrap', 'footer-wrap' ],
	'offers'     => [],
	'attributes' => [
		'width'   => [ 'type' => 'choice', 'options' => [ 'regular', 'narrow', 'wide', 'full' ], 'labels' => [ 'regular' => [ 'de_DE' => 'Standard', 'en_US' => 'Default' ], 'narrow' => [ 'de_DE' => 'Schmal', 'en_US' => 'Narrow' ], 'wide' => [ 'de_DE' => 'Breit', 'en_US' => 'Wide' ], 'full' => [ 'de_DE' => 'Volle Breite', 'en_US' => 'Full width' ] ], 'default' => 'regular', 'label' => [ 'de_DE' => 'Breite', 'en_US' => 'Width' ] ],
		'gap'     => [ 'type' => 'choice', 'options' => [ '0', '1', '2', '3', '4' ], 'labels' => [ '0' => [ 'de_DE' => 'Keiner', 'en_US' => 'None' ], '1' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], '2' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], '3' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ], '4' => [ 'de_DE' => 'Sehr groß', 'en_US' => 'Very large' ] ], 'default' => '2', 'label' => [ 'de_DE' => 'Lücke', 'en_US' => 'Gap' ] ],
		'valign'  => [ 'type' => 'choice', 'options' => [ 'stretch', 'start', 'center', 'end' ], 'labels' => [ 'stretch' => [ 'de_DE' => 'Füllend', 'en_US' => 'Stretched' ], 'start' => [ 'de_DE' => 'Oben', 'en_US' => 'Top' ], 'center' => [ 'de_DE' => 'Mitte', 'en_US' => 'Middle' ], 'end' => [ 'de_DE' => 'Unten', 'en_US' => 'Bottom' ] ], 'default' => 'stretch', 'label' => [ 'de_DE' => 'Vertikale Ausrichtung', 'en_US' => 'Vertical alignment' ] ],
		'halign'  => [ 'type' => 'choice', 'options' => [ 'start', 'center', 'end', 'between' ], 'labels' => [ 'start' => [ 'de_DE' => 'Links', 'en_US' => 'Left' ], 'center' => [ 'de_DE' => 'Mitte', 'en_US' => 'Center' ], 'end' => [ 'de_DE' => 'Rechts', 'en_US' => 'Right' ], 'between' => [ 'de_DE' => 'Verteilt', 'en_US' => 'Spread' ] ], 'default' => 'start', 'label' => [ 'de_DE' => 'Horizontale Ausrichtung', 'en_US' => 'Horizontal alignment' ] ],
		'reverse' => [ 'type' => 'choice', 'options' => [ 'off', 'on' ], 'labels' => [ 'off' => [ 'de_DE' => 'Aus', 'en_US' => 'Off' ], 'on' => [ 'de_DE' => 'An', 'en_US' => 'On' ] ], 'default' => 'off', 'responsive' => true, 'label' => [ 'de_DE' => 'Reihenfolge umkehren', 'en_US' => 'Reverse the order' ] ],
	],
];
