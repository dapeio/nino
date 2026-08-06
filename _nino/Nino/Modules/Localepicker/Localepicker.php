<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Localepicker				see Nino.php's Modules section for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

		/**
		 *	Nino								A compact filesystembased php framework
		 *	Modules							All optional modules
		 *	Localepicker				A simple localepicker shortcode
		 *
		 *	@package						Dape/Nino
		 *	@author							David Perchermeier <mail@dape.io>
		 *	@link								https://github.com/dapeio/nino
		 */

	class Localepicker {

		private static
			$_tpl = [
				'ul'	=> '<div class="sc-localepicker-wrap"><label><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 16" width="32" height="32" color="currentColor" aria-hidden="true"><g><path fill="currentColor" fill-rule="evenodd" d="M.017 7.482a8 8 0 0 1 15.967 0q.025.115.01.225a8 8 0 1 1-15.99 0 .6.6 0 0 1 .013-.225m1.247.951a6.75 6.75 0 0 0 4.197 5.823 7 7 0 0 1-.416-.781c-.555-1.213-.92-2.787-1.018-4.518a29 29 0 0 1-2.763-.524m2.739-.742a28 28 0 0 1-2.7-.535A6.76 6.76 0 0 1 5.46 1.744q-.229.372-.416.781c-.623 1.363-1.006 3.18-1.042 5.166Zm1.286 1.413c.109 1.516.436 2.852.893 3.85.59 1.292 1.28 1.796 1.818 1.796s1.228-.504 1.818-1.795c.457-1 .784-2.335.893-3.85-1.803.17-3.619.17-5.422 0Zm5.46-1.26a27.5 27.5 0 0 1-5.498 0c.018-1.904.38-3.596.93-4.799C6.774 1.755 7.462 1.25 8 1.25s1.228.504 1.818 1.795c.55 1.203.913 2.895.931 4.8Zm1.224 1.113c-.099 1.731-.463 3.305-1.018 4.518a7 7 0 0 1-.416.781 6.75 6.75 0 0 0 4.197-5.823q-1.372.33-2.763.524m2.725-1.801q-1.341.336-2.7.535c-.037-1.985-.42-3.803-1.043-5.166a7 7 0 0 0-.416-.781 6.76 6.76 0 0 1 4.159 5.412" clip-rule="evenodd"></path></g></svg><input type="checkbox"><div><h6>[[/nino/locales/title]]:</h6><ul>[[li]]</ul></div><div class="sc-localepicker-bg"></div></label></div>',
				'li'	=> '<li class="[[active]]"><a[[link]]>[[/nino/locales/locale/[[locale]]]]</a></li>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'localepicker', [ self::class, 'doShortcode' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	Prepare a http request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$request			Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			if( isset( $request['/nino/http/request']['query']['/_nino/localepicker/current'] ) === false )
				return;

			$locale = \Nino\Locales::setCurrentLocale( $appData, $request['/nino/http/request']['query']['/_nino/localepicker/current'] );

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

		/**
		 *	Replace shortcode
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		misc			$args					Shortcode arguments
		 *
		 *	@return 	array | false 				AppData array or false
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$callback	= $args['callback'] ?? '';

			// $actLi stays empty when the current locale isn't one of the
			// available ones (eg. a config.php whose /nino/locales/available
			// doesn't contain the default) - reading it undefined below is a
			// warning, and the error handler turns that into a 500
			$actLi					= '';
			$htmlLi 				= '';
			$currentLocale	= \Nino\Locales::getCurrentLocale( $appData );

			foreach( \Nino\Locales::getAvailableLocales( $appData ) AS $locale )
				if( $currentLocale === $locale )
					$actLi = str_replace( [ '[[locale]]', '[[active]]', '[[link]]' ], [ $locale, ' active', '' ], self::$_tpl['li'] );
				else
					$htmlLi .= str_replace( [ '[[locale]]', '[[active]]', '[[link]]' ], [ $locale, '', ' href="?/_nino/localepicker/current='. $locale.'"' ], self::$_tpl['li'] );

			$content = str_replace( [ '[[li]]' ], [ $actLi. $htmlLi ], self::$_tpl['ul'] );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $content );

			return $content;
		}
	}

}
