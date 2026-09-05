<?php return [
	'label' 			=> 'Newsletter',
	'moduleClass' => '\\Nino\\Modules\\Newsletter',
	'requiresModules' => [ ],
	// page-newsletter.tpl serves /.newsletter (confirm/unsubscribe/invalid) -
	// the module registers that route itself (see Newsletter::init()), so
	// unlike a "page" bundle this needs no config.php route entry, just the
	// template file copied into place
	'templates' 	=> [ 'mail-newsletter-confirm.tpl', 'page-newsletter.tpl', 'mail-header.tpl', 'mail-footer.tpl' ],
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
