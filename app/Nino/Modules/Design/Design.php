<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Design							The appearance module: a Theme as the complete baseline, then
 *											Design (the generated colour and size tokens), Header and
 *											Footer as independently editable choices. All four are edited
 *											in the workbench's Design panel (Admin/Admin.php) and, before
 *											a project exists, in the setup wizard's own steps. This class
 *											is what both share: where the generated stylesheet lands,
 *											where the settings live and how the stylesheet joins the
 *											bundle. Optional - a delivery that drops this directory keeps
 *											a working site and a wizard without Design controls.
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	class Design {

		// Where the generated stylesheet lands. A project file rather than a
		// tool file: it is part of what gets served, and a backup has to carry
		// it. Never edited by hand - every Design save rewrites it whole.
		public const string STYLESHEET = '/assets/style.design.css';

		// The settings themselves live beside the routes in config.php, so the
		// values stay editable after the install and a Restore brings them back
		public const string SETTINGS_KEY = '/nino/design/settings';

		private const string BUNDLE_KEY = '/.cache/style.css';

		/**
		 *	The module's workbench panel - see \Nino\Admin\Panels
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Design\Admin::class ];
		}

		/**
		 *	The appearance catalogue - themes, header and footer units - is
		 *	the wizard's, under _admin/install/library: the Design panel reads
		 *	the same units after setup, so a Theme or a frame can be changed
		 *	later. A delivery that dropped the wizard keeps Design (the
		 *	generated tokens) and loses the catalogue, which the panel says
		 *	rather than fails on
		 *
		 *	@return 	string									Absolute path, existing or not
		 */
		public static function library(): string {
			return \Nino\Admin\Admin::LIBRARY;
		}

		/**
		 *	Whether the wizard's classes are at hand, loading them on demand:
		 *	the frame previews and the example page borrow Install's Themes
		 *	class rather than rendering a unit a second way. The workbench
		 *	only loads _admin/install/Install.php while a project is not set
		 *	up, so the Design panel asks here first
		 *
		 *	@return 	bool
		 */
		public static function setup(): bool {

			if( class_exists( '\Nino\Install\Themes', false ) === true )
				return true;

			$file = \Nino\Admin\Admin::DIR. '/install/Install.php';

			if( is_file( $file ) === false )
				return false;

			require_once $file;

			return class_exists( '\Nino\Install\Themes', false ) === true;
		}

		/**
		 *	The settings currently in force, normalized. A project that has
		 *	never opened the Design panel still gets a complete, valid set rather than
		 *	nothing to render.
		 */
		public static function settings( array &$appData ): array {
			return \Nino\Modules\Design\Tokens::normalize( is_array( $appData[self::SETTINGS_KEY] ?? null ) ? $appData[self::SETTINGS_KEY] : [] );
		}

		/**
		 *	Generate the stylesheet, put it in the bundle, and persist the
		 *	settings that produced it. One operation: a stylesheet nothing
		 *	references and settings with no stylesheet are both broken states.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$settings			Already-normalized design settings
		 *
		 *	@return 	bool
		 */
		public static function write( array &$appData, array $settings ): bool {

			if( \Nino\Filesystem::putFileContent( $appData, self::STYLESHEET, \Nino\Modules\Design\Tokens::css( $settings ) ) === false )
				return false;

			$appData[self::SETTINGS_KEY] 		= $settings;
			$appData['/nino/html/assets']		= self::bundle( $appData );

			return \Nino\AppData::writeContentData( $appData, [ self::SETTINGS_KEY, '/nino/html/assets' ] ) !== false;
		}

		/**
		 *	Put the generated stylesheet directly behind the framework's own,
		 *	ahead of everything a theme, a header/footer variant or the project
		 *	itself contributes. Those all read the generated variables, so the
		 *	generated file is a foundation and belongs with the foundations.
		 *
		 *	Anchored on Nino.css rather than on a fixed index: the bundle grows
		 *	as a project adds stylesheets, and an index would silently start
		 *	pointing at one of them.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array									The complete '/nino/html/assets' map
		 */
		public static function bundle( array &$appData ): array {

			$assets	= is_array( $appData['/nino/html/assets'] ?? null ) ? $appData['/nino/html/assets'] : [];
			$files	= array_values( array_filter(
				array_map( 'strval', $assets[self::BUNDLE_KEY] ?? [] ),
				static fn( string $file ): bool => $file !== self::STYLESHEET
			) );

			$position = array_search( '/_nino/Nino.css', $files, true );

			// No framework stylesheet in the bundle at all (a hand-emptied
			// config): the generated file still has to come before whatever is
			// there, since everything else reads from it
			array_splice( $files, $position === false ? 0 : $position + 1, 0, [ self::STYLESHEET ] );

			$assets[self::BUNDLE_KEY] = $files;

			return $assets;
		}
	}
}
