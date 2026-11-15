<?php
// features/Hours/install/locations.php - the element type a place with opening
// hours is, copied to /elements/locations.php where a project has none.
//
// The name and the note are per language, the address and the seven days are
// the same in every language (a type file is copied byte for byte, so nothing
// here names a language). A day is text in one fixed notation, 9-12, 14-18:
// the feature reads it when an element is saved (Hours::callbackLocations()),
// refuses what it cannot read and stores the normal form 09:00-12:00, 14:00-18:00
// - an empty day is a closed day. The type holds no element: an address and
// hours on a public page are the owner's to state.
return [
	'title' => 'Locations',
	'model' => [
		'name' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'required' 	=> true,
			'maxlength' => 120,
		],
		'address' => [
			'type' 			=> 'string',
			'breaks' 		=> true,
			'maxlength' => 300,
			'inputsize' => 3,
		],
		'mon' => [ 'type' => 'string', 'maxlength' => 200 ],
		'tue' => [ 'type' => 'string', 'maxlength' => 200 ],
		'wed' => [ 'type' => 'string', 'maxlength' => 200 ],
		'thu' => [ 'type' => 'string', 'maxlength' => 200 ],
		'fri' => [ 'type' => 'string', 'maxlength' => 200 ],
		'sat' => [ 'type' => 'string', 'maxlength' => 200 ],
		'sun' => [ 'type' => 'string', 'maxlength' => 200 ],
		'note' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'breaks' 		=> true,
			'maxlength' => 300,
			'inputsize' => 3,
		],
	],
	'*' => [
		'*' => [],
	],
];
