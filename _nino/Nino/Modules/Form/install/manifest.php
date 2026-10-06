<?php return [
	// The key page units list in requiresModules - the contact form is one
	// of \Nino\Install\Setup::ALWAYS_MODULES now, applied on every setup
	// run rather than picked, so this key is never posted by the picker.
	// Without one a unit is keyed by its module directory's lowercased name
	// ("form"); the page library has always said "forms"
	'key' 				=> 'forms',
	'moduleClass' => '\\Nino\\Modules\\Form',
	// Templates copied straight into /templates/, no locale gating - the
	// visitor's own confirmation mail renders in their current locale, the
	// owner notification always in the site's native locale (see Form.php)
	'templates' 	=> [ 'mail-owner.tpl', 'mail-user.tpl', 'mail-header.tpl', 'mail-footer.tpl' ],
];
