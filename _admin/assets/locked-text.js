/**
 *	Nino										A compact filesystembased php framework
 *	locked-text.js					The sentences of the page that stands in place of the setup wizard where
 *													its folder is not part of the delivery (templates/page-locked.tpl), in German and in
 *													English under the same keys - see recovery-text.js for why a page outside the workbench
 *													has a table of its own. German is the default.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	Nino.pageText.register( {

		de : {
			'/_admin/page/title'				: 'Admin',
			'/_admin/page/language-other'	: 'English',
			'/_admin/page/error/failed'	: 'Die Anfrage ist fehlgeschlagen (Status %s).',
			'/_admin/locked/title'			: 'Noch nicht eingerichtet.',
			'/_admin/locked/text'				: 'In diesem Projekt ist der Assistent noch nie gelaufen, und sein Ordner _admin/install/ gehört nicht zu diesem Release. Stelle ihn aus dem Nino-Release wieder her, um das Projekt einzurichten.',
		},

		en : {
			'/_admin/page/title'				: 'Admin',
			'/_admin/page/language-other'	: 'Deutsch',
			'/_admin/page/error/failed'	: 'The request failed (status %s).',
			'/_admin/locked/title'			: 'Not set up yet.',
			'/_admin/locked/text'				: 'This project has never run the setup wizard, and the wizard’s folder _admin/install/ is not part of this delivery. Restore it from the release to set the project up.',
		},
	} );

})(window, document, document.documentElement, document.body);
