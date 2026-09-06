<?php
// The Search module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/search]]'						=> 'Search',
	'[[/_admin/search/label/title]]'		=> 'Elements search index',
	'[[/_admin/search/hint/intro]]'			=> 'Recreates every index configured under /nino/elements/index in config.php. The index is also rebuilt on every save of a configured type – press this after a configuration change, a manual element-file edit, or an interrupted write.',
	'[[/_admin/search/label/create]]'		=> 'Create searchindex',
	'[[/_admin/search/msg/creating]]'		=> 'Creating search indexes …',
	'[[/_admin/search/msg/none]]'				=> 'No search indexes are configured.',
	'[[/_admin/search/msg/created]]'		=> 'Created %d search index for %n elements.',
	'[[/_admin/search/msg/created-plural]]'	=> 'Created %d search indexes for %n elements.',
	'[[/_admin/search/error/create]]'		=> 'Failed to create search indexes.',
];
