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
	 *	Slots							Manage image slots (/nino/html/images): create, edit label/
	 *												width/height, delete - the "set" half of what the Images panel
	 *												edits ("values" half: which file currently
	 *												fills a slot). Same split as the Types tab and the Elements panel. Only
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

		// What usage() says about a slot no template mentions
		public const array NO_USAGE = [ 'templates' => [], 'pages' => [] ];

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

		/**
		 *	Whether a slot of this size is one an upload could ever fill.
		 *
		 *	A slot's width and height are the exact canvas \Nino\Images::process()
		 *	renders onto, and the kernel caps the picture it is prepared to hold
		 *	in memory at \Nino\Images::MAX_SOURCE_PIXELS - the target buffer is
		 *	the same kind of allocation as the source one that constant guards.
		 *	Above it the slot is saved and then cannot be filled: every upload
		 *	comes back as "the image could not be processed", which sends the person
		 *	looking at their photograph rather than at the size they typed. Total
		 *	pixels rather than either edge, for the same reason the kernel counts
		 *	them that way.
		 *
		 *	@param		int				$width
		 *	@param		int				$height
		 *
		 *	@return 	bool
		 */
		private static function fitsUploadLimit( int $width, int $height ): bool {
			return $width * $height <= \Nino\Images::MAX_SOURCE_PIXELS;
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

			$usage = self::usage( $appData );

			$slots = [];
			foreach( ( $appData['/nino/html/images'] ?? [] ) as $uri => $slot )
				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'hasImage' 	=> ( $slot['filename'] ?? null ) !== null,
					'usage' 		=> $usage[$uri] ?? self::NO_USAGE,
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

			if( self::fitsUploadLimit( $width, $height ) === false ) {
				\Nino\Http::fail( $request, 400, 'target size is larger than an upload can produce' );
				return;
			}

			/*	This one slot, in config.php as it is now. writeContentData()
				would replace the whole /nino/html/images key with the copy this
				request booted with, and an alt text or a file saved since - by
				the Images panel, in another request - would be put back to what
				it was at boot. Only the three fields this form edits are set,
				the way \Nino\Images::setSlotAlt() sets the alt texts alone.	*/
			$stored = [];
			$gone 	= false;
			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( mixed $content ) use ( $uri, $label, $width, $height, &$stored, &$gone ): ?array {

				if( is_array( $content ) === false || is_array( $content['/nino/html/images'][$uri] ?? null ) === false ) {
					$gone = true;
					return null;
				}

				$content['/nino/html/images'][$uri]['label'] 	= $label;
				$content['/nino/html/images'][$uri]['width'] 	= $width;
				$content['/nino/html/images'][$uri]['height'] = $height;

				$stored = $content['/nino/html/images'][$uri];

				return $content;
			} );

			if( $written === false ) {
				if( $gone === true )
					\Nino\Http::fail( $request, 404, 'unknown slot' );
				else
					\Nino\Http::fail( $request, 500, 'could not save the slot' );
				return;
			}

			$appData['/nino/html/images'][$uri] = $stored;

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

			if( self::fitsUploadLimit( $width, $height ) === false ) {
				\Nino\Http::fail( $request, 400, 'target size is larger than an upload can produce' );
				return;
			}

			if( isset( $appData['/nino/html/images'][$uri] ) === true ) {
				\Nino\Http::fail( $request, 409, 'slot already exists' );
				return;
			}

			// This one slot added to config.php as it is now, see apiSave()
			$slot = [
				'label' 		=> $label,
				'width' 		=> $width,
				'height' 		=> $height,
				'filename' 	=> null,
			];
			$exists = false;
			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( mixed $content ) use ( $uri, $slot, &$exists ): ?array {

				if( is_array( $content ) === false )
					return null;

				if( isset( $content['/nino/html/images'][$uri] ) === true ) {
					$exists = true;
					return null;
				}

				if( is_array( $content['/nino/html/images'] ?? null ) === false )
					$content['/nino/html/images'] = [];

				$content['/nino/html/images'][$uri] = $slot;

				return $content;
			} );

			if( $written === false ) {
				if( $exists === true )
					\Nino\Http::fail( $request, 409, 'slot already exists' );
				else
					\Nino\Http::fail( $request, 500, 'could not save the slot' );
				return;
			}

			$appData['/nino/html/images'][$uri] = $slot;

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

			if( isset( $appData['/nino/html/images'][$uri] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			/*	The record goes first and the file second, because the two
				failures are not the same size. A write that does not happen - a
				config.php that cannot be locked, a disk with nothing left on it -
				used to leave the slot standing in config.php with its image
				already deleted: a public page rendering a broken <img>, and a
				panel with nothing left to re-upload over, since the slot still
				believes it has a file. The other way round the worst case is a
				file nobody references any more, left in images/ - no scan reports
				it, but no page breaks over it.

				This one slot is taken out of config.php as it is now, see
				apiSave(), and it is the slot as it stands there whose file is
				deleted: an upload that finished since boot named another one.	*/
			$slot = [];
			$gone = false;
			$written = \Nino\Filesystem::mutate( $appData, '/config.php', static function( mixed $content ) use ( $uri, &$slot, &$gone ): ?array {

				if( is_array( $content ) === false || is_array( $content['/nino/html/images'][$uri] ?? null ) === false ) {
					$gone = true;
					return null;
				}

				$slot = $content['/nino/html/images'][$uri];
				unset( $content['/nino/html/images'][$uri] );

				return $content;
			} );

			if( $written === false ) {
				if( $gone === true ) {
					unset( $appData['/nino/html/images'][$uri] );
					\Nino\Http::fail( $request, 404, 'unknown slot' );
				}
				else
					\Nino\Http::fail( $request, 500, 'could not save the slot list' );
				return;
			}

			unset( $appData['/nino/html/images'][$uri] );

			if( ( $slot['filename'] ?? null ) !== null )
				\Nino\Images::delete( $appData, $slot['filename'] );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Where every image slot is used: the templates that show it with
		 *	[image <uri>] (or [image uri="<uri>"]) and the pages that end up
		 *	rendering it. A page is a GET route as the project serves it,
		 *	feature routes included, whose body shows the slot itself or
		 *	through the [template /templates/<name>] includes that body pulls
		 *	in, however deep (a visited set and a depth cap keep a template
		 *	that includes itself from running away). A body that names the
		 *	template by language - [[/nino/http/response/locale]], as a
		 *	hand-written route may - is read once per available language, since
		 *	there is no single template it points to. A page is named by its
		 *	[[/_nino/webpage<uri>/name]] in the workbench's language, or by its
		 *	http uri where it has none.
		 *
		 *	A slot is a key of the result only where something uses it, in a
		 *	template or on a page: no key means unused. Templates are
		 *	templates/*.tpl as _scanMissing() reads them - a template that no
		 *	route renders is listed under 'templates' alone, which is how the
		 *	Slots tab can say that a slot is used in a file nobody sees
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ slot uri => [ 'templates' => [ name, ... ],
		 *																		'pages' => [ [ 'httpUri', 'name' ], ... ] ], ... ]
		 */
		public static function usage( array &$appData ): array {

			$locales	= \Nino\Locales::getAvailableLocales( $appData );
			$dir			= \Nino\Filesystem::path( $appData, '/templates' );

			// Every template once, by name without the extension
			$sources = [];
			foreach( glob( $dir. '/*.tpl' ) ?: [] as $file ) {
				$content = is_file( $file ) === true ? file_get_contents( $file ) : false;
				if( $content !== false )
					$sources[ basename( $file, '.tpl' ) ] = $content;
			}

			$found = [];
			foreach( $sources as $name => $content )
				foreach( array_keys( self::_scanSource( $content, $locales )['images'] ) as $uri )
					$found[$uri]['templates'][] = $name;

			$text = [];
			$names = static function( array &$appData, string $uri, string $httpUri ) use ( &$text ): string {

				$locale = \Nino\Admin\Admin::sessionLocale( $appData );
				$text[$locale] ??= \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
				$name = (string) ( $text[$locale]['[[/_nino/webpage'. $uri. '/name]]'] ?? '' );

				return $name === '' ? $httpUri : $name;
			};

			foreach( ( $appData['/nino/http/routes'] ?? [] ) as $routeKey => $route ) {

				if( str_starts_with( (string) $routeKey, 'GET://' ) === false || is_array( $route ) === false )
					continue;

				$httpUri = substr( (string) $routeKey, strlen( 'GET:/' ) );
				$images = [];
				$visited = [];
				self::_scanPage( (string) ( $route['body'] ?? '' ), $locales, $sources, $visited, $images, 0 );

				foreach( array_keys( $images ) as $uri )
					$found[$uri]['pages'][] = [ 'httpUri' => $httpUri, 'name' => $names( $appData, (string) ( $route['uri'] ?? $httpUri ), $httpUri ) ];
			}

			$usage = [];
			foreach( $found as $uri => $where )
				$usage[ (string) $uri ] = [
					'templates'	=> array_values( array_unique( $where['templates'] ?? [] ) ),
					'pages'			=> $where['pages'] ?? [],
				];

			return $usage;
		}

		/**
		 *	The slots a page shows: those of its own source and of every
		 *	template it includes, however deep
		 *
		 *	@param		string		$source				A route body or a template
		 *	@param		array			$locales			Available locales
		 *	@param		array			$sources			Every template, name => content
		 *	@param		array			&$visited			Templates already read for this page
		 *	@param		array			&$images			Slot uris found, as keys
		 *	@param		int				$depth
		 *
		 *	@return 	void
		 */
		private static function _scanPage( string $source, array $locales, array $sources, array &$visited, array &$images, int $depth ): void {

			$scan = self::_scanSource( $source, $locales );

			foreach( $scan['images'] as $uri => $true )
				$images[$uri] = true;

			if( $depth >= 20 )
				return;

			foreach( array_keys( $scan['includes'] ) as $name ) {

				if( isset( $visited[$name] ) === true || isset( $sources[$name] ) === false )
					continue;

				$visited[$name] = true;
				self::_scanPage( $sources[$name], $locales, $sources, $visited, $images, $depth + 1 );
			}
		}

		/**
		 *	The [image] slots and the [template /templates/<name>] includes one
		 *	source names, read once for each language where the source is
		 *	about the language ([[/nino/http/response/locale]])
		 *
		 *	@param		string		$source
		 *	@param		array			$locales			Available locales
		 *
		 *	@return 	array										[ 'images' => [ uri => true ], 'includes' => [ template name => true ] ]
		 */
		private static function _scanSource( string $source, array $locales ): array {

			$fill			= '[[/nino/http/response/locale]]';
			$variants	= str_contains( $source, $fill ) === true
				? array_map( static fn( string $locale ): string => str_replace( $fill, $locale, $source ), $locales )
				: [ $source ];

			$images		= [];
			$includes	= [];

			foreach( $variants as $variant ) {

				if( preg_match_all( '/\[image(?: ([^\]]*))?\]/', $variant, $matches ) > 0 )
					foreach( $matches[1] as $arguments ) {

						// Split the way Html::_doShortcode() does, and read the
						// slot the way Modules\Images does: the first bare
						// argument, or uri="..."
						preg_match_all( '/\ ([^\ \=]*)(\=[\"]([^\"]*)[\"])?/i', ' '. $arguments. ' ', $attr );
						$args = [];
						foreach( $attr[1] as $id => $key )
							if( $attr[2][$id] !== '' )
								$args[$key] = $attr[3][$id];
							else
								$args[] = $key;

						// The trailing space given to the pattern adds one empty
						// bare argument at the end, which is not an argument
						array_pop( $args );

						$uri = (string) ( $args[0] ?? ( $args['uri'] ?? '' ) );
						if( $uri !== '' )
							$images[$uri] = true;
					}

				// A flat directory: the names a template file can have
				if( preg_match_all( '#\[template /templates/([A-Za-z0-9._-]+)[\] ]#', $variant, $matches ) > 0 )
					foreach( $matches[1] as $name )
						$includes[$name] = true;
			}

			return [ 'images' => $images, 'includes' => $includes ];
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
		 *	missing a slot - the Dashboard tile, see summary()
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

				$content = is_file( $file ) === true ? file_get_contents( $file ) : false;
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
