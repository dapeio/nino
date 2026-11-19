<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Archive							A tar.gz read and written in memory
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Archive						The files of a .tar.gz of one folder, read in memory: path => bytes, or the code of what is wrong with it
	 *
	 *										Nothing is written to a disk and no extension but zlib is asked for - no PharData, nothing the C library of
	 *										a host has of its own. The gzip stream is inflated in small steps under a limit of its own, so that a stream
	 *										that unpacks to more than may is stopped where it passes the limit and is not read to its end, and the tar is
	 *										walked block by block. An answer is the files of a good archive or one code of this class, never a sentence:
	 *										the caller words it.
	 *
	 *										write() makes the archive of such a folder: a plain ustar tar of the files given, sorted, with the time, the
	 *										owner and the rights fixed, in a gzip stream whose header says nothing but that it is one - the same files give
	 *										the same bytes - and refuses, by the codes of read(), whatever read() would refuse of it. Nothing is corrected.
	 *
	 *										A good archive holds exactly one folder, at its top, and below it plain files and plain folders, at most
	 *										DEPTH segments deep. Refused is whatever a reader of another make could read as something else than this
	 *										reader does: a link of either kind, a device or any entry that is no file and no folder (the long names of the
	 *										GNU tar among them), a pax header that names a path, a link or a size, a header whose checksum does not
	 *										hold, a name that is absolute, has a segment that is empty, a dot or two dots, a backslash, a control
	 *										character or a NUL byte with bytes behind it, a name twice - in the spelling the file system of a Mac folds
	 *										together as well -, a name that is a file and a folder, a size of a folder, bytes behind the end of the tar and
	 *										bytes behind the end of the gzip stream. The extensions of pax that name nothing are skipped, never read:
	 *										the name of an entry is the one of its header.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Archive {

		// The codes read() answers with
		public const string UNSUPPORTED	= 'unsupported';			// The extension that inflates is not there
		public const string GZIP				= 'gzip';							// No gzip stream, a damaged one or one that is cut
		public const string BOMB				= 'bomb';							// A stream that unpacks to more than the limits allow
		public const string TRAILING		= 'trailing';					// Bytes behind the end of the gzip stream, or behind the end of the tar
		public const string TRUNCATED		= 'truncated';				// The tar ends inside an entry
		public const string HEADER			= 'header';						// A header that does not hold: checksum, size, the size of a folder
		public const string TYPE				= 'type';							// An entry that is no file and no folder: a link, a device, a long name; write() answers it for content that is no string
		public const string PAX					= 'pax';							// An extended header that names a path, a link or a size, or is none
		public const string PATH				= 'path';							// A name that is no path of plain segments
		public const string DEPTH				= 'depth';						// More segments below the folder than DEPTH
		public const string FOLDER			= 'folder';						// A file outside a folder, another folder, or not the one that was asked for
		public const string DUPLICATE		= 'duplicate';				// A name twice, in the same or in a folded spelling, or one that is a file and a folder
		public const string NAME				= 'name';							// A file the pattern or the callback of the caller does not allow
		public const string COUNT				= 'count';						// More entries than the caller allows
		public const string FILE_SIZE		= 'file-size';				// A file larger than the caller allows
		public const string TOTAL_SIZE	= 'total-size';				// The files together larger than the caller allows
		public const string LONG				= 'long';							// write() only: a path that does not fit the name and prefix fields of a ustar header

		// The most segments a path below the folder may have: a file in a folder in a folder ... counts one for itself
		public const int DEPTH_MAX = 6;

		private const int BLOCK = 512;

		// The bytes of the compressed stream that are inflated at a time: the most one step can grow is about a thousandfold
		private const int STEP = 4096;

		// The most bytes the records of one pax header may have: what a header of a tool carries is a few lines
		private const int PAX_MAX = 4096;

		// A segment of a path: a plain name that begins with a letter or a digit
		private const string SEGMENT = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}$/D';

		/**
		 *	Whether the extension the archive is read with is there
		 *
		 *	@return 	bool
		 */
		public static function supported(): bool {
			return function_exists( 'inflate_init' ) === true && function_exists( 'inflate_add' ) === true
				&& function_exists( 'inflate_get_status' ) === true && function_exists( 'inflate_get_read_len' ) === true;
		}

		/**
		 *	The files of an archive
		 *
		 *	@param		string								$bytes				The archive, gzip over tar
		 *	@param		string|null						$folder				The one folder it holds, as it is named in it; null for the one it has, whatever it is called
		 *	@param		int										$entries			The most entries it may hold, folders included
		 *	@param		int										$each					The most bytes one file may have
		 *	@param		int										$total				The most bytes all files together may have
		 *	@param		string|\Closure|null	$allow				Which files it may hold: a pattern (preg) or a callback ( string $path ): bool, both asked of the path below the
		 *																							folder; null for every file whose segments are plain names
		 *
		 *	@return 	array|string											path below the folder => content, in the order of the archive - or the code of what is wrong, one of the constants of this class
		 */
		public static function read( string $bytes, ?string $folder, int $entries, int $each, int $total, string|\Closure|null $allow = null ): array|string {

			if( self::supported() === false )
				return self::UNSUPPORTED;

			// What the tar may be at most: the files, a header and its padding for each entry, a pax header for each, the end of it
			// A limit of the caller that is the largest integer overflows the sum into a float, which min() leaves behind
			$limit = (int) min( PHP_INT_MAX, $total + $entries * 2 * self::BLOCK + $entries * ( self::BLOCK + self::PAX_MAX + self::BLOCK ) + 2 * self::BLOCK );
			$inflated = self::_inflate( $bytes, $limit );

			if( is_string( $inflated[1] ) === true )
				return $inflated[1];

			$tar = (string) $inflated[0];
			$length = strlen( $tar );
			$at = 0;
			$found = [];
			$names = [];
			$parents = [];
			$topSeen = false;
			$count = 0;
			$paxes = 0;
			$sum = 0;
			$top = $folder;

			while( true ) {

				// A tar that stops after its last entry, with no block of nothing to end it, is as complete as one that has it
				if( $at >= $length )
					break;

				if( $at + self::BLOCK > $length )
					return self::TRUNCATED;

				$head = substr( $tar, $at, self::BLOCK );

				// The end of a tar is a block of nothing - and nothing but blocks of nothing follows it: what stands behind it is read by a tar that
				// goes on, and not by one that stops
				if( trim( $head, "\0" ) === '' )
					return trim( substr( $tar, $at ), "\0" ) === '' ? $found : self::TRAILING;

				$at += self::BLOCK;

				$size = self::_size( $head );

				if( $size === null )
					return self::HEADER;

				$type = $head[156];
				$padded = (int) ( ceil( $size / self::BLOCK ) * self::BLOCK );

				if( $at + $size > $length )
					return self::TRUNCATED;

				// The extension of pax: what it says of the entry behind it is never taken, and one that tries to say a path, a link or a size is refused
				if( $type === 'x' || $type === 'g' ) {

					if( ++$paxes > $entries || $size > self::PAX_MAX || self::_pax( substr( $tar, $at, $size ) ) === false )
						return self::PAX;

					$at += $padded;
					continue;
				}

				if( $type !== '0' && $type !== "\0" && $type !== '5' )
					return self::TYPE;

				// A folder has no data: the size in its header is read by one tar as the blocks to jump over and by another as nothing, and an entry
				// can be hidden in them
				if( $type === '5' && $size !== 0 )
					return self::HEADER;

				$path = self::_path( $head, $type );

				if( $path === null )
					return self::PATH;

				if( ++$count > $entries )
					return self::COUNT;

				$segments = explode( '/', $path );

				if( $top === null )
					$top = $segments[0];

				// The folder itself: the one entry that is no file and has no path below it
				if( count( $segments ) === 1 ) {

					if( $type !== '5' || $segments[0] !== $top )
						return self::FOLDER;

					if( $topSeen === true )
						return self::DUPLICATE;

					$topSeen = true;
					continue;
				}

				if( $segments[0] !== $top )
					return self::FOLDER;

				$below = array_slice( $segments, 1 );

				if( count( $below ) > self::DEPTH_MAX )
					return self::DEPTH;

				foreach( $below as $segment )
					if( preg_match( self::SEGMENT, $segment ) !== 1 )
						return self::PATH;

				$relative = implode( '/', $below );

				// A name of digits alone is an integer as the key of an array, and the caller is promised strings
				if( ctype_digit( $relative ) === true )
					return self::PATH;

				// A name is taken once, whatever the case it is written in - and it is a file or a folder: a file below a file, or a file of the
				// name of a folder, is a name twice as well
				$folded = strtolower( $relative );

				if( isset( $names[$folded] ) === true || ( $type !== '5' && isset( $parents[$folded] ) === true ) )
					return self::DUPLICATE;

				$prefix = '';

				foreach( array_slice( explode( '/', $folded ), 0, -1 ) as $segment ) {

					$prefix .= ( $prefix === '' ? '' : '/' ). $segment;

					if( ( $names[$prefix] ?? '' ) === 'file' )
						return self::DUPLICATE;

					$parents[$prefix] = true;
				}

				if( $type === '5' ) {

					$names[$folded] = 'folder';
					continue;
				}

				if( $allow !== null && ( is_string( $allow ) === true ? preg_match( $allow, $relative ) === 1 : $allow( $relative ) === true ) === false )
					return self::NAME;

				if( $size > $each )
					return self::FILE_SIZE;

				$sum += $size;

				if( $sum > $total )
					return self::TOTAL_SIZE;

				$names[$folded] = 'file';
				$found[$relative] = substr( $tar, $at, $size );
				$at += $padded;
			}

			return $found;
		}

		/**
		 *	The archive of the files of one folder: a .tar.gz that read() reads with the same folder and no refusal, and that gives these files byte for byte
		 *
		 *	The tar is of the format ustar: the folder first, then the files in the order of their paths, each folder of a path before the first file below it. A
		 *	header holds the name (or the prefix and the name, where the path is longer than 100 bytes - never a long-name record of the GNU tar or of pax), the rights
		 *	0644 for a file and 0755 for a folder, the owner 0 and no name of an owner, the time given, the type flag 0 or 5, and the checksum. The gzip stream
		 *	is made by gzencode() at the best level, and its header is set to what it says of no machine: no flag, no time, no name, no hint of the level and the
		 *	system Unix. The same arguments give the same bytes where the zlib is the same one.
		 *
		 *	The rules of read() hold here as a refusal and are never made good, for the folders it writes as for the files: a path that is not of plain segments, a
		 *	file or a folder whose path below the folder is nothing but digits, one that is too deep, a name twice - also in the spelling of another case, also a
		 *	file that is a folder of another -, one that has no place in a ustar header, and the limits of the caller. What is refused is refused by a code of
		 *	read() for the same fault, but where two rules catch it the code may be the other one (a path that is too deep and not plain is depth here, path there).
		 *	The folder at the top is held to more than read() holds it to: a plain segment as the paths below it are, which no reader needs to be more. A file whose
		 *	size the field of a header cannot hold is refused as a header. A callback of $allow that throws is not caught.
		 *
		 *	@param		string								$folder				The one folder at the top of the archive, a plain name
		 *	@param		array									$files				path below the folder => bytes; the keys are plain segments joined by a slash
		 *	@param		int										$entries			The most entries it may hold, as read() counts them: the folder, the folders below it and the files
		 *	@param		int										$each					The most bytes one file may have
		 *	@param		int										$total				The most bytes all files together may have
		 *	@param		int										$time					The time of every entry, in seconds from 1970, at most 8589934591
		 *	@param		string|\Closure|null	$allow				Which files it may hold, as read() takes it: a pattern or a callback ( string $path ): bool; null for every file
		 *
		 *	@return 	string|array{code: string, path: string}		The archive, which is the string; or the array [ 'code' => one of the constants of this class, 'path' => the path that is refused, '' where it is none ]
		 */
		public static function write( string $folder, array $files, int $entries, int $each, int $total, int $time = 0, string|\Closure|null $allow = null ): string|array {

			if( self::supported() === false )
				return self::_refused( self::UNSUPPORTED );

			if( preg_match( self::SEGMENT, $folder ) !== 1 )
				return self::_refused( self::FOLDER );

			// The folder itself is an entry that counts, so that a limit of none holds nothing, not even the empty archive
			if( $entries < 1 )
				return self::_refused( self::COUNT );

			if( $time < 0 || $time > 8589934591 )
				return self::_refused( self::HEADER );

			$paths = array_map( 'strval', array_keys( $files ) );
			sort( $paths, SORT_STRING );

			$tar = (string) self::_header( $folder. '/', 0, '5', $time );
			$count = 1;
			$sum = 0;
			$names = [];
			$parents = [];
			$folders = [];

			foreach( $paths as $path ) {

				$bytes = $files[$path];

				if( is_string( $bytes ) === false )
					return self::_refused( self::TYPE, $path );

				$segments = explode( '/', $path );

				if( count( $segments ) > self::DEPTH_MAX )
					return self::_refused( self::DEPTH, $path );

				foreach( $segments as $segment )
					if( preg_match( self::SEGMENT, $segment ) !== 1 )
						return self::_refused( self::PATH, $path );

				// A name of digits alone is an integer as the key of an array, which read() refuses
				if( ctype_digit( $path ) === true )
					return self::_refused( self::PATH, $path );

				// A name is taken once in whatever case it is written, and it is a file or a folder: the file system of a Mac holds one of them
				$folded = strtolower( $path );

				if( isset( $names[$folded] ) === true || isset( $parents[$folded] ) === true )
					return self::_refused( self::DUPLICATE, $path );

				$names[$folded] = true;
				$prefix = '';
				$spelled = '';

				foreach( array_slice( $segments, 0, -1 ) as $segment ) {

					$prefix .= ( $prefix === '' ? '' : '/' ). strtolower( $segment );
					$spelled .= ( $spelled === '' ? '' : '/' ). $segment;

					// A folder is an entry that read() holds to the rule of the digits as it does a file
					if( ctype_digit( $spelled ) === true )
						return self::_refused( self::PATH, $path );

					if( isset( $names[$prefix] ) === true )
						return self::_refused( self::DUPLICATE, $path );

					$parents[$prefix] = true;

					if( isset( $folders[$prefix] ) === true ) {

						if( $folders[$prefix] !== $spelled )
							return self::_refused( self::DUPLICATE, $path );

						continue;
					}

					$folders[$prefix] = $spelled;

					if( ++$count > $entries )
						return self::_refused( self::COUNT, $path );

					$head = self::_header( $folder. '/'. $spelled. '/', 0, '5', $time );

					if( $head === null )
						return self::_refused( self::LONG, $path );

					$tar .= $head;
				}

				if( ++$count > $entries )
					return self::_refused( self::COUNT, $path );

				if( $allow !== null && ( is_string( $allow ) === true ? preg_match( $allow, $path ) === 1 : $allow( $path ) === true ) === false )
					return self::_refused( self::NAME, $path );

				$size = strlen( $bytes );

				// The field of a header holds eleven octal digits
				if( $size > 8589934591 )
					return self::_refused( self::HEADER, $path );

				if( $size > $each )
					return self::_refused( self::FILE_SIZE, $path );

				$sum += $size;

				if( $sum > $total )
					return self::_refused( self::TOTAL_SIZE, $path );

				$head = self::_header( $folder. '/'. $path, $size, '0', $time );

				if( $head === null )
					return self::_refused( self::LONG, $path );

				$tar .= $head. $bytes. str_repeat( "\0", ( self::BLOCK - $size % self::BLOCK ) % self::BLOCK );
			}

			$packed = gzencode( $tar. str_repeat( "\0", 2 * self::BLOCK ), 9 );

			if( $packed === false )
				return self::_refused( self::GZIP );

			// The header of the stream is ten bytes: the magic, the method, the flags, the time, the hint of the level and the system. zlib says the level
			// and, by the system it was built for, which one it is; nothing of that is a fact of the files
			return substr_replace( $packed, "\0\0\0\0\0\x03", 4, 6 );
		}

		/**
		 *	The answer write() gives for what it refuses
		 *
		 *	@param		string		$code
		 *	@param		string		$path
		 *
		 *	@return 	array
		 */
		private static function _refused( string $code, string $path = '' ): array {
			return [ 'code' => $code, 'path' => $path ];
		}

		/**
		 *	One ustar header: the block of an entry, with its checksum
		 *
		 *	@param		string		$path					The path in the archive, with a slash at the end for a folder
		 *	@param		int				$size
		 *	@param		string		$type					0 or 5
		 *	@param		int				$time
		 *
		 *	@return 	string|null								null where the path has no place in the fields for the name and the prefix
		 */
		private static function _header( string $path, int $size, string $type, int $time ): ?string {

			$prefix = '';
			$name = $path;

			// The prefix is what stands before a slash, and the name what stands behind it: the split is the last one that leaves the prefix in its field
			if( strlen( $path ) > 100 ) {

				$name = '';

				for( $cut = strrpos( substr( $path, 0, 156 ), '/' ); $cut !== false; $cut = $cut === 0 ? false : strrpos( substr( $path, 0, $cut ), '/' ) )
					if( $cut > 0 && $cut < strlen( $path ) - 1 && strlen( $path ) - $cut - 1 <= 100 ) {

						$prefix = substr( $path, 0, $cut );
						$name = substr( $path, $cut + 1 );
						break;
					}

				if( $name === '' )
					return null;
			}

			$head = str_pad( $name, 100, "\0" ). sprintf( '%07o', $type === '5' ? 0755 : 0644 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%07o', 0 ). "\0"
				. sprintf( '%011o', $size ). "\0". sprintf( '%011o', $time ). "\0". '        '. $type. str_repeat( "\0", 100 ). "ustar\0". '00'
				. str_repeat( "\0", 32 + 32 ). sprintf( '%07o', 0 ). "\0". sprintf( '%07o', 0 ). "\0". str_pad( $prefix, 155, "\0" ). str_repeat( "\0", 12 );

			return substr( $head, 0, 148 ). sprintf( '%06o', array_sum( unpack( 'C*', $head ) ) ). "\0 ". substr( $head, 156 );
		}

		/**
		 *	The tar a gzip stream holds, inflated in steps and stopped where it grows past the limit
		 *
		 *	@param		string		$bytes
		 *	@param		int				$limit				The most bytes the tar may have
		 *
		 *	@return 	array												[ the tar, null ] or [ null, the code of what is wrong ]
		 */
		private static function _inflate( string $bytes, int $limit ): array {

			if( strlen( $bytes ) < 18 || str_starts_with( $bytes, "\x1f\x8b" ) === false )
				return [ null, self::GZIP ];

			// A stream that is cut or damaged is a warning of zlib's and no more
			set_error_handler( static fn(): bool => true );

			try {
				$context = inflate_init( ZLIB_ENCODING_GZIP );
				$out = '';

				if( $context === false )
					return [ null, self::GZIP ];

				for( $at = 0, $end = strlen( $bytes ); $at < $end; $at += self::STEP ) {

					$chunk = inflate_add( $context, substr( $bytes, $at, self::STEP ), ZLIB_NO_FLUSH );

					if( $chunk === false )
						return [ null, self::GZIP ];

					$out .= $chunk;

					if( strlen( $out ) > $limit )
						return [ null, self::BOMB ];
				}

				// A stream that was not ended is a stream that was cut
				if( inflate_get_status( $context ) !== ZLIB_STREAM_END )
					return [ null, self::GZIP ];

				// One that ended before the last byte has something behind it - a second stream, or anything - that no two readers of an archive
				// agree on
				return inflate_get_read_len( $context ) === strlen( $bytes ) ? [ $out, null ] : [ null, self::TRAILING ];
			}
			finally {
				restore_error_handler();
			}
		}

		/**
		 *	The size of an entry, read from its header - which has to hold: the checksum is the sum of the bytes with the checksum field read as spaces
		 *
		 *	@param		string		$head					One block
		 *
		 *	@return 	int|null
		 */
		private static function _size( string $head ): ?int {

			$field = rtrim( substr( $head, 124, 12 ), "\0 " );
			$check = rtrim( substr( $head, 148, 8 ), "\0 " );

			if( preg_match( '/^[0-7]{1,11}$/D', $field ) !== 1 || preg_match( '/^[0-7]{1,7}$/D', $check ) !== 1 )
				return null;

			$sum = array_sum( unpack( 'C*', substr( $head, 0, 148 ). '        '. substr( $head, 156 ) ) ?: [] );

			return $sum === octdec( $check ) ? (int) octdec( $field ) : null;
		}

		/**
		 *	Whether a key of a pax record is one that says nothing of a name or a size
		 *
		 *	@param		string		$key
		 *
		 *	@return 	bool
		 */
		private static function _paxKey( string $key ): bool {

			// What a tool writes of a file that says nothing of what it is called or how long it is: the times, the owner, a comment, and the
			// attributes of the file system. Anything else - a path, a link, a size, the sparse records of the GNU tar, a size of a tool of
			// Schily, a key nobody knows - may name or measure the entry another way than its header does
			return in_array( $key, [ 'mtime', 'atime', 'ctime', 'uid', 'gid', 'uname', 'gname', 'comment', 'charset', 'hdrcharset', 'SCHILY.dev', 'SCHILY.ino', 'SCHILY.nlink', 'SCHILY.fflags' ], true ) === true
				|| str_starts_with( $key, 'LIBARCHIVE.' ) === true || str_starts_with( $key, 'SCHILY.xattr.' ) === true;
		}

		/**
		 *	Whether the records of a pax header say nothing of the entry behind it that this reader would not take: only the keys of _paxKey(). A
		 *	record is its length in decimal, a blank, key=value and a line break, the length counting all of it
		 *
		 *	@param		string		$data					The data of the header
		 *
		 *	@return 	bool
		 */
		private static function _pax( string $data ): bool {

			$at = 0;
			$end = strlen( $data );

			while( $at < $end ) {

				if( preg_match( '/\G([1-9][0-9]{0,4}) /', $data, $found, 0, $at ) !== 1 )
					return false;

				$length = (int) $found[1];
				$record = substr( $data, $at, $length );

				if( strlen( $record ) !== $length || str_ends_with( $record, "\n" ) === false )
					return false;

				$key = strstr( substr( $record, strlen( $found[1] ) + 1 ), '=', true );

				if( $key === false || self::_paxKey( $key ) === false )
					return false;

				$at += $length;
			}

			return true;
		}

		/**
		 *	The path of an entry: the prefix and the name of its header, with no leading ./ and no trailing slash
		 *
		 *	@param		string		$head					One block
		 *	@param		string		$type					Its type flag
		 *
		 *	@return 	string|null								null for a name that is not a path of plain segments
		 */
		private static function _path( string $head, string $type ): ?string {

			$name = self::_field( $head, 0, 100 );

			// The prefix is a field of the format of POSIX, and of no other
			$prefix = substr( $head, 257, 6 ) === "ustar\0" ? self::_field( $head, 345, 155 ) : '';

			if( $name === null || $prefix === null )
				return null;

			$path = $prefix === '' ? $name : $prefix. '/'. $name;

			if( str_starts_with( $path, './' ) === true )
				$path = substr( $path, 2 );

			// A file whose name ends in a slash is a folder to one reader and a file to another
			if( $type !== '5' && str_ends_with( $path, '/' ) === true )
				return null;

			$path = rtrim( $path, '/' );

			if( $path === '' || preg_match( '/[\x00-\x1f\x7f\\\\]/', $path ) === 1 )
				return null;

			foreach( explode( '/', $path ) as $segment )
				if( $segment === '' || $segment === '.' || $segment === '..' )
					return null;

			return $path;
		}

		/**
		 *	A field of a header up to its first NUL byte - which is where a tar reads it to, and a reader of another make may read on:
		 *	one with bytes behind that is no name
		 *
		 *	@param		string		$head
		 *	@param		int				$from
		 *	@param		int				$length
		 *
		 *	@return 	string|null
		 */
		private static function _field( string $head, int $from, int $length ): ?string {

			$field = substr( $head, $from, $length );
			$end = strpos( $field, "\0" );

			if( $end === false )
				return $field;

			return trim( substr( $field, $end ), "\0" ) === '' ? substr( $field, 0, $end ) : null;
		}
	}
}
