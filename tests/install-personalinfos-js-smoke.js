/**
 *	Nino									A compact filesystembased php framework
 *	install-personalinfos-js-smoke.js	DOM-free checks for the install
 *									wizard's multi-locale Personal Infos save plan.
 *
 *	Usage: node tests/install-personalinfos-js-smoke.js
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
	install : {},
	events : { bindCallback : function() {} },
};

vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/install/assets/personalinfos.js' ), 'utf8' ),
	vm.createContext( sandbox ),
	{ filename : 'personalinfos.js' }
);

const personalinfos = sandbox.Nino.install.personalinfos;

personalinfos._entries = [
	{ key : '/project/company/general/name', global : true, values : { '*' : 'Original company' } },
	{ key : '/project/company/contact/country', global : false, values : { de_DE : 'Deutschland', en_US : 'Germany' } },
];
personalinfos._locales = [ 'de_DE', 'en_US' ];
personalinfos._fieldEls = { '/project/company/general/name' : { value : 'Edited company' } };
personalinfos._localeValues = {
	de_DE : { '/project/company/contact/country' : 'Musterland' },
	en_US : { '/project/company/contact/country' : 'Sample Country' },
};

let items = personalinfos._saveItems();
check( 'Save queues the global field exactly once', items.filter( function( item ) { return item.key === '/project/company/general/name' } ).length === 1 );
check( 'Save includes a locale edited before the visible one', items.some( function( item ) { return item.locale === 'de_DE' && item.value === 'Musterland' } ) );
check( 'Save includes the other edited locale too', items.some( function( item ) { return item.locale === 'en_US' && item.value === 'Sample Country' } ) );

personalinfos._localeValues = { de_DE : { '/project/company/contact/country' : 'Musterland' } };
items = personalinfos._saveItems();
check( 'an untouched locale keeps the value loaded from the server', items.some( function( item ) { return item.locale === 'en_US' && item.value === 'Germany' } ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
