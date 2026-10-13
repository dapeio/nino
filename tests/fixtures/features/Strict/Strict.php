<?php
declare(strict_types=1);

namespace Nino\Modules {

	// A feature with a stack in its manifest and a doShortcode(), but no stackLedger()
	class Strict {

		public static function doShortcode( array &$appData, array $args ): string {

			return '';
		}
	}
}
