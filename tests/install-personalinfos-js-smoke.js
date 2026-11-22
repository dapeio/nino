/**
 *	Nino									A compact filesystembased php framework
 *	install-personalinfos-js-smoke.js	DOM-free checks for the install
 *									wizard's multi-locale Personal Infos save plan.
 *
 *	Usage: node tests/install-personalinfos-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, load, element, documentOf, findAll, fire } = require( './harness.js' );

const sandbox = {
	console : console,
	document : { documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {
	install : {},
	events : { bindCallback : function() {} },
};

load( '_admin/install/assets/personalinfos.js', vm.createContext( sandbox ) );

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

/*	The form drawn, in both languages: the label of a field is the one the server gives in the language of the page, the fields are the blocks of the
	workbench, and what was typed stays where the page is drawn again for another language	*/
const root = element('div');
const list = element('div');
list.id = 'personalinfos-list';
root.appendChild( list );
const doc = Object.assign( documentOf( root ), { documentElement : element('html'), body : element('body'), addEventListener : function() {}, removeEventListener : function() {} } );
const world = { console : console, document : doc };
world.Nino = { dir : '', events : { bindCallback : function() {} }, content : { text : {}, getText : function( key ) { return world.Nino.content.text[key] || '' } } };
world.window = { Nino : world.Nino, matchMedia : function() { return { matches : false } }, setTimeout : function() { return 0 }, clearTimeout : function() {} };
const drawing = vm.createContext( world );
[ '_admin/assets/Nino.admin.js', '_admin/assets/pagetext.js', '_admin/install/assets/text.js' ].forEach( file => load( file, drawing ) );

const text = world.Nino.pageText;
const said = [];
world.Nino.install = {
	t : function( key, ...params ) { return text.t( key, ...params ) },
	failure : function( status, response ) { return text.failure( status, response ) },
	say : function( words, error ) { said.push( [ words, error === true ] ) },
	localeName : function( code, among ) { return text.t( '/_admin/install/locale/'+ code.toLowerCase().replace( '_', '-' ) ) },
};
world.Nino.install.apiCall = function( action, payload, callback ) {
	callback( 200, action === 'personalinfos/list' ? { locales : [ 'de_DE', 'en_US' ], entries : [
		{ key : '/project/company/general/name', global : true, labels : { de : 'Unternehmen › Name', en : 'Company › Name' }, values : { '*' : 'Acme' } },
		{ key : '/project/company/contact/address', global : true, labels : { de : 'Unternehmen › Adresse', en : 'Company › Address' }, values : { '*' : 'Street 1' } },
		{ key : '/project/company/contact/country', global : false, labels : { de : 'Unternehmen › Land', en : 'Company › Country' }, values : { de_DE : 'Deutschland', en_US : 'Germany' } },
	] } : { results : { '/project/company/general/name' : { ok : true } } } );
};
load( '_admin/install/assets/personalinfos.js', drawing );
const form = world.Nino.install.personalinfos;
text.onChange( function() { form.retext() } );
text.set('en');
form.showCurrent();

const labelsOf = () => findAll( list, el => el.tagName === 'LABEL' ).map( label => findAll( label, node => node.tagName === '#TEXT' )[0]?.textContent ?? '' );
check( 'the label of a field is the one the server gives for the language of the page', labelsOf().includes('Company › Name') && labelsOf().includes('Company › Address') );
check( '...a key that is long text is a textarea and the others a line, each the block of the workbench with its data-key', findAll( list, el => el.tagName === 'TEXTAREA' ).length === 1 && findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/general/name' ).length === 1 );

// Nothing was touched yet: what the first drawing shows and what a save sends for the language that is visible is what the server gave
check( 'the first drawing shows the values the server gave for the visible language - not empty ones', findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/contact/country' )[0].value === 'Deutschland' );
check( '...and a save sent now sends them for that language, and the ones of the other language too', form._saveItems().some( item => item.key === '/project/company/contact/country' && item.locale === 'de_DE' && item.value === 'Deutschland' )
	&& form._saveItems().some( item => item.key === '/project/company/contact/country' && item.locale === 'en_US' && item.value === 'Germany' ) );

findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/general/name' )[0].value = 'Typed company';
findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/contact/country' )[0].value = 'Typed country';
text.set('de');
check( 'a change of the language draws the labels again in that language', labelsOf().includes('Unternehmen › Name') && labelsOf().includes('Unternehmen › Adresse') && labelsOf().includes('Company › Name') === false );
check( '...and keeps what was typed: a field of every language, and the one of the language that is chosen', findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/general/name' )[0].value === 'Typed company'
	&& findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/contact/country' )[0].value === 'Typed country' );
check( '...and what is saved is what was typed', form._saveItems().some( item => item.key === '/project/company/general/name' && item.value === 'Typed company' ) && form._saveItems().some( item => item.key === '/project/company/contact/country' && item.locale === 'de_DE' && item.value === 'Typed country' ) );

// The language of the fields: a select whose options are named as the workbench names a language
const select = findAll( list, el => el.id === 'personalinfos-locale-select' )[0];
check( 'the languages of the select are named by the workbench in the language of the page', findAll( select, el => el.tagName === 'OPTION' ).map( el => el.textContent ).join() === 'Deutsch,English (US)' );
select.value = 'en_US';
fire( select, 'change' );
check( 'another language shows the values of that language, and the ones typed for the first are kept', findAll( list, el => el.tagName === 'INPUT' && el.dataset.key === '/project/company/contact/country' )[0].value === 'Germany' && form._saveItems().some( item => item.locale === 'de_DE' && item.value === 'Typed country' ) );

let saved = null;
form.save( function( ok ) { saved = ok } );
check( 'a save that went through calls back true, and a refused one says so in the words of the table in the line of the bar', saved === true );
world.Nino.install.apiCall = function( action, payload, callback ) { callback( 200, { results : { a : { ok : false }, b : { ok : false } } } ) };
form.save( function( ok ) { saved = ok } );
check( '...with the number of the keys that failed', saved === false && said[said.length - 1][1] === true && said[said.length - 1][0] === text._tables.de['/_admin/install/personalinfos/failed'].replace( '%s', '2' ) );

done();
