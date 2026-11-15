<?php
declare(strict_types=1);

// The first word is the source where it begins with ~ / or .; the parser does not count segments
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '~title', 'a' => [], 'c' => null, 'o' => 0 ],
		[ 'n' => 'b', 's' => '/a/b/c/d', 'a' => [], 'c' => null, 'o' => 10 ],
		[ 'n' => 'c', 's' => '/templates/html-header', 'a' => [], 'c' => null, 'o' => 22 ],
		[ 'n' => 'd', 's' => '/logo', 'a' => [], 'c' => null, 'o' => 48 ],
		[ 'n' => 'e', 's' => '.field', 'a' => [], 'c' => null, 'o' => 57 ],
		[ 'n' => 'f', 's' => '/a/b/c/d/e', 'a' => [], 'c' => null, 'o' => 67 ],
	],
	'notes'			=> [],
];
