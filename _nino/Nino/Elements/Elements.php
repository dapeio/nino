<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Elements				File-based, multilingual content (comparable to posts/nodes)
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Elements - file-based, multilingual content (comparable to posts/nodes)
	class Elements {

		// Get an element from file
		static public function getElement( array &$appData, string $uri, string $locale = '', mixed $return = false ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Add element type / element to cache
			self::_cacheElement( $appData, $uri, $locale );

			return $appData['./nino/elements/cache'][ $uri ][ $locale ] ?? $return;
		}

		// Get an element from file
		static public function queryElements( array &$appData, string $typeUri, array $query, string $locale = '', mixed $return = false ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Get element type data from file
			$typeData = \Nino\Elements::getElementFile( $appData, $typeUri );
			if( $typeData === false )
				return $return;

			unset( $typeData['model'] );

			// '*' means "any locale" - an element only needs data in ONE locale to
			// exist at all (eg. mid-translation, or a type with no global fields
			// at all, so nothing of it ever lives in the '*' bucket itself). Every
			// available locale has to be considered, not just '*' - treating '*' as
			// if it were a normal locale key here (as $typeData[$locale] with
			// $locale === '*') only ever looked at the '*' bucket against itself,
			// silently missing any element that only has locale-specific data.
			// Deliberately not array_keys($typeData) - besides '*' and one entry
			// per locale, a type file also has non-locale top-level keys (eg. its
			// own display "title", a plain string rather than a data bucket)
			$locales = ( $locale === '*' ) ? \Nino\Locales::getAvailableLocales( $appData ) : [ $locale ];

			$elementUris = array_keys( $typeData['*'] ?? [] );
			foreach( $locales as $l )
				$elementUris = array_merge( $elementUris, array_keys( $typeData[$l] ?? [] ) );
			$elementUris = array_diff( array_unique( $elementUris ), ['*'] );

			// Pre-compute the wildcard-trimmed value/length per query key once,
			// instead of once per element/locale below
			$queryClean = [];
			foreach( $query as $qKey => $kVal ) {
				$kVal							= (string) $kVal;
				$kValClean				= trim( $kVal, '%' );
				$queryClean[$qKey]	= [ $kVal, $kValClean, strlen( $kValClean ) ];
			}

			// Loop through elements
			$hits = [];
			foreach( $elementUris as $elementUri ) {

				// A hit if the query matches using ANY one of the locales being
				// searched (for a single real locale, $locales has just the one)
				$hit = true;
				foreach( $locales as $l ) {

					$elementData = array_merge( ( $typeData['*']['*'] ?? [] ), ( $typeData[$l]['*'] ?? [] ), ( $typeData['*'][$elementUri] ?? [] ), ( $typeData[$l][$elementUri] ?? [] ) );

					$hit = true;

					// Every query key has to match, not just the last one: $hit was
					// reset per key and only ever set on a match, so whatever the
					// final key decided was the result - query="cat=x&status=live"
					// filtered by status alone
					foreach( $queryClean as $qKey => [ $kVal, $kValClean, $kValLen ] )
						if( self::_matchesQueryValue( $elementData[$qKey] ?? null, $kVal, $kValClean, $kValLen ) === false ) {
							$hit = false;
							break;
						}

					if( $hit === true )
						break;
				}

				if( $hit === true )
					$hits[] = \Nino\Elements::getElement( $appData, $typeUri. '/'. $elementUri, $locale );

			}

			return $hits ?? $return;
		}

		// Whether one element value satisfies one query value, including the
		// '%foo%' / '%foo' / 'foo%' wildcard forms
		static private function _matchesQueryValue( mixed $value, string $kVal, string $kValClean, int $kValLen ): bool {

			if( is_scalar( $value ) === false )
				return false;

			$value = (string) $value;

			if( $value === $kValClean )
				return true;

			// An empty query value has no wildcard to read - $kVal[0] on '' is an
			// "uninitialized string offset" warning, and the error handler turns
			// that into a 500 for the whole page
			if( $kVal === '' )
				return false;

			$leading	= $kVal[0] === '%';
			$trailing	= $kVal[-1] === '%';

			// A bare '%' (or '%%') is "anything"
			if( $kValClean === '' )
				return $leading === true || $trailing === true;

			if( $leading === true && $trailing === true )
				return strpos( $value, $kValClean ) !== false;

			if( $leading === true )
				return substr( $value, 0 - $kValLen ) === $kValClean;

			if( $trailing === true )
				return substr( $value, 0, $kValLen ) === $kValClean;

			return false;
		}

		// Get the distinct values of one model field across a type's elements,
		// each with how many currently matching elements carry it. Built on
		// queryElements() - same locale resolution and query matching, no
		// second bucket-merge path that could drift from it over time.
		static public function queryElementValues( array &$appData, string $typeUri, string $key, array $query = [], string $locale = '', mixed $return = [] ): mixed {

			$model = self::getElementModel( $appData, $typeUri );
			if( isset( $model[$key] ) === false )
				return $return;

			$hits = self::queryElements( $appData, $typeUri, $query, $locale, [] );

			$counts = [];
			foreach( $hits as $element ) {
				$value = $element[$key] ?? null;
				if( is_scalar( $value ) === false || (string) $value === '' )
					continue;
				$value = (string) $value;
				$counts[$value] = ( $counts[$value] ?? 0 ) + 1;
			}

			// Declared options first, in model order - even with count 0, so a
			// project-wide "these are the categories" list can decide the shown
			// set instead of just whatever currently has a matching element.
			$options = $model[$key]['options'] ?? null;
			$declared = is_array( $options ) && array_is_list( $options ) ? $options : [];

			$result = [];
			foreach( $declared as $value ) {
				$value = (string) $value;
				$result[$value] = [ 'value' => $value, 'count' => $counts[$value] ?? 0 ];
				unset( $counts[$value] );
			}

			// Then any observed value that isn't a declared option (a free field).
			// The (string) cast is not redundant: php silently turns an
			// integer-like array key back into an int, so a numeric field value
			// ('2024' on a portfolio filtered by year) would otherwise leave here
			// as int and break every caller that expects the documented string -
			// starting with the shortcode's own strnatcasecmp() sort.
			foreach( $counts as $value => $count )
				$result[(string) $value] = [ 'value' => (string) $value, 'count' => $count ];

			return $result === [] ? $return : array_values( $result );
		}

		// Update an element
		static public function updateElement( array &$appData, string $uri, array $data, string $locale = '' ): mixed {

			$element = \Nino\Elements::getElement( $appData, $uri, $locale );

			if( $element === false )
				return ! trigger_error( 'Element \''. $uri. '\' does not exist.' );

			return self::_writeElementData( $appData, $uri, $data, $locale, true );
		}

		// Update an element
		static public function insertElement( array &$appData, string $uri, array $data, string $locale = '' ): mixed {

			if( \Nino\Elements::getElement( $appData, $uri, $locale ) !== false )
				return ! trigger_error( 'Element \''. $uri. '\' already exists.' );

			return self::_writeElementData( $appData, $uri, $data, $locale );
		}

		// Delete an element
		static public function deleteElement( array &$appData, string $uri, string $locale = '' ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Flush, reload and lock element file
			$elementUri 	= self::getElementUriFromUri( $uri );
			$typeUri			= self::getElementTypeFromUri( $uri );

			// Locked explicitly up front, distinct from mutate()'s own
			// internal lockFile() call, purely to tell "could not lock" (a
			// filesystem/permissions problem, worth an operator's attention)
			// apart from "type file missing" (a plain caller error) below -
			// re-locking the same path is a no-op per lockFile()'s own
			// docblock, so this doesn't change what mutate() does after it
			$typeFile = self::_typeFile( $typeUri );
			if( \Nino\Filesystem::lockFile( $appData, $typeFile ) === false )
				return ! trigger_error( 'Element type \''. $typeUri. '\' could not be locked for writing.' );

			// $outcome stays 'notfound' unless the callback below actually
			// runs and says otherwise - the lock is already confirmed above,
			// so reaching here with 'notfound' means the type file itself is
			// missing (the callback sees a non-array $state and aborts)
			$outcome = 'notfound';

			$success = \Nino\Filesystem::mutate( $appData, $typeFile, function( mixed $typeData, array &$appData ) use ( $elementUri, $locale, $typeUri, &$outcome ): mixed {

				if( is_array( $typeData ) === false )
					return null;

				// Unset locale data
				unset( $typeData[$locale][$elementUri] );
				if( empty( $typeData[$locale] ) === true )
					unset( $typeData[$locale] );

				// Delete all (*) - deliberately not iterating $typeData's own keys,
				// as a type file also has non-locale top-level keys (eg. its own
				// display "title", a plain string rather than a data bucket) that
				// would crash unset() on a string offset
				if( $locale === '*' )
					foreach( \Nino\Locales::getAvailableLocales( $appData ) as $l )
						unset( $typeData[$l][$elementUri] );

				// Delete last * (only if no other locale still references this element)
				$lastEntry = true;
				foreach( \Nino\Locales::getAvailableLocales( $appData ) as $l )
					if( isset( $typeData[$l][$elementUri] ) === true )
						$lastEntry = false;

				if( $lastEntry === true )
					unset( $typeData['*'][$elementUri] );

				unset( $appData['./nino/elements/cache'] );

				// Run callback
				if( \Nino\Callbacks::doCallbacks( $appData, '/nino/elements/delete'. $typeUri, $typeData ) === false ) {
					$outcome = 'veto';
					return null;
				}

				$outcome = 'success';
				return $typeData;
			}, false );

			if( $outcome === 'notfound' )
				return ! trigger_error( 'Element type \''. $typeUri. '\' does not exist.' );

			if( $outcome === 'veto' )
				return null;

			// $outcome only reflects the callback's own decision - mutate()
			// can still fail to actually persist it (disk full, permissions),
			// which used to be reported as a plain, silent success
			if( $success === false )
				return ! trigger_error( 'Element \''. $uri. '\' could not be written.' );

			// The veto-capable delete callback above runs while the type mutation
			// is still pending. This second hook is deliberately notification-only:
			// modules that maintain derived data must not see a delete which the
			// filesystem never actually persisted.
			$change = [
				'operation'		=> 'delete',
				'type'				=> $typeUri,
				'uri'					=> $uri,
				'previousUri'	=> null,
				'locale'			=> $locale,
			];
			\Nino\Callbacks::doCallbacks( $appData, '/nino/elements/committed', $change );

			return true;
		}



		private static function getElementFile( array &$appData, string $typeUri ): array|false {

			$typeUri = '/'. trim( $typeUri, '/' );
			$typeFile = self::_typeFile( $typeUri );

			// Check filecache
			$appData['./nino/elements/cache'][$typeUri] = \Nino\Filesystem::getFileContent( $appData, $typeFile, false );

			if( $appData['./nino/elements/cache'][$typeUri] === false )
				trigger_error( 'Invalid element php file \''. $typeFile. '\'' );

			return $appData['./nino/elements/cache'][$typeUri];
		}

		// Insert an element type
		static public function insertElementType( array &$appData, string $typeUri, array $model, bool $autoincrement = false ): mixed {

			$typeUri = '/'. trim( $typeUri, '/' );
			$typeFile = self::_typeFile( $typeUri );

			// Check if element exists
			if( \Nino\Filesystem::getFileContent( $appData, $typeFile, '' ) !== '' )
				return ! trigger_error( 'Element type \''. $typeUri. '\' already exists.' );

			$typeData = [
				'model'			=> [],
				'*'					=> [
					'*'				=> [],
				],
			];

			// See AUTOINCREMENT_PAD: the key holds the next number due, and a
			// brand new type starts at the first one
			if( $autoincrement === true )
				$typeData['autoincrement'] = 1;

			// Fill model
			foreach( $model AS $key => $data ) {

				if( isset( $data['type'] ) === false || in_array( $data['type'], [ 'string', 'integer', 'array', 'boolean', 'double', 'date', 'datetime', 'image', 'element' ] ) === false )
					continue;

				// An 'element' field is a reference to another element, and the
				// type it may point at is part of the field, not of the value -
				// without it there is nothing to offer a choice from, so such a
				// field is not a field at all
				if( $data['type'] === 'element' && trim( (string) ( $data['elementType'] ?? '' ) ) === '' )
					continue;

				// How many elements one reference may hold. Presence of an int is
				// the switch, the same shape a numbered type's 'autoincrement'
				// uses: 0 is "as many as you like", a positive number caps the
				// list, and an absent key is the single reference this field has
				// always been - so every model written before this keeps its exact
				// meaning. Anything else is a mistake rather than a smaller cap,
				// and dropping the key leaves the field single rather than
				// silently unlimited
				if( $data['type'] === 'element' && ( is_int( $data['multiple'] ?? null ) === false || $data['multiple'] < 0 ) )
					unset( $data['multiple'] );

				if( isset( $data['default'] ) === true ) {
					if( $data['type'] === 'double' && gettype( $data['default'] ) === 'integer' )
						$data['default'] = (float) $data['default'];
					if( gettype( $data['default'] ) !== self::_expectedGettype( $data ) )
						continue;
				}

				$typeData['model'][$key] = $data;

				if( isset( $data['default'] ) === true )
					$typeData['*']['*'][$key] = $data['default'];
			}

			if( \Nino\Filesystem::putFileContent( $appData, $typeFile, $typeData ) === false )
				return ! trigger_error( 'Element type \''. $typeUri. '\' could not be written.' );

			return true;
		}

		// Canonical virtual filename for a type URI. Type URIs intentionally
		// include a leading slash; trimming it here prevents alias cache/lock
		// keys such as /elements//articles.php for the same physical file.
		private static function _typeFile( string $typeUri ): string {
			return '/elements/'. trim( $typeUri, '/' ). '.php';
		}


		// Returns an element type model
		static public function getElementModel( array &$appData, string $typeUri ): array {
			$typeData = self::getElementFile( $appData, $typeUri );
			return ( $typeData === false || is_array( $typeData['model'] ?? null ) === false )
				? []
				: $typeData['model'];
		}

		// Get element type from element uri
		static public function getElementTypeFromUri( string $uri ): string {
			$pos = strpos( substr( $uri, 1 ), '/' );
			if( $pos === false ) {
				trigger_error( 'Element uri \''. $uri. '\' has no type separator (expected \'/type/slug\').' );
				return '';
			}
			return substr( $uri, 0, $pos + 1 );
		}

		// Get element part from element uri
		static public function getElementUriFromUri( string $uri ): string {
			$pos = strpos( substr( $uri, 1 ), '/' );
			if( $pos === false ) {
				trigger_error( 'Element uri \''. $uri. '\' has no type separator (expected \'/type/slug\').' );
				return '';
			}
			return substr( $uri, $pos + 2 );
		}

		// Sequential element uris - the type file's own AUTO_INCREMENT.
		//
		// A type whose entries have no natural name (an image in a gallery, a
		// price row) is better off numbered than made to invent a slug per
		// entry. Such a type carries one extra top-level key, next to its
		// 'title' - 'autoincrement' => <the next number to hand out> - and
		// inserting into it with an empty slug ('/gallery/', see
		// _writeElementData()) allocates that number instead.
		//
		// The counter is stored rather than derived from the existing entries,
		// which is what makes it behave like a database's: deleting the newest
		// entry does not hand its number to the next one. A uri is a public
		// address - it ends up in links, sitemaps and bookmarks - so silently
		// pointing an old one at a different element is worse than a gap in the
		// numbering. It is also read and written inside the same lock as the
		// element itself, so two simultaneous inserts cannot be given the same
		// number.
		//
		// Presence of an int is the switch. Anything else (absent, false, a
		// string) means this type names its elements itself.
		public const int AUTOINCREMENT_PAD = 5;

		// Whether this type numbers its elements, and the next number due
		static public function getAutoincrement( array &$appData, string $typeUri ): ?int {
			$typeData = self::getElementFile( $appData, $typeUri );
			return ( $typeData === false ) ? null : self::readAutoincrement( $typeData );
		}

		// The same, off an already-read type file - the form of it that can be
		// called from inside a mutate() callback without a second read
		static public function readAutoincrement( array $typeData ): ?int {
			return is_int( $typeData['autoincrement'] ?? null ) ? $typeData['autoincrement'] : null;
		}

		// The first number that cannot collide with an element this type
		// already has. Used when switching the counter on, and again on every
		// allocation as a floor under the stored value - a hand-written or
		// imported '/gallery/00042' would otherwise be overwritten by the
		// counter catching up to it.
		static public function autoincrementSeed( array $typeData ): int {

			$highest = 0;

			foreach( $typeData as $bucketName => $bucketData ) {

				if( $bucketName === 'model' || is_array( $bucketData ) === false )
					continue;

				// Numeric-string keys stay strings in php only while they are
				// not canonical decimals ('00042' does, '42' becomes int 42),
				// so both spellings have to be read the same way here
				foreach( array_keys( $bucketData ) as $elementUri )
					if( ctype_digit( (string) $elementUri ) === true )
						$highest = max( $highest, (int) $elementUri );
			}

			return $highest + 1;
		}

		// One allocated number as the slug it is stored under. Zero-padded so
		// the entries of a numbered type sort in the order they were made,
		// wherever they are compared as the strings they are - element uris are
		// array keys in the type file and text in a list. Past the padding
		// width a number simply gets longer.
		static public function autoincrementUri( int $number ): string {
			return str_pad( (string) $number, self::AUTOINCREMENT_PAD, '0', STR_PAD_LEFT );
		}

		/**
		 *	Whether an element reference holds a list rather than a single uri.
		 *
		 *	Presence of an int under 'multiple' is the switch, the same shape a
		 *	numbered type's 'autoincrement' uses: 0 means "as many as you like",
		 *	a positive number caps the list. An absent key is the single
		 *	reference the field has always been, which is what makes every model
		 *	written before this keep its exact meaning - and what lets the
		 *	setting stay optional in both element forms.
		 *
		 *	@param		array 		$field				One model field definition
		 *
		 *	@return 	bool										True for a list-valued element field
		 */
		static public function isMultiElement( array $field ): bool {
			return ( $field['type'] ?? '' ) === 'element' && is_int( $field['multiple'] ?? null ) === true;
		}

		// The PHP gettype() a model field's declared type is expected to hold
		// as - 'date'/'datetime' values are plain ISO strings (php has no
		// native date type), an 'image' field stores its uploaded file's
		// generated filename and an 'element' field the referenced element's
		// full uri, both also strings; everything else matches its type name
		// directly. A *multi* element field is the one case the type name alone
		// cannot answer: it holds the same uris as a php list, so the whole
		// field rather than its type decides
		static private function _expectedGettype( array $field ): string {

			if( self::isMultiElement( $field ) === true )
				return 'array';

			$type = (string) ( $field['type'] ?? '' );

			return in_array( $type, [ 'date', 'datetime', 'image', 'element' ], true ) ? 'string' : $type;
		}

		// Write element data into file content
		static private function _writeElementData( array &$appData, string $uri, array $data, string $locale, bool $update = false ): mixed {

			// Verify locale
			if( $locale !== '*' && ( $locale === '' || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) )
				$locale = \Nino\Locales::getCurrentLocale( $appData );

			// Flush, reload and lock element file
			$typeUri			= self::getElementTypeFromUri( $uri );

			// See deleteElement()'s identical pre-lock: distinguishes "could
			// not lock" from "type file missing" below, rather than folding
			// both into the same 'notfound' outcome and message
			$typeFile = self::_typeFile( $typeUri );
			if( \Nino\Filesystem::lockFile( $appData, $typeFile ) === false )
				return ! trigger_error( 'Element type \''. $typeUri. '\' could not be locked for writing.' );

			// $outcome stays 'notfound' unless the callback below actually
			// runs and says otherwise - the lock is already confirmed above,
			// so reaching here with 'notfound' means the type file itself is
			// missing (the callback sees a non-array $state and aborts)
			$outcome 		= 'notfound';
			$resultData	= null;

			// The uri actually written, which for a numbered type is only known
			// once the mutation below has allocated it - so a failure message
			// names the element rather than the '/type/' it was asked for
			$writtenUri = $uri;

			$success = \Nino\Filesystem::mutate( $appData, $typeFile, function( mixed $typeData, array &$appData ) use ( $uri, $data, $locale, $typeUri, $update, &$outcome, &$resultData, &$writtenUri ): mixed {

				if( is_array( $typeData ) === false )
					return null;

				// A numbered type assigns the uri itself: insert into '/gallery/'
				// - the type with an empty slug - and the next number is
				// allocated here, inside the lock this mutation already holds,
				// so two simultaneous inserts cannot be handed the same one.
				// Everything below then runs on the allocated uri, including the
				// field callbacks, which therefore see the same '.uri' they
				// would for a named element.
				$autoincrement = self::readAutoincrement( $typeData );

				if( $update === false && $autoincrement !== null && self::getElementUriFromUri( $uri ) === '' ) {
					$number 										= max( $autoincrement, self::autoincrementSeed( $typeData ) );
					$uri 												= $typeUri. '/'. self::autoincrementUri( $number );
					$writtenUri 								= $uri;
					$typeData['autoincrement'] 	= $number + 1;
				}

				// Run callbacks
				$data['.uri'] 		= $uri;
				$data['.locale']	= $locale;

				foreach( $typeData['model'] AS $key => $field )
					if( isset( $field['callbacks'] ) === true && isset( $data[$key] ) )
						foreach( $field['callbacks'] AS $callbackUri )
							if( \Nino\Callbacks::doCallbacks( $appData, $callbackUri, $data ) === false ) {
								trigger_error( 'Callback \''. $callbackUri. '\' returns an error.' );
								$outcome = 'error';
								return null;
							}

				// Check new element data - a field callback receives it by
				// reference and may change or remove these values.
				if( is_string( $data['.uri'] ?? null ) === false || is_string( $data['.locale'] ?? null ) === false ) {
					$outcome = 'veto';
					return null;
				}

				$newTypeUri = self::getElementTypeFromUri( $data['.uri'] );
				$newElementUri = self::getElementUriFromUri( $data['.uri'] );
				if(
					$newTypeUri !== $typeUri
					|| $newElementUri === ''
					|| ( $data['.locale'] !== '*' && \Nino\Locales::verifyLocale( $appData, $data['.locale'] ) === false )
				) {
					trigger_error( 'Callback returned an invalid element URI or locale for \''. $uri. '\'.' );
					$outcome = 'error';
					return null;
				}

				// Keep the set of fields this update is actually meant to write.
				// updateElement() also serves deliberately-partial updates (most
				// notably the Images panel's immediate image upload). The previous wildcard
				// merge filled every omitted key from whichever locale happened to
				// occur first in the type file, then wrote all of those values into
				// the requested locale below - uploading an English image could
				// therefore copy German title/description values into English.
				//
				// Merge the requested locale only for the returned complete element,
				// but write/validate just the keys the caller (or a field callback)
				// supplied. An insert still validates every required model field.
				$writeKeys = array_fill_keys( array_keys( $data ), true );
				if( $update === true ) {
					$existing = \Nino\Elements::getElement( $appData, $uri, $locale, [] );
					if( is_array( $existing ) === true )
						$data = $data + $existing;
				}

				// Render/write new element. When a field callback changes the URI,
				// first copy every stored locale/global field to the destination;
				// the partial update below then overwrites only the supplied keys.
				// Without that copy, omitted title/description/image fields vanished
				// when the old URI was removed at the end of the mutation.
				$elementUri 		= $newElementUri;
				$oldElementUri = self::getElementUriFromUri( $uri );
				$renameBuckets = [];

				if( $uri !== $data['.uri'] ) {
					foreach( $typeData as $bucketName => $bucketData ) {
						if( $bucketName === 'model' || is_array( $bucketData ) === false )
							continue;

						if( array_key_exists( $elementUri, $bucketData ) === true ) {
							trigger_error( 'Element \''. $data['.uri']. '\' already exists.' );
							$outcome = 'error';
							return null;
						}

						if( array_key_exists( $oldElementUri, $bucketData ) === true )
							$renameBuckets[] = $bucketName;
					}

					foreach( $renameBuckets as $bucketName )
						$typeData[$bucketName][$elementUri] = $typeData[$bucketName][$oldElementUri];
				}

				foreach( $typeData['model'] AS $key => $field ) {

					if( $update === true && isset( $writeKeys[$key] ) === false )
						continue;

					// Required - a plain empty() would also reject a legitimate 0/false
					// value on a boolean/integer/double field, so presence alone is
					// enough for those; string/array still need an actual non-empty
					// value. An image is exempt: both editing tools upload its file
					// separately, only once the element exists and has a uri to
					// attach the upload to, so a required image would reject the
					// very insert that has to happen first - making the element
					// impossible to create at all. Neither tool writes the flag onto
					// an image field (see _admin/Admin.php's cleanModel()); this
					// keeps a hand-edited model that does out of that dead end. A
					// caller that does pass an image filename is unaffected either way
					if( isset( $field['required'] ) === true && $field['required'] === true && $field['type'] !== 'image' ) {
						$isEmpty = match( true ) {
							isset( $data[$key] ) === false 																								=> true,
							in_array( $field['type'], [ 'boolean', 'integer', 'double' ], true ) === true => false,
							is_array( $data[$key] ) === true 																							=> count( $data[$key] ) === 0,
							default 																																				=> $data[$key] === '',
						};
						if( $isEmpty === true ) {
							trigger_error( 'Missing required element key \''. $key. '\' in \''. $uri. '\'.' );
							$outcome = 'error';
							return null;
						}
					}

					// Check key. A whole-number 'double' round-trips through JSON as
					// an integer; coerce it before the default comparison as well, so
					// posting 5 can genuinely reset an inherited default of 5.0.
					if( isset( $data[$key] ) === false )
						continue;

					if( $field['type'] === 'double' && gettype( $data[$key] ) === 'integer' )
						$data[$key] = (float) $data[$key];

					$targetArray = ( isset( $typeData['model'][$key]['locale'] ) === true && $typeData['model'][$key]['locale'] === true ) ? $data['.locale'] : '*';

					// A model default is inherited from ['*']['*']; writing the same
					// value is unnecessary. On update, however, an older explicit
					// override must be removed or the reset appears to save but the
					// stale value wins again on the next read.
					if( isset( $field['default'] ) === true && isset( $data[$key] ) === true && $field['default'] === $data[$key] ) {
						unset( $typeData[$targetArray][$elementUri][$key] );
						if( $targetArray !== '*' && ( $typeData[$targetArray][$elementUri] ?? null ) === [] ) {
							unset( $typeData[$targetArray][$elementUri] );
							if( ( $typeData[$targetArray] ?? null ) === [] )
								unset( $typeData[$targetArray] );
						}
						continue;
					}

					if( gettype( $data[$key] ) !== self::_expectedGettype( $field ) ) {
						trigger_error( 'Wrong var type \''. $key. '\' in \''. $uri. '\'. \''. $field['type']. '\' required, \''. gettype( $data[$key] ). '\' given.' );
						$outcome = 'error';
						return null;
					}

					// Whitelist
					if( isset( $field['whitelist'] ) === true && in_array( $data[$key], $field['whitelist'] ) === false ) {
						trigger_error( 'Element value \''. $key. '\' is not whitelisted.' );
						$outcome = 'error';
						return null;
					}

					// Blacklist
					if( isset( $field['blacklist'] ) === true && in_array( $data[$key], $field['blacklist'] ) === true ) {
						trigger_error( 'Element value \''. $key. '\' is blacklisted.' );
						$outcome = 'error';
						return null;
					}

					// An element reference stores the referenced element's full uri
					// ('/type/slug' - what getElement() takes), and may only point
					// into the type its field declares. Checked as a plain string
					// against the model, deliberately not against the referenced
					// file: this runs inside a lock on *this* type's file, and a
					// reference whose target is deleted later stays readable either
					// way (both element forms show it as missing rather than
					// dropping it). An empty value is "no reference" - 'required'
					// above is what makes one mandatory
					if( $field['type'] === 'element' ) {

						$referencePrefix = '/'. trim( (string) ( $field['elementType'] ?? '' ), '/' ). '/';

						// A list reference holds exactly the uris a single one
						// holds, so each entry answers the same question. The cap
						// is checked here rather than left to the form that drew
						// the list: an api caller is every bit as able to post one
						// entry too many, and a model that promises "at most three"
						// is not a hint the ui happens to render
						if( self::isMultiElement( $field ) === true ) {

							$limit = (int) $field['multiple'];
							$seen 	= [];

							foreach( $data[$key] as $reference ) {

								if( is_string( $reference ) === false || $reference === '' || str_starts_with( $reference, $referencePrefix ) === false ) {
									trigger_error( 'Element reference \''. $key. '\' in \''. $uri. '\' must point into \''. ( $field['elementType'] ?? '' ). '\', got \''. ( is_string( $reference ) === true ? $reference : gettype( $reference ) ). '\'.' );
									$outcome = 'error';
									return null;
								}

								// The list is an ordered set. The same element twice
								// is one choice stored twice - nothing reading it
								// could tell the copies apart, and the up/down
								// controls would move two identical rows
								if( isset( $seen[$reference] ) === true ) {
									trigger_error( 'Element reference \''. $key. '\' in \''. $uri. '\' lists \''. $reference. '\' twice.' );
									$outcome = 'error';
									return null;
								}

								$seen[$reference] = true;
							}

							if( $limit > 0 && count( $data[$key] ) > $limit ) {
								trigger_error( 'Element reference \''. $key. '\' in \''. $uri. '\' holds '. count( $data[$key] ). ' entries, at most '. $limit. ' allowed.' );
								$outcome = 'error';
								return null;
							}

							// Stored as a list, never as the gapped or string-keyed
							// array a partial removal client-side or a json object
							// can arrive as - a template iterating it would other-
							// wise see the keys rather than the order
							$data[$key] = array_values( $data[$key] );

						} else if( $data[$key] !== '' && str_starts_with( $data[$key], $referencePrefix ) === false ) {
							trigger_error( 'Element reference \''. $key. '\' in \''. $uri. '\' must point into \''. ( $field['elementType'] ?? '' ). '\', got \''. $data[$key]. '\'.' );
							$outcome = 'error';
							return null;
						}
					}

					// Set value
					$typeData[$targetArray][$elementUri] = $typeData[$targetArray][$elementUri] ?? [];
					$typeData[$targetArray][$elementUri][$key] = $data[$key];
				}

				$typeData['*'][$elementUri] = $typeData['*'][$elementUri] ?? [];

				// Check uri change
				if( $uri !== $data['.uri'] ) {

					// Notification callbacks may inspect/replace their argument, but
					// cannot change the URI after its data has already been moved.
					$uriChangeData = $data;
					\Nino\Callbacks::doCallbacks( $appData, '/nino/elements'. $typeUri. '/update/uri', $uriChangeData );

					foreach( $renameBuckets as $bucketName )
						unset( $typeData[$bucketName][$oldElementUri] );
				}

				unset( $appData['./nino/elements/cache'] );

				// Run callback - lets a module react to (or veto, by returning
				// false) a save, same veto-capable shape as deleteElement()'s
				// own '/nino/elements/delete<typeUri>' callback below
				$callbackName = '/nino/elements'. $typeUri. ( $update === true ? '/update' : '/insert' );
				if( \Nino\Callbacks::doCallbacks( $appData, $callbackName, $typeData ) === false ) {
					$outcome = 'veto';
					return null;
				}

				$outcome 		= 'success';
				$resultData	= [ '.uri' => $data['.uri'], '.locale' => $data['.locale'] ];

				return $typeData;
			}, false );

			if( $outcome === 'notfound' )
				return ! trigger_error( 'Element type \''. $typeUri. '\' does not exist.' );

			if( $outcome === 'error' )
				return false;

			if( $outcome === 'veto' )
				return null;

			// Same reasoning as deleteElement(): $outcome === 'success' only
			// means the callback agreed to the write, not that mutate() was
			// able to persist it
			if( $success === false )
				return ! trigger_error( 'Element \''. $writtenUri. '\' could not be written.' );

			// Unlike the existing type-specific hook inside the mutation, this is
			// fired only after putFileContent() succeeded. It cannot veto a commit;
			// it is the safe point for optional modules to refresh derived files.
			$change = [
				'operation'		=> $update === true ? 'update' : 'insert',
				'type'				=> $typeUri,
				'uri'					=> $resultData['.uri'],
				'previousUri'	=> $uri !== $resultData['.uri'] ? $uri : null,
				'locale'			=> $resultData['.locale'],
			];
			\Nino\Callbacks::doCallbacks( $appData, '/nino/elements/committed', $change );

			return \Nino\Elements::getElement( $appData, $resultData['.uri'], $resultData['.locale'] );
		}

		// Load an element into app cache. Resolves '*' to the actual locale
		// it found data in (reference), so the caller can look up that same
		// cache slot afterwards.
		static private function _cacheElement( array &$appData, string $uri, string &$locale ): void {

			// Get uris
			$typeUri		= self::getElementTypeFromUri( $uri );
			$elementUri	= self::getElementUriFromUri( $uri );

			if( $locale !== '*' && isset( $appData['./nino/elements/cache'][$uri][$locale] ) === true )
				return;

			// Get element type data from file
			$typeData = \Nino\Elements::getElementFile( $appData, $typeUri );
			if( $typeData === false )
				return;

			// Catch native/fallback locale: find the first locale that actually
			// has data for this element, else leave $locale at '*' (global-only).
			// is_array() because a type file's top level also holds plain values
			// that are not data buckets - its 'title', and its 'autoincrement'
			// counter if it numbers its elements
			if( $locale === '*' )
				foreach( $typeData AS $typeLocale => $typeElement )
					if( $typeLocale !== 'model' && $typeLocale !== '*' && is_array( $typeElement ) === true && isset( $typeElement[$elementUri] ) === true ) {
						$locale = (string) $typeLocale;
						break;
					}

			if( isset( $typeData[$locale][$elementUri] ) === false && isset( $typeData['*'][$elementUri] ) === false )
				return;

			// Create element
			$defaults 				= [ '.locale' => $locale, '.uri' => $uri ];
			$localeDefaults		= $typeData[$locale]['*'] ?? [];
			$globalDefaults		= $typeData['*']['*'] ?? [];
			$localeData 			= $typeData[$locale][$elementUri] ?? [];
			$globalData				= $typeData['*'][$elementUri] ?? [];


			// Combine data
			$appData['./nino/elements/cache'][$uri] = $appData['./nino/elements/cache'][$uri] ?? [];
			$appData['./nino/elements/cache'][$uri][$locale] = $localeData + $globalData + $localeDefaults + $globalDefaults + $defaults;
		}

	}
}
