<?php
declare(strict_types=1);

return [
	'group'      => 'text',
	'label'      => [ 'de_DE' => 'Abstand', 'en_US' => 'Spacer' ],
	'in'         => [ 'flow' ],
	'attributes' => [
		'size' => [ 'type' => 'choice', 'options' => [ '1', '2', '3', '4', '5', '6' ], 'default' => '2', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
	],
];
