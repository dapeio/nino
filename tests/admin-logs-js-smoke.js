/**
 *	Nino										A compact filesystembased php framework
 *	admin-logs-js-smoke.js		DOM-light checks for the Log panel's script
 *													(_admin/Nino/Modules/Logs/assets/admin.js): the lines the
 *													server answers are a table of the design system with a
 *													search over the full width, the time in the one form of
 *													the workbench, and the sentences exactly as they are
 *													stored; an empty log is the box of an empty state.
 *
 *	Usage: node tests/admin-logs-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, source, fills, load, documentOf, element, findAll, fire } = require( './harness.js' );

const words = Object.assign( {}, fills('_admin/text/en_US.php'), fills('_admin/Nino/Modules/Logs/text/en_US.php') );
const wordsDe = fills('_admin/Nino/Modules/Logs/text/de_DE.php');

const mount = element('div');
mount.id = 'logs-list';
const requests = [];

const Nino = {
	admin : {},
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) { requests.push( { action : data.action, callback : callback } ) } },
	content : { getText : function( key ) { return words[key] ?? '' } },
};
const sandbox = { console : console, document : documentOf( mount ), Nino : Nino };
sandbox.document.documentElement = { lang : 'de' };
sandbox.window = { Nino : Nino, setTimeout : function() { return 1 }, clearTimeout : function() {} };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/assets/datetext.js', context );
load( '_admin/Nino/Modules/Logs/assets/admin.js', context );

const panel = Nino.admin.logs;

function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

const rows = () => findAll( mount, el => el.tagName === 'TR' && findAll( el, cell => cell.tagName === 'TD' ).length > 0 );
const cells = row => findAll( row, el => el.tagName === 'TD' ).map( el => el.classList.contains('logs-entry') ? el.textContent : ( findAll( el, c => c.classList.contains('logs-sentence') )[0] ?? el ).textContent );
const metaOf = row => findAll( row, el => el.classList.contains('logs-meta') )[0]?.textContent ?? null;
const search = () => findAll( mount, el => el.tagName === 'INPUT' && el.type === 'search' )[0];

// The lines as the server answers them: the minute, the account and the sentence, two spaces between, newest first
const LINES = [
	'2026-11-17 06:02  admin@example.com  Upload Image /logo',
	'2026-11-17 05:53  admin@example.com  Activate feature "themes"',
	'2026-11-16 09:30  editor@example.com  Save Text /template/page-home/main-hero/title',
	'a line that is no line of the log',
];

console.log('Admin Logs');

panel.init();
check( 'the panel asks for the lines', requests.length === 1 && requests[0].action === 'logs/list' );
answer( 200, { lines : LINES } );

check( 'the lines are the rows of a table of the design system, in the order the server sent them, with a search field over it', findAll( mount, el => el.tagName === 'TABLE' && el.classList.contains('nino-admin-table') ).length === 1
	&& rows().length === 4 && search() !== undefined && search().placeholder === words['/_admin/logs/label/search'] && words['/_admin/logs/label/search'] !== '' );
check( 'a row is the time in the one form of the workbench - the day, the minute, no seconds - the account and the sentence as it is stored', cells( rows()[0] ).join('|') === Nino.adminUi.dateText('2026-11-17 06:02')+ '|admin@example.com|Upload Image /logo'
	&& Nino.adminUi.dateText('2026-11-17 06:02') === '17.11.2026, 06:02' );
check( '...and the sentence has the time and the account under it for a narrow screen, where the columns before it step aside - and the sheet of the panel says so', metaOf( rows()[0] ) === Nino.adminUi.dateText('2026-11-17 06:02')+ ' · admin@example.com'
	&& /@media \(max-width: 40rem\)[^@]*\.nino-admin-table :is\(th, td\):nth-child\(-n\+2\) \{[^}]*display: none;/.test( source('_admin/Nino/Modules/Logs/assets/admin.css') ) );
check( '...and a line that does not have that shape is shown whole, as the sentence', cells( rows()[3] ).join('|') === '||a line that is no line of the log' );
check( 'the heads of the columns are words of the module, in both interface languages', [ 'when', 'actor', 'text' ].every( key => ( words['/_admin/logs/label/'+ key] ?? '' ) !== '' && ( wordsDe['/_admin/logs/label/'+ key] ?? '' ) !== '' ) );

search().value = 'themes';
fire( search(), 'input' );
check( 'the search narrows the rows in the browser, with no request, and reads the sentence', requests.length === 1 && rows().length === 1 && cells( rows()[0] )[2] === 'Activate feature "themes"' );
search().value = Nino.adminUi.dateText('2026-11-16 09:30');
fire( search(), 'input' );
check( '...and reads the time as it is shown, not as it is stored', rows().length === 1 && cells( rows()[0] )[1] === 'editor@example.com' );
search().value = 'nothing like this';
fire( search(), 'input' );
check( '...and says so when it finds nothing, in words of the module', rows().length === 0 && findAll( mount, el => el.classList.contains('nino-admin-empty') ).some( el => el.textContent === words['/_admin/logs/nomatch'] ) );

panel.init();
answer( 200, { lines : LINES } );
check( 'a tab that is shown again reads the lines again and keeps what was searched for', requests.length === 2 && search().value === 'nothing like this' && rows().length === 0 );

panel.init();
answer( 200, { lines : [] } );
check( 'a log without a line is the box of an empty state, and has neither a table nor a search', rows().length === 0 && search() === undefined
	&& findAll( mount, el => el.classList.contains('nino-admin-empty') ).length === 1 && findAll( mount, el => el.classList.contains('nino-admin-empty') )[0].textContent === words['/_admin/logs/empty'] );

panel.init();
answer( 403, { error : 'no' } );
check( 'a list that could not be loaded is the error in place of the list', mount.children.length === 1 && mount.children[0].classList.contains('nino-admin-error') );

done();
