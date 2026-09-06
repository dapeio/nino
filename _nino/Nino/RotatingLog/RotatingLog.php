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

			foreach( glob( $dir. '/'. $prefix. '*'. $suffix ) ?: [] as $file ) {

				// An empty $suffix must mean "to the end of the string", not
				// substr()'s own -strlen('') = -0 = 0, ie. "take zero chars"
				$datePart = substr( basename( $file ), strlen( $prefix ), $suffix === '' ? null : -strlen( $suffix ) );

				// Always parsed as a full 'Y-m-d', padding a monthly 'Y-m' out
				// to the first of the month first - createFromFormat() would
				// otherwise default a bare 'Y-m''s missing day to *today's*,
				// drifting the cutoff boundary day to day and risking a
				// rollover into the next month entirely (eg. parsing "...-02"
				// on the 31st)
				$full = ( $dateFormat === 'Y-m' ) ? $datePart. '-01' : $datePart;
				$date = \DateTime::createFromFormat( 'Y-m-d', $full );

				// Round-tripped back through the same format rather than
				// matched against a regex first - stricter (createFromFormat()
				// alone tolerates eg. "2026-13-45" by rolling it into a real
				// date) and there is no separate regex to keep in sync with
				// $dateFormat
				if( $date === false || $date->format( 'Y-m-d' ) !== $full )
					continue;

				// @: two requests racing the same glob() over the same expired
				// file both reach here, and the loser's unlink() on an
				// already-gone file otherwise 500s an ordinary page view
				if( $date->setTime( 0, 0 ) < $cutoff )
					@unlink( $file );
			}
		}
	}
}
