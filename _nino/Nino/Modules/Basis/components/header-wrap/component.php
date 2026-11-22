<?php
declare(strict_types=1);

return [
	'group'      => 'header',
	'label'      => [ 'de_DE' => 'Kopfbereich', 'en_US' => 'Header' ],
	'icon'       => 'panel-top',
	'in'         => [ 'page' ],
	'offers'     => [],
	'deep'       => [ 'header' ],
	'attributes' => [
		'id' => [ 'type' => 'id', 'default' => '', 'label' => 'Id' ],
		'sticky' => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Bleibt oben stehen', 'en_US' => 'Stays at the top' ] ],
		'ground' => [ 'type' => 'choice', 'options' => [ 'base', 'alt', 'tint', 'primary', 'dark' ], 'labels' => [ 'base' => [ 'de_DE' => 'Standard', 'en_US' => 'Default' ], 'alt' => [ 'de_DE' => 'Abgesetzt', 'en_US' => 'Offset' ], 'tint' => [ 'de_DE' => 'Getönt', 'en_US' => 'Tinted' ], 'primary' => [ 'de_DE' => 'Markenfarbe', 'en_US' => 'Brand color' ], 'dark' => [ 'de_DE' => 'Dunkel', 'en_US' => 'Dark' ] ], 'default' => 'base', 'label' => [ 'de_DE' => 'Grund', 'en_US' => 'Ground' ] ],
		'image'  => [ 'type' => 'image', 'default' => '', 'label' => [ 'de_DE' => 'Bild im Hintergrund', 'en_US' => 'Picture behind the content' ] ],
		'picture' => [ 'type' => 'choice', 'options' => [ 'on', 'off' ], 'labels' => [ 'on' => [ 'de_DE' => 'Zeigen', 'en_US' => 'Show' ], 'off' => [ 'de_DE' => 'Verbergen', 'en_US' => 'Hide' ] ], 'default' => 'on', 'label' => [ 'de_DE' => 'Hintergrundbild zeigen', 'en_US' => 'Show background picture' ] ],
		'dim'    => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Bild abdunkeln', 'en_US' => 'Dim the picture' ] ],
		'height' => [ 'type' => 'choice', 'options' => [ 'auto', '60', '80', '100' ], 'labels' => [ 'auto' => [ 'de_DE' => 'Automatisch', 'en_US' => 'Automatic' ], '60' => [ 'de_DE' => '60 %', 'en_US' => '60 %' ], '80' => [ 'de_DE' => '80 %', 'en_US' => '80 %' ], '100' => [ 'de_DE' => 'Volle Höhe', 'en_US' => 'Full height' ] ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Höhe', 'en_US' => 'Height' ] ],
	],
];
