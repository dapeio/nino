<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	RotatingLog			One prune() for every "delete dated files older than a cutoff" sweep
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// RotatingLog - one prune() for every "delete dated files older than a
	// cutoff" sweep (Runtime's error log, Form's submissions, Admin\Logs,
	// Admin\Backup's retention), which each used to carry their own copy
	class RotatingLog {

		// Delete files in $dir named "<prefix><date><suffix>" whose date is
		// older than $cutoff. A name whose date portion doesn't parse cleanly
		// is left alone, not deleted - not being one of this sweep's files is
		// not evidence of being stale, and "can't tell" must never mean
		// "delete it" (true of a backup directory most of all).
		public static function prune( string $dir, string $prefix, string $dateFormat, string $suffix, \DateTime $cutoff ): void {

			/*	The directory is read rather than globbed. glob() takes a
				pattern, and the pattern was built out of $dir - which is a
				path, not a pattern: a project installed below a directory
				named "site[2]", "archive?" or anything else carrying a glob
				metacharacter had those characters read as syntax, so the
				pattern described a path that does not exist, glob() answered
				nothing, and the sweep deleted nothing at all for the life of
				that installation, in silence. Matched as the literal strings
				they are here, where a bracket is a bracket.	*/
			$names = @scandir( $dir );

			if( $names === false )
				return;

			foreach( $names as $name ) {

				// The two entries that are not files at all. Everything else
				// this sweep does not own is turned away by the prefix, the
				// suffix or the date below
				if( $name === '.' || $name === '..' )
					continue;

				if( str_starts_with( $name, $prefix ) === false || str_ends_with( $name, $suffix ) === false )
					continue;

				// An empty $suffix must mean "to the end of the string", not
				// substr()'s own -strlen('') = -0 = 0, ie. "take zero chars"
				$datePart = substr( $name, strlen( $prefix ), $suffix === '' ? null : -strlen( $suffix ) );

				/*	Parsed with the caller's own format, behind a '!': that
					resets every field the format does not set to the epoch's,
					so a monthly 'Y-m' is the first of its month rather than
					*today's* day-of-month in it - which would drift the cutoff
					boundary from day to day and roll a bucket into the next
					month entirely (parsing "...-02" on the 31st).

					That anchoring used to be a 'Y-m' padded out to '-01' and
					everything parsed as a full 'Y-m-d', which quietly made the
					two formats the kernel happens to use the only two that
					worked at all: any other one a caller named - 'Ymd', a name
					carrying an hour - parsed as nothing, matched nothing and
					pruned nothing, with no warning to say the sweep was doing
					no work. The format is the contract now, whatever it is.	*/
				$date = \DateTime::createFromFormat( '!'. $dateFormat, $datePart );

				// Round-tripped back through the same format rather than
				// matched against a regex first - stricter (createFromFormat()
				// alone tolerates eg. "2026-13-45" by rolling it into a real
				// date) and there is no separate regex to keep in sync with
				// $dateFormat
				if( $date === false || $date->format( $dateFormat ) !== $datePart )
					continue;

				// @: two requests racing the same sweep over the same expired
				// file both reach here, and the loser's unlink() on an
				// already-gone file otherwise 500s an ordinary page view
				if( $date < $cutoff )
					@unlink( $dir. '/'. $name );
			}
		}
	}
}
