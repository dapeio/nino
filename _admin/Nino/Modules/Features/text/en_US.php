<?php
// The Features module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has. A %s is filled by the script: the
// features directory, a version, a list of keys
return [
	'[[/_admin/nav/features]]'								=> 'Features',
	'[[/_admin/features/label/active]]'				=> 'Active features',
	'[[/_admin/features/hint/intro]]'					=> 'Every feature installed under %s. Drop a feature directory there and switch it on here; what it needs to know is saved as its settings in config.php, and switching it off keeps all of that for the next time.',
	'[[/_admin/features/hint/empty]]'					=> 'No features are installed. A feature is one directory under %s, with a feature.php manifest beside its class.',
	'[[/_admin/features/label/version]]'			=> 'Version %s',
	'[[/_admin/features/label/installed]]'		=> 'installed as %s',
	'[[/_admin/features/label/requires]]'			=> 'Requires: %s',
	'[[/_admin/features/label/settings]]'			=> 'Settings',
	'[[/_admin/features/label/none]]'					=> '– none –',
	'[[/_admin/features/status/active]]'			=> 'Active',
	'[[/_admin/features/status/inactive]]'		=> 'Inactive',
	'[[/_admin/features/status/update]]'			=> 'Update available',
	'[[/_admin/features/status/incompatible]]'	=> 'Not compatible',
	'[[/_admin/features/label/activate]]'			=> 'Activate',
	'[[/_admin/features/label/deactivate]]'		=> 'Deactivate',
	'[[/_admin/features/label/update]]'				=> 'Update to %s',
	'[[/_admin/features/hint/secret-set]]'		=> 'A value is stored. Leave the field empty to keep it, or type a new one to replace it.',
	'[[/_admin/features/hint/secret-unset]]'	=> 'Nothing is stored yet.',
	'[[/_admin/features/hint/lines]]'					=> 'One entry per line.',
	'[[/_admin/features/msg/activating]]'			=> 'Activating …',
	'[[/_admin/features/msg/activated]]'			=> 'Activated.',
	'[[/_admin/features/msg/deactivating]]'		=> 'Deactivating …',
	'[[/_admin/features/msg/deactivated]]'		=> 'Deactivated.',
	'[[/_admin/features/msg/updating]]'				=> 'Updating …',
	'[[/_admin/features/msg/updated]]'				=> 'Updated.',
	'[[/_admin/features/msg/reload]]'					=> 'The workbench is reloading so its navigation shows the change …',
	'[[/_admin/features/error/activate]]'			=> 'The feature could not be activated.',
	'[[/_admin/features/error/deactivate]]'		=> 'The feature could not be deactivated.',
	'[[/_admin/features/error/update]]'				=> 'The feature could not be updated.',
];
