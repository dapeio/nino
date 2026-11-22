/**
 *	Nino										A compact filesystembased php framework
 *	admin-worklocale-js-smoke.js	DOM-light checks for the language content is edited in,
 *													which the workbench chooses once: the one source
 *													(Nino.admin.workLocale, _admin/assets/script.js), the
 *													choice in the rail that asks before it throws unsaved
 *													input away, and the two blocks every view shares - the
 *													name of a language (Nino.adminUi.localeName()) and the
 *													badge of the language in a context bar
 *													(Nino.adminUi.localeBadge(), _admin/assets/Nino.admin.js).
 *
 *	Usage: node tests/admin-worklocale-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, fills, documentOf } = require( './harness.js' );

const NATIVE = 'de_DE';
// The native language is not the first one the server lists: the default is the native one, not whichever comes first
const LOCALES = [
	{ locale : 'en_US', name : 'English (US)', active : true },
	{ locale : 'de_DE', name : 'Deutsch', active : true },
	{ locale : 'it_IT', name : 'Italiano', active : false },
];
const INACTIVE = fills( '_admin/text/en_US.php' )['/_admin/common/word/inactive'];

/**
 *	A page of its own: the shell root with its data, the two selects the server renders, and the scripts of the shell
 *
 *	@param		{Object}		[options]		{ current, locales, selects }
 *
 *	@return		{Object}
 */
function page( options ) {

	options = Object.assign( { current : '', locales : JSON.stringify( LOCALES ), selects : true }, options || {} );

	const root = element('div');
	const wrap = element('div');
	wrap.id = 'admin-page-wrap';
	wrap.dataset.workLocale = options.current;
	wrap.dataset.nativeLocale = NATIVE;
	wrap.dataset.locales = options.locales;
	root.appendChild( wrap );

	const selects = [];
	if( options.selects === true )
		[ 'rail', 'menu' ].forEach( function( name ) {
			const select = element('select');
			select.id = 'admin-worklocale-'+ name;
			select.dataset.worklocale = '';
			wrap.appendChild( select );
			selects.push( select );
		} );

	const posted = [];
	const reloaded = [];
	const sandbox = { console : console, location : { hash : '', reload : function() { reloaded.push( sandbox.location.hash ) } }, history : { replaceState : function() {} }, document : documentOf( root ) };
	sandbox.window = sandbox;
	sandbox.Nino = {
		events : { bindCallback : function() {} },
		content : { getText : function( key ) { return fills( '_admin/text/en_US.php' )[key] ?? '' } },
	};

	const context = vm.createContext( sandbox );
	load( '_admin/assets/Nino.admin.js', context );
	load( '_admin/assets/script.js', context );

	// What the choice tells the server goes through the one request helper
	sandbox.Nino.adminUi.api.call = function( action, data, callback ) { posted.push( action+ ' '+ data.locale ); if( typeof callback === 'function' ) callback( 200, {} ) };
	const said = [];
	sandbox.Nino.adminUi.say = function( text, options ) { said.push( ( options || {} ).kind+ ' '+ text ) };

	const questions = [];
	let answer = 'cancel';
	sandbox.Nino.adminUi.choiceDialog = function( options ) {
		questions.push( options.choices.map( function( choice ) { return choice.value } ).join() );
		options.onChoose( answer );
		return {};
	};

	return {
		sandbox : sandbox, wrap : wrap, selects : selects, posted : posted, questions : questions, reloaded : reloaded, said : said,
		admin : sandbox.Nino.admin, ui : sandbox.Nino.adminUi, workLocale : sandbox.Nino.admin.workLocale,
		answer : function( value ) { answer = value },
		choose : function( select, locale ) { select.value = locale; fire( select, 'change' ) },
	};
}

const optionTexts = select => findAll( select, el => el.tagName === 'OPTION' ).map( option => option.value+ '='+ option.textContent );

console.log( 'Nino.admin.workLocale - the language content is edited in\n' );

// --- the one source -------------------------------------------------------------

{
	const p = page();
	p.workLocale.init();
	check( 'it starts as the native language of the project where the session has chosen none',
		p.workLocale.current === NATIVE && p.workLocale.native === NATIVE );
	check( '...and as the language the session holds once one was chosen', ( function() { const q = page( { current : 'en_US' } ); q.workLocale.init(); return q.workLocale.current === 'en_US' } )() );
	check( 'both selects of the rail say it, by the name of each language, and the language the website does not serve says so',
		p.selects.length === 2 && p.selects.every( function( select ) {
			return select.value === NATIVE && optionTexts( select ).join() === 'en_US=English (US),de_DE=Deutsch,it_IT=Italiano ('+ INACTIVE+ ')';
		} ) );

	const seen = [];
	p.workLocale.follow( 'elements', function( locale ) { seen.push( 'elements '+ locale+ ' '+ p.workLocale.current ) } );
	p.workLocale.follow( 'text', function( locale ) { seen.push( 'text '+ locale ) } );

	p.choose( p.selects[0], 'en_US' );
	check( 'a choice in the rail moves the language, and every view that follows it hears of it once, with the new language already in place',
		p.workLocale.current === 'en_US' && seen.join() === 'elements en_US en_US,text en_US' );
	check( '...the server is told, once', p.posted.join() === 'admin/locale en_US' );
	check( '...and the other select of the rail shows it too', p.selects.every( function( select ) { return select.value === 'en_US' } ) );

	p.workLocale.set( 'it_IT' );
	check( 'a panel that sets the language moves the same source: the views follow, the rail shows it, the server is told',
		p.workLocale.current === 'it_IT' && seen.slice( 2 ).join() === 'elements it_IT it_IT,text it_IT' && p.selects.every( function( select ) { return select.value === 'it_IT' } ) && p.posted.length === 2 );

	p.workLocale.set( 'it_IT' );
	p.workLocale.set( '' );
	p.workLocale.set( undefined );
	check( 'a language that is the one it holds, or none, is no move: nobody hears of it and nothing is sent', seen.length === 4 && p.posted.length === 2 );

	p.choose( p.selects[1], 'fr_FR' );
	check( 'a choice of a language the project does not have is not taken, and the rail shows the one it holds', p.workLocale.current === 'it_IT' && p.selects[1].value === 'it_IT' && p.posted.length === 2 );
}

// --- the name of the language the Builder of the catalogue still writes ---------------

{
	const p = page( { current : 'de_DE' } );
	p.workLocale.init();
	const seen = [];
	p.workLocale.follow( 'text', function( locale ) { seen.push( locale ) } );

	p.admin.sessionLocale.current = 'en_US';
	check( 'the old name is no second source: what is written to it moves the one, the views follow, the rail shows it, and it tells the server nothing - the writer has',
		p.workLocale.current === 'en_US' && p.admin.sessionLocale.current === 'en_US' && seen.join() === 'en_US' && p.selects.every( function( select ) { return select.value === 'en_US' } ) && p.posted.length === 0 );
}

// --- the question before a choice in the rail -----------------------------------

{
	const p = page();
	p.workLocale.init();

	const entries = { elements : { held : true, saved : 0, discarded : 0 }, routes : { held : true, saved : 0, discarded : 0 } };
	Object.keys( entries ).forEach( function( name ) {
		p.admin.dirty.register( name, {
			isDirty : function() { return entries[name].held },
			save : function( done ) { entries[name].saved++; entries[name].held = false; done( true ) },
			discard : function() { entries[name].discarded++; entries[name].held = false },
		} );
	} );

	const closed = [];
	p.admin.dirty._close = function( name ) { closed.push( name ) };
	const seen = [];
	p.workLocale.follow( 'elements', function( locale ) { seen.push( locale ) } );

	p.answer( 'cancel' );
	p.choose( p.selects[0], 'en_US' );
	check( 'a view that follows the language holds unsaved input: the choice asks first - Discard, Cancel, Save',
		p.questions.join( ';' ) === 'discard,cancel,save' );
	check( '...and Cancel stays in the old language: nothing moved, nobody was told, the rail shows the old one', p.workLocale.current === NATIVE && seen.length === 0 && p.posted.length === 0 && p.selects.every( function( select ) { return select.value === NATIVE } )
		&& entries.elements.discarded === 0 && entries.elements.saved === 0 );
	check( '...the unsaved input of a panel that does not follow the language is not asked about', entries.routes.held === true && entries.routes.discarded === 0 );

	p.answer( 'save' );
	p.choose( p.selects[0], 'en_US' );
	check( 'Save writes what the following view holds, and then the language moves', entries.elements.saved === 1 && entries.routes.saved === 0 && p.workLocale.current === 'en_US' && seen.join() === 'en_US' && closed.length === 0 );

	entries.elements.held = true;
	p.answer( 'discard' );
	p.choose( p.selects[0], 'de_DE' );
	check( 'Discard lets it forget, leaves the form open - the view that follows draws it again in the new language, where a move to another panel closes it - and then the language moves',
		entries.elements.discarded === 1 && closed.length === 0 && p.workLocale.current === 'de_DE' && seen.join() === 'en_US,de_DE' );

	const before = p.questions.length;
	p.choose( p.selects[0], 'en_US' );
	check( 'with nothing unsaved in a view that follows it, no question is asked', p.questions.length === before && p.workLocale.current === 'en_US' );
}

// --- a view that writes in one language and cannot draw another ---------------------------

{
	const p = page();
	p.workLocale.init();
	const entry = { held : true, discarded : 0 };
	p.admin.dirty.register( 'builder', { isDirty : function() { return entry.held }, discard : function() { entry.discarded++; entry.held = false } } );
	p.workLocale.bind( 'builder' );
	p.sandbox.location.hash = '#builder/home';

	p.answer( 'cancel' );
	p.choose( p.selects[0], 'en_US' );
	check( 'a view that is bound to a language is asked about though it does not follow: the choice asks, and Cancel keeps the language, the page and the input',
		p.questions.length === 1 && p.workLocale.current === NATIVE && p.reloaded.length === 0 && p.posted.length === 0 && entry.held === true );

	p.answer( 'discard' );
	p.choose( p.selects[0], 'en_US' );
	check( '...and with it on screen the page is loaded anew, in the language the server holds by then - never a view that shows one and writes the other',
		p.posted.join() === 'admin/locale en_US' && p.reloaded.join() === '#builder/home' && entry.discarded === 1 );

	const away = page();
	away.workLocale.init();
	away.admin.dirty.register( 'builder', { isDirty : function() { return false }, discard : function() {} } );
	away.workLocale.bind( 'builder' );
	away.sandbox.location.hash = '#text';
	away.choose( away.selects[0], 'en_US' );
	check( 'with the view not on screen the language moves as it does for any view: no reload', away.reloaded.length === 0 && away.workLocale.current === 'en_US' && away.questions.length === 0 );

	const clean = page();
	clean.workLocale.init();
	clean.workLocale.bind( 'builder' );
	clean.sandbox.location.hash = '#builder/home';
	clean.choose( clean.selects[0], 'en_US' );
	check( 'on screen and with nothing unsaved it is loaded anew all the same: what it shows has to be in the new language', clean.reloaded.length === 1 && clean.questions.length === 0 );
}

// --- a save that runs ------------------------------------------------------------------

{
	const p = page();
	p.workLocale.init();
	const seen = [];
	p.workLocale.follow( 'elements', function( locale ) { seen.push( locale ) } );
	let saving = true;
	p.admin.dirty.register( 'elements', { isDirty : function() { return true }, saving : function() { return saving }, save : function( done ) { done( false ) }, discard : function() {} } );
	p.choose( p.selects[0], 'en_US' );
	check( 'while a view that follows is saving the choice is not taken, and the person is told - no question that ends in a save that is not made twice',
		p.questions.length === 0 && p.workLocale.current === NATIVE && seen.length === 0 && p.said.length === 1 && p.said[0].indexOf( 'info ' ) === 0 && p.selects.every( function( select ) { return select.value === NATIVE } ) );
	saving = false;
	p.answer( 'discard' );
	p.choose( p.selects[0], 'en_US' );
	check( '...and once it is done the choice is asked and taken as any', p.questions.length === 1 && p.workLocale.current === 'en_US' );
}

// --- the view that holds nothing the language touches ---------------------------------

{
	const p = page();
	p.workLocale.init();
	p.admin.dirty.register( 'routes', { isDirty : function() { return true }, discard : function() {} } );
	p.workLocale.follow( null, function() {} );
	p.choose( p.selects[0], 'en_US' );
	check( 'a view that follows with no name is asked about nothing: the choice goes through, and its own unsaved input is its own', p.questions.length === 0 && p.workLocale.current === 'en_US' );
}

// --- a project of one language, and a shell that is not what it should be ----------------

{
	const p = page( { selects : false, locales : JSON.stringify( [ LOCALES[1] ] ) } );
	p.workLocale.init();
	check( 'without the selects the shell still holds the language, and the names are known', p.workLocale.current === NATIVE && p.ui.localeName( NATIVE ) === 'Deutsch' );

	const broken = page( { locales : '{"not":"a list"' } );
	broken.workLocale.init();
	check( 'data that is no list reads as no language: nothing is offered, nothing throws, and the language is the native one', broken.workLocale.locales.length === 0 && broken.workLocale.current === NATIVE && broken.selects.every( function( select ) { return select.children.length === 0 } ) );

	const odd = page( { locales : JSON.stringify( [ null, 7, 'x', { name : 'no code' }, LOCALES[0] ] ) } );
	odd.workLocale.init();
	check( '...and an entry that is no language is left out', odd.workLocale.locales.map( function( entry ) { return entry.locale } ).join() === 'en_US' );
}

console.log( '\nNino.adminUi.localeName - one form for the name of a language\n' );

{
	const p = page();
	p.workLocale.init();
	const ui = p.ui;

	check( 'a language is named as the project names it', ui.localeName( 'de_DE' ) === 'Deutsch' && ui.localeName( 'en_US' ) === 'English (US)' );
	check( '...and by its code where nothing names it, whatever the code is', [ 'fr_FR', 'toString', '__proto__', 'hasOwnProperty' ].every( function( code ) { return ui.localeName( code ) === code } ) );
	check( 'the code stays out of sight where the names differ, also among the others that stand with it', ui.localeName( 'de_DE', [ 'de_DE', 'en_US', 'it_IT' ] ) === 'Deutsch' );

	ui.locales = { native : NATIVE, names : { de_AT : 'Deutsch', de_DE : 'deutsch', en_US : 'English' } };
	check( 'two languages of one name are told apart by their codes behind the name - capitals do not make a difference - and one alone is not',
		ui.localeName( 'de_AT', [ 'de_AT', 'de_DE' ] ) === 'Deutsch (de_AT)' && ui.localeName( 'de_DE', [ 'de_AT', 'de_DE' ] ) === 'deutsch (de_DE)' && ui.localeName( 'en_US', [ 'de_AT', 'de_DE', 'en_US' ] ) === 'English'
		&& ui.localeName( 'de_AT' ) === 'Deutsch' );
}

console.log( '\nNino.adminUi.localeSelect and localeBadge - what a view shows of the language\n' );

{
	const p = page();
	p.workLocale.init();
	const ui = p.ui;

	const select = ui.localeSelect( { locales : [ 'de_DE', 'en_US', 'it_IT' ], inactive : [ 'it_IT' ], current : 'en_US' } );
	check( 'the select of a view says the languages by the same names, the code being the value', optionTexts( select ).join() === 'de_DE=Deutsch,en_US=English (US),it_IT=Italiano ('+ INACTIVE+ ')' && select.value === 'en_US' );

	check( 'the native language has no badge', ui.localeBadge( NATIVE ) === null && ui.localeBadge( '' ) === null && ui.localeBadge( undefined ) === null );
	check( '...unless the view says it shows it where the workbench is in another language: then it is named, so that nobody takes it for the other', ui.localeBadge( NATIVE, null, true ) !== null && ui.localeBadge( NATIVE, null, false ) === null && ui.localeBadge( '', null, true ) === null );

	const badge = ui.localeBadge( 'en_US' );
	check( 'another language has one: a quiet pill that names the language and says what it is the language of', badge !== null && badge.className.split( ' ' ).indexOf( 'nino-admin-badge' ) !== -1
		&& badge.dataset.state === 'warn' && badge.textContent === fills( '_admin/text/en_US.php' )['/_admin/common/label/worklocale-badge'].replace( '%s', 'English (US)' ) );

	const bar = element('div');
	const other = element('button');
	bar.appendChild( other );
	const first = ui.localeBadge( 'en_US', bar );
	check( 'given a bar it stands at its end', first !== null && bar.children.length === 2 && bar.children[1] === first );
	const second = ui.localeBadge( 'it_IT', bar );
	check( '...drawn again it takes the place of the one before, and does not add another', bar.children.length === 2 && bar.children[1] === second && second.textContent.indexOf( 'Italiano' ) !== -1 );
	check( '...and the native language takes it away', ui.localeBadge( NATIVE, bar ) === null && bar.children.length === 1 && bar.children[0] === other );
}

done();
