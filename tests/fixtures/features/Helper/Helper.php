<?php
declare(strict_types=1);

namespace Nino\Modules {

	// A feature with nothing but a class: what Sample requires
	class Helper {

		public static function init( array &$appData ): void {
			$appData['./helper/booted'] = true;
		}
	}
}
