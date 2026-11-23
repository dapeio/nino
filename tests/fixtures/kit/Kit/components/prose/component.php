<?php
declare(strict_types=1);

return [
	'group'      => 'prose',
	'label'      => [ 'de_DE' => 'Text', 'en_US' => 'Text' ],
	'icon'       => 'text',
	'in'         => [ 'flow' ],
	'source'     => 'text',
	'format'     => 'blocks',
	'preview'    => 'text',
	'attributes' => [
		'format' => [ 'type' => 'choice', 'options' => [ 'inline', 'lines', 'blocks' ], 'labels' => [ 'inline' => [ 'de_DE' => 'Fortlaufend', 'en_US' => 'Running text' ], 'lines' => [ 'de_DE' => 'Zeilen', 'en_US' => 'Lines' ], 'blocks' => [ 'de_DE' => 'Absätze', 'en_US' => 'Paragraphs' ] ], 'default' => 'blocks', 'class' => false, 'label' => [ 'de_DE' => 'Format', 'en_US' => 'Format' ] ],
		'size'   => [ 'type' => 'choice', 'options' => [ 's', 'm', 'l' ], 'labels' => [ 's' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], 'm' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], 'l' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ] ], 'default' => 'm', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
	],
];
