<?php
declare(strict_types=1);

return [
	'group'      => 'header',
	'label'      => [ 'de_DE' => 'Kopfbereich', 'en_US' => 'Header' ],
	'icon'       => 'panel-top',
	'in'         => [ 'page' ],
	'offers'     => [],
	'attributes' => [
		'id' => [ 'type' => 'id', 'default' => '', 'label' => 'Id' ],
		'sticky' => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Bleibt oben stehen', 'en_US' => 'Stays at the top' ] ],
	],
];
