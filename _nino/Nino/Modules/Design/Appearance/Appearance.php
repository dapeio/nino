<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Design\Appearance	Theme, Header and Footer: the catalogue units and how one is applied
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Design {

	/**
	 *	The selectable appearance units shared by the wizard and the Design
	 *	panel. The catalogue is the wizard's (see \Nino\Modules\Design::library()),
	 *	read from there after setup so a completed project can change its Theme
	 *	and frames later.
	 *
	 *	Theme application establishes the manifest's complete baseline. The two
	 *	frame endpoints deliberately do less: each replaces only one template,
	 *	one stylesheet and its persisted key.
	 */
	class Appearance {

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

			$data = Admin::postData();
			$key = (string) ( $data['theme'] ?? '' );
			$manifest = self::_readManifest( $key );

			if( $manifest === null ) {
				\Nino\Http::fail( $request, 400, 'unknown theme: "'. $key. '"' );
				return;
			}

			$unitDir = \Nino\Modules\Design::library(). '/themes/'. $key;

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

			$design = Tokens::normalize(
				is_array( $data['design'] ?? null )
					? $data['design']
					: ( is_array( $manifest['design'] ?? null ) ? $manifest['design'] : [] )
			);

			if( \Nino\Modules\Design::write( $appData, $design ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the design stylesheet' );
				return;
			}

			\Nino\AppData::writeContentData( $appData, $keys );
			\Nino\Http::ok( $request, [ 'theme' => $key ] + $applied );
		}

		public static function apiFrameApply( array &$appData, array &$request ): void {

			$data = Admin::postData();
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

		private static function _frameKey( string $kind, string $posted, string $declared ): ?string {
			$available = self::_frames()[$kind] ?? [];

			foreach( [ $posted, $declared ] as $candidate )
				if( $candidate !== '' && in_array( $candidate, $available, true ) === true )
					return $candidate;

			return $available[0] ?? null;
		}

		private static function _applyFrame( array &$appData, string $kind, string $frame ): void {
			$unitDir = \Nino\Modules\Design::library(). '/'. $kind. '/'. $frame;
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
				$directory = \Nino\Modules\Design::library(). '/'. $kind;

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
			$directory = \Nino\Modules\Design::library(). '/themes';

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
						? ( (string) ( $appData['/nino/dir'] ?? '' ) ). '/_admin/install/library/themes/'. $entry. '/'. $preview
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
			$directory = \Nino\Modules\Design::library(). '/themes';

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

			$path = \Nino\Modules\Design::library(). '/themes/'. $key. '/manifest.php';
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

}
