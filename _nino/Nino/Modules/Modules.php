<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules				Calls a method on every module enabled in config.php, or collects what every module answers
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Modules - calls a method (init/request/response) on every module
	// enabled in config.php's '/nino/modules', or collects what every
	// module answers to one
	class Modules {

		public static function callModules( array &$appData, string $method ): void {

			// method_exists() autoloads $className itself if it isn't defined
			// yet (see the spl_autoload_register() call at the bottom of
			// _nino/Nino.php) - config.php's own '/nino/modules' list only ever
			// controlled which modules get a method called on them here, not
			// which classes exist to be called at all, so there is nothing
			// left for this loop to load by hand
			foreach( $appData['/nino/modules'] ?? [] as $className )
				if( method_exists( $className, $method ) === true )
					$className::$method( $appData );
		}

		/**
		 *	Ask every active module one question and merge the answers - the
		 *	read-only twin of callModules(). This is how the workbench learns
		 *	what a module brings along: /_admin asks 'adminPanels', and a
		 *	module that has nothing to say simply doesn't implement the
		 *	method.
		 *
		 *	Deliberately driven by '/nino/modules' and not by scanning
		 *	directories: a module that is switched off contributes nothing,
		 *	exactly as it renders nothing - and a class under app/ is found
		 *	the same way as one under _nino/, since the autoloader makes no
		 *	difference between them.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$method				Method name every module may implement
		 *
		 *	@return 	array										Every answer's entries, in module order
		 */
		public static function collect( array &$appData, string $method ): array {

			$collected = [];

			foreach( $appData['/nino/modules'] ?? [] as $className ) {

				if( method_exists( $className, $method ) === false )
					continue;

				$answer = $className::$method( $appData );

				if( is_array( $answer ) === true )
					$collected = array_merge( $collected, array_values( $answer ) );
			}

			return $collected;
		}
	}
}
