<?php
// features/Logos/install/logo.php - the element type a logo is, copied to
// /elements/logo.php where a project has none.
//
// Five fields, all global: the name of a customer and its address are the same
// in every language, and a type file is copied byte for byte. 'name' is the alt
// text of the picture. 'image' is set into the box it declares when it is
// uploaded ('fit': the longest side takes the box, the proportions stay, nothing
// is cut off and a smaller picture is not scaled up) - 540 by 210 pixels, the
// ratio of a row of logos at its middle height (18 to 7) - so a wide wordmark and
// a square badge keep every edge. 'image-dark' is the picture for a dark ground
// and is optional: [logos /logo ...] draws it beside the first, and the ground it stands on
// says which of the two is seen. 'url' is where a click goes, left empty for a
// logo that links nowhere. 'order' is the position: sort="order" puts the smaller
// first, and a logo whose position was left empty stands last, in the order of the
// elements. sort="name" asks for another.
//
// No element is shipped: a logo is a customer's picture, and one that is not
// theirs is one they have to find and delete.
return [
	'title' => 'Logos',
	'model' => [
		'name' => [
			'type' 			=> 'string',
			'required' 	=> true,
			'maxlength' => 120,
		],
		'image' => [
			'type' 		=> 'image',
			'width' 	=> 540,
			'height' 	=> 210,
			'fit' 		=> true,
		],
		'url' => [
			'type' 			=> 'string',
			'maxlength' => 500,
		],
		'image-dark' => [
			'type' 		=> 'image',
			'width' 	=> 540,
			'height' 	=> 210,
			'fit' 		=> true,
		],
		'order' => [
			'type' 			=> 'integer',
		],
	],
	'*' => [
		'*' => [],
	],
];
