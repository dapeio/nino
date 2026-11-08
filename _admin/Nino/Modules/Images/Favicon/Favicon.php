<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Images\Favicon	Favicon tab: the icon set of the site, made from one picture
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Images {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Favicon						The third tab of the Images pane: the files the page header names
	 *											under /favicon (the browser tab icon, the touch icon of a phone's
	 *											home screen, the icons of the web manifest), made by gd from one
	 *											raster picture of at least 512 x 512 pixels, plus the vector
	 *											icon from an svg that is cleaned before it is kept. The old set
	 *											is kept as one copy for Undo, the base unit's set can be put
	 *											back, and every file is written whole or not at all. The
	 *											permission is the Images panel's own.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Favicon {

		// The edge of the master the sizes are made from, and the least a raster upload has to bring
		public const int MIN_SIDE = 512;

		// A vector icon is a few paths: more than this is not one
		public const int MAX_SVG_BYTES = 262144;

		private const string SVG_NS 			= 'http://www.w3.org/2000/svg';
		private const string BACKUP_DIR 	= '/data/favicon-previous';
		private const string BACKUP_NEXT 	= '/data/favicon-previous-next';
		private const string BACKUP_OLD 	= '/data/favicon-previous-old';
		private const string BACKUP_MARK 	= 'previous.json';

		// The rows of the preview, in the order they are shown
		private const array PREVIEW = [ 'favicon-16x16.png', 'favicon-32x32.png', 'apple-touch-icon.png', 'favicon-192x192.png', 'favicon-512x512.png', 'favicon.ico', 'favicon.svg' ];

		// What a vector icon may be made of - shapes, groups and gradients, nothing that runs, styles
		// or fetches. An element or an attribute that is not here is dropped with what is below it
		private const array SVG_ELEMENTS = [ 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'defs', 'linearGradient', 'radialGradient', 'stop', 'clipPath' ];
		private const array SVG_ATTRIBUTES = [
			'id', 'viewBox', 'width', 'height', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2', 'fx', 'fy', 'points', 'd', 'transform',
			'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'clip-path', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
			'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'offset', 'stop-color', 'stop-opacity',
			'gradientUnits', 'gradientTransform', 'spreadMethod', 'clipPathUnits', 'preserveAspectRatio',
		];
		// The only functions a value of those attributes may call
		private const array SVG_FUNCTIONS = [ 'url', 'rgb', 'rgba', 'hsl', 'hsla', 'translate', 'rotate', 'scale', 'matrix', 'skewx', 'skewy' ];

		public static function perm(): string {
			return Admin::MANAGE_PERM;
		}

		public static function actions(): array {
			return [
				'favicon/status' 	=> [ self::class, 'apiStatus' ],
				'favicon/generate'=> [ self::class, 'apiGenerate' ],
				'favicon/undo' 		=> [ self::class, 'apiUndo' ],
				'favicon/reset' 	=> [ self::class, 'apiReset' ],
			];
		}

		/**
		 *	A tab of the Images pane (see \Nino\Modules\Images\Admin::tabs())
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'favicon', '/_admin/nav/favicon', 50, 'content' ];
		}

		public static function panes(): array {
			return [ 'favicon-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/favicon.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/favicon.css' ),
			];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// Fixed lines: no name of the uploaded file and no colour is in them
		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'favicon/generate'	=> 'Generate Favicon',
				'favicon/undo'			=> 'Undo Favicon',
				'favicon/reset'			=> 'Reset Favicon',
				default	=> '',
			};
		}

		/**
		 *	What the tab shows: the files of the set that are there - each with
		 *	its address (stamped with its modification time, so a picture just
		 *	made is not the one the browser kept), size and date -, whether a
		 *	previous set is kept for Undo, whether the base unit's set can be
		 *	put back, and the two colours the manifest holds now
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiStatus( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			\Nino\Http::ok( $request, self::_status( $appData ) );
		}

		/**
		 *	Make the set from the raster picture posted as "file" and, if
		 *	there is one, the svg posted as "svg"; { background, themeColor }
		 *	are the colours as hex or empty. Everything is made in memory
		 *	first, so a picture that cannot be used changes nothing, and the
		 *	old set is kept before the first file is written. A set without an
		 *	svg removes the one the old set had: it would draw the old icon
		 *	next to the new ones
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiGenerate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			$data 			= \Nino\Admin\Admin::postData();
			$background	= self::color( \Nino\Admin\Admin::postString( $data, 'background' ) );
			$theme 			= self::color( \Nino\Admin\Admin::postString( $data, 'themeColor' ) );

			if( $background === false ) {
				\Nino\Http::fail( $request, 400, 'a colour is a hex value like #1a2b3c', 'favicon_colour', [], 'background' );
				return;
			}

			if( $theme === false ) {
				\Nino\Http::fail( $request, 400, 'a colour is a hex value like #1a2b3c', 'favicon_colour', [], 'themeColor' );
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

			$rejection = \Nino\Images::reject( $bytes );
			if( $rejection !== null ) {
				\Nino\Http::fail( $request, 400, 'the image was refused: '. $rejection['code'], $rejection['code'], $rejection['params'] );
				return;
			}

			// The picture as it is shown (a photograph stored on its side is measured upright). A gif is
			// an image the kernel takes, and no source for an icon
			$shown = \Nino\Images::size( $bytes );
			if( $shown === false || in_array( $shown['type'], [ IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'the picture is a png, a webp or a jpeg', 'favicon_type' );
				return;
			}

			if( $shown['width'] < self::MIN_SIDE || $shown['height'] < self::MIN_SIDE ) {
				\Nino\Http::fail( $request, 400, 'the picture is at least '. self::MIN_SIDE. ' x '. self::MIN_SIDE. ' pixels', 'favicon_too_small', [ self::MIN_SIDE, $shown['width'], $shown['height'] ] );
				return;
			}

			$svg = null;
			if( is_array( $_FILES['svg'] ?? null ) === true && ( $_FILES['svg']['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_NO_FILE ) {

				$failure = \Nino\Admin\Admin::uploadError( $_FILES['svg'] );
				if( $failure !== null ) {
					\Nino\Http::fail( $request, $failure['status'], $failure['error'], $failure['code'], $failure['params'] );
					return;
				}

				$source = file_get_contents( $_FILES['svg']['tmp_name'] );
				$svg 		= $source === false ? false : self::cleanSvg( $source );
				if( $svg === false ) {
					\Nino\Http::fail( $request, 400, 'the svg could not be read as a plain vector picture', 'favicon_svg' );
					return;
				}
			}

			$files = self::_build( $appData, $bytes, $background, $theme );
			if( $files === false ) {
				\Nino\Http::fail( $request, 400, 'the image could not be processed', 'image_unreadable' );
				return;
			}

			$files[\Nino\Images::FAVICON_SVG] = $svg;

			if( self::_install( $appData, $files, true ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the icons' );
				return;
			}

			\Nino\Http::ok( $request, self::_status( $appData ) );
		}

		/**
		 *	Put the set back that the last generate or reset replaced. The
		 *	copy is used up by it: a second call finds none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUndo( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			$kept = self::_kept( $appData );
			if( $kept === null ) {
				\Nino\Http::fail( $request, 409, 'there is no previous set', 'favicon_no_previous' );
				return;
			}

			$files = [];
			foreach( self::_names() as $name ) {

				$files[$name] = null;
				if( in_array( $name, $kept['files'], true ) === false )
					continue;

				$bytes = file_get_contents( \Nino\Filesystem::path( $appData, self::BACKUP_DIR. '/'. $name ) );
				if( $bytes === false ) {
					\Nino\Http::fail( $request, 500, 'the previous set could not be read' );
					return;
				}

				$files[$name] = $bytes;
			}

			if( self::_install( $appData, $files, false ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the icons' );
				return;
			}

			self::_forget( $appData, self::BACKUP_DIR );

			\Nino\Http::ok( $request, self::_status( $appData ) );
		}

		/**
		 *	Put the files of the base unit back - the set a new installation
		 *	starts with, without a vector icon. The set that is replaced is
		 *	kept for Undo
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiReset( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			$files = [ \Nino\Images::FAVICON_SVG => null ];
			foreach( \Nino\Images::FAVICON_FILES as $name ) {

				$bytes = is_file( self::_baseDir(). '/'. $name ) === true ? file_get_contents( self::_baseDir(). '/'. $name ) : false;
				if( $bytes === false ) {
					\Nino\Http::fail( $request, 404, 'the base unit has no icon set', 'favicon_no_base' );
					return;
				}

				$files[$name] = $bytes;
			}

			if( self::_install( $appData, $files, true ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the icons' );
				return;
			}

			\Nino\Http::ok( $request, self::_status( $appData ) );
		}

		/**
		 *	A colour field: '' for none, '#rrggbb' for a hex value of three or six digits (with or without the
		 *	hash, any case), false for anything else - the value ends up in a manifest and in a pixel, and
		 *	nothing but hex digits gets there
		 *
		 *	@param		string		$value
		 *
		 *	@return 	string|false
		 */
		public static function color( string $value ): string|false {

			$value = trim( $value, " \t\n\r" );
			if( $value === '' )
				return '';

			if( preg_match( '/\A#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $value, $found ) !== 1 )
				return false;

			$digits = strtolower( $found[1] );
			if( strlen( $digits ) === 3 )
				$digits = $digits[0]. $digits[0]. $digits[1]. $digits[1]. $digits[2]. $digits[2];

			return '#'. $digits;
		}

		/**
		 *	A vector picture reduced to what an icon needs. The svg is parsed and rebuilt: only the elements
		 *	and attributes of the allow lists above are copied, a value is only copied if it holds nothing but
		 *	the characters of a number, a colour, a path or a transform, and a function it calls is one of a
		 *	short list - and `url()` only to an id of the same picture. Scripts, styles, foreign objects,
		 *	images, links and every event attribute are therefore not cleaned out of the picture but never
		 *	part of the copy. A document type, entity declarations, a root that is no svg in the svg namespace,
		 *	bytes that are no UTF-8 and a picture too large or without any shape are refused
		 *
		 *	@param		string		$svg					The uploaded bytes
		 *
		 *	@return 	string|false							The svg to keep, or false
		 */
		public static function cleanSvg( string $svg ): string|false {

			if( $svg === '' || strlen( $svg ) > self::MAX_SVG_BYTES || str_contains( $svg, "\0" ) === true || mb_check_encoding( $svg, 'UTF-8' ) === false )
				return false;

			// Before the parser sees them: an entity is how a small file becomes a large one or reads a file
			if( preg_match( '/<!(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)/i', $svg ) === 1 )
				return false;

			$previous = libxml_use_internal_errors( true );
			$source 	= new \DOMDocument();
			$loaded 	= $source->loadXML( $svg, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );

			if( $loaded === false || $source->doctype !== null )
				return false;

			$root = $source->documentElement;
			if( $root === null || $root->localName !== 'svg' || $root->namespaceURI !== self::SVG_NS )
				return false;

			$target	= new \DOMDocument( '1.0', 'UTF-8' );
			$copy 	= $target->createElementNS( self::SVG_NS, 'svg' );
			$target->appendChild( $copy );

			$count = 0;
			self::_copySvg( $target, $root, $copy, 0, $count );

			// Too many elements, or no shape drawn - groups, definitions and gradients alone are no icon
			$shapes = 0;
			foreach( [ 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon' ] as $shape )
				$shapes += $copy->getElementsByTagName( $shape )->length;

			if( $count < 0 || $shapes < 1 )
				return false;

			$out = $target->saveXML( $copy );

			return is_string( $out ) === true && $out !== '' ? $out. "\n" : false;
		}

		/**
		 *	Copy the attributes and the allowed children of one svg element into its clean copy
		 *
		 *	@param		\DOMDocument	$target
		 *	@param		\DOMElement		$from
		 *	@param		\DOMElement		$to
		 *	@param		int						$depth
		 *	@param		int						&$count			(reference) The shapes copied so far; -1 once there are too many
		 *
		 *	@return 	void
		 */
		private static function _copySvg( \DOMDocument $target, \DOMElement $from, \DOMElement $to, int $depth, int &$count ): void {

			foreach( $from->attributes as $attribute ) {

				// A prefixed attribute (xlink:href, xml:space, xmlns:x) is no attribute of the list
				if( $attribute->namespaceURI !== null || in_array( $attribute->localName, self::SVG_ATTRIBUTES, true ) === false || self::_safeValue( $attribute->localName, $attribute->value ) === false )
					continue;

				$to->setAttribute( $attribute->localName, $attribute->value );
			}

			foreach( $from->childNodes as $child ) {

				if( $child instanceof \DOMElement === false || $child->namespaceURI !== self::SVG_NS || in_array( $child->localName, self::SVG_ELEMENTS, true ) === false )
					continue;

				if( $count < 0 || ++$count > 2000 || $depth >= 24 ) {
					$count = -1;
					return;
				}

				$clone = $target->createElement( $child->localName );
				$to->appendChild( $clone );
				self::_copySvg( $target, $child, $clone, $depth + 1, $count );
			}
		}

		/**
		 *	Whether an attribute value may be copied: an id is a plain name, anything else holds the characters of
		 *	numbers, colours, paths and transforms and calls only the functions of the list - `url()` to an id
		 *
		 *	@param		string		$name
		 *	@param		string		$value
		 *
		 *	@return 	bool
		 */
		private static function _safeValue( string $name, string $value ): bool {

			if( strlen( $value ) > 20000 )
				return false;

			if( $name === 'id' )
				return preg_match( '/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $value ) === 1;

			if( preg_match( '/\A[A-Za-z0-9#.,%()\s+_-]*\z/', $value ) !== 1 )
				return false;

			if( preg_match_all( '/([A-Za-z]+)\s*\(/', $value, $calls ) > 0 )
				foreach( $calls[1] as $call )
					if( in_array( strtolower( $call ), self::SVG_FUNCTIONS, true ) === false )
						return false;

			// Every url( is a reference to an id of this picture
			return preg_match_all( '/url\s*\(/i', $value ) === preg_match_all( '/url\(#[A-Za-z][A-Za-z0-9_-]*\)/i', $value );
		}

		/**
		 *	The files of a new set, in memory: the four icons with alpha, the touch icon on a surface, the ico
		 *	container and the manifest drawn from the one that is there
		 *
		 *	@param		array 		&$appData
		 *	@param		string		$bytes				The validated raster picture
		 *	@param		string		$background		'' or '#rrggbb'
		 *	@param		string		$theme				'' or '#rrggbb'
		 *
		 *	@return 	array|false								name => bytes, or false where the picture cannot be decoded
		 */
		private static function _build( array &$appData, string $bytes, string $background, string $theme ): array|false {

			$master = \Nino\Images::square( $bytes, self::MIN_SIDE );
			if( $master === false )
				return false;

			$files = [];
			foreach( [ 16, 32, 192, 512 ] as $size )
				$files['favicon-'. $size. 'x'. $size. '.png'] = self::_encode( self::_scaled( $master, $size, null ) );

			$files['favicon.ico'] = self::_ico( [ 16 => $files['favicon-16x16.png'], 32 => $files['favicon-32x32.png'], 48 => self::_encode( self::_scaled( $master, 48, null ) ) ] );

			// A touch icon is drawn on a surface of its own and shown at a corner radius the device
			// chooses, so the picture keeps a tenth of the edge free on each side
			$files['apple-touch-icon.png'] = self::_encode( self::_scaled( $master, 180, $background === '' ? '#ffffff' : $background ) );
			imagedestroy( $master );

			$files['site.webmanifest'] = self::_manifest( $appData, $background, $theme );

			foreach( $files as $content )
				if( $content === '' )
					return false;

			return $files;
		}

		/**
		 *	The master at another edge. Without a surface the result keeps its alpha; with one it is a picture
		 *	of that colour with no alpha channel at all, the master set 10% in from every side
		 *
		 *	@param		\GdImage		$master
		 *	@param		int					$size
		 *	@param		string|null	$surface			'#rrggbb' for a touch icon, null for transparent
		 *
		 *	@return 	\GdImage
		 */
		private static function _scaled( \GdImage $master, int $size, ?string $surface ): \GdImage {

			$edge = imagesx( $master );

			if( $surface === null ) {
				$canvas = \Nino\Images::transparent( $size, $size ) ?: imagecreatetruecolor( $size, $size );
				imagecopyresampled( $canvas, $master, 0, 0, 0, 0, $size, $size, $edge, $edge );
				return $canvas;
			}

			$canvas = imagecreatetruecolor( $size, $size );
			imagefill( $canvas, 0, 0, (int) imagecolorallocate( $canvas, (int) hexdec( substr( $surface, 1, 2 ) ), (int) hexdec( substr( $surface, 3, 2 ) ), (int) hexdec( substr( $surface, 5, 2 ) ) ) );

			$inset = (int) round( $size / 10 );
			imagealphablending( $canvas, true );
			imagecopyresampled( $canvas, $master, $inset, $inset, 0, 0, $size - 2 * $inset, $size - 2 * $inset, $edge, $edge );

			return $canvas;
		}

		/**
		 *	The png of a canvas - an alpha channel only where it has one
		 *
		 *	@param		\GdImage		$canvas				Destroyed
		 *
		 *	@return 	string
		 */
		private static function _encode( \GdImage $canvas ): string {

			ob_start();
			imagepng( $canvas, null, 9 );
			$png = (string) ob_get_clean();
			imagedestroy( $canvas );

			return $png;
		}

		/**
		 *	An ico container with the pictures as png entries - no encoder of anyone else's: a header of three
		 *	16 bit numbers (0, 1 for icons, the count), one entry of sixteen bytes for each picture (width,
		 *	height, no palette, 0, one plane, 32 bits, the size of the png, its offset), then the pngs
		 *
		 *	@param		array 		$pngs					edge => png bytes
		 *
		 *	@return 	string
		 */
		private static function _ico( array $pngs ): string {

			$head 		= pack( 'vvv', 0, 1, count( $pngs ) );
			$offset 	= 6 + 16 * count( $pngs );
			$entries	= '';
			$images		= '';

			foreach( $pngs as $edge => $png ) {
				$entries 	.= pack( 'CCCCvvVV', $edge >= 256 ? 0 : $edge, $edge >= 256 ? 0 : $edge, 0, 0, 1, 32, strlen( $png ), $offset );
				$images 	.= $png;
				$offset 	+= strlen( $png );
			}

			return $head. $entries. $images;
		}

		/**
		 *	The web manifest the set ends with: the file that is there - or the base unit's, or a bare one -
		 *	with only these things touched. The 192 and 512 icons are added where it names none of that size;
		 *	an entry of those sizes names the file of the set; name and short name take the name of the site where they are empty; the two colours are written
		 *	where one was given. Everything else of it, in its order, is as it was
		 *
		 *	@param		array 		&$appData
		 *	@param		string		$background		'' or '#rrggbb'
		 *	@param		string		$theme				'' or '#rrggbb'
		 *
		 *	@return 	string										'' where it cannot be encoded
		 */
		private static function _manifest( array &$appData, string $background, string $theme ): string {

			// Decoded as objects, so that an empty object of the project stays one and not a list
			$manifest = null;
			$pretty 	= false;
			foreach( [ \Nino\Filesystem::path( $appData, \Nino\Images::FAVICON_DIR. '/site.webmanifest' ), self::_baseDir(). '/site.webmanifest' ] as $path ) {
				$raw 			= is_file( $path ) === true ? (string) file_get_contents( $path ) : '';
				$decoded 	= json_decode( $raw );
				if( $decoded instanceof \stdClass ) {
					$manifest = $decoded;
					$pretty 	= str_contains( trim( $raw ), "\n" );
					break;
				}
			}

			$manifest ??= (object) [ 'name' => '', 'short_name' => '', 'icons' => [], 'display' => 'standalone' ];

			$site = self::_siteName( $appData );
			foreach( [ 'name', 'short_name' ] as $key )
				if( $site !== '' && ( is_string( $manifest->$key ?? null ) === false || $manifest->$key === '' ) )
					$manifest->$key = $site;

			// An entry of the size the set makes names the file the set makes: an icon of that size under
			// another source would never be asked for. Its other fields are the project's
			$icons = is_array( $manifest->icons ?? null ) === true ? $manifest->icons : [];
			foreach( [ 192, 512 ] as $size ) {

				$sizes 	= $size. 'x'. $size;
				$source	= 'favicon-'. $sizes. '.png';
				$named 	= false;

				foreach( $icons as $icon )
					if( $icon instanceof \stdClass && ( $icon->sizes ?? null ) === $sizes ) {
						$icon->src = $source;
						$named = true;
					}

				if( $named === false )
					$icons[] = (object) [ 'src' => $source, 'sizes' => $sizes, 'type' => 'image/png' ];
			}
			$manifest->icons = $icons;

			if( $theme !== '' )
				$manifest->theme_color = $theme;

			if( $background !== '' )
				$manifest->background_color = $background;

			$json = json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ( $pretty === true ? JSON_PRETTY_PRINT : 0 ) );

			return $json === false ? '' : $json;
		}

		/**
		 *	The name of the site in its own language - the fill the page header and the mails read
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	string
		 */
		private static function _siteName( array &$appData ): string {

			$fills = \Nino\Html::getFills( $appData, \Nino\Locales::getNativeLocale( $appData ) );
			$name 	= $fills['[[/project/company/general/name]]'] ?? '';

			return is_string( $name ) === true ? trim( $name ) : '';
		}

		/**
		 *	The names a set is made of: the files of the base unit and the vector icon
		 *
		 *	@return 	array
		 */
		private static function _names(): array {
			return array_merge( \Nino\Images::FAVICON_FILES, [ \Nino\Images::FAVICON_SVG ] );
		}

		private static function _baseDir(): string {
			return dirname( __DIR__, 4 ). '/install/library/base/favicon';
		}

		/**
		 *	Write a whole set - name => bytes, null for a file that is to be gone - or none of it. The set that
		 *	stands is read first, kept for Undo where asked to, and put back as it was if a file cannot be
		 *	written. Each file is written beside its place and renamed over it, one at a time, after all of
		 *	them were made
		 *
		 *	@param		array 		&$appData
		 *	@param		array 		$files				name => bytes|null, for every name of the set
		 *	@param		bool			$keep					Keep the set that stands for Undo
		 *
		 *	@return 	bool
		 */
		private static function _install( array &$appData, array $files, bool $keep ): bool {

			$before = [];
			foreach( self::_names() as $name ) {
				$path 					= \Nino\Filesystem::path( $appData, \Nino\Images::FAVICON_DIR. '/'. $name );
				$before[$name]	= is_file( $path ) === true ? file_get_contents( $path ) : null;
				if( $before[$name] === false )
					return false;
			}

			// The copy of the set that stands is made beside the one that is kept: the kept one is only
			// replaced once every file is in place, so a write that fails leaves the way back as it was
			if( $keep === true && self::_keep( $appData, $before ) === false ) {
				self::_forget( $appData, self::BACKUP_NEXT );
				return false;
			}

			$written = [];
			foreach( $files as $name => $bytes ) {

				if( self::_put( $appData, $name, $bytes ) === false ) {
					foreach( array_reverse( $written ) as $undone )
						self::_put( $appData, $undone, $before[$undone] );
					self::_forget( $appData, self::BACKUP_NEXT );
					return false;
				}

				$written[] = $name;
			}

			if( $keep === true && self::_promote( $appData ) === false ) {
				foreach( array_reverse( $written ) as $undone )
					self::_put( $appData, $undone, $before[$undone] );
				self::_forget( $appData, self::BACKUP_NEXT );
				return false;
			}

			return true;
		}

		private static function _put( array &$appData, string $name, ?string $bytes ): bool {

			if( in_array( $name, self::_names(), true ) === false )
				return false;

			if( $bytes !== null )
				return \Nino\Filesystem::putFileContent( $appData, \Nino\Images::FAVICON_DIR. '/'. $name, $bytes );

			$path = \Nino\Filesystem::path( $appData, \Nino\Images::FAVICON_DIR. '/'. $name );

			return is_file( $path ) === false || @unlink( $path ) === true;
		}

		/**
		 *	Make the copy Undo will return to, beside the kept one - the single copy, the set before the
		 *	one replaced. The marker with the names that were there is written last, so a copy cut short
		 *	is not one Undo believes. It takes the place of the kept one in promote(), once the new set stands
		 *
		 *	@param		array 		&$appData
		 *	@param		array 		$set						name => bytes|null
		 *
		 *	@return 	bool
		 */
		private static function _keep( array &$appData, array $set ): bool {

			// What an earlier write left of its own copies
			self::_forget( $appData, self::BACKUP_NEXT );
			self::_forget( $appData, self::BACKUP_OLD );

			$present = [];
			foreach( $set as $name => $bytes ) {

				if( $bytes === null )
					continue;

				if( \Nino\Filesystem::putFileContent( $appData, self::BACKUP_NEXT. '/'. $name, $bytes ) === false )
					return false;

				$present[] = $name;
			}

			return \Nino\Filesystem::putFileContent( $appData, self::BACKUP_NEXT. '/'. self::BACKUP_MARK, [ 'at' => time(), 'files' => $present ] );
		}

		/**
		 *	Let the copy made by keep() replace the kept one: the old one is dropped and the directory
		 *	of the new one is renamed into its place
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	bool
		 */
		private static function _promote( array &$appData ): bool {

			$next = \Nino\Filesystem::path( $appData, self::BACKUP_NEXT );
			$kept = \Nino\Filesystem::path( $appData, self::BACKUP_DIR );
			$old 	= \Nino\Filesystem::path( $appData, self::BACKUP_OLD );

			if( is_dir( $next ) === false )
				return false;

			// The kept set is moved aside, not deleted: the new one takes its place, and only then is the old one dropped
			if( is_dir( $kept ) === true && @rename( $kept, $old ) === false )
				return false;

			if( @rename( $next, $kept ) === false ) {
				if( is_dir( $old ) === true )
					@rename( $old, $kept );
				return false;
			}

			self::_forget( $appData, self::BACKUP_OLD );

			return true;
		}

		/**
		 *	Drop what is in a kept set's directory - every file in it, whatever its name, not below it - and the directory.
		 *	The directories are fixed and owned by this class: a file left by a write that was cut short is no reason
		 *	for the directory to stand
		 *
		 *	@param		array 		&$appData
		 *	@param		string		$dir						BACKUP_DIR, BACKUP_NEXT or BACKUP_OLD
		 *
		 *	@return 	void
		 */
		private static function _forget( array &$appData, string $dir ): void {

			$path = \Nino\Filesystem::path( $appData, $dir );

			foreach( is_dir( $path ) === true ? ( scandir( $path ) ?: [] ) : [] as $name )
				if( is_file( $path. '/'. $name ) === true )
					@unlink( $path. '/'. $name );

			@rmdir( $path );
		}

		/**
		 *	The kept set: when it was kept and which names were in it - or null where there is none
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	array|null
		 */
		private static function _kept( array &$appData ): ?array {

			$path = \Nino\Filesystem::path( $appData, self::BACKUP_DIR. '/'. self::BACKUP_MARK );
			$mark = is_file( $path ) === true ? json_decode( (string) file_get_contents( $path ), true ) : null;

			if( is_array( $mark ) === false || is_int( $mark['at'] ?? null ) === false || is_array( $mark['files'] ?? null ) === false )
				return null;

			return [ 'at' => $mark['at'], 'files' => array_values( array_filter( $mark['files'], static fn( mixed $name ): bool => is_string( $name ) === true && in_array( $name, self::_names(), true ) === true ) ) ];
		}

		/**
		 *	The answer of status and of every write
		 *
		 *	@param		array 		&$appData
		 *
		 *	@return 	array
		 */
		private static function _status( array &$appData ): array {

			$files = [];
			foreach( self::PREVIEW as $name ) {

				$path = \Nino\Filesystem::path( $appData, \Nino\Images::FAVICON_DIR. '/'. $name );
				clearstatcache( true, $path );

				if( is_file( $path ) === false ) {
					$files[] = [ 'name' => $name, 'exists' => false ];
					continue;
				}

				$time = (int) filemtime( $path );
				$size = @getimagesize( $path );

				$files[] = [
					'name' 		=> $name,
					'exists' 	=> true,
					'url' 		=> \Nino\Filesystem::url( $appData, \Nino\Images::FAVICON_DIR. '/'. $name ). '?v='. $time,
					'bytes' 	=> (int) filesize( $path ),
					'time' 		=> $time,
					'width' 	=> $size === false ? 0 : $size[0],
					'height' 	=> $size === false ? 0 : $size[1],
				];
			}

			$manifestPath = \Nino\Filesystem::path( $appData, \Nino\Images::FAVICON_DIR. '/site.webmanifest' );
			$manifest 		= is_file( $manifestPath ) === true ? json_decode( (string) file_get_contents( $manifestPath ), true ) : null;
			$manifest 		= is_array( $manifest ) === true ? $manifest : [];
			$kept 				= self::_kept( $appData );

			return [
				'files' 				=> $files,
				'stamp' 				=> \Nino\Images::faviconStamp( $appData ),
				'min' 					=> self::MIN_SIDE,
				'undo' 					=> $kept === null ? null : $kept['at'],
				'base' 					=> is_file( self::_baseDir(). '/favicon.ico' ),
				'themeColor' 		=> is_string( $manifest['theme_color'] ?? null ) === true ? $manifest['theme_color'] : '',
				'backgroundColor'=> is_string( $manifest['background_color'] ?? null ) === true ? $manifest['background_color'] : '',
			];
		}
	}
}
