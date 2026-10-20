<?php
// The Logs module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/logs]]'					=> 'Log',
	'[[/_admin/logs/error/load]]'					=> 'Failed to load.',
	'[[/_admin/logs/empty]]'							=> 'No entries yet.',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/logs/manual/intro]]'	=> 'Who changed what, and when: every login and every change made in the workbench, with the time and the account.',
	'[[/_admin/logs/manual/list]]'	=> 'The newest line is at the top. Nothing is written here by hand and nothing can be changed.',
	'[[/_admin/logs/manual/day]]'	=> 'Lines are kept for fourteen days. Whether they are written at all is a setting under Config.',
];
