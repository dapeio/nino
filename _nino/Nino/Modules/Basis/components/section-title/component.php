<?php
declare(strict_types=1);

return [
	'group'      => 'section',
	'label'      => [ 'de_DE' => 'Titel', 'en_US' => 'Title' ],
	'in'         => [ 'section-col' ],
	'source'     => 'text',
	'format'     => 'inline',
	'preview'    => 'title',
	'attributes' => [
		'tag'  => [ 'type' => 'choice', 'options' => [ 'h1', 'h2', 'h3', 'h4', 'div' ], 'default' => 'h2', 'class' => false, 'label' => [ 'de_DE' => 'Element', 'en_US' => 'Element' ] ],
		'size' => [ 'type' => 'choice', 'options' => [ 's', 'm', 'l', 'xl' ], 'default' => 'm', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
		'href' => [ 'type' => 'link', 'default' => '', 'label' => [ 'de_DE' => 'Link', 'en_US' => 'Link' ] ],
	],
];
