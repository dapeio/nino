/**
 *	Nino									A compact filesystembased php framework
 *	admin-elementtypes-js-smoke.js	DOM-free checks for the _admin Element
 *											Types editor's field-order handling. A field's
 *											position in the model is not cosmetic: it is the
 *											order every element form renders that type's
 *											fields in (see _admin/assets/elements.js's
 *											_globalKeys/_localeKeys, built from the model's
 *											own key order), and it survives all the way into
 *											the type file - tests/admin-smoke.php pins the
 *											server half of that down.
 *
 *											The rows themselves are re-rendered from _fields
 *											on every reorder, so reading the dom back first is
 *											what keeps in-progress edits alive - the bug this
 *											guards against is a moved row quietly reverting
 *											everything typed since the last render.
 *
 *	Usage: node tests/admin-elementtypes-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

const sandbox = {
	console : console,
	document : { documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {
	admin : {},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/elementtypes.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'elementtypes.js' }
);

const elementTypes = sandbox.Nino.admin.elementTypes;

// One rendered row, as _storeFields() reads it: every control it looks for,
// returning null for the ones a row of that type never renders (an image row
// has no "Required field" checkbox, a non-string row no maxlength, ...)
function fakeRow( field ) {
	const controls = {
		'.admin-field-key' 						: { value : field.key },
		'select' 											: { value : field.type },
		'.admin-field-locale' 				: { checked : field.locale === true },
		'.admin-field-required' 			: field.type === 'image' ? null : { checked : field.required === true },
		'.admin-field-html' 					: field.type === 'string' ? { checked : field.html === true } : null,
		'.admin-field-maxlength' 			: field.type === 'string' ? { value : field.maxlength ?? '' } : null,
		'.admin-field-select-options' : null,
		'.admin-field-width' 					: field.type === 'image' ? { value : field.width ?? '' } : null,
		'.admin-field-height' 				: field.type === 'image' ? { value : field.height ?? '' } : null,
		'.admin-field-suffix' 				: null,
	};
	return { querySelector : function( selector ) { return controls[selector] ?? null } };
}

// _move() re-renders through the real dom, which this sandbox has none of -
// the reordering itself is what matters here
elementTypes._renderFields = function() {};

function mountRows( fields ) {
	const rows = fields.map( fakeRow );
	sandbox.document.querySelectorAll = function() { return rows };
}

const model = [
	{ key : 'title', 	type : 'string', locale : true, required : true, maxlength : '80' },
	{ key : 'photo', 	type : 'image', width : '800', height : '600' },
	{ key : 'sort', 	type : 'integer' },
];

elementTypes._fields = model.map( function( f ) { return Object.assign( {}, f ) } );
mountRows( elementTypes._fields );

elementTypes._move( 2, 'up' );
check( 'moving a field up swaps it with the one above', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'title,sort,photo' );

mountRows( elementTypes._fields );
elementTypes._move( 0, 'down' );
check( 'moving a field down swaps it with the one below', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'sort,title,photo' );

// Out of range in either direction is a no-op, not a hole in the list - the
// buttons are disabled at the ends, but the handler must not rely on that
mountRows( elementTypes._fields );
elementTypes._move( 0, 'up' );
check( 'moving the first field up changes nothing', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'sort,title,photo' );

mountRows( elementTypes._fields );
elementTypes._move( 2, 'down' );
check( 'moving the last field down changes nothing', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'sort,title,photo' );
check( '...and never drops a field on the way', elementTypes._fields.length === 3 );

// The point of reading the rows back before swapping: an edit typed after the
// last render lives only in the dom until _storeFields() picks it up
elementTypes._fields = model.map( function( f ) { return Object.assign( {}, f ) } );
const edited = model.map( function( f ) { return Object.assign( {}, f ) } );
edited[0].key = 'headline';
mountRows( edited );

elementTypes._move( 1, 'up' );
check( 'a reorder keeps an edit that was only in the dom yet', elementTypes._fields.map( function( f ) { return f.key } ).join() === 'photo,headline,sort' );
check( '...including the per-type options of the moved row', ( elementTypes._fields[0].width === '800' && elementTypes._fields[0].height === '600' ) === true );
check( 'an image row, which renders no "required" checkbox, reads back as not required', elementTypes._fields[0].required === false );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
