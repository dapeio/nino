<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Images\Admin	Images panel: the image of every slot
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Images {

	/**
	 *	Nino										A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Images									Admin "Images" panel: developer-fixed image slots
	 *													(/nino/html/images in config.php) - the admin can only
	 *													replace a slot's current image, never add/remove slots,
	 *													same shape as Users can only edit accounts, not create them
	 *
	 *	@package								Dape/Nino
	 *	@author									David Perchermeier <mail@dape.io>
	 *	@link										https://github.com/dapeio/nino
	 */

	class Admin {

		public const string MANAGE_PERM = '/_admin/images/manage';

		public static function actions(): array {
			return [
				'images/list' 		=> [ self::class, 'apiList' ],
				'images/upload' 	=> [ self::class, 'apiUpload' ],
			];
		}

		public static function nav(): array {
			return [ 'images', '/_admin/nav/images', 40, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-images-icon lucide-images"><path d="m22 11-1.296-1.296a2.4 2.4 0 0 0-3.408 0L11 16"/><path d="M4 8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2"/><circle cx="13" cy="7" r="1" fill="currentColor"/><rect x="8" y="2" width="14" height="14" rx="2"/></svg>';
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'images-list', 'images-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// The slots these images fill are edited on a tab of this same
		// pane (see \Nino\Admin\Panels::collect()), with its own permission
		public static function tabs(): array {
			return [ Slots::class ];
		}

		public static function log( string $action, array $data ): string {
			return $action === 'images/upload' ? 'Upload Image '. ( $data['uri'] ?? '' ) : '';
		}

		/**
		 *	List every developer-fixed image slot
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
			foreach( \Nino\Images::getSlots( $appData ) as $uri => $slot )
				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'url' 			=> ( empty( $slot['filename'] ) === false ) ? \Nino\Images::getUrl( $appData, $slot['filename'] ) : null,
				];

			\Nino\Http::ok( $request, [ 'slots' => $slots ] );
		}

		/**
		 *	Upload, process and store a new image for one slot. Committed
		 *	immediately, same as \Nino\Modules\Elements\Admin::apiUploadImage() - stored at a
		 *	deterministic path ("images/<uri>"), so a replace overwrites in
		 *	place; the previous file only needs deleting in the rare case the
		 *	output format itself changed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUpload( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$uri 	= (string) ( \Nino\Admin\Admin::postData()['uri'] ?? '' );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			if( isset( $_FILES['file'] ) === false || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
				\Nino\Http::fail( $request, 400, 'no file uploaded' );
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				\Nino\Http::fail( $request, 400, 'could not read upload' );
				return;
			}

			$oldFilename = $slot['filename'] ?? null;

			$filename = \Nino\Images::process( $appData, $bytes, (int) ( $slot['width'] ?? 0 ), (int) ( $slot['height'] ?? 0 ), ltrim( $uri, '/' ) );

			if( $filename === false ) {
				\Nino\Http::fail( $request, 400, 'invalid or oversized image' );
				return;
			}

			\Nino\Images::setSlotFilename( $appData, $uri, $filename );

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			\Nino\Http::ok( $request, [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ] );
		}
	}
}
