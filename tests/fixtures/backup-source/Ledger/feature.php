<?php
// The feature of tests/backup-source-smoke.php: a database below data/ (the file, and a folder with a database of its own and files beside it) and files
// that are named like what a backup leaves out where it is gone - a companion of a database, a temporary file of a writer - but that the feature
// declares by name, which makes them its own
return [
	'key'					=> 'ledger',
	'name'				=> 'Ledger',
	'version'			=> '1.0.0',
	'nino'				=> '^2.0',
	'data'				=> [ '/data/ledger.sqlite', '/data/ledger', '/data/foo-wal', '/data/looks.0123456789ab.tmp', '/data/plain.sqlite', '/data/plain.sqlite-wal' ],
];
