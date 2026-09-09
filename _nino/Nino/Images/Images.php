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

	// Images - upload -> validate -> resize -> store, shared by Elements'
	// "image" field type, any developer-fixed image slot, and whatever a
	// feature stores. Every image is re-encoded via gd from scratch, never
	// the uploaded bytes as-is, which also discards anything a crafted file
	// might carry beyond actual pixel data
	//
	// Two ways to size one: process() crops to exactly the dimensions asked
	// for, fit() scales the whole picture into a box and keeps its ratio.
	// Neither is the only possible answer, which is what RENDER is for - a
	// feature that wants webp, a srcset, an imagick pipeline or an upload of
	// its own registers there and replaces the encoding without replacing the
	// gatekeeping
	class Images {

		// The rendering callback. Called with the image as an array -
		//
		//   [ 'mode' => 'crop' or 'fit', 'bytes' => the validated source,
		//     'width' => target or maximum width, 'height' => the same,
		//     'basePath' => the deterministic path without an extension,
		//     'source' => [ 'width', 'height', 'type' ] of the upload,
		//     'filename' => null ]
		//
		// - and a handler that rendered it sets 'filename' to the path it
		// wrote below /images/, or false to refuse the upload outright. One
		// that leaves it at null passes the image on: to the next callback,
		// and finally to gd here.
		//
		// It fires after the checks and before the encoding, and that split is
		// deliberate: the byte cap, the path, the image type and the pixel
		// cap are what keep an upload endpoint safe, and they are not
		// something a feature should be able to switch off by accident. What
		// a handler gets is bytes that are already known to be an image this
		// server can decode
		public const string RENDER = '/nino/images/render';

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

			return self::_render( $appData, 'crop', $bytes, $targetWidth, $targetHeight, $basePath );
		}

		/**
		 *	The whole picture, scaled to fit inside $maxWidth x $maxHeight
		 *	with its own proportions kept - and never scaled up, so a source
		 *	smaller than the box is stored as it is.
		 *
		 *	The counterpart to process(), for everything a fixed frame is the
		 *	wrong answer for: the large view behind a thumbnail, where cropping
		 *	is exactly what a viewer opened the image to undo. The box goes
		 *	into the filename rather than the result, so the name stays
		 *	deterministic per slot even though two uploads rarely come out the
		 *	same size.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$bytes				The uploaded bytes
		 *	@param		int				$maxWidth			The box, not the result
		 *	@param		int				$maxHeight
		 *	@param		string		$basePath			Deterministic, without an extension
		 *
		 *	@return 	string|false					The stored filename below /images/, or false
		 */
		public static function fit( array &$appData, string $bytes, int $maxWidth, int $maxHeight, string $basePath ): string|false {

			return self::_render( $appData, 'fit', $bytes, $maxWidth, $maxHeight, $basePath );
		}

		/**
		 *	What both of them are: the checks, the callback, and gd where no
		 *	callback took the image
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$mode					'crop' or 'fit'
		 *	@param		string		$bytes
		 *	@param		int				$width
		 *	@param		int				$height
		 *	@param		string		$basePath
		 *
		 *	@return 	string|false
		 */
		private static function _render( array &$appData, string $mode, string $bytes, int $width, int $height, string $basePath ): string|false {

			if( $bytes === '' || strlen( $bytes ) > self::MAX_UPLOAD_BYTES || $width < 1 || $height < 1 || $basePath === '' )
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

			// Past the gatekeeping, before the encoding: a feature that renders
			// images its own way gets bytes that are known to be a decodable
			// image of a sane size, and everything else is its business
			if( isset( $appData['./nino/callbacks'][ self::RENDER ] ) === true ) {

				$image = [
					'mode'			=> $mode,
					'bytes'			=> $bytes,
					'width'			=> $width,
					'height'		=> $height,
					'basePath'	=> $basePath,
					'source'		=> [ 'width' => $info[0], 'height' => $info[1], 'type' => $info[2] ],
					'filename'	=> null,
				];

				\Nino\Callbacks::doCallbacks( $appData, self::RENDER, $image );

				// A name it wrote, or an outright refusal. Checked the same way
				// a stored name is checked anywhere else here: a handler is
				// project code, not a reason to stop looking
				if( $image['filename'] === false )
					return false;

				if( is_string( $image['filename'] ) === true && $image['filename'] !== ''
					&& str_contains( $image['filename'], '..' ) === false && str_starts_with( $image['filename'], '/' ) === false )
					return $image['filename'];
			}

			// A GIF's animation does not survive this: imagecreatefromstring()
			// only ever decodes the first frame, and the re-encode below is a
			// still image regardless of source format - fine for a static
			// logo slot, a surprise for anyone uploading an animated one
			$source = @imagecreatefromstring( $bytes );
			if( $source === false )
				return false;

			$sourceWidth 	= imagesx( $source );
			$sourceHeight	= imagesy( $source );

			if( $mode === 'fit' ) {

				// The whole picture into the box, never past its own size: a
				// 400px source blown up to a 1600px box is four times the bytes
				// for the same picture, badly
				$scale = min( $width / $sourceWidth, $height / $sourceHeight, 1 );

				$cropWidth		= $sourceWidth;
				$cropHeight		= $sourceHeight;
				$cropX				= 0;
				$cropY				= 0;
				$targetWidth	= max( 1, (int) round( $sourceWidth * $scale ) );
				$targetHeight	= max( 1, (int) round( $sourceHeight * $scale ) );

			} else {

				// Centered crop: the largest rectangle matching the target aspect ratio
				// that fits inside the source, centered, then resized down/up onto it
				$targetWidth	= $width;
				$targetHeight	= $height;
				$targetRatio	= $targetWidth / $targetHeight;
				$sourceRatio	= $sourceWidth / $sourceHeight;

				if( $sourceRatio > $targetRatio ) {
					$cropHeight	= $sourceHeight;
					$cropWidth	= (int) round( $sourceHeight * $targetRatio );
				} else {
					$cropWidth	= $sourceWidth;
					$cropHeight	= (int) round( $sourceWidth / $targetRatio );
				}

				$cropX = (int) round( ( $sourceWidth - $cropWidth ) / 2 );
				$cropY = (int) round( ( $sourceHeight - $cropHeight ) / 2 );
			}

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

			// The box for a fit, the exact size for a crop: what goes into the
			// name has to be what the caller asked for, or the name stops being
			// predictable from the slot alone and every re-upload orphans a file
			$suffix = $mode === 'fit' ? ( '.fit'. $width. 'x'. $height ) : ( '.'. $width. 'x'. $height );

			$filename = $basePath. $suffix. ( $keepAlpha ? '.png' : '.jpg' );

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
