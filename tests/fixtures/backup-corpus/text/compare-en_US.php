<?php
declare(strict_types=1);

// The three words a pair carries - merged into the project's text/en_US.php
// on every activation (add-only: a key the project already has stays, one it
// lacks is added); editors keep them current in the Text panel from then on.
return [

	// What the two sides are called where the shortcode did not say. A
	// project that writes before-label="Rohbau" says something these cannot
	'[[/feature/compare/side/before]]'	=> 'Before',
	'[[/feature/compare/side/after]]'	=> 'After',

	// What the control is, for somebody who reaches it with the keyboard and
	// has never seen the two pictures
	'[[/feature/compare/handle/label]]'	=> 'Move the divider between the two pictures',
];
