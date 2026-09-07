<?php return [
	// /webpage[[/nino/http/response/uri]]/{name,title,description} - the
	// per-page meta text - isn't shipped here anymore: the Webpages step
	// (see Install\Webpages) writes those directly, keyed by whatever uri
	// a project actually mounts this template at, not this folder's name

	'[[/webpage/.demo-catalogue/uri]]' => '/.demo-catalogue',
	'[[/webpage/.demo-catalogue/name]]' => 'Katalog',
	'[[/webpage/.demo-catalogue/title]]' => 'Katalog: Presets und Bausteine',
	'[[/webpage/.demo-catalogue/description]]' => 'Jedes Section-Preset des Template Builders und jeder Baustein aus Nino.css, im Design dieses Projekts.',

	// The newsletter specimen's labels - the same two keys the Newsletter
	// feature's own unit writes, so activating it later changes nothing here
	'[[/newsletter/label/email]]' => 'E-Mail-Adresse',
	'[[/newsletter/label/submit]]' => 'Anmelden',
];
