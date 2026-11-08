/**
 *	Nino										A compact filesystembased php framework
 *	admin-dashboard-js-smoke.js	DOM-light checks for the Dashboard panel's script
 *													(_admin/Nino/Modules/Dashboard/assets/admin.js) and the
 *													notice component it draws with (Nino.adminUi.notice()).
 *
 *													A notice is what the workbench says needs attention -
 *													mail that fails, texts without a translation - so what
 *													matters here is that it is said at all, above the
 *													numbers, in the interface language, with its blanks
 *													filled in order, and that nothing the server sent can
 *													become markup.
 *
 *													The card above the notices greets the account: the
 *													pins at the end of the file hold what it says by the
 *													clock of the browser (the hour, the weekday, the day of
 *													the year), what it leaves out and that the name is only
 *													ever text.
 *
 *	Usage: node tests/admin-dashboard-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, documentOf, findAll, element } = require( './harness.js' );

/** The text a notice shows: its own, and the label of its link */
function notices() {
	return findAll( mount, function( el ) { return el.className === 'nino-admin-notice' } );
}

function links( notice ) {
	return findAll( notice, function( el ) { return el.tagName === 'A' } );
}

function ownText( notice ) {
	return notice.textContent;
}


// --- the words -------------------------------------------------------------

const moduleEn = fills('_admin/Nino/Modules/Dashboard/text/en_US.php');

const askedKeys = [];

function text( key ) {
	return moduleEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

const mount = element('div');
mount.id = 'admin-dashboard-content';
const wrap = element('div');
wrap.id = 'dashboard-list';
mount.appendChild( wrap );

const requests = [];

const Nino = {
	admin : { router : { set : function() {} } },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, callback : callback } );
	} },
	content : { getText : function( key ) { askedKeys.push( key ); return text( key ) } },
};
const sandbox = {
	console : console,
	document : Object.assign( documentOf( wrap ), {
		createTextNode : function( value ) { return { nodeType : 3, textContent : String( value ), children : [] } },
	} ),
	Nino : Nino,
};
// The browser's session storage, the one place the greeting keeps what it said last
const stored = {};
const storage = {
	getItem : function( key ) { return Object.prototype.hasOwnProperty.call( stored, key ) ? stored[key] : null },
	setItem : function( key, value ) { stored[key] = String( value ) },
};
sandbox.window = { Nino : Nino, sessionStorage : storage };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );
load( '_admin/Nino/Modules/Dashboard/assets/admin.js', context );

const panel = Nino.admin.dashboard;

function show( body ) {
	panel.init();
	requests[requests.length - 1].callback( { status : 200, responseJSON : body } );
}

console.log('Admin Dashboard');

const mailText = 'Mail delivery has been failing since %s (%s failed attempts).';
moduleEn['/_admin/dashboard/notice/mail'] = mailText;

// --- the component ---------------------------------------------------------

const plain = Nino.adminUi.notice( 'Something needs you' );
check( 'a notice is a status paragraph of the design system, its text set as text', plain.tagName === 'P' && plain.className === 'nino-admin-notice'
	&& plain.attributes.role === 'status' && plain.textContent === 'Something needs you' );
check( '...with no link where none was given', plain.children.length === 0 );

const markup = Nino.adminUi.notice( '<img src=x onerror=alert(1)>' );
check( '...and markup in it stays text', markup.textContent === '<img src=x onerror=alert(1)>' && markup.children.length === 0 );

const linked = Nino.adminUi.notice( 'Open the panel', { href : '#text', label : 'Open' } );
check( 'a link to a panel of the workbench is an anchor with its label', links( linked ).length === 1 && links( linked )[0].href === '#text' && links( linked )[0].textContent === 'Open' );
check( '...a hash with a path in it too', links( Nino.adminUi.notice( 'x', { href : '#elements/news', label : 'Open' } ) ).length === 1 );

[ 'javascript:alert(1)', 'https://example.org/', '//example.org/', '#', '#Text', '#a b', '', '#"onclick="x' ].forEach( function( href ) {
	check( 'no link for the target '+ JSON.stringify( href ), links( Nino.adminUi.notice( 'x', { href : href, label : 'Open' } ) ).length === 0 );
} );
check( '...nor for a link that is no object', links( Nino.adminUi.notice( 'x', null ) ).length === 0 && links( Nino.adminUi.notice( 'x', '#text' ) ).length === 0 );

// --- the panel -------------------------------------------------------------

show( { tiles : [ { panel : 'users', value : '3', label : 'Users' } ], lastBackup : '2024-05-03',
	notices : [
		{ text : '/_admin/dashboard/notice/mail', values : [ '2024-05-03 10:15', '3' ], link : '' },
		{ text : 'A text the server wrote itself, 100%', values : [], link : '#text' },
	] } );

const drawn = notices();
check( 'every notice of the answer is drawn', drawn.length === 2 );
check( 'the fill key is resolved and the blanks are filled in order', ownText( drawn[0] ) === 'Mail delivery has been failing since 2024-05-03 10:15 (3 failed attempts).' );
check( '...a notice with no link has none', links( drawn[0] ).length === 0 );
check( '...a text that is no key is shown as it is', ownText( drawn[1] ).startsWith( 'A text the server wrote itself, 100%' ) );
check( '...and a link is drawn for a #panel, labelled with the dashboard\'s own word', links( drawn[1] ).length === 1 && links( drawn[1] )[0].href === '#text'
	&& links( drawn[1] )[0].textContent === text('/_admin/dashboard/notice/open') && text('/_admin/dashboard/notice/open') === 'Open' );
check( 'the notices stand above the tiles', wrap.children[0].className === 'nino-admin-notice' && wrap.children[1].className === 'nino-admin-notice'
	&& wrap.children[2].id === 'admin-dashboard-tiles' );

// The values are what a person or a file produced
show( { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ '<b>x</b>', '$&' ], link : '' } ] } );
check( 'a value containing markup or a replacement pattern stays text, as it stands', ownText( notices()[0] ) === 'Mail delivery has been failing since <b>x</b> ($& failed attempts).' );

show( { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ 7 ], link : 3 } ] } );
check( 'a number is filled in as its text, a missing value leaves the blank, and a link that is no string is none', ownText( notices()[0] ) === 'Mail delivery has been failing since 7 (%s failed attempts).'
	&& links( notices()[0] ).length === 0 );

// What the Legal module tells: the placeholder, the title of a section a person wrote, the language
const unknownWords = text('/_admin/dashboard/notice/legal-unknown');
show( { tiles : [], notices : [
	{ text : '/_admin/dashboard/notice/legal-unknown', values : [ '#/project/company/contact/email#', 'Hosting <i>$&</i>', 'de_DE' ], link : '#elements/privacy/hosting' },
	{ text : '/_admin/dashboard/notice/legal-more', values : [ '3' ], link : '' },
] } );
check( 'a notice of the legal texts fills its three values in as text, whatever a section is called', unknownWords.split('%s').length === 4 && notices().length === 2
	&& ownText( notices()[0] ).indexOf( unknownWords.split('%s')[0]+ '#/project/company/contact/email#' ) === 0 && ownText( notices()[0] ).indexOf( 'Hosting <i>$&</i>' ) !== -1 && ownText( notices()[0] ).indexOf( '(de_DE)' ) !== -1 );
check( '...with a link into the section it is about', links( notices()[0] ).length === 1 && links( notices()[0] )[0].href === '#elements/privacy/hosting' );
check( '...and the line that counts the rest has no link and its number in it', links( notices()[1] ).length === 0 && ownText( notices()[1] ) === text('/_admin/dashboard/notice/legal-more').replace( '%s', '3' ) && text('/_admin/dashboard/notice/legal-more').indexOf( '%s' ) !== -1 );

show( { tiles : [ { panel : 'users', value : '3', label : 'Users' } ] } );
check( 'an answer without notices draws none, and the tiles as before', notices().length === 0 && wrap.children[0].id === 'admin-dashboard-tiles' );

show( { tiles : [], notices : [] } );
check( '...an empty list the same', notices().length === 0 );

show( { tiles : [], notices : 'broken' } );
check( '...and a notices that is no list is ignored rather than thrown at', notices().length === 0 );

// A Wednesday, a Monday, a Friday, a Saturday, a Sunday of one January
const wednesday = ( hour, minute ) => new Date( 2026, 0, 14, hour ?? 9, minute ?? 0 );
const monday = new Date( 2026, 0, 12, 9, 0 );
const friday = new Date( 2026, 0, 16, 9, 0 );
const saturday = new Date( 2026, 0, 17, 9, 0 );
const sunday = new Date( 2026, 0, 18, 9, 0 );

pinGreetingPhases();
pinGreetingName();
pinGreetingNameIsText();
pinGreetingNeedsNoName();
pinGreetingWeekdays();
pinGreetingOccasions();
pinGreetingNoRepeat();
pinGreetingFacts();
pinGreetingSwitches();
pinGreetingStandsFirst();

done();


// --- the greeting ----------------------------------------------------------

/** Every key asked of the fills, in order - what a line was made of */
function asked() {
	return askedKeys;
}

/** The card for an answer of the server, at a moment of the browser's clock; the dice at one value unless given */
function card( answer, at, dice ) {
	panel.greeting._now = function() { return at };
	panel.greeting._random = function() { return dice ?? 0 };
	askedKeys.length = 0;
	return panel.greeting.render( Object.assign( { show : { hello : true, sentence : true, facts : true }, name : '', previousLogin : null, sentences : [] }, answer ) );
}

/** The text of one line of a card, null where it has none */
function line( element, name ) {
	if( element === null )
		return null;
	const hits = findAll( element, function( el ) { return el.className === 'nino-admin-greeting-'+ name } );
	return hits.length === 1 ? hits[0].textContent : null;
}

/** A sentence as the server delivers it */
function sentence( text, when ) {
	return Object.assign( { text : text, phase : 'any', day : 'any', occasion : 'none' }, when ?? {} );
}

/** The hour limits: the minute before and the minute of each change pick the formula of the right time of day */
function pinGreetingPhases() {
	[ [ 4, 59, 'night' ], [ 5, 0, 'early' ], [ 7, 59, 'early' ], [ 8, 0, 'morning' ], [ 10, 59, 'morning' ], [ 11, 0, 'noon' ],
		[ 13, 59, 'noon' ], [ 14, 0, 'afternoon' ], [ 16, 59, 'afternoon' ], [ 17, 0, 'evening' ], [ 21, 59, 'evening' ], [ 22, 0, 'night' ], [ 0, 0, 'night' ], [ 23, 59, 'night' ] ].forEach( function( limit ) {
		card( { name : 'Ada' }, wednesday( limit[0], limit[1] ) );
		check( 'at '+ limit[0]+ ':'+ String( limit[1] ).padStart( 2, '0' )+ ' the formula is the one of the '+ limit[2], asked().indexOf( '/_admin/dashboard/greet/hello/'+ limit[2] ) !== -1
			&& asked().filter( key => key.startsWith('/_admin/dashboard/greet/hello/') ).length === 1 );
	} );
}

/** With a name the formula is followed by a comma and the name, and ends in a full stop; without, by the full stop alone */
function pinGreetingName() {
	check( 'a name follows the formula after a comma', line( card( { name : 'David' }, wednesday( 9 ) ), 'hello' ) === text('/_admin/dashboard/greet/hello/morning')+ ', David.' );
	check( '...without one there is the formula and the full stop, and no comma', line( card( { name : '' }, wednesday( 9 ) ), 'hello' ) === text('/_admin/dashboard/greet/hello/morning')+ '.'
		&& line( card( { name : '' }, wednesday( 9 ) ), 'hello' ).indexOf(',') === -1 );
	check( '...a name that is no string is none', line( card( { name : 7 }, wednesday( 9 ) ), 'hello' ) === text('/_admin/dashboard/greet/hello/morning')+ '.' );
}

/** A name with markup or the syntax of a fill is text, in the formula and in a sentence */
function pinGreetingNameIsText() {
	const name = '<b>x</b> [[/nino/dir]] %s $&';
	const drawn = card( { name : name, sentences : [ sentence( 'Hello %s, welcome.' ) ] }, wednesday( 9 ) );
	check( 'a name with markup and a shortcode stays text in the formula', line( drawn, 'hello' ) === text('/_admin/dashboard/greet/hello/morning')+ ', '+ name+ '.' );
	check( '...and in the sentence, whose own blank takes it as it stands', line( drawn, 'sentence' ) === 'Hello '+ name+ ', welcome.' );
	check( '...and the card has nothing in it but its lines: no element came out of the name', drawn.children.length === 2 && findAll( drawn, function( el ) { return el.tagName === 'B' } ).length === 0
		&& findAll( drawn, function( el ) { return el.children.length > 0 && el !== drawn } ).length === 0 );
}

/** Without a name only the sentences that need none are drawn, whatever the dice say */
function pinGreetingNeedsNoName() {
	const sentences = [ sentence( 'One %s.' ), sentence( 'Two.' ), sentence( 'Three %s.' ), sentence( 'Four.' ) ];
	const seen = {};
	for( let dice = 0; dice < 1; dice += 0.05 )
		seen[ line( card( { name : '', sentences : sentences }, wednesday( 9 ) , dice ), 'sentence' ) ] = true;
	check( 'an account without a name gets the sentences without a blank only', Object.keys( seen ).sort().join() === 'Four.,Two.' );

	const named = {};
	for( let dice = 0; dice < 1; dice += 0.05 )
		named[ line( card( { name : 'Ada', sentences : sentences }, wednesday( 9 ) , dice ), 'sentence' ) ] = true;
	check( '...and with a name every one of them can come', Object.keys( named ).sort().join() === 'Four.,One Ada.,Three Ada.,Two.' );
	check( '...none of them with the blank left in', Object.keys( named ).every( key => key.indexOf('%s') === -1 ) );
}

/** Monday, Friday and the weekend add their sentences to those of the hour; another day does not have them */
function pinGreetingWeekdays() {
	const sentences = [ sentence( 'Hour.', { phase : 'morning' } ), sentence( 'Anytime.' ), sentence( 'Monday.', { day : 'monday' } ), sentence( 'Friday.', { day : 'friday' } ),
		sentence( 'Weekend.', { day : 'weekend' } ), sentence( 'Evening.', { phase : 'evening' } ), sentence( 'Monday evening.', { phase : 'evening', day : 'monday' } ) ];
	const texts = function( at ) {
		const seen = {};
		for( let dice = 0; dice < 1; dice += 0.01 )
			seen[ line( card( { sentences : sentences }, at, dice ), 'sentence' ) ] = true;
		return Object.keys( seen ).sort().join();
	};

	check( 'on a Monday the Monday sentences join those of the hour', texts( monday ) === 'Anytime.,Hour.,Monday.' );
	check( '...and the last one, which the dice at their highest reach, is one of the weekday', line( card( { sentences : sentences }, monday, 0.999 ), 'sentence' ) === 'Monday.' );
	check( 'on a Friday the Friday sentences', texts( friday ) === 'Anytime.,Friday.,Hour.' );
	check( 'on a Saturday and on a Sunday the weekend sentences', texts( saturday ) === 'Anytime.,Hour.,Weekend.' && texts( sunday ) === 'Anytime.,Hour.,Weekend.' );
	check( 'on a day with no pool of its own none of the three', texts( wednesday( 9 ) ) === 'Anytime.,Hour.' );
	check( 'a sentence of another hour is not among them, and one of a weekday and an hour needs both', texts( new Date( 2026, 0, 12, 19, 0 ) ) === 'Anytime.,Evening.,Monday evening.,Monday.' );
}

/** An occasion replaces the sentences of the hour and the weekday; 24-26 December, 31 December and 1 January */
function pinGreetingOccasions() {
	const sentences = [ sentence( 'Hour.', { phase : 'morning' } ), sentence( 'Anytime.' ), sentence( 'Monday.', { day : 'monday' } ),
		sentence( 'Christmas.', { occasion : 'christmas' } ), sentence( 'Christmas again.', { occasion : 'christmas', phase : 'evening' } ), sentence( 'New year.', { occasion : 'newyear' } ) ];
	const texts = function( at ) {
		const seen = {};
		for( let dice = 0; dice < 1; dice += 0.01 )
			seen[ line( card( { sentences : sentences }, at, dice ), 'sentence' ) ] = true;
		return Object.keys( seen ).sort().join();
	};

	check( 'on the 24th of December only the sentences of the occasion are drawn, the hour and the weekday ignored', texts( new Date( 2026, 11, 24, 9, 0 ) ) === 'Christmas again.,Christmas.' );
	check( '...the 25th and the 26th the same, on a Saturday as on any day', texts( new Date( 2026, 11, 25, 9, 0 ) ) === 'Christmas again.,Christmas.' && texts( new Date( 2026, 11, 26, 9, 0 ) ) === 'Christmas again.,Christmas.' );
	check( 'the 23rd and the 27th are days like any other', texts( new Date( 2026, 11, 23, 9, 0 ) ) === 'Anytime.,Hour.' && texts( new Date( 2026, 11, 27, 9, 0 ) ) === 'Anytime.,Hour.' );
	check( 'the 31st of December and the 1st of January are the new year', texts( new Date( 2026, 11, 31, 9, 0 ) ) === 'New year.' && texts( new Date( 2027, 0, 1, 9, 0 ) ) === 'New year.' );
	check( '...and the 2nd of January is not', texts( new Date( 2027, 0, 2, 9, 0 ) ) === 'Anytime.,Hour.' );
	check( 'a sentence of an occasion is never drawn on a day without one', texts( wednesday( 9 ) ).indexOf('Christmas') === -1 && texts( wednesday( 9 ) ).indexOf('New year') === -1 );
}

/** Two cards in a row with the same dice show two different sentences - and nothing but the session storage remembers it */
function pinGreetingNoRepeat() {
	const sentences = [ sentence( 'Alpha.' ), sentence( 'Beta.' ), sentence( 'Gamma.' ) ];
	delete stored[ panel.greeting._memory ];
	const first = line( card( { sentences : sentences }, wednesday( 9 ), 0.5 ), 'sentence' );
	const second = line( card( { sentences : sentences }, wednesday( 9 ), 0.5 ), 'sentence' );
	const third = line( card( { sentences : sentences }, wednesday( 9 ), 0.5 ), 'sentence' );
	check( 'the same dice twice do not show the same sentence twice', first !== second && second !== third );
	check( '...a run of loads never repeats one', ( function() {
		let last = null;
		for( let i = 0; i < 40; i++ ) {
			const now = line( card( { sentences : sentences }, wednesday( 9 ), ( i * 0.37 ) % 1 ), 'sentence' );
			if( now === last )
				return false;
			last = now;
		}
		return true;
	} )() );
	check( 'what is remembered is the last sentence, in the session storage and nowhere else', stored[ panel.greeting._memory ] === third && Object.keys( stored ).join() === panel.greeting._memory );
	check( 'a single sentence is shown twice in a row - there is no other to show', line( card( { sentences : [ sentence( 'Only.' ) ] }, wednesday( 9 ) ), 'sentence' ) === 'Only.'
		&& line( card( { sentences : [ sentence( 'Only.' ) ] }, wednesday( 9 ) ), 'sentence' ) === 'Only.' );
	check( 'where the browser keeps nothing, the card is drawn all the same', ( function() {
		const keep = sandbox.window.sessionStorage;
		sandbox.window.sessionStorage = { getItem : function() { throw new Error('blocked') }, setItem : function() { throw new Error('blocked') } };
		try {
			return line( card( { sentences : sentences }, wednesday( 9 ), 0.5 ), 'sentence' ) !== null;
		} finally {
			sandbox.window.sessionStorage = keep;
		}
	} )() );
	check( 'no sentence that fits leaves no line for it', line( card( { sentences : [ sentence( 'Evening.', { phase : 'evening' } ) ] }, wednesday( 9 ) ), 'sentence' ) === null
		&& line( card( { sentences : [] }, wednesday( 9 ) ), 'hello' ) !== null );
}

/** The facts: the last visit as today, yesterday or days ago, the log only from one entry, the backup only from eight days */
function pinGreetingFacts() {
	const now = new Date( 2026, 0, 14, 12, 0 );
	const at = ( year, month, day, hour, minute ) => Math.floor( new Date( year, month, day, hour, minute ).getTime() / 1000 );
	const facts = answer => line( card( answer, now ), 'facts' );

	check( 'the first visit is told as that', facts( { previousLogin : 0 } ) === text('/_admin/dashboard/greet/fact/first') );
	check( 'a visit earlier today', facts( { previousLogin : at( 2026, 0, 14, 0, 5 ) } ) === text('/_admin/dashboard/greet/fact/today') );
	check( 'yesterday, even a minute before midnight - it is the calendar day of the browser that counts, not 24 hours', facts( { previousLogin : at( 2026, 0, 13, 23, 59 ) } ) === text('/_admin/dashboard/greet/fact/yesterday')
		&& line( card( { previousLogin : at( 2026, 0, 13, 23, 59 ) }, new Date( 2026, 0, 14, 0, 1 ) ), 'facts' ) === text('/_admin/dashboard/greet/fact/yesterday') );
	check( 'a number of days ago, in the words of the language', facts( { previousLogin : at( 2026, 0, 9, 8, 0 ) } ) === text('/_admin/dashboard/greet/fact/days').replace( '%s', '5' ) );
	check( '...across the turn of the month and the year', line( card( { previousLogin : at( 2025, 11, 30, 8, 0 ) }, now ), 'facts' ) === text('/_admin/dashboard/greet/fact/days').replace( '%s', '15' ) );
	check( 'a visit that is no number says nothing', facts( { previousLogin : null } ) === null && facts( { previousLogin : 'yesterday' } ) === null && facts( {} ) === null );

	check( 'the log since the visit is told from one entry on, one entry in the singular', facts( { activitySince : 0 } ) === null
		&& facts( { activitySince : 1 } ) === text('/_admin/dashboard/greet/fact/activity-one')
		&& facts( { activitySince : 12 } ) === text('/_admin/dashboard/greet/fact/activity').replace( '%s', '12' ) );
	check( 'the backup only from the eighth day', facts( { backupDays : 7 } ) === null && facts( { backupDays : 8 } ) === text('/_admin/dashboard/greet/fact/backup').replace( '%s', '8' )
		&& facts( { backupDays : 0 } ) === null );
	check( 'two of them are told in one line, with a dot between', facts( { previousLogin : at( 2026, 0, 14, 8, 0 ), backupDays : 9 } ) === text('/_admin/dashboard/greet/fact/today')+ ' · '+ text('/_admin/dashboard/greet/fact/backup').replace( '%s', '9' ) );
	check( '...and never more than two: of three the visit and the backup stay', facts( { previousLogin : at( 2026, 0, 14, 8, 0 ), activitySince : 4, backupDays : 9 } ) === text('/_admin/dashboard/greet/fact/today')+ ' · '+ text('/_admin/dashboard/greet/fact/backup').replace( '%s', '9' ) );
	check( 'a figure that is no whole number is not told', facts( { activitySince : '4', backupDays : 8.5 } ) === null );
	check( 'no fact, no line - the card keeps the other two', line( card( { name : 'Ada', sentences : [ sentence( 'Hi.' ) ] }, now ), 'facts' ) === null && line( card( { name : 'Ada', sentences : [ sentence( 'Hi.' ) ] }, now ), 'sentence' ) === 'Hi.' );
}

/** Each line is switched off on its own; with all three off the card is not there */
function pinGreetingSwitches() {
	const answer = { name : 'Ada', previousLogin : 0, sentences : [ sentence( 'Hi.' ) ] };
	const lines = show => { const drawn = card( Object.assign( {}, answer, { show : show } ), wednesday( 9 ) ); return [ line( drawn, 'hello' ), line( drawn, 'sentence' ), line( drawn, 'facts' ) ].map( value => value !== null ) .join() };

	check( 'all three lines when all three are on', lines( { hello : true, sentence : true, facts : true } ) === 'true,true,true' );
	check( 'the formula alone is switched off', lines( { hello : false, sentence : true, facts : true } ) === 'false,true,true' );
	check( 'the sentence alone is switched off', lines( { hello : true, sentence : false, facts : true } ) === 'true,false,true' );
	check( 'the facts alone are switched off', lines( { hello : true, sentence : true, facts : false } ) === 'true,true,false' );
	check( 'two off leave the third', lines( { hello : false, sentence : false, facts : true } ) === 'false,false,true' );
	check( 'all three off, the card is not drawn at all', card( Object.assign( {}, answer, { show : { hello : false, sentence : false, facts : false } } ), wednesday( 9 ) ) === null );
	check( 'no answer of the server, no card', panel.greeting.render( undefined ) === null && panel.greeting.render( null ) === null );
	check( 'the card is a card of the workbench with the id the stylesheet and the shell know it by, and no heading in it', ( function() {
		const drawn = card( answer, wednesday( 9 ) );
		return drawn.id === 'admin-dashboard-greeting' && drawn.className === 'nino-admin-card nino-admin-greeting'
			&& findAll( drawn, function( el ) { return /^H[1-6]$/.test( el.tagName ) } ).length === 0 && drawn.getAttribute('aria-live') === null && drawn.getAttribute('role') === null;
	} )() );
}

/** The card is the first thing of the dashboard, before the notices; with no greeting in the answer the dashboard opens with its tiles */
function pinGreetingStandsFirst() {
	panel.greeting._now = function() { return wednesday( 9 ) };
	show( { tiles : [], notices : [ { text : '/_admin/dashboard/notice/mail', values : [ 'x', '1' ], link : '' } ], greeting : { show : { hello : true, sentence : true, facts : true }, name : 'Ada', previousLogin : 0, sentences : [] } } );
	check( 'the greeting stands before the notices', wrap.children[0].id === 'admin-dashboard-greeting' && wrap.children[1].className === 'nino-admin-notice' && wrap.children[2].id === 'admin-dashboard-tiles' );
	show( { tiles : [], notices : [] } );
	check( 'an answer without a greeting draws none, and the tiles come first', wrap.children[0].id === 'admin-dashboard-tiles' );
}
