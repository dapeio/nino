<?php
declare(strict_types=1);

return [
	'group'      => 'button',
	'label'      => [ 'de_DE' => 'Button', 'en_US' => 'Button' ],
	'in'         => [ 'flow', 'button-group' ],
	'source'     => 'text',
	'preview'    => 'button',
	'attributes' => [
		'href'   => [ 'type' => 'link', 'default' => '', 'label' => [ 'de_DE' => 'Link', 'en_US' => 'Link' ] ],
		'target' => [ 'type' => 'choice', 'options' => [ 'self', 'blank' ], 'default' => 'self', 'class' => false, 'label' => [ 'de_DE' => 'Öffnen in', 'en_US' => 'Open in' ] ],
		'tone'   => [ 'type' => 'choice', 'options' => [ 'primary', 'quiet' ], 'default' => 'primary', 'label' => [ 'de_DE' => 'Gewicht', 'en_US' => 'Tone' ] ],
		'size'   => [ 'type' => 'choice', 'options' => [ 's', 'm', 'l' ], 'default' => 'm', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
		'wide'   => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Volle Breite', 'en_US' => 'Full width' ] ],
	],
];
