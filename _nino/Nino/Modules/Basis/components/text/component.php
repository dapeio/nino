<?php
declare(strict_types=1);

return [
	'group'      => 'text',
	'label'      => [ 'de_DE' => 'Text', 'en_US' => 'Text' ],
	'in'         => [ 'flow' ],
	'source'     => 'text',
	'format'     => 'blocks',
	'preview'    => 'text',
	'attributes' => [
		'format' => [ 'type' => 'choice', 'options' => [ 'inline', 'lines', 'blocks' ], 'default' => 'blocks', 'class' => false, 'label' => [ 'de_DE' => 'Format', 'en_US' => 'Format' ] ],
		'size'   => [ 'type' => 'choice', 'options' => [ 's', 'm', 'l' ], 'default' => 'm', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
	],
];
