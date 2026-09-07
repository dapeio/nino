<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Design\Admin	The workbench's Design panel - see docs/development.md
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Design {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Admin							Four appearance editors behind one panel: Theme establishes
	 *											a complete baseline, Design, Header and Footer stay
	 *											independently editable. The panel brings its own template
	 *											(templates/panel.tpl, rendered whole into the workbench's
	 *											pane), script and stylesheet; the colour maths lives in
	 *											Tokens, the catalogue in Appearance, the example page in
	 *											Preview. A developer surface - every action writes project
	 *											files, so every one of them asks for MANAGE_PERM.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/design/manage';

		public static function actions(): array {
			return [
				'appearance/read'		=> [ self::class, 'apiAppearanceRead' ],
				'theme/apply'				=> [ self::class, 'apiThemeApply' ],
				'frame/preview'			=> [ self::class, 'apiFramePreview' ],
				'frame/apply'				=> [ self::class, 'apiFrameApply' ],
				'design/read'				=> [ self::class, 'apiRead' ],
				'design/preview'		=> [ self::class, 'apiPreview' ],
				'design/save'				=> [ self::class, 'apiSave' ],
			];
		}

		public static function nav(): array {
			return [ 'design', '/_admin/nav/design', 5, 'structure' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		// The panel renders its own markup - four panes and the action bar -
		// rather than mount points a script fills (see Panels::panesHtml())
		public static function template(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/templates/panel' );
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-palette-icon lucide-palette"><path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/></svg>';
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/design.js' ), \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/style.css' ) ];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	The activity-log line for a mutating action, '' for a read - see
		 *	\Nino\Admin\Admin::_logAction()
		 *
		 *	@param		string		$action				The dispatched action name
		 *	@param		array			$data					The posted data
		 *
		 *	@return 	string
		 */
		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'theme/apply'		=> 'Applied theme "'. (string) ( $data['theme'] ?? '' ). '"',
				'frame/apply'		=> 'Applied '. (string) ( $data['kind'] ?? '' ). ' "'. (string) ( $data['frame'] ?? '' ). '"',
				'design/save'		=> 'Saved the design settings',
				default					=> '',
			};
		}

		/**
		 *	Same shape as every other panel's guard: each api method calls it
		 *	itself rather than trusting the dispatcher, so a direct call in a
		 *	test or a future dispatch change cannot walk past it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	bool
		 */
		public static function guard( array &$appData, array &$request ): bool {
			return \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM );
		}

		public static function postData(): array {
			return \Nino\Admin\Admin::postData();
		}

		/**
		 *	Which mode the preview is being asked for. It travels with the
		 *	settings rather than being toggled in the browser because the
		 *	example is delivered into a sandboxed iframe: that has an opaque
		 *	origin, so nothing out here can reach in and stamp the attribute
		 *	the generated stylesheet keys its dark block on.
		 *
		 *	@return 	string								'light' or 'dark'
		 */
		public static function mode(): string {
			return ( $_POST['mode'] ?? '' ) === 'dark' ? 'dark' : 'light';
		}

		/**
		 *	Theme, Header and Footer share the persistent appearance catalogue
		 *	under _admin/install/library with the wizard, but own their authenticated
		 *	API here. The wizard can therefore be deleted after setup as documented.
		 */
		public static function apiAppearanceRead( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			Appearance::apiList( $appData, $request );
		}

		public static function apiThemeApply( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			Appearance::apiApply( $appData, $request );
		}

		public static function apiFramePreview( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			// The preview is the wizard's rendering of a unit - a delivery
			// without the wizard has no catalogue to preview from
			if( \Nino\Modules\Design::setup() === false ) {
				\Nino\Http::fail( $request, 404, 'the setup library is not part of this delivery' );
				return;
			}

			\Nino\Install\Themes::apiFrame( $appData, $request );
		}

		public static function apiFrameApply( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			Appearance::apiFrameApply( $appData, $request );
		}

		/**
		 *	The stored settings plus the vocabulary a UI needs to render its
		 *	controls, so the frontend never carries a second copy of the lists.
		 */
		public static function apiRead( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$settings = \Nino\Modules\Design::settings( $appData );

			\Nino\Http::ok( $request, [
				'settings'	=> $settings,
				'choices'	=> Tokens::choices(),
				'groups'		=> Tokens::GROUPS,
				'surfaces'	=> Tokens::SURFACES,
				'raster'	=> Tokens::raster( $settings ),
				'brand'		=> Tokens::brand( $settings ),
				'example'	=> Preview::document( $appData, $settings, self::mode() ),
			] );
		}

		/**
		 *	A palette for settings that are not stored yet. The knobs need
		 *	immediate feedback, and the colour maths lives here - mirroring it
		 *	in JavaScript would be a second implementation to keep in step.
		 */
		public static function apiPreview( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$settings = Tokens::normalize( self::postData() );

			\Nino\Http::ok( $request, [
				'settings'	=> $settings,
				'raster'	=> Tokens::raster( $settings ),
				'brand'		=> Tokens::brand( $settings ),
				'example'	=> Preview::document( $appData, $settings, self::mode() ),
			] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$settings = Tokens::normalize( self::postData() );

			if( \Nino\Modules\Design::write( $appData, $settings ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the design stylesheet' );
				return;
			}

			\Nino\Http::ok( $request, [ 'settings' => $settings ] );
		}

	}
}
