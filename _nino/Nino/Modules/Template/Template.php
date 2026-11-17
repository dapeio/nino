<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Template				see _nino/Nino/Modules/Modules.php for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules							All optional modules
	 *	Template						A html shortcode for including templates
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */


	class Template {

		// The largest template that is drawn, in bytes: a page is a few KB
		public const int MAX_BYTES = 1048576;

		// A word of a text key: lower-case letters and digits, joined by hyphens
		private const string SEGMENT = '#^[a-z0-9]+(?:-[a-z0-9]+)*$#D';

		// The drafts of this request, category => html+: the templates that [template] draws from a preview in place of the file
		private const string SWAPPED = './nino/template/swapped';

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'template', [ self::class, 'doShortcode' ], false );
		}


		/**
		 *	The category of a template, the second segment of every text key it
		 *	carries (/template/<category>/<part>/<name>): its file name without
		 *	.tpl, prefix and all - page-home.tpl is page-home, html-footer.tpl
		 *	is html-footer. Nothing is derived or cut off. A name that is not a
		 *	word of a key - a dot, an upper-case letter, a slash - has no
		 *	category, and the template carries no keys of its own: it may read
		 *	every other one. Only a file directly in templates/ has one
		 *
		 *	@param		string		$name					A file name (page-home.tpl), or a template as a
		 *																	shortcode or a route body names it (/templates/page-home)
		 *
		 *	@return 	string|null							The category, or null
		 */
		public static function category( string $name ): ?string {

			if( str_starts_with( $name, '/templates/' ) === true )
				$name = substr( $name, strlen( '/templates/' ) );

			if( str_ends_with( $name, '.tpl' ) === true )
				$name = substr( $name, 0, -strlen( '.tpl' ) );

			return preg_match( self::SEGMENT, $name ) === 1 ? $name : null;
		}

		/**
		 *	For this request, and only where it holds a draft (\Nino\Preview::held()): the template of a file is a source
		 *	the caller hands over. A call of [template <file>] draws the source - as renderSource() draws one, with the context of
		 *	the call and the words of the file - and not the file, and a template that is called for the file has what the
		 *	source says of it. The file and the tree that is kept of it are not touched, and the page of a visitor, or of a
		 *	request that holds no draft, has the file as it is: for such a request this does nothing. A file that is no template
		 *	of the project's own - a name with a dot, a slash beyond the directory - and a source of more than MAX_BYTES are
		 *	taken for nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$file					The template, as the shortcode names it (/templates/page-home) or by the file (page-home.tpl)
		 *	@param		string		$source				html+
		 *
		 *	@return 	void
		 */
		public static function swap( array &$appData, string $file, string $source ): void {

			$category = self::category( $file );

			if( $category === null || \Nino\Preview::active( $appData ) === false || strlen( $source ) > self::MAX_BYTES )
				return;

			$appData[self::SWAPPED][$category] = $source;
		}

		/**
		 *	The position a file that is drawn counts its nodes from, where the drawing is traced (see \Nino\Components::render()):
		 *	its own beginning - the call it stands in is a call of the page that included it, and has its own position there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ 'path' => '' ] for a call that is traced, [] for any other
		 */
		private static function _fresh( array &$appData ): array {
			return ( \Nino\Html::context( $appData )['trace'] ?? false ) === true ? [ 'path' => '' ] : [];
		}

		/**
		 *	Replace template shortcode: the file drawn where the call stands. It is a
		 *	leaf, and the file is read as a document of its own - one tree for the
		 *	request, from the tree kept of it where the file is as it was (see
		 *	\Nino\Html::fileTree()) - and drawn with the context of the call, the template set to
		 *	the file's own category, '' where it has none: the words of a template are
		 *	the ones of /template/<its name>/..., whatever includes it. A source with
		 *	a fill in it ([template /templates/page-x.[[/nino/http/response/locale]]]) has it put in
		 *	before the call is made. A file of more than MAX_BYTES is not drawn and
		 *	the log says so; a template that includes itself ends where
		 *	\Nino\Html::renderSource() ends it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$name = (string) ( $args[0] ?? '' );
			$file = $name. '.tpl';
			$category = self::category( $name );

			// A draft of a preview takes the place of the file: drawn from the source, which is not kept, with the position
			// of its nodes counted from its own beginning
			if( $category !== null && $name === '/templates/'. $category && isset( $appData[self::SWAPPED][$category] ) === true )
				return \Nino\Html::renderSource( $appData, $appData[self::SWAPPED][$category], [ 'template' => $category ] + self::_fresh( $appData ) + \Nino\Html::context( $appData ), false );

			$path = \Nino\Filesystem::path( $appData, $file );

			if( is_file( $path ) === true && (int) filesize( $path ) > self::MAX_BYTES ) {

				trigger_error( 'Template: "'. $name. '" is larger than '. self::MAX_BYTES. ' bytes and is not drawn', E_USER_WARNING );

				return '';
			}

			// A name with no category has no words of its own, and reads none by ~name
			return \Nino\Html::renderFile( $appData, $file, [ 'template' => $category ?? '' ] + self::_fresh( $appData ) + \Nino\Html::context( $appData ) );
		}
	}

}
