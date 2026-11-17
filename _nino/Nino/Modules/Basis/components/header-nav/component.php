<?php
declare(strict_types=1);

return [
	'group'      => 'header',
	'label'      => [ 'de_DE' => 'Menü', 'en_US' => 'Menu' ],
	'icon'       => 'menu',
	'in'         => [ 'header-wrap' ],
	'once'       => true,
	'attributes' => [
		'menu'     => [ 'type' => 'choice', 'options' => '\\Nino\\Modules\\Basis::menus', 'default' => 'main', 'class' => false, 'label' => [ 'de_DE' => 'Navigation', 'en_US' => 'Navigation' ] ],
		'collapse' => [ 'type' => 'choice', 'options' => [ 'small', 'always', 'never' ], 'labels' => [ 'small' => [ 'de_DE' => 'Nur auf kleinen Bildschirmen', 'en_US' => 'On small screens only' ], 'always' => [ 'de_DE' => 'Immer', 'en_US' => 'Always' ], 'never' => [ 'de_DE' => 'Nie', 'en_US' => 'Never' ] ], 'default' => 'small', 'label' => [ 'de_DE' => 'Menü-Knopf', 'en_US' => 'Menu button' ] ],
		'label'    => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Name der Navigation', 'en_US' => 'Name of the navigation' ] ],
	],
];
