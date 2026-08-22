<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Theme								/_theme - four post-install appearance editors.
 *											Theme establishes a complete baseline; Design,
 *											Header and Footer remain independently editable.
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */

namespace Nino\Theme {

	/**
	 *	Bootstrap, routes and the authenticated action map for /_theme.
	 *	Authentication is /_admin's, exactly as /_templates does it - the tool
	 *	writes project files, so it is a developer surface, not a public one.
	 */
	class Theme {

		// Where the generated stylesheet lands. A project file rather than a
		// tool file: it is part of what gets served, and a backup has to carry
		// it. Never edited by hand - every /_theme save rewrites it whole.
		public const string STYLESHEET = '/assets/style.design.css';

		// The settings themselves live beside the routes in config.php, so the
		// values stay editable after the install and a Restore brings them back
		public const string SETTINGS_KEY = '/nino/theme/design';

		private const string BUNDLE_KEY = '/.cache/style.css';

		public static function init( array &$appData ): void {

			// Tool-owned routes always win over stale persisted collisions.
			$appData['/nino/http/routes']['GET://_theme'] = [
				'uri' 			=> '/_theme',
				'body'			=> '[template /_theme/templates/page-index]',
				'statusCode'	=> 200,
			];
			$appData['/nino/http/routes']['POST://_theme'] = [ 'uri' => '/_theme' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://_theme', 	[ self::class, 'handleGet' ] );
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://_theme',	[ self::class, 'handlePost' ] );
		}

		public static function handleGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::isAuthed( $appData ) === false )
				$request['/nino/http/response']['body'] = '[template /_theme/templates/page-login]';
		}

		public static function handlePost( array &$appData, array &$request ): void {

			$action = (string) ( $_POST['action'] ?? '' );

			$actions = [
				'appearance/read'		=> [ self::class, 'apiAppearanceRead' ],
				'theme/apply'			=> [ self::class, 'apiThemeApply' ],
				'frame/preview'			=> [ self::class, 'apiFramePreview' ],
				'frame/apply'			=> [ self::class, 'apiFrameApply' ],
				'design/read'			=> [ self::class, 'apiRead' ],
				'design/preview'		=> [ self::class, 'apiPreview' ],
				'design/save'			=> [ self::class, 'apiSave' ],
			];

			if( isset( $actions[$action] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown action' );
				return;
			}

			call_user_func_array( $actions[$action], [ &$appData, &$request ] );
		}

		/**
		 *	Same shape as every other tool's guard: each api method calls it
		 *	itself rather than trusting the dispatcher above, so a direct call
		 *	in a test or a future dispatch change cannot walk past it.
		 */
		public static function guard( array &$appData, array &$request ): bool {

			if( $request['/nino/http/response']['statusCode'] !== 200 )
				return false;

			if( \Nino\Admin\Admin::isAuthed( $appData ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in' );
				return false;
			}

			return true;
		}

		public static function postData(): array {
			$data = json_decode( $_POST['data'] ?? '', true );
			return is_array( $data ) ? $data : [];
		}

		/**
		 *	Theme, Header and Footer share the persistent appearance catalogue
		 *	under /library with /_install, but own their authenticated API here.
		 *	The installer can therefore be deleted after setup as documented.
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

			Appearance::apiFrame( $appData, $request );
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

			\Nino\Http::ok( $request, [
				'settings'	=> self::settings( $appData ),
				'choices'	=> Design::choices(),
				'surfaces'	=> Design::SURFACES,
				'palette'	=> [
					'light' => Design::palette( self::settings( $appData ), 'light' ),
					'dark'	=> Design::palette( self::settings( $appData ), 'dark' ),
				],
				'raster'	=> Design::raster( self::settings( $appData ) ),
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

			$settings = Design::normalize( self::postData() );

			\Nino\Http::ok( $request, [
				'settings'	=> $settings,
				'palette'	=> [
					'light' => Design::palette( $settings, 'light' ),
					'dark'	=> Design::palette( $settings, 'dark' ),
				],
				'raster'	=> Design::raster( $settings ),
			] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( self::guard( $appData, $request ) === false )
				return;

			$settings = Design::normalize( self::postData() );

			if( self::write( $appData, $settings ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the design stylesheet' );
				return;
			}

			\Nino\Http::ok( $request, [ 'settings' => $settings ] );
		}

		/**
		 *	The settings currently in force, normalized. A project that has
		 *	never opened /_theme still gets a complete, valid set rather than
		 *	nothing to render.
		 */
		public static function settings( array &$appData ): array {
			return Design::normalize( is_array( $appData[self::SETTINGS_KEY] ?? null ) ? $appData[self::SETTINGS_KEY] : [] );
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

			if( \Nino\Filesystem::putFileContent( $appData, self::STYLESHEET, Design::css( $settings ) ) === false )
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

	/**
	 *	The selectable appearance units shared by /_install and /_theme. The
	 *	catalogue lives at project-root /library rather than below the removable
	 *	installer, so a completed project can change its Theme and frames later.
	 *
	 *	Theme application establishes the manifest's complete baseline. The two
	 *	frame endpoints deliberately do less: each replaces only one template,
	 *	one stylesheet and its persisted key.
	 */
	class Appearance {

		private const string LIBRARY = __DIR__. '/../library';
		private const string BUNDLE_KEY = '/.cache/style.css';

		private const array FRAMES = [
			'header' => [ 'template' => '/templates/theme.header.tpl', 'stylesheet' => '/assets/style.header.css' ],
			'footer' => [ 'template' => '/templates/theme.footer.tpl', 'stylesheet' => '/assets/style.footer.css' ],
		];

		public static function apiList( array &$appData, array &$request ): void {
			\Nino\Http::ok( $request, [
				'themes' 		=> self::_themes( $appData ),
				'activeTheme' => self::_currentTheme( $appData ),
				'frames' 		=> self::_frames(),
				'activeFrames'=> [
					'header' => (string) ( $appData['/nino/install/header'] ?? '' ),
					'footer' => (string) ( $appData['/nino/install/footer'] ?? '' ),
				],
			] );
		}

		public static function apiApply( array &$appData, array &$request ): void {

			$data = Theme::postData();
			$key = (string) ( $data['theme'] ?? '' );
			$manifest = self::_readManifest( $key );

			if( $manifest === null ) {
				\Nino\Http::fail( $request, 400, 'unknown theme: "'. $key. '"' );
				return;
			}

			$unitDir = self::LIBRARY. '/themes/'. $key;

			foreach( ( $manifest['files'] ?? [] ) as $file ) {

				$target = \Nino\Filesystem::path( $appData, '/'. $file );

				if( is_dir( $unitDir. '/'. $file ) === true )
					\Nino\Filesystem::copyDir( $unitDir. '/'. $file, $target );
				else if( is_file( $unitDir. '/'. $file ) === true )
					self::_copyFile( $unitDir. '/'. $file, $target );
			}

			$appData['/nino/install/theme'] = $key;
			$appData['/nino/html/assets'] = self::_bundleTheme( $appData, (string) $manifest['stylesheet'] );

			$applied = [];

			foreach( self::FRAMES as $kind => $paths ) {

				$frame = self::_frameKey( $kind, (string) ( $data[$kind] ?? '' ), (string) ( $manifest[$kind] ?? '' ) );

				if( $frame === null )
					continue;

				self::_applyFrame( $appData, $kind, $frame );
				$appData['/nino/install/'. $kind] = $frame;
				$applied[$kind] = $frame;
			}

			$appData['/nino/html/assets'] = self::_bundleFrames( $appData, array_keys( $applied ) );
			$keys = array_merge(
				[ '/nino/install/theme', '/nino/html/assets' ],
				array_map( static fn( string $kind ): string => '/nino/install/'. $kind, array_keys( $applied ) )
			);

			$design = Design::normalize(
				is_array( $data['design'] ?? null )
					? $data['design']
					: ( is_array( $manifest['design'] ?? null ) ? $manifest['design'] : [] )
			);

			if( Theme::write( $appData, $design ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the design stylesheet' );
				return;
			}

			\Nino\AppData::writeContentData( $appData, $keys );
			\Nino\Http::ok( $request, [ 'theme' => $key ] + $applied );
		}

		public static function apiFrame( array &$appData, array &$request ): void {

			$data = Theme::postData();
			$kind = (string) ( $data['kind'] ?? '' );

			if( isset( self::FRAMES[$kind] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown frame kind' );
				return;
			}

			$frame = self::_frameKey( $kind, (string) ( $data['frame'] ?? '' ), '' );

			if( $frame === null ) {
				\Nino\Http::fail( $request, 400, 'no frame units for "'. $kind. '"' );
				return;
			}

			$unitDir = self::LIBRARY. '/'. $kind. '/'. $frame;
			$source = @file_get_contents( $unitDir. '/template.tpl' );

			if( $source === false ) {
				\Nino\Http::fail( $request, 500, 'could not read the frame template' );
				return;
			}

			\Nino\Http::ok( $request, [
				'frame' => $frame,
				'html' => self::_framePreviewDocument(
					self::_framePreviewMarkup( $appData, $source ),
					self::_framePreviewCss( $appData, $data, $unitDir )
				),
			] );
		}

		public static function apiFrameApply( array &$appData, array &$request ): void {

			$data = Theme::postData();
			$kind = (string) ( $data['kind'] ?? '' );
			$frame = (string) ( $data['frame'] ?? '' );

			if( isset( self::FRAMES[$kind] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown frame kind' );
				return;
			}

			if( in_array( $frame, self::_frames()[$kind] ?? [], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown '. $kind. ' frame: "'. $frame. '"' );
				return;
			}

			self::_applyFrame( $appData, $kind, $frame );

			$appData['/nino/install/'. $kind] = $frame;
			$appData['/nino/html/assets'] = self::_bundleFrames( $appData, self::_activeFrameKinds( $appData ) );
			\Nino\AppData::writeContentData( $appData, [ '/nino/install/'. $kind, '/nino/html/assets' ] );

			\Nino\Http::ok( $request, [ 'kind' => $kind, 'frame' => $frame ] );
		}

		private static function _framePreviewMarkup( array &$appData, string $source ): string {

			// Prefer the installed include: /_theme edits a live project, so its
			// preview should use the navigation/legal/locale markup actually there.
			$source = (string) preg_replace_callback( '/\[template \/templates\/([a-z0-9.-]+)\]/', static function( array $include ) use ( &$appData ): string {
				$content = \Nino\Filesystem::getFileContent( $appData, '/templates/'. $include[1]. '.tpl', '' );
				return is_string( $content ) ? $content : '';
			}, $source );

			$fills = self::_framePreviewFills( $appData );
			uksort( $fills, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );
			$source = str_replace( array_keys( $fills ), array_values( $fills ), $source );
			$source = (string) preg_replace( '/\[\[\/webpage\[\[[^\]]*\]\]\/[a-z]+\]\]/', 'Home', $source );

			// Let active project modules render first. The deterministic fallback
			// below only handles a Navigation shortcode that was not registered.
			$source = \Nino\Html::renderHtml( $appData, $source );

			$items = '';
			foreach( [ 'Home', 'Services', 'About', 'Contact' ] as $index => $label )
				$items .= str_replace(
					[ '[[uri]]', '[[attributes]]', '[[title]]' ],
					[ '#', $index === 0 ? ' class="nino-is-active"' : '', $label ],
					\Nino\Modules\Navigation::$html['li']
				);

			$source = (string) preg_replace_callback( '/\[navigation\b([^\]]*)\](.*?)\[\/navigation\]|\[navigation\b([^\]]*)\]/s', static function( array $nav ) use ( $items ): string {

				$arguments = ( $nav[1] ?? '' ). ( $nav[3] ?? '' );
				$shell = \Nino\Modules\Navigation::$html[str_contains( $arguments, 'burger' ) === true ? 'nav-burger' : 'nav-regular'];
				$content = '';

				foreach( array_filter( array_map( 'trim', explode( "\n", $nav[2] ?? '' ) ) ) as $line )
					$content .= str_replace( '[[content]]', $line, \Nino\Modules\Navigation::$html['div'] );

				$content .= str_replace( '[[content]]', $items, \Nino\Modules\Navigation::$html['ul'] );

				return str_replace(
					[ '[[class]]', '[[id]]', '[[content]]' ],
					[ '', 'theme-preview-nav', $content ],
					$shell
				);
			}, $source );

			$source = (string) preg_replace( '/\[\[[^\]]*\]\]/', '', $source );

			return (string) preg_replace( '/\[\/?[a-z][a-z0-9]*\b[^\]]*\]/', '', $source );
		}

		private static function _framePreviewFills( array &$appData ): array {
			$blank = 'data:image/svg+xml;charset=utf-8,'
				. rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 40" role="img" aria-label="Logo">'
					. '<rect width="160" height="40" rx="6" fill="currentColor" opacity=".12"/>'
					. '<text x="80" y="25" text-anchor="middle" font-family="system-ui,sans-serif" font-size="13"'
					. ' font-weight="600" letter-spacing="2" fill="currentColor" opacity=".55">LOGO</text></svg>' );

			$fills = [
				'[[/nino/public]]/images/logo-invert.png' => $blank,
				'[[/nino/public]]/images/logo.png' => $blank,
				'[[/nino/dir]]' => '#',
				'[[/nino/public]]' => '#',
				'[[/date/year]]' => date( 'Y' ),
				'[[/company/name]]' => 'Your Company',
				'[[/company/description]]' => 'A concise description of the company and its work.',
				'[[/company/adress]]' => 'Street 1, 12345 City',
				'[[/company/country]]' => 'Germany',
				'[[/company/phone]]' => '+49 123 456789',
				'[[/company/email]]' => 'contact@example.com',
				'[[/global/adress]]' => 'Address',
				'[[/global/phone]]' => 'Phone',
				'[[/global/email]]' => 'Email',
				'[[/website/footer/title/followus]]' => 'Follow us',
				'[[/website/footer/title/getintouch]]' => 'Get in touch',
			];

			$textfiles = (string) ( $appData['/nino/locales/textfiles'] ?? '/text' );
			$locale = (string) ( $appData['/nino/locales/native'] ?? '' );

			foreach( [ $textfiles. '/global.php', $locale === '' ? '' : $textfiles. '/'. $locale. '.php' ] as $path ) {
				if( $path === '' )
					continue;

				$text = \Nino\Filesystem::getFileContent( $appData, $path, [] );
				if( is_array( $text ) === true )
					$fills = $text + $fills;
			}

			// Preview fills cross two syntax boundaries before they reach the iframe:
			// first the Navigation shortcode's quoted arguments, then the new HTML
			// document itself. Keep plain text plain across both. The
			// false double_encode flag preserves an intentional entity such as
			// &shy;, while quotes, raw ampersands and angle brackets can no longer
			// terminate an attribute or become preview markup of their own.
			foreach( $fills as $key => $value ) {
				if( is_string( $value ) === false ) {
					unset( $fills[$key] );
					continue;
				}

				// Existing projects can predate Nino's UTF-8-only browser editors.
				// Recover their common Windows-1252 text here instead of letting one
				// umlaut make json_encode() reject the complete preview response.
				if( function_exists( 'mb_check_encoding' ) === true && mb_check_encoding( $value, 'UTF-8' ) === false )
					$value = mb_convert_encoding( $value, 'UTF-8', 'Windows-1252' );

				// Textfills that deliberately contain one of Nino's allowed inline
				// HTML tags keep that markup. Everything else is text, including a
				// company name such as: Müller & Söhne "Studio".
				$fills[$key] = \Nino\Html::containsHtml( $value ) === true
					? $value
					: htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false );
			}

			return $fills;
		}

		private static function _framePreviewCss( array &$appData, array $data, string $unitDir ): string {

			$css = (string) @file_get_contents( dirname( __DIR__ ). '/_nino/Nino.css' );
			$settings = is_array( $data['design'] ?? null )
				? Design::normalize( $data['design'] )
				: Theme::settings( $appData );
			$css .= "\n". Design::css( $settings );

			$theme = (string) ( $data['theme'] ?? self::_currentTheme( $appData ) ?? '' );
			if( self::_readManifest( $theme ) !== null )
				foreach( glob( self::LIBRARY. '/themes/'. $theme. '/assets/*.css' ) ?: [] as $themeCss )
					$css .= "\n". (string) file_get_contents( $themeCss );

			if( is_file( $unitDir. '/style.css' ) === true )
				$css .= "\n". (string) file_get_contents( $unitDir. '/style.css' );

			$css = (string) preg_replace( '~@font-face\s*\{[^{}]*\}~i', '', $css );

			return str_replace(
				[ '[[/nino/dir]]', '[[/nino/public]]' ],
				[ (string) ( $appData['/nino/dir'] ?? '' ), (string) ( $appData['/nino/public'] ?? '' ) ],
				$css
			);
		}

		private static function _framePreviewDocument( string $markup, string $css ): string {
			return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
				. '<meta name="viewport" content="width=device-width, initial-scale=1">'
				. '<style>'. $css. '</style>'
				. '<style>:root{'
				. '--fontfamily-text:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
				. '--fontfamily-title:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
				. '--fontfamily-subtitle:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
				. '.theme-frame-filler{min-height:4rem}</style>'
				. '</head><body>'. $markup. '<div class="theme-frame-filler"></div></body></html>';
		}

		private static function _frameKey( string $kind, string $posted, string $declared ): ?string {
			$available = self::_frames()[$kind] ?? [];

			foreach( [ $posted, $declared ] as $candidate )
				if( $candidate !== '' && in_array( $candidate, $available, true ) === true )
					return $candidate;

			return $available[0] ?? null;
		}

		private static function _applyFrame( array &$appData, string $kind, string $frame ): void {
			$unitDir = self::LIBRARY. '/'. $kind. '/'. $frame;
			self::_copyFile( $unitDir. '/template.tpl', \Nino\Filesystem::path( $appData, self::FRAMES[$kind]['template'] ) );

			$stylesheet = \Nino\Filesystem::path( $appData, self::FRAMES[$kind]['stylesheet'] );
			if( is_file( $unitDir. '/style.css' ) === true )
				self::_copyFile( $unitDir. '/style.css', $stylesheet );
			else
				file_put_contents( $stylesheet, '' );
		}

		private static function _frames(): array {
			$frames = [];

			foreach( array_keys( self::FRAMES ) as $kind ) {
				$frames[$kind] = [];
				$directory = self::LIBRARY. '/'. $kind;

				if( is_dir( $directory ) === false )
					continue;

				foreach( scandir( $directory ) ?: [] as $entry ) {
					if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $entry ) !== 1 )
						continue;

					if( is_file( $directory. '/'. $entry. '/template.tpl' ) === true )
						$frames[$kind][] = $entry;
				}

				sort( $frames[$kind] );
			}

			return $frames;
		}

		private static function _activeFrameKinds( array &$appData ): array {
			$bundled = array_map( 'strval', $appData['/nino/html/assets'][self::BUNDLE_KEY] ?? [] );
			$active = [];

			foreach( self::FRAMES as $kind => $paths )
				if( (string) ( $appData['/nino/install/'. $kind] ?? '' ) !== '' || in_array( $paths['stylesheet'], $bundled, true ) === true )
					$active[] = $kind;

			return $active;
		}

		private static function _bundleFrames( array &$appData, array $kinds ): array {
			$assets = is_array( $appData['/nino/html/assets'] ?? null ) ? $appData['/nino/html/assets'] : [];
			$wanted = [];

			foreach( $kinds as $kind )
				if( isset( self::FRAMES[$kind] ) === true )
					$wanted[] = self::FRAMES[$kind]['stylesheet'];

			$files = array_values( array_filter(
				array_map( 'strval', $assets[self::BUNDLE_KEY] ?? [] ),
				static fn( string $file ): bool => in_array( $file, $wanted, true ) === false
			) );

			$position = false;
			foreach( self::_stylesheets() as $stylesheet ) {
				$found = array_search( $stylesheet, $files, true );
				if( $found !== false )
					$position = $found;
			}

			array_splice( $files, $position === false ? count( $files ) : $position + 1, 0, $wanted );
			$assets[self::BUNDLE_KEY] = $files;

			return $assets;
		}

		private static function _bundleTheme( array &$appData, string $stylesheet ): array {
			$assets = is_array( $appData['/nino/html/assets'] ?? null ) ? $appData['/nino/html/assets'] : [];
			$known = self::_stylesheets();
			$files = [];
			$placed = false;

			foreach( ( $assets[self::BUNDLE_KEY] ?? [] ) as $file ) {
				if( in_array( (string) $file, $known, true ) === false ) {
					$files[] = $file;
					continue;
				}

				if( $placed === false ) {
					$files[] = $stylesheet;
					$placed = true;
				}
			}

			if( $placed === false )
				$files[] = $stylesheet;

			$assets[self::BUNDLE_KEY] = $files;

			return $assets;
		}

		private static function _themes( array &$appData ): array {
			$themes = [];
			$directory = self::LIBRARY. '/themes';

			if( is_dir( $directory ) === false )
				return $themes;

			foreach( scandir( $directory ) ?: [] as $entry ) {
				$manifest = self::_readManifest( $entry );
				if( $manifest === null )
					continue;

				$preview = (string) ( $manifest['preview'] ?? '' );
				$themes[$entry] = [
					'label' => (string) ( $manifest['label'] ?? $entry ),
					'description' => (string) ( $manifest['description'] ?? '' ),
					'preview' => ( $preview !== '' && is_file( $directory. '/'. $entry. '/'. $preview ) === true )
						? ( (string) ( $appData['/nino/dir'] ?? '' ) ). '/library/themes/'. $entry. '/'. $preview
						: null,
					'header' => (string) ( $manifest['header'] ?? '' ),
					'footer' => (string) ( $manifest['footer'] ?? '' ),
					'design' => is_array( $manifest['design'] ?? null ) ? $manifest['design'] : null,
				];
			}

			ksort( $themes );

			return $themes;
		}

		private static function _currentTheme( array &$appData ): ?string {
			$key = (string) ( $appData['/nino/install/theme'] ?? '' );

			if( self::_readManifest( $key ) !== null )
				return $key;

			$bundled = array_map( 'strval', $appData['/nino/html/assets'][self::BUNDLE_KEY] ?? [] );
			foreach( self::_stylesheets() as $candidate => $stylesheet )
				if( in_array( $stylesheet, $bundled, true ) === true )
					return $candidate;

			return null;
		}

		private static function _stylesheets(): array {
			$paths = [];
			$directory = self::LIBRARY. '/themes';

			if( is_dir( $directory ) === false )
				return $paths;

			foreach( scandir( $directory ) ?: [] as $entry ) {
				$manifest = self::_readManifest( $entry );
				if( $manifest !== null )
					$paths[$entry] = (string) $manifest['stylesheet'];
			}

			return $paths;
		}

		private static function _readManifest( string $key ): ?array {
			if( preg_match( '/^[a-z0-9][a-z0-9-]*$/', $key ) !== 1 )
				return null;

			$path = self::LIBRARY. '/themes/'. $key. '/manifest.php';
			if( is_file( $path ) === false )
				return null;

			$manifest = include $path;

			return is_array( $manifest ) === true && ( $manifest['stylesheet'] ?? '' ) !== '' ? $manifest : null;
		}

		private static function _copyFile( string $from, string $to ): void {
			$content = @file_get_contents( $from );
			if( $content === false )
				return;

			if( is_file( $to ) === true )
				@unlink( $to );

			file_put_contents( $to, $content );
		}
	}

	/**
	 *	The generated design layer: a colour palette and a size raster, both
	 *	produced from a handful of settings rather than written by hand.
	 *
	 *	A Theme decides which surface a component sits on ("this section is
	 *	alt"); Design decides what that surface and everything readable on it
	 *	actually are, and guarantees each pair meets a contrast target. Colours
	 *	are solved in OKLCH, whose L is perceptual, so a hue can be moved onto a
	 *	target ratio without changing what colour it reads as - sRGB and HSL
	 *	lightness cannot do that (yellow at 50% is far lighter than blue at
	 *	50%). Every emitted pair is measured against the real WCAG formula
	 *	rather than assumed from a fixed lightness, so the guarantee is one the
	 *	tests can check and not a convention.
	 */
	class Design {


		// A Theme addresses these, never a ramp step. default/alt/dark/black
		// already exist as .nino-section--* and as Areas' frame choices;
		// origin is the brand surface and vibrant the secondary accent. The
		// three status surfaces are the same idea applied to meaning rather
		// than brand - an alert is a surface with text on it like any other,
		// so it comes out of the same machinery and carries the same promise.
		public const array SURFACES = [ 'default', 'alt', 'dark', 'black', 'origin', 'vibrant', 'success', 'warning', 'danger' ];

		// Measured off the colours Nino ships today rather than picked by eye,
		// so the generated status hues stay recognisably the project's own
		private const array STATUS = [
			'success'	=> [ 'hue' => 2.7938, 'chroma' => 0.081 ],	// 160.07 degrees
			'warning'	=> [ 'hue' => 1.4237, 'chroma' => 0.132 ],	//  81.57
			'danger'	=> [ 'hue' => 0.5010, 'chroma' => 0.178 ],	//  28.70
		];

		// Contrast picks the target for body text on a surface. UI parts
		// (borders, dividers) use TARGET_UI - WCAG 2.2 SC 1.4.11 asks 3:1 for
		// them, and holding those to the text target makes every border a
		// heavy line
		private const array TARGET_TEXT = [ 'soft' => 4.5, 'default' => 4.5, 'high' => 7.0 ];
		private const array TARGET_MUTED = [ 'soft' => 3.0, 'default' => 4.5, 'high' => 4.5 ];
		private const float TARGET_UI = 3.0;

		// Colors scales chroma only. Lightness stays with Contrast, or the two
		// knobs fight over the same axis and neither is predictable
		private const array CHROMA = [ 'clean' => 0.45, 'default' => 1.0, 'vibrant' => 1.5 ];

		/*	The size raster. Same split as the colours: Design publishes a
			fixed set of steps, a theme decides which step a component uses.
			Numbered rather than named because a size has no meaning of its
			own - --nino-space-3 is a step, --nino-alt is a surface.

			The baselines below are Nino.css's own scale, so the 'default'
			setting of every knob reproduces the framework exactly. Turning the
			design layer on must not move a project that has not asked for
			anything, or nobody can safely adopt it.	*/
		private const array BASE_TEXT 		= [ 1.0, 1.125, 1.25, 1.5, 1.8, 2.5 ];
		private const array BASE_SPACE 		= [ 0.5, 1.0, 2.0, 3.0, 5.0, 8.0 ];

		// Only the display end grows on a wide screen - keyed by step, since
		// the small steps are body copy and have nowhere to go
		private const array BASE_TEXT_WIDE = [ 4 => 1.6, 5 => 2.0, 6 => 3.0 ];

		// Mirrors Nino.css's own breakpoint. A second value here would split
		// the type scale from the layout it belongs to
		private const string BREAKPOINT = '768px';

		/*	Each knob is a pair of multipliers, applied to the first and last
			step and interpolated across the ones between.

			Volume is anchored at 1.0 for step 1 on purpose: body text is the
			one size that is already right, and a scale gets bigger by fanning
			out at the display end, not by pushing everything up. Spacing runs
			the other way - it bites hardest on the small steps, because the
			large ones are already section-sized and doubling them just adds
			scrolling.	*/
		private const array VOLUME 	= [ 'compact' => [ 1.0, 0.80 ], 'default' => [ 1.0, 1.0 ], 'generous' => [ 1.0, 1.35 ] ];
		private const array SPACING = [ 'tight' => [ 0.75, 0.85 ], 'default' => [ 1.0, 1.0 ], 'airy' => [ 1.5, 1.15 ] ];

		// Vertical rhythm belongs to Spacing, not Volume: it is the distance
		// between lines, not the size of them
		private const array LINE_HEIGHT = [ 'tight' => 1.45, 'default' => 1.5, 'airy' => 1.65 ];

		// Absolute rather than multiplied: a radius has a floor (0) and a
		// ceiling (half the box) that a multiplier would sail straight past
		private const array SHAPING = [
			'sharp' 	=> [ 0.05, 0.1, 0.2 ],
			'default'	=> [ 0.1, 0.2, 0.4 ],
			'round'		=> [ 0.5, 1.5, 2.5 ],
		];

		// How far a surface sits from the page ground. alt is a whisper, dark
		// and black are deliberate steps; they are absolute OKLCH lightnesses
		// rather than offsets so a brand hue cannot drag them somewhere unusable
		private const array SURFACE_L = [
			'light' => [ 'default' => 1.00, 'alt' => 0.965, 'dark' => 0.30, 'black' => 0.18 ],
			'dark'  => [ 'default' => 0.19, 'alt' => 0.235, 'dark' => 0.30, 'black' => 0.13 ],
		];

		/**
		 *	The vocabulary a UI renders its controls from, so the frontend never
		 *	carries a second copy of these lists.
		 *
		 *	@return 	array
		 */
		public static function choices(): array {
			return [
				'contrast'	=> array_keys( self::TARGET_TEXT ),
				'colors'		=> array_keys( self::CHROMA ),
				'volume'		=> array_keys( self::VOLUME ),
				'spacing'		=> array_keys( self::SPACING ),
				'shaping'		=> array_keys( self::SHAPING ),
			];
		}

		/**
		 *	Bring posted settings into a complete, valid shape. Everything here
		 *	arrives from a browser, so nothing is trusted: an unknown knob falls
		 *	back to its default and a colour that is not a hex is refused rather
		 *	than carried into the generator, where it would silently become the
		 *	fallback brand and look like the picker had ignored the choice.
		 *
		 *	@param		array			$input				Raw posted settings
		 *
		 *	@return 	array									primary, secondary, contrast, colors
		 */
		public static function normalize( array $input ): array {

			$primary = self::color( $input['primary'] ?? '', '#4faae8' );

			return [
				'primary'	=> $primary,
				// An empty secondary is a legitimate answer - it means "use the
				// brand for the vibrant surface too", not "fall back silently"
				'secondary'	=> self::color( $input['secondary'] ?? '', '' ),
				'contrast'	=> self::choice( $input['contrast'] ?? null, self::TARGET_TEXT ),
				'colors'		=> self::choice( $input['colors'] ?? null, self::CHROMA ),
				'volume'		=> self::choice( $input['volume'] ?? null, self::VOLUME ),
				'spacing'		=> self::choice( $input['spacing'] ?? null, self::SPACING ),
				'shaping'		=> self::choice( $input['shaping'] ?? null, self::SHAPING ),
			];
		}

		/**
		 *	One posted knob, or 'default'.
		 *
		 *	The type check is the point: these arrive as decoded json, so a
		 *	value can be an array or an object as easily as a string, and php
		 *	raises rather than returning false when one is used as an array
		 *	offset - isset() included. Reached from the wire through
		 *	/_theme's design/save and /_install's design/apply, so an
		 *	unchecked one is a 500 anybody can ask for.
		 *
		 *	@param		mixed			$value				Straight off the wire
		 *	@param		array			$allowed			The table this knob indexes
		 *
		 *	@return 	string
		 */
		private static function choice( mixed $value, array $allowed ): string {
			return ( is_string( $value ) === true && isset( $allowed[$value] ) === true ) ? $value : 'default';
		}

		/**
		 *	One posted colour, or the fallback. Three-digit hexes are expanded
		 *	so the rest of the class only ever sees one form.
		 */
		private static function color( mixed $value, string $fallback ): string {

			$value = strtolower( trim( (string) $value ) );
			$value = ltrim( $value, '#' );

			if( preg_match( '/^[0-9a-f]{3}$/', $value ) === 1 )
				$value = $value[0]. $value[0]. $value[1]. $value[1]. $value[2]. $value[2];

			return preg_match( '/^[0-9a-f]{6}$/', $value ) === 1 ? '#'. $value : $fallback;
		}

		/**
		 *	Build a complete palette for one mode.
		 *
		 *	@param		array			$settings			primary, secondary, contrast, colors
		 *	@param		string		$mode					'light' or 'dark'
		 *
		 *	@return 	array									surface => [ bg, on, onMuted, border, hover ]
		 */
		public static function palette( array $settings, string $mode = 'light' ): array {

			$mode 		= $mode === 'dark' ? 'dark' : 'light';
			$contrast	= isset( self::TARGET_TEXT[$settings['contrast'] ?? '' ] ) ? $settings['contrast'] : 'default';
			$chroma		= self::CHROMA[$settings['colors'] ?? ''] ?? self::CHROMA['default'];

			[ , $primaryC, $primaryH ] = self::oklch( (string) ( $settings['primary'] ?? '#4faae8' ) );
			$secondary = (string) ( $settings['secondary'] ?? '' );
			[ , $secondC, $secondH ] = self::oklch( $secondary === '' ? (string) ( $settings['primary'] ?? '#4faae8' ) : $secondary );

			// The neutral surfaces carry a trace of the brand hue rather than
			// being pure grey - a grey biased toward the accent reads as chosen,
			// a pure one as unconsidered
			$neutralC = min( 0.012, $primaryC * 0.08 );

			$palette = [];

			foreach( [ 'default', 'alt', 'dark', 'black' ] as $surface ) {
				$bg = self::hex( self::SURFACE_L[$mode][$surface], $neutralC, $primaryH );
				$palette[$surface] = self::pair( $bg, $primaryH, $neutralC, $contrast, $mode );
			}

			// origin, vibrant and the status surfaces are all "a hue as a
			// surface": hold hue and chroma, solve lightness until the text on
			// top clears the target. Status hues are fixed because red has to
			// stay red - the brand knobs must not be able to turn a danger
			// surface into something reassuring.
			$hued = [ 'origin' => [ $primaryC, $primaryH ], 'vibrant' => [ $secondC, $secondH ] ];

			foreach( self::STATUS as $surface => $status )
				$hued[$surface] = [ $status['chroma'], $status['hue'] ];

			foreach( $hued as $surface => [ $c, $h ] ) {
				$scaled	= $c * ( isset( self::STATUS[$surface] ) ? 1.0 : $chroma );
				$bg		= self::solveSurface( $h, $scaled, $mode, self::TARGET_TEXT[$contrast] );
				$palette[$surface] = self::pair( $bg, $h, $scaled, $contrast, $mode );
			}

			return $palette;
		}

		/**
		 *	Every value a Theme may assign for one surface, each measured.
		 *
		 *	@param		string		$bg						The surface itself
		 *	@param		float			$hue					Brand hue, for the tinted parts
		 *	@param		float			$chroma				Chroma of the tinted parts
		 *	@param		string		$contrast			Knob position
		 *
		 *	@return 	array
		 */
		private static function pair( string $bg, float $hue, float $chroma, string $contrast, string $mode ): array {

			// Primary text is ink, not the faintest thing that still passes.
			// Solving it to the target would put grey body copy on white at
			// exactly 4.5:1 - legal, and visibly washed out. Whichever of the
			// two inks wins takes it, and it wins by a wide margin.
			[ $onLight, $onDark ] = self::inks( $hue, $chroma );
			$on = self::contrast( $onDark, $bg ) >= self::contrast( $onLight, $bg ) ? $onDark : $onLight;

			// Everything below is measured against that decision, so a muted
			// value never crosses over to the other polarity
			$darker = $on === $onDark;

			return [
				'bg'			=> $bg,
				'on'			=> $on,
				// The muted tier is where the target is a target: it is meant to
				// sit as light as it may while staying legible. On a brand
				// surface there is barely room above the primary ink, so it is
				// capped there - a "muted" value that out-contrasts the text it
				// is subordinate to is not muted.
				'on-muted'	=> self::muted( $bg, $on, $hue, $chroma, self::TARGET_MUTED[$contrast], $darker ),
				// A link needs its own value on every surface. Reusing the brand
				// colour is exactly what breaks: that value is solved against the
				// page ground, and on a dark section it can be unreadable.
				// Carrying more chroma than the body text is what marks it as a
				// link beyond the underline.
				'link'		=> self::solve( $hue, max( $chroma, 0.10 ), $bg, self::TARGET_TEXT[$contrast], $darker ),
				'border'		=> self::solve( $hue, $chroma * 0.2, $bg, self::TARGET_UI, $darker ),
				// The focus ring is held to SC 1.4.11's 3:1 against the surface
				// it sits on, and carries full chroma so it never reads as just
				// another border - Nino.css currently drops the ring entirely on
				// form fields and replaces it with a 1.87:1 colour change
				'focus'		=> self::solve( $hue, max( $chroma, 0.12 ), $bg, self::TARGET_UI, $darker ),
				// Hover and active are nudges, not contrast promises - they only
				// have to be visible next to the resting surface, and active has
				// to be visibly past hover
				'hover'		=> self::nudge( $bg, self::luminance( $bg ) > 0.18, 1.0 ),
				'active'		=> self::nudge( $bg, self::luminance( $bg ) > 0.18, 2.1 ),
				// Disabled deliberately fails the text target: it is the one
				// state that must not look actionable. Held at 2.2:1 - readable
				// enough to identify, clearly not offered.
				'disabled'	=> self::solve( $hue, $chroma * 0.15, $bg, 2.2, $darker ),
				// Shadows are why a light-mode design collapses in dark mode:
				// black at 12% over a dark surface is invisible, so the dark
				// palette casts denser instead of reusing the light value
				'shadow'		=> $mode === 'dark' ? 'rgb(0 0 0 / 55%)' : 'rgb(15 23 42 / 12%)',
			];
		}

		/**
		 *	The two inks every surface chooses between. Both the surface solver
		 *	and the pair builder read them here, so a surface is never solved
		 *	against one white and then painted with a slightly different one -
		 *	that gap put a brand button at 4.39:1 against a 4.5:1 target.
		 *
		 *	@param		float			$hue					Brand hue, for the barely-tinted dark ink
		 *	@param		float			$chroma				Brand chroma, capped hard
		 *
		 *	@return 	array									[ light ink, dark ink ]
		 */
		private static function inks( float $hue, float $chroma ): array {
			return [
				self::hex( 0.99, 0.0, $hue ),
				self::hex( 0.16, min( 0.006, $chroma ), $hue ),
			];
		}

		/**
		 *	The secondary text tier, never more prominent than the primary one.
		 */
		private static function muted( string $bg, string $on, float $hue, float $chroma, float $target, bool $darker ): string {

			$muted = self::solve( $hue, $chroma * 0.3, $bg, $target, $darker );

			return self::contrast( $muted, $bg ) > self::contrast( $on, $bg ) ? $on : $muted;
		}

		/**
		 *	One surface's own lightness, solved so that text of the required
		 *	target still fits on it. A brand color that is too light in dark
		 *	mode (or too dark in light mode) is moved until it works rather
		 *	than shipped as an unreadable button.
		 */
		private static function solveSurface( float $hue, float $chroma, string $mode, float $target ): string {

			// The text polarity is decided first, then one lightness is solved
			// for it. Accepting whichever of white/dark happens to clear the
			// target lets the search escape to the far end of the scale - that
			// is how a brand blue came back as near-white.
			[ $lightInk, $darkInk ] = self::inks( $hue, $chroma );
			$ink	= $mode === 'dark' ? $darkInk : $lightInk;
			$lo		= 0.0;
			$hi		= 1.0;

			// Light mode: the darkest half carries white text, so take the
			// LIGHTEST lightness that still clears the target - the brand stays
			// as bright as it can while remaining readable. Dark mode inverts:
			// the surface carries dark text, so take the darkest that clears.
			for( $i = 0; $i < 40; $i++ ) {
				$mid = ( $lo + $hi ) / 2;
				$ok  = self::contrast( self::hex( $mid, $chroma, $hue ), $ink ) >= $target;

				if( $mode === 'dark' )
					$ok ? $hi = $mid : $lo = $mid;
				else
					$ok ? $lo = $mid : $hi = $mid;
			}

			return self::hex( $mode === 'dark' ? $hi : $lo, $chroma, $hue );
		}

		/**
		 *	Solve a foreground lightness against a fixed background until the
		 *	measured ratio meets the target. Binary search rather than a fixed
		 *	OKLCH lightness: fixed values land near the target, this lands on it.
		 */
		private static function solve( float $hue, float $chroma, string $against, float $target, bool $darker ): string {

			$lo = 0.0;
			$hi = 1.0;

			for( $i = 0; $i < 40; $i++ ) {
				$mid	= ( $lo + $hi ) / 2;
				$ratio	= self::contrast( self::hex( $mid, $chroma, $hue ), $against );

				if( $darker === true )
					$ratio >= $target ? $lo = $mid : $hi = $mid;
				else
					$ratio >= $target ? $hi = $mid : $lo = $mid;
			}

			return self::hex( $darker === true ? $lo : $hi, $chroma, $hue );
		}

		private static function nudge( string $hex, bool $lighter, float $steps ): string {
			[ $l, $c, $h ] = self::oklch( $hex );
			return self::hex( max( 0.0, min( 1.0, $l + ( $lighter ? -0.045 : 0.055 ) * $steps ) ), $c, $h );
		}

		/**
		 *	The generated :root block, ready to be written as a stylesheet.
		 *	Only Design writes these names; a Theme reads them and assigns its
		 *	own semantic tokens from them.
		 */
		public static function css( array $settings ): string {

			$out = "/* Generated by /_theme. Do not edit - every save rewrites this file. */\n";
			$out .= ":root {\n\tcolor-scheme: light dark;\n". self::block( $settings, 'light' ). self::sizes( $settings ). "}\n";

			// The raster has no dark variant, so it sits in the bare :root
			// above and only its wide-screen half needs a block of its own.
			// Placed before the two dark colour blocks because it overrides
			// nothing they set - keeping the colour cascade one uninterrupted
			// run is worth more than grouping by media query
			$wide = self::raster( $settings )['textWide'];

			if( $wide !== [] ) {
				$out .= "\n@media (min-width: ". self::BREAKPOINT. ") {\n\t:root {\n";
				foreach( $wide as $step => $value )
					$out .= "\t\t--nino-text-". $step. ': '. $value. ";\n";
				$out .= "\t}\n}\n";
			}

			// Three states, not two: an explicit choice stamps data-nino-mode,
			// and the default "follow the system" setting stamps nothing at all -
			// so the media query has to carry the unstamped case while the
			// attribute rule wins in both directions when somebody has chosen
			$out .= "\n@media (prefers-color-scheme: dark) {\n\t:root:not([data-nino-mode=\"light\"]) {\n";
			$out .= self::block( $settings, 'dark', 2 );
			$out .= "\t}\n}\n";

			$out .= "\n:root[data-nino-mode=\"dark\"] {\n". self::block( $settings, 'dark' ). "}\n";

			return $out;
		}

		/**
		 *	The size raster: one fixed set of steps a theme assigns from, the
		 *	way the palette is one fixed set of surfaces.
		 *
		 *	Unlike the palette this has no light/dark variant - a heading is
		 *	the same size at night - so it is emitted once, in the bare :root,
		 *	and never repeated in the two dark blocks.
		 *
		 *	@param		array			$settings			Already-normalized design settings
		 *
		 *	@return 	array									{ text, space, radius, textWide, lineHeight }
		 */
		public static function raster( array $settings ): array {

			$volume 	= self::VOLUME[$settings['volume'] ?? 'default'] ?? self::VOLUME['default'];
			$spacing	= self::SPACING[$settings['spacing'] ?? 'default'] ?? self::SPACING['default'];

			$text 		= [];
			$space 		= [];
			$textWide	= [];

			foreach( self::BASE_TEXT as $index => $rem ) {

				$step = $index + 1;

				$text[$step] = self::rem( $rem * self::fan( $volume, $index, count( self::BASE_TEXT ) ) );

				if( isset( self::BASE_TEXT_WIDE[$step] ) === true )
					$textWide[$step] = self::rem( self::BASE_TEXT_WIDE[$step] * self::fan( $volume, $index, count( self::BASE_TEXT ) ) );
			}

			foreach( self::BASE_SPACE as $index => $rem )
				$space[$index + 1] = self::rem( $rem * self::fan( $spacing, $index, count( self::BASE_SPACE ) ) );

			$radius = [];
			foreach( self::SHAPING[$settings['shaping'] ?? 'default'] ?? self::SHAPING['default'] as $index => $rem )
				$radius[$index + 1] = self::rem( $rem );

			return [
				'text' 				=> $text,
				'textWide' 		=> $textWide,
				'space' 			=> $space,
				'radius' 			=> $radius,
				'lineHeight' 	=> (string) ( self::LINE_HEIGHT[$settings['spacing'] ?? 'default'] ?? self::LINE_HEIGHT['default'] ),
			];
		}

		/**
		 *	One knob's multiplier at one step, interpolated between the pair it
		 *	declares. Linear rather than exponential: a knob that compounds
		 *	turns a one-notch change at the display end into a different design
		 *
		 *	@param		array			$knob					[ multiplier at the first step, at the last ]
		 *	@param		int				$index				Zero-based step
		 *	@param		int				$steps				How many steps the scale has
		 *
		 *	@return 	float
		 */
		private static function fan( array $knob, int $index, int $steps ): float {
			return $steps < 2 ? $knob[0] : $knob[0] + ( $knob[1] - $knob[0] ) * ( $index / ( $steps - 1 ) );
		}

		/**
		 *	A rem value with no more precision than a browser can use and no
		 *	trailing zeroes - '1.5rem', not '1.500rem'
		 *
		 *	@param		float			$value
		 *
		 *	@return 	string
		 */
		private static function rem( float $value ): string {
			return rtrim( rtrim( number_format( $value, 3, '.', '' ), '0' ), '.' ). 'rem';
		}

		/**
		 *	The raster as css declarations, for the bare :root block
		 *
		 *	@param		array			$settings			Already-normalized design settings
		 *	@param		int				$depth				Indentation, in tabs
		 *
		 *	@return 	string
		 */
		private static function sizes( array $settings, int $depth = 1 ): string {

			$raster	= self::raster( $settings );
			$indent	= str_repeat( "\t", $depth );
			$out		= $indent. '--nino-line-height: '. $raster['lineHeight']. ";\n";

			foreach( [ 'text' => 'text', 'space' => 'space', 'radius' => 'radius' ] as $group => $name )
				foreach( $raster[$group] as $step => $value )
					$out .= $indent. '--nino-'. $name. '-'. $step. ': '. $value. ";\n";

			// A pill is not a step on the scale - it is "as round as this box
			// gets", which is the same answer at every shaping setting
			$out .= $indent. "--nino-radius-full: 999rem;\n";

			return $out;
		}

		private static function block( array $settings, string $mode, int $depth = 1 ): string {

			$indent	= str_repeat( "\t", $depth );
			$out		= '';

			foreach( self::palette( $settings, $mode ) as $surface => $values )
				foreach( $values as $part => $hex )
					$out .= $indent. self::token( $surface, $part ). ': '. $hex. ";\n";

			return $out;
		}

		/**
		 *	The surface always follows an "on-" prefix directly, modifiers come
		 *	last: --nino-origin, --nino-on-origin, --nino-on-origin-muted. A
		 *	Theme reads these and assigns its own semantic names from them.
		 */
		private static function token( string $surface, string $part ): string {
			return match( true ) {
				$part === 'bg'					=> '--nino-'. $surface,
				$part === 'on'					=> '--nino-on-'. $surface,
				str_starts_with( $part, 'on-' )	=> '--nino-on-'. $surface. substr( $part, 2 ),
				default							=> '--nino-'. $surface. '-'. $part,
			};
		}

		// --- color math ------------------------------------------------------

		private static function toLinear( float $c ): float {
			return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		}

		private static function fromLinear( float $c ): float {
			return $c <= 0.0031308 ? $c * 12.92 : 1.055 * ( $c ** ( 1 / 2.4 ) ) - 0.055;
		}

		public static function luminance( string $hex ): float {
			$hex = ltrim( $hex, '#' );
			return 0.2126 * self::toLinear( hexdec( substr( $hex, 0, 2 ) ) / 255 )
				+ 0.7152 * self::toLinear( hexdec( substr( $hex, 2, 2 ) ) / 255 )
				+ 0.0722 * self::toLinear( hexdec( substr( $hex, 4, 2 ) ) / 255 );
		}

		public static function contrast( string $a, string $b ): float {
			$la = self::luminance( $a );
			$lb = self::luminance( $b );
			return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
		}

		// sRGB hex -> OKLCH, using Björn Ottosson's published matrices
		public static function oklch( string $hex ): array {

			$hex = ltrim( trim( $hex ), '#' );
			if( strlen( $hex ) === 3 )
				$hex = $hex[0]. $hex[0]. $hex[1]. $hex[1]. $hex[2]. $hex[2];
			if( preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) !== 1 )
				$hex = '4faae8';

			$r = self::toLinear( hexdec( substr( $hex, 0, 2 ) ) / 255 );
			$g = self::toLinear( hexdec( substr( $hex, 2, 2 ) ) / 255 );
			$b = self::toLinear( hexdec( substr( $hex, 4, 2 ) ) / 255 );

			$l = ( 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b ) ** ( 1 / 3 );
			$m = ( 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b ) ** ( 1 / 3 );
			$s = ( 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b ) ** ( 1 / 3 );

			$okL = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
			$okA = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
			$okB = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

			return [ $okL, sqrt( $okA * $okA + $okB * $okB ), atan2( $okB, $okA ) ];
		}

		/**
		 *	OKLCH -> sRGB hex. Chroma is reduced until the color actually fits
		 *	in sRGB rather than letting the channels clip: clipping does not
		 *	darken a too-saturated color, it shifts its hue - an out-of-gamut
		 *	brand blue came back as pale cyan, which is neither the brand nor
		 *	a lightness the solver asked for.
		 */
		public static function hex( float $L, float $C, float $H ): string {

			for( $i = 0; $i < 24 && $C > 0.0005; $i++ ) {
				if( self::inGamut( $L, $C, $H ) === true )
					break;
				$C *= 0.85;
			}

			return self::clip( $L, $C, $H );
		}

		private static function inGamut( float $L, float $C, float $H ): bool {
			foreach( self::linearRgb( $L, $C, $H ) as $channel )
				if( $channel < -0.0001 || $channel > 1.0001 )
					return false;
			return true;
		}

		private static function clip( float $L, float $C, float $H ): string {

			$out = '#';
			foreach( self::linearRgb( $L, $C, $H ) as $channel )
				$out .= str_pad( dechex( (int) round( max( 0.0, min( 1.0, self::fromLinear( $channel ) ) ) * 255 ) ), 2, '0', STR_PAD_LEFT );

			return $out;
		}

		private static function linearRgb( float $L, float $C, float $H ): array {

			$a = $C * cos( $H );
			$b = $C * sin( $H );

			$l = ( $L + 0.3963377774 * $a + 0.2158037573 * $b ) ** 3;
			$m = ( $L - 0.1055613458 * $a - 0.0638541728 * $b ) ** 3;
			$s = ( $L - 0.0894841775 * $a - 1.2914855480 * $b ) ** 3;

			return [
				 4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
				-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
				-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
			];
		}
	}
}
