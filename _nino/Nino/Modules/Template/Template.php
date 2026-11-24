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

		// The frames of a page. A page calls [frame /templates/frame-header[-<word>]] or [frame /templates/frame-footer[-<word>]]; the call draws the document
		// of the page - html-header.tpl or html-footer.tpl, the scaffold - and puts the frame into it where it says [[header]] or [[footer]]. While a frame or a
		// scaffold is drawn nothing is called a second time: INSIDE is true. What was said of a frame is kept for the request
		private const string INSIDE = './nino/template/frame/inside';
		private const string SAID = './nino/template/frame/said';

		// The name a [frame] call is written with: the standard frame of a side, or one with a word of lower-case letters and digits joined by hyphens, at most FRAME_LENGTH characters
		private const string FRAME_NAME = '#^/templates/frame-(header|footer)(?:-([a-z0-9]+(?:-[a-z0-9]+)*))?$#D';
		private const string FRAME_WORD = '#^[a-z0-9]+(?:-[a-z0-9]+)*$#D';
		public const int FRAME_LENGTH = 40;

		// 'none' is no frame - the scaffold is drawn with nothing in it - and 'default' is the standard: neither is a word of a file
		public const array FRAME_RESERVED = [ 'none', 'default' ];

		// The sides of a page that has a frame, each with the scaffold it is drawn in and the key of the fill that scaffold has for it: [[header]], [[footer]]
		public const array FRAME_SIDES = [ 'header', 'footer' ];

		// What is said of a frame is said once in a request and once an hour, whoever asks (see \Nino\Feature::count()): a page that names a frame wrong would fill the log with every visit
		private const string NOTICE_COUNTER = 'template-frame-notices';
		private const int NOTICE_WINDOW = 3600;
		private const int NOTICES = 20;

		/**
		 *	Module initiating
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addShortcode( $appData, 'template', [ self::class, 'doShortcode' ], false );
			\Nino\Html::addShortcode( $appData, 'frame', [ self::class, 'doFrame' ], false );
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
		 *	The scaffold of a page - /templates/html-header and /templates/html-footer, called by name - is drawn with the standard
		 *	frame in its [[header]] or [[footer]] (see doFrame(), which draws it with another): a page that calls it as it always
		 *	has shows the standard frame. The fill is set in the scaffold's own text and in no other template, not in one the scaffold
		 *	includes either, and what is put in is read by nobody
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *
		 *	@return 	string							Rendered html
		 */
		public static function doShortcode( array &$appData, array $args ): string {

			$side = self::_side( (string) ( $args[0] ?? '' ) );

			if( $side === null )
				return self::_draw( $appData, $args, [] );

			return self::_scaffold( $appData, $side, null );
		}

		/**
		 *	The side of a scaffold: header for /templates/html-header, footer for /templates/html-footer, null for any other name
		 *
		 *	@param		string		$name
		 *
		 *	@return 	string|null
		 */
		private static function _side( string $name ): ?string {

			foreach( self::FRAME_SIDES as $side )
				if( $name === '/templates/html-'. $side )
					return $side;

			return null;
		}

		/**
		 *	The file of a [template] call drawn, as doShortcode() has it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments
		 *	@param		array			$fill					[ '[[header]]' => html ] for a scaffold, [] for any other template
		 *
		 *	@return 	string							Rendered html
		 */
		private static function _draw( array &$appData, array $args, array $fill ): string {

			// The context of the call, with the tokens of the frame taken out of it - a template that is not the scaffold has none - and the fill of the scaffold put in
			$context = \Nino\Html::context( $appData );
			$context['fills'] = array_diff_key( (array) ( $context['fills'] ?? [] ), array_fill_keys( array_map( static fn( string $side ): string => '[['. $side. ']]', self::FRAME_SIDES ), true ) ) + $fill;

			$name = (string) ( $args[0] ?? '' );
			$file = $name. '.tpl';
			$category = self::category( $name );

			// A draft of a preview takes the place of the file: drawn from the source, which is not kept, with the position
			// of its nodes counted from its own beginning
			if( $category !== null && $name === '/templates/'. $category && isset( $appData[self::SWAPPED][$category] ) === true )
				return \Nino\Html::renderSource( $appData, $appData[self::SWAPPED][$category], [ 'template' => $category ] + self::_fresh( $appData ) + $context, false );

			$path = \Nino\Filesystem::path( $appData, $file );

			if( is_file( $path ) === true && (int) filesize( $path ) > self::MAX_BYTES ) {

				trigger_error( 'Template: "'. $name. '" is larger than '. self::MAX_BYTES. ' bytes and is not drawn', E_USER_WARNING );

				return '';
			}

			// A name with no category has no words of its own, and reads none by ~name
			return \Nino\Html::renderFile( $appData, $file, [ 'template' => $category ?? '' ] + self::_fresh( $appData ) + $context );
		}

		/**
		 *	The scaffold of a side drawn with a frame in it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$side					header or footer
		 *	@param		string|null	$html					The frame, drawn; null: the standard one, drawn only where the scaffold has a place for it
		 *
		 *	@return 	string
		 */
		private static function _scaffold( array &$appData, string $side, ?string $html ): string {

			$inside = $appData[self::INSIDE] ?? false;
			$appData[self::INSIDE] = true;

			try {

				if( $html === null )
					$html = self::_wears( $appData, $side ) === true ? self::_frame( $appData, $side, '' ) : '';

				return self::_draw( $appData, [ '/templates/html-'. $side ], [ '[['. $side. ']]' => $html ] );
			}
			finally {

				if( $inside === false )
					unset( $appData[self::INSIDE] );
				else
					$appData[self::INSIDE] = $inside;
			}
		}

		/**
		 *	The frame of a side, drawn: the file of the word, or the standard where the word has none, or nothing for none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$side
		 *	@param		string		$word					'' for the standard, 'none', 'default', or the word
		 *
		 *	@return 	string
		 */
		private static function _frame( array &$appData, string $side, string $word ): string {

			if( $word === 'none' )
				return '';

			$name = '/templates/frame-'. $side;

			if( $word !== '' && $word !== 'default' ) {

				if( self::_exists( $appData, $side, $word ) === true )
					$name .= '-'. $word;
				else
					self::_say( $appData, 'file '. $side. ' '. $word, 'Template: [frame '. $name. '-'. $word. '] has no template frame-'. $side. '-'. $word. '.tpl; the standard frame is drawn' );
			}

			// Drawn as a template, with the words of its own file, and as no scaffold
			return self::_draw( $appData, [ $name ], [] );
		}

		/**
		 *	Whether a frame with this word is there: its file, in the directory as it is listed, or a draft of a preview in its place
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$side
		 *	@param		string		$word
		 *
		 *	@return 	bool
		 */
		private static function _exists( array &$appData, string $side, string $word ): bool {
			return isset( $appData[self::SWAPPED]['frame-'. $side. '-'. $word] ) === true || in_array( $word, self::frames( $appData )[$side] ?? [], true ) === true;
		}

		/**
		 *	Whether the scaffold of a side has a place for the frame: [[header]] or [[footer]] in its own text, and not in a comment
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$side
		 *
		 *	@return 	bool
		 */
		private static function _wears( array &$appData, string $side ): bool {

			$draft = $appData[self::SWAPPED]['html-'. $side] ?? null;
			$tree = is_string( $draft ) === true ? \Nino\Markup::parse( $draft, \Nino\Components::containers( $appData ) ) : \Nino\Html::fileTree( $appData, '/templates/html-'. $side. '.tpl' );

			return self::_carries( $tree, $side );
		}

		/**
		 *	Whether the nodes of a text hold a fill of this key outside of a comment of html: \[[header]] is the text it says, and so is
		 *	what a comment holds
		 *
		 *	@param		array			$nodes				A tree of \Nino\Markup
		 *	@param		string		$key					header or footer
		 *
		 *	@return 	bool
		 */
		private static function _carries( array $nodes, string $key ): bool {

			$open = false;
			$walk = static function( array $nodes ) use ( &$walk, &$open, $key ): bool {

				foreach( $nodes as $node ) {

					if( is_array( $node ) === false )
						continue;

					if( isset( $node['t'] ) === true && is_string( $node['t'] ) === true ) {

						$begin = strrpos( $node['t'], '<!--' );
						$end = strrpos( $node['t'], '-->' );

						if( $begin !== false && ( $end === false || $end < $begin ) )
							$open = true;
						else if( $end !== false )
							$open = false;
					}
					else if( ( $node['f'] ?? null ) === $key && $open === false )
						return true;

					if( is_array( $node['c'] ?? null ) === true && $walk( $node['c'] ) === true )
						return true;
				}

				return false;
			};

			return $walk( $nodes );
		}

		/**
		 *	A value as the log shows it: printable ASCII only, at most FRAME_LENGTH characters
		 *
		 *	@param		mixed			$value
		 *
		 *	@return 	string
		 */
		private static function _shown( mixed $value ): string {

			$text = is_string( $value ) === true ? $value : '(not a string)';

			return substr( (string) preg_replace( '/[^\x20-\x7e]/', '?', $text ), 0, self::FRAME_LENGTH * 2 ). ( strlen( $text ) > self::FRAME_LENGTH * 2 ? '...' : '' );
		}

		/**
		 *	Say a thing in the log: once in a request, once in NOTICE_WINDOW seconds whoever asks, and for the first NOTICES things of a request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$what					What it is about: the same words, the same line
		 *	@param		string		$message
		 *
		 *	@return 	void
		 */
		private static function _say( array &$appData, string $what, string $message ): void {

			$what = substr( $what, 0, 120 );

			if( isset( $appData[self::SAID][$what] ) === true || count( (array) ( $appData[self::SAID] ?? [] ) ) >= self::NOTICES )
				return;

			$appData[self::SAID][$what] = true;

			// Read first: a thing that was said in the hour writes nothing, however often it is met
			if( \Nino\Feature::spent( $appData, self::NOTICE_COUNTER, $what, 1 ) === true || \Nino\Feature::count( $appData, self::NOTICE_COUNTER, $what, 1, self::NOTICE_WINDOW ) === false )
				return;

			trigger_error( $message, E_USER_WARNING );
		}

		/**
		 *	Replace frame shortcode: the frame a page chooses, a leaf. [frame /templates/frame-header] draws the scaffold of the page
		 *	(/templates/html-header) with the standard frame in its [[header]], [frame /templates/frame-header-landing] with the file
		 *	frame-header-landing.tpl in it, [frame /templates/frame-footer-...] the same for the footer; the side is what the name says. The word none
		 *	draws the scaffold with nothing in it, and a file of that name is never read; default is the standard. The call takes exactly one argument
		 *	of the form /templates/frame-(header|footer)[-<word>], the word of lower-case letters and digits joined by hyphens, at most FRAME_LENGTH
		 *	characters, and it is read as it is written (see \Nino\Html::LITERAL): a fill in it is no fill. Anything else - another template, another
		 *	directory, a path, capitals, a blank, more than one argument, none - draws nothing at all, the scaffold not either, and is said. A word
		 *	whose file is not there draws the scaffold with the standard frame and is said. A [frame] in a frame, in a scaffold or in another [frame]
		 *	draws nothing and is said: nothing here calls itself
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$args					Shortcode arguments: the name of the frame
		 *
		 *	@return 	string							Rendered html
		 */
		public static function doFrame( array &$appData, array $args ): string {

			$name = $args[0] ?? null;

			if( ( $appData[self::INSIDE] ?? false ) === true ) {

				self::_say( $appData, 'inside '. md5( self::_shown( $name ) ), 'Template: [frame '. self::_shown( $name ). '] stands in a frame or in the scaffold of a page, where nothing is called a second time; nothing is drawn' );

				return '';
			}

			if( array_keys( $args ) !== [ 0 ] && is_string( $name ) === true && preg_match( self::FRAME_NAME, $name ) === 1 ) {

				self::_say( $appData, 'many '. md5( self::_shown( $name ). count( $args ) ), 'Template: [frame '. self::_shown( $name ). '] has more than one argument - the call takes the name of the frame and nothing else; nothing is drawn' );

				return '';
			}

			if( array_keys( $args ) !== [ 0 ] || is_string( $name ) === false || preg_match( self::FRAME_NAME, $name, $found ) !== 1 || strlen( $found[2] ?? '' ) > self::FRAME_LENGTH ) {

				self::_say( $appData, 'name '. md5( self::_shown( $name ). count( $args ) ), 'Template: [frame '. self::_shown( $name ). '] is no frame - the name is /templates/frame-header or /templates/frame-footer, with a word of lower-case letters, digits and hyphens behind a hyphen, at most '. self::FRAME_LENGTH. ' characters, and there is nothing else in the call; nothing is drawn' );

				return '';
			}

			$appData[self::INSIDE] = true;

			try {
				// A scaffold that has no place for a frame draws none: the one that was chosen is not drawn for nothing
				return self::_scaffold( $appData, $found[1], self::_wears( $appData, $found[1] ) === true ? self::_frame( $appData, $found[1], $found[2] ?? '' ) : '' );
			}
			finally {
				unset( $appData[self::INSIDE] );
			}
		}

		/**
		 *	The templates a source draws that no [template] call of it names: what the kernel puts round a page. A page that calls the scaffold
		 *	([template /templates/html-header]) has the standard frame in its [[header]] - the file frame-header - where the scaffold has a place for it,
		 *	and one that calls [frame /templates/frame-header-<word>] has the scaffold and the frame of that word (the standard where the file is not
		 *	there), and none for the word none - decided by the same rules that draw the page (see doFrame(), _wears(), _frame()), so that a tool that
		 *	looks for what a page shows - the pictures of the head and the foot of every page - reads the same files. A source that calls neither
		 *	has no frame
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$source				html+ of a page or of a template
		 *
		 *	@return 	array										[ template name without .tpl, ... ] - html-header, frame-header-landing -, each once
		 */
		public static function frameFiles( array &$appData, string $source ): array {

			$names = [];

			foreach( self::FRAME_SIDES as $side ) {

				$wears = null;

				// The scaffold called by name: the standard frame is in it
				if( preg_match( '#\[template /templates/html-'. $side. '[\] ]#', $source ) === 1 ) {
					$wears = self::_wears( $appData, $side );

					if( $wears === true )
						$names[] = 'frame-'. $side;
				}

				// A frame the page chooses: the scaffold with the file of the word, the standard where the word has none
				if( preg_match_all( '#\[frame /templates/frame-'. $side. '(?:-([a-z0-9]+(?:-[a-z0-9]+)*))?[\] ]#', $source, $found ) > 0 ) {

					$wears ??= self::_wears( $appData, $side );
					$names[] = 'html-'. $side;

					foreach( $found[1] as $word ) {

						if( $wears === false || $word === 'none' || strlen( $word ) > self::FRAME_LENGTH )
							continue;

						$names[] = $word !== '' && $word !== 'default' && self::_exists( $appData, $side, $word ) === true ? 'frame-'. $side. '-'. $word : 'frame-'. $side;
					}
				}
			}

			return array_values( array_unique( $names ) );
		}

		/**
		 *	The frames of the site: the standard of each side, where its template is there, and the named ones - the files frame-header-<word>.tpl
		 *	and frame-footer-<word>.tpl of the templates, as the directory lists them: a name that has the form exactly (so a file whose name has
		 *	a capital is none, on a file system that folds the case as on one that does not), a file and no link, a word that is none of the reserved
		 *	ones - sorted. What a page chooses from; the words of a frame are those of its file, and its texts are the keys /template/frame-header-<word>/...
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										side => [ 'default' where the standard is there, then the words ], for header and footer
		 */
		public static function frames( array &$appData ): array {

			$frames = [];

			foreach( self::FRAME_SIDES as $side ) {

				$words = [];

				foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/frame-'. $side. '-*.tpl' ) ?: [] as $file ) {

					$word = substr( basename( $file ), strlen( 'frame-'. $side. '-' ), -strlen( '.tpl' ) );

					if( is_link( $file ) === false && is_file( $file ) === true && strlen( $word ) <= self::FRAME_LENGTH && preg_match( self::FRAME_WORD, $word ) === 1 && in_array( $word, self::FRAME_RESERVED, true ) === false )
						$words[] = $word;
				}

				sort( $words, SORT_STRING );

				$standard = \Nino\Filesystem::path( $appData, '/templates/frame-'. $side. '.tpl' );
				$frames[$side] = array_merge( is_file( $standard ) === true && is_link( $standard ) === false ? [ 'default' ] : [], $words );
			}

			return $frames;
		}

		/**
		 *	Whether the scaffold of the site - html-header.tpl and html-footer.tpl, the document the pages are drawn in - has the place for a frame
		 *	that a [frame] call fills: [[header]] and [[footer]] in its text, read as the notation reads it (a fill that is written escaped, or stands
		 *	in a comment of html, is none). A scaffold that draws its frame by a [template] call of its own is drawn as it always was, and the
		 *	choice of a page has no effect there
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										side => bool, for header and footer
		 */
		public static function framed( array &$appData ): array {

			$framed = [];

			foreach( self::FRAME_SIDES as $side )
				$framed[$side] = self::_wears( $appData, $side );

			return $framed;
		}
	}

}
