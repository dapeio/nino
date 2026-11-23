<?php
declare(strict_types=1);

return [
	'group'      => 'foot',
	'label'      => [ 'de_DE' => 'Menü', 'en_US' => 'Menu' ],
	'icon'       => 'menu',
	'in'         => [ 'foot-col', 'foot' ],
	'menu'       => [ 'attribute' => 'menu' ],
	'attributes' => [
		'menu' => [ 'type' => 'choice', 'options' => '\\Nino\\Modules\\Kit::menus', 'default' => 'footer', 'class' => false, 'label' => [ 'de_DE' => 'Navigation', 'en_US' => 'Navigation' ] ],
		'label'    => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Name der Navigation', 'en_US' => 'Name of the navigation' ] ],
	],
];
