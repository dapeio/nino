<?php
/**
 *	Nino							A compact filesystembased php framework
 *	blacklist.php			Keys hidden from the admin Text-Editor - technical
 *											values that aren't really "content" and would break
 *											routing/navigation/design if edited freely. Everything
 *											else found in global.php/{locale}.php is editable.
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
return [

	'/website/lang',
	'/website/charset',
	'/website/url',

	'/webpage/home/uri',
	'/webpage/404/uri',
	'/webpage/contact/uri',
	'/webpage/legal/uri',
	'/webpage/demo-elements/uri',
	'/webpage/demo-sections/uri',
	'/webpage/demo-vpa/uri',

	// /ui/* fills only used by templates/mail-header.tpl now - the rest of
	// the frontend uses CSS custom properties (:root in _nino/Nino.css)
	'/ui/color/primary',
	'/ui/color/secondary',
	'/ui/color/text',
	'/ui/color/background',
	'/ui/color/border',
	'/ui/color/section/alt/bg',
	'/ui/typography/line-height',
	'/ui/typography/font-small',
	'/ui/typography/font-big',
	'/ui/spacing/1',
	'/ui/spacing/2',
	'/ui/spacing/3',
];
