<?php
declare(strict_types=1);
/**
 *	Design-Labor - a developer tool for a Nino site. NOT part of Nino.
 *
 *	One file, put into the web root of a site that has the feature Design.
 *	Choose a part and a variant of the Design library, edit its template and
 *	css in two textareas and watch a live preview: the real specimen page of the
 *	Design panel, compiled by the feature's own compiler with the text you are
 *	typing in place of the library file. "Save" writes the two files back into
 *	features/Design/library/.
 *
 *	DELETE THIS FILE FROM THE SERVER AFTER USE. It lives in the web root and can
 *	write files. Do not commit it to the repository or ship it in a release.
 *
 *	Access: a signed-in workbench session of an account that holds every
 *	permission ('/*'). Every POST carries the kernel's CSRF token.
 */

// A site that moves its halves out of the project with NINO_* constants defines
// them in its index.php; define the very same ones here (see index.php), or
// this file will not find config.php and the features.
// define( 'NINO_PRIVATE_DIR', '/absolute/path/outside/webroot' );
// define( 'NINO_CONFIG_DIR', '/absolute/path/outside/webroot' );
// define( 'NINO_APP_DIR', '/absolute/path/to/app' );
// define( 'NINO_FEATURES_DIR', '/absolute/path/to/features' );

namespace DesignLab {

	if( is_file( __DIR__. '/_nino/Nino.php' ) === false ) {
		http_response_code( 500 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( "Design-Labor: _nino/Nino.php nicht gefunden. Die Datei gehört in das Wurzelverzeichnis einer Nino-Site.\n" );
	}

	require __DIR__. '/_nino/Nino.php';

	use Nino\Modules\Design\Setup;
	use Nino\Modules\Design\Themes;
	use Nino\Modules\Design\Compiler;
	use Nino\Modules\Design\Preview;

	final class Lab {

		// What a request body, a file and a typed text may weigh
		private const int MAX_BODY = 4194304;
		private const int MAX_TEXT = 524288;

		// The part names for the select; a part the library knows and this list does not shows under its key
		private const array PART_LABELS = [
			'header' 		=> 'Kopfbereich (Header)',
			'footer' 		=> 'Fußbereich (Footer)',
			'section' 	=> 'Abschnitt (Section)',
			'tiles' 		=> 'Kacheln (Tiles)',
			'card' 			=> 'Karte (Card)',
			'buttons' 	=> 'Buttons',
			'lists' 		=> 'Listen (Lists)',
			'tables' 		=> 'Tabellen (Tables)',
			'forms' 		=> 'Formulare (Forms)',
			'tabs' 			=> 'Tabs',
			'accordion' => 'Accordion',
		];

		// Where the specimen page shows each part (the ids of templates/preview-specimen.tpl, as the Design panel's OBJECT_ANCHORS)
		private const array ANCHORS = [
			'header' => 'global', 'footer' => 'footer', 'section' => 'colours', 'tiles' => 'tiles', 'card' => 'cards', 'buttons' => 'buttons',
			'lists' => 'lists', 'tables' => 'tables', 'forms' => 'forms', 'tabs' => 'tabs', 'accordion' => 'accordion',
		];

		// Titles of a made-up long menu, for judging a header with many entries
		private const array MENU_TITLES = [
			'de' => [ 'Start', 'Leistungen', 'Referenzen', 'Preise', 'Über uns', 'Team', 'Karriere', 'Journal', 'Veranstaltungen', 'Kontakt', 'Häufige Fragen', 'Presse', 'Partner', 'Downloads' ],
			'en' => [ 'Home', 'Services', 'Projects', 'Pricing', 'About us', 'Team', 'Careers', 'Journal', 'Events', 'Contact', 'Questions', 'Press', 'Partners', 'Downloads' ],
		];

		private static array $_caught = [];

		/* ------------------------------------------------------------------ entry */

		public static function run(): never {

			$appData = \Nino\init();

			if( (string) ( $appData['/nino/dir'] ?? '' ) === '' )
				$appData['/nino/dir'] = \Nino\Filesystem::deriveDir( $appData, $_SERVER );

			$request = $_SERVER;
			\Nino\Http::request( $appData, $request );
			\Nino\Html::addFills( $appData, \Nino\Html::bootFills( $appData ), '*' );

			$method = (string) $request['/nino/http/request']['method'];
			$isPost = $method === 'POST';

			// Only the signed-in workbench account with every right, and nobody else gets a word of this tool
			$user = \Nino\Auth::getCurrentUser( $appData );

			if( $user === false )
				self::deny( $appData, $request, $isPost, 'Zugriff verweigert: Das Design-Labor braucht eine angemeldete Workbench-Sitzung, bitte zuerst in der Workbench anmelden.' );

			if( \Nino\Auth::checkPermission( $appData, '/*' ) === false )
				self::deny( $appData, $request, $isPost, 'Zugriff verweigert: Das Design-Labor ist nur für Konten mit allen Rechten (/*) freigegeben.' );

			if( class_exists( '\\Nino\\Modules\\Design\\Setup' ) === false || is_dir( \Nino\Modules\Design::libraryDir() ) === false )
				self::send( $appData, $request, 500, self::plain( 'Das Feature Design ist auf dieser Site nicht installiert oder nicht aktiv, das Design-Labor hat nichts zu bearbeiten.' ), 'text/html; charset=utf-8' );

			if( $method === 'GET' ) {

				if( isset( $_GET['a'] ) === true )
					self::json( $appData, $request, 405, [ 'error' => 'Nur POST.' ] );

				self::page( $appData, $request );
			}

			if( $isPost === false )
				self::json( $appData, $request, 405, [ 'error' => 'Methode nicht erlaubt.' ] );

			// The kernel's own check of the session token: header X-CSRF-Token, or _csrf in the body
			if( self::sizeOf( $request ) > self::MAX_BODY )
				self::json( $appData, $request, 413, [ 'error' => 'Die Anfrage ist zu groß.' ] );

			\Nino\Csrf::callbackResponse( $appData, $request );

			if( (int) $request['/nino/http/response']['statusCode'] !== 200 )
				self::json( $appData, $request, 403, [ 'error' => 'Sitzungs-Token ungültig, bitte die Seite neu laden.', 'code' => 'csrf' ] );

			// Nothing below writes the session: let go of its lock so that quick previews do not queue behind each other
			if( session_status() === PHP_SESSION_ACTIVE )
				session_write_close();

			$data = json_decode( (string) $request['/nino/http/request']['body'], true );

			if( is_array( $data ) === false )
				self::json( $appData, $request, 400, [ 'error' => 'Die Anfrage ist kein JSON.' ] );

			$action = is_string( $_GET['a'] ?? null ) === true ? $_GET['a'] : '';

			try {
				$answer = match( $action ) {
					'file' 		=> self::actionFile( $appData, $data ),
					'preview' => self::actionPreview( $appData, $request, $data ),
					'save' 		=> self::actionSave( $appData, $data ),
					default 	=> throw new Refusal( 'Unbekannte Aktion.', 404 ),
				};
			}
			catch( Refusal $e ) {
				self::json( $appData, $request, $e->status, [ 'error' => $e->getMessage() ] + $e->extra );
			}
			catch( \Throwable $e ) {
				self::json( $appData, $request, 500, [ 'error' => 'Interner Fehler: '. $e->getMessage() ] );
			}

			self::json( $appData, $request, 200, $answer );
		}

		/* ------------------------------------------------------------- responses */

		private static function sizeOf( array $request ): int {
			return strlen( (string) ( $request['/nino/http/request']['body'] ?? '' ) );
		}

		private static function plain( string $sentence ): string {

			$here = htmlspecialchars( rtrim( dirname( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) ), '/\\' ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

			return '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Design-Labor</title>'
				. '<body style="font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem"><p>'
				. htmlspecialchars( $sentence, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
				. '</p><p><a href="'. $here. '/_admin/">Zur Workbench</a></p></body></html>';
		}

		private static function deny( array &$appData, array &$request, bool $json, string $sentence ): never {

			if( $json === true )
				self::json( $appData, $request, 403, [ 'error' => $sentence ] );

			self::send( $appData, $request, 403, self::plain( $sentence ), 'text/html; charset=utf-8' );
		}

		private static function json( array &$appData, array &$request, int $status, array $body ): never {
			self::send( $appData, $request, $status, (string) json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE ), 'application/json; charset=utf-8' );
		}

		private static function send( array &$appData, array &$request, int $status, string $body, string $type, string $policy = '' ): never {

			$request['/nino/http/response']['statusCode'] = $status;
			$request['/nino/http/response']['body'] = $body;
			$request['/nino/http/response']['header'] = [
				'Content-Type' 							=> $type,
				'Cache-Control' 						=> 'no-store',
				'X-Frame-Options' 					=> 'DENY',
				'X-Robots-Tag' 							=> 'noindex, nofollow',
				'Content-Security-Policy' 	=> $policy !== '' ? $policy : "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; style-src 'unsafe-inline'",
			];

			\Nino\Http::output( $appData, $request );
		}

		/* ------------------------------------------------------------------ page */

		private static function page( array &$appData, array &$request ): never {

			$nonce 	= base64_encode( random_bytes( 16 ) );
			$dir 		= rtrim( \Nino\Filesystem::getDir( $appData ), '/' );
			$locales = array_values( array_map( 'strval', \Nino\Locales::getAvailableLocales( $appData ) ) );

			$boot = [
				'csrf' 		=> \Nino\Csrf::getToken( $appData ),
				'nonce' 	=> $nonce,
				'parts' 	=> self::catalogue( $appData ),
				'themes' 	=> self::themes( $appData ),
				'locales' => $locales,
				'locale' 	=> in_array( 'de_DE', $locales, true ) === true ? 'de_DE' : (string) ( $locales[0] ?? 'en_US' ),
				'anchors' => self::ANCHORS,
				'admin' 	=> $dir. '/_admin/',
				'max' 		=> self::MAX_TEXT,
			];

			$json = (string) json_encode( $boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP );

			$html = str_replace( [ '__NONCE__', '__BOOT__', '__CSS__', '__JS__' ], [ $nonce, $json, self::CSS, self::JS ], self::HTML );

			// The page may run its own script (nonce) and ask this file for json; the preview frames are srcdoc documents that inherit this
			// policy - scripts only with the nonce, styles inline, images and fonts as data or from the site
			$policy = "default-src 'none'; script-src 'nonce-". $nonce. "'; style-src 'self' 'unsafe-inline'; img-src * data: blob:; font-src 'self' data:; "
				. "connect-src 'self'; frame-src about:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";

			self::send( $appData, $request, 200, $html, 'text/html; charset=utf-8', $policy );
		}

		/* -------------------------------------------------------------- catalogue */

		/**
		 *	The library as it is on disk: every part with its variants and, for each variant, the files this tool may edit.
		 *	The one list a save is checked against - a path is never taken from a request, only looked up here.
		 */
		private static function catalogue( array &$appData, string $only = '' ): array {

			$lib = \Nino\Modules\Design::libraryDir();
			$out = [];

			foreach( Setup::PARTS as $part => $kind ) {

				if( $only !== '' && $only !== $part )
					continue;

				$variants = [];

				foreach( Setup::available( $lib, $part ) as $set ) {

					$info = Setup::describe( $lib, $part, $set, 'de_DE' );
					$home = $lib. '/'. $part. '/'. $set;
					$main = $kind === 'frame' ? $part. '/'. $set. '/style.css' : $part. '/'. $set. '.css';
					$files = [];

					// The styles the head declares (style-option.css) and the knobs this variant brings in files of its own
					$notes = [];
					$byName = [];

					foreach( Setup::styles( $lib, $part, $set, 'de_DE', $notes ) as $style => $entry )
						foreach( $entry['options'] as $option => $o )
							$byName[ basename( (string) $o['file'] ) ] = [ 'style' => $style, 'option' => $option, 'label' => $entry['label'], 'name' => $o['name'] ];

					$lazy = array_values( array_map( 'basename', Setup::lazy( $lib, $part, $set ) ) );

					$files[] = [ 'id' => $main, 'label' => basename( $main ). ' (Hauptdatei)', 'kind' => 'main', 'exists' => is_file( $lib. '/'. $main ) ];

					if( is_dir( $home ) === true ) {

						foreach( self::scan( $home ) as $name ) {

							if( $kind === 'frame' && $name === 'style.css' )
								continue;

							$file = [ 'id' => $part. '/'. $set. '/'. $name, 'exists' => true ];

							if( isset( $byName[$name] ) === true ) {
								$s = $byName[$name];
								$file += [ 'label' => $name. ' (Stil '. $s['style']. ': '. $s['name']. ')', 'kind' => 'style', 'style' => $s['style'], 'option' => $s['option'] ];
							}
							else
								$file += [ 'label' => $name. ' (nicht zugeordnet)', 'kind' => 'other' ];

							$files[] = $file;
						}

						foreach( self::scan( $home. '/'. Setup::KNOB_DIR ) as $name ) {

							$knob = substr( $name, 0, -4 );
							$file = [ 'id' => $part. '/'. $set. '/'. Setup::KNOB_DIR. '/'. $name, 'exists' => true ];

							if( in_array( $name, $lazy, true ) === true && Setup::kind( $part, $knob ) !== '' ) {
								$kindOf = Setup::kind( $part, $knob );
								$steps = [];

								foreach( Setup::positions( $kindOf ) as $position )
									$steps[] = [ 'value' => $position, 'word' => Setup::word( $position, $kindOf ) ];

								$file += [ 'label' => Setup::KNOB_DIR. '/'. $name. ' (Regler '. $knob. ')', 'kind' => 'knob', 'knob' => $knob, 'steps' => $steps ];
							}
							else
								$file += [ 'label' => Setup::KNOB_DIR. '/'. $name. ' (nicht zugeordnet)', 'kind' => 'other' ];

							$files[] = $file;
						}
					}

					$variants[] = [
						'key' 			=> $set,
						'label' 		=> $info['name'] !== '' ? $info['name']. ' ('. $set. ')' : $set,
						'template' 	=> $kind === 'frame' ? $part. '/'. $set. '/template.tpl' : '',
						'files' 		=> $files,
					];
				}

				$out[] = [ 'key' => $part, 'label' => self::PART_LABELS[$part] ?? $part, 'kind' => $kind, 'anchor' => self::ANCHORS[$part] ?? 'global', 'variants' => $variants ];
			}

			return $out;
		}

		/** The css files of one directory (no recursion, no links), sorted naturally */
		private static function scan( string $dir ): array {

			if( is_dir( $dir ) === false )
				return [];

			$found = [];

			foreach( (array) @scandir( $dir ) as $name )
				if( is_string( $name ) === true && preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*\.css$/D', $name ) === 1 && is_file( $dir. '/'. $name ) === true && is_link( $dir. '/'. $name ) === false )
					$found[] = $name;

			sort( $found, SORT_NATURAL );

			return $found;
		}

		private static function themes( array &$appData ): array {

			$lib = \Nino\Modules\Design::libraryDir();
			$list = [];

			foreach( Themes::all( $appData, $lib, 'de_DE' ) as $theme )
				$list[] = [ 'slug' => $theme['slug'], 'name' => $theme['name'], 'shipped' => $theme['shipped'] ];

			$active = (string) ( Setup::read( $appData, $lib )['theme'] ?? '' );
			$known = array_column( $list, 'slug' );

			return [ 'list' => $list, 'active' => in_array( $active, $known, true ) === true ? $active : (string) ( $known[0] ?? '' ) ];
		}

		/** Part, variant and file of a request, looked up in the catalogue - the only way to a path */
		private static function resolve( array &$appData, array $data ): array {

			$part 		= is_string( $data['part'] ?? null ) === true ? $data['part'] : '';
			$variant 	= is_string( $data['variant'] ?? null ) === true ? $data['variant'] : '';
			$file 		= is_string( $data['file'] ?? null ) === true ? $data['file'] : '';

			foreach( self::catalogue( $appData, $part ) as $p )
				if( $p['key'] === $part )
					foreach( $p['variants'] as $v )
						if( $v['key'] === $variant )
							foreach( $v['files'] as $f )
								if( $f['id'] === $file )
									return [ 'part' => $p, 'variant' => $v, 'file' => $f ];

			throw new Refusal( 'Part, Variante oder Datei stehen nicht in der Bibliothek.', 400 );
		}

		private static function text( array $data, string $key ): ?string {

			if( ( $data[$key] ?? null ) === null )
				return null;

			if( is_string( $data[$key] ) === false )
				throw new Refusal( 'Der Text "'. $key. '" ist keine Zeichenkette.', 400 );

			$text = $data[$key];

			if( strlen( $text ) > self::MAX_TEXT )
				throw new Refusal( 'Der Text "'. $key. '" ist zu groß (höchstens '. (int) ( self::MAX_TEXT / 1024 ). ' KB).', 413 );

			if( str_contains( $text, "\0" ) === true || mb_check_encoding( $text, 'UTF-8' ) === false )
				throw new Refusal( 'Der Text "'. $key. '" enthält Null-Bytes oder ungültiges UTF-8.', 400 );

			return str_replace( "\r\n", "\n", $text );
		}

		/** The path of a catalogue id, only inside the library and never through a link */
		private static function pathOf( string $id ): string {

			$lib = (string) realpath( \Nino\Modules\Design::libraryDir() );
			$path = $lib. '/'. $id;

			if( $lib === '' || preg_match( '#(^|/)\.\.?(/|$)#', $id ) === 1 || str_contains( $id, "\0" ) === true || str_contains( $id, '\\' ) === true )
				throw new Refusal( 'Ungültiger Pfad.', 400 );

			if( is_link( $path ) === true )
				throw new Refusal( 'Die Datei ist ein Link und wird nicht angefasst.', 400 );

			// An existing file must resolve into the library; a file to be made must have its directory in it
			$real = is_file( $path ) === true ? (string) realpath( $path ) : (string) realpath( dirname( $path ) ). '/'. basename( $path );

			if( str_starts_with( $real, $lib. '/' ) === false )
				throw new Refusal( 'Der Pfad liegt außerhalb der Bibliothek.', 400 );

			return $path;
		}

		private static function read( string $path ): string {
			return is_file( $path ) === true ? (string) @file_get_contents( $path ) : '';
		}

		/* ------------------------------------------------------------------- file */

		private static function actionFile( array &$appData, array $data ): array {

			$r = self::resolve( $appData, $data );
			$css = self::read( self::pathOf( $r['file']['id'] ) );
			$tpl = $r['variant']['template'] !== '' ? self::read( self::pathOf( $r['variant']['template'] ) ) : null;

			return [
				'css' 			=> $css,
				'template' 	=> $tpl,
				'hash' 			=> [ 'css' => sha1( $css ), 'template' => $tpl === null ? '' : sha1( $tpl ) ],
				'exists' 		=> $r['file']['exists'],
			];
		}

		/* ------------------------------------------------------------------- save */

		private static function actionSave( array &$appData, array $data ): array {

			$r = self::resolve( $appData, $data );
			$css = self::text( $data, 'css' );
			$tpl = self::text( $data, 'template' );
			$force = ( $data['force'] ?? false ) === true;
			$base = is_array( $data['base'] ?? null ) === true ? $data['base'] : [];

			if( $css === null )
				throw new Refusal( 'Kein CSS übergeben.', 400 );

			$code = self::refusal( $css );

			if( $code !== '' )
				throw new Refusal( 'CSS nicht gespeichert: '. $code, 400 );

			if( $tpl !== null && $r['variant']['template'] === '' )
				throw new Refusal( 'Diese Variante hat kein Template.', 400 );

			$jobs = [ [ 'id' => $r['file']['id'], 'text' => $css, 'base' => (string) ( $base['css'] ?? '' ), 'what' => 'CSS' ] ];

			if( $tpl !== null )
				$jobs[] = [ 'id' => $r['variant']['template'], 'text' => $tpl, 'base' => (string) ( $base['template'] ?? '' ), 'what' => 'Template' ];

			// Check everything first, write after: a conflict on the second file must not leave the first one half done
			$todo = [];

			foreach( $jobs as $job ) {

				$path = self::pathOf( $job['id'] );
				$now = self::read( $path );

				if( $now === $job['text'] )
					continue;

				if( $force === false && is_file( $path ) === true && $job['base'] !== '' && hash_equals( sha1( $now ), $job['base'] ) === false )
					throw new Refusal( $job['what']. ': Die Datei hat sich auf dem Server geändert, seit sie geladen wurde.', 409, [ 'conflict' => true ] );

				$todo[] = [ 'path' => $path ] + $job;
			}

			$written = [];

			foreach( $todo as $job ) {
				self::write( $job['path'], $job['text'] );
				$written[] = $job['id'];
			}

			$css = self::read( self::pathOf( $r['file']['id'] ) );
			$tpl = $r['variant']['template'] !== '' ? self::read( self::pathOf( $r['variant']['template'] ) ) : null;

			return [ 'written' => $written, 'hash' => [ 'css' => sha1( $css ), 'template' => $tpl === null ? '' : sha1( $tpl ) ] ];
		}

		/** Write through a temporary file in the same directory and rename, keeping the file's mode; a plain locked write where the directory does not allow that */
		private static function write( string $path, string $text ): void {

			$dir = dirname( $path );

			if( is_dir( $dir ) === false && @mkdir( $dir, 0775, true ) === false && is_dir( $dir ) === false )
				throw new Refusal( 'Das Verzeichnis konnte nicht angelegt werden: '. basename( $dir ), 500 );

			$mode = is_file( $path ) === true ? ( @fileperms( $path ) & 0777 ) : 0664;

			if( is_file( $path ) === true && is_writable( $path ) === false )
				throw new Refusal( 'Die Datei ist nicht beschreibbar: '. basename( $path ), 500 );

			$tmp = @tempnam( $dir, '.lab-' );

			if( $tmp !== false ) {

				if( @file_put_contents( $tmp, $text, LOCK_EX ) === strlen( $text ) ) {

					@chmod( $tmp, $mode );

					if( @rename( $tmp, $path ) === true )
						return;
				}

				@unlink( $tmp );
			}

			if( @file_put_contents( $path, $text, LOCK_EX ) !== strlen( $text ) )
				throw new Refusal( 'Die Datei konnte nicht geschrieben werden: '. basename( $path ), 500 );
		}

		/** A refusal of the css of the Design (Themes::refusal() - its patterns; its size limit is a theme's, a library file may be larger), in words */
		private static function refusal( string $css ): string {

			$code = Themes::refusal( $css );

			// The size alone is not a reason here, the patterns are (the same three Themes::refusal() looks for)
			if( $code === 'design_size' )
				$code = match( true ) {
					preg_match( '/@import\b/i', $css ) === 1 														=> 'design_import',
					preg_match( '/javascript\s*:/i', $css ) === 1 || preg_match( '/[<]script/i', $css ) === 1 	=> 'design_script',
					preg_match( '/[<]\/style/i', $css ) === 1 													=> 'design_tag',
					default 																														=> '',
				};

			return match( $code ) {
				'' 						=> '',
				'design_import' => '@import ist in Design-CSS nicht erlaubt.',
				'design_script' => 'javascript: oder <script im CSS ist nicht erlaubt.',
				'design_tag' 		=> '</style im CSS ist nicht erlaubt.',
				default 			=> 'Das CSS wurde abgelehnt ('. $code. ').',
			};
		}

		/* ---------------------------------------------------------------- preview */

		private static function actionPreview( array &$appData, array $request, array $data ): array {

			$started = microtime( true );
			$r = self::resolve( $appData, $data );
			$css = self::text( $data, 'css' ) ?? '';
			$tpl = self::text( $data, 'template' );
			$lib = \Nino\Modules\Design::libraryDir();
			$part = $r['part']['key'];
			$set = $r['variant']['key'];

			$refused = self::refusal( $css );

			if( $refused !== '' )
				return [ 'ok' => false, 'error' => $refused ];

			if( $tpl !== null && $r['variant']['template'] === '' )
				$tpl = null;

			$slug = is_string( $data['theme'] ?? null ) === true ? $data['theme'] : '';
			$mode = in_array( $data['mode'] ?? '', [ 'dark', 'auto' ], true ) === true ? (string) $data['mode'] : 'light';
			$locale = is_string( $data['locale'] ?? null ) === true && \Nino\Locales::verifyLocale( $appData, $data['locale'] ) === true ? $data['locale'] : \Nino\Locales::getCurrentLocale( $appData );
			$menu = in_array( $data['menu'] ?? '', [ 'short', 'long' ], true ) === true ? (string) $data['menu'] : 'project';
			$nonce = is_string( $data['nonce'] ?? null ) === true && preg_match( '/^[A-Za-z0-9+\/=]{16,64}$/D', $data['nonce'] ) === 1 ? $data['nonce'] : '';

			if( $nonce === '' )
				return [ 'ok' => false, 'error' => 'Die Seite hat kein gültiges Script-Token, bitte neu laden.' ];

			$raw = Themes::read( $appData, $lib, $slug );

			if( $raw === null )
				return [ 'ok' => false, 'error' => 'Das Theme "'. $slug. '" gibt es nicht.' ];

			$overlay = self::overlay( $lib );

			// Errors the render raises are collected, not logged or fatal: a broken template is a message above the preview
			self::$_caught = [];
			set_error_handler( static function( int $level, string $message ): bool {
				self::$_caught[] = [ 'level' => $level, 'message' => $message ];
				return true;
			} );

			try {

				// The typed text in place of the library files, in a copy of the library
				self::put( $overlay, $r['file']['id'], $css );

				if( $tpl !== null )
					self::put( $overlay, $r['variant']['template'], $tpl );

				$notes = [];

				// The theme's own setup, the way the panel loads it, with this variant chosen for the part
				$wanted = Themes::setup( $raw );
				$taken = [ 'theme' => $slug ];

				foreach( [ 'size', 'width', 'whitespace', 'radius', 'radius-alt' ] as $key )
					if( array_key_exists( $key, $wanted ) === true )
						$taken[$key] = $wanted[$key];

				foreach( [ 'radii', 'motion', 'parts', 'navs', 'knobs', 'styles', 'features', 'tiers', 'fonts', 'colours' ] as $key )
					$taken[$key] = is_array( $wanted[$key] ?? null ) === true ? $wanted[$key] : [];

				// A theme names no colours of its own: the brand stays the project's
				if( is_array( $wanted['colours'] ?? null ) === false )
					$taken['colours'] = (array) ( Setup::read( $appData, $lib )['colours'] ?? [] );

				// 'auto' leaves the mode of the theme or the project as it is, which is how a visitor gets the site: the system decides
				if( $mode !== 'auto' )
					$taken['colours']['mode'] = $mode;

				$taken['css'] = Themes::css( $raw );
				$taken['parts'][$part] = $set;

				// A file of a style is shown with that style chosen
				if( $r['file']['kind'] === 'style' ) {
					$taken['styles'][$part] = is_array( $taken['styles'][$part] ?? null ) === true ? $taken['styles'][$part] : [];
					$taken['styles'][$part][ $r['file']['style'] ] = $r['file']['option'];
				}

				$setup = Setup::normalize( $appData, $taken, $overlay, $notes );
				$stands = null;

				// A file of a knob is written only where the knob is off its middle: put it on the position that was asked for
				if( $r['file']['kind'] === 'knob' ) {

					$allowed = array_column( $r['file']['steps'], 'value' );
					$asked = $data['step'] ?? null;
					$stands = is_int( $asked ) === true && in_array( $asked, $allowed, true ) === true ? $asked : self::defaultStep( $allowed );
					$setup['knobs'][$part][ $r['file']['knob'] ] = $stands;
				}

				$notes = [];
				$sheet = Compiler::compile( $appData, $setup, $overlay, $notes );
				$public = \Nino\Filesystem::getPublicDir( $appData );
				$sheet = self::inlineFonts( $appData, str_replace( '[[/nino/public]]', $public, $sheet ) );

				$work = $appData;
				$head = self::styleNotes( $overlay, $r );

				\Nino\Html::addFills( $work, self::fills( $work ), '*' );
				\Nino\Html::addFills( $work, \Nino\Html::requestFills( [ '/nino/http/request' => [ 'uri' => self::home( $work ) ], '/nino/http/response' => [ 'uri' => self::home( $work ), 'locale' => $locale ] ], '' ), '*' );

				if( $menu !== 'project' )
					self::menu( $work, $menu, $locale );

				$blocks = Preview::blocks( $work, $notes );
				\Nino\Locales::useLocale( $work, $locale );

				$body = Preview::render( $work, $overlay, $setup, $notes, $blocks );
				$document = self::document( $work, $locale, $sheet, $body, $nonce );
			}
			catch( \Throwable $e ) {
				restore_error_handler();
				self::rmtree( $overlay );
				return [ 'ok' => false, 'error' => 'Fehler beim Zeichnen der Vorschau: '. $e->getMessage() ];
			}

			restore_error_handler();
			self::rmtree( $overlay );

			// Warnings: what is wrong with the text itself. Hints: what the library and the compiler say about it
			$warnings = array_merge( self::lintCss( $css ), $tpl !== null ? self::lintTemplate( $work, $tpl ) : [] );
			$hints = array_merge( $head, array_map( static fn( string $n ): string => 'Compiler: '. $n, array_values( array_unique( array_filter( $notes, 'is_string' ) ) ) ) );
			$errors = [];

			foreach( self::$_caught as $c ) {

				$line = 'PHP/Nino: '. $c['message'];

				if( in_array( $c['level'], [ E_USER_ERROR, E_ERROR, E_RECOVERABLE_ERROR ], true ) === true )
					$errors[] = $line;
				else
					$warnings[] = $line;
			}

			// A frame that rendered with shortcodes left in it is what a broken template looks like
			if( $tpl !== null && preg_match_all( '/\[(?:\/)?[a-z][a-z0-9-]*(?: [^\]\[<>\n]{0,80})?\]/', strip_tags( $body ), $left ) > 0 && $left[0] !== [] )
				$warnings[] = 'Im Ergebnis stehen noch Shortcodes im Text: '. implode( ' ', array_slice( array_unique( $left[0] ), 0, 4 ) );

			if( $errors !== [] )
				return [ 'ok' => false, 'error' => implode( "\n", $errors ) ];

			return [
				'ok' 			=> true,
				'html' 		=> $document,
				'warnings' => array_values( array_unique( $warnings ) ),
				'hints' 	=> array_values( array_unique( $hints ) ),
				'anchor' 	=> self::ANCHORS[$part] ?? 'global',
				'step' 		=> $stands,
				'ms' 			=> (int) round( ( microtime( true ) - $started ) * 1000 ),
			];
		}

		private static function defaultStep( array $allowed ): int {

			foreach( [ 1, 2, -1, 3, 4 ] as $try )
				if( in_array( $try, $allowed, true ) === true )
					return $try;

			return (int) ( $allowed[0] ?? 0 );
		}

		/** What the library says about the styles of this variant that it does not offer (a file named for no style, a @style without a file) */
		private static function styleNotes( string $overlay, array $r ): array {

			$notes = [];
			Setup::styles( $overlay, $r['part']['key'], $r['variant']['key'], 'en_US', $notes );

			return array_map( static fn( mixed $n ): string => 'Bibliothek: '. (string) $n, $notes );
		}

		private static function home( array &$appData ): string {
			return (string) ( $appData['/nino/http/routes']['GET://']['uri'] ?? '' ) !== '' ? (string) $appData['/nino/http/routes']['GET://']['uri'] : '/design-preview';
		}

		/** What the frames resolve through a page: the front page of the project stands in for the page, as the panel does */
		private static function fills( array &$appData ): array {

			if( (string) ( $appData['/nino/http/routes']['GET://']['uri'] ?? '' ) !== '' )
				return [ '/nino/http/response/uri' => (string) $appData['/nino/http/routes']['GET://']['uri'] ];

			return [
				'/nino/http/response/uri' 						=> '/design-preview',
				'/_nino/webpage/design-preview/title' => 'Vorschau',
				'/_nino/webpage/design-preview/name' 	=> 'Vorschau',
			];
		}

		/** A shorter or a longer menu than the project's, through the kernel's own hook for the lines of a navigation */
		private static function menu( array &$appData, string $mode, string $locale ): void {

			$titles = self::MENU_TITLES[ str_starts_with( $locale, 'de' ) === true ? 'de' : 'en' ];

			\Nino\Callbacks::registerCallback( $appData, '/nino/navigation/lines', static function( array &$appData, mixed &$args ) use ( $mode, $titles ): array {

				$lines = is_array( $args['lines'] ?? null ) === true ? array_values( $args['lines'] ) : [];

				if( $mode === 'short' )
					$lines = array_slice( $lines, 0, 3 );
				else
					foreach( $titles as $i => $title )
						$lines[] = '/menu-beispiel-'. ( $i + 1 ). ':'. $title;

				$args['lines'] = $mode === 'long' ? array_slice( $lines, 0, max( 14, count( $lines ) ) ) : $lines;

				return $args;
			}, 99 );
		}

		/** The page around the render: the framework as a link, the sheets of the features and the compiled sheet inline, the framework's scripts with the nonce */
		private static function document( array &$appData, string $locale, string $sheet, string $body, string $nonce ): string {

			$dir = rtrim( \Nino\Filesystem::getDir( $appData ), '/' );
			$features = '';

			foreach( \Nino\Html::getAssets( $appData, '/.cache/style.css' ) as $asset ) {

				if( is_string( $asset ) === false || str_starts_with( $asset, '/features/' ) === false || preg_match( '#\.css$#', $asset ) !== 1 )
					continue;

				$file = \Nino\Filesystem::path( $appData, $asset );

				if( $file !== '' && is_file( $file ) === true )
					$features .= "\n". (string) @file_get_contents( $file );
			}

			$head = '<meta http-equiv="Content-Security-Policy" content="script-src \'nonce-'. $nonce. '\'; form-action \'none\'; base-uri \'none\'">'
				. '<link rel="stylesheet" href="'. htmlspecialchars( $dir, ENT_QUOTES ). '/_nino/Nino.css">'
				. ( $features !== '' ? '<style>'. str_replace( '</style', '<\\/style', $features ). '</style>' : '' )
				. '<style id="design-preview-css">'. $sheet. '</style>';

			$tail = '<script nonce="'. $nonce. '" src="'. htmlspecialchars( $dir, ENT_QUOTES ). '/_nino/Nino.js"></script>'
				. '<script nonce="'. $nonce. '" src="'. htmlspecialchars( $dir, ENT_QUOTES ). '/_nino/Nino.ui.js"></script>';

			return Preview::document( $appData, $locale, $head, '<main>'. $body. '</main>', $tail );
		}

		/** The fonts of the compiled sheet as data uris: a frame without an origin of its own cannot fetch a font from the site */
		private static function inlineFonts( array &$appData, string $css ): string {

			$public = \Nino\Filesystem::getPublicDir( $appData );
			$root = (string) ( $appData['./nino/filesystem/publicpath'] ?? '' );
			$types = [ 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf' ];

			return (string) preg_replace_callback( '/url\(\s*(["\']?)([^)"\']+)\1\s*\)/', static function( array $m ) use ( $public, $root, $types ): string {

				$prefix = $public. '/fonts/';

				if( $root === '' || str_starts_with( $m[2], $prefix ) === false )
					return $m[0];

				$name = substr( $m[2], strlen( $prefix ) );
				$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

				if( preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $name ) !== 1 || isset( $types[$ext] ) === false )
					return $m[0];

				$file = $root. '/fonts/'. $name;

				if( is_file( $file ) === false || is_link( $file ) === true || filesize( $file ) > 3145728 )
					return $m[0];

				return 'url(data:'. $types[$ext]. ';base64,'. base64_encode( (string) @file_get_contents( $file ) ). ')';
			}, $css );
		}

		/* ---------------------------------------------------------------- overlay */

		/** A copy of the library in the temporary directory, which the typed text is put into; gone at the end of the request */
		private static function overlay( string $lib ): string {

			$tmp = rtrim( sys_get_temp_dir(), '/\\' );
			$root = $tmp. '/nino-design-lab-'. bin2hex( random_bytes( 8 ) );

			if( @mkdir( $root, 0700 ) === false )
				throw new Refusal( 'Das temporäre Verzeichnis konnte nicht angelegt werden.', 500 );

			register_shutdown_function( static fn() => self::rmtree( $root ) );

			$lib = rtrim( $lib, '/' );
			$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $lib, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );

			foreach( $items as $item ) {

				$rel = substr( $item->getPathname(), strlen( $lib ) + 1 );

				if( $item->isLink() === true )
					continue;

				if( $item->isDir() === true )
					@mkdir( $root. '/'. $rel, 0700, true );
				else
					@copy( $item->getPathname(), $root. '/'. $rel );
			}

			return $root;
		}

		private static function put( string $overlay, string $id, string $text ): void {

			$path = $overlay. '/'. $id;

			if( is_dir( dirname( $path ) ) === false )
				@mkdir( dirname( $path ), 0700, true );

			if( @file_put_contents( $path, $text ) === false )
				throw new Refusal( 'Der Text konnte nicht in die Vorschau-Bibliothek geschrieben werden.', 500 );
		}

		/** Removes a directory this file made - and only one: the name and the parent are checked */
		private static function rmtree( string $dir ): void {

			$tmp = (string) realpath( sys_get_temp_dir() );
			$real = (string) realpath( $dir );

			if( $real === '' || $tmp === '' || dirname( $real ) !== $tmp || str_starts_with( basename( $real ), 'nino-design-lab-' ) === false )
				return;

			$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $real, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );

			foreach( $items as $item )
				if( $item->isDir() === true && $item->isLink() === false )
					@rmdir( $item->getPathname() );
				else
					@unlink( $item->getPathname() );

			@rmdir( $real );
		}

		/* ------------------------------------------------------------------- lint */

		/** Braces and comments of a css text: what a browser would silently swallow */
		private static function lintCss( string $css ): array {

			$out = [];
			$stripped = (string) preg_replace( '~/\*.*?\*/~s', '', $css );

			if( str_contains( $css, '/*' ) === true && substr_count( $css, '/*' ) > substr_count( $css, '*/' ) )
				$out[] = 'CSS: Ein Kommentar ist nicht geschlossen (/* ohne */).';

			$stripped = (string) preg_replace( '/"(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'/', '""', $stripped );
			$open = substr_count( $stripped, '{' );
			$close = substr_count( $stripped, '}' );

			if( $open !== $close )
				$out[] = 'CSS: Die geschweiften Klammern sind nicht ausgeglichen ('. $open. ' mal { gegen '. $close. ' mal }).';

			if( substr_count( $stripped, '(' ) !== substr_count( $stripped, ')' ) )
				$out[] = 'CSS: Die runden Klammern sind nicht ausgeglichen.';

			return $out;
		}

		/** What is wrong with a template before it is rendered: tags that are not closed or closed twice, brackets that do not pair, an include with no file behind it */
		private static function lintTemplate( array &$appData, string $tpl ): array {

			$out = [];

			if( trim( $tpl ) === '' )
				return [ 'Template: Das Template ist leer, die Vorschau zeigt die Seite ohne diesen Teil.' ];

			if( substr_count( $tpl, '[' ) !== substr_count( $tpl, ']' ) )
				$out[] = 'Template: Eckige Klammern sind nicht ausgeglichen - ein Shortcode ist vielleicht nicht geschlossen.';

			if( preg_match( '/<script\b|\son[a-z]+\s*=/i', $tpl ) === 1 )
				$out[] = 'Template: Skripte und on...-Attribute laufen in der Vorschau nicht (sie bleiben gesperrt).';

			if( preg_match_all( '/\[template\s+(\/[A-Za-z0-9_\/.-]+)\]/', $tpl, $includes ) > 0 )
				foreach( array_unique( $includes[1] ) as $include ) {

					$file = \Nino\Filesystem::path( $appData, $include. '.tpl' );

					if( $file === '' || is_file( $file ) === false )
						$out[] = 'Template: [template '. $include. '] findet keine Datei ('. $include. '.tpl).';
				}

			// A stack of the tags that are open; void elements and comments have none
			$void = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];
			$clean = (string) preg_replace_callback( '/<!--.*?-->/s', static fn( array $m ): string => str_repeat( ' ', strlen( $m[0] ) ), $tpl );
			$count = (int) preg_match_all( '/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/s', $clean, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
			$stack = [];
			$tagNotes = [];
			$line = static fn( int $offset ): int => substr_count( $clean, "\n", 0, $offset ) + 1;

			for( $i = 0; $i < $count; $i++ ) {

				$name = strtolower( $tags[$i][2][0] );
				$at = (int) $tags[$i][0][1];

				if( in_array( $name, $void, true ) === true || str_ends_with( rtrim( $tags[$i][3][0] ), '/' ) === true )
					continue;

				if( $tags[$i][1][0] === '' ) {
					$stack[] = [ $name, $at ];
					continue;
				}

				$found = null;

				foreach( array_reverse( array_keys( $stack ) ) as $k )
					if( $stack[$k][0] === $name ) { $found = $k; break; }

				if( $found === null ) {
					$tagNotes[] = 'Template: </'. $name. '> in Zeile '. $line( $at ). ' schließt nichts, das offen ist.';
					continue;
				}

				foreach( array_slice( $stack, $found + 1 ) as $open )
					$tagNotes[] = 'Template: <'. $open[0]. '> aus Zeile '. $line( $open[1] ). ' ist vor </'. $name. '> nicht geschlossen.';

				$stack = array_slice( $stack, 0, $found );
			}

			foreach( $stack as $open )
				$tagNotes[] = 'Template: <'. $open[0]. '> aus Zeile '. $line( $open[1] ). ' wird nicht geschlossen.';

			return array_merge( $out, array_slice( $tagNotes, 0, 5 ) );
		}

		/* -------------------------------------------------------------- templates */

		private const string HTML = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Design-Labor</title>
<style nonce="__NONCE__">__CSS__</style>
</head>
<body>
<div class="warn" role="note"><strong>Entwicklerwerkzeug.</strong> <span>Diese Datei (<code>design-lab.php</code>) kann Dateien der Design-Bibliothek überschreiben. Nach Gebrauch vom Server löschen, nicht ins Repository oder Release aufnehmen.</span></div>

<header class="bar">
	<div class="field"><label for="f-part">Part</label><select id="f-part"></select></div>
	<div class="field"><label for="f-variant">Variante</label><select id="f-variant"></select></div>
	<div class="field"><label for="f-file">Datei</label><select id="f-file"></select></div>
	<div class="field" id="w-step" hidden><label for="f-step">Reglerstellung</label><select id="f-step"></select></div>
	<div class="field"><label for="f-theme">Theme</label><select id="f-theme"></select></div>
	<div class="field"><label for="f-mode">Modus</label>
		<div class="seg" role="group" aria-label="Hell oder dunkel"><button type="button" id="m-light" aria-pressed="true">Hell</button><button type="button" id="m-dark" aria-pressed="false">Dunkel</button><button type="button" id="m-auto" aria-pressed="false" title="Der Modus der Site: das System entscheidet">System</button></div>
	</div>
	<div class="field"><label for="f-width">Breite</label>
		<span class="inline"><select id="f-width"><option value="390">390</option><option value="768">768</option><option value="1280" selected>1280</option><option value="free">frei</option></select><input id="f-free" type="number" min="240" max="3000" step="10" value="1024" hidden aria-label="Freie Breite in Pixeln"></span>
	</div>
	<div class="field" id="w-locale"><label for="f-locale">Sprache</label><select id="f-locale"></select></div>
	<div class="field" id="w-menu"><label for="f-menu">Menü</label><select id="f-menu"><option value="project">wie die Site</option><option value="short">kurz (3)</option><option value="long">lang (+14)</option></select></div>
	<div class="actions">
		<span id="state" class="chip" role="status">geladen</span>
		<button type="button" id="b-reset">Zurücksetzen</button>
		<button type="button" id="b-save" class="primary" title="Strg/Cmd+S">In die Bibliothek speichern</button>
	</div>
</header>

<main class="work" id="work">
	<section class="left" id="left" aria-label="Editor">
		<div class="pane" id="pane-tpl">
			<div class="pane-head"><label for="t-tpl">Template</label><span class="meta" id="meta-tpl"></span><button type="button" class="small" id="c-tpl">Kopieren</button></div>
			<div class="ed"><pre class="gut" id="g-tpl" aria-hidden="true">1</pre><textarea id="t-tpl" spellcheck="false" autocomplete="off" autocapitalize="off" wrap="off" aria-describedby="hint-tpl"></textarea></div>
			<p class="hint" id="hint-tpl" hidden>Diese Variante hat kein Template, nur CSS.</p>
		</div>
		<div class="pane" id="pane-css">
			<div class="pane-head"><label for="t-css">CSS</label><span class="meta" id="meta-css"></span><button type="button" class="small" id="c-css">Kopieren</button></div>
			<div class="ed"><pre class="gut" id="g-css" aria-hidden="true">1</pre><textarea id="t-css" spellcheck="false" autocomplete="off" autocapitalize="off" wrap="off"></textarea></div>
		</div>
		<p class="tabhint">Tab rückt ein, Umschalt+Tab aus. Esc, dann Tab verlässt das Feld.</p>
	</section>

	<div class="split" id="split" role="separator" aria-orientation="vertical" aria-label="Teilung zwischen Editor und Vorschau" tabindex="0"></div>

	<section class="right" aria-label="Vorschau">
		<div class="pv-bar">
			<div class="field"><label for="f-jump">Ansicht</label><select id="f-jump"></select></div>
			<button type="button" class="small" id="b-reload">Neu zeichnen</button>
			<span class="pv-info" id="pv-info"></span>
		</div>
		<div id="msgs" class="msgs" aria-live="polite"></div>
		<div class="stage" id="stage"><div class="frames" id="frames"></div><p class="empty" id="empty">Vorschau wird geladen ...</p></div>
	</section>
</main>

<script type="application/json" id="boot">__BOOT__</script>
<script nonce="__NONCE__">__JS__</script>
</body>
</html>
HTML;

		private const string CSS = <<<'CSS'
:root {
	color-scheme: light dark;
	--bg: #f4f5f7; --panel: #ffffff; --ink: #1c2230; --muted: #5a6478; --line: #d3d8e0; --field: #ffffff;
	--accent: #1f5fd1; --accent-ink: #ffffff; --warn-bg: #fff2cc; --warn-ink: #4d3a00; --warn-line: #e0b84a;
	--err-bg: #fde8e8; --err-ink: #7a1212; --err-line: #e19a9a; --hint-bg: #eef3fb; --hint-ink: #263a5e; --hint-line: #b7c7e4;
	--ok: #1d7a43; --dirty: #b3560b; --gut: #eceff4; --gut-ink: #8089a0;
}
@media (prefers-color-scheme: dark) {
	:root {
		--bg: #12151b; --panel: #1a1e27; --ink: #e4e8f0; --muted: #98a2b8; --line: #343b4b; --field: #11141a;
		--accent: #6aa0ff; --accent-ink: #0b1220; --warn-bg: #3a2f0a; --warn-ink: #ffe9a8; --warn-line: #7a6320;
		--err-bg: #3b1717; --err-ink: #ffc9c9; --err-line: #7d3535; --hint-bg: #1b2538; --hint-ink: #c5d5f2; --hint-line: #34466a;
		--ok: #54c585; --dirty: #ffa24d; --gut: #161a22; --gut-ink: #6b748a;
	}
}
* { box-sizing: border-box; }
html, body { height: 100%; }
body { margin: 0; background: var(--bg); color: var(--ink); font: 14px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; display: flex; flex-direction: column; }
code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .95em; }
[hidden] { display: none !important; }
.warn { background: var(--warn-bg); color: var(--warn-ink); border-bottom: 2px solid var(--warn-line); padding: 6px 16px; font-size: 13px; }
.bar { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: flex-end; padding: 10px 16px; background: var(--panel); border-bottom: 1px solid var(--line); }
.field { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.field > label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
.inline { display: flex; gap: 4px; }
select, input[type=number], button { font: inherit; color: var(--ink); background: var(--field); border: 1px solid var(--line); border-radius: 6px; padding: 5px 8px; min-height: 30px; }
select { max-width: 30ch; }
button { cursor: pointer; }
button:hover { border-color: var(--accent); }
button:focus-visible, select:focus-visible, input:focus-visible, textarea:focus-visible, .split:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
button.primary { background: var(--accent); color: var(--accent-ink); border-color: var(--accent); font-weight: 600; }
button.primary:disabled { opacity: .45; cursor: default; }
button.small { padding: 2px 8px; min-height: 24px; font-size: 12px; }
input[type=number] { width: 7ch; }
.seg { display: inline-flex; }
.seg button { border-radius: 0; margin-left: -1px; }
.seg button:first-child { border-radius: 6px 0 0 6px; margin-left: 0; }
.seg button:last-child { border-radius: 0 6px 6px 0; }
.seg button[aria-pressed=true] { background: var(--accent); color: var(--accent-ink); border-color: var(--accent); }
.actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.chip { font-size: 12px; padding: 2px 10px; border-radius: 999px; border: 1px solid var(--line); color: var(--ok); white-space: nowrap; }
.chip.dirty { color: var(--dirty); border-color: var(--dirty); font-weight: 600; }
.chip.busy { color: var(--muted); }

.work { flex: 1 1 auto; min-height: 0; display: flex; --left: 42%; }
.left { flex: 0 0 var(--left); min-width: 220px; display: flex; flex-direction: column; min-height: 0; background: var(--panel); }
.pane { flex: 1 1 0; min-height: 0; display: flex; flex-direction: column; border-bottom: 1px solid var(--line); }
.pane.off { flex: 0 0 auto; }
.pane.off .ed { display: none; }
.pane-head { display: flex; align-items: center; gap: 10px; padding: 4px 10px; background: var(--bg); border-bottom: 1px solid var(--line); }
.pane-head label { font-weight: 600; }
.pane-head .meta { color: var(--muted); font-size: 12px; margin-right: auto; font-variant-numeric: tabular-nums; }
.hint { margin: 0; padding: 8px 12px; color: var(--muted); }
.ed { position: relative; flex: 1 1 auto; min-height: 0; display: flex; }
.gut, textarea { font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; tab-size: 4; margin: 0; padding: 8px 0; }
.gut { flex: 0 0 auto; min-width: 3.4em; padding-right: 8px; text-align: right; background: var(--gut); color: var(--gut-ink); overflow: hidden; user-select: none; border-right: 1px solid var(--line); white-space: pre; }
textarea { flex: 1 1 auto; min-width: 0; width: 100%; resize: none; border: 0; border-radius: 0; background: var(--field); color: var(--ink); padding: 8px 10px; white-space: pre; overflow: auto; }
textarea:disabled { opacity: .5; }
.tabhint { margin: 0; padding: 3px 10px; font-size: 11px; color: var(--muted); }
.split { flex: 0 0 7px; cursor: col-resize; background: var(--line); }
.split:hover, .split.drag { background: var(--accent); }
.right { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; min-height: 0; }
.pv-bar { display: flex; gap: 10px; align-items: flex-end; padding: 6px 10px; background: var(--panel); border-bottom: 1px solid var(--line); }
.pv-bar .field { flex-direction: row; align-items: center; gap: 6px; }
.pv-bar .field > label { text-transform: none; letter-spacing: 0; font-size: 12px; }
.pv-info { margin-left: auto; color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }
.msgs:empty { display: none; }
.msgs { max-height: 34%; overflow: auto; flex: 0 0 auto; }
.msg { margin: 0; padding: 6px 12px; border-bottom: 1px solid; font-size: 13px; white-space: pre-wrap; overflow-wrap: anywhere; }
.msg.err { background: var(--err-bg); color: var(--err-ink); border-color: var(--err-line); }
.msg.warn { background: var(--warn-bg); color: var(--warn-ink); border-color: var(--warn-line); }
.msg summary { font-weight: 600; cursor: pointer; }
.msg.hint { background: var(--hint-bg); color: var(--hint-ink); border-color: var(--hint-line); }
.msg.hint ul { margin: 2px 0 0; padding-left: 18px; }
.msg strong { font-weight: 600; }
.stage { position: relative; flex: 1 1 auto; min-height: 0; overflow: hidden; background: repeating-conic-gradient(var(--bg) 0 25%, var(--panel) 0 50%) 0 0 / 20px 20px; }
.frames { position: relative; transform-origin: 0 0; }
.frames iframe { position: absolute; left: 0; top: 0; border: 0; background: #fff; display: block; box-shadow: 0 0 0 1px var(--line); }
.frames iframe.next { visibility: hidden; }
.empty { margin: 0; padding: 24px; color: var(--muted); }

@media (max-width: 900px) {
	body { height: auto; display: block; }
	html { height: auto; }
	.work { display: block; }
	.left { flex: none; }
	.pane { flex: 0 0 auto; height: 38vh; }
	.pane.off { height: auto; }
	.split { display: none; }
	.right { height: 80vh; }
	.actions { margin-left: 0; width: 100%; }
	.bar { position: static; }
}
CSS;

		private const string JS = <<<'JS'
'use strict';
( function() {

const $ = ( id ) => document.getElementById( id );
const boot = JSON.parse( $( 'boot' ).textContent );
const LS = 'design-lab.v1';

// What the page remembers for the next visit - a convenience only, never state that matters
function remembered() { try { return JSON.parse( localStorage.getItem( LS ) || '{}' ) || {}; } catch( e ) { return {}; } }
function remember( patch ) { try { localStorage.setItem( LS, JSON.stringify( Object.assign( remembered(), patch ) ) ); } catch( e ) { /* private window, blocked storage */ } }

const el = {
	part: $( 'f-part' ), variant: $( 'f-variant' ), file: $( 'f-file' ), step: $( 'f-step' ), stepWrap: $( 'w-step' ), theme: $( 'f-theme' ),
	light: $( 'm-light' ), dark: $( 'm-dark' ), auto: $( 'm-auto' ), width: $( 'f-width' ), free: $( 'f-free' ), locale: $( 'f-locale' ), menu: $( 'f-menu' ), menuWrap: $( 'w-menu' ),
	state: $( 'state' ), reset: $( 'b-reset' ), save: $( 'b-save' ), tpl: $( 't-tpl' ), css: $( 't-css' ), gTpl: $( 'g-tpl' ), gCss: $( 'g-css' ),
	metaTpl: $( 'meta-tpl' ), metaCss: $( 'meta-css' ), paneTpl: $( 'pane-tpl' ), hintTpl: $( 'hint-tpl' ), jump: $( 'f-jump' ), reload: $( 'b-reload' ),
	info: $( 'pv-info' ), msgs: $( 'msgs' ), stage: $( 'stage' ), frames: $( 'frames' ), empty: $( 'empty' ), work: $( 'work' ), split: $( 'split' ),
};

const NAMES = { global: 'Seitenanfang (Kopf)', content: 'Hero', cards: 'Karten', colours: 'Abschnitte', type: 'Schrift', buttons: 'Buttons', tables: 'Tabellen', lists: 'Listen', forms: 'Formular', tabs: 'Tabs', accordion: 'Accordion', tiles: 'Kacheln', footer: 'Fußbereich (Footer)' };

const st = {
	part: null, variant: null, file: null, mode: 'light',
	base: { css: '', template: '' }, hash: { css: '', template: '' }, hasTpl: false, loaded: false, busy: false,
	y: null, jump: 'auto', anchor: 'global', seq: 0, timer: 0, ctl: null, last: null, current: null, next: null, loadSeq: 0,
};

/* ------------------------------------------------------------------ helpers */

function option( value, text ) { const o = document.createElement( 'option' ); o.value = value; o.textContent = text; return o; }
function fill( select, items, value ) { select.textContent = ''; items.forEach( ( i ) => select.appendChild( option( i[0], i[1] ) ) ); if( value !== undefined && value !== null ) select.value = value; }
const part = () => boot.parts.find( ( p ) => p.key === st.part );
const variant = () => ( part() || { variants: [] } ).variants.find( ( v ) => v.key === st.variant );
const file = () => ( variant() || { files: [] } ).files.find( ( f ) => f.id === st.file );

function dirty() {
	if( st.loaded === false ) return false;
	return el.css.value !== st.base.css || ( st.hasTpl === true && el.tpl.value !== st.base.template );
}

function paintState( text ) {
	const d = dirty();
	el.state.className = 'chip' + ( d ? ' dirty' : '' ) + ( st.busy ? ' busy' : '' );
	el.state.textContent = text || ( d ? 'ungespeichert' : 'gespeichert' );
	el.save.disabled = d === false;
	document.title = ( d ? '● ' : '' ) + 'Design-Labor' + ( st.part ? ' – ' + st.part + ' / ' + st.variant : '' );
}

async function post( action, data, signal ) {
	let res;
	try {
		res = await fetch( location.pathname + '?a=' + action, { method: 'POST', credentials: 'same-origin', signal, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': boot.csrf }, body: JSON.stringify( data ) } );
	}
	catch( e ) {
		if( e && e.name === 'AbortError' ) throw e;
		throw new Error( 'Der Server ist nicht erreichbar.' );
	}
	let body = null;
	try { body = await res.json(); } catch( e ) { /* not json */ }
	if( res.ok === false || body === null || ( body.error && body.ok !== false ) ) {
		const err = new Error( body && body.error ? body.error : 'Serverfehler (' + res.status + ').' );
		err.status = res.status; err.body = body;
		throw err;
	}
	return body;
}

function lines( ta, gut, meta, path ) {
	const n = ta.value === '' ? 1 : ta.value.split( '\n' ).length;
	if( gut.dataset.n !== String( n ) ) {
		gut.dataset.n = String( n );
		let out = '';
		for( let i = 1; i <= n; i++ ) out += i + '\n';
		gut.textContent = out;
	}
	gut.scrollTop = ta.scrollTop;
	meta.textContent = ( path ? path + ' · ' : '' ) + n + ' Zeilen, ' + ta.value.length + ' Zeichen';
}

function paintMeta() {
	const f = file(), v = variant();
	lines( el.css, el.gCss, el.metaCss, f ? 'library/' + f.id : '' );
	if( st.hasTpl ) lines( el.tpl, el.gTpl, el.metaTpl, v ? 'library/' + v.template : '' );
	else { el.metaTpl.textContent = 'kein Template'; el.gTpl.textContent = ''; el.gTpl.dataset.n = ''; }
}

/* ---------------------------------------------------------------- selection */

function guard() {
	if( dirty() === false ) return true;
	return window.confirm( 'Es gibt ungespeicherte Änderungen. Verwerfen?' );
}

function pickStep( steps ) {
	const values = steps.map( ( s ) => s.value );
	for( const t of [ 1, 2, -1, 3, 4 ] ) if( values.includes( t ) ) return t;
	return values[0];
}

function fillParts() {
	fill( el.part, boot.parts.map( ( p ) => [ p.key, p.label ] ) );
}

function fillVariants() {
	fill( el.variant, ( part() ? part().variants : [] ).map( ( v ) => [ v.key, v.label ] ) );
}

function fillFiles() {
	fill( el.file, ( variant() ? variant().files : [] ).map( ( f ) => [ f.id, f.label + ( f.exists ? '' : ' - noch nicht vorhanden' ) ] ) );
}

function fillSteps() {
	const f = file();
	el.stepWrap.hidden = !( f && f.kind === 'knob' );
	if( el.stepWrap.hidden ) return;
	fill( el.step, f.steps.map( ( s ) => [ String( s.value ), s.word + ' (' + ( s.value > 0 ? '+' : '' ) + s.value + ')' ] ), String( pickStep( f.steps ) ) );
}

function fillJump() {
	const keep = el.jump.value || 'auto';
	const items = [ [ 'auto', 'automatisch (passend zum Part)' ] ];
	Object.keys( NAMES ).forEach( ( k ) => items.push( [ k, NAMES[k] ] ) );
	fill( el.jump, items, keep );
}

async function select( what, value, force ) {

	if( force !== true && guard() === false ) {
		el[what].value = st[what] || '';
		return;
	}

	if( what === 'part' ) {
		st.part = value; fillVariants();
		el.jump.value = 'auto'; st.jump = 'auto';
		const memory = remembered().variants || {};
		const want = part().variants.some( ( v ) => v.key === memory[value] ) ? memory[value] : ( part().variants[0] || {} ).key;
		el.variant.value = want || '';
		st.variant = el.variant.value;
		fillFiles(); st.file = el.file.value;
	}
	else if( what === 'variant' ) {
		st.variant = value; fillFiles(); st.file = el.file.value;
		const memory = remembered().variants || {}; memory[st.part] = value; remember( { variants: memory } );
	}
	else st.file = value;

	remember( { part: st.part } );
	fillSteps();
	el.menuWrap.hidden = [ 'header', 'footer' ].includes( st.part ) === false;
	await load( true );
}

async function load( reanchor ) {

	const my = ++st.loadSeq;
	st.loaded = false; st.busy = true; paintState( 'lädt ...' );
	el.css.disabled = true; el.tpl.disabled = true;

	try {
		const r = await post( 'file', { part: st.part, variant: st.variant, file: st.file } );
		if( my !== st.loadSeq ) return;
		el.css.value = r.css;
		st.hasTpl = r.template !== null;
		el.tpl.value = st.hasTpl ? r.template : '';
		st.base = { css: r.css, template: st.hasTpl ? r.template : '' };
		st.hash = r.hash;
		st.loaded = true;
		el.paneTpl.classList.toggle( 'off', st.hasTpl === false );
		el.hintTpl.hidden = st.hasTpl;
		el.tpl.disabled = st.hasTpl === false;
		el.css.disabled = false;
		el.gCss.dataset.n = ''; el.gTpl.dataset.n = '';
		st.busy = false;
		paintMeta(); paintState( r.exists === false ? 'neue Datei' : 'geladen' );
		run( reanchor === true, 0 );
	}
	catch( e ) {
		if( my !== st.loadSeq ) return;
		st.busy = false; el.css.disabled = false; el.tpl.disabled = st.hasTpl === false;
		paintState( 'Fehler' );
		message( 'err', 'Laden fehlgeschlagen: ' + e.message );
	}
}

/* ------------------------------------------------------------------ preview */

function message( kind, text, items ) {
	const p = document.createElement( kind === 'err' ? 'div' : 'details' );
	p.className = 'msg ' + kind;
	if( kind === 'err' ) { const s = document.createElement( 'strong' ); s.textContent = 'Fehler: '; p.appendChild( s ); p.appendChild( document.createTextNode( text ) ); }
	else {
		const s = document.createElement( 'summary' ); s.textContent = text; p.appendChild( s );
		p.open = kind === 'warn' || ( items || [] ).length <= 3;
		const ul = document.createElement( 'ul' );
		( items || [] ).forEach( ( t ) => { const li = document.createElement( 'li' ); li.textContent = t; ul.appendChild( li ); } );
		p.appendChild( ul );
	}
	return el.msgs.appendChild( p );
}

function clearMessages() { el.msgs.textContent = ''; }
const plural = ( n, one, many ) => n + ' ' + ( n === 1 ? one : many );

function width() { return el.width.value === 'free' ? Math.max( 240, Math.min( 3000, parseInt( el.free.value, 10 ) || 1024 ) ) : parseInt( el.width.value, 10 ); }

function payload() {
	const f = file();
	return {
		part: st.part, variant: st.variant, file: st.file, template: st.hasTpl ? el.tpl.value : null, css: el.css.value,
		theme: el.theme.value, mode: st.mode, locale: el.locale.value, menu: el.menu.value, nonce: boot.nonce,
		step: f && f.kind === 'knob' ? parseInt( el.step.value, 10 ) : null,
	};
}

function schedule() {
	if( st.loaded === false ) return;
	window.clearTimeout( st.timer );
	st.timer = window.setTimeout( () => run( false ), 300 );
	paintState();
}

// What runs in the frame besides the site's own scripts: put the page where it was (or at the part), report the scroll, keep the frame from navigating
function child( S ) {
	let touched = false;
	const win = window, doc = document;
	function place( anchor, y ) {
		if( typeof y === 'number' ) { win.scrollTo( { top: y, behavior: 'instant' } ); return; }
		const target = anchor && anchor !== 'global' ? doc.getElementById( anchor ) : null;
		if( target === null ) { win.scrollTo( { top: 0, behavior: 'instant' } ); return; }
		const main = doc.querySelector( 'main' );
		const clear = main ? ( parseFloat( win.getComputedStyle( main ).paddingTop ) || 0 ) : 0;
		const top = target.getBoundingClientRect().top + ( win.scrollY || 0 );
		win.scrollTo( { top: Math.max( 0, Math.round( anchor === 'footer' ? top : top - clear ) ), behavior: 'instant' } );
	}
	// No scroll anchoring: a font that arrives after the first layout must not move the page the way it was put
	doc.documentElement.style.overflowAnchor = 'none';
	place( S.anchor, S.y );
	[ 'wheel', 'touchstart', 'keydown', 'mousedown' ].forEach( ( t ) => win.addEventListener( t, () => { touched = true; }, { passive: true } ) );
	win.addEventListener( 'load', () => { if( touched === false ) place( S.anchor, S.y ); } );
	const again = () => { if( touched === false && win.performance.now() < 4000 ) place( S.anchor, S.y ); };
	if( doc.fonts ) { doc.fonts.ready.then( again ); doc.fonts.addEventListener( 'loadingdone', again ); }
	let wait = 0;
	win.addEventListener( 'scroll', () => {
		if( wait !== 0 ) return;
		wait = win.setTimeout( () => { wait = 0; parent.postMessage( { lab: 'scroll', y: win.scrollY }, '*' ); }, 100 );
	}, { passive: true } );
	win.addEventListener( 'message', ( e ) => {
		if( e.source !== parent || !e.data || e.data.lab !== 'goto' ) return;
		touched = true;
		place( String( e.data.id || '' ), null );
	} );
	doc.addEventListener( 'click', ( e ) => {
		const a = e.target && e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if( a !== null && ( a.getAttribute( 'href' ) || '' ).charAt( 0 ) !== '#' ) e.preventDefault();
	}, true );
	doc.addEventListener( 'submit', ( e ) => e.preventDefault(), true );
}

function injectChild( html, anchor, y ) {
	const tag = '<script nonce="' + boot.nonce + '">(' + child.toString() + ')(' + JSON.stringify( { anchor, y } ) + ')<\/script>';
	const at = html.lastIndexOf( '</body>' );
	return at < 0 ? html + tag : html.slice( 0, at ) + tag + html.slice( at );
}

async function run( reanchor, delay ) {

	window.clearTimeout( st.timer );
	if( st.loaded === false ) return;
	if( st.ctl ) st.ctl.abort();
	const my = ++st.seq;
	st.ctl = new AbortController();
	st.busy = true; paintState( 'zeichnet ...' );

	try {
		const r = await post( 'preview', payload(), st.ctl.signal );
		if( my !== st.seq ) return;
		st.busy = false; paintState();

		clearMessages();

		if( r.ok === false ) {
			message( 'err', r.error );
			el.info.textContent = 'letzte gültige Vorschau bleibt stehen';
			return;
		}

		if( r.warnings && r.warnings.length ) message( 'warn', plural( r.warnings.length, 'Warnung', 'Warnungen' ) + ' zum getippten Text', r.warnings );
		if( r.hints && r.hints.length ) message( 'hint', plural( r.hints.length, 'Hinweis', 'Hinweise' ) + ' der Bibliothek', r.hints );

		st.anchor = r.anchor;
		el.info.textContent = r.ms + ' ms';
		show( r.html, reanchor === true || st.jump !== 'auto' && st.last === null ? 'anchor' : 'keep' );
	}
	catch( e ) {
		if( e && e.name === 'AbortError' ) return;
		if( my !== st.seq ) return;
		st.busy = false; paintState();
		message( 'err', e.message );
		el.info.textContent = 'letzte gültige Vorschau bleibt stehen';
	}
}

function sizeFrame( f ) {
	const w = width(), box = el.stage.getBoundingClientRect();
	const s = Math.min( 1, box.width / w );
	f.style.width = w + 'px';
	f.style.height = Math.max( 100, Math.floor( box.height / s ) ) + 'px';
	return s;
}

function layout() {
	const w = width(), box = el.stage.getBoundingClientRect();
	const s = Math.min( 1, box.width / w );
	el.frames.style.transform = 'scale(' + s + ')';
	el.frames.style.left = Math.max( 0, ( box.width - w * s ) / 2 ) + 'px';
	el.frames.style.position = 'absolute';
	el.frames.style.top = '0';
	el.frames.querySelectorAll( 'iframe' ).forEach( sizeFrame );
	el.info.title = 'Breite ' + w + ' px' + ( s < 1 ? ', verkleinert auf ' + Math.round( s * 100 ) + ' %' : '' );
}

function show( html, how ) {

	const goto = how === 'anchor' ? ( st.jump === 'auto' ? st.anchor : st.jump ) : ( st.y === null ? ( st.jump === 'auto' ? st.anchor : st.jump ) : st.anchor );
	const y = how === 'keep' && st.y !== null ? st.y : null;
	if( how === 'anchor' ) st.y = null;

	if( st.next ) { st.next.remove(); st.next = null; }

	const f = document.createElement( 'iframe' );
	f.className = 'next';
	f.title = 'Vorschau';
	// No allow-same-origin: what is typed cannot reach the session of this page; scripts run only where they carry the page's nonce
	f.setAttribute( 'sandbox', 'allow-scripts' );
	sizeFrame( f );
	st.next = f;

	const swap = () => {
		if( st.next !== f ) return;
		window.clearTimeout( guardTimer );
		el.frames.querySelectorAll( 'iframe:not(.next)' ).forEach( ( old ) => old.remove() );
		f.classList.remove( 'next' );
		st.current = f; st.next = null; st.last = Date.now();
		el.empty.hidden = true;
	};
	const guardTimer = window.setTimeout( () => { if( st.next === f ) { swap(); } }, 12000 );

	f.addEventListener( 'load', swap, { once: true } );
	f.srcdoc = injectChild( html, goto, y );
	el.frames.appendChild( f );
}

window.addEventListener( 'message', ( e ) => {
	if( st.current === null || e.source !== st.current.contentWindow || !e.data || e.data.lab !== 'scroll' || typeof e.data.y !== 'number' ) return;
	st.y = e.data.y;
} );

/* -------------------------------------------------------------------- save */

async function save() {

	if( dirty() === false || st.busy ) return;

	const send = async ( force ) => post( 'save', {
		part: st.part, variant: st.variant, file: st.file, css: el.css.value, template: st.hasTpl ? el.tpl.value : null,
		base: { css: st.hash.css, template: st.hash.template }, force,
	} );

	st.busy = true; paintState( 'speichert ...' );

	try {
		let r;
		try { r = await send( false ); }
		catch( e ) {
			if( e.body && e.body.conflict === true && window.confirm( e.message + '\n\nTrotzdem überschreiben?' ) ) r = await send( true );
			else throw e;
		}
		st.hash = r.hash;
		st.base = { css: el.css.value, template: st.hasTpl ? el.tpl.value : '' };
		st.busy = false;
		paintState( 'gespeichert ' + new Date().toLocaleTimeString( 'de-DE' ) );
	}
	catch( e ) {
		st.busy = false; paintState();
		message( 'err', 'Nicht gespeichert: ' + e.message );
	}
}

async function reset() {
	if( st.loaded === false ) return;
	if( dirty() && window.confirm( 'Änderungen verwerfen und die Datei neu laden?' ) === false ) return;
	await load( false );
}

/* ----------------------------------------------------------------- editors */

function insert( ta, text ) {
	ta.focus();
	if( document.execCommand( 'insertText', false, text ) === false ) {
		ta.setRangeText( text, ta.selectionStart, ta.selectionEnd, 'end' );
		ta.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}
}

function indent( ta, back ) {
	const v = ta.value, s = ta.selectionStart, e = ta.selectionEnd;
	if( s === e && back === false ) { insert( ta, '\t' ); return; }
	const from = v.lastIndexOf( '\n', s - 1 ) + 1;
	let to = v.indexOf( '\n', e > s && v.charAt( e - 1 ) === '\n' ? e - 1 : e );
	if( to < 0 ) to = v.length;
	const out = v.slice( from, to ).split( '\n' ).map( ( l ) => back ? l.replace( /^(\t| {1,4})/, '' ) : '\t' + l ).join( '\n' );
	ta.setSelectionRange( from, to );
	insert( ta, out );
	ta.setSelectionRange( from, from + out.length );
}

function keys( ta ) {
	ta.addEventListener( 'keydown', ( e ) => {
		if( e.key === 'Escape' ) { ta.dataset.free = '1'; return; }
		if( e.ctrlKey || e.metaKey || e.altKey ) return;
		if( e.key === 'Tab' ) {
			if( ta.dataset.free === '1' ) { delete ta.dataset.free; return; }
			e.preventDefault(); indent( ta, e.shiftKey );
		}
		else if( e.key === 'Enter' && e.shiftKey === false ) {
			const v = ta.value, s = ta.selectionStart;
			const lead = /^[\t ]*/.exec( v.slice( v.lastIndexOf( '\n', s - 1 ) + 1, s ) )[0];
			e.preventDefault(); insert( ta, '\n' + lead );
		}
	} );
	ta.addEventListener( 'blur', () => { delete ta.dataset.free; } );
}

function copy( ta, button ) {
	const done = () => { const t = button.textContent; button.textContent = 'Kopiert'; window.setTimeout( () => { button.textContent = t; }, 1200 ); };
	const old = () => { ta.focus(); ta.select(); try { if( document.execCommand( 'copy' ) ) done(); } catch( e ) { /* nothing to do */ } ta.setSelectionRange( 0, 0 ); };
	if( navigator.clipboard && window.isSecureContext ) navigator.clipboard.writeText( ta.value ).then( done, old );
	else old();
}

/* ------------------------------------------------------------------ wiring */

function setMode( mode ) {
	st.mode = mode;
	el.light.setAttribute( 'aria-pressed', String( mode === 'light' ) );
	el.dark.setAttribute( 'aria-pressed', String( mode === 'dark' ) );
	el.auto.setAttribute( 'aria-pressed', String( mode === 'auto' ) );
	remember( { mode } );
}

function init() {

	const mem = remembered();

	fillParts();
	fill( el.theme, [ [ '', '' ] ].slice( 1 ) );
	const lib = boot.themes.list.filter( ( t ) => t.shipped ), own = boot.themes.list.filter( ( t ) => t.shipped === false );
	[ [ 'Bibliothek', lib ], [ 'Projekt', own ] ].forEach( ( g ) => {
		if( g[1].length === 0 ) return;
		const og = document.createElement( 'optgroup' ); og.label = g[0];
		g[1].forEach( ( t ) => og.appendChild( option( t.slug, t.name ) ) );
		el.theme.appendChild( og );
	} );
	el.theme.value = boot.themes.list.some( ( t ) => t.slug === mem.theme ) ? mem.theme : boot.themes.active;

	fill( el.locale, boot.locales.map( ( l ) => [ l, l ] ), boot.locales.includes( mem.locale ) ? mem.locale : boot.locale );
	if( [ 'project', 'short', 'long' ].includes( mem.menu ) ) el.menu.value = mem.menu;
	if( [ '390', '768', '1280', 'free' ].includes( mem.width ) ) el.width.value = mem.width;
	else if( window.innerWidth < 900 ) el.width.value = '390';
	if( mem.free ) el.free.value = mem.free;
	el.free.hidden = el.width.value !== 'free';
	setMode( [ 'dark', 'auto' ].includes( mem.mode ) ? mem.mode : 'light' );
	fillJump();
	if( mem.left ) el.work.style.setProperty( '--left', mem.left );

	const first = boot.parts.some( ( p ) => p.key === mem.part ) ? mem.part : ( boot.parts[0] || {} ).key;
	el.part.value = first;

	el.part.addEventListener( 'change', () => select( 'part', el.part.value ) );
	el.variant.addEventListener( 'change', () => select( 'variant', el.variant.value ) );
	el.file.addEventListener( 'change', () => { st.file = el.file.value; select( 'file', el.file.value, true ); } );
	el.step.addEventListener( 'change', () => { schedule(); } );
	[ el.theme, el.locale, el.menu ].forEach( ( s ) => s.addEventListener( 'change', () => { remember( { theme: el.theme.value, locale: el.locale.value, menu: el.menu.value } ); run( false, 0 ); } ) );
	el.width.addEventListener( 'change', () => { el.free.hidden = el.width.value !== 'free'; remember( { width: el.width.value } ); layout(); } );
	el.free.addEventListener( 'input', () => { remember( { free: el.free.value } ); layout(); } );
	el.light.addEventListener( 'click', () => { setMode( 'light' ); run( false, 0 ); } );
	el.dark.addEventListener( 'click', () => { setMode( 'dark' ); run( false, 0 ); } );
	el.auto.addEventListener( 'click', () => { setMode( 'auto' ); run( false, 0 ); } );
	el.jump.addEventListener( 'change', () => {
		st.jump = el.jump.value;
		const id = st.jump === 'auto' ? st.anchor : st.jump;
		if( st.current ) st.current.contentWindow.postMessage( { lab: 'goto', id }, '*' );
	} );
	el.reload.addEventListener( 'click', () => run( false, 0 ) );
	el.save.addEventListener( 'click', save );
	el.reset.addEventListener( 'click', reset );
	$( 'c-tpl' ).addEventListener( 'click', () => copy( el.tpl, $( 'c-tpl' ) ) );
	$( 'c-css' ).addEventListener( 'click', () => copy( el.css, $( 'c-css' ) ) );

	[ [ el.tpl, el.gTpl ], [ el.css, el.gCss ] ].forEach( ( pair ) => {
		keys( pair[0] );
		pair[0].addEventListener( 'input', () => { paintMeta(); schedule(); } );
		pair[0].addEventListener( 'scroll', () => { pair[1].scrollTop = pair[0].scrollTop; } );
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if( ( e.ctrlKey || e.metaKey ) && ( e.key === 's' || e.key === 'S' ) ) { e.preventDefault(); save(); }
	} );

	window.addEventListener( 'beforeunload', ( e ) => { if( dirty() ) { e.preventDefault(); e.returnValue = ''; } } );

	// The divider between the editors and the preview: dragged, or moved with the arrow keys
	const place = ( px ) => {
		const box = el.work.getBoundingClientRect();
		const v = Math.max( 240, Math.min( box.width - 280, px ) ), value = v + 'px';
		el.work.style.setProperty( '--left', value ); remember( { left: value } ); layout();
	};
	el.split.addEventListener( 'pointerdown', ( e ) => {
		el.split.setPointerCapture( e.pointerId ); el.split.classList.add( 'drag' );
		document.body.style.userSelect = 'none'; el.stage.style.pointerEvents = 'none';
	} );
	el.split.addEventListener( 'pointermove', ( e ) => { if( el.split.hasPointerCapture( e.pointerId ) ) place( e.clientX - el.work.getBoundingClientRect().left ); } );
	const drop = ( e ) => { el.split.classList.remove( 'drag' ); document.body.style.userSelect = ''; el.stage.style.pointerEvents = ''; };
	el.split.addEventListener( 'pointerup', drop ); el.split.addEventListener( 'pointercancel', drop );
	el.split.addEventListener( 'keydown', ( e ) => {
		if( e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' ) return;
		e.preventDefault(); place( document.getElementById( 'left' ).getBoundingClientRect().width + ( e.key === 'ArrowLeft' ? -24 : 24 ) );
	} );

	if( 'ResizeObserver' in window ) new ResizeObserver( layout ).observe( el.stage );
	window.addEventListener( 'resize', layout );

	st.part = first;
	fillVariants();
	const memory = mem.variants || {};
	const want = part().variants.some( ( v ) => v.key === memory[first] ) ? memory[first] : ( part().variants[0] || {} ).key;
	el.variant.value = want || ''; st.variant = el.variant.value;
	fillFiles(); st.file = el.file.value;
	fillSteps();
	el.menuWrap.hidden = [ 'header', 'footer' ].includes( st.part ) === false;
	layout();
	load( true );
}

init();

} )();
JS;
	}

	final class Refusal extends \RuntimeException {

		public function __construct( string $message, public readonly int $status = 400, public readonly array $extra = [] ) {
			parent::__construct( $message );
		}
	}

	Lab::run();
}
