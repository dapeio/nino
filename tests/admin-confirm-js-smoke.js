/**
 *	Nino										A compact filesystembased php framework
 *	admin-confirm-js-smoke.js	Nino.adminUi.confirm(): the question with two
 *													answers that the shell's mode decides the weight of.
 *													Whether it is asked at all is the shell's
 *													(Nino.admin.mode.policy()), and what it adds - a note -
 *													is drawn by choiceDialog() under the message; a page
 *													without a shell, a shell without a policy and a
 *													browser without <dialog> ask as confirm() always did.
 *													Nino.admin.js is loaded the way the panel suites load
 *													it, with a dialog that opens modally and closes the way
 *													the browser's does.
 *
 *	Usage: node tests/admin-confirm-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, documentOf, element, findAll, fire } = require( './harness.js' );


// --- the page --------------------------------------------------------------

/**
 *	Nino.admin.js in a page of its own
 *
 *	@param		{Object}		[shell]						What the page has of Nino.admin; none at all when it is left out
 *	@param		{boolean}		[modal]						Whether the browser has <dialog>.showModal(), true where it is left out
 *
 *	@return		{Object}
 */
function world( shell, modal ) {

	const dialogs = [];
	const asked = [];
	const page = element('div');

	const Nino = {
		events : { bindCallback : function() {} },
		content : { getText : function( key ) { return key } },
	};
	if( shell !== undefined )
		Nino.admin = shell;

	const doc = documentOf( page );
	doc.body = element('body');
	doc.createElement = function( tag ) {
		const created = element( tag );
		if( tag === 'dialog' ) {
			created.open = false;
			if( modal !== false )
				created.showModal = function() { created.open = true };
			created.close = function() { created.open = false; fire( created, 'close' ) };
			dialogs.push( created );
		}
		return created;
	};

	const sandbox = { console : console, document : doc, Nino : Nino };
	sandbox.window = { Nino : Nino, confirm : function( question ) { asked.push( question ); return sandbox.answer } };
	sandbox.answer = true;
	load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

	return { Nino : Nino, ui : Nino.adminUi, dialogs : dialogs, asked : asked, sandbox : sandbox, body : doc.body };
}

const buttons = dialog => findAll( dialog, el => el.tagName === 'BUTTON' );
const paragraphs = dialog => findAll( dialog, el => el.tagName === 'P' );

/** The options of a confirm(), and the answers it was called back with */
function ask( overrides ) {
	const answers = [];
	return {
		answers : answers,
		options : Object.assign( { message : 'Delete it?', yes : 'Delete', no : 'Cancel', onChoose : function( ok ) { answers.push( ok ) } }, overrides ),
	};
}

console.log('Nino.adminUi.confirm');


// --- a page with no shell: every question is asked --------------------------

{
	const w = world();
	const a = ask();
	const returned = w.ui.confirm( 'destructive', a.options );
	check( 'a page that has no shell asks, whatever the weight: a dialog opens and nobody has answered yet', returned === true && w.dialogs.length === 1 && w.dialogs[0].open === true && a.answers.length === 0 );
	check( '...with the message in a paragraph, and the two answers in the words the caller gave, the one that goes on first', paragraphs( w.dialogs[0] ).length === 1 && paragraphs( w.dialogs[0] )[0].textContent === 'Delete it?'
		&& buttons( w.dialogs[0] ).map( b => b.textContent ).join() === 'Delete,Cancel' );
	check( '...a destructive question makes the way on the dangerous answer, and No the quiet one', buttons( w.dialogs[0] )[0].className === 'nino-admin-btn-danger' && buttons( w.dialogs[0] )[1].className === 'nino-admin-btn-secondary' );
	fire( buttons( w.dialogs[0] )[0], 'click' );
	check( '...Yes calls back once with true, and the dialog is gone', a.answers.join() === 'true' && w.dialogs[0].open === false && w.ui._choiceOpen === false );

	const b = ask();
	w.ui.confirm( 'big', b.options );
	check( 'a question of any other weight makes the way on the primary answer', buttons( w.dialogs[1] )[0].className === 'nino-admin-btn-primary' );
	fire( buttons( w.dialogs[1] )[1], 'click' );
	check( '...and No calls back once with false', b.answers.join() === 'false' );

	const c = ask( { kind : 'danger' } );
	w.ui.confirm( 'big', c.options );
	const d = ask( { kind : 'primary' } );
	fire( buttons( w.dialogs[2] )[1], 'click' );
	w.ui.confirm( 'destructive', d.options );
	check( 'the caller may say which kind the way on is, whatever the weight', buttons( w.dialogs[2] )[0].className === 'nino-admin-btn-danger' && buttons( w.dialogs[3] )[0].className === 'nino-admin-btn-primary' );
	fire( buttons( w.dialogs[3] )[1], 'click' );

	const f = ask();
	w.ui.confirm( 'nonsense', f.options );
	check( 'a weight nobody named is asked as the heaviest is, and its way on is the dangerous answer', buttons( w.dialogs[4] )[0].className === 'nino-admin-btn-danger' );
	fire( buttons( w.dialogs[4] )[1], 'click' );
	const r = ask();
	w.ui.confirm( 'routine', r.options );
	check( 'a routine question makes the way on the primary answer', buttons( w.dialogs[5] )[0].className === 'nino-admin-btn-primary' );
	fire( buttons( w.dialogs[5] )[1], 'click' );

	const e = ask();
	w.ui.confirm( 'destructive', e.options );
	w.dialogs[6].close();
	check( 'a dialog closed without an answer - the Escape key - is a No, once', e.answers.join() === 'false' );
}


// --- a question already open ------------------------------------------------

{
	const w = world();
	const first = ask();
	const second = ask();
	w.ui.confirm( 'destructive', first.options );
	const returned = w.ui.confirm( 'destructive', second.options );
	check( 'while a question is open a second one is not asked: confirm() answers false and calls nobody back', returned === false && w.dialogs.length === 1 && second.answers.length === 0 );
	fire( buttons( w.dialogs[0] )[0], 'click' );
	check( '...and the one that was open is answered as it was', first.answers.join() === 'true' && second.answers.length === 0 );
	const third = ask();
	check( '...and once it is, the next one is asked', w.ui.confirm( 'destructive', third.options ) === true && w.dialogs.length === 2 );
	fire( buttons( w.dialogs[1] )[1], 'click' );

	// A question that is not asked is not one that is open
	const quiet = world( { mode : { policy : function( level ) { return { ask : level !== 'routine', note : '' } } } } );
	const q = ask();
	quiet.ui.confirm( 'routine', q.options );
	const afterwards = ask();
	check( 'a question the shell let through does not keep the next one from being asked', quiet.ui.confirm( 'destructive', afterwards.options ) === true && quiet.dialogs.length === 1 );
}


// --- the shell's policy -----------------------------------------------------

{
	const levels = [];
	let policy = { ask : false, note : '' };
	const w = world( { mode : { policy : function( level ) { levels.push( level ); return policy } } } );

	const a = ask();
	const returned = w.ui.confirm( 'big', a.options );
	check( 'a policy that does not ask is answered Yes at once - before confirm() has returned, and with no dialog', returned === true && a.answers.join() === 'true' && w.dialogs.length === 0 && w.asked.length === 0 );
	check( '...and it was asked about the weight the caller named', levels.join() === 'big' );

	policy = { ask : true, note : '' };
	const b = ask();
	w.ui.confirm( 'routine', b.options );
	check( 'a policy that asks opens the dialog, and nothing is answered until it is', w.dialogs.length === 1 && b.answers.length === 0 && levels.join() === 'big,routine' );
	check( '...an empty note is no paragraph', paragraphs( w.dialogs[0] ).length === 1 );
	fire( buttons( w.dialogs[0] )[0], 'click' );

	policy = { ask : true, note : 'Visitors see this at once.' };
	const c = ask();
	w.ui.confirm( 'big', c.options );
	const text = paragraphs( w.dialogs[1] );
	check( 'a note the policy adds is a paragraph of its own under the message, as text', text.length === 2 && text[0].textContent === 'Delete it?' && text[1].textContent === 'Visitors see this at once.' && w.dialogs[1].children[0].children.indexOf( text[1] ) > w.dialogs[1].children[0].children.indexOf( text[0] ) );
	check( '...and the dialog is described by both, so that it is read out with the note', w.dialogs[1].attributes['aria-describedby'] === text[0].id+ ' '+ text[1].id );
	fire( buttons( w.dialogs[1] )[1], 'click' );
	check( '...whichever way it is answered, the answer is the caller\'s', c.answers.join() === 'false' );

	// The note goes through the dialog as text, never as markup
	policy = { ask : true, note : '<b>live</b>' };
	const d = ask();
	w.ui.confirm( 'big', d.options );
	check( 'a note is set as text', paragraphs( w.dialogs[2] )[1].textContent === '<b>live</b>' && findAll( w.dialogs[2], el => el.tagName === 'B' ).length === 0 );
	fire( buttons( w.dialogs[2] )[1], 'click' );
}

{
	// A shell that is older than the policy asks, as a page without a shell does
	const w = world( { mode : {} } );
	const a = ask();
	w.ui.confirm( 'routine', a.options );
	check( 'a shell with no policy asks', w.dialogs.length === 1 && a.answers.length === 0 );
	fire( buttons( w.dialogs[0] )[1], 'click' );
	const b = world( { dirty : {} } );
	const asked = ask();
	b.ui.confirm( 'routine', asked.options );
	check( '...and so does one that has no mode', b.dialogs.length === 1 );
}


// --- a browser without <dialog> ---------------------------------------------

{
	const w = world( { mode : { policy : function() { return { ask : true, note : 'Visitors see this at once.' } } } }, false );
	const a = ask();
	w.sandbox.answer = true;
	const returned = w.ui.confirm( 'big', a.options );
	check( 'a browser without showModal() asks the message and the note, a blank line apart, and OK is Yes', returned === true && w.asked.join() === 'Delete it?\n\nVisitors see this at once.' && a.answers.join() === 'true' && w.dialogs.length === 1 && w.dialogs[0].open === false );
	const b = ask();
	w.sandbox.answer = false;
	w.ui.confirm( 'destructive', b.options );
	check( '...and Cancel is No', b.answers.join() === 'false' );

	const quiet = world( { mode : { policy : function() { return { ask : true, note : '' } } } }, false );
	quiet.ui.confirm( 'destructive', ask().options );
	check( '...with no note it is asked the message alone', quiet.asked.join() === 'Delete it?' );
}


// --- the note of choiceDialog() ---------------------------------------------

{
	const w = world();
	const chosen = [];
	w.ui.choiceDialog( { message : 'Save?', note : 'Take care.', choices : [ { value : 'yes', label : 'Yes', kind : 'primary' }, { value : 'no', label : 'No', kind : 'secondary' } ], onChoose : value => chosen.push( value ) } );
	check( 'choiceDialog() draws a note it is given under its message', paragraphs( w.dialogs[0] ).map( p => p.textContent ).join( '|' ) === 'Save?|Take care.' );
	fire( buttons( w.dialogs[0] )[0], 'click' );
	check( '...and answers with the value of the choice, as it did', chosen.join() === 'yes' );

	const content = element('div');
	content.textContent = 'A manual';
	w.ui.choiceDialog( { message : 'A manual', note : 'Not here.', content : content, choices : [ { value : 'close', label : 'Close', kind : 'secondary' } ], onChoose : function() {} } );
	check( 'a dialog that holds content in place of its message has no note either', paragraphs( w.dialogs[1] ).length === 0 );
}

// --- who asks, and how heavily ----------------------------------------------

{
	const fs = require('fs');
	const path = require('path');
	const root = path.join( __dirname, '..' );
	const scripts = [ '_admin/Nino/Modules', '_nino/Nino/Modules' ].flatMap( function( base ) {
		return fs.readdirSync( path.join( root, base ) ).flatMap( function( name ) {
			const dir = path.join( root, base, name, 'assets' );
			return fs.existsSync( dir ) ? fs.readdirSync( dir ).filter( file => file.endsWith('.js') ).map( file => path.join( dir, file ) ) : [];
		} );
	} );

	// The weight of every question a panel asks, by the words of its message: what each action is worth decides what a
	// site that is being built is asked, so the table is the contract and a change of it is a decision
	const expected = {
		'/_admin/types/confirm/rename' : 'big',
		'/_admin/elements/confirm/image-remove' : 'destructive',
		'/_admin/elements/confirm/delete' : 'destructive',
		'/_admin/backups/confirm/restore' : 'destructive',
		'/_admin/routes/confirm/delete-left' : 'destructive',
		'/_admin/translations/confirm/native' : 'big',
		'/_admin/roles/scope/warning-leave' : 'big',
		'/_admin/roles/scope/warning' : 'big',
		'/_admin/roles/confirm/delete' : 'destructive',
		'/_admin/users/confirm/deactivate' : 'big',
		'/_admin/users/confirm/delete' : 'destructive',
		'/_admin/users/confirm/logoutall' : 'big',
		'/_admin/slots/confirm/delete' : 'destructive',
		'/_admin/images/confirm/remove' : 'destructive',
		'/_admin/keys/confirm/format' : 'big',
		'/_admin/keys/confirm/delete' : 'destructive',
		'/_admin/features/confirm/remove' : 'destructive',
		'/_admin/features/confirm/deactivate' : 'big',
		'/_admin/navs/confirm/delete' : 'destructive',
		'/_admin/submissions/confirm/delete' : 'destructive',
	};
	const found = {};
	let calls = 0;
	scripts.forEach( function( file ) {
		const source = fs.readFileSync( file, 'utf8' );
		calls += ( source.match( /Nino\.adminUi\.confirm\(/g ) ?? [] ).length;
		for( const match of source.matchAll( /Nino\.adminUi\.confirm\(\s*'(\w+)',\s*\{\s*(?:title[^\n]*\n\s*)?message\s*:[^\n]*?getText\('(\/_admin\/[^']+)'\)/g ) )
			found[match[2]] = match[1];
	} );
	check( 'every panel script that asks names the weight of its question as the workbench has decided it, and a weight is one of three words - an unnamed one is taken for the heaviest, so a misspelled one would ask more than it was meant to and never say so',
		calls === Object.keys( expected ).length && JSON.stringify( Object.keys( found ).sort().map( key => [ key, found[key] ] ) ) === JSON.stringify( Object.keys( expected ).sort().map( key => [ key, expected[key] ] ) ) );
}

done();
