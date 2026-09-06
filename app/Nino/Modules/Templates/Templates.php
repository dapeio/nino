<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Templates						The Template Builder module: section-first composition of the
 *											project's page-*.tpl files, edited in the workbench's Templates
 *											panel (Admin/Admin.php). The visible unit is a complete
 *											<section>, never an arbitrary DOM node; unknown page-frame
 *											source stays byte-for-byte locked while sections are inserted,
 *											reordered or edited. Optional - a delivery that drops this
 *											directory keeps a working site and a workbench without it.
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	class Templates {

		/**
		 *	Module initiating: the workbench page carries the Builder's
		 *	sandboxed previews, and their inlined fonts need a data: font
		 *	source the kernel's policy does not grant - added on the
		 *	workbench's GET route alone (see callbackResponse())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_admin', [ self::class, 'callbackResponse' ] );
		}

		/**
		 *	The module's workbench panel - see \Nino\Admin\Panels
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Templates\Admin::class ];
		}

		/**
		 *	Widen the workbench page's Content-Security-Policy by the one
		 *	source the section previews need
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {
			$policy = (string) ( $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '' );
			$request['/nino/http/response']['header']['Content-Security-Policy'] = self::_allowPreviewDataFonts( $policy );
		}

		/**
		 *	The workbench page's policy is inherited by the Template Builder's
		 *	sandboxed srcdoc frames. Allow their inlined preview fonts without
		 *	widening Nino's global CSP by more than that one source
		 *
		 *	@param		string		$policy				The Content-Security-Policy header as it stands
		 *
		 *	@return 	string
		 */
		private static function _allowPreviewDataFonts( string $policy ): string {

			$directives = preg_split( '~\s*;\s*~', trim( $policy, '; ' ), -1, PREG_SPLIT_NO_EMPTY );
			if( is_array( $directives ) === false )
				$directives = [];

			$fontDirective = false;
			foreach( $directives as &$directive ) {
				if( preg_match( '~^font-src(?:\s|$)~i', $directive ) !== 1 )
					continue;

				$fontDirective = true;
				if( preg_match( '~(?:^|\s)data:(?:\s|$)~i', $directive ) !== 1 )
					$directive .= ' data:';
			}
			unset( $directive );

			if( $fontDirective === false )
				$directives[] = "font-src 'self' data:";

			return implode( '; ', $directives );
		}

	}
}
