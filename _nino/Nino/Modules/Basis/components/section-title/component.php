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
		'tag'  => [ 'type' => 'choice', 'options' => [ 'h1', 'h2', 'h3', 'h4', 'div' ], 'labels' => [ 'h1' => [ 'de_DE' => 'Überschrift 1', 'en_US' => 'Heading 1' ], 'h2' => [ 'de_DE' => 'Überschrift 2', 'en_US' => 'Heading 2' ], 'h3' => [ 'de_DE' => 'Überschrift 3', 'en_US' => 'Heading 3' ], 'h4' => [ 'de_DE' => 'Überschrift 4', 'en_US' => 'Heading 4' ], 'div' => [ 'de_DE' => 'Ohne Überschrift', 'en_US' => 'No heading' ] ], 'default' => 'h2', 'class' => false, 'label' => [ 'de_DE' => 'Element', 'en_US' => 'Element' ] ],
		'size' => [ 'type' => 'choice', 'options' => [ 's', 'm', 'l', 'xl' ], 'labels' => [ 's' => [ 'de_DE' => 'Klein', 'en_US' => 'Small' ], 'm' => [ 'de_DE' => 'Mittel', 'en_US' => 'Medium' ], 'l' => [ 'de_DE' => 'Groß', 'en_US' => 'Large' ], 'xl' => [ 'de_DE' => 'Sehr groß', 'en_US' => 'Very large' ] ], 'default' => 'm', 'label' => [ 'de_DE' => 'Größe', 'en_US' => 'Size' ] ],
		'href' => [ 'type' => 'link', 'default' => '', 'label' => [ 'de_DE' => 'Link', 'en_US' => 'Link' ] ],
	],
];
