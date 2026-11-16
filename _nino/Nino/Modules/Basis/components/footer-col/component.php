<?php
declare(strict_types=1);

return [
	'group'      => 'footer',
	'label'      => [ 'de_DE' => 'Spalte', 'en_US' => 'Column' ],
	'in'         => [ 'footer-wrap' ],
	'offers'     => [ 'flow' ],
	'attributes' => [
		'size' => [ 'type' => 'choice', 'options' => [ '25', '33', '50', '66', '75', '100' ], 'default' => '100', 'responsive' => true, 'label' => [ 'de_DE' => 'Breite', 'en_US' => 'Width' ] ],
	],
];
