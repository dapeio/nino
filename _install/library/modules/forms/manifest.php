<?php return [
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
