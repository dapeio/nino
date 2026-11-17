<?php
declare(strict_types=1);

return [
	'group'      => 'footer',
	'label'      => [ 'de_DE' => 'Spalte', 'en_US' => 'Column' ],
	'icon'       => 'columns-3',
	'in'         => [ 'footer-wrap' ],
	'offers'     => [ 'flow' ],
	'attributes' => [
		'size' => [ 'type' => 'choice', 'options' => [ '25', '33', '50', '66', '75', '100' ], 'labels' => [ '25' => [ 'de_DE' => '25 %', 'en_US' => '25 %' ], '33' => [ 'de_DE' => '33 %', 'en_US' => '33 %' ], '50' => [ 'de_DE' => '50 %', 'en_US' => '50 %' ], '66' => [ 'de_DE' => '66 %', 'en_US' => '66 %' ], '75' => [ 'de_DE' => '75 %', 'en_US' => '75 %' ], '100' => [ 'de_DE' => '100 %', 'en_US' => '100 %' ] ], 'default' => '100', 'responsive' => true, 'label' => [ 'de_DE' => 'Breite', 'en_US' => 'Width' ] ],
	],
];
