<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	deliver.php					The outbox of \Nino\Mail from the command line: what a cron job calls so that a mail does not
 *											wait for a visitor
 *
 *	Usage: php _nino/Nino/MailQueue/deliver.php
 *

 *	Delivers what is due in the outbox, up to LIMIT mails, and prints one line: the mails delivered, the mails to nobody
 *	(\Nino\Mail::VOID) that were let go - a count for the operator, which tells a visitor nothing - the ones that wait for
 *	another attempt, the ones that wait for the cap of the hour, the ones given up on and the ones that still wait - no address.
 *	It ends with 1 and a sentence on the error stream where the project cannot be booted, where it does not run as the owner of private/data (it
 *	writes and sends nothing then), or where the outbox cannot be written. It is the same call every request
 *	makes after its response (see \Nino\Mail::deliverQueued()), and a run that meets another deliverer at work does nothing.
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */

// Reached from a browser it is nothing: the file is code of the kernel, and the webserver is told not to serve it
if( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Each entry point boots the kernel on its own, and a constant that index.php defines is defined here from the environment of the
// command - the same names, set before the command is run, with no file of the kernel to change:
//
//   NINO_PRIVATE_DIR=/absolute/path php _nino/Nino/MailQueue/deliver.php
//
// Where index.php names a directory of its own, the command is given the same one, or it looks for the project where it is not
foreach( [ 'NINO_PRIVATE_DIR', 'NINO_CONFIG_DIR', 'NINO_APP_DIR', 'NINO_FEATURES_DIR' ] as $name ) {

	$value = getenv( $name );

	if( is_string( $value ) === true && $value !== '' )
		define( $name, $value );
}

// The most mails one run delivers
const LIMIT = 100;

require dirname( __DIR__, 2 ). '/Nino.php';

try {
	$appData	= \Nino\init();
	$foreign	= \Nino\MailQueue::foreignOwner( $appData );

	// The files of the outbox are written with a rename and are 0600: a run as another user than the owner of private/data would leave
	// files that the webserver cannot read. It writes nothing and sends nothing
	if( $foreign !== null ) {

		fwrite( STDERR, 'mail queue: this command runs as user '. $foreign['user']. ' and private/data belongs to user '. $foreign['owner']. ' - nothing was delivered; run it as the user of the webserver'. "\n" );
		exit( 1 );
	}

	$result		= \Nino\Mail::deliverQueued( $appData, LIMIT );
	$counts		= \Nino\Mail::queued( $appData );
}
catch( \Throwable $error ) {

	fwrite( STDERR, 'mail queue: the command failed - '. get_class( $error ). ': '. $error->getMessage(). "\n" );
	exit( 1 );
}

echo 'delivered '. $result['delivered']. ', void '. $result['void']. ', retry '. $result['retry']. ', held '. $result['held']. ', failed '. $result['failed']. ', waiting '. $counts['waiting']. "\n";

// An outbox that cannot be written delivers nothing, and a cron job that ends with 0 would say it was well
if( $result['unwritable'] === true ) {

	fwrite( STDERR, "mail queue: the outbox cannot be written, nothing was delivered - check the disk and the owner of private/data\n" );
	exit( 1 );
}
