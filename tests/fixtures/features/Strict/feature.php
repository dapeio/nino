<?php
// A feature whose stack cannot be rendered although its class has a doShortcode():
// a component falls back to that method, a stack does not - tests/features-smoke.php
// reads the warning the kernel raises and that the stack is not registered
return [
	'key'					=> 'strict',
	'name'				=> 'Strict',
	'version'			=> '0.1.0',
	'stacks'			=> [
		'ledger'		=> [ 'label' => 'Ledger', 'preview' => 'block' ],
	],
];
