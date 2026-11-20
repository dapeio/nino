/**
 *	Nino						A compact filesystembased php framework
 *	nino-ui-tilechoice-js-smoke.js	DOM-light checks for Nino.adminUi.tileChoice(), one tile chosen in each of
 *											several groups: a radiogroup for each group, named by its heading, tiles that are
 *											radios with the chosen one marked, one tab stop for a group, the arrow keys in
 *											every direction and Home and End inside the group, the value and what is told
 *											on a change, the picture of a tile and the hook that draws it, the lines of
 *											the groups and of the whole, and the field of a dialog that carries it.
 *
 *	Usage: node tests/nino-ui-tilechoice-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, documentOf } = require( './harness.js' );

const sandbox = { console : console, document : documentOf( element('div') ) };
sandbox.window = sandbox;
sandbox.Nino = { content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } } };
load( '_admin/assets/Nino.admin.js', vm.createContext( sandbox ) );
const adminUi = sandbox.Nino.adminUi;

const tilesOf = function( handle, group ) {
	return findAll( handle.el, function( el ) { return el.tagName === 'BUTTON' && el.dataset.group === group } );
};

const press = function( tile, name ) {
	const event = { prevented : false };
	fire( tile, 'keydown', { key : name, preventDefault : function() { event.prevented = true } } );
	return event;
};

const checkedOf = function( handle, group ) {
	return tilesOf( handle, group ).map( function( tile ) { return tile.getAttribute('aria-checked') === 'true' ? tile.dataset.value : '' } ).filter( Boolean ).join();
};

// Two groups of four tiles, as the frames of a page are: the header and the footer
function build( extra ) {

	const told = [];
	const drawn = [];
	const handle = adminUi.tileChoice( Object.assign( {
		key : 'frames',
		label : 'Frames of this page',
		help : 'The frame is the header and the footer of the site.',
		note : 'Both are set at once.',
		groups : [
			{ key : 'header', label : 'Header', help : 'The top of the page.', note : 'Chosen for this page.', value : 'default', items : [
				{ value : 'default', label : 'Standard', hint : 'frame-header', image : '/p/default.png' },
				{ value : 'landing', label : 'Landing' },
				{ value : 'none', label : 'None', hint : 'no frame' },
				{ value : 'slim', label : 'Slim', draw : function( face ) { face.dataset.drawn = 'own' } },
			] },
			{ key : 'footer', label : 'Footer', value : 'none', items : [
				{ value : 'default', label : 'Standard' },
				{ value : 'landing', label : 'Landing' },
				{ value : 'none', label : 'None' },
				{ value : 'slim', label : 'Slim' },
			] },
		],
		draw : function( face, item, group ) { drawn.push( group.key+ ':'+ item.value ); face.dataset.drawn = face.dataset.drawn ?? 'hook' },
		onChange : function( group, value, values ) { told.push( group+ '='+ value+ ' '+ JSON.stringify( values ) ) },
	}, extra || {} ) );

	return { handle : handle, told : told, drawn : drawn };
}

console.log( 'Nino.adminUi.tileChoice - one tile chosen in each group\n' );

{
	const w = build();
	const h = w.handle;
	const header = tilesOf( h, 'header' );
	const sets = findAll( h.el, function( el ) { return el.getAttribute('role') === 'radiogroup' } );

	check( 'each group is a radiogroup named by its heading, and the whole is one element', sets.length === 2 && h.el.dataset.key === 'frames' );
	check( '...the heading of a group is the text of the group, with the (?) of its help behind the words', ( function() {
		const names = sets.map( function( set ) {
			const title = findAll( h.el, function( el ) { return el.id === set.getAttribute('aria-labelledby') } )[0];
			return title === undefined ? null : title.textContent;
		} );
		return names[0] !== null && names[1] === 'Footer' && findAll( h.el, function( el ) { return el.className.indexOf( 'nino-admin-help' ) !== -1 && el.tagName === 'BUTTON' } ).length >= 2;
	} )() );
	check( 'a tile is a radio button of the tile of pickDialog(): a picture surface over the name, with the quiet line of its own where it has one',
		header.length === 4 && header.every( function( tile ) { return tile.type === 'button' && tile.getAttribute('role') === 'radio' && tile.className.indexOf( 'nino-admin-pick-item--tile' ) !== -1 } )
		&& findAll( header[0], function( el ) { return el.className.indexOf( 'nino-admin-pick-image' ) !== -1 } ).length === 1 && findAll( header[0], function( el ) { return el.className.indexOf( 'nino-admin-tilechoice-hint' ) !== -1 } )[0].textContent === 'frame-header'
		&& findAll( header[1], function( el ) { return el.className.indexOf( 'nino-admin-tilechoice-hint' ) !== -1 } ).length === 0 );
	check( 'the tile that is given as the value is the chosen one - the value of a group that has none is null - and it is the one stop of its group',
		checkedOf( h, 'header' ) === 'default' && checkedOf( h, 'footer' ) === 'none' && header.map( function( tile ) { return tile.tabIndex } ).join() === '0,-1,-1,-1' && tilesOf( h, 'footer' ).map( function( tile ) { return tile.tabIndex } ).join() === '-1,-1,0,-1' );
	check( 'value() is an object, group => value', JSON.stringify( h.value() ) === '{"header":"default","footer":"none"}' && h.get('header') === 'default' && h.get('nope') === null );
	check( 'the picture is decoration, an address that loads when it comes near; the hook is called once for each tile with its surface - a tile with a hook of its own has that one',
		( function() {
			const picture = findAll( header[0], function( el ) { return el.tagName === 'IMG' } )[0];
			return picture !== undefined && picture.src === '/p/default.png' && picture.alt === '' && picture.loading === 'lazy' && w.drawn.length === 7 && w.drawn.indexOf( 'footer:slim' ) !== -1
				&& h.faces.header.slim.dataset.drawn === 'own' && h.faces.header.landing.dataset.drawn === 'hook' && h.faces.footer.none === findAll( tilesOf( h, 'footer' )[2], function( el ) { return el.className.indexOf( 'nino-admin-pick-image' ) !== -1 } )[0];
		} )() );
	check( 'a quiet line under each heading that has a note, and one under the name of the whole',
		findAll( h.el, function( el ) { return el.className.indexOf( 'nino-admin-tilechoice-note' ) !== -1 } ).map( function( el ) { return el.textContent } ).join() === 'Chosen for this page.'
		&& findAll( h.el, function( el ) { return el.className.indexOf( 'nino-admin-field-note' ) !== -1 } ).map( function( el ) { return el.textContent } ).join() === 'Both are set at once.' );
}

{
	const w = build();
	const h = w.handle;
	const header = tilesOf( h, 'header' );

	fire( header[1], 'click' );
	check( 'a press chooses the tile, tells the change with the group, the value and all values, and the stop goes with it',
		checkedOf( h, 'header' ) === 'landing' && w.told.join( '|' ) === 'header=landing {"header":"landing","footer":"none"}' && header.map( function( tile ) { return tile.tabIndex } ).join() === '-1,0,-1,-1' && checkedOf( h, 'footer' ) === 'none' );
	fire( header[1], 'click' );
	check( 'a press on the chosen tile changes nothing and tells nobody', w.told.length === 1 );

	const e = press( header[1], 'ArrowRight' );
	check( 'ArrowRight chooses and focuses the next tile, and the key is taken', e.prevented === true && checkedOf( h, 'header' ) === 'none' && header[2].focused === true && w.told.length === 2 );
	press( header[2], 'ArrowRight' );
	press( header[3], 'ArrowRight' );
	check( 'the walk stops at the last tile and does not go into the next group', checkedOf( h, 'header' ) === 'slim' && checkedOf( h, 'footer' ) === 'none' && w.told.length === 3 );
	press( header[3], 'ArrowLeft' );
	check( 'ArrowLeft goes back', checkedOf( h, 'header' ) === 'none' );
	press( header[2], 'Home' );
	check( 'Home is the first tile', checkedOf( h, 'header' ) === 'default' && header[0].focused === true );
	press( header[0], 'End' );
	check( 'End is the last tile', checkedOf( h, 'header' ) === 'slim' );
	press( header[3], 'ArrowLeft' );
	press( header[2], 'ArrowLeft' );
	press( header[1], 'ArrowLeft' );
	press( header[0], 'ArrowLeft' );
	check( 'ArrowLeft stops at the first tile', checkedOf( h, 'header' ) === 'default' );
	check( 'a key that is none of these is left alone', press( header[0], 'a' ).prevented === false && press( header[0], 'Tab' ).prevented === false && checkedOf( h, 'header' ) === 'default' );

	const told = w.told.length;

	h.set( 'footer', 'slim' );
	check( 'set() chooses without telling, in the group it names', checkedOf( h, 'footer' ) === 'slim' && w.told.length === told && h.value().footer === 'slim' );
	h.set( 'footer', 'nothing' );
	check( 'a value that is no tile chooses none: the value is null, and the first tile is the stop', h.value().footer === null && tilesOf( h, 'footer' ).map( function( tile ) { return tile.tabIndex } ).join() === '0,-1,-1,-1' && checkedOf( h, 'footer' ) === '' );
	h.focus( 'footer' );
	check( 'focus() puts the focus on the stop of a group, and on the first group\'s without a name', tilesOf( h, 'footer' )[0].focused === true && ( h.focus(), header[0].focused === true ) );
}

// Up and Down: the tile over or under, by where the tiles are
{
	const w = build();
	const h = w.handle;
	const header = tilesOf( h, 'header' );
	const box = [ [ 0, 0 ], [ 100, 0 ], [ 0, 80 ], [ 100, 80 ] ];

	header.forEach( function( tile, at ) {
		tile.getBoundingClientRect = function() { return { left : box[at][0], top : box[at][1], width : 90, height : 60 } };
	} );

	press( header[0], 'ArrowDown' );
	check( 'ArrowDown goes to the tile under, in the same column', checkedOf( h, 'header' ) === 'none' );
	press( header[2], 'ArrowRight' );
	press( header[3], 'ArrowUp' );
	check( 'ArrowUp goes to the tile over', checkedOf( h, 'header' ) === 'landing' );
	press( header[1], 'ArrowUp' );
	check( 'where there is none over, nothing moves', checkedOf( h, 'header' ) === 'landing' );
	press( header[1], 'ArrowDown' );
	press( header[3], 'ArrowDown' );
	check( 'where there is none under, nothing moves either', checkedOf( h, 'header' ) === 'slim' );
}

// A tile that cannot be chosen, and the form of a dialog
{
	const w = build( { groups : [ { key : 'one', label : 'One', value : 'a', items : [ { value : 'a', label : 'A' }, { value : 'b', label : 'B', disabled : true }, { value : 'c', label : 'C' } ] } ] } );
	const h = w.handle;
	const tiles = tilesOf( h, 'one' );

	press( tiles[0], 'ArrowRight' );
	check( 'a tile that is disabled is passed over by the keys', checkedOf( h, 'one' ) === 'c' && tiles[1].disabled === true );
}

{
	const made = adminUi._dialogFields( [ { key : 'frames', type : 'tiles', label : 'Frames', groups : [ { key : 'header', label : 'Header', value : 'a', items : [ { value : 'a', label : 'A' }, { value : 'b', label : 'B' } ] } ] } ], function() {} );
	const tile = findAll( made.el, function( el ) { return el.tagName === 'BUTTON' && el.dataset.value === 'b' } )[0];

	check( 'the fields of a dialog carry it as the type tiles: the value is the object, and a change is told with it', JSON.stringify( made.values() ) === '{"frames":{"header":"a"}}' && made.controls.frames !== undefined );
	fire( tile, 'click' );
	check( '...and what a press chooses is read with the other values, and the form knows it changed', JSON.stringify( made.values() ) === '{"frames":{"header":"b"}}' && made.serial( made.values().frames ) !== made.initial.frames );
}

{
	const bare = adminUi.tileChoice( { groups : [ { key : 'g', label : 'G', items : [ { value : '1', label : 'One' } ] } ] } );
	const tile = tilesOf( bare, 'g' )[0];

	check( 'a whole with no label has no heading and a tile with no picture the quiet surface alone; a group with none chosen has a stop on its first tile',
		findAll( bare.el, function( el ) { return el.className.indexOf( 'nino-admin-tilechoice-title' ) !== -1 } ).length === 0 && findAll( tile, function( el ) { return el.tagName === 'IMG' } ).length === 0 && tile.tabIndex === 0 && bare.value().g === null );
}

done();
