<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\Search\Admin		The /_admin panel of the Search module - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Search {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						Optional modules
	 *	Search\Admin			"Search" panel: the one button that recreates every
	 *											Elements search index configured in config.php - see
	 *											docs/development.md, "Elements Search Index". Used to sit
	 *											inside the Config panel, which then had to know this
	 *											module exists; here it comes and goes with the module.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/search/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function actions(): array {
			return [ 'search/createindex' => [ self::class, 'apiCreateIndex' ] ];
		}

		public static function nav(): array {
			return [ 'search', '/_admin/nav/search', 30, 'system' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search-code-icon lucide-search-code"><path d="m13 13.5 2-2.5-2-2.5"/><path d="m21 21-4.3-4.3"/><path d="M9 8.5 7 11l2 2.5"/><circle cx="11" cy="11" r="8"/></svg>';
		}

		public static function panes(): array {
			return [ 'search-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The panel's own strings, one <locale>.php per interface language
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'search/createindex'	=> 'Rebuild Search Index',
				default	=> '',
			};
		}

		/**
		 *	Recreate every Elements search index configured in config.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreateIndex( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$result = \Nino\Modules\Search::createIndexes( $appData );

			if( $result['failed'] !== [] ) {
				\Nino\Http::fail( $request, 500, 'could not create search indexes for '. implode( ', ', $result['failed'] ) );
				return;
			}

			\Nino\Http::ok( $request, $result );
		}
	}

}
