<?php return [
	// Part of the starter site the Routes step opens on, at this position
	// in the list. A proposal the operator edits or removes - not a default
	// underneath config.php, which could never be removed at all
	'preset' 		=> 3,
	'label' 		=> '404',
	'routes' 		=> [
		'GET://404' => [ 'uri' => '/404', 'body' => '[template /templates/page-404]', 'statusCode' => 404 ],
	],
	'templates' => [ 'page-404.tpl' ],
];
