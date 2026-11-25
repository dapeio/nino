<?php
// The second feature with a hook of tests/backup-hook-smoke.php: its own file, and one it shares with Vault on paper
return [
	'key'					=> 'other',
	'name'				=> 'Other',
	'version'			=> '1.0.0',
	'nino'				=> '^2.0',
	'data'				=> [ '/data/other.db', '/data/shared.db' ],
];
