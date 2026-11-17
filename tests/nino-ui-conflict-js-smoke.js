/**
 *	Nino								A compact filesystembased php framework
 *	nino-ui-conflict-js-smoke.js	Nino.adminUi.conflict(): the question a refused save asks - overwrite, cancel or reload, and
 *												the sentence that says what the reload drops, asked once. The keyboard
 *												and the screen reader are choiceDialog()'s, which this stands on: Escape is Cancel, and
 *												the focus goes back to the opener.
 *
 *	Usage: node tests/nino-ui-conflict-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, documentOf, element, findAll, fire, fills } = require( './harness.js' );

const dialogs = [];
const page = element('div');
const doc = documentOf( page );

doc.body = element('body');
doc.createElement = function( tag ) {
	const created = element( tag );
	if( tag === 'dialog' ) {
		created.open = false;
		created.showModal = function() { created.open = true };
		created.close = function() { created.open = false; fire( created, 'close' ) };
		dialogs.push( created );
	}
	return created;
};

const sandbox = { console : console, document : doc };
sandbox.window = sandbox;
sandbox.Nino = { events : { bindCallback : function() {} }, content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

const buttons = dialog => findAll( dialog, el => el.tagName === 'BUTTON' );

function ask( extra ) {
	const calls = [];
	const opened = adminUi.conflict( Object.assign( {
		message : 'Somebody saved it meanwhile.',
		onOverwrite : function() { calls.push('overwrite') },
		onReload : function() { calls.push('reload') },
		onCancel : function() { calls.push('cancel') },
	}, extra || {} ) );

	return { calls : calls, opened : opened, dialog : dialogs[dialogs.length - 1] };
}

console.log( 'Nino.adminUi.conflict - a save that was refused\n' );

{
	const a = ask();
	check( 'the question has the message of the refusal and three answers in the order of every dialog: reload, cancel, and at the far right overwrite - the dangerous one', a.opened === true && a.dialog.open === true
		&& findAll( a.dialog, el => el.tagName === 'P' )[0].textContent === 'Somebody saved it meanwhile.'
		&& buttons( a.dialog ).map( b => b.textContent ).join() === 'Reload,Cancel,Overwrite' && buttons( a.dialog ).map( b => b.className ).join() === 'nino-admin-btn-secondary,nino-admin-btn-secondary,nino-admin-btn-danger' );
	check( '...the focus does not open on the dangerous answer, but on Cancel, which is what Escape is', buttons( a.dialog )[1].autofocus === true && buttons( a.dialog )[2].autofocus !== true && buttons( a.dialog )[1].focused === true );
	check( '...the dialog is named by a heading, and nothing has been done yet', a.dialog.attributes['aria-labelledby'] === 'nino-admin-choice-title' && a.calls.length === 0 );
	fire( buttons( a.dialog )[2], 'click' );
	check( 'Overwrite calls back once, and only for overwrite', a.calls.join() === 'overwrite' );
}

{
	const a = ask();
	fire( buttons( a.dialog )[1], 'click' );
	check( 'Cancel calls onCancel and nothing else', a.calls.join() === 'cancel' );

	const b = ask();
	b.dialog.close();
	check( 'the Escape key is Cancel', b.calls.join() === 'cancel' );
}

{
	const a = ask();
	const before = dialogs.length;
	fire( buttons( a.dialog )[0], 'click' );
	check( 'Reload is asked once: the question that has it says what it drops, a press calls onReload at once, and no second dialog is opened', findAll( a.dialog, el => el.tagName === 'P' ).length === 2
		&& a.calls.join() === 'reload' && dialogs.length === before );
}

{
	const a = ask();
	const again = adminUi.conflict( { message : 'x' } );
	check( 'while a question is open a second one is not asked', again === false && dialogs[dialogs.length - 1] === a.dialog );
	fire( buttons( a.dialog )[1], 'click' );

	const b = ask( { text : { title : 'Elsewhere', overwrite : 'Force', reload : 'Load', cancel : 'Leave' } } );
	check( 'the words are the caller\'s where it gives them', findAll( b.dialog, el => el.tagName === 'H2' )[0].textContent === 'Elsewhere' && buttons( b.dialog ).map( x => x.textContent ).join() === 'Load,Leave,Force' );
	fire( buttons( b.dialog )[1], 'click' );

	// Only what is called is asked for: a caller that has no way to reload is not made to have one
	const c = adminUi.conflict( { message : 'x' } );
	fire( buttons( dialogs[dialogs.length - 1] )[1], 'click' );
	check( 'a caller that gives no callback is called nothing', c === true );
}

done();
