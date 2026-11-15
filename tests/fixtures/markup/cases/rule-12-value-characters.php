<?php
declare(strict_types=1);

// In a value \" is ", \\ is \, any other \ stays, and [ ] ' [[ ]] and a tab are what they are
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [ 'v' => "x\"y\\z\\qw [k] ]] '1' \tt", 'w' => '[[k]]' ], 'c' => null, 'o' => 0 ],
	],
	'notes'			=> [],
];
