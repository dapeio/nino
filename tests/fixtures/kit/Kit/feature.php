<?php
// tests/fixtures/kit/Kit/feature.php - the manifest of the test feature of the kernel's suites: a feature a project has, below a features directory
// the suite names (NINO_FEATURES_DIR), with components, groups and blocks in folders. It has nothing of Basis but its shape.
return [
	'key'					=> 'kit',
	'name'				=> [ 'en_US' => 'Kit', 'de_DE' => 'Bausatz' ],
	'description'	=> [ 'en_US' => 'The parts the suites of the kernel build pages from.', 'de_DE' => 'Die Teile, aus denen die Suiten des Kernels Seiten bauen.' ],
	'category'		=> 'content',
	'version'			=> '1.0.0',
];
