<?php return [
	'label' 		=> '404',
	'routes' 		=> [
		'GET://404' => [ 'uri' => '/404', 'body' => '[template /templates/page-404]', 'statusCode' => 404 ],
	],
	'templates' => [ 'page-404.tpl' ],
];
