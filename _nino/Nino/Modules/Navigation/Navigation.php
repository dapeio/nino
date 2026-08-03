<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Navigation				see Nino.php's Modules section for the
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
	 *	Navigation					A quick & dirty nav renderer
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Navigation {

		public static
			$html = [
				'li'					=> '<li><a href="[[uri]]"[[attributes]]>[[title]]</a></li>',
				'nav-burger'	=> '<div class="sc-nav-wrap sc-nav-fullscreen sc-nav-burger [[class]]" id="[[id]]"><label><input type="checkbox"><div class="sc-nav-bg"></div><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><rect y="3" width="24" height="3"/><rect y="10" width="24" height="3"/><rect y="17" width="24" height="3"/></svg><div class="sc-nav-content">[[content]]</div></label></div>',
				'nav-regular'	=> '<div class="sc-nav-wrap sc-nav-fullscreen sc-nav-regular [[class]]" id="[[id]]"><div class="sc-nav-content">[[content]]</div></div>',
				'ul'					=> '<ul>[[content]]</ul>',
				'div'					=> '<div>[[content]]</div>',
			];

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'navigation', [ self::class, 'doShortcode' ] );
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

			$content	= $args['content'] ?? '';
			$callback	= $args['callback'] ?? '';
			$id				= $args['id'] ?? '';
			$class		= $args['class'] ?? '';
			$html 		= '';

			// Render list elements
			$lis	= '';

			if( $content === '' )
				return '';

			$lines = explode( PHP_EOL, $content );

			foreach( $lines as $line ) {

				if( $line === '' )
					continue;

				if( strpos( $line, ':' ) === false ) {
					$html .= str_replace( '[[content]]', $line, self::$html['div'] );
					continue;
				}

				$element		= explode( ':', $line );
				$uri				= \Nino\Http::getRequest( $appData )['/nino/http/request']['uri'] ?? '/';
				$attributes = $element[2] ?? '';
				$element[0]	= trim( $element[0] );

				$attributes	.= ( $uri === $element[0] ) ? ' class="active"' : '';
				$lis				.= str_replace( [ '[[uri]]', '[[attributes]]', '[[title]]' ], [ $element[0]	, $attributes, $element[1] ], self::$html['li'] );
			}
			$html .= str_replace( '[[content]]', $lis, self::$html['ul'] );


			$template = ( in_array( 'burger', $args ) === true ) ? 'burger' : 'regular';
			$result = str_replace( [ '[[content]]', '[[id]]', '[[class]]' ], [ $html, $id, $class ], self::$html['nav-'. $template] );

			if( $callback !== '' )
				\Nino\Callbacks::doCallbacks( $appData, $callback, $result );

			return $result;
		}
	}

}
