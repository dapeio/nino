<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Text					The [[key]] textfill layer the Text and Text Keys editors sit on top of
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Text - the [[key]] textfill layer the workbench's Text and Text Keys
	// editor both sit on top of: reads every key out of /text/global.php +
	// every /text/{locale}.php, batches a save into one lock/read/write per
	// file. Only holds what was byte-for-byte identical between the two
	// UIs - blacklist filtering, PERM- vs session-gated saves, shape
	// conversion stay in Admin.php/Admin.php.
	class Text {

		private const int MIN_MAXLENGTH 		= 150;
		private const int MAX_MAXLENGTH 		= 2000;
		private const int MAXLENGTH_BUFFER = 150;
		private const int HARD_MAXLENGTH 	= 20000;

		// Every known key across global.php + every locale file, with its
		// current value(s), whether it's global or per-locale, whether it
		// currently holds markup, a maxlength derived from its longest
		// current value, and whether it's blacklisted (see blacklist()).
		// $includeBlacklisted controls whether a blacklisted key is skipped
		// entirely or just flagged - the Text panel hides them, Text Keys
		// editor needs to see them to be able to un-blacklist one.
		public static function entries( array &$appData, bool $includeBlacklisted = true ): array {

			$global 	= \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );

			$localeData = [];
			foreach( $locales as $locale )
				$localeData[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$blacklist = self::blacklist( $appData );

			$bracketKeys = array_keys( $global );
			foreach( $localeData as $data )
				$bracketKeys = array_merge( $bracketKeys, array_keys( $data ) );
			$bracketKeys = array_unique( $bracketKeys );

			$entries = [];

			foreach( $bracketKeys as $bracketKey ) {

				$key 					= trim( $bracketKey, '[]' );
				$isBlacklisted = isset( $blacklist[$key] ) === true;

				if( $isBlacklisted === true && $includeBlacklisted === false )
					continue;

				$isGlobal = array_key_exists( $bracketKey, $global );
				$values 	= $isGlobal
					? [ '*' => $global[$bracketKey] ]
					: array_map( fn( array $data ) => $data[$bracketKey] ?? null, $localeData );

				$longest 	= 0;
				$html 		= false;

				foreach( $values as $value ) {
					if( $value === null )
						continue;
					$longest 	= max( $longest, strlen( $value ) );
					$html 		= $html || \Nino\Html::containsHtml( $value );
				}

				$entries[] = [
					'key' 				=> $key,
					'global' 			=> $isGlobal,
					'blacklisted' => $isBlacklisted,
					'html' 				=> $html,
					'maxlength' 	=> min( self::MAX_MAXLENGTH, max( self::MIN_MAXLENGTH, $longest + self::MAXLENGTH_BUFFER ) ),
					'values' 			=> $values,
				];
			}

			usort( $entries, fn( array $a, array $b ) => strcmp( $a['key'], $b['key'] ) );

			return $entries;
		}

		public static function entry( array &$appData, string $key, bool $includeBlacklisted = true ): array|null {

			foreach( self::entries( $appData, $includeBlacklisted ) as $entry )
				if( $entry['key'] === $key )
					return $entry;

			return null;
		}

		// Read the developer-maintained list of keys hidden from the workbench's
		// Text panel (technical values, not content - uris, colors,
		// typography, ...)
		public static function blacklist( array &$appData ): array {
			return array_flip( \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ) );
		}

		// Add or remove one key from /text/blacklist.php - _admin-only,
		// the Text panel only ever reads the list
		public static function setBlacklisted( array &$appData, string $key, bool $blacklisted ): void {

			\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $list ) use ( $key, $blacklisted ): ?array {

				$has = in_array( $key, $list, true );

				if( $blacklisted === true && $has === false )
					$list[] = $key;
				else if( $blacklisted === false && $has === true )
					$list = array_values( array_diff( $list, [ $key ] ) );
				else
					return null;

				return $list;
			} );
		}

		// Save several keys' values in one request, batched per target file
		// - a locale file gets one lock -> re-read -> write cycle no matter
		// how many of its keys changed, instead of one per key. That's not
		// just an optimization: two separate saves hitting the same file
		// concurrently would race (each reads the file before the other's
		// write lands, so one update gets silently lost) - batching removes
		// the race entirely by construction.
		//
		// A per-item failure (unknown/blacklisted key, invalid locale)
		// doesn't fail the whole call - it's reported per key in the
		// returned results so the other, valid items still get saved.
		// $includeBlacklisted has the same meaning as entries()'s own
		// parameter: whether a blacklisted key is a valid save target.
		public static function saveBatch( array &$appData, array $items, bool $includeBlacklisted ): array {

			$results 		= [];
			$fileChanges = [];
			$fileKeys 	= [];

			// entry() rebuilds the full entries() list (global.php + every
			// locale file) on every call - fine for a single lookup, not for
			// one per item in a batch. Built once here and indexed by key
			// instead.
			$entriesByKey = array_column( self::entries( $appData, $includeBlacklisted ), null, 'key' );

			foreach( $items as $item ) {

				$key 		= (string) ( $item['key'] ?? '' );
				$locale = (string) ( $item['locale'] ?? '' );
				$value 	= (string) ( $item['value'] ?? '' );

				$entry = $entriesByKey[$key] ?? null;

				if( $entry === null ) {
					$results[$key] = [ 'ok' => false, 'error' => 'unknown key' ];
					continue;
				}

				if( $entry['global'] === false && \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
					$results[$key] = [ 'ok' => false, 'error' => 'invalid locale' ];
					continue;
				}

				$value = self::sanitizeValue( $value, $entry['html'] === true );

				$file = ( $entry['global'] === true ) ? '/text/global.php' : '/text/'. $locale. '.php';

				$fileChanges[$file]['[['. $key. ']]'] = $value;
				$fileKeys[$file][] = $key;

				$results[$key] = [ 'ok' => true, 'value' => $value ];
			}

			foreach( $fileChanges as $file => $changes ) {

				$written = \Nino\Filesystem::mutate( $appData, $file, function( array $content ) use ( $changes ): array {
					return array_merge( $content, $changes );
				} );

				// mutate()'s result was previously discarded - every key
				// destined for this file was reported 'ok' even if the write
				// itself (lock failure, disk full) never happened
				if( $written === false )
					foreach( $fileKeys[$file] as $key )
						$results[$key] = [ 'ok' => false, 'error' => 'could not be written' ];
			}

			return $results;
		}

		// The one value-normalization path shared by regular batch saves and
		// _admin's JSON translation import. Keeping it here prevents import
		// from bypassing the hard length limit and HTML whitelist that the form
		// itself enforces.
		public static function sanitizeValue( string $value, bool $html ): string {

			$value = substr( $value, 0, self::HARD_MAXLENGTH );

			if( $html === true )
				return \Nino\Html::sanitizeHtml( $value );

			// strip_tags() answers "no markup of its own", which is the whole
			// requirement as long as a fill lands in text content. It does not
			// survive an attribute though, and Html::_renderFills() is a blind
			// str_replace over the finished document: the shipped templates put
			// plain-text fills inside href/src/alt/content/title (eg.
			// '<a href="[[/company/facebook]]">' in html-socialmedia.tpl), where
			// a stored value of  x" onmouseover="...  closes the attribute and
			// opens an event handler that fires for every visitor. The quotes go
			// in as entities, which render as themselves in text and as
			// themselves in an attribute, so nothing on screen changes.
			// Deliberately not htmlspecialchars(): that also encodes '&', and a
			// value re-saved from the editor would gain a round of escaping on
			// every pass. Neither entity below contains a quote, so this stays
			// idempotent.
			return str_replace( [ '"', "'" ], [ '&quot;', '&#039;' ], strip_tags( $value ) );
		}
	}
}
