<?php
// What a request that includes this file leaves behind, for the suite to count
$GLOBALS['ninoRegisterReads'][] = 'probe-box';

return [
	'group'			=> 'probe',
	'label'			=> [ 'en_US' => 'Box', 'de_DE' => 'Kasten' ],
	'in'				=> [ 'page', 'flow' ],
	'offers'		=> [ 'flow' ],
	'variants'	=> [ 'quiet' ],
	'attributes'	=> [
		'size'	=> [ 'type' => 'choice', 'options' => [ 's', 'm', 'l' ], 'default' => 'm' ],
	],
];
