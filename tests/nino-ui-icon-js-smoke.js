/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-icon-js-smoke.js	DOM-light checks for the buttons of the workbench's
 *								scripts: Nino.adminUi.icon(), the Lucide icon as an
 *								inline <svg>, and button() and iconButton(), which
 *								draw it.
 *
 *								Pinned: what an icon is made of and how it is hidden
 *								from or named for a screen reader, that a name nobody
 *								has is no error, that every icon the guide names has
 *								its drawing, that a button called the old way carries
 *								what it carried, what kind, small and icon add, and
 *								that a button of an icon alone cannot be made without
 *								its name.
 *
 *	Usage: node tests/nino-ui-icon-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, fire } = require( './harness.js' );

const warnings = [];

const sandbox = {
	console : { log : console.log, warn : function( text ) { warnings.push( String( text ) ) } },
	document : {
		createElement : element,
		createElementNS : function( space, tag ) { const el = element( tag ); el.namespaceURI = space; return el },
		createTextNode : function( text ) { return Object.assign( element('#text'), { textContent : String( text ) } ) },
		documentElement : null,
		body : null,
	},
};
sandbox.window = sandbox;
sandbox.Nino = {};

load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );

const adminUi = sandbox.Nino.adminUi;

// The names that the guide and the plan ask for
const NAMES = [ 'trash-2', 'copy', 'arrow-up', 'arrow-down', 'plus', 'x', 'code', 'brackets', 'repeat', 'database', 'settings-2', 'sliders-horizontal', 'undo-2', 'redo-2', 'refresh-cw', 'search', 'chevron-left', 'chevron-right', 'chevron-down', 'external-link', 'pencil', 'check', 'triangle-alert', 'eye', 'eye-off', 'panels-top-left', 'panel-top', 'list' ];

function classes( el ) {
	return String( el.className ).split(/\s+/).filter( Boolean );
}

// The name of what a call throws: the script runs in a context of its own, whose TypeError is not this one's
function thrown( fn ) {
	try {
		fn();
	} catch( error ) {
		return error.name;
	}
	return null;
}

console.log( 'Nino.adminUi.icon, button, iconButton - the icons and the buttons\n' );

// --- icon() ----------------------------------------------------------------

const plain = adminUi.icon( 'trash-2' );

check( 'an icon is an inline svg that draws in the colour of its text, on the 24 grid', plain.tagName === 'SVG' && plain.getAttribute('stroke') === 'currentColor' && plain.getAttribute('viewBox') === '0 0 24 24' && plain.children.length > 0 );
check( 'without a label it is hidden from a screen reader and is no image', plain.getAttribute('aria-hidden') === 'true' && plain.hasAttribute('role') === false && plain.hasAttribute('aria-label') === false );

const named = adminUi.icon( 'trash-2', { label : 'Delete' } );

check( 'with a label it is an image of that name and is not hidden', named.getAttribute('role') === 'img' && named.getAttribute('aria-label') === 'Delete' && named.hasAttribute('aria-hidden') === false );
check( 'its size is the one asked for, as wide as high, and a size that is none is the default', adminUi.icon( 'x', { size : 20 } ).getAttribute('width') === '20' && adminUi.icon( 'x', { size : 20 } ).getAttribute('height') === '20' && adminUi.icon( 'x', { size : -3 } ).getAttribute('width') === plain.getAttribute('width') );
check( 'it is drawn in the svg namespace', plain.namespaceURI === 'http://www.w3.org/2000/svg' && plain.children.every( function( part ) { return part.namespaceURI === 'http://www.w3.org/2000/svg' } ) );

const before = warnings.length;
let stranger = null;
const strangerError = thrown( function() { stranger = adminUi.icon( 'no-such-icon' ) } );
adminUi.icon( 'no-such-icon' );

check( 'a name that the set does not have throws nothing and answers an svg with nothing drawn, hidden', strangerError === null && stranger.tagName === 'SVG' && stranger.children.length === 0 && stranger.getAttribute('aria-hidden') === 'true' );
check( 'it tells the console once per name, however often it is asked for', warnings.length === before + 1 );
check( 'a name that is a property of every object is no icon either', thrown( function() { adminUi.icon( 'constructor' ); adminUi.icon( '__proto__' ); adminUi.icon( undefined ) } ) === null && adminUi.icon( 'constructor' ).children.length === 0 );

const asked = warnings.length;

const drawn = NAMES.every( function( name ) {
	const svg = adminUi.icon( name );
	return svg.children.length > 0 && svg.children.every( function( part ) { return [ 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon' ].indexOf( part.tagName.toLowerCase() ) !== -1 && Object.keys( part.attributes ).length > 0 } );
} );

check( 'every icon that the guide names has a drawing', drawn === true );
check( 'the drawing of a known name raised no warning', warnings.length === asked );

// --- button() ---------------------------------------------------------------

let pressed = 0;
const old = adminUi.button( 'Save', function() { pressed++ }, { className : 'nino-admin-btn-primary', name : 'Save it', id : 'save', disabled : true, type : 'submit' } );

check( 'a button called the way it always was carries its class, text, name, id and type', old.tagName === 'BUTTON' && old.className === 'nino-admin-btn-primary' && old.textContent === 'Save' && old.title === 'Save it' && old.getAttribute('aria-label') === 'Save it' && old.id === 'save' && old.type === 'submit' && old.disabled === true );

const bare = adminUi.button( 'Go' );

check( 'a button without options has no class, does not submit and shows its text alone', bare.className === '' && bare.type === 'button' && bare.textContent === 'Go' && bare.children.length === 0 && bare.hasAttribute('aria-label') === false );

fire( adminUi.button( 'x', function() { pressed++ } ), 'click' );

check( 'a click calls what it was given', pressed === 1 );

const kinds = { primary : 'nino-admin-btn-primary', secondary : 'nino-admin-btn-secondary', danger : 'nino-admin-btn-danger', link : 'nino-admin-linkbutton' };

check( 'a kind sets the class of that kind, each its own', Object.keys( kinds ).every( function( kind ) { return adminUi.button( 'a', null, { kind : kind } ).className === kinds[kind] } ) );
check( 'a kind that there is none of is refused', thrown( function() { adminUi.button( 'a', null, { kind : 'huge' } ) } ) === 'TypeError' );
check( 'small sets the small class beside the kind and the class of the caller', function() {
	const list = classes( adminUi.button( 'a', null, { kind : 'danger', small : true, className : 'mine' } ) );
	return [ 'nino-admin-btn-danger', 'nino-admin-btn--small', 'mine' ].every( function( name ) { return list.indexOf( name ) !== -1 } );
}() );

const lead = adminUi.button( 'Add', null, { icon : 'plus' } );

check( 'an icon stands before the word, hidden from a screen reader, and the text is still the button\'s name', lead.children[0].tagName === 'SVG' && lead.children[0].getAttribute('aria-hidden') === 'true' && lead.children[1].textContent === 'Add' && classes( lead ).indexOf( 'nino-admin-btn--lead-icon' ) !== -1 );
check( 'a name that is not the text becomes the title and the aria-label of a button with an icon', adminUi.button( 'Add', null, { icon : 'plus', name : 'Add a row' } ).getAttribute('aria-label') === 'Add a row' );

// --- iconButton() -----------------------------------------------------------

let clicked = 0;
const only = adminUi.iconButton( 'arrow-up', 'Move up', function() { clicked++ } );
fire( only, 'click' );

check( 'an icon button is a button of the icon class with the icon in it and no text', only.tagName === 'BUTTON' && classes( only ).indexOf( 'nino-admin-btn-icon' ) !== -1 && only.children.length === 1 && only.children[0].tagName === 'SVG' && only.textContent === '' );
check( 'it carries its name as the aria-label and as the title', only.getAttribute('aria-label') === 'Move up' && only.title === 'Move up' );
check( 'a click calls what it was given', clicked === 1 );
check( 'one made without a name is refused, whatever the name is that is none', [ undefined, null, '', '   ', 4 ].every( function( name ) { return thrown( function() { adminUi.iconButton( 'x', name, null ) } ) === 'TypeError' } ) );

const small = classes( adminUi.iconButton( 'x', 'Remove', null, { danger : true, small : true } ) );

check( 'danger and small are classes beside the icon class', [ 'nino-admin-btn-icon', 'nino-admin-btn-danger', 'nino-admin-btn-icon--small' ].every( function( name ) { return small.indexOf( name ) !== -1 } ) );
check( 'without them it is neither', classes( only ).indexOf( 'nino-admin-btn-danger' ) === -1 && classes( only ).indexOf( 'nino-admin-btn-icon--small' ) === -1 );
check( 'it can be disabled and given an id', function() {
	const button = adminUi.iconButton( 'x', 'Remove', null, { disabled : true, id : 'gone' } );
	return button.disabled === true && button.id === 'gone';
}() );

// --- the styles ---------------------------------------------------------------

const css = require('fs').readFileSync( require('path').join( __dirname, '..', '_admin/assets/style.css' ), 'utf8' );

function rule( selector ) {
	const at = css.indexOf( selector+ ' {' );
	return at === -1 ? '' : css.slice( at, css.indexOf( '}', at ) );
}

check( 'the icon button is a target of 28px and the small one of 24px', /width: var\(--nino-admin-control-icon\)/.test( rule( ':where(.nino-admin) .nino-admin-btn-icon' ) ) && /min-height: var\(--nino-admin-control-icon\)/.test( rule( ':where(.nino-admin) .nino-admin-btn-icon' ) ) && /width: var\(--nino-admin-control-icon-small\)/.test( rule( ':where(.nino-admin) .nino-admin-btn-icon--small' ) ) && /min-height: var\(--nino-admin-control-icon-small\)/.test( rule( ':where(.nino-admin) .nino-admin-btn-icon--small' ) ) );
check( 'it has the focus ring of every button', /button[^{]*:focus-visible\s*\{[^}]*outline: 2px solid/.test( css ) );

done();
