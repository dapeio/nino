<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The renderer of Boxy: a link it draws itself, with the class a linked card stretches
	class Boxy {

		public static function componentBoxyLink( array &$appData, array $args ): string {
			return '<a class="nino-stretch boxy-link" href="#boxy">'. $args['value']. '</a>';
		}
	}
}
