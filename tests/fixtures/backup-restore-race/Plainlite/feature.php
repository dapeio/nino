<?php
// A feature of tests/backup-restore-race-smoke.php that owns a database and has no hook: its file is packed as it lies
return [
	'key'					=> 'plainlite',
	'name'				=> 'Plainlite',
	'version'			=> '1.0.0',
	'nino'				=> '^2.0',
	'data'				=> [ '/data/plain.sqlite' ],
];
