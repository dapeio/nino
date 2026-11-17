<?php
declare(strict_types=1);

return [
	'group'      => 'text',
	'label'      => [ 'de_DE' => 'Abstand', 'en_US' => 'Spacer' ],
	'in'         => [ 'flow' ],
	'attributes' => [
		'size' => [ 'type' => 'choice', 'options' => [ '1', '2', '3', '4', '5', '6' ], 'labels' => [ '1' => [ 'de_DE' => 'Winzig', 'en_US' => 'Tiny' ], '2' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], '3' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], '4' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ], '5' => [ 'de_DE' => 'Sehr groß', 'en_US' => 'Very large' ], '6' => [ 'de_DE' => 'Riesig', 'en_US' => 'Huge' ] ], 'default' => '2', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
	],
];
