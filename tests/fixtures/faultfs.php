<?php
declare(strict_types=1);

/**
 *	Nino
 *	faultfs.php		Test helper of the suites of the outbox of \Nino\Mail (mail-queue-smoke.php, mail-void-smoke.php) and of the
 *								restore (backup-hook-smoke.php): the file protocol of php with a record of every operation and with the
 *								failures a test names
 */

/**
 *	The file protocol of php for a moment, with a record of what is done and with the failures a test names: a read of a file that is
 *	there fails, a chmod, an unlink, the replace of a file (rename). Everything else is what php does itself
 */
final class FaultFs {

	public $context;

	// What was done, in order: [ operation, path, detail ]
	public static array $log = [];

	// operation => function( string $path ): bool, true to fail it. Besides the operations of php:
	//   'cut'    => function( string $path ): bool, true: a write of the file puts half of what it is given and answers that it put all of it
	//   'closed' => function( string $path, string $mode ): void, called when a file that was opened is closed - the moment a process can write to it
	public static array $fail = [];

	private $handle = null;
	private $dir = null;
	private string $path = '';
	private string $mode = '';

	public static function on( array $fail = [] ): void {
		self::$log = [];
		self::$fail = $fail;
		stream_wrapper_unregister( 'file' );
		stream_wrapper_register( 'file', self::class );
	}

	public static function off(): void {
		stream_wrapper_restore( 'file' );
	}

	private static function real( callable $call ): mixed {
		stream_wrapper_restore( 'file' );

		try {
			return $call();
		}
		finally {
			stream_wrapper_unregister( 'file' );
			stream_wrapper_register( 'file', self::class );
		}
	}

	private static function fails( string $operation, string $path ): bool {
		return isset( self::$fail[$operation] ) === true && ( self::$fail[$operation] )( $path ) === true;
	}

	public function stream_open( string $path, string $mode, int $options, ?string &$opened ): bool {
		self::$log[] = [ 'open', $path, $mode, str_starts_with( $mode, 'x' ) === true ? self::real( static fn(): int => (int) ( @fileperms( dirname( $path ) ) & 0777 ) ) : -1 ];

		if( str_starts_with( $mode, 'r' ) === true && self::fails( 'read', $path ) === true )
			return false;

		$this->path = $path;
		$this->mode = $mode;
		$this->handle = self::real( static fn(): mixed => @fopen( $path, $mode ) );

		return $this->handle !== false;
	}

	public function stream_read( int $count ): string|false { return fread( $this->handle, $count ); }
	public function stream_write( string $data ): int {
		self::$log[] = [ 'write', $this->path, (string) strlen( $data ) ];

		if( self::fails( 'cut', $this->path ) === true ) {
			fwrite( $this->handle, substr( $data, 0, intdiv( strlen( $data ), 2 ) ) );

			return strlen( $data );
		}

		return (int) fwrite( $this->handle, $data );
	}
	public function stream_eof(): bool { return feof( $this->handle ); }
	public function stream_tell(): int { return (int) ftell( $this->handle ); }
	public function stream_seek( int $offset, int $whence ): bool { return fseek( $this->handle, $offset, $whence ) === 0; }
	public function stream_flush(): bool { return fflush( $this->handle ); }
	public function stream_close(): void {
		fclose( $this->handle );

		if( isset( self::$fail['closed'] ) === true )
			( self::$fail['closed'] )( $this->path, $this->mode );
	}
	public function stream_stat(): array|false { return fstat( $this->handle ); }
	public function stream_lock( int $operation ): bool { return flock( $this->handle, $operation ); }
	public function stream_truncate( int $size ): bool { return ftruncate( $this->handle, $size ); }
	public function stream_set_option( int $option, int $arg1, ?int $arg2 ): bool { return false; }

	public function url_stat( string $path, int $flags ): array|false {
		$stat = self::real( static fn(): mixed => ( $flags & STREAM_URL_STAT_LINK ) !== 0 ? @lstat( $path ) : @stat( $path ) );

		// 'uid': function( string $path ): ?int - the owner a path is said to have
		if( is_array( $stat ) === true && isset( self::$fail['uid'] ) === true && ( $uid = ( self::$fail['uid'] )( $path ) ) !== null ) {
			$stat['uid'] = $uid;
			$stat[4] = $uid;
		}

		return $stat;
	}

	public function dir_opendir( string $path, int $options ): bool {
		$this->dir = self::real( static fn(): mixed => @opendir( $path ) );

		return $this->dir !== false;
	}

	public function dir_readdir(): string|false { return readdir( $this->dir ); }
	public function dir_rewinddir(): bool { rewinddir( $this->dir ); return true; }
	public function dir_closedir(): bool { closedir( $this->dir ); return true; }

	public function unlink( string $path ): bool {
		self::$log[] = [ 'unlink', $path, '' ];

		return self::fails( 'unlink', $path ) === true ? false : self::real( static fn(): mixed => @unlink( $path ) );
	}

	public function rename( string $from, string $to ): bool {
		self::$log[] = [ 'rename', $from, $to ];

		// 'rename' => function( string $to ): bool, true to refuse the replace of a file: the file is then as it was
		if( self::fails( 'rename', $to ) === true )
			return false;

		return self::real( static fn(): mixed => @rename( $from, $to ) );
	}

	public function mkdir( string $path, int $mode, int $options ): bool {
		return self::real( static fn(): mixed => @mkdir( $path, $mode, ( $options & STREAM_MKDIR_RECURSIVE ) !== 0 ) );
	}

	public function rmdir( string $path, int $options ): bool {
		return self::real( static fn(): mixed => @rmdir( $path ) );
	}

	public function stream_metadata( string $path, int $option, mixed $value ): bool {
		if( $option === STREAM_META_ACCESS ) {
			self::$log[] = [ 'chmod', $path, decoct( (int) $value ) ];

			return self::fails( 'chmod', $path ) === true ? false : self::real( static fn(): mixed => @chmod( $path, (int) $value ) );
		}

		if( $option === STREAM_META_TOUCH )
			return self::real( static fn(): mixed => @touch( $path, ...( is_array( $value ) === true ? $value : [] ) ) );

		return false;
	}
}
