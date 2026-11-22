<?php
declare(strict_types=1);

return [
	'group'      => 'footer',
	'label'      => [ 'de_DE' => 'Logo', 'en_US' => 'Logo' ],
	'icon'       => 'image',
	'in'         => [ 'footer-col', 'footer' ],
	'source'     => 'image',
	'attributes' => [
		'alt' => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Alternativtext', 'en_US' => 'Alternative text' ] ],
		'decorative' => [ 'type' => 'bool', 'default' => false, 'class' => false, 'label' => [ 'de_DE' => 'Nur Schmuck, ohne Alternativtext', 'en_US' => 'Decorative, with no alternative text' ] ],
	],
];
