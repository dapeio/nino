<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	format-smoke.php		Dependency-free smoke test for \Nino\Format: the registry of the
 *											formatters - add(), registered(), options(), apply() - the seven
 *											built in with the outputs of the table in both languages, the
 *											values they cannot read, the words of a language and the texts
 *											that override them, the currency of the configuration, and the
 *											answer of a formatter of a project. Nothing here reads the clock,
 *											the time zone of the server or its locale.
 *											Runs against an isolated sandbox directory, never touches the real
 *											project data.
 *
 *	Usage: php tests/format-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'format' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

$nb = "\u{00A0}";

/*	One call: the formatter by its name, in a language	*/
$write = static function( string $name, mixed $value, string $locale ) use ( &$appData ): ?string {
	$appData['./nino/locales/current'] = $locale;
	return \Nino\Format::apply( $appData, $name, $value );
};


// --- The registry ---------------------------------------------------------

echo "The registry - the seven built in, add(), options()\n";

$builtin = [ 'number', 'currency', 'percent', 'date', 'date-long', 'datetime', 'time' ];
check( 'registered() lists the seven formatters built in, in the order options() offers them', array_keys( \Nino\Format::registered( $appData ) ) === $builtin );
$options = \Nino\Format::options( $appData, 'as' );
check( 'options() starts with the empty word, then the formatters by name - each with a label in both languages', array_keys( $options ) === array_merge( [ '' ], $builtin )
	&& array_filter( $options, static fn( array $label ): bool => array_keys( $label ) !== [ 'en_US', 'de_DE' ] || $label['en_US'] === '' || $label['de_DE'] === '' ) === [] );

$nights = static function( array &$appData, mixed $value ): ?string {
	$number = \Nino\Format::number( $appData, $value );
	return $number === null ? null : $number. ( (string) $value === '1' ? ' Nacht' : ' Nächte' );
};
\Nino\Format::add( $appData, 'nights', $nights, [ 'en_US' => 'Nights', 'de_DE' => 'Nächte' ] );
check( 'add() puts a formatter of the project after the built-in ones, and it formats', array_keys( \Nino\Format::registered( $appData ) ) === array_merge( $builtin, [ 'nights' ] )
	&& array_keys( \Nino\Format::options( $appData, 'as' ) ) === array_merge( [ '' ], $builtin, [ 'nights' ] )
	&& \Nino\Format::options( $appData, 'as' )['nights'] === [ 'en_US' => 'Nights', 'de_DE' => 'Nächte' ]
	&& $write( 'nights', 3, 'de_DE' ) === '3 Nächte' && $write( 'nights', '1', 'en_US' ) === '1 Nacht' && $write( 'nights', 'abc', 'en_US' ) === null );

\Nino\Format::add( $appData, 'currency', static fn( array &$appData, mixed $value ): ?string => 'own', 'Own' );
check( 'add() with the name of a built-in formatter replaces it at its place', array_keys( \Nino\Format::registered( $appData ) ) === array_merge( $builtin, [ 'nights' ] )
	&& $write( 'currency', 5, 'de_DE' ) === 'own' && \Nino\Format::options( $appData, 'as' )['currency'] === 'Own' );

$refused = [];
foreach( [ 'Bad Name', '../x', '', 'Upper', '1a', 'a_b', "a\n", 'strtoupper()' ] as $name )
	$refused[$name] = ninoErrors( static function() use ( &$appData, $name, $nights ): void { \Nino\Format::add( $appData, $name, $nights, 'Label' ); } );
check( 'add() refuses a name that is no slug, as addComponent() refuses a schema: an E_USER_ERROR, and nothing is registered',
	array_filter( $refused, static fn( array $errors ): bool => count( $errors ) !== 1 || $errors[0][0] !== E_USER_ERROR ) === []
	&& array_keys( \Nino\Format::registered( $appData ) ) === array_merge( $builtin, [ 'nights' ] ) );
$refused = [];
foreach( [ '', '  ', [], [ 'en_US' => '' ], [ 'x' ], [ 'en_US' => 5 ], 5, null, 1.5, true ] as $index => $label )
	$refused[$index] = ninoErrors( static function() use ( &$appData, $nights, $label ): void { \Nino\Format::add( $appData, 'labelled', $nights, $label ); } );
check( '...and a label that is no string or locale => string map', array_filter( $refused, static fn( array $errors ): bool => count( $errors ) !== 1 || $errors[0][0] !== E_USER_ERROR ) === []
	&& isset( \Nino\Format::registered( $appData )['labelled'] ) === false );

check( 'apply() of a name nobody registered is null, and says nothing - a name a template could make up is none of the registry',
	array_filter( [ 'system', 'strtoupper', '\Nino\Format::number', 'Number', 'number ', '', 'nights()', 'phpinfo' ], static fn( string $name ): bool => $write( $name, '1234.5', 'de_DE' ) !== null ) === [] && ninoWarnings() === [] );

// A new sandbox: the registry above has the replaced currency
\Nino\Filesystem::removeDir( $sandbox );
$appData = ninoSandbox( 'format-clean' );


// --- The outputs, the table in both languages --------------------------------

echo "The built-in formatters - numbers\n";

$numbers = [
	// value, formatter, de_DE, en_US
	[ 1234.5, 'number', '1.234,5', '1,234.5' ],
	[ 1234, 'number', '1.234', '1,234' ],
	[ 0.1 + 0.2, 'number', '0,3', '0.3' ],
	[ -1234.567, 'number', '-1.234,567', '-1,234.567' ],
	[ 1234.5, 'currency', "1.234,50{$nb}€", '€1,234.50' ],
	[ -1234.567, 'currency', "-1.234,57{$nb}€", '-€1,234.57' ],
	[ -0.004, 'currency', "0,00{$nb}€", '€0.00' ],
	[ 2.675, 'currency', "2,68{$nb}€", '€2.68' ],
	[ 0.005, 'currency', "0,01{$nb}€", '€0.01' ],
	[ 19, 'percent', "19{$nb}%", '19%' ],
	[ 7.5, 'percent', "7,5{$nb}%", '7.5%' ],
	[ '1234.50', 'currency', "1.234,50{$nb}€", '€1,234.50' ],
	[ '1234.50', 'number', '1.234,5', '1,234.5' ],
	[ 999999999999999.0, 'number', '999.999.999.999.999', '999,999,999,999,999' ],
	[ 1e-7, 'number', '0', '0' ],
	[ -1e-7, 'number', '0', '0' ],
	[ -0.0, 'number', '0', '0' ],
	[ '-0', 'number', '0', '0' ],
	[ '-0.00', 'currency', "0,00{$nb}€", '€0.00' ],
	[ 0, 'currency', "0,00{$nb}€", '€0.00' ],
	[ 123, 'currency', "123,00{$nb}€", '€123.00' ],
	[ 1000, 'number', '1.000', '1,000' ],
	[ 100, 'number', '100', '100' ],
	[ -100, 'percent', "-100{$nb}%", '-100%' ],
];
$wrong = [];
foreach( $numbers as [ $value, $name, $de, $en ] ) {
	if( $write( $name, $value, 'de_DE' ) !== $de )
		$wrong[] = $name. ' '. var_export( $value, true ). ' de_DE';
	if( $write( $name, $value, 'en_US' ) !== $en )
		$wrong[] = $name. ' '. var_export( $value, true ). ' en_US';
}
check( 'every row of the table of numbers is written as the table says, in German and in American English'. ( $wrong === [] ? '' : ' - '. implode( ', ', $wrong ) ), $wrong === [] );
check( '...the money and the percent keep the number and its unit on one line: the space between them is the no-break space, in German', str_contains( (string) $write( 'currency', 1, 'de_DE' ), $nb ) === true
	&& str_contains( (string) $write( 'percent', 1, 'de_DE' ), $nb ) === true && str_contains( (string) $write( 'currency', 1, 'en_US' ), ' ' ) === false );

$unreadable = [ '12,50', ' 12.5', '12.5 ', '1e5', '0x1A', '007', '+5', '.5', '5.', '1.0000000000001', '1234567890123456', true, false, '', '   ', [], new \stdClass, null, NAN, INF, -INF, 1e15, -1e15, PHP_INT_MAX, PHP_INT_MIN, "12\0", "1\n", '1 000', '१२', "\xB1" ];
$wrong = [];
foreach( $unreadable as $index => $value )
	foreach( [ 'number', 'currency', 'percent' ] as $name )
		foreach( [ 'de_DE', 'en_US' ] as $locale )
			if( $write( $name, $value, $locale ) !== null )
				$wrong[] = $name. ' '. $index. ' '. $locale;
check( 'a value that is no number is not written by any formatter of numbers: a bool, an empty string, a comma, spaces, an exponent, hex, a leading zero, NAN, INF, 1e15 and beyond, PHP_INT_MAX, an array, an object, null'. ( $wrong === [] ? '' : ' - '. implode( ', ', $wrong ) ), $wrong === [] );
check( '...a big value with decimals is written as far as a double holds them: no digits past the fifteenth',
	( static function() use ( $write ): bool {
		return $write( 'number', 123456789012345.67, 'en_US' ) === '123,456,789,012,346' && $write( 'number', 1234567890123.45, 'en_US' ) === '1,234,567,890,123.45' && $write( 'number', 0.1 + 0.2, 'en_US' ) === '0.3';
	} )() === true );
check( '...a value under the limit that rounds up to it is not written either, as a number, a percent or an amount',
	( static function() use ( $write, &$appData ): bool {
		$appData['./nino/locales/current'] = 'en_US';
		foreach( [ 999999999999999.9, '999999999999999.9', -999999999999999.9 ] as $value )
			foreach( [ 'number', 'percent' ] as $name )
				if( $write( $name, $value, 'en_US' ) !== null )
					return false;
		// An amount keeps its decimals inside the fifteen digits: ten million millions with cents is not written, a tenth of it is
		if( $write( 'currency', '99999999999999.99', 'en_US' ) !== null || $write( 'currency', 9999999999999.99, 'en_US' ) !== '€9,999,999,999,999.99' )
			return false;
		if( \Nino\Format::amount( $appData, 99999999999999.99, 2 ) !== null || \Nino\Format::amount( $appData, 9999999999999.99, 2 ) !== '9,999,999,999,999.99' )
			return false;
		return \Nino\Format::amount( $appData, 999999999999999.9, 0 ) === null;
	} )() === true );
check( '...and no formatter of the kernel raises anything, whatever it is handed', ninoWarnings() === [] );
check( '...amount() writes a number with the separators of the language and the decimals asked for, and is null beyond what a float holds exactly',
	( static function() use ( &$appData ): bool {
		$appData['./nino/locales/current'] = 'de_DE';
		$de = \Nino\Format::amount( $appData, 1234567.891, 2 ) === '1.234.567,89' && \Nino\Format::amount( $appData, 5, 0 ) === '5' && \Nino\Format::amount( $appData, -0.001, 2 ) === '0,00';
		$appData['./nino/locales/current'] = 'en_US';
		return $de && \Nino\Format::amount( $appData, 1234567.891, 2 ) === '1,234,567.89' && \Nino\Format::amount( $appData, NAN, 2 ) === null && \Nino\Format::amount( $appData, 1e15, 2 ) === null && \Nino\Format::amount( $appData, INF, 0 ) === null;
	} )() === true );


echo "The built-in formatters - dates and times\n";

$dates = [
	// value, formatter, de_DE, en_US
	[ '2026-11-11', 'date', '11.11.2026', '11/11/2026' ],
	[ '2026-11-11', 'date-long', '11. November 2026', 'November 11, 2026' ],
	[ '2026-03-05T09:05', 'date', '05.03.2026', '3/5/2026' ],
	[ '2026-03-05T09:05', 'date-long', '5. März 2026', 'March 5, 2026' ],
	[ '2026-03-05T09:05', 'datetime', '05.03.2026, 09:05', "3/5/2026, 9:05{$nb}AM" ],
	[ '2026-03-05T09:05', 'time', '09:05', "9:05{$nb}AM" ],
	[ '2026-11-11T14:30:00', 'datetime', '11.11.2026, 14:30', "11/11/2026, 2:30{$nb}PM" ],
	[ '2026-11-11 00:15', 'time', '00:15', "12:15{$nb}AM" ],
	[ '2026-11-11 12:00:59', 'time', '12:00', "12:00{$nb}PM" ],
	[ '2026-11-11', 'datetime', '11.11.2026', '11/11/2026' ],
	[ '14:30', 'time', '14:30', "2:30{$nb}PM" ],
	[ '14:30:15', 'time', '14:30', "2:30{$nb}PM" ],
	[ '2024-02-29', 'date', '29.02.2024', '2/29/2024' ],
	[ '0001-01-01', 'date', '01.01.0001', '1/1/0001' ],
	[ '9999-12-31', 'date-long', '31. Dezember 9999', 'December 31, 9999' ],
];
$wrong = [];
foreach( $dates as [ $value, $name, $de, $en ] ) {
	if( $write( $name, $value, 'de_DE' ) !== $de )
		$wrong[] = $name. ' '. $value. ' de_DE';
	if( $write( $name, $value, 'en_US' ) !== $en )
		$wrong[] = $name. ' '. $value. ' en_US';
}
check( 'every row of the table of dates and times is written as the table says, in German and in American English'. ( $wrong === [] ? '' : ' - '. implode( ', ', $wrong ) ), $wrong === [] );

check( 'a date alone has no time: the formatter of a time is null for it, and one of a date and time writes just the date', $write( 'time', '2026-11-11', 'de_DE' ) === null && $write( 'time', '2026-11-11', 'en_US' ) === null
	&& $write( 'datetime', '2026-11-11', 'de_DE' ) === '11.11.2026' );
check( 'a time alone has no date: the formatters of a date and of a date and time are null for it', $write( 'date', '14:30', 'de_DE' ) === null && $write( 'date-long', '14:30', 'en_US' ) === null && $write( 'datetime', '14:30', 'en_US' ) === null );

$unreadable = [ '2026-02-30', '2026-02-29', '2026-13-01', '2026-00-10', '2026-2-3', '2026-11-1', '99999-01-01', '0000-01-01', '0000-00-00', '-001-01-01', '2026-11-11T25:00', '2026-11-11T14:60', '2026-11-11T14:30:60',
	'2026-11-11T14:30:00+01:00', '2026-11-11T14:30:00Z', '2026-11-11Z', '2026-11-11T14:30:00.000', '  2026-11-11', '2026-11-11 ', "2026-11-11\n", "2026-11-11\0", '2026/11/11', '11.11.2026', 'tomorrow', 'now', '@1731330000', '1731330000',
	1731330000, 20261111, 2026.11, 0, true, null, [], [ '2026-11-11' ], new \stdClass, "\xFF2026-11-11", '24:00', '2026-11-11T24:00', '' ];
$wrong = [];
foreach( $unreadable as $index => $value )
	foreach( [ 'date', 'date-long', 'datetime', 'time' ] as $name )
		foreach( [ 'de_DE', 'en_US' ] as $locale )
			if( $write( $name, $value, $locale ) !== null )
				$wrong[] = $name. ' '. $index. ' '. $locale;
check( 'a value that is no date is not written by any formatter of dates: 2026-02-30 which php would roll over, a month 13, a year of five digits, year 0, an hour 25, an offset, a zone, a timestamp, a string of the wrong form, spaces around it, an array'. ( $wrong === [] ? '' : ' - '. implode( ', ', $wrong ) ), $wrong === [] );
check( '...and none raises anything', ninoWarnings() === [] );

// A wall-clock time is written as it stands: neither the zone of the server nor its clock is read
$battery = static function() use ( $write, $numbers, $dates ): array {
	$all = [];
	foreach( array_merge( $numbers, $dates ) as [ $value, $name ] )
		foreach( [ 'de_DE', 'en_US' ] as $locale )
			$all[] = $write( $name, $value, $locale );
	return $all;
};
$zone = date_default_timezone_get();
date_default_timezone_set( 'Pacific/Kiritimati' );
$east = $battery();
date_default_timezone_set( 'Pacific/Pago_Pago' );
$west = $battery();
date_default_timezone_set( $zone );
check( 'the output does not depend on the time zone of the server: +14 hours and -11 hours write the same', $east === $west && $east === $battery() && in_array( null, array_slice( $east, 0, 4 ), true ) === false );


// --- The words of a language ------------------------------------------------------

echo "The words - built in per language, overridden by the texts of the language\n";

$appData['./nino/locales/current'] = 'de_DE';
check( 'words() are German for a language that begins with de and American English for every other', \Nino\Format::words( $appData )['decimal'] === ',' && \Nino\Format::words( $appData )['months'][2] === 'März'
	&& ( static function() use ( &$appData ): bool {
		$appData['./nino/locales/current'] = 'en_US';
		return \Nino\Format::words( $appData )['decimal'] === '.' && \Nino\Format::words( $appData )['months'][2] === 'March';
	} )() === true
	&& ( static function() use ( &$appData ): bool {
		foreach( [ 'fr_FR', 'en_GB', 'ja_JP', 'xx_XX', 'dex_X', 'de' ] as $locale ) {
			$appData['./nino/locales/current'] = $locale;
			$decimal = \Nino\Format::words( $appData )['decimal'];
			if( $decimal !== ( $locale === 'de' ? ',' : '.' ) )
				return false;
		}
		return true;
	} )() === true );
check( '...twelve months, and the words a pattern is made of', ( static function() use ( &$appData ): bool {
	$appData['./nino/locales/current'] = 'en_US';
	$words = \Nino\Format::words( $appData );
	return count( $words['months'] ) === 12 && array_diff( [ 'decimal', 'thousands', 'currency', 'percent', 'date', 'date-long', 'datetime', 'time', 'am', 'pm', 'months' ], array_keys( $words ) ) === [];
} )() === true );

// Texts of the project: German is the native language; a text only German has is not the English page's
$words = ninoSandbox( 'format-words' );
$words['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
$words['/nino/dir'] = '';
\Nino\Filesystem::putFileContent( $words, '/text/de_DE.php', [
	'[[/template/common/format/decimal]]'		=> '·',
	'[[/template/common/month/january]]'		=> 'Jänner',
	'[[/template/common/format/thousands]]'	=> " \n ",
	'[[/template/common/format/am]]'				=> 'vorm.',
] );
\Nino\Filesystem::putFileContent( $words, '/text/en_US.php', [
	'[[/template/common/format/currency]]'	=> '{amount} bucks',
	'[[/template/common/format/percent]]'		=> 'per cent',
	'[[/template/common/format/time]]'			=> '<b>{hour}</b> [[x]] {minute2}',
	'[[/template/common/format/date]]'			=> '{day}.{month}.{year} {unknown}',
	'[[/template/common/month/march]]'			=> '   ',
] );
$wordsWrite = static function( string $name, mixed $value, string $locale ) use ( &$words ): ?string {
	$words['./nino/locales/current'] = $locale;
	return \Nino\Format::apply( $words, $name, $value );
};
check( 'a text the language has of its own overrides the word: a decimal separator, a month', $wordsWrite( 'number', 1234.5, 'de_DE' ) === '1.234·5' && $wordsWrite( 'date-long', '2026-01-05', 'de_DE' ) === '5. Jänner 2026'
	&& $wordsWrite( 'date-long', '2026-02-05', 'de_DE' ) === '5. Februar 2026' );
check( '...a text only another language has is not used: the English page does not write the decimal separator or the month of the German text, whichever way the fills fall back',
	$wordsWrite( 'number', 1234.5, 'en_US' ) === '1,234.5' && $wordsWrite( 'date-long', '2026-01-05', 'en_US' ) === 'January 5, 2026' && $wordsWrite( 'time', '2026-01-05 09:00', 'de_DE' ) === '09:00' );
check( '...nor does a language that has no words and no texts of its own, although the native language has: it writes American English', $wordsWrite( 'number', 1234.5, 'fr_FR' ) === '1,234.5'
	&& $wordsWrite( 'date-long', '2026-01-05', 'fr_FR' ) === 'January 5, 2026' && $wordsWrite( 'currency', 1.5, 'fr_FR' ) === '€1.50' );
check( '...an empty or whitespace text is no text: the built-in word stays', $wordsWrite( 'number', 1234.5, 'de_DE' ) !== null && str_contains( (string) $wordsWrite( 'number', 1234.5, 'de_DE' ), '.' ) === true
	&& $wordsWrite( 'date-long', '2026-03-05', 'en_US' ) === 'March 5, 2026' );
check( '...a pattern with no {amount} writes what it says, a pattern of the language writes its words, and a placeholder that is none stays as it is',
	$wordsWrite( 'currency', 5, 'en_US' ) === '5.00 bucks' && $wordsWrite( 'percent', 5, 'en_US' ) === 'per cent' && $wordsWrite( 'date', '2026-03-05', 'en_US' ) === '5.3.2026 {unknown}' );
check( '...a pattern is text and stays text: the formatter writes the markup of the pattern as it stands, for the kernel to escape, and a [[ in it is no fill',
	$wordsWrite( 'time', '2026-01-05 09:07', 'en_US' ) === '<b>9</b> [[x]] 07' );
check( '...the texts did not raise anything', ninoWarnings() === [] );
\Nino\Filesystem::removeDir( ninoSandboxDir( $words ) );


// --- The currency ----------------------------------------------------------------

echo "The currency - '/nino/format/currency'\n";

$currency = [ 'USD' => [ "12,50{$nb}\$", '$12.50' ], 'GBP' => [ "12,50{$nb}£", '£12.50' ], 'EUR' => [ "12,50{$nb}€", '€12.50' ], 'JPY' => [ "12,50{$nb}¥", '¥12.50' ],
	'CHF' => [ "12,50{$nb}CHF", "CHF{$nb}12.50" ], 'SEK' => [ "12,50{$nb}SEK", "SEK{$nb}12.50" ],
	'chf' => [ "12,50{$nb}€", '€12.50' ], 'EURO' => [ "12,50{$nb}€", '€12.50' ], 'EU' => [ "12,50{$nb}€", '€12.50' ], '' => [ "12,50{$nb}€", '€12.50' ], 'E1R' => [ "12,50{$nb}€", '€12.50' ], ' USD' => [ "12,50{$nb}€", '€12.50' ] ];
$wrong = [];
foreach( $currency as $code => [ $de, $en ] ) {
	$appData['/nino/format/currency'] = $code;
	if( $write( 'currency', 12.5, 'de_DE' ) !== $de || $write( 'currency', 12.5, 'en_US' ) !== $en )
		$wrong[] = $code;
}
check( 'the currency is the code of three capital letters the configuration names - a symbol for the five the kernel knows, the code itself for another, a letter symbol set off by the no-break space - and EUR for anything else'. ( $wrong === [] ? '' : ' - '. implode( ', ', $wrong ) ), $wrong === [] );
$appData['/nino/format/currency'] = 5;
check( '...and a configuration that is no string is EUR', $write( 'currency', 12.5, 'en_US' ) === '€12.50' );
$appData['/nino/format/currency'] = [ 'USD' ];
check( '...an array is none either, and raises nothing', $write( 'currency', 12.5, 'en_US' ) === '€12.50' && ninoWarnings() === [] );
unset( $appData['/nino/format/currency'] );
check( '...and none set at all', $write( 'currency', 12.5, 'en_US' ) === '€12.50' && \Nino\AppData::DEFAULTS['/nino/format/currency'] === 'EUR' );


// --- The formatter of a project -------------------------------------------------------

echo "A formatter of a project - the answer it gives\n";

$project = ninoSandbox( 'format-project' );
\Nino\Format::add( $project, 'null', static fn( array &$appData, mixed $value ): ?string => null, 'Null' );
\Nino\Format::add( $project, 'int', static fn( array &$appData, mixed $value ): mixed => 42, 'Int' );
\Nino\Format::add( $project, 'array', static fn( array &$appData, mixed $value ): mixed => [ 'x' ], 'Array' );
\Nino\Format::add( $project, 'echo', static fn( array &$appData, mixed $value ): ?string => is_scalar( $value ) ? (string) $value : null, 'Echo' );
\Nino\Format::add( $project, 'markup', static fn( array &$appData, mixed $value ): ?string => '<b>"x"</b> [[/project/company/contact/email]] [image /logo]', 'Markup' );
\Nino\Format::add( $project, 'throws', static function( array &$appData, mixed $value ): ?string { throw new \RuntimeException( 'boom' ); }, 'Throws' );
\Nino\Format::add( $project, 'empty', static fn( array &$appData, mixed $value ): ?string => '', 'Empty' );

ninoWarnings();
$answers = [];
$errors = ninoErrors( static function() use ( &$project, &$answers ): void {
	$answers = [ \Nino\Format::apply( $project, 'null', 'x' ), \Nino\Format::apply( $project, 'null', 'y' ) ];
} );
check( 'a formatter that answers null leaves the value as it is, with no warning', $answers === [ null, null ] && $errors === [] );
$answers = [];
$errors = ninoErrors( static function() use ( &$project, &$answers ): void {
	$answers = [ \Nino\Format::apply( $project, 'int', 'x' ), \Nino\Format::apply( $project, 'int', 'y' ), \Nino\Format::apply( $project, 'array', 'y' ), \Nino\Format::apply( $project, 'array', 'z' ) ];
} );
check( 'a formatter that answers no string leaves the value as it is and warns once - once for each formatter in a request, not for each value',
	$answers === [ null, null, null, null ] && count( $errors ) === 2 && $errors[0][0] === E_USER_WARNING && $errors[1][0] === E_USER_WARNING
	&& str_contains( $errors[0][1], '"int"' ) === true && str_contains( $errors[1][1], '"array"' ) === true );
check( 'the answer of a formatter is handed on as it is, plain text - the kernel that asked escapes it', \Nino\Format::apply( $project, 'markup', 'x' ) === '<b>"x"</b> [[/project/company/contact/email]] [image /logo]'
	&& \Nino\Format::apply( $project, 'empty', 'x' ) === '' && \Nino\Format::apply( $project, 'echo', 12.5 ) === '12.5' && \Nino\Format::apply( $project, 'echo', [] ) === null );
$thrown = null;
$errors = ninoErrors( static function() use ( &$project, &$thrown ): void {
	try {
		\Nino\Format::apply( $project, 'throws', 'x' );
	}
	catch( \RuntimeException $exception ) {
		$thrown = $exception->getMessage();
	}
} );
check( 'a formatter that throws is not caught: a programming error of a project is seen', $thrown === 'boom' && $errors === [] );
check( 'a formatter replaced by add() is warned about again - the warning is about the one that answered', ( static function() use ( &$project ): bool {
	\Nino\Format::add( $project, 'int', static fn( array &$appData, mixed $value ): mixed => 7, 'Int' );
	$errors = ninoErrors( static function() use ( &$project ): void { \Nino\Format::apply( $project, 'int', 'x' ); } );
	return count( $errors ) === 1 && $errors[0][0] === E_USER_WARNING;
} )() === true );
\Nino\Filesystem::removeDir( ninoSandboxDir( $project ) );

echo "\n";

ninoDone( $appData );
