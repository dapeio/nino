/**
 * Dependency-free structural checks for _admin's shared drill-down lists.
 *
 * Usage: node tests/admin-lists-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

function asset( filename ) {
	return fs.readFileSync( path.join( __dirname, '../_admin/assets/', filename ), 'utf8' );
}

console.log('Admin drill-down lists');

[
	[ 'Element Types', 'elementtypes.js' ],
	[ 'Elements', 'elements.js' ],
	[ 'Text', 'text.js' ],
	[ 'Routes', 'pages.js' ],
	[ 'Images', 'images.js' ],
	[ 'Config', 'config.js' ],
].forEach( function( entry ) {
	check( entry[0]+ ' uses the shared grouped-list component', asset( entry[1] ).includes( "ul.className = 'admin-drill-list'" ) );
} );

const textSource = asset('text.js');
const categoryStart = textSource.indexOf('_renderCategoryList : function()');
const categoryEnd = textSource.indexOf('_openGroup : function', categoryStart);
const categorySource = textSource.slice( categoryStart, categoryEnd );
check( 'Text renders accessible links instead of non-interactive div cards', categorySource.includes( "dc.createElement('a')" ) && categorySource.includes( "dc.createElement('div')" ) === false );
check( 'Text places its template scan before the category list', categorySource.indexOf('wrap.appendChild( scanBtn )') < categorySource.indexOf('wrap.appendChild( ul )') );

const css = asset('style.css');
check( 'shared rows expose a right-facing drill-down arrow', css.includes('.admin-drill-list li a::after') && css.includes('content: "›"') );
check( 'shared rows retain a keyboard focus treatment', css.includes('.admin-drill-list li a:focus-visible') );
check( 'clickable Dashboard list rows expose the same arrow', css.includes('#admin-dashboard-types a::after') && css.includes('#admin-dashboard-summary a::after') );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
