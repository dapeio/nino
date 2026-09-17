<?php return [
	'label'		=> 'Helper',
	// One config default and nothing else: enough for a check to see whether
	// this unit was applied, which is what "does activating something that
	// requires helper re-apply helper's unit?" comes down to
	'config'	=> [ '/helper/config' => 'unit-default' ],
];
