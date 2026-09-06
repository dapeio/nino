<?php
// The Text module's own workbench strings, merged into its fills while
// the module is active (see the panel's text()) - same keys and shape the
// workbench's own text/<locale>.php has
return [
	'[[/_admin/nav/text]]'					=> 'Text',
	'[[/_admin/text/label/save]]'					=> 'Save',
	'[[/_admin/text/label/back]]'					=> 'Back to list',
	'[[/_admin/text/msg/pending]]'					=> 'Saving …',
	'[[/_admin/text/msg/saved]]'						=> 'Saved.',
	'[[/_admin/text/error/save]]'					=> 'Save failed.',
	'[[/_admin/text/error/load]]'					=> 'Failed to load.',
	'[[/_admin/nav/keys]]'							=> 'Text Keys',
	'[[/_admin/keys/empty]]'						=> 'No text keys yet. Create one below or scan the templates.',
	'[[/_admin/keys/label/scan]]'				=> 'Scan templates for missing keys',
	'[[/_admin/keys/label/new]]'				=> 'New text key',
	'[[/_admin/keys/label/blacklist]]'	=> 'Hidden from the Text panel',
	'[[/_admin/keys/label/perlocale]]'	=> 'Per language',
	'[[/_admin/keys/label/key]]'				=> 'Key (eg. /home/welcome/subtitle)',
	'[[/_admin/keys/label/global-hint]]'	=> 'Global (one translation for all languages, instead of one per language)',
	'[[/_admin/keys/label/initial]]'		=> 'Initial value (for global, or as a starting point for every language)',
	'[[/_admin/keys/label/scan-value]]'	=> 'Starting value for every language',
	'[[/_admin/keys/label/scan-create]]'	=> 'Apply',
	'[[/_admin/keys/label/scan-ignore]]'	=> 'Ignore permanently',
	'[[/_admin/keys/scan/hint]]'			=> 'A key with a value is created in every language. A key left empty is passed over this once and comes back on the next scan. A key marked “Ignore permanently” is retired: it leaves this scan and the Text panel, and the Text Keys list is where you can bring it back.',
	'[[/_admin/keys/scan/result]]'		=> '%c key(s) created, %i permanently ignored, %s left for later.',
	'[[/_admin/keys/scan/none]]'				=> 'No missing keys found. Every key referenced in templates/*.tpl is already defined.',
	'[[/_admin/keys/confirm/delete]]'		=> 'Really delete text key “%s”? The value is lost in every language.',
	'[[/_admin/keys/error/save-partial]]'	=> 'Save failed for %s',
	'[[/_admin/dashboard/label/keys]]'	=> 'Missing text keys',
];
