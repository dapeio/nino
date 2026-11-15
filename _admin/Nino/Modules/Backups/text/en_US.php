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
	'[[/_admin/backups/msg/stopped-time]]'	=> 'The backup that began on %s was ended by the server: its time limit (%s s) ran out. To pack: %s MB, files: %s.',
	'[[/_admin/backups/msg/stopped-memory]]'	=> 'The backup that began on %s was ended by the server: its memory limit (%s MB) ran out. To pack: %s MB, files: %s.',
	'[[/_admin/backups/msg/stopped-open]]'	=> 'The backup that began on %s did not end, and the server did not say why - most likely its time or memory limit ran out. To pack: %s MB, files: %s.',
	'[[/_admin/backups/msg/stopped-retry]]'	=> 'Nino does not try again by itself today; Back up now makes one once the cause is out of the way.',
	'[[/_admin/backups/msg/reason-write]]'	=> 'there was no room left, in the temporary directory or where the backups are kept, or a file could not be written',
	'[[/_admin/error/backups_write]]'	=> 'There was no room left to write the backup, in the temporary directory or where the backups are kept, or a file could not be written. The backups that exist are untouched.',
	'[[/_admin/error/backups_restored_other_version]]'	=> 'The backup comes from Nino %s; this is %s.',
	'[[/_admin/error/backups_busy]]'	=> 'A backup or a restore is running. Try again in a moment.',

	// The manual the "?" of the panel opens (see \Nino\Admin\Admin::manual()): the line of each handle its manual() names
	'[[/_admin/backups/manual/intro]]'	=> 'A backup is a copy of your website’s content as it was on one day. Nino takes one by itself every day and keeps the newest few; how many is set in Config, which also switches it off.',
	'[[/_admin/backups/manual/backup]]'	=> 'Back up now takes a copy at once. The list shows the days that have a backup.',
	'[[/_admin/backups/manual/restore]]'	=> 'Choose a day to restore it; Nino asks first. The current state is backed up before, so a restore can be taken back. A restore ends your session: afterwards you are logged out and log in again.',
];
