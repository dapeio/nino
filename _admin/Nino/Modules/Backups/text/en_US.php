<?php
// The Backups module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/backups]]'						=> 'Backups',
	'[[/_admin/backups/empty]]'					=> 'No backup available yet.',
	'[[/_admin/backups/confirm/restore]]'	=> 'Restore the backup from %s? The current state is backed up automatically first. Afterwards you are logged out and land on the login page.',
	'[[/_admin/backups/label/login]]'	=> 'To the login',
	'[[/_admin/backups/msg/restored]]'	=> 'The backup from %s has been restored.',
	'[[/_admin/backups/label/now]]'			=> 'Back up now',
	'[[/_admin/backups/msg/created]]'		=> 'Backup created: %s',
	'[[/_admin/backups/msg/missed]]'		=> 'Today’s automatic backup did not run: %s. Nino does not try again by itself today; Back up now makes one once the cause is out of the way.',
	'[[/_admin/backups/msg/interrupted]]'	=> 'Today’s automatic backup did not finish: the request that was making it ended, most likely because the server ran out of memory or time. Nino does not try again by itself today; Back up now makes one.',
	'[[/_admin/error/backups_restored_other_version]]'	=> 'The backup comes from Nino %s; this is %s.',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/backups/manual/intro]]'	=> 'A backup is a copy of your website’s content as it was on one day. Nino takes one by itself every day and keeps fourteen days; Config switches that off.',
	'[[/_admin/backups/manual/backup]]'	=> 'Back up now takes a copy at once. The list shows the days that have a backup.',
	'[[/_admin/backups/manual/restore]]'	=> 'Choose a day to restore it; Nino asks first. The current state is backed up before, so a restore can be taken back. A restore ends your session: afterwards you are logged out and log in again.',
];
