<?php
$GLOBALS['ninoRegisterReads'][] = 'probe-line';

return [
	'group'			=> 'probe',
	'label'			=> 'Line',
	'in'				=> [ 'probe-box' ],
	'source'		=> 'text',
	'attributes'	=> [
		'tone'	=> [ 'type' => 'choice', 'options' => '\Nino\Modules\Probe::tones', 'default' => 'calm' ],
	],
];
