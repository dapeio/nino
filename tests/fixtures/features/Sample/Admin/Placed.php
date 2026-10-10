<?php
declare(strict_types=1);

namespace Nino\Modules\Sample {

	// Two more panels of the reference feature, naming a group of their own:
	// one of the four, with a permission the roles tab files under it, and one
	// that is none. Not autoloaded - the test that wants them requires this file
	// and answers them from a module of its own
	class PlacedStructure {

		public const string MANAGE_PERM = '/_admin/sample-placed/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function actions(): array {
			return [];
		}

		public static function nav(): array {
			return [ 'sample-placed', 'Placed', 71, 'structure' ];
		}
	}

	class PlacedNowhere {

		public static function actions(): array {
			return [];
		}

		public static function nav(): array {
			return [ 'sample-nowhere', 'Nowhere', 72, 'nowhere' ];
		}
	}
}
