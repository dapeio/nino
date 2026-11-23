<?php return [
	/*	The header and the footer of the starter site: the markup every page of the starter set draws through [[header]] and [[footer]] of the
		scaffold (html-header.tpl, html-footer.tpl of the base unit), see \Nino\Modules\Template::doFrame(). Not a unit the Setup step applies:
		the last step of the wizard writes it with the starter pages (\Nino\Install\StarterPages::apply()), and only where the project has no
		pages of its own - so an application that provides the start page brings its own frames, or none, and every page is drawn without them
		all the same	*/
	'label' 		=> 'Header and footer',
	'templates' => [
		'frame-header.tpl',
		'frame-footer.tpl',
	],
	/*	The stylesheet the two frames and the pages are written against: the classes they use (site-header, hero, card) are defined in it. It replaces the project's own
		stylesheet, which the base unit ships empty - so a site whose start page an application provides has none of its rules	*/
	'files' => [ 'assets' ],
];
