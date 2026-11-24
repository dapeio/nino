<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Backup\Seal					The file of an encrypted backup archive, written and read in chunks
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Backup {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Backup\Seal				The one place that knows how an archive is laid in its file, shared by the run that writes it
	 *										(\Nino\Modules\Backups, the safety snapshot of a restore) and by everything that opens one (the Backups
	 *										panel, recovery.php): the kernel, since a restore has to work where nothing else is loadable.
	 *
	 *										The file is the stub that ends a direct request, base64 between its two constants as one never closed php
	 *										string (see \Nino\Modules\Backups for why), and what the base64 holds is one of two formats. Format 1 is
	 *										12 bytes of iv, 16 bytes of tag and the ciphertext of the whole gzip archive: AES-256-GCM verifies a
	 *										message only at its end and php has no incremental call for it, so opening one takes the memory of the file
	 *										(see \Nino\Modules\Backups::openShort()). It is read and no longer written. Format 2 is a header of 24 bytes -
	 *										the magic "NinoBak" and the version 2, the size of a chunk as 4 bytes big-endian, a nonce of 12 random bytes
	 *										- and then the chunks, each the ciphertext of CHUNK bytes of the archive (the last one of 1 to CHUNK bytes)
	 *										followed by its 16 bytes of tag. Every chunk is its own AES-256-GCM message: its nonce is the nonce of the
	 *										header plus the number of the chunk, as one 96 bit number, and the data it authenticates besides the
	 *										ciphertext is the whole header, the number of the chunk (4 bytes big-endian) and a byte that says whether it
	 *										is the last. So a chunk that is moved, repeated or left out, an end that is cut off at a boundary of the
	 *										chunks or inside one, bytes that are added, a chunk of another file made with the same key and a changed
	 *										header each fail the tag of the chunk they touch, and nothing that was read is trusted before the whole
	 *										file was: the reader writes the plain archive where the caller says and the caller uses it once open()
	 *										answers that the end was reached and every tag held. Whether a chunk is the last is told by the size of the
	 *										file, which is what the tag then confirms. Nothing but openssl_*() is used: no construction of our own
	 *										beyond the layout above, which is the usual STREAM one (a nonce counter and a final flag in the data
	 *										that is authenticated) that a one-shot AEAD allows.
	 *
	 *										The memory a chunk costs is CHUNK bytes of the archive, its ciphertext and their base64, whatever the size
	 *										of the archive is.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Seal {

		// What a request for an archive file that is served after all ends with: it answers 403 and exits before the data. The one
		// definition, which \Nino\Admin\Recovery and \Nino\Modules\Backups name
		public const string STUB_PREFIX = "<?php http_response_code(403); exit; return '";
		public const string STUB_SUFFIX = "';\n";

		// The start of the header of format 2, the version in its last byte: what tells it from the iv of format 1, which is random
		public const string MAGIC = "NinoBak\x02";

		// How much of the archive a chunk holds, and what a header may name: a reader holds one chunk at a time, so the bound is what a
		// file can ask of its memory. 256 KiB, measured: the allocator of php gives a string of a megabyte a block of two, and a
		// run with chunks of a megabyte did not stay under a memory limit of 16M where this one stays under 8M, the code of the
		// test that runs it included
		// A reader takes at most a megabyte from the header of a file, which no tag has checked: that is what an unchecked file may ask
		// of it (about 5 MB of memory at the most), four times what the writer makes
		public const int CHUNK = 262144;
		public const int CHUNK_MIN = 1024;
		public const int CHUNK_MAX = 1048576;

		// The number of a chunk is 4 bytes in the data that is authenticated: a file is at most this many chunks, so that two chunks of
		// a file never share a number or a nonce
		public const int MAX_CHUNKS = 4294967295;

		private const int HEADER = 24;
		private const int TAG = 16;

		// A piece of base64 that is read at a time: a multiple of four characters, so that it decodes on its own
		private const int PIECE = 393216;

		// The codes open() answers with
		public const string SHAPE 		= 'shape';			// Not the stub of an archive: no stub, no base64, no chunk, or an end that is no end of a chunk
		public const string HEADER_BAD = 'header';		// The magic of format 2 and a header that is none: a chunk size no writer makes
		public const string AUTH 			= 'auth';				// A tag does not hold: the key is another one, or the file is not what was written
		public const string TOO_MANY 	= 'too-many';		// More chunks than a number can name

		/**
		 *	Which format a file is laid in
		 *
		 *	@param		string		$path					Absolute path of the file
		 *
		 *	@return 	int|null								1 or 2, null where the file is no stub of an archive
		 */
		public static function format( string $path ): ?int {

			$handle = @fopen( $path, 'rb' );

			if( $handle === false )
				return null;

			$head = (string) fread( $handle, strlen( self::STUB_PREFIX ) + 12 );

			fclose( $handle );

			if( str_starts_with( $head, self::STUB_PREFIX ) === false || strlen( $head ) < strlen( self::STUB_PREFIX ) + 12 )
				return null;

			$bytes = base64_decode( substr( $head, strlen( self::STUB_PREFIX ) ), true );

			return is_string( $bytes ) === false ? null : ( str_starts_with( $bytes, self::MAGIC ) === true ? 2 : 1 );
		}

		/**
		 *	The file of an archive in format 2 as the pieces it is written in, one after the other: the stub's prefix, the base64
		 *	of the header and the chunks, the suffix. The plain archive is read in pieces of a chunk, so the memory of the call is
		 *	a few chunks whatever its size is. A reader of the pieces that stops early leaves the file it writes to unfinished,
		 *	which no caller renames into place
		 *
		 *	@param		string		$gz						Absolute path of the compressed archive
		 *	@param		string		$key					The 32 bytes of the key
		 *	@param		int				$chunk				How much of the archive a chunk holds, CHUNK_MIN to CHUNK_MAX
		 *	@param		int				$maxChunks		How many chunks a file may have: a parameter so that the bound can be tried without four billion of them
		 *
		 *	@return 	\Generator<int, string>
		 *
		 *	@throws		\RuntimeException				If the key or the size of a chunk is none, or the archive cannot be read, or a chunk cannot be sealed, or there are more chunks than $maxChunks
		 */
		public static function pieces( string $gz, string $key, int $chunk = self::CHUNK, int $maxChunks = self::MAX_CHUNKS ): \Generator {

			if( strlen( $key ) !== 32 )
				throw new \RuntimeException( 'backup encryption key is invalid' );

			if( $chunk < self::CHUNK_MIN || $chunk > self::CHUNK_MAX )
				throw new \RuntimeException( 'backup chunk size is invalid' );

			$in = @fopen( $gz, 'rb' );

			if( $in === false )
				throw new \RuntimeException( 'compressed backup could not be read' );

			try {

				$base 		= random_bytes( 12 );
				$header 	= self::MAGIC. pack( 'N', $chunk ). $base;
				$pending 	= $header;
				$number 	= 0;
				$current	= self::_read( $in, $chunk );

				yield self::STUB_PREFIX;

				do {

					// A chunk that is not full is the last; a full one is the last where nothing follows it
					$next 	= strlen( $current ) === $chunk ? self::_read( $in, $chunk ) : '';
					$final 	= $next === '';

					if( $number >= $maxChunks )
						throw new \RuntimeException( 'the backup has more chunks than a file can number' );

					$tag 			= '';
					$cipher 	= openssl_encrypt( $current, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, self::_nonce( $base, $number ), $tag, self::_aad( $header, $number, $final ), self::TAG );

					if( $cipher === false || strlen( $tag ) !== self::TAG )
						throw new \RuntimeException( 'backup encryption failed' );

					$pending .= $cipher. $tag;

					// Base64 of whole threes, the rest waits for the next chunk
					$whole = strlen( $pending ) - strlen( $pending ) % 3;

					yield base64_encode( substr( $pending, 0, $whole ) );

					$pending 	= (string) substr( $pending, $whole );
					$current 	= $next;
					$number++;

				} while( $final === false );

				yield base64_encode( $pending ). self::STUB_SUFFIX;
			}
			finally {
				fclose( $in );
			}
		}

		/**
		 *	Open an archive file: read it chunk by chunk, check every tag and write the plain archive to $to. What lies in $to
		 *	is the archive only where this answers null - the caller uses nothing of it before - and the caller removes it
		 *	where this answers a code. A file in format 1 is read whole (see the docblock of the class)
		 *
		 *	@param		string		$path					Absolute path of the file
		 *	@param		string		$key					The 32 bytes of the key
		 *	@param		string|null	$to					Where to write the plain archive, an existing or a new file; null where only the checking is wanted
		 *	@param		string|null	&$head				(reference) The first two bytes of the archive, where it was opened
		 *	@param		int				$maxChunks		See pieces()
		 *
		 *	@return 	string|null							null where every chunk held and the file ended where it had to, else SHAPE, HEADER_BAD, AUTH or TOO_MANY
		 *
		 *	@throws		\RuntimeException				If the file cannot be read or $to cannot be written
		 */
		public static function open( string $path, string $key, ?string $to = null, ?string &$head = null, int $maxChunks = self::MAX_CHUNKS ): ?string {

			$head = null;

			if( strlen( $key ) !== 32 )
				return self::AUTH;

			$in = @fopen( $path, 'rb' );

			if( $in === false )
				throw new \RuntimeException( 'backup could not be read' );

			$out = null;

			try {

				$size 	= (int) ( fstat( $in )['size'] ?? 0 );
				$prefix = strlen( self::STUB_PREFIX );
				$inner 	= $size - $prefix - strlen( self::STUB_SUFFIX );

				// Base64 comes in fours, and the stub has its two constants around it
				if( $inner < 4 || $inner % 4 !== 0 || fread( $in, $prefix ) !== self::STUB_PREFIX || fseek( $in, -strlen( self::STUB_SUFFIX ), SEEK_END ) !== 0 || fread( $in, strlen( self::STUB_SUFFIX ) ) !== self::STUB_SUFFIX )
					return self::SHAPE;

				// How many bytes the base64 holds: the padding is the end of it and nothing else
				fseek( $in, $size - strlen( self::STUB_SUFFIX ) - 2 );
				$ending = (string) fread( $in, 2 );
				$total 	= intdiv( $inner, 4 ) * 3 - substr_count( $ending, '=' );

				fseek( $in, $prefix );

				if( $to !== null ) {

					$out = @fopen( $to, 'wb' );

					if( $out === false )
						throw new \RuntimeException( 'file could not be written', \Nino\Backup::WRITE_FAILED );
				}

				// The decoded bytes, taken as they are asked for: no more than a piece of the file and what is asked for is in memory
				$buffer = '';
				$left 	= $inner;

				$take = static function( int $count ) use ( $in, &$buffer, &$left ): ?string {

					while( strlen( $buffer ) < $count && $left > 0 ) {

						$piece = fread( $in, min( self::PIECE, $left ) );

						if( $piece === false || $piece === '' || strlen( $piece ) % 4 !== 0 )
							return null;

						$left -= strlen( $piece );

						// Padding is the end of the whole and of nothing before it; php's strict decoding skips white space, so the characters are asked for
						$plain = preg_match( $left === 0 ? '/^[A-Za-z0-9+\/]*={0,2}$/D' : '/^[A-Za-z0-9+\/]+$/D', $piece ) === 1 ? base64_decode( $piece, true ) : false;

						if( $plain === false )
							return null;

						$buffer .= $plain;
					}

					if( strlen( $buffer ) < $count )
						return null;

					$taken 	= substr( $buffer, 0, $count );
					$buffer = (string) substr( $buffer, $count );

					return $taken;
				};

				$magic = $take( strlen( self::MAGIC ) );

				if( $magic !== self::MAGIC ) {

					// Whatever does not start with the magic is read as what format 1 begins with, the random iv of an archive
					return $magic === null ? self::SHAPE : self::_openWhole( $in, $size, $key, $out, $head );
				}

				$rest = $take( self::HEADER - strlen( self::MAGIC ) );

				if( $rest === null )
					return self::SHAPE;

				$chunk 	= (int) unpack( 'N', substr( $rest, 0, 4 ) )[1];
				$base 	= substr( $rest, 4, 12 );
				$header = self::MAGIC. $rest;

				if( $chunk < self::CHUNK_MIN || $chunk > self::CHUNK_MAX )
					return self::HEADER_BAD;

				$remaining 	= $total - self::HEADER;
				$number 		= 0;

				// An archive of no bytes is not one: there is at least a chunk, which is the last
				if( $remaining < self::TAG )
					return self::SHAPE;

				while( $remaining > 0 ) {

					if( $number >= $maxChunks )
						return self::TOO_MANY;

					$final 	= $remaining <= $chunk + self::TAG;
					$length = $final === true ? $remaining : $chunk + self::TAG;

					// The tag is the end of a chunk: nothing shorter is one
					if( $length < self::TAG )
						return self::SHAPE;

					$bytes = $take( $length );

					if( $bytes === null )
						return self::SHAPE;

					$plain = openssl_decrypt( substr( $bytes, 0, $length - self::TAG ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, self::_nonce( $base, $number ), substr( $bytes, $length - self::TAG ), self::_aad( $header, $number, $final ) );

					if( $plain === false )
						return self::AUTH;

					// A last chunk of no bytes is that of an archive of none: after another it would be a second way to write the same
					if( $plain === '' && $number > 0 )
						return self::SHAPE;

					if( $number === 0 )
						$head = substr( $plain, 0, 2 );

					if( $out !== null && @fwrite( $out, $plain ) !== strlen( $plain ) )
						throw new \RuntimeException( 'file could not be written', \Nino\Backup::WRITE_FAILED );

					$remaining -= $length;
					$number++;
				}

				if( $out !== null && ( @fflush( $out ) === false || @fclose( $out ) === false ) ) {
					$out = null;
					throw new \RuntimeException( 'file could not be written', \Nino\Backup::WRITE_FAILED );
				}

				$out = null;

				return null;
			}
			finally {

				fclose( $in );

				if( is_resource( $out ) === true )
					fclose( $out );
			}
		}

		/**
		 *	An archive of format 1: iv, tag and ciphertext, read whole and decrypted whole. Written to $out where there is one
		 *
		 *	@param		resource	$in						The file, read again from the start of the base64
		 *	@param		int				$size					The size of the file
		 *	@param		string		$key					The 32 bytes of the key
		 *	@param		resource|null	$out				Where to write the plain archive
		 *	@param		string|null	&$head				(reference) The first two bytes of the archive
		 *
		 *	@return 	string|null							null where it was opened, SHAPE or AUTH
		 */
		private static function _openWhole( $in, int $size, string $key, $out, ?string &$head ): ?string {

			// Read and decoded piece by piece: the file is the base64 of the payload, and as one string next to its decoding and its
			// parts it took more memory to open an archive than it took to make it. A piece is a multiple of four characters
			$remaining 	= $size - strlen( self::STUB_PREFIX ) - strlen( self::STUB_SUFFIX );
			$header 		= '';
			$cipher 		= '';

			fseek( $in, strlen( self::STUB_PREFIX ) );

			while( $remaining > 0 ) {

				$piece = fread( $in, min( self::PIECE, $remaining ) );

				if( $piece === false || $piece === '' || preg_match( '/^[A-Za-z0-9+\/]*={0,2}$/D', $piece ) !== 1 )
					return self::SHAPE;

				$remaining -= strlen( $piece );
				$bytes 			= base64_decode( $piece, true );

				if( $bytes === false )
					return self::SHAPE;

				// iv and tag come first, 28 bytes, and are not part of the ciphertext
				if( strlen( $header ) < 28 ) {
					$take 		= 28 - strlen( $header );
					$header .= substr( $bytes, 0, $take );
					$bytes 		= substr( $bytes, $take );
				}

				$cipher .= $bytes;
			}

			if( strlen( $header ) !== 28 )
				return self::SHAPE;

			$plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $header, 0, 12 ), substr( $header, 12, 16 ) );

			unset( $cipher );

			if( $plain === false )
				return self::AUTH;

			$head = substr( $plain, 0, 2 );

			if( $out !== null && @fwrite( $out, $plain ) !== strlen( $plain ) )
				throw new \RuntimeException( 'file could not be written', \Nino\Backup::WRITE_FAILED );

			if( $out !== null && ( @fflush( $out ) === false || @fclose( $out ) === false ) )
				throw new \RuntimeException( 'file could not be written', \Nino\Backup::WRITE_FAILED );

			return null;
		}

		/**
		 *	The nonce of a chunk: the nonce of the header plus the number of the chunk, as a number of 96 bits that wraps
		 *
		 *	@param		string		$base					The 12 bytes of the header
		 *	@param		int				$number				The number of the chunk
		 *
		 *	@return 	string									12 bytes
		 */
		private static function _nonce( string $base, int $number ): string {

			$carry = $number;

			for( $at = 11; $at >= 0 && $carry > 0; $at-- ) {

				$sum 				= ord( $base[$at] ) + ( $carry & 0xFF );
				$base[$at] 	= chr( $sum & 0xFF );
				$carry 			= ( $carry >> 8 ) + ( $sum >> 8 );
			}

			return $base;
		}

		/**
		 *	What a chunk authenticates besides its ciphertext: the header, its number and whether it is the last
		 *
		 *	@param		string		$header				The 24 bytes
		 *	@param		int				$number				The number of the chunk
		 *	@param		bool			$final				Whether it is the last
		 *
		 *	@return 	string
		 */
		private static function _aad( string $header, int $number, bool $final ): string {
			return $header. pack( 'N', $number ). ( $final === true ? "\x01" : "\x00" );
		}

		/**
		 *	Up to $length bytes of the archive, fewer only where it ends
		 *
		 *	@param		resource	$in
		 *	@param		int				$length
		 *
		 *	@return 	string
		 *
		 *	@throws		\RuntimeException				If the archive cannot be read
		 */
		private static function _read( $in, int $length ): string {

			$bytes = '';

			while( strlen( $bytes ) < $length && feof( $in ) === false ) {

				$piece = fread( $in, $length - strlen( $bytes ) );

				if( $piece === false )
					throw new \RuntimeException( 'compressed backup could not be read' );

				$bytes .= $piece;
			}

			return $bytes;
		}
	}
}
