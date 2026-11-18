/**
 *	Nino										A compact filesystembased php framework
 *	admin-datetext-js-smoke.js	Checks for Nino.adminUi.dateText() (_admin/assets/datetext.js): the one
 *													way the workbench writes a date and a time it draws itself, in
 *													the form of the interface language - one form for German and
 *													one for every other language, the clock of the value kept, no
 *													seconds.
 *
 *	Usage: node tests/admin-datetext-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load } = require( './harness.js' );

/** A page whose interface language is the one given, with the namespace the helper extends */
function page( lang ) {
	const Nino = { adminUi : {} };
	const sandbox = { Nino : Nino, document : { documentElement : { lang : lang }, body : null } };
	sandbox.window = { Nino : Nino };
	load( '_admin/assets/datetext.js', vm.createContext( sandbox ) );
	return Nino.adminUi;
}

console.log('Admin dateText');

const german = page('de');
const other = page('en');

check( 'a German interface writes day, month and year with dots, and the time after a comma', german.dateText('2026-11-17 06:02') === '17.11.2026, 06:02' );
check( 'every other interface writes the year first, with hyphens, and the time after a comma', other.dateText('2026-11-17 06:02') === '2026-11-17, 06:02' && page('fr').dateText('2026-11-17 06:02') === '2026-11-17, 06:02' );
check( 'a language code that only begins with the letters of German is no German: the form follows the code', page('dev').dateText('2026-11-17') === '2026-11-17' && page('de-DE').dateText('2026-11-17') === '17.11.2026' );
check( 'a date with no time of day is the date alone, and so is any value asked without the time', german.dateText('2026-11-17') === '17.11.2026' && other.dateText('2026-11-17 06:02', { time : false } ) === '2026-11-17' );
check( 'the seconds of a value are read and left out, and a T is a space', german.dateText('2026-11-17T06:02:59') === '17.11.2026, 06:02' && other.dateText('2026-11-17 06:02:59') === '2026-11-17, 06:02' );
check( 'a string is kept on the clock it carries, and a Date is read on the clock of the browser', other.dateText( new Date( 2026, 10, 17, 6, 2, 30 ) ) === '2026-11-17, 06:02' );
check( 'what is no moment is no text: an empty string, a made-up month, a number, nothing', [ '', 'yesterday', '17.11.2026', 1700000000, null, undefined, new Date( NaN ) ].every( function( value ) { return other.dateText( value ) === '' } ) );
check( 'digits that cannot be a moment are none: a month, a day, an hour or a minute out of range gives no text, so that a broken line does not look like a date', [ '2026-13-45 99:99', '2026-13-01', '2026-00-10', '2026-11-32', '2026-11-17 24:00', '2026-11-17 06:60' ].every( function( value ) { return other.dateText( value ) === '' } ) && other.dateText('2026-12-31 23:59') !== '' );
check( 'the language can be named by the caller', german.dateText( '2026-11-17 06:02', { lang : 'en' } ) === '2026-11-17, 06:02' );

done();
