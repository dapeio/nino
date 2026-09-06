<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Locales				Current-locale resolution, switching, and redirects
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Locales - current-locale resolution, switching, and redirects
	class Locales {

		public static function init( array &$appData ): void {

			// The project's configured native locale is the default for anyone
			// who hasn't picked one. AppData::$_initialInstance can only seed a
			// hardcoded placeholder there, since config.php isn't read until
			// AppData::init() - which runs immediately before this. Left at that
			// placeholder, every first visit rendered in whatever locale was
			// hardcoded rather than the project's own, and a project that does
			// not install that locale at all fell through to a text file that
			// does not exist: an unresolved [[key]] in place of every per-locale
			// fill on the page, the <html lang> and <title> included.
			//
			// A native locale that isn't among the available ones is a broken
			// config (Install\Setup won't produce one, _admin's raw Config
			// editor can) - the first available locale is still a far better
			// answer than a locale this project has no text for at all.
			//
			// Assigned directly rather than through setCurrentLocale(): a
			// default nobody chose has no business being written into the
			// visitor's session, where it would then outlive a later change of
			// the project's native locale.
			$available 	= $appData['/nino/locales/available'] ?? [];
			$default 		= (string) ( $appData['/nino/locales/native'] ?? '' );

			if( in_array( $default, $available, true ) === false )
				$default = (string) ( $available[0] ?? '' );

			if( $default !== '' )
				$appData['./nino/locales/current'] = $default;

			// A locale the visitor picked themselves still wins over that
			// default - setCurrentLocale() verifies it and falls back to the
			// value just set if it isn't available anymore
			$currentLocale = \Nino\Runtime::getSessionValue( $appData, './nino/locales/current' );

			if( is_string( $currentLocale ) === true )
				\Nino\Locales::setCurrentLocale( $appData, $currentLocale );

			// Registered rather than called directly out of \Nino\request(): a
			// route can declare its own 'statusCode' (eg. GET://_admin), and
			// Http::response() array_merge()s the route into the response array
			// *after* seeding it - calling this any earlier had the merge wipe
			// the 302 this sets right back to the route's own status, so the
			// Location header ended up in the response with a 200 and the
			// browser never followed it. Hooking '/nino/http/response' runs
			// this after that merge, same as Modules\Localepicker's own
			// locale-switch callback.
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}

		// Switch locale via the '/_nino/locales/current' query param and
		// redirect back to the current uri in the new locale
		public static function callbackResponse( array &$appData, array &$request ): void {

			// Catch locale change. parse_str() legitimately creates arrays for a
			// query such as current[]=de_DE; only scalar locale ids are valid.
			$requestedLocale = $request['/nino/http/request']['query']['/_nino/locales/current'] ?? null;
			if( is_string( $requestedLocale ) === false )
				return;

			$locale = \Nino\Locales::setCurrentLocale( $appData, $requestedLocale );

			// Keep the response's own 'locale' in sync with the switch just
			// made - \Nino\request() calls Locales::response() right after
			// Http::response()'s callbacks run, and that method reverts the
			// current locale straight back if it doesn't match this field.
			// Left at whatever Http::request() seeded it with (the locale
			// *before* this switch), that's exactly what would happen: the
			// switch above would never survive past this same request
			$request['/nino/http/response']['locale'] = $locale;

			$newUri = \Nino\Http::findRouteUri( $appData, $request['/nino/http/response']['uri'], $locale );

			// Redirect via the response array - a direct header() call would be
			// overwritten by Http::output()'s own http_response_code() pass
			if( $newUri !== null ) {
				$request['/nino/http/response']['statusCode'] 					= 302;
				$request['/nino/http/response']['header']['Location']	= str_replace( $request['/nino/http/request']['method']. ':/', '', $newUri );
			}
		}

		// Apply the locale of the resolved route, if it declares one (eg.
		// config.php's 'GET://rechtliches' => [ ..., 'locale' => 'de_DE' ]).
		//
		// Has to run after Http::response(): the route - and with it its
		// 'locale' - is only merged into the response array there, so
		// comparing response locale against current locale any earlier
		// only ever sees the seeded default, not the route's own choice.
		public static function response( array &$appData, array &$request ): void {

			$locale = $request['/nino/http/response']['locale'] ?? '';

			if( is_string( $locale ) === false || $locale === '' || $locale === \Nino\Locales::getCurrentLocale( $appData ) )
				return;

			// setCurrentLocale() also persists into the session, same as the
			// ?/_nino/locales/current switch above - visiting a locale-specific
			// page is a locale choice like any other
			$request['/nino/http/response']['locale'] = \Nino\Locales::setCurrentLocale( $appData, $locale );
		}

		public static function getCurrentLocale( array &$appData ): string {
			return $appData['./nino/locales/current'];
		}

		public static function getNativeLocale( array &$appData ): string {
			return $appData['/nino/locales/native'];
		}

		public static function getAvailableLocales( array &$appData ): array {
			return $appData['/nino/locales/available'];
		}

		public static function verifyLocale( array &$appData, string $locale ): bool {

			return in_array( $locale, \Nino\Locales::getAvailableLocales( $appData ) );
		}

		public static function setCurrentLocale( array &$appData, string $locale ): string {

			// Verify, if requested locale is available
			$appData['./nino/locales/current'] = ( \Nino\Locales::verifyLocale( $appData, $locale ) === true ) ? $locale : \Nino\Locales::getCurrentLocale( $appData );
			$locale = $appData['./nino/locales/current'];

			\Nino\Runtime::setSessionValue( $appData, './nino/locales/current', $locale );

			return $locale;
		}
	}
}
