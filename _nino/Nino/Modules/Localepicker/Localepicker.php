<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Localepicker				see _nino/Nino/Modules/Modules.php for the
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
				'ul'	=> '<ul aria-label="[[/module/localepicker/menu/title]]">[[li]]</ul>',
				'li'	=> '<li><a href="[[href]]" lang="[[lang]]" hreflang="[[lang]]">[[name]]</a></li>',
				'li-current'	=> '<li><span aria-current="true" lang="[[lang]]">[[name]]</span></li>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'localepicker', [ self::class, 'doShortcode' ], false );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	The same locale switch the kernel answers, under this module's own
		 *	query key - see \Nino\Locales::switchFromQuery(), which this is the
		 *	second caller of. It used to be a verbatim copy of that method,
		 *	comments and all, differing in the key alone
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			&$request			Current request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {

			\Nino\Locales::switchFromQuery( $appData, $request, '/_nino/localepicker/current' );
		}

		/**
		 *	The languages of the site as data, for what draws the choice in a markup of its own: one entry for
		 *	each available language, in the order the site lists them. [localepicker] draws its list from this
		 *	function.
		 *
		 *	The values are raw - text, not markup: the caller escapes what it writes, and a name is never read as
		 *	a call or a fill, whatever an editor typed into it. The address that switches to a language is the
		 *	one this module answers: ?/_nino/localepicker/current=<locale>, a query of the page that is shown.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										A list of [ 'locale' => 'de_DE', 'lang' => the tag of it, 'de-DE', 'name' => the language in
		 *																	its own tongue, as the text /_nino/locale/<locale>/name has it (decoded; the locale where
		 *																	the site has no name for it), 'href' => the address that switches
		 *																	to it, 'current' => whether it is the language of the page ]
		 */
		public static function entries( array &$appData ): array {

			$current	= \Nino\Locales::getCurrentLocale( $appData );
			$entries	= [];

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {

				$name = \Nino\Html::resolveTextfill( $appData, '/_nino/locale/'. $locale. '/name' );

				$entries[] = [
					'locale'	=> $locale,
					// A language tag is hyphenated - en-US - where the locale is en_US
					'lang'		=> str_replace( '_', '-', $locale ),
					'name'		=> $name === null || $name === '' ? $locale : html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					'href'		=> '?/_nino/localepicker/current='. $locale,
					'current'	=> $current === $locale,
				];
			}

			return $entries;
		}

		/**
		 *	Replace shortcode - a leaf, which answers finished html
		 *
		 *	A bare list with no class of its own: every language is a plain link - an <a>
		 *	with lang and hreflang and the language's own name for its text - and the
		 *	current one a <span aria-current="true">. The list is named by the title of
		 *	the menu; the look of it is the part of a feature (the footer of Basis draws
		 *	its own from entries())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$callback	= $args['callback'] ?? '';

			// $actLi stays empty when the current locale isn't one of the
			// available ones (eg. a config.php whose /nino/locales/available
			// doesn't contain the default) - reading it undefined below is a
			// warning, and the error handler turns that into a 500
			$actLi					= '';
			$htmlLi 				= '';

			foreach( self::entries( $appData ) as $entry ) {

				// What the entry says is text, written escaped; a bracket in a name is no call, since what is
				// filled in comes last and nobody reads it again
				$values = array_map( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ), [ $entry['href'], $entry['lang'], $entry['name'] ] );

				if( $entry['current'] === true )
					$actLi = str_replace( [ '[[href]]', '[[lang]]', '[[name]]' ], $values, self::$_tpl['li-current'] );
				else
					$htmlLi .= str_replace( [ '[[href]]', '[[lang]]', '[[name]]' ], $values, self::$_tpl['li'] );
			}

			// The words of the fragment - the title of the list - are fills of the site: put in here, as the text they
			// are, since what a shortcode answers is read by nobody. The list is put in after them, so that what an
			// editor typed as the name of a language is never read for a fill
			$fill = static function( string $fragment ) use ( &$appData ): string {
				return (string) preg_replace_callback( '/\[\[(\/[^\[\]]+)\]\]/', static function( array $found ) use ( &$appData ): string {
					return \Nino\Html::resolveTextfill( $appData, $found[1] ) ?? $found[0];
				}, $fragment );
			};

			[ $before, $after ] = explode( '[[li]]', self::$_tpl['ul'], 2 );

			$content = $fill( $before ). $actLi. $htmlLi. $fill( $after );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $content );

			return $content;
		}
	}

}
