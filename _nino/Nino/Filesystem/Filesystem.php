<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Filesystem				Central file I/O with an in-request cache, locking, and automatic .php/.json (de)serialization
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Filesystem - central file I/O with an in-request cache, locking, and
	// automatic .php/.json (de)serialization
	class Filesystem {


		public static function init( array &$appData ): void {

			$path = $appData['./nino/uid'];
			if( is_dir( $path ) === false ) {
				trigger_error( 'Filesystem path \''. $path. '\' does not exist.', E_USER_ERROR );
				return;
			}
			// Three levels up: this file lives in _nino/Nino/Filesystem/, the
			// project root is the directory _nino/ sits in
			if( realpath( $path ) !== realpath( dirname( __DIR__, 3 ) ) ) {
				trigger_error( 'Filesystem path \''. $path. '\' is not the project root.', E_USER_ERROR );
				return;
			}

			$appData['./nino/filesystem/path'] 	= $path;

		}

		public static function getFileContent( array &$appData, string $filename, mixed $default = false ): mixed {

			// Every call site is expected to already validate $filename against its
			// own whitelist (element type/uri, locale, image slot, ...) - this ".."
			// rejection is defense-in-depth only, so a call site that forgets to
			// validate its input can't turn into a path-traversal read.
			if( str_contains( $filename, '..' ) === true )
				return $default;

			if( self::_prepareFileCache( $appData, $filename ) === false )
				return $default;

			$path = $appData['./nino/filesystem/cache'][$filename]['path'];

			// No flock() on the read side: a LOCK_SH here would first downgrade,
			// then drop, an exclusive lock a caller may already hold on this
			// same handle mid read-modify-write (Elements does exactly that
			// between lockFile() and putFileContent()). Writes are atomic (see
			// _writeFile()), so a reader always sees either the whole old file
			// or the whole new one and needs no lock of its own.
			clearstatcache( true, $path );
			$stat = @stat( $path );

			if( $stat === false )
				return $default;

			// mtime alone has 1-second resolution; comparing size as well
			// catches most same-second rewrites. Callers that must not miss one
			// (AppData::writeContentData(), Auth's tries file) still drop their
			// cache slot explicitly before reading.
			$fingerprint = [ 'mtime' => $stat['mtime'], 'size' => $stat['size'] ];

			if( $appData['./nino/filesystem/cache'][$filename]['fstat'] !== $fingerprint ) {

				$appData['./nino/filesystem/cache'][$filename]['fstat'] 	= $fingerprint;
				$appData['./nino/filesystem/cache'][$filename]['content']	= ( substr( $filename, -4 ) === '.php' )
					? include $path
					: (string) @file_get_contents( $path );

				if( substr( $filename, -5 ) === '.json' )
					$appData['./nino/filesystem/cache'][$filename]['content'] = json_decode( $appData['./nino/filesystem/cache'][$filename]['content'], true );
			}

			return $appData['./nino/filesystem/cache'][$filename]['content'];
		}

		public static function putFileContent( array &$appData, string $filename, mixed $content, bool $nolock = false, bool $append = false ): bool {

			// See getFileContent() - same defense-in-depth ".." rejection, on the write side
			if( str_contains( $filename, '..' ) === true )
				return false;

			self::_prepareFileCache( $appData, $filename );

			self::forceDir( $appData, dirname( ltrim( $filename, '/' ) ) );

			$path = $appData['./nino/filesystem/cache'][$filename]['path'];

			// Take the lock unless the caller already holds it via lockFile()
			if( $nolock === false && self::lockFile( $appData, $filename ) === false )
				return false;

			// Preserve the caller-facing value for the cache. Serialization and
			// disk I/O must succeed before it becomes observable in this request.
			$cacheContent = $content;

			// Prepare content
			if( substr( $filename, -5 ) === '.json' ) {
				$content = json_encode( $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				if( $content === false ) {
					self::unlockFile( $appData, $filename );
					return false;
				}
			}
			if( substr( $filename, -4 ) === '.php' )
				$content = '<?php return '. var_export( $content, true ). ';';

			$success = ( $append === true )
				? self::_appendFile( $path, (string) $content )
				: self::_writeFile( $path, (string) $content );

			// Released either way, including for $nolock === true: the write is
			// the end of the caller's read-modify-write sequence (lockFile() ->
			// read -> putFileContent()), which is exactly where the previous
			// implementation dropped its flock too. Holding on past it would
			// block the next sequence in the same process - two appData copies
			// in one request, or simply the next write of the same file.
			self::unlockFile( $appData, $filename );

			// A failed atomic replace leaves the old file in place; a failed
			// append may have written a short prefix. In either case, discard the
			// slot so the next read reflects disk rather than the attempted value.
			if( $success === false ) {
				unset( $appData['./nino/filesystem/cache'][$filename] );
				return false;
			}

			// Update cache only after persistence. For an append the complete new
			// value is unknown here, so force the next read back to disk.
			if( $append === true )
				$appData['./nino/filesystem/cache'][$filename]['fstat'] = [];
			else
				$appData['./nino/filesystem/cache'][$filename]['content'] = $cacheContent;

			// Reset opcache
			if( function_exists( 'opcache_invalidate' ) === true && is_file( $path ) === true )
				opcache_invalidate( $path, true );

			// Refresh the fingerprint getFileContent() compares against, so the
			// write this call just made doesn't look like someone else's change
			clearstatcache( true, $path );
			$stat = @stat( $path );

			if( $append === false && $stat !== false )
				$appData['./nino/filesystem/cache'][$filename]['fstat'] = [ 'mtime' => $stat['mtime'], 'size' => $stat['size'] ];

			return true;
		}

		// Replace a file's content atomically: write a temp file next to it,
		// then rename() over the target. A plain ftruncate()+fwrite() leaves
		// the file empty for the duration of the write and, if the process
		// dies in between (php timeout, oom kill, deploy restart), leaves it
		// empty for good - for config.php that is every route, every module
		// binding and every password hash gone. rename() within the same
		// directory is atomic, so a concurrent reader sees either the whole
		// old file or the whole new one, never a half-written one.
		private static function _writeFile( string $path, string $content ): bool {

			$temp		= $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			// @ on every primary I/O call below, not just the cleanup ones -
			// a disk-full/quota/permission failure here otherwise raises a
			// plain E_WARNING, which Runtime::handleError() (deliberately)
			// still treats as fatal. Without @, that warning - not the
			// === false check three lines down - is what ends the request,
			// and every caller's carefully worded "could not be written"/
			// "could not be locked" message not currently reachable
			$handle	= @fopen( $temp, 'wb' );

			if( $handle === false )
				return false;

			$written = @fwrite( $handle, $content );

			// fflush() before closing: a short write (full disk, quota) has to
			// fail here, while the temp file is still the only thing affected
			if( $written === false || $written !== strlen( $content ) || @fflush( $handle ) === false ) {
				fclose( $handle );
				@unlink( $temp );
				return false;
			}

			fclose( $handle );

			// Keep the existing file's mode - the temp file was created fresh
			// under the current umask, so without this a rewrite could quietly
			// widen (or narrow) permissions on eg. config.php
			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				return false;
			}

			return true;
		}

		// Append to a file. Kept separate from _writeFile(): appending is by
		// definition an in-place operation, there is nothing to swap in.
		private static function _appendFile( string $path, string $content ): bool {

			// See _writeFile()'s identical @ - same reasoning, same handler
			$handle = @fopen( $path, 'a' );

			if( $handle === false )
				return false;

			// No ftruncate() here - it used to run unconditionally, so an
			// "append" emptied the file and wrote the chunk on its own
			$written = @fwrite( $handle, $content );
			$flushed = @fflush( $handle );

			fclose( $handle );

			return $written !== false && $written === strlen( $content ) && $flushed === true;
		}

		// Lock a file for a read-modify-write sequence. The lock lives on a
		// side-car file in /data/.locks rather than on the file itself: the
		// data file is replaced by rename() (see _writeFile()), and a lock
		// held on the replaced inode stops serializing anything the moment
		// that happens. The lock file is only ever created, never replaced.
		public static function lockFile( array &$appData, string $filename ): bool {

			self::_prepareFileCache( $appData, $filename );

			// Already holding it (eg. lockFile() followed by a putFileContent()
			// that takes its own lock) - flock() is per handle, so re-locking
			// the same handle is a no-op rather than a deadlock, but there is
			// no point in opening a second one
			if( is_resource( $appData['./nino/filesystem/locks'][$filename] ?? null ) === true )
				return true;

			self::forceDir( $appData, '/data/.locks' );

			$lockPath	= self::path( $appData, '/data' ). '/.locks/'. sha1( $filename ). '.lock';
			// See _writeFile()'s @fopen() - same reasoning: without it, a
			// permission/quota failure here 500s before "could not be
			// locked for writing" (mutate(), writeContentData(), Elements)
			// ever gets a chance to run
			$handle		= @fopen( $lockPath, 'c' );

			if( $handle === false )
				return false;

			if( flock( $handle, LOCK_EX ) === false ) {
				fclose( $handle );
				return false;
			}

			// Deliberately NOT in the file's cache slot: several call sites drop
			// a slot to force a re-read (Auth's tries file, writeContentData),
			// and _admin drops the whole cache array at once - any of which would
			// take the only reference to this resource with it, closing the
			// handle and releasing the lock while the caller still believes it
			// holds one. Locks live in their own map for that reason.
			$appData['./nino/filesystem/locks'][$filename] = $handle;

			return true;
		}

		public static function unlockFile( array &$appData, string $filename ): bool {

			$handle = $appData['./nino/filesystem/locks'][$filename] ?? null;

			if( is_resource( $handle ) === false )
				return false;

			flock( $handle, LOCK_UN );
			fclose( $handle );

			unset( $appData['./nino/filesystem/locks'][$filename] );

			return true;
		}

		// Lock -> invalidate -> read -> write, in that order, around a
		// callback that computes the new state - the shape every
		// read-modify-write cycle on a file needs, written once. Reading
		// before locking leaves a window for exactly the concurrent write
		// the lock exists to exclude. $fn is fn(mixed $state, array
		// &$appData): mixed, returning either the new state to write or
		// null to abort - which still releases the lock, so a caller with
		// an early exit doesn't need its own unlockFile() call.
		public static function mutate( array &$appData, string $path, callable $fn, mixed $default = [] ): bool {

			if( self::lockFile( $appData, $path ) === false )
				return false;

			$appData['./nino/filesystem/cache'][$path]['fstat'] = [];

			$state 	= self::getFileContent( $appData, $path, $default );
			$new 		= $fn( $state, $appData );

			if( $new === null ) {
				self::unlockFile( $appData, $path );
				return false;
			}

			return self::putFileContent( $appData, $path, $new, true );
		}

		public static function fileExists( array &$appData, string $filename ): bool {

			// Force file array cache
			if( self::_prepareFileCache( $appData, $filename ) === false )
				return false;

			// Check file
			return is_file( $appData['./nino/filesystem/cache'][$filename]['path'] );
		}


		// The virtual path prefix everything under the private directory is
		// addressed by. Callers keep using '/private/...' whatever
		// NINO_PRIVATE_DIR points at, so a moved private directory changes
		// no call site - the same indirection '/config.php' already has
		public const string CONTENT_DIR = '/private';
		// Everything a project keeps that a webserver must never serve: its
		// configuration, the templates it renders, the text and elements it
		// renders them from, the data its visitors produce, and the stylesheet
		// and script sources the asset bundle is built out of. Addressed by
		// these virtual paths throughout, resolved against the private root -
		// see path(), and getPath()'s own docblock for the other half.
		//
		// /assets is here rather than opposite because nothing ever requests
		// one of those files: Modules\Assets reads them off disk, concatenates
		// them into /.cache/<bundle> and links only that. A source served
		// alongside its own bundle is the same css twice on one host, one copy
		// of it unversioned, and a preview of the project's private authoring
		// state on top - see url(), which no caller ever hands an /assets path.
		public const array PRIVATE_DIRS = [ '/config.php', '/templates', '/text', '/elements', '/data', '/assets' ];

		// The mirror of it: everything a browser loads directly - the pictures,
		// the webfonts a stylesheet points at, the favicon set, and the bundles
		// built out of the sources above. Kept together under the public root
		// so the project root is code and nothing else - the tool folders stay
		// put, since they serve their own js/css from where they are
		public const array PUBLIC_DIRS = [ '/images', '/favicon', '/fonts', '/.cache' ];

		// The installed features: resolved against \Nino\Features::dir(), which
		// is features/ below the project root or wherever NINO_FEATURES_DIR
		// points - never served, but read for a feature's own files
		public const string FEATURES_DIR = '/features';

		// Map a virtual, project-relative path onto its real location on
		// disk:
		//
		//	- config.php may live outside the webroot on its own (NINO_CONFIG_DIR)
		//	- everything under CONTENT_DIR is this project's own state and
		//	  moves with NINO_PRIVATE_DIR
		//	- everything under PRIVATE_DIRS resolves against the private root
		//	- everything else - /images, /fonts, the asset cache - is public
		//	  and stays where the webserver can reach it
		//
		private static function _resolvePath( array &$appData, string $filename ): string {

			$filename = '/'. ltrim( $filename, '/' );

			if( $filename === '/config.php' && ( $appData['./nino/filesystem/configpath'] ?? '' ) !== '' )
				return $appData['./nino/filesystem/configpath']. '/config.php';

			if(
				( $appData['./nino/filesystem/contentpath'] ?? '' ) !== ''
				&& ( $filename === self::CONTENT_DIR || str_starts_with( $filename, self::CONTENT_DIR. '/' ) === true )
			)
				return rtrim( $appData['./nino/filesystem/contentpath']. substr( $filename, strlen( self::CONTENT_DIR ) ), '/' );

			if( self::_isIn( self::PRIVATE_DIRS, $filename ) === true && ( $appData['./nino/filesystem/privatepath'] ?? '' ) !== '' )
				return rtrim( $appData['./nino/filesystem/privatepath']. $filename, '/' );

			// The installed features, wherever NINO_FEATURES_DIR put them - so
			// a feature names its own files as '/features/<Name>/...', for an
			// asset it adds to a bundle, and they are found after a relocation
			if( $filename === self::FEATURES_DIR || str_starts_with( $filename, self::FEATURES_DIR. '/' ) === true )
				return rtrim( \Nino\Features::dir(). substr( $filename, strlen( self::FEATURES_DIR ) ), '/' );

			if( self::_isIn( self::PUBLIC_DIRS, $filename ) === true && ( $appData['./nino/filesystem/publicpath'] ?? '' ) !== '' )
				return rtrim( $appData['./nino/filesystem/publicpath']. $filename, '/' );

			return rtrim( $appData['./nino/filesystem/path']. $filename, '/' );
		}

		// Whether a virtual path belongs to one of these directories - the
		// whole entry, never a prefix match on a partial segment: '/textures'
		// must not read as '/text', '/imagesets' not as '/images'
		private static function _isIn( array $dirs, string $filename ): bool {

			foreach( $dirs as $dir )
				if( $filename === $dir || str_starts_with( $filename, $dir. '/' ) === true )
					return true;

			return false;
		}

		/**
		 *	Resolve a virtual, project-relative path to the absolute one it
		 *	lives at - for the handful of callers that need a real path rather
		 *	than a getFileContent()/mutate() call, typically to glob() a
		 *	directory. Everything else should keep passing virtual paths and
		 *	let the filesystem functions resolve them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Eg. '/elements', '/templates/page-home.tpl'
		 *
		 *	@return 	string									Absolute path, existing or not
		 */
		public static function path( array &$appData, string $filename ): string {
			return self::_resolvePath( $appData, $filename );
		}

		public static function forceDir( array &$appData, string $dirpath ): void {

			$dirpath = self::_resolvePath( $appData, $dirpath );

			// 0755, not 0777: with the umask cleared (not unusual on shared
			// hosting, and the default in some cli/cron contexts) 0777 really
			// does mean world-writable - for /images, /data and the asset cache,
			// ie. directories the webserver serves from
			//
			// @ on mkdir(), whose return value this never checked anyway
			// (best-effort - a real failure surfaces properly at the actual
			// file write that follows): without it, two concurrent requests
			// racing to create the same missing directory raise "File
			// exists" as a plain E_WARNING on whichever one loses the race,
			// which - unguarded - 500s a request that has nothing wrong
			// with it; the directory exists either way once either finishes
			if( is_dir( $dirpath ) === false )
				@mkdir( $dirpath, 0755, true );
		}

		// The project root containing the entry points, kernel and tool code.
		// Project-owned public and private files live in their own roots; use
		// path() instead of concatenating either kind onto this directory.
		public static function getPath( array &$appData ): string {

			return $appData['./nino/filesystem/path'];

		}

		// The project's *private* root - config.php, templates, text,
		// elements, data and the asset sources (see PRIVATE_DIRS)
		public static function getPrivatePath( array &$appData ): string {

			return $appData['./nino/filesystem/privatepath'];

		}

		// The project's *public content* root - images, fonts, the favicon
		// set and the generated bundles (see PUBLIC_DIRS). Inside the
		// webroot, one level down from it, so the project root keeps only
		// the code that runs the site
		public static function getPublicPath( array &$appData ): string {

			return $appData['./nino/filesystem/publicpath'];

		}

		/**
		 *	The url one virtual path is reached under - the mirror of path().
		 *	A public-content path (PUBLIC_DIRS) gets the public prefix, a tool
		 *	folder's own file gets the plain project dir: /_admin bundles its
		 *	login css into /_admin/.cache/, which is code shipped with the
		 *	tool, not this project's public content, and must keep resolving
		 *	next to the tool itself
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			Eg. '/.cache/style.css', '/_admin/.cache/login.js'
		 *
		 *	@return 	string
		 */
		public static function url( array &$appData, string $filename ): string {

			$filename = '/'. ltrim( $filename, '/' );

			$prefix = self::_isIn( self::PUBLIC_DIRS, $filename ) === true
				? self::getPublicDir( $appData )
				: self::getDir( $appData );

			return rtrim( $prefix, '/' ). $filename;
		}

		/**
		 *	The url prefix those same files are reached under - getDir() plus
		 *	the public directory's own segment. Rendered as the
		 *	[[/nino/public]] fill, which is what every template, stylesheet
		 *	and admin preview builds an image or asset url from.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									Eg. '/public', '/subdir/public'
		 */
		public static function getPublicDir( array &$appData ): string {

			return rtrim( self::getDir( $appData ), '/' ). '/public';

		}

		// Return the path config.php actually lives under - normally the
		// same as getPath(), but distinct when NINO_CONFIG_DIR moves it
		// outside the webroot (see \Nino\init()). Callers that build a list
		// of on-disk files by hand (Backup, Restore) must resolve config.php
		// through this, not getPath(), or they miss/misplace it entirely
		// under a hardened, out-of-webroot setup.
		public static function getConfigPath( array &$appData ): string {

			return $appData['./nino/filesystem/configpath'];

		}

		// Return the path this project's own, non-code state lives under -
		// normally <project>/private, moved by NINO_PRIVATE_DIR (see
		// \Nino\init()). The generalisation of getConfigPath(): where that
		// one moves a single file, this moves everything that makes one
		// installation differ from another, so the tool folders stay pure
		// code an update may replace outright.
		//
		public static function getContentPath( array &$appData ): string {

			return $appData['./nino/filesystem/contentpath'];

		}

		public static function getDir( array &$appData ): string {

			return $appData['/nino/dir'];

		}

		public static function copyDir( string $source, string $dest ): bool {

			// @ throughout - see forceDir()'s identical reasoning: none of
			// these return values were ever checked (this is used for a
			// single request's own restore/backup copy, not concurrent
			// writers, but a permission/quota failure mid-copy is exactly
			// as real), and an unguarded warning here 500s a restore before
			// it can report anything more useful than that

			// 0755, see forceDir() - this one copies backup/restore trees, so a
			// world-writable mode here would land on the backup directory too
			if( ! file_exists( dirname( $dest ) ) )
				@mkdir( dirname( $dest ), 0755, true );

			if( is_file( $source ) )
				return @copy( $source, $dest );

			if( is_dir( $dest ) === false && @mkdir( $dest, 0755, true ) === false && is_dir( $dest ) === false )
				return false;

			$ok = true;
			foreach( @scandir( $source ) ?: [] as $object ) {

				if( $object === '.' || $object === '..' )
					continue;

				if( self::copyDir( $source. '/'. $object, $dest. '/'. $object ) === false )
					$ok = false;
			}

			return $ok;
		}


		public static function removeDir( string $target ): void {

			if( is_file( $target ) ) {
				@unlink( $target );
				return;
			}

			if( ! is_dir( $target ) || is_link( $target ) )
				return;

			// @ throughout: two concurrent cleanups (or a request racing this
			// same recursion) can both reach a since-removed entry - is_dir()
			// above and each unlink()/rmdir() below all have their own TOCTOU
			// gap, and scandir() on the way in warns just as unguarded on an
			// unreadable directory, taking the foreach below with it
			foreach( @scandir( $target ) ?: [] as $object ) {
				if( $object === '.' || $object === '..' )
					continue;

				self::removeDir( $target. '/'. $object );
			}

			@rmdir( $target );
		}


		private static function _prepareFileCache( array &$appData, string $filename ): bool {

			// 'fstat' is the mtime/size fingerprint getFileContent() decides
			// staleness by; lock handles deliberately live outside this slot,
			// see lockFile()
			// Keyed on 'path', not on the slot as a whole: a caller that
			// invalidated the fingerprint (['fstat'] = []) leaves a slot behind
			// that exists but isn't set up yet.
			// The cache key stays the virtual path callers pass in, while
			// 'path' is where it actually lives - see _resolvePath(), which is
			// what lets config.php and everything under /private sit outside
			// the project root without any call site knowing
			if( isset( $appData['./nino/filesystem/cache'][$filename]['path'] ) === false )
				$appData['./nino/filesystem/cache'][$filename] = [
					'path'				=> self::_resolvePath( $appData, $filename ),
					'content'			=> '',
					'fstat'				=> [],
				];

			return is_file( $appData['./nino/filesystem/cache'][$filename]['path'] );
		}

	}
}
