<?php
// features/Social/install/social.php - the element type the links are, copied
// to /elements/social.php where a project has none.
//
// Every field is global: a link and the name of a network are the same in
// every language, and a type file is copied byte for byte, so a name given per
// locale here would be missing in every locale this file does not name.
// 'icon' is the slug of one of features/Social/icons/ - the options are what
// the Elements panel offers in its select. 'order' is the position, ten apart
// so a link fits between two; 'hidden' keeps a link without showing it, and
// is false unless an element says otherwise.
//
// The four links point at the networks' front pages rather than at a made-up
// account: a made-up name can be somebody's real one, and rel="me" on it would
// claim it as this site's. A front page left standing is harmless, and shows
// at a glance that it is still to be done.
return [
	'title' => 'Social Media',
	'model' => [
		'title' => [
			'type' 			=> 'string',
			'required' 	=> true,
			'maxlength' => 60,
		],
		'icon' => [
			'type' 			=> 'string',
			'required' 	=> true,
			'options' 	=> [
				'instagram', 'facebook', 'linkedin', 'youtube', 'github', 'twitch',
				'telegram', 'whatsapp', 'mastodon', 'threads', 'bluesky', 'tiktok', 'xing', 'pinterest',
				'website', 'mail', 'phone', 'rss', 'podcast', 'blog', 'shop', 'map', 'link',
			],
		],
		'link' => [
			'type' 			=> 'string',
			'required' 	=> true,
			'maxlength' => 500,
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
		'instagram' => [
			'title' => 'Instagram',
			'icon' 	=> 'instagram',
			'link' 	=> 'https://www.instagram.com/',
			'order' => 10,
		],
		'facebook' => [
			'title' => 'Facebook',
			'icon' 	=> 'facebook',
			'link' 	=> 'https://www.facebook.com/',
			'order' => 20,
		],
		'youtube' => [
			'title' => 'YouTube',
			'icon' 	=> 'youtube',
			'link' 	=> 'https://www.youtube.com/',
			'order' => 30,
		],
		'telegram' => [
			'title' => 'Telegram',
			'icon' 	=> 'telegram',
			'link' 	=> 'https://t.me/',
			'order' => 40,
		],
	],
];
