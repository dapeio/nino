<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Images					Upload -> validate -> centered crop/resize -> store, shared by every image slot
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Images - upload -> validate -> centered crop/resize -> store, shared
	// by Elements' "image" field type and any developer-fixed image slot.
	// Every image is re-encoded via gd from scratch, never the uploaded
	// bytes as-is, which also discards anything a crafted file might carry
	// beyond actual pixel data
	class Images {

		private const int MAX_UPLOAD_BYTES 		= 8 * 1024 * 1024;
		// 20 megapixels, not 40: gd decodes into a 4-bytes-per-pixel truecolor
		// buffer, so this caps imagecreatefromstring() at roughly 80MB - the raw
		// bytes and the target canvas come on top of that, and the total still
		// fits php's 128M memory_limit default. At 40MP the buffer alone is
		// ~160MB, ie. the guard would let through exactly the upload that OOMs
		// on the cheap shared hosting this is built for. Larger camera sources
		// (including a 24MP 6000x4000 image) are deliberately rejected.
		private const int MAX_SOURCE_PIXELS 		= 20 * 1000 * 1000;
		private const string UPLOAD_DIR 				= '/images';

		// Validate, center-crop and resize raw uploaded image bytes to exactly
		// $targetWidth x $targetHeight, then store the result at $basePath
		// with an extension appended for the chosen output format - the
		// caller picks $basePath deterministically (eg. "elements/<type>/<uri>"),
		// so re-uploading the *same slot at the same configured dimensions*
		// overwrites in place rather than accumulating orphaned files. The
		// target size is baked into the filename (see below) - changing a
		// slot's configured width/height in config.php orphans whatever file
		// the old dimensions produced; the stored filename keeps pointing at
		// it until the next re-upload writes a new one under the new name
		public static function process( array &$appData, string $bytes, int $targetWidth, int $targetHeight, string $basePath ): string|false {

			if( $bytes === '' || strlen( $bytes ) > self::MAX_UPLOAD_BYTES || $targetWidth < 1 || $targetHeight < 1 || $basePath === '' )
				return false;

			// $basePath is built from a type/uri/key that are already each individually
			// validated by the caller, but never trust a path past this point regardless
			if( str_contains( $basePath, '..' ) === true || str_starts_with( $basePath, '/' ) === true )
				return false;

			$info = @getimagesizefromstring( $bytes );
			if( $info === false || in_array( $info[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ], true ) === false )
				return false;

			// Total pixel count, not either edge alone: an edge-only check let an
			// 8000x8000 source through (both edges exactly at the old limit) -
			// 64 megapixels, which imagecreatefromstring() below decodes into a
			// ~256MB truecolor buffer on top of the raw bytes and the target
			// canvas. A small, deceptively compressed source (eg. a flat-color
			// PNG scan) sails straight past MAX_UPLOAD_BYTES, so the byte-size
			// check alone never catches this
			if( $info[0] * $info[1] > self::MAX_SOURCE_PIXELS )
				return false;

			// A GIF's animation does not survive this: imagecreatefromstring()
			// only ever decodes the first frame, and the re-encode below is a
			// still image regardless of source format - fine for a static
			// logo slot, a surprise for anyone uploading an animated one
			$source = @imagecreatefromstring( $bytes );
			if( $source === false )
				return false;

			$sourceWidth 	= imagesx( $source );
			$sourceHeight	= imagesy( $source );

			// Centered crop: the largest rectangle matching the target aspect ratio
			// that fits inside the source, centered, then resized down/up onto it
			$targetRatio = $targetWidth / $targetHeight;
			$sourceRatio = $sourceWidth / $sourceHeight;

			if( $sourceRatio > $targetRatio ) {
				$cropHeight	= $sourceHeight;
				$cropWidth	= (int) round( $sourceHeight * $targetRatio );
			} else {
				$cropWidth	= $sourceWidth;
				$cropHeight	= (int) round( $sourceWidth / $targetRatio );
			}

			$cropX = (int) round( ( $sourceWidth - $cropWidth ) / 2 );
			$cropY = (int) round( ( $sourceHeight - $cropHeight ) / 2 );

			// Alpha-aware output: png (with transparency preserved) for a source that
			// might carry it, jpeg otherwise - keeps photos small, logos crisp
			$keepAlpha = in_array( $info[2], [ IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ], true );

			$canvas = @imagecreatetruecolor( $targetWidth, $targetHeight );

			// Same allocation-failure class MAX_SOURCE_PIXELS above guards
			// against, just on the target side - a clean false here beats
			// imagealphablending()'s first param TypeError-ing under
			// strict_types when handed one
			if( $canvas === false ) {
				imagedestroy( $source );
				return false;
			}

			if( $keepAlpha === true ) {
				imagealphablending( $canvas, false );
				imagesavealpha( $canvas, true );
				imagefill( $canvas, 0, 0, imagecolorallocatealpha( $canvas, 0, 0, 0, 127 ) );
			}

			imagecopyresampled( $canvas, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight );
			imagedestroy( $source );

			ob_start();
			if( $keepAlpha === true ) {
				imagepng( $canvas, null, 8 );
			} else {
				// Progressive encoding (renders a low-res pass immediately, then
				// sharpens - faster perceived load on a slow connection) and quality
				// 82 rather than 85 - the standard sweet spot for web photos, smaller
				// files with no visible difference at normal viewing sizes
				imageinterlace( $canvas, true );
				imagejpeg( $canvas, null, 82 );
			}
			$encoded = ob_get_clean();
			imagedestroy( $canvas );

			if( $encoded === false || $encoded === '' )
				return false;

			$filename = $basePath. ( '.'. $targetWidth. 'x'. $targetHeight ). ( $keepAlpha ? '.png' : '.jpg' );

			if( \Nino\Filesystem::putFileContent( $appData, self::UPLOAD_DIR. '/'. $filename, $encoded ) === false )
				return false;

			return $filename;
		}

		// Read a previously processed image for a short-lived rollback snapshot.
		// Element image replacement normally overwrites a deterministic filename
		// in place; if the following metadata update is vetoed, the panel must be
		// able to restore those old bytes rather than deleting the only copy.
		public static function read( array &$appData, string $filename ): string|false {

			if( $filename === '' || str_contains( $filename, '..' ) === true || str_starts_with( $filename, '/' ) === true )
				return false;

			$content = \Nino\Filesystem::getFileContent( $appData, self::UPLOAD_DIR. '/'. $filename, false );

			return is_string( $content ) ? $content : false;
		}

		// Counterpart to read(): restore a validated processed filename through
		// Filesystem's atomic writer after a failed deterministic replacement.
		public static function restore( array &$appData, string $filename, string $bytes ): bool {

			if( $filename === '' || str_contains( $filename, '..' ) === true || str_starts_with( $filename, '/' ) === true )
				return false;

			return \Nino\Filesystem::putFileContent( $appData, self::UPLOAD_DIR. '/'. $filename, $bytes );
		}

		public static function delete( array &$appData, string $filename ): void {

			// process() only ever hands out names it generated itself (nested under
			// our own upload dir, eg. "elements/<type>/<uri>.jpg"), but a stored
			// value could in theory have been hand-edited, so never trust it blindly
			if( $filename === '' || str_contains( $filename, '..' ) === true || str_starts_with( $filename, '/' ) === true )
				return;

			$path = \Nino\Filesystem::path( $appData, self::UPLOAD_DIR. '/'. $filename );
			// @: same TOCTOU as Filesystem::removeDir() - is_file() above and
			// unlink() here are two syscalls, not one
			if( is_file( $path ) === true )
				@unlink( $path );
		}

		public static function getUrl( array &$appData, string $filename ): string {
			return \Nino\Filesystem::url( $appData, self::UPLOAD_DIR. '/'. $filename );
		}

		// Every developer-fixed image slot ("/nino/html/images" in config.php) -
		// unlike an Element's "image" field, slots themselves can't be added or
		// removed from the admin, only the file each currently points to changes
		public static function getSlots( array &$appData ): array {
			return $appData['/nino/html/images'] ?? [];
		}

		public static function getSlot( array &$appData, string $uri ): array|false {
			return self::getSlots( $appData )[$uri] ?? false;
		}

		// Record a new filename for an existing slot and persist it - only
		// the /nino/html/images key of config.php, same as Auth::updateUser()
		// only persists /nino/auth/user (see AppData::writeContentData())
		public static function setSlotFilename( array &$appData, string $uri, string $filename ): bool {

			if( self::getSlot( $appData, $uri ) === false )
				return false;

			$appData['/nino/html/images'][$uri]['filename'] = $filename;
			\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

			return true;
		}
	}
}
