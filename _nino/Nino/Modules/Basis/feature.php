<?php
// _nino/Nino/Modules/Basis/feature.php - what the Features panel reads. The class is not declared here: this
// directory can only ever serve \Nino\Modules\Basis. The feature comes with the kernel and is switched off until
// somebody switches it on; it has no 'nino' constraint, since it is updated with the kernel it is part of.
return [
	'key'					=> 'basis',
	'name'				=> [ 'en_US' => 'Basic components', 'de_DE' => 'Basis-Komponenten' ],
	'description'	=> [
		'en_US' => 'Header, footer, section, button, images and text blocks: the components a page is built of, with their standard look - readable, responsive, light and dark, and nothing more.',
		'de_DE' => 'Header, Footer, Sektion, Button, Bilder und Textblöcke: die Komponenten, aus denen eine Seite gebaut wird, mit ihrem Standard-Aussehen - lesbar, responsiv, hell und dunkel, und mehr nicht.',
	],
	'manual'			=> [
		'shortcodes' => [
			'[header-wrap]' => [
				'en_US' => 'The header of the page, with [header-logo], [header-nav] and [header-actions] in it; sticky keeps it at the top.',
				'de_DE' => 'Der Kopf der Seite, darin [header-logo], [header-nav] und [header-actions]; sticky hält ihn oben fest.',
			],
			'[footer-wrap]' => [
				'en_US' => 'The foot of the page, with [footer-col]s - a [footer-logo], a [footer-nav] and what flows - and one [footer-legal] for the legal pages and the languages.',
				'de_DE' => 'Der Fuß der Seite, mit [footer-col]-Spalten - [footer-logo], [footer-nav] und Fließendes - und einem [footer-legal] für die Rechtstexte und die Sprachen.',
			],
			'[section-wrap]' => [
				'en_US' => 'A section of the page, with [section-row]s of [section-col]s; ground, image, dim and height are its own.',
				'de_DE' => 'Ein Abschnitt der Seite, mit [section-row]-Zeilen aus [section-col]-Spalten; Grund, Bild, Abdunkeln und Höhe sind seine eigenen.',
			],
			'[section-col variant="card"]' => [
				'en_US' => 'A column that is a surface of its own: a card. size, ground, pad, bleed and link are its words; [section-title] and [section-subtitle] stand in it.',
				'de_DE' => 'Eine Spalte, die eine eigene Fläche ist: eine Karte. size, ground, pad, bleed und link sind ihre Worte; [section-title] und [section-subtitle] stehen darin.',
			],
			'[button]' => [
				'en_US' => 'A link that looks like a button, or - without an address - a span of the same look; [button-group] puts several in a row.',
				'de_DE' => 'Ein Link, der wie ein Button aussieht, oder - ohne Adresse - ein span in derselben Form; [button-group] stellt mehrere in eine Reihe.',
			],
			'[image]' => [
				'en_US' => 'The picture of a slot or of an image field, with a caption and a link where the call has them.',
				'de_DE' => 'Das Bild eines Platzes oder eines Bildfelds, mit Unterschrift und Link, wo der Aufruf sie hat.',
			],
			'[text]' => [
				'en_US' => 'A text with paragraphs and lists; [spacer] is room between two things and [html] a block of markup that is cleaned.',
				'de_DE' => 'Ein Text mit Absätzen und Listen; [spacer] ist Raum zwischen zwei Dingen und [html] ein Block Markup, der gesäubert wird.',
			],
		],
		'markup' => [
			'<name>--ground-<word>' => [
				'en_US' => 'The class a ground (base, alt, tint, primary, dark) is written as, for every component that has one; Nino.css names the roles for what stands on it.',
				'de_DE' => 'Die Klasse, als die ein Grund (base, alt, tint, primary, dark) geschrieben wird, für jede Komponente, die einen hat; Nino.css nennt die Rollen für das, was darauf steht.',
			],
			'--brand-..., --page-...' => [
				'en_US' => 'The tokens the standard parts read, set in Nino.css; a project turns them in assets/style.css.',
				'de_DE' => 'Die Tokens, die die Standard-Teile lesen, gesetzt in Nino.css; ein Projekt dreht sie in assets/style.css.',
			],
		],
		'routes' => [],
		'panel' => [],
		'callbacks' => [
			'/nino/components/parts' => [
				'en_US' => 'Asked for the standard part of each of its six groups before it goes into the style bundle; a theme answers another css file, or an empty one for none.',
				'de_DE' => 'Wird für den Standard-Teil jeder seiner sechs Gruppen gefragt, bevor er ins Stil-Bündel kommt; ein Theme antwortet eine andere css-Datei oder eine leere für keine.',
			],
		],
		'install' => [
			'text/<locale>.php' => [
				'en_US' => 'Puts the three words of the menus - Main, Menu, Footer - into the Text panel, for the names of the buttons and lists a screen reader reads.',
				'de_DE' => 'Bringt die drei Worte der Menüs - Haupt, Menü, Fuß - ins Panel Texte, für die Namen der Knöpfe und Listen, die ein Screenreader liest.',
			],
		],
	],
	'category'		=> 'content',
	'version'			=> '1.0.0',
];
