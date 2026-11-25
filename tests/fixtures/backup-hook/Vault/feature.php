<?php
// The feature of tests/backup-hook-smoke.php that hands a copy of its data over before a backup packs it - and, by the mode the test sets,
// hands over what it must not
return [
	'key'					=> 'vault',
	'name'				=> 'Vault',
	'version'			=> '1.0.0',
	'nino'				=> '^2.0',
	'data'				=> [ '/data/vault.db', '/data/vault.php', '/data/vault-dir', '/data/shared.db', '/data/crm.sqlite' ],
];
