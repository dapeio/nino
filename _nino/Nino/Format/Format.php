<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Format							How a number or a date is written, by the language of the page
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Format						The formatters a component names by the attribute "as": a
	 *										registry of names, each a function that gets the raw value
	 *										of a field or a text and answers plain text, or null where
	 *										it cannot read the value - the value is then shown as it
	 *										is shown without a formatter. The kernel escapes the
	 *										answer (\Nino\Modules\Components::escape()); a formatter
	 *										never writes html. A template names a formatter and never
	 *										a callable: only what add() registered is ever called.
	 *
	 *										The seven built in write a number or a date in the
	 *										conventions of the language of the page - German for a
	 *										language that begins with "de", American English for
	 *										every other - with no intl and no setlocale(), so that a
	 *										page reads the same on every host. A language overrides
	 *										each word by a text of its own, read only where the
	 *										language has one: /template/common/format/<word> and
	 *										/template/common/month/<month>. A date is a wall-clock
	 *										time, written as it stands, with no zone and no clock.
	 *										A built-in formatter throws nothing and raises nothing.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Format {

		// The formatters this request added with add(): name => [ 'callback', 'label' ]
		public const string REGISTRY	= './nino/format';

		// The names add() answered with a warning, once each: the formatter did not answer a string
		private const string WARNED		= './nino/format/warned';

		// The built-in formatters in the order options() offers them: name => [ the method of this class, label ]
		public const array BUILTIN = [
			'number'		=> [ 'number',		[ 'en_US' => 'Number',					'de_DE' => 'Zahl' ] ],
			'currency'	=> [ 'currency',	[ 'en_US' => 'Currency',				'de_DE' => 'Währung' ] ],
			'percent'		=> [ 'percent',		[ 'en_US' => 'Percent',					'de_DE' => 'Prozent' ] ],
			'date'			=> [ 'date',			[ 'en_US' => 'Date (short)',		'de_DE' => 'Datum kurz' ] ],
			'date-long'	=> [ 'dateLong',	[ 'en_US' => 'Date (long)',			'de_DE' => 'Datum lang' ] ],
			'datetime'	=> [ 'datetime',	[ 'en_US' => 'Date and time',		'de_DE' => 'Datum und Uhrzeit' ] ],
			'time'			=> [ 'time',			[ 'en_US' => 'Time',						'de_DE' => 'Uhrzeit' ] ],
		];

		// The words of the two languages built in, with the patterns of the dates and of the amounts.
		// {amount} and {symbol} stand in a currency and a percent pattern; {day}, {day2}, {month},
		// {month2}, {monthname}, {year}, {hour}, {hour2}, {hour12}, {minute2} and {ampm} in a date's.
		// The no-break space (U+00A0) keeps a number and its unit on one line
		private const array WORDS = [
			'de' => [
				'decimal'		=> ',',
				'thousands'	=> '.',
				'currency'	=> "{amount}\u{00A0}{symbol}",
				'percent'		=> "{amount}\u{00A0}%",
				'date'			=> '{day2}.{month2}.{year}',
				'date-long'	=> '{day}. {monthname} {year}',
				'datetime'	=> '{day2}.{month2}.{year}, {hour2}:{minute2}',
				'time'			=> '{hour2}:{minute2}',
				'am'				=> 'AM',
				'pm'				=> 'PM',
			],
			'en' => [
				'decimal'		=> '.',
				'thousands'	=> ',',
				'currency'	=> '{symbol}{amount}',
				'percent'		=> '{amount}%',
				'date'			=> '{month}/{day}/{year}',
				'date-long'	=> '{monthname} {day}, {year}',
				'datetime'	=> "{month}/{day}/{year}, {hour12}:{minute2}\u{00A0}{ampm}",
				'time'			=> "{hour12}:{minute2}\u{00A0}{ampm}",
				'am'				=> 'AM',
				'pm'				=> 'PM',
			],
		];

		// The names of the months in the order of the year: the key the text of a language has is /template/common/month/<name>
		private const array MONTHS = [
			'de' => [ 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ],
			'en' => [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ],
		];
		private const array MONTH_KEYS = [ 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december' ];

		// The symbol a currency is written with, by its code; a code that is not here is its own symbol
		private const array SYMBOLS = [ 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF', 'JPY' => '¥' ];

		// The currency written when the configuration names none that is three capital letters
		private const string CURRENCY = 'EUR';

		// A number a formatter reads from a string: canonical, with a point, no grouping and no space
		private const string NUMBER = '/^-?(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,12})?$/D';

		// A number of 1e15 or more is no longer exact in a float and is not written
		private const float LIMIT = 1e15;

		// The forms of a date or a time a formatter reads, as createFromFormat() has them: false a date, true a date and a time, 'time' a time alone
		private const array FORMS = [
			'Y-m-d'				=> false,
			'Y-m-d\TH:i'		=> true,
			'Y-m-d\TH:i:s'	=> true,
			'Y-m-d H:i'			=> true,
			'Y-m-d H:i:s'		=> true,
			'H:i'					=> 'time',
			'H:i:s'				=> 'time',
		];

		/**
		 *	Register a formatter, or replace one - a built-in too, at its place. From
		 *	the init() of a module or a feature. It is called with the raw value
		 *	( array &$appData, mixed $value ): ?string and answers plain text, or
		 *	null for a value it cannot read; the kernel escapes the answer. A name
		 *	that is no slug or a label that is no string or locale => string map is
		 *	refused as addComponent() refuses a schema
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					A slug: lower case letters, digits and hyphens, a letter first
		 *	@param		callable	$callback			( array &$appData, mixed $value ): ?string
		 *	@param		mixed			$label				What a tool lists it as: a string or a locale => string map
		 *
		 *	@return 	void
		 */
		public static function add( array &$appData, string $name, callable $callback, mixed $label ): void {

			if( preg_match( '/^[a-z][a-z0-9-]*$/D', $name ) !== 1 )
				trigger_error( 'Format::add(): a name is a slug of lower case letters, digits and hyphens, a letter first', E_USER_ERROR );
			elseif( \Nino\Features::localizedValid( $label ) === false )
				trigger_error( 'Format::add(): formatter "'. $name. '": "label" must be a string or a locale => string map', E_USER_ERROR );
			else {
				$appData[self::REGISTRY][$name] = [ 'callback' => $callback, 'label' => $label ];
				unset( $appData[self::WARNED][$name] );
			}
		}

		/**
		 *	The formatters there are: the built-in ones first, in the order options()
		 *	offers them - one that add() replaced at its place - then the others in
		 *	the order they were added
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										name => [ 'callback' => callable, 'label' => string|array ]
		 */
		public static function registered( array &$appData ): array {

			$added		= (array) ( $appData[self::REGISTRY] ?? [] );
			$formats	= [];

			foreach( self::BUILTIN as $name => [ $method, $label ] )
				$formats[$name] = $added[$name] ?? [ 'callback' => [ self::class, $method ], 'label' => $label ];

			return $formats + $added;
		}

		/**
		 *	The options of the attribute "as" of a component that is formatted - the
		 *	signature of the options method of an attribute: the empty word, which
		 *	shows the value as it is, and every formatter by its name
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$attribute		The attribute asking
		 *
		 *	@return 	array										value => label, a string or a locale => string map
		 */
		public static function options( array &$appData, string $attribute ): array {

			$options = [ '' => [ 'en_US' => 'As stored', 'de_DE' => 'Wie gespeichert' ] ];

			foreach( self::registered( $appData ) as $name => $format )
				$options[$name] = $format['label'];

			return $options;
		}

		/**
		 *	Write a value by the formatter of that name, in the language of the page.
		 *	A name nobody registered, a value the formatter cannot read and an answer
		 *	that is no string are all null, and the caller shows the value as it
		 *	stands; the last is a warning too, once for each formatter in a request.
		 *	The answer is plain text and not yet escaped
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name
		 *	@param		mixed			$value				What a field or a text key holds, raw
		 *
		 *	@return 	string|null
		 */
		public static function apply( array &$appData, string $name, mixed $value ): ?string {

			$format = self::registered( $appData )[$name] ?? null;

			if( $format === null )
				return null;

			$answer = call_user_func_array( $format['callback'], [ &$appData, $value ] );

			if( $answer === null || is_string( $answer ) === true )
				return $answer;

			if( isset( $appData[self::WARNED][$name] ) === false ) {
				$appData[self::WARNED][$name] = true;
				trigger_error( 'Format: formatter "'. $name. '" did not answer a string or null - the value is shown as it is', E_USER_WARNING );
			}

			return null;
		}

		/**
		 *	The words of the language of the page: decimal, thousands, currency,
		 *	percent, date, date-long, datetime, time, am, pm and months, the twelve
		 *	names. German for a language that begins with "de", American English for
		 *	every other, and a word the language has a text of its own for - not the
		 *	text of another language that the fills would fall back to, and not an
		 *	empty one - is that text
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function words( array &$appData ): array {

			$locale	= \Nino\Locales::getCurrentLocale( $appData );
			$base		= preg_match( '/^de(?:_|$)/', $locale ) === 1 ? 'de' : 'en';
			$words	= self::WORDS[$base] + [ 'months' => self::MONTHS[$base] ];

			foreach( array_keys( self::WORDS[$base] ) as $word )
				$words[$word] = self::_own( $appData, '/template/common/format/'. $word ) ?? $words[$word];

			foreach( self::MONTH_KEYS as $index => $month )
				$words['months'][$index] = self::_own( $appData, '/template/common/month/'. $month ) ?? $words['months'][$index];

			return $words;
		}

		/**
		 *	A number written with the separators of the language, the given number of
		 *	decimals, rounded
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		int|float	$number
		 *	@param		int				$decimals
		 *
		 *	@return 	string|null							null for a number that is not finite, or whose digits and decimals are more than the fifteen a double holds
		 */
		public static function amount( array &$appData, int|float $number, int $decimals ): ?string {

			// A double holds about fifteen digits: an amount whose decimals lie past them is not written
			if( is_finite( (float) $number ) === false || abs( (float) $number ) >= self::LIMIT / 10 ** max( 0, $decimals ) )
				return null;

			$canonical = number_format( (float) $number, max( 0, $decimals ), '.', '' );

			// Rounding may carry a value under the limit across it
			if( abs( (float) $canonical ) >= self::LIMIT )
				return null;

			return self::_group( self::words( $appData ), $canonical );
		}

		/**
		 *	A number: as many decimals as it has, up to six, and no zero at their end
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				An int, a finite float, or a canonical number string
		 *
		 *	@return 	string|null							null for a value that is no number
		 */
		public static function number( array &$appData, mixed $value ): ?string {

			$number = self::_number( $value );

			if( $number === null || abs( (float) $number ) >= self::LIMIT )
				return null;

			// A double holds about fifteen digits: those past them are noise, not decimals
			$decimals = is_int( $number ) === true ? 0 : max( 0, min( 6, 15 - strlen( (string) (int) abs( (float) $number ) ) ) );
			$canonical = number_format( (float) $number, $decimals, '.', '' );

			// Rounding may carry a value under the limit across it
			if( abs( (float) $canonical ) >= self::LIMIT )
				return null;

			if( str_contains( $canonical, '.' ) === true )
				$canonical = rtrim( rtrim( $canonical, '0' ), '.' );

			return self::_group( self::words( $appData ), $canonical );
		}

		/**
		 *	An amount of money: two decimals in the currency of '/nino/format/currency'
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				As number()
		 *
		 *	@return 	string|null
		 */
		public static function currency( array &$appData, mixed $value ): ?string {

			$number = self::_number( $value );

			// Two decimals of the fifteen digits a double holds: the cents of an amount beyond that are not its own
			if( $number === null || abs( (float) $number ) >= self::LIMIT / 100 )
				return null;

			$words		= self::words( $appData );
			$amount		= number_format( abs( (float) $number ), 2, '.', '' );
			$code			= is_string( $appData['/nino/format/currency'] ?? null ) === true ? $appData['/nino/format/currency'] : self::CURRENCY;
			$code			= preg_match( '/^[A-Z]{3}$/D', $code ) === 1 ? $code : self::CURRENCY;
			$symbol		= self::SYMBOLS[$code] ?? $code;
			$pattern	= $words['currency'];

			// A symbol of letters does not touch the digits it stands beside
			if( preg_match( '/^[A-Za-z]+$/D', $symbol ) === 1 )
				$pattern = str_replace( [ '{symbol}{amount}', '{amount}{symbol}' ], [ "{symbol}\u{00A0}{amount}", "{amount}\u{00A0}{symbol}" ], $pattern );

			// An amount that rounds to nothing has no sign
			$sign = $number < 0 && $amount !== '0.00' ? '-' : '';

			return $sign. strtr( $pattern, [ '{amount}' => self::_group( $words, $amount ), '{symbol}' => $symbol ] );
		}

		/**
		 *	A percent: the value is the percent number, 19 is "19 %"
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				As number()
		 *
		 *	@return 	string|null
		 */
		public static function percent( array &$appData, mixed $value ): ?string {

			$number = self::number( $appData, $value );

			if( $number === null )
				return null;

			$sign = str_starts_with( $number, '-' ) === true ? '-' : '';

			return $sign. strtr( self::words( $appData )['percent'], [ '{amount}' => ltrim( $number, '-' ) ] );
		}

		/**
		 *	A date, short. A date and time is written as its date
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				A string: Y-m-d, optionally with a time after it
		 *
		 *	@return 	string|null
		 */
		public static function date( array &$appData, mixed $value ): ?string {
			return self::_date( $appData, $value, 'date' );
		}

		/**
		 *	A date, long: with the name of the month
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				As date()
		 *
		 *	@return 	string|null
		 */
		public static function dateLong( array &$appData, mixed $value ): ?string {
			return self::_date( $appData, $value, 'date-long' );
		}

		/**
		 *	A date and a time; a date with no time is written as a date, with no
		 *	midnight made up
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				As date()
		 *
		 *	@return 	string|null
		 */
		public static function datetime( array &$appData, mixed $value ): ?string {
			return self::_date( $appData, $value, 'datetime' );
		}

		/**
		 *	A time of day, from a date and time or a time alone
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value				A string: Y-m-d H:i, Y-m-dTH:i, with seconds or not, or H:i
		 *
		 *	@return 	string|null							null for a date with no time
		 */
		public static function time( array &$appData, mixed $value ): ?string {
			return self::_date( $appData, $value, 'time' );
		}

		/**
		 *	The text a language has of its own for a word - not the text of another
		 *	language that the fills fall back to, and not one that is empty
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key
		 *
		 *	@return 	string|null							The text as the characters it stands for, null where there is none
		 */
		private static function _own( array &$appData, string $key ): ?string {

			$text = \Nino\Html::resolveTextfill( $appData, $key );

			if( $text === null || trim( $text ) === '' || \Nino\Html::fillOrigin( $appData, $key ) !== null )
				return null;

			return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		/**
		 *	The number a value is, or null: an int, a finite float, or a string that
		 *	is a canonical number (see NUMBER). A bool, '12,50', ' 12.5', '1e5', NAN,
		 *	an array are none
		 *
		 *	@param		mixed			$value
		 *
		 *	@return 	int|float|null
		 */
		private static function _number( mixed $value ): int|float|null {

			if( is_int( $value ) === true )
				return $value;

			if( is_float( $value ) === true )
				return is_finite( $value ) === true ? $value : null;

			if( is_string( $value ) === true && preg_match( self::NUMBER, $value ) === 1 )
				return str_contains( $value, '.' ) === true ? (float) $value : (int) $value;

			return null;
		}

		/**
		 *	A number as number_format() writes it with a point and no grouping,
		 *	written with the separators of the language. The sign of a zero is
		 *	dropped
		 *
		 *	@param		array			$words
		 *	@param		string		$canonical		'-1234.50'
		 *
		 *	@return 	string
		 */
		private static function _group( array $words, string $canonical ): string {

			$sign			= str_starts_with( $canonical, '-' ) === true ? '-' : '';
			$parts		= explode( '.', ltrim( $canonical, '-' ), 2 );
			$groups		= [];

			for( $end = strlen( $parts[0] ); $end > 0; $end -= 3 )
				array_unshift( $groups, substr( $parts[0], max( 0, $end - 3 ), min( 3, $end ) ) );

			// A number that is nothing has no sign
			if( trim( $parts[0]. ( $parts[1] ?? '' ), '0' ) === '' )
				$sign = '';

			return $sign. implode( $words['thousands'], $groups ). ( isset( $parts[1] ) === true ? $words['decimal']. $parts[1] : '' );
		}

		/**
		 *	A date and time read from a string of one of the forms of FORMS, as it
		 *	stands - a wall-clock time in UTC, no zone is converted. Read only when
		 *	writing it again gives the very string (php rolls 2026-02-30 over to the
		 *	second of March), and for a year from 1 to 9999
		 *
		 *	@param		mixed			$value
		 *
		 *	@return 	array|null							[ the time, false|true|'time' - what the form holds ]
		 */
		private static function _read( mixed $value ): ?array {

			// What a date is made of, before php is asked: createFromFormat() throws at a NUL
			if( is_string( $value ) === false || preg_match( '/^[0-9T :-]{4,19}$/D', $value ) !== 1 )
				return null;

			$utc = new \DateTimeZone( 'UTC' );

			foreach( self::FORMS as $form => $holds ) {

				$read = \DateTimeImmutable::createFromFormat( '!'. $form, $value, $utc );

				if( $read !== false && $read->format( $form ) === $value && (int) $read->format( 'Y' ) >= 1 )
					return [ $read, $holds ];
			}

			return null;
		}

		/**
		 *	A date or a time by the pattern of the language
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$value
		 *	@param		string		$pattern			date, date-long, datetime or time
		 *
		 *	@return 	string|null
		 */
		private static function _date( array &$appData, mixed $value, string $pattern ): ?string {

			$read = self::_read( $value );

			if( $read === null )
				return null;

			[ $moment, $holds ] = $read;

			// A time alone has no date, a date alone no time, and a date and time with none is a date
			if( ( $holds === 'time' && $pattern !== 'time' ) || ( $holds === false && $pattern === 'time' ) )
				return null;

			if( $holds === false && $pattern === 'datetime' )
				$pattern = 'date';

			$words	= self::words( $appData );
			$hour		= (int) $moment->format( 'G' );

			return strtr( $words[$pattern], [
				'{day}'				=> $moment->format( 'j' ),
				'{day2}'			=> $moment->format( 'd' ),
				'{month}'			=> $moment->format( 'n' ),
				'{month2}'		=> $moment->format( 'm' ),
				'{monthname}'	=> $words['months'][ (int) $moment->format( 'n' ) - 1 ],
				'{year}'			=> $moment->format( 'Y' ),
				'{hour}'			=> (string) $hour,
				'{hour2}'			=> $moment->format( 'H' ),
				'{hour12}'		=> (string) ( $hour % 12 === 0 ? 12 : $hour % 12 ),
				'{minute2}'		=> $moment->format( 'i' ),
				'{ampm}'			=> $hour < 12 ? $words['am'] : $words['pm'],
			] );
		}
	}
}
