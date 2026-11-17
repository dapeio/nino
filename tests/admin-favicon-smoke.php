<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-favicon-smoke.php	Dependency-free smoke test for the Favicon tab of the Images panel
 *											(_admin/Nino/Modules/Images/Favicon): the tab and its permission, the
 *											set made from one picture (sizes, alpha, the touch icon's surface,
 *											the ico container, the manifest), the svg that is cleaned, the one
 *											copy kept for Undo, the base set put back, the fills a template
 *											reads the set through, and what is refused. Runs against an
 *											isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/admin-favicon-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'favicon' );
$sandbox = ninoSandboxDir( $appData );
mkdir( $sandbox. '/private/text', 0777, true );
file_put_contents( $sandbox. '/private/text/global.php', '<?php return [ \'[[/project/company/general/name]]\' => \'Acme\' ];' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$appData['/nino/dir']											= '';
$appData['/nino/install/completed']				= true;
$appData['/nino/auth/maxtries']						= 3;
$appData['/nino/auth/cooldown']						= 3600;
$appData['/nino/auth/user']								= [];
$appData['/nino/auth/roles']							= \Nino\Modules\Users\Roles::defaults( $appData );

// The set a new installation starts with, where the wizard puts it
$baseDir = dirname( __DIR__ ). '/_admin/install/library/base/favicon';
mkdir( $sandbox. '/public/favicon', 0777, true );
foreach( \Nino\Images::FAVICON_FILES as $name )
	copy( $baseDir. '/'. $name, $sandbox. '/public/favicon/'. $name );

ninoUser( $appData, 'admin@example.com' );

echo "Sandbox: $sandbox\n\n";


// --- helpers -----------------------------------------------------------------

/**
 *	A picture as png bytes: transparent, or a solid colour, with an opaque disc in the middle
 *
 *	@param		int				$width
 *	@param		int				$height
 *	@param		bool			$transparent		Transparent ground, else a solid green one
 *
 *	@return		string
 */
function faviconPicture( int $width, int $height, bool $transparent = true ): string {
	$image = imagecreatetruecolor( $width, $height );
	imagealphablending( $image, false );
	imagesavealpha( $image, true );
	imagefill( $image, 0, 0, $transparent === true ? imagecolorallocatealpha( $image, 0, 0, 0, 127 ) : imagecolorallocate( $image, 0, 160, 0 ) );
	imagefilledellipse( $image, intdiv( $width, 2 ), intdiv( $height, 2 ), (int) ( min( $width, $height ) * 0.6 ), (int) ( min( $width, $height ) * 0.6 ), imagecolorallocate( $image, 220, 20, 20 ) );
	ob_start();
	imagepng( $image );
	return (string) ob_get_clean();
}

/**
 *	Post to a favicon action with the files given: name => bytes (or an upload error code)
 *
 *	@param		array 		&$appData
 *	@param		string		$action				eg. 'favicon/generate'
 *	@param		array 		$data
 *	@param		array 		$files				'file'/'svg' => bytes | int (an upload error)
 *	@param		int				$statusBefore
 *
 *	@return		array											[ status, body, request ]
 */
function callFavicon( array &$appData, string $action, array $data = [], array $files = [], int $statusBefore = 200 ): array {

	$_FILES = [];
	$temps	= [];
	foreach( $files as $field => $content ) {
		if( is_int( $content ) === true ) {
			$_FILES[$field] = [ 'tmp_name' => '', 'error' => $content, 'name' => 'x', 'size' => 0 ];
			continue;
		}
		$path = tempnam( sys_get_temp_dir(), 'nino-favicon-' );
		file_put_contents( $path, $content );
		$temps[] = $path;
		$_FILES[$field] = [ 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'my-secret-logo.'. ( $field === 'svg' ? 'svg' : 'png' ), 'size' => strlen( $content ) ];
	}

	$response = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], $data, $action, $statusBefore );

	foreach( $temps as $path )
		@unlink( $path );
	$_FILES = [];

	return $response;
}

/**
 *	The bytes of every file of the set (and the vector icon), null where it is not there
 *
 *	@param		string		$sandbox
 *
 *	@return		array
 */
function faviconSet( string $sandbox ): array {
	$set = [];
	foreach( array_merge( \Nino\Images::FAVICON_FILES, [ \Nino\Images::FAVICON_SVG ] ) as $name )
		$set[$name] = is_file( $sandbox. '/public/favicon/'. $name ) === true ? file_get_contents( $sandbox. '/public/favicon/'. $name ) : null;
	return $set;
}

/**
 *	The pixel of a png at a place: [ red, green, blue, alpha (0 opaque - 127 transparent) ]
 *
 *	@param		string		$png
 *	@param		int				$x
 *	@param		int				$y
 *
 *	@return		array
 */
function faviconPixel( string $png, int $x, int $y ): array {
	$image = imagecreatefromstring( $png );
	$color = imagecolorat( $image, $x, $y );
	return [ ( $color >> 16 ) & 0xFF, ( $color >> 8 ) & 0xFF, $color & 0xFF, ( $color >> 24 ) & 0x7F ];
}

/**
 *	The log lines of today
 *
 *	@param		string		$sandbox
 *
 *	@return		array
 */
function faviconLog( string $sandbox ): array {
	$path = $sandbox. '/private/.logs/'. date( 'Y-m-d' ). '.php';
	if( is_file( $path ) === false )
		return [];
	$prefix = "<?php http_response_code(403); exit; return '";
	$decoded = base64_decode( substr( (string) file_get_contents( $path ), strlen( $prefix ), -strlen( "';\n" ) ) );
	return $decoded === '' ? [] : explode( "\n", $decoded );
}

/**
 *	A svg an icon cannot be made of as it is: it carries a script beside its one path
 *
 *	@return		string
 */
function faviconDirtySvg(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><script>alert(1)</script><path d="M1 1 L15 15" stroke="red" onclick="alert(2)"/></svg>';
}


// --- the tab ------------------------------------------------------------------

echo "The tab\n";

$registry = \Nino\Admin\Admin::panels( $appData );
$tab = $registry['images']['tabs']['favicon'] ?? null;
check( 'Favicon is a tab of the Images pane', $tab !== null && $tab['class'] === \Nino\Modules\Images\Favicon::class );
check( '...with the permission of the Images panel', $tab !== null && $tab['perm'] === \Nino\Modules\Images\Admin::MANAGE_PERM && $registry['images']['perm'] === $tab['perm'] );
check( '...whose four actions the workbench dispatches', array_diff( [ 'favicon/status', 'favicon/generate', 'favicon/undo', 'favicon/reset' ], array_keys( \Nino\Admin\Admin::actions( $appData ) ) ) === [] );

[ $status, $body ] = callFavicon( $appData, 'favicon/status' );
check( 'the status lists the set a new installation has, with the least size and the base set on offer', $status === 200 && $body['min'] === 512 && $body['base'] === true && $body['undo'] === null
	&& count( array_filter( $body['files'], static fn( array $file ): bool => $file['exists'] === true ) ) === 6 );
check( '...each file with an address that carries its date, and none for the vector icon that is not there', str_contains( $body['files'][0]['url'], '/favicon/favicon-16x16.png?v=' ) === true && end( $body['files'] )['exists'] === false );

$baseSet = faviconSet( $sandbox );
$oldTime = time() - 86400;
foreach( \Nino\Images::FAVICON_FILES as $name )
	touch( $sandbox. '/public/favicon/'. $name, $oldTime );
$stampBefore = \Nino\Images::faviconStamp( $appData );

echo "\n";


// --- making the set -----------------------------------------------------------

echo "Generate\n";

[ $status, $body ] = callFavicon( $appData, 'favicon/generate', [ 'background' => '#336699', 'themeColor' => '#ABC' ], [ 'file' => faviconPicture( 600, 600 ) ] );
check( 'a picture of 600 x 600 makes the set', $status === 200 && $body['undo'] !== null );

$set = faviconSet( $sandbox );

pinSizes( $set );
pinAlpha( $set );
pinTouchIcon( $set, '#336699' );
pinIcoContainer( $set['favicon.ico'] );
pinManifestKeepsAndAdds( $set, $baseSet );
pinNoVectorIconWithoutOne( $set );
pinStampMoves( $appData, $stampBefore );
pinOneCopyKept( $sandbox, $baseSet );

echo "\n";


// --- a picture that is not square ----------------------------------------------

echo "Generate - a wide picture\n";

[ $status ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 1024, 512, false ) ] );
check( 'a picture of 1024 x 512 is taken', $status === 200 );
pinWideIsCentred( faviconSet( $sandbox )['favicon-512x512.png'] );
$wideSet = faviconSet( $sandbox );
pinColoursKeptWhenEmpty( $wideSet );
pinTouchIconInset( $appData, $sandbox );
pinManifestOfTheProject( $appData, $sandbox );

[ $status ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => (static function(): string { ob_start(); imagejpeg( imagecreatetruecolor( 700, 700 ), null, 90 ); return (string) ob_get_clean(); })() ] );
check( 'a jpeg is taken too, and its icons still carry an alpha channel', $status === 200 && ord( faviconSet( $sandbox )['favicon-32x32.png'][25] ) === 6 );

echo "\n";


// --- the vector icon -----------------------------------------------------------

echo "The vector icon\n";

pinSvgStored( $appData, $sandbox );

echo "\n";


// --- undo and reset ------------------------------------------------------------

echo "Undo, reset\n";

pinUndoIsByteEqual( $appData, $sandbox );
pinResetIsTheBaseSet( $appData, $sandbox, $baseSet );
pinWriteFailureRollsBack( $appData, $sandbox );
pinLeftoversDoNotBlock( $appData, $sandbox );

echo "\n";


// --- what is refused -----------------------------------------------------------

echo "Refusals\n";

pinRefusals( $appData, $sandbox );
pinColourField( $appData, $sandbox );
pinGates( $appData, $sandbox );

echo "\n";


// --- the fills ------------------------------------------------------------------

echo "The fills\n";

pinFills( $appData, $sandbox );
pinOnlyTheSetIsTouched( $sandbox );
pinLogHoldsNoName( $sandbox );

ninoDone( $appData );


// --- the pins ------------------------------------------------------------------
// Each one a function of its own, so that a change to the code under test turns one of them red and says which

/**
 *	The sizes of the five pictures
 *
 *	@param		array 		$set
 *
 *	@return		void
 */
function pinSizes( array $set ): void {
	$sizes = array_map( static fn( string $name ): string => implode( 'x', array_slice( (array) getimagesizefromstring( (string) $set[$name] ), 0, 2 ) ), [ 'favicon-16x16.png', 'favicon-32x32.png', 'favicon-192x192.png', 'favicon-512x512.png', 'apple-touch-icon.png' ] );
	check( 'the pictures are 16, 32, 192, 512 and 180 pixels square', $sizes === [ '16x16', '32x32', '192x192', '512x512', '180x180' ] );
}

/**
 *	Alpha in four, none in the touch icon
 *
 *	@param		array 		$set
 *
 *	@return		void
 */
function pinAlpha( array $set ): void {
	$types = array_map( static fn( string $name ): int => ord( $set[$name][25] ), [ 'favicon-16x16.png', 'favicon-32x32.png', 'favicon-192x192.png', 'favicon-512x512.png' ] );
	check( 'the four icons are png with an alpha channel (colour type 6), and the corner of each is transparent', $types === [ 6, 6, 6, 6 ]
		&& faviconPixel( $set['favicon-16x16.png'], 0, 0 )[3] === 127 && faviconPixel( $set['favicon-32x32.png'], 0, 0 )[3] === 127
		&& faviconPixel( $set['favicon-192x192.png'], 0, 0 )[3] === 127 && faviconPixel( $set['favicon-512x512.png'], 0, 0 )[3] === 127 );
	$middle = faviconPixel( $set['favicon-512x512.png'], 256, 256 );
	check( '...and the picture itself is in the middle of them, opaque', $middle[3] === 0 && $middle[0] > 200 && $middle[1] < 60 );
}

/**
 *	The touch icon: a surface, no alpha, the picture inset by a tenth
 *
 *	@param		array 		$set
 *	@param		string		$surface
 *
 *	@return		void
 */
function pinTouchIcon( array $set, string $surface ): void {
	$icon = $set['apple-touch-icon.png'];
	check( 'the touch icon has no alpha channel (colour type 2) and the colour asked for in its corner', ord( $icon[25] ) === 2 && faviconPixel( $icon, 0, 0 ) === [ 0x33, 0x66, 0x99, 0 ] && $surface === '#336699' );
	$inset = faviconPixel( $icon, 6, 90 );
	$middle = faviconPixel( $icon, 90, 90 );
	check( '...with ten per cent of the edge left free around the picture, which is in the middle', $inset === [ 0x33, 0x66, 0x99, 0 ] && $middle[0] > 200 && $middle[1] < 60 );
}

/**
 *	The ico container: the header, three entries with a png each
 *
 *	@param		string		$ico
 *
 *	@return		void
 */
function pinIcoContainer( string $ico ): void {
	$head = unpack( 'vreserved/vtype/vcount', $ico );
	check( 'the ico starts with 0, 1 (icons) and three pictures', $head === [ 'reserved' => 0, 'type' => 1, 'count' => 3 ] );

	$entries = [];
	for( $i = 0; $i < 3; $i++ )
		$entries[] = unpack( 'Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vsize/Voffset', substr( $ico, 6 + 16 * $i, 16 ) );

	check( '...of 16, 32 and 48 pixels, one plane, 32 bits', array_column( $entries, 'width' ) === [ 16, 32, 48 ] && array_column( $entries, 'height' ) === [ 16, 32, 48 ] && array_unique( array_column( $entries, 'planes' ) ) === [ 1 ] && array_unique( array_column( $entries, 'bits' ) ) === [ 32 ] );

	$pngs = true;
	$end = 6 + 48;
	foreach( $entries as $entry ) {
		$png = substr( $ico, $entry['offset'], $entry['size'] );
		$info = getimagesizefromstring( $png );
		$pngs = $pngs && str_starts_with( $png, "\x89PNG\r\n\x1a\n" ) && $info !== false && $info[0] === $entry['width'] && $entry['offset'] === $end;
		$end = $entry['offset'] + $entry['size'];
	}
	check( '...each entry a png of its size at its offset, back to back, and the file ends with the last', $pngs === true && $end === strlen( $ico ) );
}

/**
 *	The manifest: the icons added, the colours written, the name filled, the rest as it was
 *
 *	@param		array 		$set
 *	@param		array 		$baseSet
 *
 *	@return		void
 */
function pinManifestKeepsAndAdds( array $set, array $baseSet ): void {
	$before = json_decode( (string) $baseSet['site.webmanifest'], true );
	$after = json_decode( (string) $set['site.webmanifest'], true );
	check( 'the manifest has the 192 and the 512 icon, the colours as written (a short hex value made whole) and the name of the site where it was empty',
		array_column( $after['icons'], 'sizes' ) === [ '192x192', '512x512' ] && $after['theme_color'] === '#aabbcc' && $after['background_color'] === '#336699' && $after['name'] === 'Acme' && $after['short_name'] === 'Acme' );
	check( '...and everything else of it is as it was: its keys in their order, its icons untouched', array_slice( array_keys( $after ), 0, 3 ) === [ 'name', 'short_name', 'icons' ] && $after['display'] === $before['display'] && $after['icons'] === $before['icons'] );
}

/**
 *	No vector icon from a raster alone
 *
 *	@param		array 		$set
 *
 *	@return		void
 */
function pinNoVectorIconWithoutOne( array $set ): void {
	check( 'a set made without an svg has none', $set['favicon.svg'] === null );
}

/**
 *	The stamp moves with the set
 *
 *	@param		array 		&$appData
 *	@param		int				$before
 *
 *	@return		void
 */
function pinStampMoves( array &$appData, int $before ): void {
	$after = \Nino\Images::faviconStamp( $appData );
	check( 'the stamp of the set is a later time once the set was written', $after > $before && $before > 0 && $after >= time() - 5 );
}

/**
 *	The set that stood is kept once, as it was
 *
 *	@param		string		$sandbox
 *	@param		array 		$baseSet
 *
 *	@return		void
 */
function pinOneCopyKept( string $sandbox, array $baseSet ): void {
	$kept = true;
	foreach( \Nino\Images::FAVICON_FILES as $name )
		$kept = $kept && is_file( $sandbox. '/private/data/favicon-previous/'. $name ) === true && file_get_contents( $sandbox. '/private/data/favicon-previous/'. $name ) === $baseSet[$name];
	$mark = json_decode( (string) @file_get_contents( $sandbox. '/private/data/favicon-previous/previous.json' ), true );
	check( 'the set that stood is kept below the private data, byte for byte, with a marker naming its files', $kept === true && is_array( $mark ) === true && $mark['files'] === \Nino\Images::FAVICON_FILES && is_int( $mark['at'] ) === true );
}

/**
 *	The middle of a wide picture, not the whole width of it
 *
 *	@param		string		$png
 *
 *	@return		void
 */
function pinWideIsCentred( string $png ): void {
	check( 'a picture that is not square is set in the middle of the square, whole: transparent above and below, the picture in the middle, the corners of its own ground inside the band',
		faviconPixel( $png, 256, 10 )[3] === 127 && faviconPixel( $png, 256, 500 )[3] === 127 && faviconPixel( $png, 256, 256 )[3] === 0
		&& faviconPixel( $png, 5, 256 ) === [ 0, 160, 0, 0 ] && faviconPixel( $png, 506, 256 ) === [ 0, 160, 0, 0 ] && faviconPixel( $png, 5, 130 )[3] === 0 && faviconPixel( $png, 5, 126 )[3] === 127 );
}

/**
 *	The touch icon keeps a tenth of its edge free: a picture that fills its square reaches the inset, not the edge
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinTouchIconInset( array &$appData, string $sandbox ): void {

	$ground = imagecreatetruecolor( 600, 600 );
	imagefill( $ground, 0, 0, imagecolorallocate( $ground, 0, 160, 0 ) );
	ob_start();
	imagepng( $ground );
	callFavicon( $appData, 'favicon/generate', [ 'background' => '#336699' ], [ 'file' => (string) ob_get_clean() ] );

	$icon = faviconSet( $sandbox )['apple-touch-icon.png'];
	check( 'a picture that fills its square is drawn into the touch icon 18 pixels in from every edge (a tenth of 180), the surface around it',
		faviconPixel( $icon, 16, 90 ) === [ 0x33, 0x66, 0x99, 0 ] && faviconPixel( $icon, 21, 90 ) === [ 0, 160, 0, 0 ] && faviconPixel( $icon, 158, 90 ) === [ 0, 160, 0, 0 ] && faviconPixel( $icon, 163, 90 ) === [ 0x33, 0x66, 0x99, 0 ]
		&& faviconPixel( $icon, 90, 16 ) === [ 0x33, 0x66, 0x99, 0 ] && faviconPixel( $icon, 90, 21 ) === [ 0, 160, 0, 0 ] && faviconPixel( $icon, 90, 163 ) === [ 0x33, 0x66, 0x99, 0 ] );
}

/**
 *	A manifest the project changed is changed in few places: its own name stays, an icon of a size the set makes names the file of
 *	the set and keeps its other fields, the one it lacks is added, an empty object stays one, the layout of the file stays
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinManifestOfTheProject( array &$appData, string $sandbox ): void {

	$compact = (string) file_get_contents( $sandbox. '/public/favicon/site.webmanifest' );
	check( 'a manifest written on one line is written on one line', str_contains( trim( $compact ), "\n" ) === false );

	$own = "{\n    \"name\": \"Own Name\",\n    \"short_name\": \"\",\n    \"lang\": \"de\",\n    \"icons\": [\n        {\"src\": \"tiny.png\", \"sizes\": \"96x96\", \"type\": \"image/png\"},\n        {\"src\": \"big.png\", \"sizes\": \"512x512\", \"type\": \"image/png\", \"purpose\": \"maskable\"}\n    ],\n    \"shortcuts\": {},\n    \"display\": \"minimal-ui\"\n}\n";
	file_put_contents( $sandbox. '/public/favicon/site.webmanifest', $own );

	callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 600, 600 ) ] );
	$raw = (string) file_get_contents( $sandbox. '/public/favicon/site.webmanifest' );
	$after = json_decode( $raw, true );

	check( 'a name the project set stays and an empty one takes the name of the site', $after['name'] === 'Own Name' && $after['short_name'] === 'Acme' );
	check( '...an icon of a size the set makes names the file of the set and keeps its other fields, the one the manifest lacks is added, the icon of another size is as it was',
		array_column( $after['icons'], 'src' ) === [ 'tiny.png', 'favicon-512x512.png', 'favicon-192x192.png' ] && $after['icons'][1]['purpose'] === 'maskable' && $after['icons'][0]['sizes'] === '96x96' );
	check( '...an empty object of the project is an empty object and not a list, and the manifest is written in the layout it had', str_contains( $raw, '"shortcuts": {}' ) === true && str_contains( trim( $raw ), "\n" ) === true );
	check( '...and every other entry is as the project had it, in its place', array_keys( $after ) === [ 'name', 'short_name', 'lang', 'icons', 'shortcuts', 'display' ] && $after['lang'] === 'de' && $after['display'] === 'minimal-ui' );
}

/**
 *	A colour left empty leaves the manifest's
 *
 *	@param		array 		$set
 *
 *	@return		void
 */
function pinColoursKeptWhenEmpty( array $set ): void {
	$manifest = json_decode( (string) $set['site.webmanifest'], true );
	check( 'a colour left empty leaves the value the manifest holds, and the touch icon is white without a colour', $manifest['theme_color'] === '#aabbcc' && $manifest['background_color'] === '#336699' && faviconPixel( $set['apple-touch-icon.png'], 0, 0 ) === [ 255, 255, 255, 0 ] );
}

/**
 *	A svg is kept as \Nino\Images::cleanSvg() rebuilds it, and a set without one removes the old one
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinSvgStored( array &$appData, string $sandbox ): void {

	$clean = \Nino\Images::cleanSvg( faviconDirtySvg() );

	[ $status, $body ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 512, 512 ), 'svg' => faviconDirtySvg() ] );
	$stored = (string) @file_get_contents( $sandbox. '/public/favicon/favicon.svg' );
	check( 'posted with a picture the svg is stored as the kernel rebuilds it - no script, no event - and the status shows it', $status === 200 && is_string( $clean ) === true && $stored === $clean
		&& preg_match( '/script|onclick/', $stored ) !== 1 && end( $body['files'] )['exists'] === true );

	[ $status ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 512, 512 ) ] );
	check( 'a set made without a svg removes the one the old set had - it would draw the old picture beside the new icons', $status === 200 && is_file( $sandbox. '/public/favicon/favicon.svg' ) === false );

	[ $status ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 512, 512 ), 'svg' => faviconDirtySvg() ] );
	check( 'and once more with one, for the undo below', $status === 200 && is_file( $sandbox. '/public/favicon/favicon.svg' ) === true );
}

/**
 *	Undo gives back every byte of the set it replaced
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinUndoIsByteEqual( array &$appData, string $sandbox ): void {

	$withSvg = faviconSet( $sandbox );
	callFavicon( $appData, 'favicon/generate', [ 'themeColor' => '#010203' ], [ 'file' => faviconPicture( 700, 700 ) ] );
	$replaced = faviconSet( $sandbox );

	[ $status, $body ] = callFavicon( $appData, 'favicon/undo' );
	check( 'Undo puts back every file of the set it replaced, byte for byte, the vector icon the new set had removed included', $status === 200 && faviconSet( $sandbox ) === $withSvg && $replaced !== $withSvg && $withSvg['favicon.svg'] !== null );
	check( '...and uses the copy up: there is none left, and a second Undo is a conflict that changes nothing', $body['undo'] === null && is_file( $sandbox. '/private/data/favicon-previous/previous.json' ) === false
		&& callFavicon( $appData, 'favicon/undo' )[0] === 409 && faviconSet( $sandbox ) === $withSvg );
}

/**
 *	Reset writes the base unit's files, and is itself undone
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *	@param		array 		$baseSet
 *
 *	@return		void
 */
function pinResetIsTheBaseSet( array &$appData, string $sandbox, array $baseSet ): void {

	$before = faviconSet( $sandbox );
	[ $status ] = callFavicon( $appData, 'favicon/reset' );
	check( 'Reset puts the files of the base unit back byte for byte and removes the vector icon', $status === 200 && faviconSet( $sandbox ) === $baseSet && is_file( $sandbox. '/public/favicon/favicon.svg' ) === false );

	[ $status ] = callFavicon( $appData, 'favicon/undo' );
	check( '...and Undo brings the set back that it replaced', $status === 200 && faviconSet( $sandbox ) === $before );
}

/**
 *	A file that cannot be written leaves the set as it was - and the way back to the set before it as it was too
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinWriteFailureRollsBack( array &$appData, string $sandbox ): void {

	// Two sets made, so that the kept copy is the first one and the set that stands is the second
	callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 620, 620 ) ] );
	$first = faviconSet( $sandbox );
	callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 630, 630 ) ] );
	$before = faviconSet( $sandbox );
	$kept = faviconKept( $sandbox );

	// The 512 icon is written after the 16 and the 32: those have to be put back
	$blocked = $sandbox. '/public/favicon/favicon-512x512.png';
	unlink( $blocked );
	mkdir( $blocked );

	[ $status ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 640, 640 ) ] );
	$after = faviconSet( $sandbox );
	$keptAfter = faviconKept( $sandbox );
	rmdir( $blocked );
	file_put_contents( $blocked, (string) $before['favicon-512x512.png'] );

	check( 'a file that cannot be written is a 500, and the files written before it are put back: the set is as it was', $status === 500 && $after['favicon-16x16.png'] === $before['favicon-16x16.png'] && $after['favicon-32x32.png'] === $before['favicon-32x32.png'] && $after['site.webmanifest'] === $before['site.webmanifest'] && $after['favicon.ico'] === $before['favicon.ico'] );
	check( '...and no temporary file is left in the directory', glob( $sandbox. '/public/favicon/*.tmp' ) === [] );
	check( '...the copy Undo returns to is the one it was - every file and the marker byte for byte - and no half-made copy is left beside it', $kept !== [] && $keptAfter === $kept && is_dir( $sandbox. '/private/data/favicon-previous-next' ) === false );

	callFavicon( $appData, 'favicon/undo' );
	check( '...so that Undo brings back the set before the one that stands', faviconSet( $sandbox ) === $first );
}

/**
 *	A file a cut-short write left in the directories of the copies is no reason for them to stand: the sets are made and undone as always
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinLeftoversDoNotBlock( array &$appData, string $sandbox ): void {

	$data = $sandbox. '/private/data';
	callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 650, 650 ) ] );

	@mkdir( $data. '/favicon-previous-next' );
	@mkdir( $data. '/favicon-previous-old' );
	file_put_contents( $data. '/favicon-previous-next/favicon.ico.a1b2c3.tmp', 'cut short' );
	file_put_contents( $data. '/favicon-previous-old/favicon.ico.d4e5f6.tmp', 'cut short' );
	file_put_contents( $data. '/favicon-previous/stray.txt', 'not of the set' );

	[ $one ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 660, 660 ) ] );
	file_put_contents( $data. '/favicon-previous/stray.txt', 'not of the set' );
	[ $two ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 670, 670 ) ] );
	[ $reset ] = callFavicon( $appData, 'favicon/reset' );
	$afterReset = faviconSet( $sandbox );
	callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 680, 680 ) ] );
	[ $undo ] = callFavicon( $appData, 'favicon/undo' );

	check( 'a temporary file in a copy directory and a stray file in the kept one block nothing: generate, generate and reset are all 200', $one === 200 && $two === 200 && $reset === 200 );
	check( '...Undo brings back the set before the one that stands, and no directory of a copy is left over', $undo === 200 && faviconSet( $sandbox ) === $afterReset && is_dir( $data. '/favicon-previous-next' ) === false && is_dir( $data. '/favicon-previous-old' ) === false );
}

/**
 *	The files of the kept copy: name => bytes
 *
 *	@param		string		$sandbox
 *
 *	@return		array
 */
function faviconKept( string $sandbox ): array {
	$kept = [];
	foreach( array_diff( @scandir( $sandbox. '/private/data/favicon-previous' ) ?: [], [ '.', '..' ] ) as $name )
		$kept[$name] = file_get_contents( $sandbox. '/private/data/favicon-previous/'. $name );
	return $kept;
}

/**
 *	What is refused changes nothing
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinRefusals( array &$appData, string $sandbox ): void {

	$before = faviconSet( $sandbox );
	$mark = (string) @file_get_contents( $sandbox. '/private/data/favicon-previous/previous.json' );
	$logged = count( faviconLog( $sandbox ) );

	$gif = imagecreatetruecolor( 600, 600 );
	ob_start();
	imagegif( $gif );
	$gifBytes = (string) ob_get_clean();

	// A header that claims 6000 x 6000 - 36 megapixels - over a few bytes of data: refused before anything decodes it
	$bomb = faviconPicture( 64, 64 );
	$bomb = substr( $bomb, 0, 16 ). pack( 'NN', 6000, 6000 ). substr( $bomb, 24 );

	$cases = [
		'a picture of 511 x 900'			=> [ [ 'file' => faviconPicture( 511, 900 ) ], 400, 'favicon_too_small' ],
		'a picture of 900 x 400'			=> [ [ 'file' => faviconPicture( 900, 400 ) ], 400, 'favicon_too_small' ],
		'bytes that are no picture'		=> [ [ 'file' => 'this is not a picture at all' ], 400, 'image_type' ],
		'a gif'												=> [ [ 'file' => $gifBytes ], 400, 'favicon_type' ],
		'a picture over the byte limit'=> [ [ 'file' => faviconPicture( 600, 600 ). str_repeat( "\0", \Nino\Images::MAX_UPLOAD_BYTES ) ], 400, 'image_too_large' ],
		'a picture over the pixel cap'=> [ [ 'file' => $bomb ], 400, 'image_too_many_pixels' ],
		'no file'											=> [ [], 400, 'upload_missing' ],
		'a file php refused'					=> [ [ 'file' => UPLOAD_ERR_INI_SIZE ], 413, 'upload_too_large' ],
		'a broken svg beside a good picture'=> [ [ 'file' => faviconPicture( 600, 600 ), 'svg' => '<svg><path' ], 400, 'favicon_svg' ],
		'a svg php refused'						=> [ [ 'file' => faviconPicture( 600, 600 ), 'svg' => UPLOAD_ERR_PARTIAL ], 400, 'upload_partial' ],
	];

	foreach( $cases as $label => [ $files, $expectedStatus, $expectedCode ] ) {
		[ $status, $body ] = callFavicon( $appData, 'favicon/generate', [], $files );
		check( $label. ' is a '. $expectedStatus. ' ('. $expectedCode. ') and the set and the kept copy are as they were',
			$status === $expectedStatus && ( $body['code'] ?? '' ) === $expectedCode && faviconSet( $sandbox ) === $before && (string) @file_get_contents( $sandbox. '/private/data/favicon-previous/previous.json' ) === $mark );
	}

	[ , $body ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => faviconPicture( 300, 700 ) ] );
	check( 'none of the refusals, and no status read, is a line of the activity log', count( faviconLog( $sandbox ) ) === $logged && callFavicon( $appData, 'favicon/status' )[0] === 200 && count( faviconLog( $sandbox ) ) === $logged );
	check( 'the answer to a picture below the least size names it and the size it has', ( $body['params'] ?? [] ) === [ 512, 300, 700 ] );
}

/**
 *	The colour fields
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinColourField( array &$appData, string $sandbox ): void {

	$before = faviconSet( $sandbox );
	$picture = faviconPicture( 600, 600 );
	$wrong = [ 'red', '#12345', '#gggggg', '#12345678', '##fff', 'fff;background:url(x)', "#fff\0", '#fff<script>', 'rgb(1,2,3)', '1234567' ];
	$accepted = [];
	foreach( $wrong as $value )
		foreach( [ 'background', 'themeColor' ] as $field ) {
			[ $status, $body ] = callFavicon( $appData, 'favicon/generate', [ $field => $value ], [ 'file' => $picture ] );
			if( $status !== 400 || ( $body['code'] ?? '' ) !== 'favicon_colour' || ( $body['field'] ?? '' ) !== $field )
				$accepted[] = $field. '='. addcslashes( $value, "\0" );
		}

	[ $status ] = callFavicon( $appData, 'favicon/generate', [ 'background' => [ '#fff' ], 'themeColor' => true ], [ 'file' => $picture ] );

	check( 'a colour that is not three or six hex digits is a 400 at its field, whichever way it is dressed'. ( $accepted === [] ? '' : ' - taken: '. implode( ', ', $accepted ) ), $accepted === [] );
	check( '...a list or a bool for a colour is no colour - read as none, no fatal error', $status === 200 && $before !== faviconSet( $sandbox ) );

	$colours = [ '#FFF' => '#ffffff', 'a1B2c3' => '#a1b2c3', '' => '', '  #123456 ' => '#123456' ];
	$made = [];
	foreach( $colours as $given => $expected )
		$made[$given] = \Nino\Modules\Images\Favicon::color( (string) $given ) === $expected;
	check( 'three digits are made six, case is lowered, the hash is optional and space around is not part of it', in_array( false, $made, true ) === false );
}

/**
 *	Who may, and the csrf guard
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinGates( array &$appData, string $sandbox ): void {

	$before = faviconSet( $sandbox );
	$picture = faviconPicture( 600, 600 );

	[ $status ] = callFavicon( $appData, 'favicon/generate', [], [ 'file' => $picture ], 403 );
	check( 'a request the csrf guard already refused makes nothing', $status === 403 && faviconSet( $sandbox ) === $before );

	ninoUser( $appData, 'plain@example.com', [], 'plain password' );
	$statuses = [];
	foreach( [ 'favicon/status', 'favicon/generate', 'favicon/undo', 'favicon/reset' ] as $action )
		$statuses[$action] = callFavicon( $appData, $action, [], [ 'file' => $picture ] )[0];
	check( 'an account with no permission is refused all four actions with 403, and the set is as it was', array_values( array_unique( $statuses ) ) === [ 403 ] && faviconSet( $sandbox ) === $before );

	ninoUser( $appData, 'images@example.com', [ '/_admin/images/manage' ], 'images password' );
	check( 'the permission of the Images panel is enough', callFavicon( $appData, 'favicon/status' )[0] === 200 );

	\Nino\Auth::logoutUser( $appData );
	$statuses = [];
	foreach( [ 'favicon/status', 'favicon/generate', 'favicon/undo', 'favicon/reset' ] as $action )
		$statuses[$action] = callFavicon( $appData, $action, [], [ 'file' => $picture ] )[0];
	check( 'nobody signed in is refused all four actions with 401, and the set is as it was', array_values( array_unique( $statuses ) ) === [ 401 ] && faviconSet( $sandbox ) === $before );

	\Nino\Auth::loginUser( $appData, 'admin@example.com', 'correct horse battery staple' );
}

/**
 *	The two fills a template reads the set through
 *
 *	@param		array 		&$appData
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinFills( array &$appData, string $sandbox ): void {

	$present = faviconSet( $sandbox );
	$stamp = \Nino\Images::faviconStamp( $appData );
	$fills = \Nino\Html::bootFills( $appData );
	check( 'the stamp is a fill of the kernel under /nino/ and answers the newest time of the set', ( $fills['[[/nino/favicon/stamp]]'] ?? '' ) === (string) $stamp && $stamp >= filemtime( $sandbox. '/public/favicon/favicon.ico' ) && in_array( 'nino/favicon/stamp', array_map( static fn( string $key ): string => ltrim( $key, '/' ), \Nino\Html::runtimeFillKeys( $appData ) ), true ) === true );

	// Without a vector icon the fill is empty, so the template shows no line for it
	@unlink( $sandbox. '/public/favicon/favicon.svg' );
	$fills = \Nino\Html::bootFills( $appData );
	check( 'the fill of the vector icon is empty while the site has none', ( $fills['[[/nino/favicon/svg]]'] ?? null ) === '' );

	file_put_contents( $sandbox. '/public/favicon/favicon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>' );
	$fills = \Nino\Html::bootFills( $appData );
	check( '...and the line of the icon, with the stamp on its address, once it has one', ( $fills['[[/nino/favicon/svg]]'] ?? '' ) === "\n\t\t\t".'<link rel="icon" type="image/svg+xml" href="/public/favicon/favicon.svg?v='. \Nino\Images::faviconStamp( $appData ). '">' );

	// A directory with a quote in it is an address with a quote in it, and the quote is not the end of the attribute
	$quoted = $appData['/nino/dir'];
	$appData['/nino/dir'] = '/a"b';
	$link = \Nino\Images::faviconSvgLink( $appData );
	$appData['/nino/dir'] = $quoted;
	check( '...and an address with a quote in it is escaped in the attribute', str_contains( $link, 'href="/a&quot;b/public/favicon/favicon.svg?v=' ) === true && substr_count( $link, '"' ) === 6 );
	unlink( $sandbox. '/public/favicon/favicon.svg' );

	$header = (string) file_get_contents( dirname( __DIR__ ). '/_admin/install/library/base/templates/html-header.tpl' );
	check( 'the header the base unit ships puts the line of the vector icon in and the stamp on every address of the set', str_contains( $header, '[[/nino/favicon/svg]]' ) === true
		&& preg_match_all( '#/favicon/[a-z0-9.-]+\?v=\[\[/nino/favicon/stamp\]\]#', $header ) === 5 );

	file_put_contents( $sandbox. '/public/favicon/favicon.svg', $present['favicon.svg'] ?? '' );
	if( $present['favicon.svg'] === null )
		unlink( $sandbox. '/public/favicon/favicon.svg' );
}

/**
 *	Everything written is the set, below public/favicon, or the copy below the private data
 *
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinOnlyTheSetIsTouched( string $sandbox ): void {

	$allowed = array_merge( \Nino\Images::FAVICON_FILES, [ 'favicon.svg' ] );
	$found = array_values( array_diff( scandir( $sandbox. '/public/favicon' ), [ '.', '..' ] ) );
	$kept = array_values( array_diff( @scandir( $sandbox. '/private/data/favicon-previous' ) ?: [], [ '.', '..' ] ) );
	$top = array_values( array_diff( scandir( $sandbox. '/public' ), [ '.', '..' ] ) );

	check( 'the public directory holds the set and nothing else - no temporary file, no other name', array_diff( $found, $allowed ) === [] && $top === [ 'favicon' ] );
	check( '...and the kept copy holds files of the set and its marker, and there is no half-made copy beside it', array_diff( $kept, array_merge( $allowed, [ 'previous.json' ] ) ) === [] && is_dir( $sandbox. '/private/data/favicon-previous-next' ) === false );
}

/**
 *	The activity log says what happened and not with what
 *
 *	@param		string		$sandbox
 *
 *	@return		void
 */
function pinLogHoldsNoName( string $sandbox ): void {

	$lines = faviconLog( $sandbox );
	$mine = array_values( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'Favicon' ) ) );
	$all = implode( "\n", $lines );

	check( 'a made set, an undo and a reset are in the activity log', count( array_filter( $mine, static fn( string $line ): bool => str_ends_with( $line, 'Generate Favicon' ) ) ) >= 5 && count( array_filter( $mine, static fn( string $line ): bool => str_ends_with( $line, 'Undo Favicon' ) ) ) >= 2 && count( array_filter( $mine, static fn( string $line ): bool => str_ends_with( $line, 'Reset Favicon' ) ) ) >= 1 );
	check( '...and the name of the file that was uploaded, a colour, a path and the svg are in none of its lines', str_contains( $all, 'secret' ) === false && str_contains( $all, 'logo.png' ) === false && str_contains( $all, '#336699' ) === false && str_contains( $all, 'svg' ) === false );
}
