<?php
// A feature's contribution to the privacy policy: two sections added to the
// type 'privacy' by the unit's 'elements' key (see \Nino\Elements::seed()).
// The model is only used where the project has no such type yet
return [
	'title' => [ 'de_DE' => 'Datenschutzerklärung', 'en_US' => 'Privacy policy' ],
	'model' => [
		'title' 	=> [ 'type' => 'string', 'locale' => true, 'required' => true ],
		'text' 		=> [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ],
		'order' 	=> [ 'type' => 'integer' ],
		'hidden' 	=> [ 'type' => 'boolean' ],
	],
	'*' => [
		'*' 				=> [ 'hidden' => false ],
		'sample' 		=> [ 'order' => 450 ],
		'sample-more' => [ 'order' => 460 ],
	],
	'de_DE' => [
		'sample' 		=> [ 'title' => 'Beispiel', 'text' => '<p>Das Beispiel-Feature speichert nichts.</p>' ],
		'sample-more' => [ 'title' => 'Beispiel mehr', 'text' => '<p>Mehr dazu.</p>' ],
	],
	'en_US' => [
		'sample' 		=> [ 'title' => 'Sample', 'text' => '<p>The sample feature stores nothing.</p>' ],
		'sample-more' => [ 'title' => 'Sample more', 'text' => '<p>More on it.</p>' ],
	],
];
