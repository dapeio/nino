<?php
// features/Hours/install/closures.php - the element type a deviation from the
// weekly hours is (a holiday, a trip, a short day), copied to /elements/closures.php
// where a project has none.
//
// 'location' is a reference to one element of the type locations; empty means
// every location. 'from' and 'until' are dates, 'until' empty is the one day
// 'from'. 'times' has the notation of a day of a location, empty is closed.
// The feature refuses a date that does not exist, an 'until' before 'from',
// unreadable times and a location that is not there when an element is saved
// (Hours::callbackClosures()). 'reason' is per language. No element is shipped.
return [
	'title' => 'Closures',
	'model' => [
		'location' => [
			'type' 				=> 'element',
			'elementType' => 'locations',
		],
		'from' => [
			'type' 			=> 'date',
			'required' 	=> true,
		],
		'until' => [
			'type' 			=> 'date',
		],
		'times' => [
			'type' 			=> 'string',
			'maxlength' => 200,
		],
		'reason' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'maxlength' => 200,
		],
	],
	'*' => [
		'*' => [],
	],
];
