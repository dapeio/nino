<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Applications				Setting up an application of the catalogue: what the setup wizard and the Features panel share
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Applications			An application is a named set of features the catalogue's
	 *										index lists (see \Nino\Catalogue::applications()). It is
	 *										no state of its own: it is set up when every feature of it
	 *										is on, and partly set up when some are. Two places set one
	 *										up - the setup wizard's Applications step, for the official
	 *										catalogue, and the Features panel, for the catalogue the
	 *										project reads - and both do it here: the order of the
	 *										features, what loading needs, when a feature counts as on,
	 *										and the one step that places a feature below features/ and
	 *										switches it on. This class lives in the kernel because the
	 *										wizard is deletable once the project is set up and the panel
	 *										is not.
	 *
	 *										What stays with the caller is who may ask and which
	 *										catalogue is believed: the wizard reads the official one and
	 *										nothing a request names, the panel the one config.php
	 *										configures, with the key of that one. Both believe a
	 *										catalogue only after its signature holds (see
	 *										\Nino\Catalogue::fetch()).
	 *
	 *										Nothing here touches a route, a page or a file of the
	 *										project outside what \Nino\Features::activate() adds: a
	 *										feature's own install unit is the only thing that writes.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Applications {

		/**
		 *	The language the setup wizard speaks, and so the one it shows the names and
		 *	descriptions of applications and of their features in, whatever the project's
		 *	language is: the catalogue requires it of every text, and a text that lacks
		 *	it falls back as \Nino\Features::localized() does everywhere
		 */
		public const string LOCALE = 'en_US';

		/**
		 *	What loading an application needs and this server lacks
		 *
		 *	@return 	array										key => phrase, [] when nothing is missing
		 */
		public static function missing(): array {

			$missing = [];

			if( function_exists( 'openssl_verify' ) === false )
				$missing['openssl'] = 'the openssl extension, which checks the signature of the catalogue';

			if( class_exists( 'PharData' ) === false || extension_loaded( 'zlib' ) === false )
				$missing['unpack'] = 'the Phar and zlib extensions, which unpack what is downloaded';

			if( function_exists( 'curl_init' ) === false && filter_var( ini_get( 'allow_url_fopen' ), FILTER_VALIDATE_BOOLEAN ) === false )
				$missing['http'] = 'the curl extension or allow_url_fopen, which download the catalogue';

			if( \Nino\Catalogue::writable() === false )
				$missing['features'] = 'a features/ directory the web server can write to';

			return $missing;
		}

		/**
		 *	@param		array|null	$feature			A feature as Features::get() answers it
		 *
		 *	@return 	bool											Whether it is on at the version its directory carries, with nothing against it
		 */
		public static function isOn( ?array $feature ): bool {

			return $feature !== null && $feature['active'] === true && $feature['problems'] === [] && $feature['installed'] === $feature['version'];
		}

		/**
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$application	One application of the catalogue
		 *
		 *	@return 	bool										Whether every feature of it is on
		 */
		public static function isApplied( array &$appData, array $application ): bool {

			return self::notOn( $appData, $application ) === [];
		}

		/**
		 *	The features of an application that are not on, in the order it lists them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$application	One application of the catalogue
		 *
		 *	@return 	array										Feature keys
		 */
		public static function notOn( array &$appData, array $application ): array {

			$off = [];

			foreach( $application['features'] as $feature )
				if( self::isOn( \Nino\Features::get( $appData, $feature ) ) === false )
					$off[] = $feature;

			return $off;
		}

		/**
		 *	The features the given applications switch on, in the order they are applied:
		 *	the applications in the order given, the features of each in the order it lists
		 *	them, each feature once
		 *
		 *	@param		array 		$applications	Applications as the catalogue lists them
		 *
		 *	@return 	array										Feature keys
		 */
		public static function features( array $applications ): array {

			$features = [];

			foreach( $applications as $application )
				foreach( $application['features'] as $feature )
					if( in_array( $feature, $features, true ) === false )
						$features[] = $feature;

			return $features;
		}

		/**
		 *	Of the applications on offer, those the keys name - in the order of the offer,
		 *	whatever order or repetition the keys have
		 *
		 *	@param		array 		$offered			Applications as Catalogue::applications() answers them
		 *	@param		array 		$keys					Application keys
		 *
		 *	@return 	array										The applications, a list
		 */
		public static function chosen( array $offered, array $keys ): array {

			return array_values( array_filter( $offered, static fn( array $application ): bool => in_array( $application['key'], $keys, true ) ) );
		}

		/**
		 *	The place an application leads to once it is set up: its entry point when
		 *	that is a path of this site, else nothing
		 *
		 *	@param		array 		$application	One application of the catalogue
		 *
		 *	@return 	string|null
		 */
		public static function entry( array $application ): ?string {

			return is_string( $application['next'] ?? null ) === true && \Nino\Http::isSitePath( $application['next'] ) === true ? $application['next'] : null;
		}

		/**
		 *	Set one feature of an application up: place it below features/ where it is not
		 *	there, and switch it on where it is not on - with the features it requires, which
		 *	come along. A feature that is on, at the version its directory carries, is left
		 *	as it is: that is what makes a second round after a failure go on from there.
		 *	The version placed is the one the catalogue offers this kernel.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue, believed by the caller
		 *	@param		string		$key					The feature, which the caller has taken from an application of that catalogue
		 *
		 *	@return 	array										{ placed, activated, reason, status }: reason is '' and status 200 where it went through, else the kernel's sentence and 400 (the feature could not be applied), 409 (another change of the features is running) or 500 (a placed feature is not there, or the lock cannot be had: the reason is \Nino\Features::UNLOCKABLE then)
		 */
		public static function place( array &$appData, array $catalogue, string $key ): array {

			// One lock for the whole step - looking, placing and switching on - so
			// that no other change of the features slips in between the two
			$locked = \Nino\Features::lock( $appData );
			if( $locked !== true )
				return [ 'placed' => false, 'activated' => false, 'reason' => $locked, 'status' => $locked === \Nino\Features::BUSY ? 409 : 500 ];

			try {
				return self::_place( $appData, $catalogue, $key );
			}
			finally {
				\Nino\Features::unlock( $appData );
			}
		}

		/**
		 *	The step of place(), under the lock of the features
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$catalogue		A parsed catalogue, believed by the caller
		 *	@param		string		$key					The feature
		 *
		 *	@return 	array										See place()
		 */
		private static function _place( array &$appData, array $catalogue, string $key ): array {

			$offer	= \Nino\Catalogue::offers( $appData, $catalogue )[$key] ?? null;
			$placed	= false;

			if( $offer === null )
				return [ 'placed' => false, 'activated' => false, 'reason' => 'the catalogue does not list "'. $key. '"', 'status' => 400 ];

			if( \Nino\Features::get( $appData, $key ) === null ) {

				$result = \Nino\Catalogue::install( $appData, $key, $offer['version'] );
				if( $result !== true )
					return [ 'placed' => false, 'activated' => false, 'reason' => $result, 'status' => 400 ];

				$placed = true;
			}

			$feature = \Nino\Features::get( $appData, $key );
			if( $feature === null )
				return [ 'placed' => $placed, 'activated' => false, 'reason' => 'feature "'. $key. '" is not there after it was placed', 'status' => 500 ];

			$activated = false;

			if( self::isOn( $feature ) === false ) {

				$result = \Nino\Features::activate( $appData, $key );
				if( $result !== true )
					return [ 'placed' => $placed, 'activated' => false, 'reason' => $result, 'status' => 400 ];

				$activated = true;
			}

			return [ 'placed' => $placed, 'activated' => $activated, 'reason' => '', 'status' => 200 ];
		}
	}
}
