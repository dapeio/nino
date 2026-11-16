<?php
$GLOBALS['ninoRegisterReads'][] = 'probe-note';

// No template.tpl and no renderer: the markup is the schema's own
return [
	'group'			=> 'probe',
	'label'			=> 'Note',
	'in'				=> [ 'page', 'flow' ],
	'source'		=> 'text',
	'template'	=> '<i class="[[class]]">[[value]]</i>',
];
