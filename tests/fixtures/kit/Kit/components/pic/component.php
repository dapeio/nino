<?php
declare(strict_types=1);

return [
	'group'      => 'pic',
	'label'      => [ 'de_DE' => 'Bild', 'en_US' => 'Image' ],
	'icon'       => 'image',
	'in'         => [ 'flow' ],
	'source'     => 'image',
	'preview'    => 'image',
	'attributes' => [
		'alt'     => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Alternativtext', 'en_US' => 'Alternative text' ] ],
		'decorative' => [ 'type' => 'bool', 'default' => false, 'class' => false, 'label' => [ 'de_DE' => 'Nur Schmuck, ohne Alternativtext', 'en_US' => 'Decorative, with no alternative text' ] ],
		'ratio'   => [ 'type' => 'choice', 'options' => [ 'auto', '1-1', '4-3', '3-2', '3-4', '16-9', '21-9' ], 'labels' => [ 'auto' => [ 'de_DE' => 'Original', 'en_US' => 'Original' ], '1-1' => [ 'de_DE' => '1:1', 'en_US' => '1:1' ], '4-3' => [ 'de_DE' => '4:3', 'en_US' => '4:3' ], '3-2' => [ 'de_DE' => '3:2', 'en_US' => '3:2' ], '3-4' => [ 'de_DE' => '3:4', 'en_US' => '3:4' ], '16-9' => [ 'de_DE' => '16:9', 'en_US' => '16:9' ], '21-9' => [ 'de_DE' => '21:9', 'en_US' => '21:9' ] ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Seitenverhältnis', 'en_US' => 'Ratio' ] ],
		'fit'     => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Einpassen statt zuschneiden', 'en_US' => 'Fit instead of crop' ] ],
		'height'  => [ 'type' => 'choice', 'options' => [ 'auto', '1', '2', '3', '4' ], 'labels' => [ 'auto' => [ 'de_DE' => 'Automatisch', 'en_US' => 'Automatic' ], '1' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], '2' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], '3' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ], '4' => [ 'de_DE' => 'Sehr groß', 'en_US' => 'Very large' ] ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Feste Höhe', 'en_US' => 'Fixed height' ], 'hint' => [ 'de_DE' => 'Die Breite folgt dem Bild, höchstens die Spalte; mit einem Seitenverhältnis ist sie Höhe mal Verhältnis. Das Bild wird eingepasst.', 'en_US' => 'The width follows the picture, at most the column; with a ratio it is the height times the ratio. The picture is fitted.' ] ],
		'focus'   => [ 'type' => 'choice', 'options' => [ 'center', 'top', 'right', 'bottom', 'left', 'top-left', 'top-right', 'bottom-left', 'bottom-right' ], 'labels' => [ 'center' => [ 'de_DE' => 'Mitte', 'en_US' => 'Center' ], 'top' => [ 'de_DE' => 'Oben', 'en_US' => 'Top' ], 'right' => [ 'de_DE' => 'Rechts', 'en_US' => 'Right' ], 'bottom' => [ 'de_DE' => 'Unten', 'en_US' => 'Bottom' ], 'left' => [ 'de_DE' => 'Links', 'en_US' => 'Left' ], 'top-left' => [ 'de_DE' => 'Oben links', 'en_US' => 'Top left' ], 'top-right' => [ 'de_DE' => 'Oben rechts', 'en_US' => 'Top right' ], 'bottom-left' => [ 'de_DE' => 'Unten links', 'en_US' => 'Bottom left' ], 'bottom-right' => [ 'de_DE' => 'Unten rechts', 'en_US' => 'Bottom right' ] ], 'default' => 'center', 'label' => [ 'de_DE' => 'Fokus', 'en_US' => 'Focus' ] ],
		'caption' => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Bildunterschrift', 'en_US' => 'Caption' ] ],
		'href'    => [ 'type' => 'link', 'default' => '', 'label' => [ 'de_DE' => 'Link', 'en_US' => 'Link' ] ],
	],
];
