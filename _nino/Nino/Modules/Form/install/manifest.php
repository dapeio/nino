<?php return [
	// Checked when the Setup step opens on a project that has decided
	// nothing yet. Only the opening position - once Setup has applied once,
	// the operator's own answer is the only one that counts
	'preset' 		=> true,
	// The key the picker posts back and page units list in requiresModules.
	// Without one a unit is keyed by its module directory's lowercased name
	// ("form"); the page library has always said "forms"
	'key' 				=> 'forms',
	'label' 			=> 'Contact form',
	'moduleClass' => '\\Nino\\Modules\\Form',
	'requiresModules' => [ ],
	// Templates copied straight into /templates/, no locale gating - the
	// visitor's own confirmation mail renders in their current locale, the
	// owner notification always in the site's native locale (see Form.php)
	'templates' 	=> [ 'mail-owner.tpl', 'mail-user.tpl', 'mail-header.tpl', 'mail-footer.tpl' ],
	'blacklist' => [
		'/mail/style/color/primary',
		'/mail/style/color/text',
		'/mail/style/color/background',
		'/mail/style/color/border',
		'/mail/style/color/section/alt/bg',
		'/mail/style/typography/line-height',
		'/mail/style/typography/font-small',
		'/mail/style/typography/font-big',
		'/mail/style/spacing/1',
		'/mail/style/spacing/2',
		'/mail/style/spacing/3',
	],
];
