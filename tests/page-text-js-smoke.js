/**
 *	Nino									A compact filesystembased php framework
 *	page-text-js-smoke.js	The words of the setup wizard and of the recovery page
 *									(_admin/assets/pagetext.js and the two tables, install/assets/text.js
 *									and assets/recovery-text.js): the choice of the language is an
 *									allow-list of two and holds, a refusal of the server is a code whose
 *									sentence the table has - a code that is none, or carries something it
 *									should not, is the general sentence and never the server's own -,
 *									both languages have the same keys, none empty and the same places for
 *									what goes in, no sentence stands in a script or a template of the two
 *									pages, and the words the blocks of the workbench look up are the
 *									workbench's own.
 *
 *	Usage: node tests/page-text-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, source, fills, element, documentOf } = require( './harness.js' );

/**
 *	pagetext.js in a page with a storage of its own
 *
 *	@param		{Object}		[storage]			The browser's storage as a plain object, or null for a browser that keeps it shut
 *
 *	@return		{Object}
 */
function world( storage ) {

	const root = element('div');
	const doc = documentOf( root );
	doc.documentElement = element('html');
	doc.body = element('body');
	doc.title = '';
	doc.activeElement = null;

	const sandbox = { console : console, document : doc, Nino : { events : { bindCallback : function() {} }, content : { text : {} } } };
	sandbox.window = sandbox;

	if( storage !== null )
		sandbox.localStorage = {
			getItem : function( key ) { return Object.prototype.hasOwnProperty.call( storage, key ) ? storage[key] : null },
			setItem : function( key, value ) { storage[key] = String( value ) },
		};
	else
		Object.defineProperty( sandbox, 'localStorage', { get : function() { throw new Error('blocked') } } );

	load( '_admin/assets/pagetext.js', vm.createContext( sandbox ) );

	return { text : sandbox.Nino.pageText, root : root, doc : doc, sandbox : sandbox };
}

// --- the choice of the language ---------------------------------------------------

const KEY = 'nino-admin-page-language';

{
	const kept = {};
	const w = world( kept );

	check( 'German is the default, and the choice has two values - an allow-list, in this order', w.text.language() === 'de' && w.text.LANGUAGES.join() === 'de,en' && w.text.other() === 'en' );

	const changes = [];
	w.text.onChange( function( code ) { changes.push( code ) } );
	check( 'the other language is taken, kept under one key of the storage and told to whoever listens', w.text.set('en') === true && w.text.language() === 'en' && kept[KEY] === 'en' && changes.join() === 'en' && w.text.other() === 'de' );
	check( '...and the page speaks it: the lang of <html>', w.doc.documentElement.lang === 'en' );

	// A value that is none of the two changes nothing: a path, a markup, a code of a workbench language, a number, an object, nothing
	const foreign = [ '../../etc/passwd', '"><script>x</script>', 'de_DE', 'EN', 'en ', '', 'fr', null, undefined, 7, {}, [ 'de' ], 'constructor', '__proto__' ];
	check( 'a value that is none of the two is refused, whatever it is: nothing is kept, nothing is told, the language stays', foreign.every( function( value ) { return w.text.set( value ) === false } ) && w.text.language() === 'en' && kept[KEY] === 'en' && changes.length === 1 );
}

{
	// What the storage holds is no more believed than what is asked for
	[ '../x', '<b>', 'de_DE', 'EN', '', 'constructor' ].forEach( function( held ) {
		const w = world( { [KEY] : held } );
		check( 'a stored value that is none of the two is not the language - "'+ held +'": German', w.text.language() === 'de' );
	} );

	const w = world( { [KEY] : 'en' } );
	check( 'a stored choice holds over a reload: the next page is born in it', w.text.language() === 'en' );
}

{
	const w = world( null );
	check( 'a browser that keeps its storage shut reads German and the page still changes language for as long as it is open', w.text.language() === 'de' && w.text.set('en') === true && w.text.language() === 'en' );
}

// --- the sentences --------------------------------------------------------------------

{
	const w = world( {} );

	w.text.register( {
		de : { '/_admin/page/title' : 'Titel', '/_admin/page/language-other' : 'English', '/_admin/page/error/failed' : 'Fehlgeschlagen (%s).', '/_admin/a' : 'Eins %s und %s', '/_admin/page/error/some-code' : 'Der Code sagt %s.', '/_admin/page/error/short' : 'Mindestens %s.' },
		en : { '/_admin/page/title' : 'Title', '/_admin/page/language-other' : 'Deutsch', '/_admin/page/error/failed' : 'Failed (%s).', '/_admin/a' : 'One %s and %s', '/_admin/page/error/some-code' : 'The code says %s.', '/_admin/page/error/short' : 'At least %s.' },
	} );

	check( 'a sentence is the table\'s in the language of the page, its %s filled in the order they stand', w.text.t( '/_admin/a', 'x', 'y' ) === 'Eins x und y' && ( w.text.set('en'), w.text.t( '/_admin/a', 'x', 'y' ) === 'One x and y' ) );
	check( '...what goes in is put in as it is: a "$&" of a typed value is no pattern', w.text.t( '/_admin/a', '$&', '$1' ) === 'One $& and $1' );
	check( '...a key the table has not is the empty string, never the key', w.text.t( '/_admin/nope' ) === '' && w.text.t( 'toString' ) === '' && w.text.t( '__proto__' ) === '' );
	check( '...and the words are published to Nino.content under their own keys, where the blocks of the workbench look theirs up', w.sandbox.Nino.content.text['/_admin/a'] === 'One %s and %s' );

	w.text.set('de');
	check( 'the words follow the language when it is changed', w.sandbox.Nino.content.text['/_admin/a'] === 'Eins %s und %s' );

	// A sentence said in one language is said again in the other: whole, with a part that is a label, or with a number
	w.text.register( {
		de : { '/_admin/common/error/missing' : '%s fehlt.', '/_admin/x/label' : 'Passwort', '/_admin/x/error/short' : 'Mindestens %s Zeichen.', '/_admin/x/title' : 'Passwort' },
		en : { '/_admin/common/error/missing' : '%s is missing.', '/_admin/x/label' : 'Password', '/_admin/x/error/short' : 'At least %s characters.', '/_admin/x/title' : 'Password' },
	} );
	w.text.set('de');
	check( 'a sentence said in English is said in German again: a whole one, one with a label in it and one with a number', w.text.translate( 'Password is missing.', 'en' ) === 'Passwort fehlt.' && w.text.translate( 'At least 8 characters.', 'en' ) === 'Mindestens 8 Zeichen.' );
	check( '...and what the table does not know, the same language and a language that is none come back as they were', w.text.translate( 'Something else', 'en' ) === 'Something else' && w.text.translate( 'Password is missing.', 'de' ) === 'Password is missing.' && w.text.translate( 'x', '__proto__' ) === 'x' );
	check( '...and a sentence longer than any the tables say is not tried against their patterns: it comes back as it was', ( function() { const long = 'x'.repeat( 3000 )+ ' is missing.'; return w.text.translate( long, 'en' ) === long && w.text.translate( 'x'.repeat( 300 )+ ' is missing.', 'en' ) === 'x'.repeat( 300 )+ ' fehlt.' } )() );

	// What the server answers: its code is looked up, its own sentence is never shown
	const failed = function( status, response, ...known ) { return w.text.failure( status, response, ...known ) };
	check( 'a refusal with a code the table has a sentence for says that sentence, in the language of the page', failed( 400, { error : 'english of the server', code : 'some_code' } ) === 'Der Code sagt .' || failed( 400, { error : 'english of the server', code : 'some_code' } ) === 'Der Code sagt %s.' );
	check( '...a code with no sentence, no code, an answer that is none and a code that is no word of the grammar all say the general sentence with the status, and never the English of the server',
		[ { error : 'secret detail', code : 'unknown_code' }, { error : 'secret detail' }, null, 'text', 7, { code : 'Some_Code' }, { code : '../x' }, { code : 'a b' }, { code : [ 'some_code' ] }, { code : '' }, { code : 'constructor' } ].every( function( answer ) {
			return failed( 500, answer ) === 'Fehlgeschlagen (500).';
		} ) );
	check( '...a status that is no whole number is 0', failed( '500; drop', { code : 'x' } ) === 'Fehlgeschlagen (0).' && failed( 1.5, null ) === 'Fehlgeschlagen (0).' );
	check( 'what the page itself knows - the length of a password - is said by the sentence that has a place for it', failed( 400, { code : 'short' }, 8 ) === 'Mindestens 8.' );
	check( 'what the answer carries goes into a sentence only as a number or a short word - a version, a size - and never as markup or as a sentence of the server', failed( 413, { code : 'some_code', params : [ '<b>x</b>' ] } ) !== 'Der Code sagt <b>x</b>.'
		&& failed( 413, { code : 'some_code', params : [ 'a long sentence of the server with spaces' ] } ).indexOf('sentence') === -1 && failed( 413, { code : 'some_code', params : [ '1.8.0' ] } ) === 'Der Code sagt 1.8.0.' && failed( 413, { code : 'some_code', params : [ 12 ] } ) === 'Der Code sagt 12.' );

	// The page is written: <html lang>, <title>, and what names a key
	const holder = element('div');
	const labelled = element('span');
	labelled.setAttribute( 'data-text', '/_admin/page/title' );
	holder.appendChild( labelled );
	const named = element('button');
	named.setAttribute( 'data-label', '/_admin/page/title' );
	named.setAttribute( 'data-title', '/_admin/page/language-other' );
	holder.appendChild( named );
	w.root.appendChild( holder );
	w.text.apply( holder );
	check( 'apply() writes the text, the aria-label and the title of what names a key, as text and never as markup', labelled.textContent === 'Titel' && named.getAttribute('aria-label') === 'Titel' && named.getAttribute('title') === 'English' );
	check( '...and the <title> of the page and the lang of <html>', w.doc.title === 'Titel' && w.doc.documentElement.lang === 'de' );
}

// --- keeping what was typed ------------------------------------------------------------

{
	const w = world( {} );
	const root = element('div');
	const draw = function( labelText ) {
		root.innerHTML = '';
		const text = element('input'); text.dataset.key = 'name'; text.type = 'text';
		const tick = element('input'); tick.dataset.key = 'tick'; tick.type = 'checkbox'; tick.value = 'a';
		const tick2 = element('input'); tick2.dataset.key = 'tick'; tick2.type = 'checkbox'; tick2.value = 'b';
		const file = element('input'); file.dataset.key = 'file'; file.type = 'file';
		root.appendChild( text ); root.appendChild( tick ); root.appendChild( tick2 ); root.appendChild( file );
		return [ text, tick, tick2, file ];
	};
	let [ text, tick, tick2 ] = draw();
	text.value = 'typed';
	tick2.checked = true;
	w.text.keep( root, function() { [ text, tick, tick2 ] = draw() } );
	check( 'a page drawn again for another language keeps what was typed - a text, and the ticks of a list by their values', text.value === 'typed' && tick.checked === false && tick2.checked === true );
}

// --- the tables ---------------------------------------------------------------------------

/**
 *	A table out of a script of the checkout: the page's two languages as the script registers them
 *
 *	@param		{string}		script			A path from the checkout root
 *
 *	@return		{Object}									{ de, en }
 */
function table( script ) {

	const w = world( {} );
	const context = w.sandbox;
	context.Nino.install = {};
	context.Nino.recovery = {};
	load( script, context );

	return { de : Object.assign( {}, w.text._tables.de ), en : Object.assign( {}, w.text._tables.en ) };
}

const tables = {
	'the setup wizard' : table('_admin/install/assets/text.js'),
	'the recovery page' : table('_admin/assets/recovery-text.js'),
	'the page that stands where the wizard is not delivered' : table('_admin/assets/locked-text.js'),
};

const workbench = { de : fills('_admin/text/de_DE.php'), en : fills('_admin/text/en_US.php') };

Object.keys( tables ).forEach( function( page ) {

	const pair = tables[page];
	const deKeys = Object.keys( pair.de ).sort();
	const enKeys = Object.keys( pair.en ).sort();

	check( page+ ': both languages have the same keys, and there are enough to be the page\'s words ('+ deKeys.length+ ' in each)', deKeys.join() === enKeys.join() && deKeys.length > ( page === 'the setup wizard' || page === 'the recovery page' ? 40 : 4 ) );
	check( '...no sentence is empty, in either language', deKeys.every( function( key ) { return pair.de[key].trim() !== '' && pair.en[key].trim() !== '' } ) );
	check( '...and the two languages have the same places for what goes in', deKeys.every( function( key ) { return ( pair.de[key].match( /%s/g ) || [] ).length === ( pair.en[key].match( /%s/g ) || [] ).length } ) );
	check( '...every key is of the grammar of a text key - /_admin/<part>/<name> and a sentence of a refusal /_admin/page/error/<code>, in letters, digits and hyphens - so that no key of the table can be the text key of a project',
		deKeys.every( function( key ) { return /^\/_admin\/[a-z0-9]+(-[a-z0-9]+)*(\/[a-z0-9]+(-[a-z0-9]+)*){1,2}$/.test( key ) } ) );
	check( '...the German speaks to "Du" with a capital, where it speaks to anybody: no "Ihr", no "dein" with a small letter', deKeys.every( function( key ) { return /\b(dein|deine|deinen|deinem|deiner|deines|dir|dich)\b|\bIhr(e|en|em|er|es)?\b|\bIhnen\b/.test( pair.de[key] ) === false } ) );

	// The words under /_admin/common/ are the workbench's own: the blocks look them up by those keys
	const common = deKeys.filter( function( key ) { return key.indexOf('/_admin/common/') === 0 } );
	check( '...the words of the blocks of the workbench are the workbench\'s own, in both languages ('+ common.length+ ' of them)', ( common.length > 3 || page === 'the page that stands where the wizard is not delivered' ) && common.every( function( key ) { return pair.de[key] === workbench.de[key] && pair.en[key] === workbench.en[key] } ) );
} );

check( 'the two pages ask the same table for what both have: the title, the other language, the general sentence of a refusal',
	[ '/_admin/page/title', '/_admin/page/language-other', '/_admin/page/error/failed' ].every( function( key ) { return Object.values( tables ).every( function( pair ) { return pair.de[key] !== undefined && pair.en[key] !== undefined } ) } )
	&& tables['the setup wizard'].de['/_admin/page/language-other'] === 'English' && tables['the setup wizard'].en['/_admin/page/language-other'] === 'Deutsch' );

// Every code the server answers with on these pages has a sentence, and a code of the sentences is one the server answers
const php = [ '_admin/Admin.php', '_admin/install/Install.php', '_admin/Nino/Modules/Backups/Admin/Admin.php' ].map( source ).join('\n');
const answered = {};
[ ...php.matchAll( /'((?:recovery|install|backups|upload)_[a-z_]+)'/g ) ].forEach( function( found ) { answered[found[1]] = true } );
const sentenced = function( pair, code ) { return pair.de['/_admin/page/error/'+ code.replace( /_/g, '-' )] !== undefined };
const wizardCodes = Object.keys( answered ).filter( function( code ) { return code.indexOf('install_') === 0 } );
const recoveryCodes = Object.keys( answered ).filter( function( code ) { return code.indexOf('recovery_') === 0 || code.indexOf('backups_') === 0 || code.indexOf('upload_') === 0 } );

check( 'every code the wizard\'s server gives has a sentence in the table of the wizard, except the one the step reads as a reason and not as a refusal (install_none)', wizardCodes.length > 15
	&& wizardCodes.filter( function( code ) { return code !== 'install_none' && sentenced( tables['the setup wizard'], code ) === false } ).join() === '' );
check( 'every code the recovery page can come to has a sentence in the table of that page, except the ones of a notice and of a restore of the workbench\'s own panel', recoveryCodes.length > 15
	&& recoveryCodes.filter( function( code ) { return [ 'backups_restored_other_version', 'backups_held' ].indexOf( code ) === -1 && sentenced( tables['the recovery page'], code ) === false } ).join() === '' );

// --- no sentence in a script or a template of the two pages --------------------------------

/*	What a script writes into the page is a key of the table; a sentence of two words or more starting with a capital, assigned to what a person
	reads, or a text between two tags of a template, is one that is written where no other language can reach it. A code that gets its word from the
	table has none. Held as admin-lists-js-smoke.js holds the panels: by what the text of a file looks like	*/
const scripts = [ '_admin/assets/recovery.js', '_admin/assets/pagetext.js', '_admin/install/assets/script.js', '_admin/install/assets/checks.js', '_admin/install/assets/setup.js',
	'_admin/install/assets/personalinfos.js', '_admin/install/assets/accounts.js', '_admin/install/assets/applications.js', '_admin/install/assets/finish.js' ];
const templates = [ '_admin/templates/page-recovery.tpl', '_admin/install/templates/page-wizard.tpl', '_admin/install/templates/page-locked.tpl', '_admin/templates/page-locked.tpl' ];
// Which table a template is written from: the wizard's own two pages from the wizard's, the other pages from theirs
const tableOf = function( file ) {
	return file === '_admin/templates/page-recovery.tpl' || file === '_admin/assets/recovery.js' ? tables['the recovery page']
		: ( file === '_admin/templates/page-locked.tpl' ? tables['the page that stands where the wizard is not delivered'] : tables['the setup wizard'] );
};

const literals = [];

scripts.forEach( function( file ) {
	source( file ).split('\n').forEach( function( line, index ) {

		if( /^\s*(\/\/|\*|\/\*)/.test( line ) )
			return;

		const re = /\.(?:textContent|placeholder|title|innerHTML|value|label|alt)\s*=\s*'([A-Za-z][^']*\s[^']*)'|(?:say|fail|idle|notice|hint|button|sectionHelp|fieldError|emptyState|el)\(\s*(?:[^,()]*,\s*){0,2}'([A-Z][a-z]+[^']*\s[^']*)'/g;
		let found;

		while( ( found = re.exec( line ) ) )
			literals.push( file+ ':'+ ( index + 1 )+ ' '+ JSON.stringify( found[1] ?? found[2] ) );
	} );
} );

templates.forEach( function( file ) {
	source( file ).split('\n').forEach( function( line, index ) {

		if( /^\s*<!--/.test( line ) || /^\s+\S/.test( line ) && /^\s*(\*|-->)/.test( line ) )
			return;

		// Text between two tags, and the attributes a person reads: two words of prose are a sentence; a fill, a word of a name and a mark are not
		const between = />([^<>{}\[\]]*[A-Za-zÄÖÜ][a-zäöüß]+\s+[A-Za-z][^<>{}\[\]]*)</g;
		const attributes = /(?:placeholder|aria-label|title|alt)="([^"\[]*[A-Za-zÄÖÜ][a-zäöüß]+\s+[A-Za-z][^"]*)"/g;
		let found;

		while( ( found = between.exec( line ) ) )
			literals.push( file+ ':'+ ( index + 1 )+ ' '+ JSON.stringify( found[1].trim() ) );

		while( ( found = attributes.exec( line ) ) )
			literals.push( file+ ':'+ ( index + 1 )+ ' @'+ JSON.stringify( found[1] ) );
	} );
} );

check( 'no sentence of the setup wizard or of the recovery page stands in a script or a template: each is a key of the table of its page'+ ( literals.length ? ' - '+ literals.slice( 0, 5 ).join( ', ' ) : '' ), literals.length === 0 );

// The templates name nothing the table does not have, and a script asks for nothing it does not have
const asked = [];

templates.forEach( function( file ) {
	[ ...source( file ).matchAll( /data-(?:text|label|title)="(\/[^"]+)"/g ) ].forEach( function( found ) { asked.push( [ file, found[1] ] ) } );
} );

scripts.forEach( function( file ) {
	[ ...source( file ).matchAll( /'(\/_admin\/(?:install|recovery|page|common)\/[a-z0-9\/-]+)'/g ) ].forEach( function( found ) { asked.push( [ file, found[1] ] ) } );
} );

const unknown = asked.filter( function( pair ) {
	const key = pair[1];
	return pair[0].indexOf('pagetext') === -1 && key.slice( -1 ) !== '-' && key.slice( -1 ) !== '/' && tableOf( pair[0] ).de[key] === undefined;
} ).map( function( pair ) { return pair[0]+ ' '+ pair[1] } );

check( 'every key a template or a script of a page names is in the table of that page, in both languages'+ ( unknown.length ? ' - '+ unknown.slice( 0, 5 ).join( ', ' ) : '' ), asked.length > 100 && unknown.length === 0 );

done();
