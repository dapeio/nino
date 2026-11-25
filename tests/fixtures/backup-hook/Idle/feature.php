<?php
// A feature of tests/backup-hook-smoke.php that is never switched on: it has a hook, which nothing may call
return [
	'key'					=> 'idle',
	'name'				=> 'Idle',
	'version'			=> '1.0.0',
	'nino'				=> '^2.0',
	'data'				=> [ '/data/idle.db' ],
];
