<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Callbacks				Any code registers itself under a string key, any other code fires that key deliberately
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	// Callbacks - any code registers itself under a string key, any other
	// code fires that key deliberately
	class Callbacks {

		public static function registerCallback( array &$appData, string $name, mixed $callback, int $prio = 5 ): void {

			if( is_callable( $callback ) === false )
				return;

			$appData['./nino/callbacks'][$name] = $appData['./nino/callbacks'][$name] ?? [[],[],[],[],[],[],[],[],[],[]];

			if( isset( $appData['./nino/callbacks'][$name][$prio] ) === false )
				$prio = 5;

			$appData['./nino/callbacks'][$name][$prio][] = $callback;
		}

		public static function doCallbacks( array &$appData, string $name, mixed &$args = null ): mixed {

			// Check registered callback
			if( isset( $appData['./nino/callbacks'][$name] ) === false )
				return $args;

			// call_user_func_array() handles every callable shape
			// registerCallback() accepts (function name, [object, method],
			// [class, method], closure) uniformly - no per-shape branching
			// needed
			foreach( $appData['./nino/callbacks'][$name] AS $prioArray )
				foreach( $prioArray as $callback )
					$args = call_user_func_array( $callback, [ &$appData, &$args ] ) ?? $args;

			return $args;
		}
	}
}
