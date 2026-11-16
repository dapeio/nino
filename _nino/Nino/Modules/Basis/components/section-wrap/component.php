<?php
declare(strict_types=1);

return [
	'group'      => 'section',
	'label'      => [ 'de_DE' => 'Abschnitt', 'en_US' => 'Section' ],
	'in'         => [ 'page' ],
	'offers'     => [],
	'attributes' => [
		'id'     => [ 'type' => 'id', 'default' => '', 'label' => 'Id' ],
		'ground' => [ 'type' => 'choice', 'options' => [ 'base', 'alt', 'tint', 'primary', 'dark' ], 'default' => 'base', 'label' => [ 'de_DE' => 'Grund', 'en_US' => 'Ground' ] ],
		'image'  => [ 'type' => 'image', 'default' => '', 'label' => [ 'de_DE' => 'Bild im Hintergrund', 'en_US' => 'Picture behind the content' ] ],
		'dim'    => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Bild abdunkeln', 'en_US' => 'Dim the picture' ] ],
		'height' => [ 'type' => 'choice', 'options' => [ 'auto', '60', '80', '100' ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Höhe', 'en_US' => 'Height' ] ],
	],
];
