<?php
declare(strict_types=1);

return [
	'group'      => 'crown',
	'label'      => [ 'de_DE' => 'Logo', 'en_US' => 'Logo' ],
	'icon'       => 'image',
	'in'         => [ 'crown-wrap', 'crown' ],
	'once'       => true,
	'attributes' => [
		'light' => [ 'type' => 'image', 'default' => '/logo', 'label' => [ 'de_DE' => 'Bild für hellen Grund', 'en_US' => 'Picture for a light ground' ] ],
		'dark'  => [ 'type' => 'image', 'default' => '/logo/dark', 'label' => [ 'de_DE' => 'Bild für dunklen Grund', 'en_US' => 'Picture for a dark ground' ] ],
		'href'  => [ 'type' => 'link', 'default' => 'page:/home', 'label' => [ 'de_DE' => 'Ziel', 'en_US' => 'Target' ] ],
	],
];
