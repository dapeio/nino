<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Images\Slots	Image Slots tab: the slots and their target sizes
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Images {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Manage image slots (/nino/html/images): create, edit label/
	 *												width/height, delete - the "set" half of what the Images panel's
	 *												Images panel edits ("values" half: which file currently
	 *												fills a slot). Same split as ElementTypes/Elements. Only
	 *												ever touches a slot's filename when deleting the slot
	 *												itself (cleans up its uploaded file via \Nino\Images::
	 *												delete()) - replacing it stays the Images panel's job via the actual
	 *												upload/crop pipeline (\Nino\Images::process()/
	 *												setSlotFilename()).
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Slots {

		public const string MANAGE_PERM = '/_admin/slots/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'slots/list' 	=> [ self::class, 'apiList' ],
				'slots/save' 	=> [ self::class, 'apiSave' ],
				'slots/create' => [ self::class, 'apiCreate' ],
				'slots/delete' => [ self::class, 'apiDelete' ],
				'slots/scan' 	=> [ self::class, 'apiScan' ],
			];
		}

		/**
		 *	A Dashboard tile: slots the templates use that are not defined yet
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function summary( array &$appData ): array {
			return [ 'value' => self::missingCount( $appData ), 'label' => '/_admin/dashboard/label/slots' ];
		}

		/**
		 *	A tab of the Images pane (see \Nino\Modules\Images\Admin::tabs()) - the uri names the
		 *	hash prefix and the script, the weight orders the strip, and the
		 *	group only says where the permission is listed
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'slots', '/_admin/nav/slots', 40, 'structure' ];
		}

		public static function panes(): array {
			return [ 'slots-list', 'slots-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/slots.js' ) ];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	A slot uri follows the same shape as an element uri (Elements/
		 *	Images shortcodes address slots by a "/segment/segment" path)
		 *
		 *	@param		string		$uri
		 *
		 *	@return 	bool
		 */
		private static function isValidUri( string $uri ): bool {
			return preg_match( '#^/[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)*$#', $uri ) === 1;
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'slots/create'	=> 'Add Image Slot '. ( $data['uri'] ?? '' ),
				'slots/save'		=> 'Edit Image Slot '. ( $data['uri'] ?? '' ),
				'slots/delete'	=> 'Delete Image Slot '. ( $data['uri'] ?? '' ),
				default	=> '',
			};
		}

		/**
		 *	List every image slot with its current filename (if any)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$slots = [];
			foreach( ( $appData['/nino/html/images'] ?? [] ) as $uri => $slot )
				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'hasImage' 	=> ( $slot['filename'] ?? null ) !== null,
				];

			usort( $slots, fn( array $a, array $b ) => strcmp( $a['uri'], $b['uri'] ) );

			\Nino\Http::ok( $request, [ 'slots' => $slots ] );
		}

		/**
		 *	Edit an existing slot's label/width/height - never its filename
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			if( isset( $appData['/nino/html/images'][$uri] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$width 	= max( 1, (int) ( $data['width'] ?? 0 ) );
			$height = max( 1, (int) ( $data['height'] ?? 0 ) );

			if( $label === '' ) {
				\Nino\Http::fail( $request, 400, 'label is required' );
				return;
			}

			$appData['/nino/html/images'][$uri]['label'] 	= $label;
			$appData['/nino/html/images'][$uri]['width'] 	= $width;
			$appData['/nino/html/images'][$uri]['height'] = $height;

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Create a brand new, empty (no filename yet) image slot
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$uri 		= (string) ( $data['uri'] ?? '' );
			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$width 	= max( 1, (int) ( $data['width'] ?? 0 ) );
			$height = max( 1, (int) ( $data['height'] ?? 0 ) );

			if( self::isValidUri( $uri ) === false || $label === '' ) {
				\Nino\Http::fail( $request, 400, 'invalid uri or missing label' );
				return;
			}

			if( isset( $appData['/nino/html/images'][$uri] ) === true ) {
				\Nino\Http::fail( $request, 409, 'slot already exists' );
				return;
			}

			$appData['/nino/html/images'][$uri] = [
				'label' 		=> $label,
				'width' 		=> $width,
				'height' 		=> $height,
				'filename' 	=> null,
			];

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			\Nino\Http::ok( $request, [ 'ok' => true, 'uri' => $uri ] );
		}

		/**
		 *	Delete an image slot - its currently uploaded file too, if any
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			$slot = $appData['/nino/html/images'][$uri] ?? null;

			if( $slot === null ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			if( ( $slot['filename'] ?? null ) !== null )
				\Nino\Images::delete( $appData, $slot['filename'] );

			unset( $appData['/nino/html/images'][$uri] );

			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Scan every public-site template (templates/*.tpl) for literal
		 *	<img src="/images/..."> tags not backed by any image slot - the
		 *	gap this closes: a template built with a placeholder/demo photo
		 *	hardcoded straight into the markup instead of going through the
		 *	[image /uri] shortcode (see \Nino\Modules\Images), so an
		 *	admin can never swap it without editing code. Proposes a slot
		 *	per file (uri guessed from the filename, width/height read off
		 *	the <img> tag's own attributes or, failing that, probed from the
		 *	actual file), filename deliberately left for apiCreate() to
		 *	leave empty - same "dev only ever creates the empty slot, a real
		 *	upload is the Images panel's job" rule the class docblock already states.
		 *	An <img> outside /images/ (external url, data: uri, favicon,
		 *	logo) is skipped entirely, none of those fit this slot system
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiScan( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'missing' => self::_scanMissing( $appData ) ] );
		}

		/**
		 *	How many <img> tags apiScan() above would currently report as
		 *	missing a slot - shared by \Nino\Modules\Dashboard\Admin::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function missingCount( array &$appData ): int {
			return count( self::_scanMissing( $appData ) );
		}

		/**
		 *	Scan every public-site template for <img src="/images/..."> tags
		 *	not backed by any image slot - the actual work behind
		 *	apiScan()/missingCount() above
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'filename', 'src', 'suggestedUri', 'width', 'height', 'files' ], ... ]
		 */
		private static function _scanMissing( array &$appData ): array {

			$known = [];
			foreach( ( $appData['/nino/html/images'] ?? [] ) as $slot )
				if( ( $slot['filename'] ?? null ) !== null )
					$known[ $slot['filename'] ] = true;

			// Two roots: the templates being scanned are private, the images
			// they reference are public (see \Nino\Filesystem::PRIVATE_DIRS)
			$templates 	= \Nino\Filesystem::path( $appData, '/templates' );
			$images 		= \Nino\Filesystem::path( $appData, '/images' );
			$found 			= [];

			foreach( glob( $templates. '/*.tpl' ) ?: [] as $file ) {

				$content = file_get_contents( $file );
				if( $content === false || preg_match_all( '/<img\b[^>]*\bsrc="([^"]+)"[^>]*>/i', $content, $matches, PREG_SET_ORDER ) === false )
					continue;

				foreach( $matches as $match ) {

					// A local image is referenced as [[/nino/public]]/images/...
					// in current template source. Also accept [[/nino/dir]] here:
					// scanning developer-authored templates should diagnose that
					// form rather than silently ignoring the image altogether.
					$src = preg_replace( '#^\[\[/nino/(?:public|dir)\]\]#', '', $match[1] );

					if( str_starts_with( $src, '/images/' ) === false )
						continue;

					$relative = substr( $src, strlen( '/images/' ) );

					if( isset( $known[$relative] ) === true )
						continue;

					if( isset( $found[$relative] ) === false ) {

						$width 	= 0;
						$height = 0;

						if( preg_match( '/\bwidth="(\d+)"/i', $match[0], $w ) === 1 )
							$width = (int) $w[1];
						if( preg_match( '/\bheight="(\d+)"/i', $match[0], $h ) === 1 )
							$height = (int) $h[1];

						if( ( $width === 0 || $height === 0 ) && is_file( $images. '/'. $relative ) === true ) {
							$size = @getimagesize( $images. '/'. $relative );
							if( $size !== false ) {
								$width 	= $width ?: $size[0];
								$height = $height ?: $size[1];
							}
						}

						$suggestedUri = '/'. preg_replace( '/[^a-z0-9-]+/', '-', strtolower( pathinfo( $relative, PATHINFO_FILENAME ) ) );

						$found[$relative] = [ 'src' => $src, 'suggestedUri' => $suggestedUri, 'width' => $width, 'height' => $height, 'files' => [] ];
					}

					$found[$relative]['files'][] = basename( $file );
				}
			}

			foreach( $found as &$entry )
				$entry['files'] = array_values( array_unique( $entry['files'] ) );
			unset( $entry );

			ksort( $found );

			return array_map( fn( $filename, $entry ) => array_merge( [ 'filename' => $filename ], $entry ), array_keys( $found ), array_values( $found ) );
		}
	}
}
