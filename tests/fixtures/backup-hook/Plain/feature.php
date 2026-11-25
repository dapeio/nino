<?php
// A feature of tests/backup-hook-smoke.php that owns a file and has no hook: its file is packed as it lies
return [
	'key'					=> 'plain',
	'name'				=> 'Plain',
	'version'			=> '1.0.0',
	'nino'				=> '^2.0',
	'data'				=> [ '/data/plain.db', '/data/vault.db-old', '/data/vault.db-journal' ],
];
