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

			/*	Said out loud rather than dropped in silence. A hook is a string
				and a callable, and both are easy to get slightly wrong - a
				method renamed and one registration left behind, a typo in
				'callbackRespones'. The registration then did nothing, the hook
				never fired, and there was no line anywhere saying why: the
				symptom is a feature that quietly does not work.
				E_USER_WARNING is the kernel's "record this and carry on"
				channel (see \Nino\Runtime::NON_FATAL_LEVELS), so the request
				still finishes - a bad callback must not take the page down	*/
			if( is_callable( $callback ) === false ) {
				trigger_error( 'Nino: the callback registered for \''. $name. '\' is not callable and was not registered.', E_USER_WARNING );
				return;
			}

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
