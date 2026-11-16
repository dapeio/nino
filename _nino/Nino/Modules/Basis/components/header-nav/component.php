<?php
declare(strict_types=1);

return [
	'group'      => 'header',
	'label'      => [ 'de_DE' => 'Menü', 'en_US' => 'Menu' ],
	'in'         => [ 'header-wrap' ],
	'once'       => true,
	'attributes' => [
		'menu'     => [ 'type' => 'choice', 'options' => '\\Nino\\Modules\\Basis::menus', 'default' => 'main', 'class' => false, 'label' => [ 'de_DE' => 'Navigation', 'en_US' => 'Navigation' ] ],
		'collapse' => [ 'type' => 'choice', 'options' => [ 'small', 'always', 'never' ], 'default' => 'small', 'label' => [ 'de_DE' => 'Menü-Knopf', 'en_US' => 'Menu button' ] ],
		'label'    => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Name der Navigation', 'en_US' => 'Name of the navigation' ] ],
	],
];
