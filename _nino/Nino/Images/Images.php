<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Images					Upload -> validate -> orient -> centered crop/resize -> store, shared by every image slot
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
	// might carry beyond actual pixel data - the EXIF block included, so the
	// orientation a camera recorded there is applied to the pixels first
	// (see _jpegOrientation()) and is not lost with it
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
		//     'source' => [ 'width', 'height', 'type', 'orientation' ] of the
		//     upload, 'filename' => null ]
		//
		// 'orientation' is the EXIF value 1-8 of a JPEG (1 for everything
		// else, and for a file that says nothing): width and height stay the
		// pixels as they are stored, so for 5-8 the picture is shown turned -
		// a handler that renders it itself has to apply the orientation, or
		// a photograph taken upright comes out on its side. A payload without
		// the key, as an older kernel sent it, means 1.
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

		// Public for the same reason as MAX_SOURCE_PIXELS below: it is what an
		// upload form has to tell the person before they pick a file (see
		// limits())
		public const int MAX_UPLOAD_BYTES 		= 8 * 1024 * 1024;
		// 20 megapixels, not 40: gd decodes into a 4-bytes-per-pixel truecolor
		// buffer, so this caps imagecreatefromstring() at roughly 80MB - the raw
		// bytes and the target canvas come on top of that, and the total still
		// fits php's 128M memory_limit default. At 40MP the buffer alone is
		// ~160MB, ie. the guard would let through exactly the upload that OOMs
		// on the cheap shared hosting this is built for. Larger camera sources
		// (including a 24MP 6000x4000 image) are deliberately rejected.
		//
		// Public because it is not only a gate on the way in: the target canvas
		// _render() allocates below is the same kind of buffer, so this is also
		// the largest picture this server can be asked to produce. A panel that
		// lets somebody configure a slot size has to be able to read it, or it
		// accepts a size no upload can ever fill
		public const int MAX_SOURCE_PIXELS 		= 20 * 1000 * 1000;
		private const string UPLOAD_DIR 				= '/images';

		// Validate, turn upright, center-crop and resize raw uploaded image bytes
		// to exactly $targetWidth x $targetHeight, then store the result at $basePath
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
		 *	The whole picture, turned upright and scaled to fit inside
		 *	$maxWidth x $maxHeight with its own proportions kept - and never
		 *	scaled up, so a source smaller than the box is stored as it is.
		 *	The box is the picture as it is shown, so a photograph that a
		 *	camera stored on its side is measured upright.
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
		 *	The limits an uploaded image runs into, all of them in one answer:
		 *	the kernel's own byte cap, what php lets a request carry
		 *	(upload_max_filesize for the file, post_max_size for the whole
		 *	request - the smaller of the two is what can arrive), and the pixel
		 *	cap. 'bytes' is the one to show and to check against, the smallest
		 *	of what applies.
		 *
		 *	php's two limits are ini strings ("2M", "8M", "512K", "0"), and a
		 *	value it cannot read counts as no limit rather than as a warning -
		 *	a malformed ini setting would otherwise be a failed request on every
		 *	workbench page, since limits() is asked for by the shell. 0 and
		 *	below mean no limit, which is what post_max_size = 0 says in php
		 *
		 *	@return 	array											[ 'kernel' => int, 'php' => int, 'post' => int, 'bytes' => int, 'pixels' => int ] -
		 *																		bytes, 'php' and 'post' 0 where php sets none, 'pixels' the
		 *																		largest source in pixels
		 */
		public static function limits(): array {

			return self::_limits( ini_get( 'upload_max_filesize' ), ini_get( 'post_max_size' ) );
		}

		/**
		 *	limits() for the two php settings given - which cannot be changed at
		 *	run time, so a test stands in for them here. Internal - public only so that
		 *	tests/kernel-smoke.php can call it
		 *
		 *	@param		string|false	$uploadMax		upload_max_filesize
		 *	@param		string|false	$postMax			post_max_size
		 *
		 *	@return 	array											See limits()
		 */
		public static function _limits( string|false $uploadMax, string|false $postMax ): array {

			$read = static function( string|false $value ): int {
				return $value === false ? 0 : max( 0, (int) @ini_parse_quantity( $value ) );
			};

			$upload	= $read( $uploadMax );
			$post		= $read( $postMax );
			$php		= array_filter( [ $upload, $post ], static fn( int $bytes ): bool => $bytes > 0 );
			$php		= $php === [] ? 0 : min( $php );

			return [
				'kernel'	=> self::MAX_UPLOAD_BYTES,
				'php'			=> $php,
				'post'		=> $post,
				'bytes'		=> $php > 0 ? min( self::MAX_UPLOAD_BYTES, $php ) : self::MAX_UPLOAD_BYTES,
				'pixels'	=> self::MAX_SOURCE_PIXELS,
			];
		}

		/**
		 *	Why the kernel would refuse these bytes as an image, in the terms
		 *	a client can say in its own language - or null if it would not.
		 *	The same checks _render() runs, ahead of everything it does with
		 *	the picture, so a panel can name the reason instead of answering
		 *	"invalid image" and there is one place the limits are enforced
		 *
		 *	@param		string		$bytes				The uploaded bytes
		 *
		 *	@return 	array|null								[ 'code' => string, 'params' => array ], one of
		 *																		image_too_large (the limit in MB), image_type,
		 *																		image_too_many_pixels (the limit in megapixels)
		 */
		public static function reject( string $bytes ): ?array {

			return self::_inspect( $bytes )[1];
		}

		/**
		 *	The size an image is shown at: its pixels, swapped where the
		 *	JPEG's EXIF orientation (5-8) turns it on its side. What the
		 *	slot's "smaller than the target size" check has to compare, since
		 *	that is the picture crop mode scales up - and false for anything
		 *	this class would not take as an image
		 *
		 *	@param		string		$bytes				The uploaded bytes
		 *
		 *	@return 	array|false								[ 'width', 'height', 'type', 'orientation' ], or false
		 */
		public static function size( string $bytes ): array|false {

			$info = @getimagesizefromstring( $bytes );
			if( $info === false || in_array( $info[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ], true ) === false )
				return false;

			$orientation	= $info[2] === IMAGETYPE_JPEG ? self::_jpegOrientation( $bytes ) : 1;
			$turned				= $orientation >= 5;

			return [
				'width'				=> $turned ? $info[1] : $info[0],
				'height'			=> $turned ? $info[0] : $info[1],
				'type'				=> $info[2],
				'orientation'	=> $orientation,
			];
		}

		/**
		 *	The EXIF orientation (1-8) of a JPEG, read off its header without
		 *	ext-exif - a host does not have to ship it, and nothing else of the
		 *	metadata is wanted. 1 is upright, and also the answer for anything
		 *	that is not a well-formed Exif block: there is no error here, a
		 *	file that cannot be read is simply not turned.
		 *
		 *	The segments are walked from the start marker to the first Exif
		 *	APP1 (an XMP APP1 before it is skipped), the TIFF header inside it
		 *	names the byte order, and IFD0 is searched for tag 0x0112. Every
		 *	read is checked against the length first - unpack() on short input
		 *	is a warning and an offset past the end a ValueError, and either of
		 *	them is a failed upload - and the walk is capped, so a crafted
		 *	file cannot keep it busy.
		 *
		 *	@param		string		$bytes				The validated source
		 *
		 *	@return 	int
		 */
		private static function _jpegOrientation( string $bytes ): int {

			$length = strlen( $bytes );
			if( $length < 4 || str_starts_with( $bytes, "\xFF\xD8" ) === false )
				return 1;

			$offset = 2;
			for( $segments = 0; $segments < 64; $segments++ ) {

				// A marker is FF and its code, with any number of FF fill
				// bytes in front of the code
				if( $offset + 1 >= $length || $bytes[$offset] !== "\xFF" )
					return 1;
				while( $offset + 1 < $length && $bytes[$offset + 1] === "\xFF" )
					$offset++;
				if( $offset + 1 >= $length )
					return 1;

				$marker = ord( $bytes[$offset + 1] );
				$offset += 2;

				// The pixels (or the end) come first: no Exif block after them
				if( $marker === 0xDA || $marker === 0xD9 )
					return 1;
				// These carry no length
				if( $marker === 0x01 || ( $marker >= 0xD0 && $marker <= 0xD7 ) )
					continue;

				if( $offset + 2 > $length )
					return 1;
				$size = unpack( 'n', substr( $bytes, $offset, 2 ) )[1];
				if( $size < 2 )
					return 1;

				$payload = substr( $bytes, $offset + 2, $size - 2 );
				if( $marker === 0xE1 && str_starts_with( $payload, "Exif\0\0" ) === true )
					return self::_tiffOrientation( substr( $payload, 6 ) );

				$offset += $size;
			}

			return 1;
		}

		/**
		 *	The orientation tag of the TIFF structure inside an Exif block,
		 *	for _jpegOrientation()
		 *
		 *	@param		string		$tiff					The block after "Exif\0\0"
		 *
		 *	@return 	int												1-8, or 1
		 */
		private static function _tiffOrientation( string $tiff ): int {

			$length = strlen( $tiff );
			if( $length < 8 )
				return 1;

			// The byte order of every number below: II little, MM big endian
			$order = substr( $tiff, 0, 2 );
			if( $order !== 'II' && $order !== 'MM' )
				return 1;
			$short	= $order === 'II' ? 'v' : 'n';
			$long		= $order === 'II' ? 'V' : 'N';

			if( unpack( $short, substr( $tiff, 2, 2 ) )[1] !== 42 )
				return 1;

			$ifd = unpack( $long, substr( $tiff, 4, 4 ) )[1];
			if( $ifd < 8 || $ifd + 2 > $length )
				return 1;

			$entries = min( unpack( $short, substr( $tiff, $ifd, 2 ) )[1], 256 );
			for( $index = 0; $index < $entries; $index++ ) {

				$entry = $ifd + 2 + $index * 12;
				if( $entry + 12 > $length )
					return 1;

				if( unpack( $short, substr( $tiff, $entry, 2 ) )[1] !== 0x0112 )
					continue;

				// A SHORT, one of it - its value sits left-aligned in the field
				$type		= unpack( $short, substr( $tiff, $entry + 2, 2 ) )[1];
				$count	= unpack( $long, substr( $tiff, $entry + 4, 4 ) )[1];
				$value	= unpack( $short, substr( $tiff, $entry + 8, 2 ) )[1];

				return ( $type === 3 && $count === 1 && $value >= 1 && $value <= 8 ) ? $value : 1;
			}

			return 1;
		}

		/**
		 *	The header of an image and what is wrong with it: [ getimagesize()
		 *	of the bytes, or null where they are refused; the refusal, or null ]
		 *
		 *	@param		string		$bytes
		 *
		 *	@return 	array
		 */
		private static function _inspect( string $bytes ): array {

			if( strlen( $bytes ) > self::MAX_UPLOAD_BYTES )
				return [ null, [ 'code' => 'image_too_large', 'params' => [ self::MAX_UPLOAD_BYTES / 1048576 ] ] ];

			$info = @getimagesizefromstring( $bytes );
			if( $info === false || in_array( $info[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP ], true ) === false )
				return [ null, [ 'code' => 'image_type', 'params' => [] ] ];

			// Total pixel count, not either edge alone: an edge-only check let an
			// 8000x8000 source through (both edges exactly at the old limit) -
			// 64 megapixels, which imagecreatefromstring() below decodes into a
			// ~256MB truecolor buffer on top of the raw bytes and the target
			// canvas. A small, deceptively compressed source (eg. a flat-color
			// PNG scan) sails straight past MAX_UPLOAD_BYTES, so the byte-size
			// check alone never catches this
			if( $info[0] * $info[1] > self::MAX_SOURCE_PIXELS )
				return [ null, [ 'code' => 'image_too_many_pixels', 'params' => [ self::MAX_SOURCE_PIXELS / 1000000 ] ] ];

			return [ $info, null ];
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

			if( $bytes === '' || $width < 1 || $height < 1 || $basePath === '' )
				return false;

			// $basePath is built from a type/uri/key that are already each individually
			// validated by the caller, but never trust a path past this point regardless
			if( str_contains( $basePath, '..' ) === true || str_starts_with( $basePath, '/' ) === true )
				return false;

			[ $info ] = self::_inspect( $bytes );
			if( $info === null )
				return false;

			$orientation = $info[2] === IMAGETYPE_JPEG ? self::_jpegOrientation( $bytes ) : 1;

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
					'source'		=> [ 'width' => $info[0], 'height' => $info[1], 'type' => $info[2], 'orientation' => $orientation ],
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

			// What the picture looks like once it is upright: the geometry
			// below is worked out on that, and only the small target canvas is
			// turned in the end. Turning the source itself would hold a second
			// buffer of it, and MAX_SOURCE_PIXELS is what keeps one inside
			// the memory limit
			$turned				= $orientation >= 5;
			$shownWidth		= $turned ? $sourceHeight : $sourceWidth;
			$shownHeight	= $turned ? $sourceWidth : $sourceHeight;

			if( $mode === 'fit' ) {

				// The whole picture into the box, never past its own size: a
				// 400px source blown up to a 1600px box is four times the bytes
				// for the same picture, badly
				$scale = min( $width / $shownWidth, $height / $shownHeight, 1 );

				$cropWidth		= $shownWidth;
				$cropHeight		= $shownHeight;
				$targetWidth	= max( 1, (int) round( $shownWidth * $scale ) );
				$targetHeight	= max( 1, (int) round( $shownHeight * $scale ) );

			} else {

				// Centered crop: the largest rectangle matching the target aspect ratio
				// that fits inside the picture as shown, centered, then resized down/up onto it
				$targetWidth	= $width;
				$targetHeight	= $height;
				$targetRatio	= $targetWidth / $targetHeight;
				$shownRatio		= $shownWidth / $shownHeight;

				if( $shownRatio > $targetRatio ) {
					$cropHeight	= $shownHeight;
					$cropWidth	= (int) round( $shownHeight * $targetRatio );
				} else {
					$cropWidth	= $shownWidth;
					$cropHeight	= (int) round( $shownWidth / $targetRatio );
				}
			}

			// The same rectangle in the stored pixels: its sides swapped for
			// an orientation that turns the picture, and still the centre -
			// which is the one point every flip and rotation leaves where it is
			$readWidth	= $turned ? $cropHeight : $cropWidth;
			$readHeight	= $turned ? $cropWidth : $cropHeight;
			$cropX			= (int) round( ( $sourceWidth - $readWidth ) / 2 );
			$cropY			= (int) round( ( $sourceHeight - $readHeight ) / 2 );

			// The canvas is drawn the way the pixels are stored and turned
			// upright afterwards, so for 5-8 it is the target on its side
			$canvasWidth	= $turned ? $targetHeight : $targetWidth;
			$canvasHeight	= $turned ? $targetWidth : $targetHeight;

			/*	Alpha-aware output: png (with transparency preserved) for a source
				that carries it, jpeg otherwise - keeps photos small, logos crisp.

				For png and gif the source format is also a fair guess at the
				content: what arrives as one is usually line art, where jpeg would
				soften exactly the edges that matter. webp is the other way round -
				it is what phones and export tools write for photographs - and
				guessing from the format cost it a factor of twelve. A 1600x1000
				photograph, measured on this gd: 3185 KB as png against 264 KB as
				jpeg, for every derived size and every visitor. Its container says
				outright whether there is an alpha channel, so that is read rather
				than assumed.	*/
			$keepAlpha = in_array( $info[2], [ IMAGETYPE_PNG, IMAGETYPE_GIF ], true )
				|| ( $info[2] === IMAGETYPE_WEBP && self::_webpHasAlpha( $bytes ) === true );

			$canvas = @imagecreatetruecolor( $canvasWidth, $canvasHeight );

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

			imagecopyresampled( $canvas, $source, 0, 0, $cropX, $cropY, $canvasWidth, $canvasHeight, $readWidth, $readHeight );
			imagedestroy( $source );

			if( $orientation > 1 ) {
				$canvas = self::_orient( $canvas, $orientation );
				if( $canvas === false )
					return false;
			}

			/*	webp replaces both formats rather than the decision between them.
				The branch above stays exactly as it was - it is a good guess at
				the content, and webp is simply a better container for either
				answer. Measured on this gd, 1600x1000:

				  a photograph    png 2930 KB | jpeg  199 KB | webp lossy     151 KB
				  line art        png   24 KB | jpeg  242 KB | webp lossless    1 KB

				So lossless where png would have been - no quality lost at all,
				and the alpha channel comes with it - and lossy where jpeg would
				have been. Not lossy for both: on line art it is 67 KB here, worse
				than png and soft into the bargain, which is the whole reason that
				branch exists.

				A gd without webp, or a project that switched it off, writes the
				two formats it always did.	*/
			$webp = ( $appData['/nino/images/webp'] ?? true ) === true
				&& function_exists( 'imagewebp' ) === true
				&& ( imagetypes() & IMG_WEBP ) !== 0;

			ob_start();
			if( $webp === true ) {
				imagewebp( $canvas, null, $keepAlpha === true ? IMG_WEBP_LOSSLESS : 82 );
			} elseif( $keepAlpha === true ) {
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

			$filename = $basePath. $suffix. ( $webp === true ? '.webp' : ( $keepAlpha ? '.png' : '.jpg' ) );

			if( \Nino\Filesystem::putFileContent( $appData, self::UPLOAD_DIR. '/'. $filename, $encoded ) === false )
				return false;

			return $filename;
		}

		/**
		 *	Turn a canvas drawn the way a JPEG is stored upright, for its EXIF
		 *	orientation: 2 mirrored, 3 upside down, 4 mirrored vertically, 5-8
		 *	on their side (5 and 7 mirrored on top of it). imagerotate() counts
		 *	counter-clockwise, so the 90 degrees clockwise a 6 asks for is -90.
		 *	Only ever a JPEG, which has no alpha channel to keep
		 *
		 *	@param		\GdImage	$canvas				Consumed: destroyed where it is replaced
		 *	@param		int				$orientation	2-8
		 *
		 *	@return 	\GdImage|false
		 */
		private static function _orient( \GdImage $canvas, int $orientation ): \GdImage|false {

			$angle = match( $orientation ) { 3 => 180, 5, 6, 7 => -90, 8 => 90, default => 0 };
			$flip  = match( $orientation ) { 2, 5 => IMG_FLIP_HORIZONTAL, 4, 7 => IMG_FLIP_VERTICAL, default => 0 };

			if( $angle !== 0 ) {
				$rotated = imagerotate( $canvas, $angle, 0 );
				imagedestroy( $canvas );
				if( $rotated === false )
					return false;
				$canvas = $rotated;
			}

			if( $flip !== 0 )
				imageflip( $canvas, $flip );

			return $canvas;
		}

		/**
		 *	Whether a webp carries an alpha channel, read off its container.
		 *
		 *	A webp is a RIFF file whose first chunk names the bitstream. 'VP8 '
		 *	is the simple lossy one and has no alpha at all - the overwhelmingly
		 *	common case for a photograph. 'VP8L' is lossless and carries
		 *	alpha_is_used as bit 4 of the byte that follows its 14+14 bit
		 *	dimensions. 'VP8X' is the extended container, whose flags byte comes
		 *	first and marks alpha with the same bit.
		 *
		 *	Anything this does not recognize - a truncated upload, a chunk order
		 *	a future encoder writes - is answered with true: that costs bytes,
		 *	while a wrong false would flatten a transparent logo onto black.
		 *
		 *	@param		string		$bytes				The validated source
		 *
		 *	@return 	bool
		 */
		private static function _webpHasAlpha( string $bytes ): bool {

			if( strlen( $bytes ) < 25 || str_starts_with( $bytes, 'RIFF' ) === false || substr( $bytes, 8, 4 ) !== 'WEBP' )
				return true;

			return match( substr( $bytes, 12, 4 ) ) {
				'VP8 '	=> false,
				'VP8L'	=> ord( $bytes[20] ) !== 0x2F || ( ord( $bytes[24] ) & 0x10 ) !== 0,
				'VP8X'	=> ( ord( $bytes[20] ) & 0x10 ) !== 0,
				default	=> true,
			};
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

			// process() and fit() only ever hand out names they built below our
			// own upload dir - "<basePath>.<W>x<H>" or "<basePath>.fit<W>x<H>",
			// then ".webp", or ".png"/".jpg" with webp off (eg.
			// "elements/<type>/<uri>.800x600.webp") - and a RENDER handler a path
			// below it too; but a stored value could in theory have been
			// hand-edited, so never trust it blindly
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

		// Every developer-fixed image slot ("/nino/html/images" in config.php).
		// The set and the values are two panes, the same split Element Types and
		// Elements have: the Image Slots tab creates a slot, edits its label and
		// its target size and deletes it (see \Nino\Modules\Images\Slots), and
		// the Images panel only changes which file each one points at
		public static function getSlots( array &$appData ): array {
			return $appData['/nino/html/images'] ?? [];
		}

		public static function getSlot( array &$appData, string $uri ): array|false {
			return self::getSlots( $appData )[$uri] ?? false;
		}

		// Record a new filename for an existing slot - null for no image at all -
		// and persist it with a mutation of config.php that changes this one
		// entry and nothing else, like setSlotAlt(): writeContentData() would
		// replace the whole /nino/html/images key with this request's copy of
		// it, and an upload or a removal that finishes after an alt text was
		// saved would put the old alt texts back. True only where the record
		// was written: an unknown slot (here or in config.php) and a config.php
		// that could not be written are both false, and both leave $appData as
		// it was, so that the caller can leave the file the old record still
		// names alone
		public static function setSlotFilename( array &$appData, string $uri, ?string $filename ): bool {

			if( self::getSlot( $appData, $uri ) === false )
				return false;

			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( mixed $content ) use ( $uri, $filename ): ?array {

				if( is_array( $content ) === false || isset( $content['/nino/html/images'][$uri] ) === false )
					return null;

				$content['/nino/html/images'][$uri]['filename'] = $filename;

				return $content;
			} );

			if( $written === false )
				return false;

			$appData['/nino/html/images'][$uri]['filename'] = $filename;

			return true;
		}

		/**
		 *	One alt text as it is stored: control characters (a line break
		 *	among them) become spaces and the ends are trimmed - an alt text
		 *	is one short line of plain text, and it ends up in an attribute.
		 *	The same cleaning setSlotAlt() applies, public so that a panel can
		 *	measure what would be stored rather than what was typed
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string|false							The cleaned text, or false for bytes that are not valid UTF-8
		 */
		public static function cleanAlt( string $value ): string|false {

			if( preg_match( '//u', $value ) !== 1 )
				return false;

			return trim( (string) preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $value ) );
		}

		/**
		 *	Set the alt text of a slot per language - [ 'de_DE' => 'Ein Haus' ].
		 *	Merged per posted language: an empty value removes that language's
		 *	entry (an empty alt means decorative - the shortcode then falls
		 *	back to the template's own alt, or to none), and a language that
		 *	is not posted stays as it is. Only available languages and strings
		 *	of valid UTF-8 are taken; anything else refuses the whole call,
		 *	unchanged.
		 *
		 *	Persisted with a mutation of config.php that changes this one entry
		 *	and nothing else, instead of writeContentData() like the other
		 *	slot writers: that one replaces the whole /nino/html/images key with
		 *	this request's copy of it, and an alt text saved while an upload
		 *	finishes would take one of the two with it. The slot has to be in
		 *	config.php, or there is nothing to change
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$uri					The slot
		 *	@param		array			$alt					Language => text
		 *
		 *	@return 	bool
		 */
		public static function setSlotAlt( array &$appData, string $uri, array $alt ): bool {

			if( self::getSlot( $appData, $uri ) === false )
				return false;

			$clean = [];
			foreach( $alt as $locale => $value ) {

				if( is_string( $locale ) === false || is_string( $value ) === false || \Nino\Locales::verifyLocale( $appData, $locale ) === false )
					return false;

				$text = self::cleanAlt( $value );
				if( $text === false )
					return false;

				$clean[$locale] = $text;
			}

			$stored = [];
			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( mixed $content ) use ( $uri, $clean, &$stored ): ?array {

				if( is_array( $content ) === false || isset( $content['/nino/html/images'][$uri] ) === false )
					return null;

				$current = $content['/nino/html/images'][$uri]['alt'] ?? [];
				$merged = is_array( $current ) === true ? $current : [];

				foreach( $clean as $locale => $text )
					if( $text === '' )
						unset( $merged[$locale] );
					else
						$merged[$locale] = $text;

				if( $merged === [] )
					unset( $content['/nino/html/images'][$uri]['alt'] );
				else
					$content['/nino/html/images'][$uri]['alt'] = $merged;

				$stored = $merged;

				return $content;
			} );

			if( $written === false )
				return false;

			if( $stored === [] )
				unset( $appData['/nino/html/images'][$uri]['alt'] );
			else
				$appData['/nino/html/images'][$uri]['alt'] = $stored;

			return true;
		}
	}
}
