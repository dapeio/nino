<?php
declare(strict_types=1);

return [
	'group'      => 'image',
	'label'      => [ 'de_DE' => 'Bild', 'en_US' => 'Image' ],
	'in'         => [ 'flow' ],
	'source'     => 'image',
	'preview'    => 'image',
	'attributes' => [
		'alt'     => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Alternativtext', 'en_US' => 'Alternative text' ] ],
		'decorative' => [ 'type' => 'bool', 'default' => false, 'class' => false, 'label' => [ 'de_DE' => 'Nur Schmuck, ohne Alternativtext', 'en_US' => 'Decorative, with no alternative text' ] ],
		'ratio'   => [ 'type' => 'choice', 'options' => [ 'auto', '1-1', '4-3', '3-2', '3-4', '16-9', '21-9' ], 'default' => 'auto', 'label' => [ 'de_DE' => 'Seitenverhältnis', 'en_US' => 'Ratio' ] ],
		'focus'   => [ 'type' => 'choice', 'options' => [ 'center', 'top', 'right', 'bottom', 'left', 'top-left', 'top-right', 'bottom-left', 'bottom-right' ], 'default' => 'center', 'label' => [ 'de_DE' => 'Fokus', 'en_US' => 'Focus' ] ],
		'caption' => [ 'type' => 'text', 'default' => '', 'label' => [ 'de_DE' => 'Bildunterschrift', 'en_US' => 'Caption' ] ],
		'href'    => [ 'type' => 'link', 'default' => '', 'label' => [ 'de_DE' => 'Link', 'en_US' => 'Link' ] ],
	],
];
