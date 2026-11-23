<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Assets				see _nino/Nino/Modules/Modules.php for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules 						Optional Modules
	 *	Assets							A html shortcode for including assets
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */

	class Assets {

		/**
		 *	The first rule of every stylesheet bundle of the site: the four layers in
		 *	the order that decides the cascade - the kernel's reset, then its
		 *	components, then the theme, then the project. A layer that comes later
		 *	overrules an earlier one whatever the specificity of the rules in them.
		 *	The bundles of the workbench (/_admin/) are no part of it.
		 */
		public const string LAYERS = '@layer nino.reset, nino.components, nino.theme, project;';

		// What follow() and need() keep for a request, and the token of the marks doShortcode() leaves behind a link
		private const string FOLLOWERS	= './nino/assets/followers';
		private const string NEEDED			= './nino/assets/needed';
		private const string MARK				= './nino/assets/mark';

		// The tag that loads a bundle, by the kind of file. A callback of '/nino/shortcodes/assets/output/...' may answer another one,
		// which stays the template of $_template for the rest of the process (see _link()); this is the one that is always the plain tag
		private const array LINKS = [
			'css'							=> '<link rel="stylesheet" href="[[filename]]" type="text/css" />',
			'js'							=> '<script src="[[filename]]"></script>',
		];

		private static
			$_template				= self::LINKS,
			$_minifyRegex 		= [
				'css'							=> [
					[
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
						// A zero keeps its unit: in calc() a length and a number do not add up, and a token that holds 0px is
						// read by a calc() somewhere else. Zeros are folded into one for a property, never for a token (--name),
						// whose value is whatever its reader makes of it
						'#(?<=[\{;])([a-z][a-z-]*):(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
						'#(background-position):0(?=[;\}])#si',
						'#(?<=[\s:,\-])0+\.(\d+)#s',
						'#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
						'#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
						'#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
						'#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
						'#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s',
					], [
						'$1',
						'$1$2$3$4$5$6$7',
						'$1:0',
						'$1:0 0',
						'.$1',
						'$1$3',
						'$1$2$4$5',
						'$1$2$3',
						'$1:0',
						'$1$2',
					],
				],
				'js'				=> [
					[
						'#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
						'#;+\}#',
						'#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
						'#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i',
					], [
						'$1',
						'$1$2',
						'}',
						'$1$3',
						'$1.$3',
					]
				]
			];

		/**
		 *	Init module
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'assets', [ self::class, 'doShortcode' ], false );
			\Nino\Callbacks::registerCallback( $appData, '/nino/html/render', [ self::class, 'callbackRender' ] );
		}

		/**
		 *	Let a bundle follow another one on the pages that ask for it: where a template writes the link of $after
		 *	(the style bundle in the head, the script bundle at the end), the link of $bundle stands behind it - if
		 *	need() has been called for $bundle by the time the page is finished, and not at all if it has not. This is
		 *	how a bundle is loaded only by the pages that use it, without a template naming it: the head of a page is
		 *	drawn before the body that decides.
		 *
		 *	The bundle is a bundle like any other - its files are listed with \Nino\Html::addAsset() under its own
		 *	name, and it is built, stamped and cached as the others are. A bundle with no files has no link. Called
		 *	from an init(), once for a request; a second call with the same pair adds nothing.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$after				The bundle whose link it follows, as the shortcode names it: '/.cache/style.css'
		 *	@param		string		$bundle				The bundle that follows, the same kind of file: '/.cache/form.css'
		 *
		 *	@return 	void
		 */
		public static function follow( array &$appData, string $after, string $bundle ): void {

			if( in_array( $bundle, $appData[self::FOLLOWERS][$after] ?? [], true ) === false )
				$appData[self::FOLLOWERS][$after][] = $bundle;
		}

		/**
		 *	Say that the page of this request uses a bundle that follows another one (see follow()): its link is written
		 *	when the page is finished, wherever the page named the bundle it follows. Any time while the page is drawn
		 *	- the template that comes first need not know what comes after it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$bundle				The bundle, as follow() has it
		 *
		 *	@return 	void
		 */
		public static function need( array &$appData, string $bundle ): void {
			$appData[self::NEEDED][$bundle] = true;
		}

		/**
		 *	Finish the page: the mark that doShortcode() left behind a link becomes the link of the bundle that follows
		 *	it where the page needs that bundle, and nothing where it does not. A callback of '/nino/html/render', so
		 *	it runs once the whole page is drawn - and sees no mark in a piece of html that is no page
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$html					The finished page
		 *
		 *	@return 	string
		 */
		public static function callbackRender( array &$appData, string $html ): string {

			$token = $appData[self::MARK] ?? '';

			if( $token === '' )
				return $html;

			foreach( $appData[self::FOLLOWERS] ?? [] as $bundles )
				foreach( $bundles as $bundle ) {

					$mark	= self::_mark( $token, $bundle );
					$at		= strpos( $html, $mark );

					if( $at === false )
						continue;

					// A bundle with no files has no link: a request for a file that is not there only fails. The first
					// mark takes the link, a second call of the same bundle in the page is none
					$link	= ( $appData[self::NEEDED][$bundle] ?? false ) === true && \Nino\Html::getAssets( $appData, $bundle ) !== [] ? self::_link( $appData, $bundle, false ) : '';
					$html	= substr_replace( $html, $link, $at, strlen( $mark ) );
					$html	= str_replace( $mark, '', $html );
				}

			return $html;
		}

		/**
		 *	What stands in the page where the link of a following bundle will be: a comment that holds a token of this
		 *	request. The token is made when the first mark is, from random bytes, so that nothing a page is made of
		 *	- a text of the editor, the value of a field - can write a mark of its own
		 *
		 *	@param		string		$token				The token of the request
		 *	@param		string		$bundle				The bundle that follows
		 *
		 *	@return 	string
		 */
		private static function _mark( string $token, string $bundle ): string {
			return '<!--nino-bundle-'. $token. ' '. $bundle. '-->';
		}


		/**
		 *	Execute assets shortcode
		 *
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		array			$args 						Shortcode arguments
		 *
		 *	@return 	string											Rendered html code
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$targetFile	= $args[0] ?? '';
			$link				= self::_link( $appData, $targetFile );

			// The bundles that follow this one wait for the end of the page, where it is known whether it needs them
			if( $link !== '' && isset( $appData[self::FOLLOWERS][$targetFile] ) === true ) {

				if( ( $appData[self::MARK] ?? '' ) === '' )
					$appData[self::MARK] = bin2hex( random_bytes( 8 ) );

				foreach( $appData[self::FOLLOWERS][$targetFile] as $bundle )
					$link .= self::_mark( $appData[self::MARK], $bundle );
			}

			return $link;
		}

		/**
		 *	The tag that loads a bundle: the bundle is built first where its files changed
		 *
		 *	@param		array 		&$appData					(reference) Array with current app data
		 *	@param		string		$targetFile				The bundle as the shortcode names it, eg. '/.cache/style.css'
		 *	@param		bool			$asked						Whether the callbacks of '/nino/shortcodes/assets/output/...' may change the tag. They are written for the bundle a
		 *																			template names - a preview that puts its own stylesheet in the place of the link, a page that has no script - and
		 *																			the link of a bundle that follows another is the plain one: such a callback would draw its answer a second time
		 *
		 *	@return 	string											Rendered html code, '' for a name that is no bundle
		 */
		private static function _link( array &$appData, string $targetFile, bool $asked = true ): string {

			$pathinfo			= pathinfo( $targetFile );
			$assetFiles 	= \Nino\Html::getAssets( $appData, $targetFile );

			// A missing/extension-less argument (eg. a bare "[assets]") renders
			// nothing instead of erroring on the absent pathinfo key
			if( isset( $pathinfo['extension'] ) === false || isset( self::$_template[$pathinfo['extension']] ) === false )
				return '';

			// Compare hash / create cache
			if( empty( $assetFiles ) === false && self::_getFileHashes( $appData, $assetFiles, self::_layered( $appData, $pathinfo ) ) !== self::_readHashLine( $appData, $targetFile ) )
				self::_createCachefile( $appData, $pathinfo, $assetFiles );

			// Fill template
			$content	= $asked === true
				? \Nino\Callbacks::doCallbacks( $appData, '/nino/shortcodes/assets/output/'. $pathinfo['extension'], self::$_template[$pathinfo['extension']] )
				: self::LINKS[$pathinfo['extension']];

			/*	The url carries the bundle's own hash - the sha1
				_createCachefile() writes into its first line, read back here
				after any rebuild above. Without it a regenerated bundle keeps
				the address a browser may still be holding a copy of: the page
				asks for /public/.cache/style.css, gets the stylesheet from
				before the change, and the way out is a hard reload nobody knows
				to do. As a query rather than a name, so the file on disk stays
				the one file, nothing piles up and no rewrite rule is needed	*/
			$version = self::_readHashLine( $appData, $targetFile );

			return str_replace( [
				'[[filename]]',
			], [
				\Nino\Filesystem::url( $appData, $targetFile ). ( $version !== '' ? '?v='. substr( $version, 0, 12 ) : '' ),
			], $content );
		}

		/**
		 *	Create a unique filehash for all files in a asset dir
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$assetFiles		Asset files array
		 *	@param		bool					$layered			Whether the bundle puts each file in a layer: the layer each file gets is part of the hash
		 *
		 *	@return 	string											Filehash
		 */
		private static function _getFileHashes( array &$appData, array $assetFiles, bool $layered ): string {

				$hash 	= '';

				// Loop through files and collect file details - resolved one
				// by one, since a bundle mixes this project's own sources
				// (/assets/..., private) with a tool folder's (/_nino/Nino.css)
				foreach( $assetFiles as $file ) {
					$filepath = \Nino\Filesystem::path( $appData, $file );

					if( is_file( $filepath ) === true )
						$hash .= $filepath. '|'. filesize( $filepath ). '|'. filemtime(  $filepath ). ( $layered === true ? '|'. ( self::_layerOf( $appData, $filepath ) ?? '' ) : '' );
				}

				return sha1( $hash );
		}


		/**
		 *	Read first line of an rendered assetfile
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$targetFile		Target asset file to hash
		 *
		 *	@return 	string											Filehash
		 */
		private static function _readHashLine( array &$appData, string $targetFile ): string {

				$path 	= \Nino\Filesystem::path( $appData, $targetFile );

				if( is_file( $path ) === false )
					return '';

				$handle = fopen( $path , 'r' );

				if( $handle === false )
					return '';

				$line = fgets( $handle );
				fclose( $handle );

				if( $line === false )
					return '';

				// Strip the '/**' / '**/' wrapper _createCachefile() writes
				// around the hash, returning the bare sha1() the caller compares
				// against.
				$line = trim( $line );

				if( str_starts_with( $line, '/**' ) === false || str_ends_with( $line, '**/' ) === false )
					return '';

				return substr( $line, 3, -3 );
		}

		/**
		 *	The stylesheet bundle of a target as a string, built in memory: the
		 *	layers, the files in them, the repair of a source whose braces do not
		 *	balance and the first line with the stamp are the ones of the file
		 *	_createCachefile() writes, from the same code (_compose()). With an empty
		 *	$swap the string is the file's bytes. A file named in $swap is
		 *	replaced by the css given and stands in the layer of the file it
		 *	replaces; the stamp of such a bundle differs from the public one. Nothing
		 *	is written and the public bundle is left as it is.
		 *
		 *	A target that is no stylesheet, a bundle without files, a path in $swap
		 *	that is no file of the bundle as \Nino\Html::getAssets() lists it (a path
		 *	outside the project is none) and a replacement that is no string give
		 *	'' and a warning - the call is refused as a whole.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$target				The bundle as the shortcode names it, eg. '/public/.cache/style.css'
		 *	@param		array			$swap					Path of a file of the bundle => the css that stands in its place
		 *
		 *	@return 	string									The bundle, '' if refused
		 */
		public static function stylesheet( array &$appData, string $target, array $swap = [] ): string {

			$pathinfo		= pathinfo( $target );
			$assetFiles	= \Nino\Html::getAssets( $appData, $target );

			if( ( $pathinfo['extension'] ?? '' ) !== 'css' || $assetFiles === [] )
				return '';

			foreach( $swap as $file => $css ) {
				if( in_array( (string) $file, $assetFiles, true ) === false || is_string( $css ) === false ) {
					trigger_error( 'Assets: '. addcslashes( (string) $file, "\0" ). ' is no file of the bundle '. addcslashes( $target, "\0" ). ' with a css to put in its place, the bundle is refused', E_USER_WARNING );
					return '';
				}
			}

			return self::_compose( $appData, $pathinfo, $assetFiles, $swap );
		}

		/**
		 *	Write all asset files from source dir into an target file
		 *
		 *	A stylesheet bundle of the site (not the workbench's, see _layered())
		 *	begins with the four layers and holds every file in its own: see
		 *	_layerOf() for which and _layer() for how.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$pathinfo			Pathinfo of target asset file
		 *	@param		array					$assetFiles		Asset files array
		 *
		 *	@return 	void
		 */
		private static function _createCachefile( array &$appData, array $pathinfo, array $assetFiles ): void {

			\Nino\Filesystem::putFileContent( $appData, $pathinfo['dirname']. '/'. $pathinfo['basename'], self::_compose( $appData, $pathinfo, $assetFiles, [] ) );
		}

		/**
		 *	The content of a bundle file, first line included - the one way a bundle
		 *	is built, for the file (_createCachefile()) and for the string (stylesheet())
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$pathinfo			Pathinfo of target asset file
		 *	@param		array					$assetFiles		Asset files array
		 *	@param		array					$swap					Path of a file => the text that stands in its place, already checked
		 *
		 *	@return 	string											The bundle
		 */
		private static function _compose( array &$appData, array $pathinfo, array $assetFiles, array $swap ): string {

			$layered	= self::_layered( $appData, $pathinfo );

			// Loop through files and collect content
			$content	= '';
			$imports	= '';
			foreach( $assetFiles AS $file ) {
				$source = $swap[$file] ?? \Nino\Filesystem::getFileContent( $appData, $file, '' );

				$content .= $layered === true && is_string( $source ) === true
					? self::_layer( $source, self::_layerOf( $appData, \Nino\Filesystem::path( $appData, $file ) ), $file, $imports )
					: $source;
			}

			// The layers are declared before any rule, and the @import rules a source
			// had stand between them and the first block: an @import is void behind a rule
			if( $layered === true )
				$content = self::LAYERS. PHP_EOL. $imports. $content;

			// Deliberately NOT Html::renderHtml(): that pipeline also runs admin-
			// editable textfills (text/global.php, per-locale text files) and every
			// registered shortcode - including ones that take admin-controlled
			// arguments. Since this content is written out as a static file served
			// straight from the webroot, that would turn any admin-editable fill
			// into stored XSS on every page that includes the bundle. Only the
			// developer/kernel-controlled tokens the shipped assets actually use are
			// substituted here - both of them: form.js falls back to
			// '[[/nino/dir]]/.form' for a form that carries no action of its own,
			// and with only the public one handled that shipped as a literal.
			$content = str_replace(
				[ '[[/nino/public]]', '[[/nino/dir]]' ],
				[ \Nino\Filesystem::getPublicDir( $appData ), \Nino\Filesystem::getDir( $appData ) ],
				$content
			);

			// Minify
			if( substr( $pathinfo['filename'], -4 ) === '.min' )
				$content = trim( preg_replace( self::$_minifyRegex[$pathinfo['extension']][0], self::$_minifyRegex[$pathinfo['extension']][1], $content ), ';' );

			// Add prefix: the stamp of the files, and of what was put in place of one
			$stamp				= self::_getFileHashes( $appData, $assetFiles, $layered );

			if( $swap !== [] )
				$stamp = sha1( $stamp. serialize( $swap ) );

			return '/**'. $stamp. '**/'. PHP_EOL. trim( $content, ';' );
		}

		/**
		 *	Whether a bundle puts its files in layers: every stylesheet bundle of the
		 *	site. The workbench has its own order and is left as it is - its bundles
		 *	are the ones written below /_admin/.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$pathinfo			Pathinfo of target asset file
		 *
		 *	@return 	bool
		 */
		private static function _layered( array &$appData, array $pathinfo ): bool {

			return $pathinfo['extension'] === 'css'
				&& str_starts_with( \Nino\Filesystem::path( $appData, $pathinfo['dirname']. '/'. $pathinfo['basename'] ), \Nino\Filesystem::path( $appData, '/_admin' ). '/' ) === false;
		}

		/**
		 *	The layer a stylesheet of the site's bundle stands in, by the file it is:
		 *	the project's assets/theme.css is 'nino.theme', any other file of the
		 *	project's assets/ 'project', and what a feature or a module adds
		 *	'nino.components'. A project never writes a layer, the bundle puts the
		 *	file in it. Nino.css is the layer nino.reset and holds it itself - the
		 *	order of the four is written by the bundle, not by the file: null, it is
		 *	taken as it is.
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		string				$filepath			The absolute path of the file, as \Nino\Filesystem::path() resolves it
		 *
		 *	@return 	string|null									The layer, null for the file that carries its own
		 */
		private static function _layerOf( array &$appData, string $filepath ): ?string {

			if( $filepath === \Nino\Filesystem::path( $appData, '/_nino/Nino.css' ) )
				return null;

			if( $filepath === \Nino\Filesystem::path( $appData, '/assets/theme.css' ) )
				return 'nino.theme';

			return str_starts_with( $filepath, \Nino\Filesystem::path( $appData, '/assets' ). '/' ) === true ? 'project' : 'nino.components';
		}

		/**
		 *	One stylesheet made ready for the bundle: in a block of its layer, which
		 *	nothing it holds can leave. The source is read once, as a css parser
		 *	reads it - comments, strings, escapes and an unquoted url() are skipped,
		 *	braces counted - and
		 *	- a source whose braces do not balance is repaired as a browser reads it,
		 *	  and stays in its block: a } that closes nothing is left out, a { that
		 *	  is never closed is closed at the end, and a comment, string or url()
		 *	  that does not end ends where the source does. Wrapped as it is, such a
		 *	  source would close its own block and put what follows in another
		 *	  layer, or swallow the files behind it. One warning says which faults
		 *	  the source held;
		 *	- an @charset is dropped: it is void anywhere but the first byte of a file,
		 *	  and the bundle is served with its encoding;
		 *	- an @import is taken out of the block and handed back in $imports with
		 *	  layer(<its layer>), to stand in front of every rule of the bundle as
		 *	  the language wants it. A layer the import names itself is replaced.
		 *	@font-face, @keyframes and @property stand in the block as they are.
		 *
		 *	@param		string				$css					The source
		 *	@param		string|null		$layer				Its layer, null for a file that holds its own (see _layerOf())
		 *	@param		string				$file					The path as the bundle lists it, for the warning
		 *	@param		string				&$imports			(reference) The @import rules of the bundle so far
		 *
		 *	@return 	string											The block, '' for an empty source
		 */
		private static function _layer( string $css, ?string $layer, string $file, string &$imports ): string {

			$css			= (string) preg_replace( '/^\xEF\xBB\xBF/', '', $css );
			$length		= strlen( $css );
			$body			= '';
			$found		= '';
			$copied		= 0;
			$depth		= 0;
			$i				= 0;
			$space		= " \t\r\n\f";
			$faults		= [];
			$open			= '';

			while( $open === '' && ( $i += strcspn( $css, '/"\'(\\{}@', $i ) ) < $length ) {

				$char = $css[$i];

				if( $char === '/' ) {
					if( ( $css[$i + 1] ?? '' ) !== '*' )
						$i++;
					elseif( ( $end = strpos( $css, '*/', $i + 2 ) ) === false ) {
						$faults[]	= 'a comment that does not end';
						$open			= '*/';
					}
					else
						$i = $end + 2;
				}
				elseif( $char === '"' || $char === "'" ) {
					$i++;
					while( true ) {
						$i += strcspn( $css, $char. "\\\r\n\f", $i );

						if( $i >= $length ) {
							$faults[]	= 'a string that does not end';
							$open			= $char;
							break;
						}
						if( $css[$i] === "\\" )
							$i += substr( $css, $i + 1, 2 ) === "\r\n" ? 3 : 2;
						elseif( $css[$i] === $char ) {
							$i++;
							break;
						}
						else
							break;
					}
				}
				elseif( $char === '(' ) {
					$i++;

					// url( without a quote: the bytes up to the ) are one token, { and } among them
					if( $i >= 4 && strcasecmp( substr( $css, $i - 4, 3 ), 'url' ) === 0 && in_array( $css[$i + strspn( $css, $space, $i )] ?? '', [ '"', "'" ], true ) === false ) {
						if( preg_match( '/\G(?:[^)\\\\]++|\\\\.)*+\)/s', $css, $match, 0, $i ) !== 1 ) {
							$faults[]	= 'a url() that does not end';
							$open			= ')';
						}
						else
							$i += strlen( $match[0] );
					}
				}
				elseif( $char === '\\' )
					$i += 2;
				elseif( $char === '{' ) {
					$depth++;
					$i++;
				}
				elseif( $char === '}' ) {
					if( $depth === 0 ) {
						$faults[]	= 'a } that closes no block';
						$body		 .= substr( $css, $copied, $i - $copied );
						$copied		= ++$i;
					}
					else {
						$depth--;
						$i++;
					}
				}
				elseif( $depth === 0 && preg_match( '/\G@(charset|import)(?![\w-])/i', $css, $match, 0, $i ) === 1 ) {
					$string = '"(?:[^"\\\\\r\n\f]|\\\\.)*+"|\'(?:[^\'\\\\\r\n\f]|\\\\.)*+\'';

					if( strtolower( $match[1] ) === 'charset' )
						$statement = preg_match( '/\G@charset['. $space. ']*+(?:'. $string. ')['. $space. ']*+;?/i', $css, $cut, 0, $i ) === 1;
					else
						$statement = preg_match( '/\G@import['. $space. ']*+(url\(['. $space. ']*+(?:'. $string. '|(?:[^)\\\\]|\\\\.)*+)\)|'. $string. ')([^;{}]*+);/is', $css, $cut, 0, $i ) === 1;

					if( $statement === false )
						$i++;
					else {
						if( isset( $cut[2] ) === true ) {
							$rest		= trim( (string) preg_replace( '/(?<![\w-])layer(?:\([^)]*\))?(?![\w-])/i', '', $cut[2] ) );
							$found .= '@import '. $cut[1]. ( $layer !== null ? ' layer('. $layer. ')' : '' ). ( $rest !== '' ? ' '. $rest : '' ). ';'. PHP_EOL;
						}

						$body		.= substr( $css, $copied, $i - $copied );
						$i			+= strlen( $cut[0] );
						$copied	 = $i;
					}
				}
				else
					$i++;
			}

			// What was open when the source ended ends there, and so does every block
			$body	.= substr( $css, $copied ). $open;

			if( $depth !== 0 ) {
				$faults[]	= 'a { that is never closed';
				$body		 .= PHP_EOL. str_repeat( '}', $depth );
			}

			if( $faults !== [] )
				trigger_error( 'Assets: '. addcslashes( $file, "\0" ). ' is repaired in its bundle, it holds '. implode( ', ', array_unique( $faults ) ), E_USER_WARNING );

			if( trim( $body ) === '' )
				return '';

			$imports .= $found;

			return $layer === null ? $body. PHP_EOL : '@layer '. $layer. ' {'. PHP_EOL. $body. PHP_EOL. '}'. PHP_EOL;
		}
	}

}
