<?php
// A stray directory named like a module of the kernel (\Nino\Modules\Elements): the autoloader serves
// the kernel's class, so the manifest is refused, and the class listed in the modules is the kernel's own,
// not this feature's - tests/features-smoke.php reads that it is no feature that runs unfinished
return [
	'key'		=> 'elements-stray',
	'name'	=> 'Elements',
	'version'	=> '0.1.0',
];
