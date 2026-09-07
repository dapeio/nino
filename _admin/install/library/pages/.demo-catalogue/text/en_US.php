<?php return [
	// /webpage[[/nino/http/response/uri]]/{name,title,description} - the
	// per-page meta text - isn't shipped here anymore: the Webpages step
	// (see Install\Webpages) writes those directly, keyed by whatever uri
	// a project actually mounts this template at, not this folder's name

	'[[/webpage/.demo-catalogue/uri]]' => '/.demo-catalogue',
	'[[/webpage/.demo-catalogue/name]]' => 'Catalogue',
	'[[/webpage/.demo-catalogue/title]]' => 'Catalogue: presets and building blocks',
	'[[/webpage/.demo-catalogue/description]]' => 'Every section preset the Template Builder ships and every building block in Nino.css, in this project\'s own design.',

	// The newsletter specimen's labels - the same two keys the Newsletter
	// feature's own unit writes, so activating it later changes nothing here
	'[[/newsletter/label/email]]' => 'Email address',
	'[[/newsletter/label/submit]]' => 'Subscribe',
];
