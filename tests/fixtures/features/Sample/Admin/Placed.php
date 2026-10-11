<?php
declare(strict_types=1);

namespace Nino\Modules\Sample {

	// Three more panels of the reference feature, naming a group of their own:
	// two of the four, with a permission each - 'structure', and 'content',
	// the group whose permissions the wizard's Editor role takes in -, and
	// one that is none. A feature's panel sits in the features group whatever
	// its nav() names, and the roles tab files its permission there, so these
	// three prove that the entry is ignored. Not autoloaded - the test that
	// wants them requires this file and answers them from a module of its own
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

	class PlacedContent {

		public const string MANAGE_PERM = '/_admin/sample-content/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function actions(): array {
			return [];
		}

		public static function nav(): array {
			return [ 'sample-content', 'Placed content', 73, 'content' ];
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
