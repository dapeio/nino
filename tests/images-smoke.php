<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	images-smoke.php		Dependency-free smoke test for \Nino\Images: process() and the
 *											formats it reads, limits() and reject() - what an upload form tells
 *											and what the kernel refuses -, fit(), the orientation a camera
 *											recorded (with the images the checks make themselves), delete(), and
 *											the slots of an image: getSlots(), getSlot(), setSlotFilename().
 *											Runs against an isolated sandbox directory, never touches the real
 *											project data.
 *
 *	Usage: php tests/images-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'images' );
$sandbox = ninoSandboxDir( $appData );

$appData['/nino/dir'] = '';

echo "Sandbox: $sandbox\n\n";


// --- Images ------------------------------------------------------------

echo "Images::process / delete\n";

/*	Everything down to the webp-output block runs with '/nino/images/webp'
	off, so the png/jpeg fallback - what a gd without webp writes, and what a
	project that switched it off gets - keeps being exercised in full. The
	alpha reader is what picks between those two, so it is visible here rather
	than hidden behind one container that fits both	*/
$appData['/nino/images/webp'] = false;

/**
 *	Build raw jpeg/png bytes for a solid-color test image
 *
 *	@param		int				$width
 *	@param		int				$height
 *	@param		bool			$alpha				Encode as png with a transparent pixel, instead of jpeg
 *
 *	@return		string
 */
function makeTestImage( int $width, int $height, bool $alpha = false ): string {
	$img = imagecreatetruecolor( $width, $height );
	if( $alpha === true ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 200, 0, 64 ) );
	} else {
		imagefill( $img, 0, 0, imagecolorallocate( $img, 200, 0, 0 ) );
	}
	ob_start();
	$alpha === true ? imagepng( $img ) : imagejpeg( $img, null, 90 );
	$bytes = ob_get_clean();
	imagedestroy( $img );
	return $bytes;
}

// A wide (400x200, 2:1) source cropped to a square (100x100) target must center-crop
// horizontally rather than distort - the output must be exactly the target size either way
$wideSource = makeTestImage( 400, 200 );
$squareFilename = \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/item1' );
check( 'process() returns the deterministic filename (basePath + dimensions + extension)', $squareFilename === 'elements/demo/item1.100x100.jpg' );

$squarePath = \Nino\Filesystem::path( $appData, '/images/'. ( $squareFilename ?: '' ) );
check( 'process() writes the file to disk, creating parent dirs as needed', is_file( $squarePath ) === true );

[ $outWidth, $outHeight ] = getimagesize( $squarePath );
check( 'process() resizes to exactly the target dimensions', $outWidth === 100 && $outHeight === 100 );

check( 'process() outputs jpeg for a source with no transparency', str_ends_with( $squareFilename, '.jpg' ) === true );

// Progressive jpeg (SOF2, 0xFFC2) rather than baseline (SOF0, 0xFFC0) - smaller
// perceived load time, same content
$squareBytes = file_get_contents( $squarePath );
check( 'process() encodes jpeg output as progressive', strpos( $squareBytes, "\xFF\xC2" ) !== false && strpos( $squareBytes, "\xFF\xC0" ) === false );

// Re-uploading to the same basePath overwrites in place, not a new file
$replacementSource = makeTestImage( 400, 200 );
$secondFilename = \Nino\Images::process( $appData, $replacementSource, 100, 100, 'elements/demo/item1' );
check( 'process() overwrites the same deterministic path on a repeat upload, not a new file', $secondFilename === $squareFilename );

\Nino\Images::delete( $appData, $squareFilename );
check( 'delete() removes the file', is_file( $squarePath ) === false );

// A png or gif is re-encoded as png: the format is the guess at the content
// too, and what arrives as one is usually line art that jpeg would soften
$alphaSource = makeTestImage( 100, 100, true );
$alphaFilename = \Nino\Images::process( $appData, $alphaSource, 50, 50, 'elements/demo/item2' );
check( 'process() outputs png for a source that may have transparency', $alphaFilename === 'elements/demo/item2.50x50.png' );
\Nino\Images::delete( $appData, $alphaFilename );

/*	webp is the other way round: it is what phones and export tools write for
	photographs, and answering it with png cost a factor of twelve on every
	derived size. Its container says whether there is an alpha channel - 'VP8 '
	is the simple lossy chunk and never has one, 'VP8L' and 'VP8X' carry a flag -
	so that is read rather than guessed. Real encoder output here, not a
	hand-built header	*/
function makeTestWebp( int $width, int $height, bool $alpha, bool $lossless = false ): string {
	$img = imagecreatetruecolor( $width, $height );
	if( $alpha === true ) {
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		imagefill( $img, 0, 0, imagecolorallocatealpha( $img, 0, 200, 0, 64 ) );
	} else {
		imagefill( $img, 0, 0, imagecolorallocate( $img, 200, 120, 60 ) );
	}
	ob_start();
	imagewebp( $img, null, $lossless === true ? IMG_WEBP_LOSSLESS : 80 );
	$bytes = ob_get_clean();
	imagedestroy( $img );
	return $bytes;
}
if( function_exists( 'imagewebp' ) === true && ( imagetypes() & IMG_WEBP ) !== 0 ) {

	$webpChunks = [];
	foreach( [ 'lossy opaque' => [ false, false ], 'lossy alpha' => [ true, false ], 'lossless opaque' => [ false, true ], 'lossless alpha' => [ true, true ] ] as $webpCase => $webpHow )
		$webpChunks[$webpCase] = substr( makeTestWebp( 60, 40, $webpHow[0], $webpHow[1] ), 12, 4 );
	check( 'the webp fixtures really are the three container shapes the reader knows - '. json_encode( $webpChunks ),
	( $webpChunks['lossy opaque'] ?? '' ) === 'VP8 ' && ( $webpChunks['lossy alpha'] ?? '' ) === 'VP8X'
	&& in_array( $webpChunks['lossless opaque'] ?? '', [ 'VP8L', 'VP8X' ], true ) === true
	&& in_array( $webpChunks['lossless alpha'] ?? '', [ 'VP8L', 'VP8X' ], true ) === true );

	$webpOpaque = \Nino\Images::process( $appData, makeTestWebp( 200, 200, false ), 60, 60, 'elements/demo/webp-opaque' );
	check( 'an opaque webp is answered with jpeg, not png', $webpOpaque === 'elements/demo/webp-opaque.60x60.jpg' );
	\Nino\Images::delete( $appData, (string) $webpOpaque );

	$webpAlpha = \Nino\Images::process( $appData, makeTestWebp( 200, 200, true ), 60, 60, 'elements/demo/webp-alpha' );
	check( '...and a webp that carries alpha still gets png, so the channel survives', $webpAlpha === 'elements/demo/webp-alpha.60x60.png' );
	\Nino\Images::delete( $appData, (string) $webpAlpha );

	$webpLossless = \Nino\Images::process( $appData, makeTestWebp( 200, 200, true, true ), 60, 60, 'elements/demo/webp-lossless' );
	check( '...lossless webp too, whose flag sits behind its dimensions', $webpLossless === 'elements/demo/webp-lossless.60x60.png' );
	\Nino\Images::delete( $appData, (string) $webpLossless );

	// Anything the reader does not recognize costs bytes rather than a channel:
	// a truncated or foreign container is answered as if it had alpha
	$webpTruncated = \Nino\Images::process( $appData, substr( makeTestWebp( 200, 200, false ), 0, 20 ), 60, 60, 'elements/demo/webp-broken' );
	check( '...and bytes that are no longer a decodable image are refused outright', $webpTruncated === false );
} else {
	check( 'this php has no webp support, so the container reader is not exercised here', true );
}

/*	And with it on, which is the default: webp replaces both formats rather
	than the decision between them. The branch stays, the container changes -
	lossless where png would have been (no quality lost, alpha carried along),
	lossy where jpeg would have been. The written file says which: a lossless
	webp is a 'VP8L' chunk, a lossy one 'VP8 ' or 'VP8X'	*/
if( function_exists( 'imagewebp' ) === true && ( imagetypes() & IMG_WEBP ) !== 0 ) {

	$appData['/nino/images/webp'] = true;
	$webpChunkOf = static function( string $filename ) use ( &$appData ): string {
		return substr( (string) @file_get_contents( \Nino\Filesystem::path( $appData, '/images/'. $filename ) ), 12, 4 );
	};

	$outPhoto = \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/out-photo' );
	check( 'with webp on, a source that would have been jpeg is written as webp', $outPhoto === 'elements/demo/out-photo.100x100.webp' );
	check( '...lossy, the way jpeg was', in_array( $webpChunkOf( (string) $outPhoto ), [ 'VP8 ', 'VP8X' ], true ) === true );
	\Nino\Images::delete( $appData, (string) $outPhoto );

	$outArt = \Nino\Images::process( $appData, makeTestImage( 100, 100, true ), 50, 50, 'elements/demo/out-art' );
	check( '...and one that would have been png is webp too', $outArt === 'elements/demo/out-art.50x50.webp' );
	check( '...lossless, so the edges png was chosen for stay sharp and the alpha channel survives', $webpChunkOf( (string) $outArt ) === 'VP8L' );
	\Nino\Images::delete( $appData, (string) $outArt );

	$appData['/nino/images/webp'] = false;
	$outOff = \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/out-off' );
	check( '...and switching it off in config.php brings jpeg back, name and all', $outOff === 'elements/demo/out-off.100x100.jpg' );
	\Nino\Images::delete( $appData, (string) $outOff );

} else {
	check( 'this php writes no webp, so the kernel keeps png and jpeg by itself', \Nino\Images::process( $appData, $wideSource, 100, 100, 'elements/demo/out-none' ) === 'elements/demo/out-none.100x100.jpg' );
	\Nino\Images::delete( $appData, 'elements/demo/out-none.100x100.jpg' );
}

check( 'process() rejects bytes that are not a valid image', \Nino\Images::process( $appData, 'not an image', 100, 100, 'elements/demo/item3' ) === false );
check( 'process() rejects an empty target dimension', \Nino\Images::process( $appData, $wideSource, 0, 100, 'elements/demo/item3' ) === false );
check( 'process() rejects a path-traversal basePath', \Nino\Images::process( $appData, $wideSource, 100, 100, '../escape' ) === false );

echo "\n";


// --- Images::limits / reject - what an upload form tells, what the kernel refuses

echo "Images::limits / Images::reject\n";

/*	php's two limits are ini strings, so what limits() reads is stood in for by
	_limits()'s arguments here - upload_max_filesize and post_max_size cannot be changed
	at run time. The smaller of the two is what can arrive, the kernel's own
	cap is the other bound, and a value php cannot read counts as no limit
	instead of raising the warning this framework would treat as fatal	*/
// - a handler is called under @ too, so the harness's handler asks error_reporting() the way Runtime::handleError() does
ninoWarnings();

$limits = \Nino\Images::_limits( '2M', '8M' );
check( 'limits() takes the smaller of php\'s two limits for what can arrive', $limits['php'] === 2 * 1024 * 1024 && $limits['post'] === 8 * 1024 * 1024 && $limits['bytes'] === 2 * 1024 * 1024 );
$limits = \Nino\Images::_limits( '64M', '16M' );
check( '...and the kernel\'s own cap when that is smaller still', $limits['php'] === 16 * 1024 * 1024 && $limits['kernel'] === 8 * 1024 * 1024 && $limits['bytes'] === 8 * 1024 * 1024 );
$limits = \Nino\Images::_limits( '512K', '0' );
check( 'post_max_size = 0 means no limit - the upload limit alone applies', $limits['php'] === 512 * 1024 && $limits['post'] === 0 && $limits['bytes'] === 512 * 1024 );
$limits = \Nino\Images::_limits( '0', '0' );
check( 'no php limit at all leaves the kernel\'s cap', $limits['php'] === 0 && $limits['bytes'] === \Nino\Images::MAX_UPLOAD_BYTES );
$limits = \Nino\Images::_limits( 'plenty', '-1' );
check( 'a value php cannot read, or a negative one, counts as no limit', $limits['php'] === 0 && $limits['post'] === 0 && $limits['bytes'] === \Nino\Images::MAX_UPLOAD_BYTES );
$limits = \Nino\Images::_limits( '1G', '2G' );
check( 'the pixel cap is the kernel\'s whatever php says', $limits['pixels'] === \Nino\Images::MAX_SOURCE_PIXELS && $limits['bytes'] === \Nino\Images::MAX_UPLOAD_BYTES );
$limits = \Nino\Images::limits();
check( 'limits() reads the running php\'s own', $limits['kernel'] === 8 * 1024 * 1024 && $limits['bytes'] > 0 && $limits['bytes'] <= $limits['kernel'] );
$limitWarnings = ninoWarnings();
check( '...and none of it raised a warning, malformed values included', $limitWarnings === [] );

check( 'reject() lets an image the kernel takes through', \Nino\Images::reject( makeTestImage( 40, 30 ) ) === null );
check( 'reject() names bytes above the cap, with the limit in MB', \Nino\Images::reject( str_repeat( 'a', \Nino\Images::MAX_UPLOAD_BYTES + 1 ) ) === [ 'code' => 'image_too_large', 'params' => [ 8 ] ] );
check( 'reject() names something that is no image', \Nino\Images::reject( 'not an image' ) === [ 'code' => 'image_type', 'params' => [] ] && \Nino\Images::reject( '' )['code'] === 'image_type' );
$ihdr = pack( 'NN', 5000, 5000 ). "\x08\x02\x00\x00\x00";
$hugePng = "\x89PNG\r\n\x1a\n". pack( 'N', 13 ). 'IHDR'. $ihdr. pack( 'N', crc32( 'IHDR'. $ihdr ) );
check( 'reject() names a picture of more pixels than the kernel decodes, with the megapixels', \Nino\Images::reject( $hugePng ) === [ 'code' => 'image_too_many_pixels', 'params' => [ 20 ] ] );
check( '_render() refuses the same three through process() - one implementation of the limits', \Nino\Images::process( $appData, str_repeat( 'a', \Nino\Images::MAX_UPLOAD_BYTES + 1 ), 10, 10, 'elements/demo/limit' ) === false
	&& \Nino\Images::process( $appData, 'not an image', 10, 10, 'elements/demo/limit' ) === false && \Nino\Images::process( $appData, $hugePng, 10, 10, 'elements/demo/limit' ) === false );

// Http::fail() - a code, params and a field only when they are given
$failRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Http::fail( $failRequest, 400, 'invalid' );
check( 'Http::fail() with three arguments answers the body it always did', $failRequest['/nino/http/response'] === [ 'statusCode' => 400, 'body' => [ 'error' => 'invalid' ] ] );
\Nino\Http::fail( $failRequest, 409, 'taken', 'slug_taken' );
check( '...a code is added when given', $failRequest['/nino/http/response']['body'] === [ 'error' => 'taken', 'code' => 'slug_taken' ] );
\Nino\Http::fail( $failRequest, 400, 'range', 'int_range', [ 1, 'x' => 100 ], 'maxtries' );
check( '...and params as a list, and the field', $failRequest['/nino/http/response']['body'] === [ 'error' => 'range', 'code' => 'int_range', 'params' => [ 1, 100 ], 'field' => 'maxtries' ] );

echo "\n";


// --- Images::fit - the whole picture, in a box -----------------------------

echo "Images::fit / the render callback\n";

$fitFilename = \Nino\Images::fit( $appData, $wideSource, 100, 100, 'gallery/demo/one' );
check( 'fit() names the box rather than the result, so the path stays predictable per slot', $fitFilename === 'gallery/demo/one.fit100x100.jpg' );

[ $fitWidth, $fitHeight ] = getimagesize( \Nino\Filesystem::path( $appData, '/images/'. ( $fitFilename ?: '' ) ) );
check( 'the whole 400x200 picture is in the 100x100 box, its own proportions kept - nothing cropped', $fitWidth === 100 && $fitHeight === 50 );

$tallFilename = \Nino\Images::fit( $appData, makeTestImage( 200, 400 ), 100, 100, 'gallery/demo/two' );
[ $tallWidth, $tallHeight ] = getimagesize( \Nino\Filesystem::path( $appData, '/images/'. ( $tallFilename ?: '' ) ) );
check( '...whichever edge is the long one', $tallWidth === 50 && $tallHeight === 100 );

$smallFilename = \Nino\Images::fit( $appData, makeTestImage( 40, 30 ), 800, 800, 'gallery/demo/three' );
[ $smallWidth, $smallHeight ] = getimagesize( \Nino\Filesystem::path( $appData, '/images/'. ( $smallFilename ?: '' ) ) );
check( 'a source smaller than the box is stored as it is - four times the bytes for the same picture is not an improvement', $smallWidth === 40 && $smallHeight === 30 );

check( 'fit() refuses what process() refuses', \Nino\Images::fit( $appData, 'not an image', 100, 100, 'gallery/demo/four' ) === false
	&& \Nino\Images::fit( $appData, $wideSource, 0, 100, 'gallery/demo/four' ) === false
	&& \Nino\Images::fit( $appData, $wideSource, 100, 100, '../escape' ) === false );

// The seam a richer uploader hooks into: past the checks, before the
// encoding. A handler that renders the image itself says so with a filename
$rendered = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ) use ( &$rendered ): void {
	$rendered[] = $image['mode']. ' '. $image['width']. 'x'. $image['height']. ' from '. $image['source']['width']. 'x'. $image['source']['height'];
	$image['filename'] = $image['basePath']. '.handled.webp';
} );

check( 'a handler renders instead of gd, and gets the mode, the box and the source it was given', \Nino\Images::process( $appData, $wideSource, 64, 64, 'gallery/demo/hooked' ) === 'gallery/demo/hooked.handled.webp'
	&& \Nino\Images::fit( $appData, $wideSource, 64, 64, 'gallery/demo/hooked' ) === 'gallery/demo/hooked.handled.webp'
	&& $rendered === [ 'crop 64x64 from 400x200', 'fit 64x64 from 400x200' ] );
check( '...and nothing was written by the kernel for either', \Nino\Filesystem::fileExists( $appData, '/images/gallery/demo/hooked.64x64.jpg' ) === false
	&& \Nino\Filesystem::fileExists( $appData, '/images/gallery/demo/hooked.fit64x64.jpg' ) === false );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ): void {
	$image['filename'] = false;
} );
check( 'a handler that refuses the upload refuses it, and gd never runs', \Nino\Images::process( $appData, $wideSource, 64, 64, 'gallery/demo/refused' ) === false
	&& \Nino\Filesystem::fileExists( $appData, '/images/gallery/demo/refused.64x64.jpg' ) === false );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

// A handler is project code, not a reason to stop checking what comes back
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ): void {
	$image['filename'] = '../../escape.jpg';
} );
check( 'a filename that climbs out of the upload directory is not taken - gd renders it after all', \Nino\Images::process( $appData, $wideSource, 64, 64, 'gallery/demo/climb' ) === 'gallery/demo/climb.64x64.jpg' );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

// The checks are ahead of the callback on purpose: what keeps an upload
// endpoint safe is not something a feature switches off by registering
$reached = false;
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ) use ( &$reached ): void {
	$reached = true;
} );
check( 'a handler never sees bytes that are not a decodable image of a sane size', \Nino\Images::process( $appData, 'not an image', 64, 64, 'gallery/demo/bad' ) === false && $reached === false );

unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

echo "\n";


// --- Images - the orientation a camera recorded ------------------------

echo "Images::process / fit / size - EXIF orientation\n";

/**
 *	A 300x200 picture of four flat quadrants (red, green / blue, yellow),
 *	stored the way a camera that wrote this EXIF orientation stores it: the
 *	pixels are the inverse of the turn, so a reader that applies the
 *	orientation sees the upright picture again. The mapping is the table of
 *	the EXIF specification - which edge of the stored image is the visual top,
 *	left, right or bottom - and deliberately not gd's own rotate, which is
 *	what the kernel is checked against
 *
 *	@param		int				$orientation	1-8
 *
 *	@return		string										Plain jpeg bytes, no EXIF
 */
function makeQuadrantJpeg( int $orientation ): string {

	$displayWidth		= 300;
	$displayHeight	= 200;
	$turned					= $orientation >= 5;
	$storedWidth		= $turned ? $displayHeight : $displayWidth;
	$storedHeight		= $turned ? $displayWidth : $displayHeight;

	$img = imagecreatetruecolor( $storedWidth, $storedHeight );
	$colors = [
		'TL' => imagecolorallocate( $img, 220, 0, 0 ),
		'TR' => imagecolorallocate( $img, 0, 220, 0 ),
		'BL' => imagecolorallocate( $img, 0, 0, 220 ),
		'BR' => imagecolorallocate( $img, 220, 220, 0 ),
	];

	for( $row = 0; $row < $storedHeight; $row++ )
		for( $column = 0; $column < $storedWidth; $column++ ) {
			[ $x, $y ] = match( $orientation ) {
				1 => [ $column, $row ],
				2 => [ $displayWidth - 1 - $column, $row ],
				3 => [ $displayWidth - 1 - $column, $displayHeight - 1 - $row ],
				4 => [ $column, $displayHeight - 1 - $row ],
				5 => [ $row, $column ],
				6 => [ $displayWidth - 1 - $row, $column ],
				7 => [ $displayWidth - 1 - $row, $displayHeight - 1 - $column ],
				8 => [ $row, $displayHeight - 1 - $column ],
			};
			imagesetpixel( $img, $column, $row, $colors[ ( $y < $displayHeight / 2 ? 'T' : 'B' ). ( $x < $displayWidth / 2 ? 'L' : 'R' ) ] );
		}

	ob_start();
	imagejpeg( $img, null, 95 );
	$bytes = ob_get_clean();
	imagedestroy( $img );

	return $bytes;
}

/**
 *	The payload of an Exif APP1 segment - "Exif\0\0", a TIFF header and an
 *	IFD0 of two entries (an ASCII one first, so the reader has to walk past
 *	something before it finds the orientation)
 *
 *	@param		string		$order				'II' (little) or 'MM' (big endian)
 *	@param		int				$orientation
 *	@param		int				$type					The TIFF type of the orientation entry: 3 SHORT, 4 LONG
 *	@param		int				$ifdOffset		Where the header says IFD0 starts
 *
 *	@return		string
 */
function makeExifPayload( string $order, int $orientation, int $type = 3, int $ifdOffset = 8 ): string {

	$short	= $order === 'II' ? 'v' : 'n';
	$long		= $order === 'II' ? 'V' : 'N';
	$value	= $type === 3 ? pack( $short, $orientation ). "\0\0" : pack( $long, $orientation );

	$ifd = pack( $short, 2 )
		. pack( $short, 0x010E ). pack( $short, 2 ). pack( $long, 4 ). "abc\0"
		. pack( $short, 0x0112 ). pack( $short, $type ). pack( $long, 1 ). $value
		. pack( $long, 0 );

	return "Exif\0\0". $order. pack( $short, 42 ). pack( $long, $ifdOffset ). $ifd;
}

/**
 *	A jpeg with an APP1 segment (or several) put in right behind its start marker
 *
 *	@param		string		$jpeg
 *	@param		string		...$payloads	One segment payload each, in this order
 *
 *	@return		string
 */
function withApp1( string $jpeg, string ...$payloads ): string {

	$segments = '';
	foreach( $payloads as $payload )
		$segments .= "\xFF\xE1". pack( 'n', strlen( $payload ) + 2 ). $payload;

	return substr( $jpeg, 0, 2 ). $segments. substr( $jpeg, 2 );
}

/**
 *	Which quadrant colour a pixel is nearest to: R, G, B or Y
 *
 *	@param		string		$bytes				An image
 *	@param		float			$x						As a share of the width
 *	@param		float			$y						As a share of the height
 *
 *	@return		string
 */
function quadrantAt( string $bytes, float $x, float $y ): string {

	$img		= imagecreatefromstring( $bytes );
	$color	= imagecolorat( $img, (int) ( imagesx( $img ) * $x ), (int) ( imagesy( $img ) * $y ) );
	imagedestroy( $img );

	[ $red, $green, $blue ] = [ ( $color >> 16 ) & 255, ( $color >> 8 ) & 255, $color & 255 ];

	return $blue > 120 ? 'B' : ( $red > 120 && $green > 120 ? 'Y' : ( $red > 120 ? 'R' : 'G' ) );
}

/**
 *	The four quadrants of a stored image, top left to bottom right
 *
 *	@param		array			&$appData
 *	@param		string		$filename			Below /images/
 *
 *	@return		string
 */
function quadrantsOf( array &$appData, string $filename ): string {

	$bytes = (string) file_get_contents( \Nino\Filesystem::path( $appData, '/images/'. $filename ) );

	return quadrantAt( $bytes, 0.25, 0.25 ). quadrantAt( $bytes, 0.75, 0.25 ). quadrantAt( $bytes, 0.25, 0.75 ). quadrantAt( $bytes, 0.75, 0.75 );
}

$exifRight = true;
$exifWhy = '';
foreach( [ 'II', 'MM' ] as $order )
	foreach( range( 1, 8 ) as $orientation ) {

		$source = withApp1( makeQuadrantJpeg( $orientation ), makeExifPayload( $order, $orientation ) );
		$read		= \Nino\Images::size( $source );
		if( $read === false || $read['orientation'] !== $orientation || $read['width'] !== 300 || $read['height'] !== 200 ) {
			$exifRight = false;
			$exifWhy .= " size($order $orientation)";
		}

		// A wide target, a tall one (which crops the sides off the picture as
		// it is shown, not as it is stored) and a fit - each one has to come
		// out the size and with the quadrants of the upright picture
		$crops = [ [ 150, 100 ], [ 100, 150 ] ];
		foreach( $crops as [ $w, $h ] ) {
			$stored = \Nino\Images::process( $appData, $source, $w, $h, 'exif/crop-'. $order. $orientation. '-'. $w );
			$size		= $stored === false ? false : getimagesize( \Nino\Filesystem::path( $appData, '/images/'. $stored ) );
			if( $stored === false || $size[0] !== $w || $size[1] !== $h || quadrantsOf( $appData, $stored ) !== 'RGBY' ) {
				$exifRight = false;
				$exifWhy .= " process($order $orientation {$w}x{$h})";
			}
		}

		$stored = \Nino\Images::fit( $appData, $source, 120, 120, 'exif/fit-'. $order. $orientation );
		$size		= $stored === false ? false : getimagesize( \Nino\Filesystem::path( $appData, '/images/'. $stored ) );
		if( $stored === false || $size[0] !== 120 || $size[1] !== 80 || quadrantsOf( $appData, $stored ) !== 'RGBY' ) {
			$exifRight = false;
			$exifWhy .= " fit($order $orientation)";
		}
	}
check( 'every EXIF orientation 1-8, in both byte orders, comes out upright: process() to 150x100 and 100x150 and fit() to 120x120 keep the size and the quadrants of the upright picture'. $exifWhy, $exifRight === true );

$unturned = \Nino\Images::process( $appData, makeQuadrantJpeg( 6 ), 100, 150, 'exif/none' );
check( '...while the same stored pixels without an Exif block are not turned - it is the header that decides', $unturned !== false && quadrantsOf( $appData, $unturned ) !== 'RGBY' );

// What a header reader may and may not believe - and that it never raises
// a warning on the way, which the framework's error handler would make a 500
ninoWarnings();

$upright		= makeQuadrantJpeg( 1 );
$plainPng		= makeTestImage( 40, 30, true );
$xmp				= "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta/>";
$exif6			= makeExifPayload( 'MM', 6 );
$shortOf		= static fn( string $bytes ): int => \Nino\Images::size( $bytes )['orientation'] ?? 0;

check( 'size() of a png is its size and orientation 1', \Nino\Images::size( $plainPng ) === [ 'width' => 40, 'height' => 30, 'type' => IMAGETYPE_PNG, 'orientation' => 1 ] );
check( '...of a jpeg without an APP1 segment, orientation 1', $shortOf( $upright ) === 1 );
check( '...of a truncated Exif block, orientation 1', $shortOf( withApp1( $upright, substr( $exif6, 0, -16 ) ) ) === 1 );
check( '...of an IFD offset past the end of the segment, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 6, 3, 4000 ) ) ) === 1 );
check( '...of an IFD offset inside the header, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 6, 3, 4 ) ) ) === 1 );
check( '...of an orientation that is not a SHORT, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 6, 4 ) ) ) === 1 );
check( '...of an orientation value out of range, orientation 1', $shortOf( withApp1( $upright, makeExifPayload( 'II', 9 ) ) ) === 1 && $shortOf( withApp1( $upright, makeExifPayload( 'MM', 0 ) ) ) === 1 );
check( '...of a TIFF header that is neither II nor MM, or has no 42, orientation 1', $shortOf( withApp1( $upright, str_replace( 'MM', 'XX', $exif6 ) ) ) === 1
	&& $shortOf( withApp1( $upright, substr_replace( $exif6, "\x00\x2B", 8, 2 ) ) ) === 1 );
check( '...an XMP APP1 in front of the Exif one is skipped', $shortOf( withApp1( $upright, $xmp, $exif6 ) ) === 6 );
check( '...and an APP1 that is not Exif is not read as one', $shortOf( withApp1( $upright, $xmp ) ) === 1 );

$sizeSix		= \Nino\Images::size( withApp1( $upright, makeExifPayload( 'II', 6 ) ) );
$sizeEight	= \Nino\Images::size( withApp1( $upright, makeExifPayload( 'MM', 8 ) ) );
$sizeThree	= \Nino\Images::size( withApp1( $upright, makeExifPayload( 'MM', 3 ) ) );
check( 'size() swaps width and height for 6 and 8, and not for 3', $sizeSix['width'] === 200 && $sizeSix['height'] === 300 && $sizeEight['width'] === 200 && $sizeEight['height'] === 300
	&& $sizeThree['width'] === 300 && $sizeThree['height'] === 200 && $sizeThree['orientation'] === 3 );
check( 'size() answers false for what is not an image, or not one the kernel takes', \Nino\Images::size( 'not an image' ) === false && \Nino\Images::size( '' ) === false );

// Fill bytes in front of a marker are legal, and a header of endless
// segments is not followed to its end
$app1		= "\xFF\xE1". pack( 'n', strlen( $exif6 ) + 2 ). $exif6;
$behind	= static fn( string $segments ): string => substr( $upright, 0, 2 ). $segments. substr( $upright, 2 );
check( 'fill bytes in front of the Exif marker are skipped, and a header with 70 segments in front of it is not followed that far',
	$shortOf( $behind( "\xFF\xFF\xFF". $app1 ) ) === 6
	&& $shortOf( $behind( str_repeat( "\xFF\xE2\x00\x02", 70 ). $app1 ) ) === 1
	&& $shortOf( $behind( str_repeat( "\xFF\xE2\x00\x02", 10 ). $app1 ) ) === 6 );
$exifWarnings = ninoWarnings();
check( 'nothing above raised a php warning'. ( $exifWarnings === [] ? '' : ' - '. $exifWarnings[0] ), $exifWarnings === [] );

// A handler that renders the picture itself is told what the pixels need
$seenSource = null;
\Nino\Callbacks::registerCallback( $appData, \Nino\Images::RENDER, static function( array &$appData, array &$image ) use ( &$seenSource ): void {
	$seenSource = $image['source'];
	$image['filename'] = false;
} );
\Nino\Images::process( $appData, withApp1( makeQuadrantJpeg( 6 ), makeExifPayload( 'MM', 6 ) ), 64, 64, 'exif/hooked' );
check( 'a RENDER handler is told the orientation, while width and height stay the stored pixels', ( $seenSource['orientation'] ?? null ) === 6 && ( $seenSource['width'] ?? null ) === 200 && ( $seenSource['height'] ?? null ) === 300 );
\Nino\Images::process( $appData, makeTestImage( 40, 30, true ), 64, 64, 'exif/hooked' );
check( '...and 1 for an image that has none', ( $seenSource['orientation'] ?? null ) === 1 );
unset( $appData['./nino/callbacks'][ \Nino\Images::RENDER ] );

check( 'the header is read without ext-exif: Images.php calls no exif_ function', preg_match( '/\bexif_[a-z_]+\s*\(/', (string) file_get_contents( __DIR__. '/../_nino/Nino/Images/Images.php' ) ) === 0 );

echo "\n";


// --- Images::delete --------------------------------------------------------

echo "Images::delete - never outside its own directory\n";

// delete() must never escape its own upload dir, even given a maliciously crafted filename
\Nino\Filesystem::putFileContent( $appData, '/canary.txt', 'still here' );
\Nino\Images::delete( $appData, '../canary.txt' );
check( 'delete() refuses a path-traversal filename', \Nino\Filesystem::fileExists( $appData, '/canary.txt' ) === true );

echo "\n";


// --- Images::getSlots / getSlot / setSlotFilename -----------------------

echo "Images::getSlots / getSlot / setSlotFilename\n";

$appData['/nino/html/images'] = [
	'hero' => [ 'label' => 'Hero', 'width' => 1600, 'height' => 600, 'filename' => null ],
];
// The slots live in config.php: the writers below change what that holds, not this request's copy
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

check( 'getSlots returns every developer-fixed slot', array_keys( \Nino\Images::getSlots( $appData ) ) === [ 'hero' ] );
check( 'getSlot returns one slot\'s definition', ( \Nino\Images::getSlot( $appData, 'hero' )['label'] ?? null ) === 'Hero' );
check( 'getSlot returns false for an unknown uri', \Nino\Images::getSlot( $appData, 'nope' ) === false );

check( 'setSlotFilename rejects an unknown slot', \Nino\Images::setSlotFilename( $appData, 'nope', 'x.jpg' ) === false );
check( 'setSlotFilename accepts a known slot', \Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' ) === true );
check( 'setSlotFilename updates the in-memory slot immediately', \Nino\Images::getSlot( $appData, 'hero' )['filename'] === 'hero.1600x600.jpg' );

$persisted = include \Nino\Filesystem::path( $appData, '/config.php' );
check( 'setSlotFilename persists to config.php (same as Auth::updateUser)', ( $persisted['/nino/html/images']['hero']['filename'] ?? null ) === 'hero.1600x600.jpg' );

// null is "no image": the record is written, and a slot with none renders nothing
check( 'setSlotFilename( null ) takes the image out of the slot and persists that', \Nino\Images::setSlotFilename( $appData, 'hero', null ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['filename'] === null
	&& array_key_exists( 'filename', ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero'] ) === true
	&& ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['filename'] === null );
\Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' );

/*	A write that cannot happen answers false and leaves the record as it was -
	the caller decides about a file on the strength of that answer. The lock
	file's path is made a directory, the way a full disk or a missing right
	would stop it	*/
$configLock = $sandbox. '/private/data/.locks/'. sha1( '/config.php' ). '.lock';
@unlink( $configLock );
mkdir( $configLock, 0755, true );
check( 'setSlotFilename answers false where config.php cannot be written, and leaves the filename as it was', \Nino\Images::setSlotFilename( $appData, 'hero', null ) === false
	&& \Nino\Images::getSlot( $appData, 'hero' )['filename'] === 'hero.1600x600.jpg' );
rmdir( $configLock );
check( '...and writes again once it can', \Nino\Images::setSlotFilename( $appData, 'hero', null ) === true );
\Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' );

$appData['/nino/html/images']['logo'] = [ 'label' => 'Logo', 'width' => 400, 'height' => 400, 'filename' => null ];
\Nino\AppData::writeContentData( $appData, [ '/nino/html/images' ] );

\Nino\Modules\Components::init( $appData );
check( '[image] shortcode renders an <img> tag under the public prefix', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/public/images/hero.1600x600.jpg" width="1600" height="600"' ) === true );
check( '[image] shortcode renders nothing for a slot with no file uploaded yet', \Nino\Html::renderHtml( $appData, '[image logo]' ) === '' );
check( '[image] shortcode renders nothing for an unknown slot', \Nino\Html::renderHtml( $appData, '[image nope]' ) === '' );

// What a shortcode is handed. An empty value used to read as no value at all,
// so alt="" - the way a decorative picture is written, and the way AGENTS.md
// writes one - arrived as the positional argument 'alt' and the picture kept
// the slot's label as its alt text. A value that happens to equal its own
// name went the same way
$probeArgs = null;
\Nino\Html::addShortcode( $appData, 'argprobe', static function( array &$appData, array $args ) use ( &$probeArgs ): string {
	$probeArgs = $args;
	return '';
} );

\Nino\Html::renderHtml( $appData, '[argprobe /uri alt="" title="A title" name="name" bare]' );
check( 'an empty argument value is a value, not a name', array_key_exists( 'alt', $probeArgs ?? [] ) === true && ( $probeArgs['alt'] ?? null ) === '' );
check( '...a value that reads like its own name is one too', ( $probeArgs['name'] ?? null ) === 'name' );
check( '...and the ordinary two shapes are unchanged', ( $probeArgs['title'] ?? null ) === 'A title'
	&& in_array( '/uri', $probeArgs ?? [], true ) === true && in_array( 'bare', $probeArgs ?? [], true ) === true );

// The picture this is really about
check( 'a decorative picture is written with an empty alt and keeps it', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt=""]' ), 'alt=""' ) === true );

/*	The <img> is a named property, not a string the method builds - which is
	what makes it one place to read and something a project can replace. Proven
	by replacing it: a project that wants loading="lazy" on every picture sets
	the entry, and the shortcode renders through it	*/
$shippedImg = \Nino\Modules\Components::$html['img'];
\Nino\Modules\Components::$html['img'] = '<img loading="lazy" src="[[src]]" alt="[[alt]]" data-size="[[width]]x[[height]]">';
$replacedImg = \Nino\Html::renderHtml( $appData, '[image hero]' );
\Nino\Modules\Components::$html['img'] = $shippedImg;
check( 'the <img> fragment is the property, so replacing it replaces what the shortcode renders', $replacedImg === '<img loading="lazy" src="/public/images/hero.1600x600.jpg" alt="" data-size="1600x600">' );
check( '...and putting the shipped one back renders the shipped markup again', str_starts_with( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/public/images/hero.1600x600.jpg"' ) === true );

unset( $appData['./nino/html/shortcodes']['argprobe'], $appData['./nino/callbacks']['/nino/html/shortcode/argprobe'] );

// The alt text of a slot: the one stored for the language, then the template's
// own, then none - and never the slot's label, which is a name for the person
// who edits the slot and says nothing about the picture
check( '[image] with no alt anywhere is alt="" - the label of the slot is not an alt text', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), ' alt="">' ) === true );

check( 'setSlotAlt answers false for an unknown slot, a language the site does not have, a value that is no string and bytes that are not utf-8',
	\Nino\Images::setSlotAlt( $appData, 'nope', [ 'de_DE' => 'x' ] ) === false
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'xx_XX' => 'x' ] ) === false
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => [ 'x' ] ] ) === false
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => "caf\xE9" ] ) === false
	&& isset( \Nino\Images::getSlot( $appData, 'hero' )['alt'] ) === false );
check( 'setSlotAlt trims, and turns control characters - a line break among them - into spaces', \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => "  Ein\nHaus\t am See \x07 " ] ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['alt'] === [ 'de_DE' => 'Ein Haus  am See' ] );
check( '...and persists exactly that to config.php', ( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['alt'] ?? null ) === [ 'de_DE' => 'Ein Haus  am See' ] );
check( 'a language that is not posted keeps its text', \Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => 'A house' ] ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['alt'] === [ 'de_DE' => 'Ein Haus  am See', 'en_US' => 'A house' ] );
check( 'an empty value removes that language\'s entry, and the last one takes the key away', \Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => '' ] ) === true
	&& \Nino\Images::getSlot( $appData, 'hero' )['alt'] === [ 'en_US' => 'A house' ]
	&& \Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => '  ' ] ) === true
	&& isset( \Nino\Images::getSlot( $appData, 'hero' )['alt'] ) === false
	&& isset( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['alt'] ) === false );
check( 'cleanAlt() is the same cleaning, and false for what is no utf-8', \Nino\Images::cleanAlt( " a\r\nb " ) === 'a  b' && \Nino\Images::cleanAlt( "\xFF" ) === false );

// An alt text saved while the slot's record is written by someone else too: only
// its own entry is changed, so an upload that finished in between survives
$earlierRequest = $appData;
$appData['/nino/html/images']['hero']['filename'] = 'stale.jpg';
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => 'Haus' ] );
check( 'setSlotAlt changes the alt entry alone: what config.php holds for the rest of the slot stays, whatever this request believes', ( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['filename'] ?? null ) === 'hero.1600x600.jpg' );
$appData['/nino/html/images']['hero']['filename'] = 'hero.1600x600.jpg';

// ...and the other order: a request that booted before the alt text was saved - an
// upload or a removal that is slow - writes its filename afterwards. Only the filename
// is changed, so the alt text survives, and the request takes the filename as its own
check( 'setSlotFilename changes the filename alone: an alt text saved after this request booted stays, whatever this request believes', isset( $earlierRequest['/nino/html/images']['hero']['alt'] ) === false
	&& \Nino\Images::setSlotFilename( $earlierRequest, 'hero', 'later.1600x600.jpg' ) === true
	&& ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['alt'] === [ 'de_DE' => 'Haus' ]
	&& ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['hero']['filename'] === 'later.1600x600.jpg'
	&& $earlierRequest['/nino/html/images']['hero']['filename'] === 'later.1600x600.jpg' );
\Nino\Images::setSlotFilename( $appData, 'hero', 'hero.1600x600.jpg' );

// A slot this request still knows but config.php no longer holds (a developer deleted
// it in between) is not written back into it
$staleSlot = $appData;
$staleSlot['/nino/html/images']['ghost'] = [ 'label' => 'Ghost', 'width' => 10, 'height' => 10, 'filename' => null ];
check( 'setSlotFilename answers false for a slot config.php does not hold any more, writes nothing and leaves this request as it was', \Nino\Images::setSlotFilename( $staleSlot, 'ghost', 'ghost.10x10.jpg' ) === false
	&& $staleSlot['/nino/html/images']['ghost']['filename'] === null
	&& isset( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['ghost'] ) === false );

check( '[image] prefers the alt stored for the current language over the template\'s own', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt="tpl"]' ), ' alt="Haus">' ) === true );
$appData['./nino/locales/current'] = 'en_US';
check( '...and uses the template\'s where nothing is stored for that language', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt="tpl"]' ), ' alt="tpl">' ) === true );
check( '...and none, alt="", where there is neither', str_contains( \Nino\Html::renderHtml( $appData, '[image hero]' ), ' alt="">' ) === true );

// Shortcode output is rendered once more: a text an editor wrote must not open a fill or a shortcode there
\Nino\Html::addFills( $appData, [ '[[/k]]' => 'FILLED' ], '*' );
\Nino\Filesystem::putFileContent( $appData, '/templates/t.tpl', 'INCLUDED' );
\Nino\Modules\Template::init( $appData );
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => 'x [[/k]] [template /templates/t] "q" <b>' ] );
$hostileAlt = \Nino\Html::renderHtml( $appData, '[image hero]' );
check( 'a stored alt text is escaped and its brackets neutralised: neither the fill nor the template runs', str_contains( $hostileAlt, 'alt="x &#91;&#91;/k]] &#91;template /templates/t] &quot;q&quot; &lt;b&gt;"' ) === true
	&& str_contains( $hostileAlt, 'FILLED' ) === false && str_contains( $hostileAlt, 'INCLUDED' ) === false );
check( '...and it wins over the template\'s own alt text in that language', str_contains( \Nino\Html::renderHtml( $appData, '[image hero alt="y"]' ), 'alt="x &#91;' ) === true );
// Between the tags the content is what is rendered, and only for a slot that has a picture: where an address and not a <img> is wanted - a meta tag, a mail
check( '[image]...[/image] renders its content with [[src]] filled in, and an alt text stored for the slot is brackets-neutralised there too', \Nino\Html::renderHtml( $appData, '[image hero]<i data-alt="[[alt]]"></i>[/image]' ) === '<i data-alt="x &#91;&#91;/k]] &#91;template /templates/t] &quot;q&quot; &lt;b&gt;"></i>' );
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'en_US' => '' ] );
check( '[image]...[/image] fills [[src]], [[width]], [[height]] and [[alt]] - the alt from the template where nothing is stored', \Nino\Html::renderHtml( $appData, '[image hero alt="Haus"]<meta content="https://example.com[[src]]" data-size="[[width]]x[[height]]" data-alt="[[alt]]">[/image]' )
	=== '<meta content="https://example.com/public/images/hero.1600x600.jpg" data-size="1600x600" data-alt="Haus">' );
check( '...and renders nothing at all - no content, no empty tag - for a slot with no file or one that is not there', \Nino\Html::renderHtml( $appData, '[image logo]<meta content="[[src]]">[/image]' ) === '' && \Nino\Html::renderHtml( $appData, '[image nope]<meta content="[[src]]">[/image]' ) === '' );
check( '...and a bare [image] with no content is the <img> as before', str_starts_with( \Nino\Html::renderHtml( $appData, '[image hero]' ), '<img src="/public/images/hero.1600x600.jpg"' ) === true );
// The content of a shortcode runs up to the first closing tag, across a second opening of the same one: a bare [image] ahead of the content form has to close itself
check( '...a bare [image] ahead of the content form in one template is written [image x][/image], and then the two do not run into each other', \Nino\Html::renderHtml( $appData, '<p>[image hero alt=""][/image]</p><h1>T</h1>[image hero]<meta content="[[src]]">[/image]' )
	=== '<p><img src="/public/images/hero.1600x600.jpg" width="1600" height="600" alt=""></p><h1>T</h1><meta content="/public/images/hero.1600x600.jpg">' );
$appData['./nino/locales/current'] = 'de_DE';
\Nino\Images::setSlotAlt( $appData, 'hero', [ 'de_DE' => '' ] );
unset( $appData['./nino/html/shortcodes']['template'], $appData['./nino/callbacks']['/nino/html/shortcode/template'] );
$appData['./nino/html/cache'] = false;

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
