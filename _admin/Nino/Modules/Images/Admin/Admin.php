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
	 *													replace a slot's current image or take it away, never
	 *													add/remove slots themselves, same shape as Users can
	 *													only edit accounts, not create them
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
				'images/remove' 	=> [ self::class, 'apiRemove' ],
				'images/alt' 			=> [ self::class, 'apiAlt' ],
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
			return match( $action ) {
				'images/upload'	=> 'Upload Image '. ( $data['uri'] ?? '' ),
				'images/remove'	=> 'Remove Image '. ( $data['uri'] ?? '' ),
				'images/alt'		=> 'Edit Image Alt '. ( $data['uri'] ?? '' ),
				default	=> '',
			};
		}

		/**
		 *	List every developer-fixed image slot - each with its alt texts per
		 *	language and where it is used (see Slots::usage()) - and the
		 *	languages an alt text can be written in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$usage = Slots::usage( $appData );

			$slots = [];
			foreach( \Nino\Images::getSlots( $appData ) as $uri => $slot )
				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'url' 			=> ( empty( $slot['filename'] ) === false ) ? \Nino\Images::getUrl( $appData, $slot['filename'] ) : null,
					'alt' 			=> $slot['alt'] ?? [],
					'usage' 		=> $usage[$uri] ?? Slots::NO_USAGE,
				];

			\Nino\Http::ok( $request, [ 'slots' => $slots, 'locales' => \Nino\Locales::getAvailableLocales( $appData ) ] );
		}

		/**
		 *	Upload, process and store a new image for one slot. Committed
		 *	immediately, same as \Nino\Modules\Elements\Admin::apiUploadImage() - stored at a
		 *	deterministic path ("images/<uri>"), so a replace overwrites in
		 *	place; the previous file only needs deleting in the rare case the
		 *	output format itself changed - and only once the slot's record is
		 *	written. A config.php that cannot be written leaves the old file
		 *	standing: put back as it was where the new one overwrote it, the
		 *	new one removed where it has a name of its own. The answer says how large the picture is as it is shown
		 *	and whether that is smaller than the slot's target: crop mode
		 *	scales such a picture up, which the person should hear about
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

			$failure = \Nino\Admin\Admin::uploadError( $_FILES['file'] ?? null );
			if( $failure !== null ) {
				\Nino\Http::fail( $request, $failure['status'], $failure['error'], $failure['code'], $failure['params'] );
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				\Nino\Http::fail( $request, 500, 'could not read upload', 'upload_server' );
				return;
			}

			// Said before the picture is touched, and in the terms the limits
			// are documented in - which of them it was is what the person has
			// to know to fix it
			$rejection = \Nino\Images::reject( $bytes );
			if( $rejection !== null ) {
				\Nino\Http::fail( $request, 400, 'the image was refused: '. $rejection['code'], $rejection['code'], $rejection['params'] );
				return;
			}

			$oldFilename	= $slot['filename'] ?? null;
			// A same-format replacement overwrites the old file in place, so
			// what a failed record write has to put back is read before that
			$oldBytes			= is_string( $oldFilename ) === true && $oldFilename !== '' ? \Nino\Images::read( $appData, $oldFilename ) : false;

			$filename = \Nino\Images::process( $appData, $bytes, (int) ( $slot['width'] ?? 0 ), (int) ( $slot['height'] ?? 0 ), ltrim( $uri, '/' ) );

			// The bytes are an image the kernel accepts, so this is gd or a
			// render callback giving up on one - or the file not being written,
			// which process() does not tell apart
			if( $filename === false ) {
				\Nino\Http::fail( $request, 400, 'the image could not be processed', 'image_unreadable' );
				return;
			}

			if( \Nino\Images::setSlotFilename( $appData, $uri, $filename ) === false ) {

				// The record still names the old file, which stays - and the new
				// one is a file nobody references, unless it overwrote the old
				// one in place under the same name: then the old bytes go back
				if( $filename === $oldFilename && is_string( $oldBytes ) === true )
					\Nino\Images::restore( $appData, $filename, $oldBytes );
				else
					\Nino\Images::delete( $appData, $filename );

				\Nino\Http::fail( $request, 500, 'could not save the slot' );
				return;
			}

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			$body = [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ];

			// What the picture is shown at, not what it is stored at: a
			// photograph a camera stored on its side is measured upright
			$shown = \Nino\Images::size( $bytes );
			if( $shown !== false ) {
				$body['source']				= [ 'width' => $shown['width'], 'height' => $shown['height'] ];
				$body['belowTarget']	= $shown['width'] < (int) ( $slot['width'] ?? 0 ) || $shown['height'] < (int) ( $slot['height'] ?? 0 );
			}

			\Nino\Http::ok( $request, $body );
		}

		/**
		 *	Save the alt texts of one slot - { uri, alt : { <locale> : text } }.
		 *	Merged per posted language, an empty text removes that language's
		 *	entry (see \Nino\Images::setSlotAlt()), so the panel posts every
		 *	input it shows. An alt text is one short line: more than 250
		 *	characters once it is cleaned, a language the site does not have,
		 *	a value that is not a string and bytes that are not UTF-8 are all
		 *	a 400 and change nothing. Answers the slot's stored map
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiAlt( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data	= \Nino\Admin\Admin::postData();
			$uri 	= (string) ( $data['uri'] ?? '' );

			if( \Nino\Images::getSlot( $appData, $uri ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$alt = $data['alt'] ?? null;
			if( is_array( $alt ) === false ) {
				\Nino\Http::fail( $request, 400, 'alt must be a list of texts by language' );
				return;
			}

			foreach( $alt as $locale => $value ) {

				if( is_string( $locale ) === false || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown language' );
					return;
				}

				$text = is_string( $value ) === true ? \Nino\Images::cleanAlt( $value ) : false;
				if( $text === false ) {
					\Nino\Http::fail( $request, 400, 'an alt text is plain text' );
					return;
				}

				if( mb_strlen( $text ) > 250 ) {
					\Nino\Http::fail( $request, 400, 'an alt text is at most 250 characters' );
					return;
				}
			}

			if( \Nino\Images::setSlotAlt( $appData, $uri, $alt ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the alt texts' );
				return;
			}

			\Nino\Http::ok( $request, [ 'alt' => \Nino\Images::getSlot( $appData, $uri )['alt'] ?? [] ] );
		}

		/**
		 *	Take a slot's image away: the slot stays, the website shows no
		 *	image there until a new one is uploaded. The record goes first and
		 *	the file second, as in Slots::apiDelete() - a write that does not
		 *	happen leaves the slot and its file as they were, and the worst
		 *	case the other way round is a file nobody references. Only a file
		 *	the slot owns is deleted: its name starts with the slot's own
		 *	deterministic base path, which is what apiUpload() writes, and no
		 *	other slot names it. A name an editor wrote into config.php by
		 *	hand - a picture a template includes literally - is cleared from the
		 *	slot and stays on disk. Answering [ 'filename' => null ] for a
		 *	slot that has none makes the call safe to repeat
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRemove( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$uri 	= (string) ( \Nino\Admin\Admin::postData()['uri'] ?? '' );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$old = $slot['filename'] ?? null;

			if( is_string( $old ) === false || $old === '' ) {
				\Nino\Http::ok( $request, [ 'filename' => null ] );
				return;
			}

			if( \Nino\Images::setSlotFilename( $appData, $uri, null ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the slot' );
				return;
			}

			$shared = false;
			foreach( \Nino\Images::getSlots( $appData ) as $other )
				if( ( $other['filename'] ?? null ) === $old )
					$shared = true;

			if( $shared === false && str_starts_with( $old, ltrim( $uri, '/' ). '.' ) === true )
				\Nino\Images::delete( $appData, $old );

			\Nino\Http::ok( $request, [ 'filename' => null ] );
		}
	}
}
