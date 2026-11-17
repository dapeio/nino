<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Failure				The error that ends a request, thrown by Runtime::fail()
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Failure - what \Nino\Runtime::fail() throws. An \Error and not an
	// \Exception, so that the catch( \Exception ) of a caller who expects a
	// recoverable failure does not take it; it ends the request through
	// Runtime::handleException() (log, 500). Final: nobody refines it, a
	// catch of it is a catch of every failure that ends a request. Its file
	// and line are those of the call of fail(), where the failure was decided
	final class Failure extends \Error {

		/**
		 *	@param		string		$message			What went wrong
		 *	@param		string		$file					The file that called Runtime::fail()
		 *	@param		int				$line					The line of that call
		 */
		public function __construct( string $message, string $file, int $line ) {

			parent::__construct( $message );

			$this->file = $file;
			$this->line = $line;
		}
	}
}
