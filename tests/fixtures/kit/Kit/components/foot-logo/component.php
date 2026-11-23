<?php
declare(strict_types=1);

return [
	'group'      => 'foot',
	'label'      => [ 'de_DE' => 'Logo', 'en_US' => 'Logo' ],
	'icon'       => 'image',
	'in'         => [ 'foot-col', 'foot' ],
	'source'     => 'image',
	'logo'       => true,
	'attributes' => [
		'alt' => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Alternativtext', 'en_US' => 'Alternative text' ] ],
		'decorative' => [ 'type' => 'bool', 'default' => false, 'class' => false, 'label' => [ 'de_DE' => 'Nur Schmuck, ohne Alternativtext', 'en_US' => 'Decorative, with no alternative text' ] ],
	],
];
