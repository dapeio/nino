<?php
// _nino/Nino/Modules/Legal/install/elements/legal.php - the imprint: one
// element per section, in German and English, written for this module. Applied
// by \Nino\Features::applyUnit() through the unit's 'elements' key, which
// only ever adds (see \Nino\Elements::seed()): an element an editor changed
// or deleted is not touched again.
//
// 'order' is the position, a hundred apart so a section fits between two.
// The texts are in the form the sanitizer gives a field with 'blocks' -
// paragraphs, lists, strong, br, and links to the anchor of another section -
// and carry no '&', no entity and no '[': what #/project/...# names is put in
// when a page is drawn (see \Nino\Modules\Legal::placeholders()).
return [
	'title' => [ 'de_DE' => 'Impressum', 'en_US' => 'Imprint' ],
	'model' => [
		'title' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'required' 	=> true,
			'maxlength' => 150,
		],
		'text' => [
			'type' 			=> 'string',
			'locale' 		=> true,
			'required' 	=> true,
			'html' 			=> true,
			'blocks' 		=> true,
			'maxlength' => 8000,
			'inputsize' => 12,
		],
		'order' => [
			'type' 			=> 'integer',
		],
		'hidden' => [
			'type' 			=> 'boolean',
		],
	],
	'*' => [
		'*' => [
			'hidden' => false,
		],
		'provider' => [
			'order' => 100,
		],
		'dispute' => [
			'order' => 200,
		],
	],
	'de_DE' => [
		'provider' => [
			'title' => 'Anbieter dieser Website',
			'text' 	=> '<p>Anbieter im Sinne von § 5 Digitale-Dienste-Gesetz (DDG):</p><p>#/project/website/general/author#<br>#/project/company/general/name#<br>#/project/company/contact/address#</p><p>Telefon: #/project/company/contact/phone#<br>E-Mail: #/project/company/contact/email#</p>',
		],
		'dispute' => [
			'title' => 'Verbraucherstreitbeilegung',
			'text' 	=> '<p>Wir nehmen an keinem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teil und sind dazu weder verpflichtet noch bereit.</p>',
		],
	],
	'en_US' => [
		'provider' => [
			'title' => 'Provider of this website',
			'text' 	=> '<p>Provider within the meaning of Section 5 of the German Digital Services Act (DDG):</p><p>#/project/website/general/author#<br>#/project/company/general/name#<br>#/project/company/contact/address#</p><p>Phone: #/project/company/contact/phone#<br>Email: #/project/company/contact/email#</p>',
		],
		'dispute' => [
			'title' => 'Consumer dispute resolution',
			'text' 	=> '<p>We do not take part in dispute settlement before a consumer dispute resolution body, and we are neither obliged nor willing to do so.</p>',
		],
	],
];
