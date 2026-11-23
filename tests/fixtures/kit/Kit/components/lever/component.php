<?php
declare(strict_types=1);

return [
	'group'      => 'lever',
	'label'      => [ 'de_DE' => 'Button', 'en_US' => 'Button' ],
	'icon'       => 'rectangle-horizontal',
	'in'         => [ 'flow', 'lever-group' ],
	'source'     => 'text',
	'preview'    => 'button',
	'attributes' => [
		'href'   => [ 'type' => 'link', 'default' => '', 'label' => [ 'de_DE' => 'Link', 'en_US' => 'Link' ] ],
		'target' => [ 'type' => 'choice', 'options' => [ 'self', 'blank' ], 'labels' => [ 'self' => [ 'de_DE' => 'Gleiches Fenster', 'en_US' => 'Same window' ], 'blank' => [ 'de_DE' => 'Neues Fenster', 'en_US' => 'New window' ] ], 'default' => 'self', 'class' => false, 'label' => [ 'de_DE' => 'Öffnen in', 'en_US' => 'Open in' ] ],
		'tone'   => [ 'type' => 'choice', 'options' => [ 'primary', 'secondary', 'outline', 'quiet' ], 'labels' => [ 'primary' => [ 'de_DE' => 'Hauptknopf', 'en_US' => 'Main button' ], 'secondary' => [ 'de_DE' => 'Zweite Farbe', 'en_US' => 'Second colour' ], 'outline' => [ 'de_DE' => 'Umriss', 'en_US' => 'Outline' ], 'quiet' => [ 'de_DE' => 'Ruhig', 'en_US' => 'Quiet' ] ], 'default' => 'primary', 'label' => [ 'de_DE' => 'Gewicht', 'en_US' => 'Tone' ] ],
		'size'   => [ 'type' => 'choice', 'options' => [ 's', 'm', 'l' ], 'labels' => [ 's' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], 'm' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], 'l' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ] ], 'default' => 'm', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
		'wide'   => [ 'type' => 'bool', 'default' => false, 'label' => [ 'de_DE' => 'Volle Breite', 'en_US' => 'Full width' ] ],
	],
];
