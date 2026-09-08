<?php
// The Maintenance module's own workbench strings, merged into its fills
// while the module is active (see the panel's text()) - same keys and
// shape the workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/maintenance]]'						=> 'Maintenance',
	'[[/_admin/maintenance/state/online]]'		=> 'The site is online.',
	'[[/_admin/maintenance/state/offline]]'	=> 'The site shows the maintenance page.',
	'[[/_admin/maintenance/label/status]]'		=> 'Show the maintenance page',
	'[[/_admin/maintenance/hint/status]]'		=> 'Every visitor gets a 503 answer instead of the site while this is on.',
	'[[/_admin/maintenance/label/retry]]'		=> 'Ask visitors to retry after',
	'[[/_admin/maintenance/hint/retry]]'			=> 'Sent as the Retry-After header, so a well-behaved browser or bot waits this long before trying again.',
	'[[/_admin/maintenance/hint/signedin]]'	=> 'A signed-in account still sees the site as it is – open it in another browser, or log out, to check what a visitor sees.',
	'[[/_admin/dashboard/label/maintenance]]'	=> 'Maintenance on',
];
