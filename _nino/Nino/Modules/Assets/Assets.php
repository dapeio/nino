<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Assets				see Nino.php's Modules section for the
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

		private static
			$_template				= [
				'css'							=> '<link rel="stylesheet" href="[[filename]]" type="text/css" />',
				'js'							=> '<script src="[[filename]]"></script>',
			],
			$_minifyRegex 		= [
				'css'							=> [
					[
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
						'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
						'#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
						'#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
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
						'$1',
						':0',
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
			\Nino\Html::addShortcode( $appData, 'assets', [ self::class, 'doShortcode' ] );
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

			$targetFile		= $args[0] ?? '';
			$pathinfo			= pathinfo( $targetFile );
			$assetFiles 	= \Nino\Html::getAssets( $appData, $targetFile );

			// A missing/extension-less argument (eg. a bare "[assets]") renders
			// nothing instead of erroring on the absent pathinfo key
			if( isset( $pathinfo['extension'] ) === false || isset( self::$_template[$pathinfo['extension']] ) === false )
				return '';

			// Compare hash / create cache
			if( empty( $assetFiles ) === false && self::_getFileHashes( $appData, $assetFiles ) !== self::_readHashLine( $appData, $targetFile ) )
				self::_createCachefile( $appData, $pathinfo, $assetFiles );

			// Fill template
			$content	= \Nino\Callbacks::doCallbacks( $appData, '/nino/shortcodes/assets/output/'. $pathinfo['extension'], self::$_template[$pathinfo['extension']] );

			return str_replace( [
				'[[filename]]',
			], [
				\Nino\Filesystem::url( $appData, $targetFile ),
			], $content );
		}

		/**
		 *	Create a unique filehash for all files in a asset dir
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$assetFiles		Asset files array
		 *
		 *	@return 	string											Filehash
		 */
		private static function _getFileHashes( array &$appData, array $assetFiles ): string {

				$hash 	= '';

				// Loop through files and collect file details - resolved one
				// by one, since a bundle mixes public content (/assets/...)
				// with a tool folder's own source (/_nino/Nino.css)
				foreach( $assetFiles AS $file ) {
					$filepath = \Nino\Filesystem::path( $appData, $file );

					if( is_file( $filepath ) === true )
						$hash .= $filepath. '|'. filesize( $filepath ). '|'. filemtime(  $filepath );
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
		 *	Write all asset files from source dir into an target file
		 *
		 *	@param		array 				&$appData			(reference) Array with current app data
		 *	@param		array					$pathinfo			Pathinfo of target asset file
		 *	@param		array					$assetFiles		Asset files array
		 *
		 *	@return 	void
		 */
		private static function _createCachefile( array &$appData, array $pathinfo, array $assetFiles ): void {


			// Loop through files and collect content
			$content = '';
			foreach( $assetFiles AS $file )
				$content .= \Nino\Filesystem::getFileContent( $appData, $file, '' );

			// Deliberately NOT Html::renderHtml(): that pipeline also runs admin-
			// editable textfills (text/global.php, per-locale text files) and every
			// registered shortcode - including ones that take admin-controlled
			// arguments. Since this content is written out as a static file served
			// straight from the webroot, that would turn any admin-editable fill
			// into stored XSS on every page that includes the bundle. Only the one
			// developer/kernel-controlled token the shipped assets actually use is
			// substituted here.
			$content = str_replace( '[[/nino/public]]', \Nino\Filesystem::getPublicDir( $appData ), $content );

			// Minify
			if( substr( $pathinfo['filename'], -4 ) === '.min' )
				$content = trim( preg_replace( self::$_minifyRegex[$pathinfo['extension']][0], self::$_minifyRegex[$pathinfo['extension']][1], $content ), ';' );

			// Add prefix and write file
			$content			= '/**'. self::_getFileHashes( $appData, $assetFiles ). '**/'. PHP_EOL. trim( $content, ';' );
			\Nino\Filesystem::putFileContent( $appData, $pathinfo['dirname']. '/'. $pathinfo['basename'], $content );
		}
	}

}
