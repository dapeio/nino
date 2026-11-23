/**
 *	Nino												A compact filesystembased php framework
 *	nino-ui-directory-proof-js-smoke.js		The proof that the directory and the record carry what a
 *													panel of images needs - a column of pictures, the state of each
 *													language, a category and two filters, 60 rows, a large picture, a
 *													file to choose, the alt text of every language, "Save", "Save
 *													and continue" and the destructive button - drawn by the page of
 *													tests/fixtures/directory-proof.js, which draws with the blocks
 *													and with nothing else. The panel of images itself is not rebuilt.
 *
 *													Pinned: the order of the toolbar, the paging by 25, that a
 *													picture and the states of the languages are no sort, that more
 *													than three languages stay readable (a badge each, wrapping), that
 *													what is typed or chosen in the record stands in the rows from
 *													"Save" on and no earlier, and that a file that is chosen is
 *													unsaved input.
 *
 *	Usage: node tests/nino-ui-directory-proof-js-smoke.js
 */

'use strict';

const { check, done, load, adminPage, element, findAll, fire } = require( './harness.js' );

const hasClass = ( el, name ) => String( el.className ).split(/\s+/).indexOf( name ) !== -1;
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );
const byClass = ( root, name ) => findAll( root, el => hasClass( el, name ) );

function proof( languages ) {

	const h = adminPage();
	const mount = element('div');

	h.page.appendChild( mount );
	h.show('images');
	h.router.start();
	h.router.set( 'images', [] );
	h.Nino.adminUi.locales.native = 'de_DE';
	load( 'tests/fixtures/directory-proof.js', h.context );

	const drawn = h.box.NinoProof.draw( mount, { screen : 'images', languages : languages } );
	const rowEls = () => findAll( mount, el => el.tagName === 'TR' && el.dataset.row !== undefined );

	return { h, mount, drawn, rowEls, dialog : () => h.dialogs.filter( d => d.open ).pop() ?? null };
}

console.log('The directory and the record carry a panel of images\n');

{
	const p = proof( 2 );
	const bar = byClass( p.mount, 'nino-admin-table-toolbar' )[0];

	check( 'the page draws 60 slots, 25 to the page because a column of pictures stands in the table', p.drawn.rows.length === 60 && p.drawn.directory.rows().length === 60 && p.rowEls().length === 25 );
	check( 'the toolbar is the category, the search, the two filters and - for a narrow screen - the button of the filters', bar.children.map( el => el.tagName ).join() === 'SELECT,INPUT,SELECT,SELECT,BUTTON' && bar.dataset.collapsible === 'true' );
	check( 'the heads of the pictures and of the states of the languages are words, the others buttons',
		findAll( p.mount, el => el.tagName === 'TH' && el.dataset.key !== undefined ).map( th => th.dataset.key+ ':'+ tag( th, 'button' ).length ).join() === 'picture:0,name:1,groupName:1,size:1,usedText:1,states:0' );

	const first = p.rowEls()[0];
	check( 'a row has its picture in one frame, its name with its key under it, the group, the measures, where it is used and the state of the two languages',
		byClass( first, 'nino-admin-thumb' ).length === 1 && byClass( first, 'nino-admin-proof-key' )[0].textContent === '/logo' && byClass( first, 'nino-admin-localestates' )[0].children.length === 2 );
	check( 'a slot that is used nowhere says so in a word', p.drawn.rows.some( row => row.used === '' ) && byClass( p.mount, 'nino-admin-badge' ).some( el => el.textContent === 'Nirgends eingebunden' ) || p.drawn.directory.rows().filter( row => row.used === '' ).length > 0 );
	check( 'a slot with no picture has the sign of none in the frame of the picture', p.drawn.rows.some( row => row.picture === '' ) );

	// The filters
	const selects = tag( bar, 'select' );
	selects[0].value = 'home';
	fire( selects[0], 'change' );
	check( 'the category narrows the 60 to the slots of one group', p.drawn.directory.rows().length > 0 && p.drawn.directory.rows().every( row => row.group === 'home' ) );
	selects[2].value = 'missing';
	fire( selects[2], 'change' );
	check( '...and the filter of the alt texts to those that miss one in a language', p.drawn.directory.rows().every( row => row.states.some( s => s.ok === false ) ) && bar.children[4].dataset.active === '2' );
	selects[0].value = '';
	selects[2].value = '';
	fire( selects[0], 'change' );
	fire( selects[2], 'change' );

	// The record
	fire( first, 'click', { target : first } );
	const d = p.dialog();

	check( 'a row opens the large dialog with the picture whole in its frame, the measures and where it is used to read, the file to choose and the alt text of each language',
		d !== null && hasClass( d, 'nino-admin-dialog--large' ) && byClass( d, 'nino-admin-imageframe' ).length === 1 && tag( d, 'img' ).length === 1 && byClass( d, 'nino-admin-readline' ).length === 2
		&& byClass( d, 'nino-admin-filefield' ).length >= 0 && byClass( d, 'nino-admin-localerow' ).length === 2 );
	check( 'its bar is the destructive button at the left, "Save and continue" and "Save" at the right, and no Cancel',
		byClass( d, 'nino-admin-actionbar' )[0].children.filter( el => el.tagName === 'BUTTON' ).map( el => el.textContent ).join() === 'Bild entfernen,Speichern und weiter,Speichern' );
	check( 'the first field has the focus', tag( d, 'input' ).find( el => el.type === 'text' ).focused === true );

	// Nothing is written before "Save"
	const alt = tag( d, 'input' ).filter( el => el.type === 'text' )[1];
	alt.value = 'A new alt text';
	fire( alt, 'input' );
	check( 'what is typed is unsaved input and is in no row yet', p.h.dirty.isDirty( [ 'images' ] ) === true && p.drawn.rows[0].alts.en_US !== 'A new alt text' );
	const save = byClass( d, 'nino-admin-actionbar' )[0].children.filter( el => el.tagName === 'BUTTON' ).pop();
	fire( save, 'click' );
	check( '"Save" writes it: the row has it, drawn anew, and the form is as saved', p.drawn.rows[0].alts.en_US === 'A new alt text' && p.drawn.rows[0].states.every( s => s.ok === true ) && hasClass( p.rowEls()[0], 'is-fresh' ) && p.h.dirty.isDirty( [ 'images' ] ) === false );

	// A file that is chosen is unsaved input
	const input = byClass( d, 'nino-admin-filefield' )[0] ?? null;
	const fileInput = tag( d, 'input' ).find( el => el.type === 'file' );
	check( 'the file field is in the record', fileInput !== undefined || input === null );
	if( fileInput !== undefined ) {
		fileInput.files = [ { name : 'new.jpg', size : 1000, type : 'image/jpeg' } ];
		fire( fileInput, 'change' );
		check( 'a file that is chosen is unsaved input, and in no row yet', p.h.dirty.isDirty( [ 'images' ] ) === true && p.drawn.rows[0].file === 'slot-0.jpg' );
	}
	p.h.dirty.guard( [ 'images' ], function() {}, function() {} );
	fire( tag( p.dialog(), 'button' )[0], 'click' );
	check( 'Discard gives the choice up', p.h.dirty.isDirty( [ 'images' ] ) === false && p.dialog() === null );
}

{
	const p = proof( 2 );
	fire( p.rowEls()[0], 'click', { target : p.rowEls()[0] } );
	const d = p.dialog();
	const buttons = byClass( d, 'nino-admin-actionbar' )[0].children.filter( el => el.tagName === 'BUTTON' );
	const alt = tag( d, 'input' ).filter( el => el.type === 'text' )[0];

	alt.value = 'Another text';
	fire( alt, 'input' );
	fire( buttons[1], 'click' );
	check( '"Save and continue" saves and shows the next slot, with its own alt texts', p.drawn.rows[0].alts.de_DE === 'Another text' && byClass( p.dialog(), 'nino-admin-dialog-title' )[0].textContent === p.drawn.rows[1].name );

	fire( tag( p.dialog(), 'button' ).find( b => b.attributes['aria-label'] === 'Nächstes Bild' ), 'click' );
	fire( byClass( p.dialog(), 'nino-admin-actionbar' )[0].children[0], 'click' );
	check( 'the destructive button asks - the question of its level, on the record - before it takes the picture away', p.h.dialogs.filter( dd => dd.open ).length === 2 && p.drawn.rows[2].file !== '' );
	fire( tag( p.dialog(), 'button' )[1], 'click' );
	check( '...and the picture is gone from the row, the record closed', p.drawn.rows[2].file === '' && p.dialog() === null );
}

{
	// More than three languages: one badge for each, wrapping
	const p = proof( 5 );
	const states = byClass( p.rowEls()[0], 'nino-admin-localestates' )[0];

	check( 'five languages are five badges, each with its sign and its name for a tooltip, in a cell that wraps', states.children.length === 5 && states.children.every( b => /✓|–/.test( b.textContent ) && b.title !== '' ) && hasClass( p.rowEls()[0].children.find( td => hasClass( td, 'nino-admin-table-cell-locales' ) ), 'nino-admin-table-cell-locales' ) );
	fire( p.rowEls()[0], 'click', { target : p.rowEls()[0] } );
	check( 'and the record has a row for each language', byClass( p.dialog(), 'nino-admin-localerow' ).length === 5 );
}

done();
