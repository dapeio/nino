<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The class of Picker: the methods its attributes name for their options, each counting
	// the times it was asked, and the renderers of its components
	class Picker {

		// How often each method was asked, by method
		public static array $asked = [];

		// The forms the project has: value => label, a label a string or a locale map
		public static function forms( array &$appData, string $attribute ): array {

			self::$asked['forms'] = ( self::$asked['forms'] ?? 0 ) + 1;
			self::$asked['formsFor'] = $attribute;

			return [
				''				=> [ 'en_US' => 'None', 'de_DE' => 'Keines' ],
				'contact'	=> 'Contact',
				'news'		=> [ 'en_US' => 'Newsletter', 'de_DE' => 'Rundbrief' ],
			];
		}

		public static function tones( array &$appData, string $attribute ): array {
			return [ '' => 'None', 'loud' => 'Loud', 'soft-grey' => [ 'en_US' => 'Soft grey' ] ];
		}

		// Not an array
		public static function flat( array &$appData, string $attribute ): mixed {
			self::$asked['flat'] = ( self::$asked['flat'] ?? 0 ) + 1;
			return 'a,b';
		}

		public static function thrown( array &$appData, string $attribute ): array {
			self::$asked['thrown'] = ( self::$asked['thrown'] ?? 0 ) + 1;
			throw new \RuntimeException( 'no forms today' );
		}

		// A label that is neither a string nor a map of strings
		public static function unlabelled( array &$appData, string $attribute ): array {
			return [ 'a' => 'A', 'b' => 7 ];
		}

		// Words a variant cannot be written as
		public static function notWords( array &$appData, string $attribute ): array {
			return [ '' => 'None', 'Loud Word' => 'Loud' ];
		}

		// A method that asks the registry for the schema it is being asked for
		public static function again( array &$appData, string $attribute ): array {

			self::$asked['again'] = ( self::$asked['again'] ?? 0 ) + 1;

			// Asks the registry, which must not ask this method again: if it does, the third time
			// is the last, so that the test fails and does not run out of memory
			if( self::$asked['again'] < 3 )
				\Nino\Modules\Components::components( $appData );

			return [ '' => 'None', 'x' => 'X' ];
		}

		public static function componentPickerForm( array &$appData, array $args ): string {
			return '<p class="picker-form'. ( $args['tone'] !== '' ? ' picker-form--'. $args['tone'] : '' ). '" data-form="'. \Nino\Modules\Components::escape( $args['form'] ). '"></p>';
		}

		public static function componentPickerBroken( array &$appData, array $args ): string {
			return '<p class="picker-broken" data-flat="'. \Nino\Modules\Components::escape( $args['flat'] ). '" data-thrown="'. \Nino\Modules\Components::escape( $args['thrown'] ). '" data-label="'. \Nino\Modules\Components::escape( $args['label'] ). '" data-word="'. \Nino\Modules\Components::escape( $args['word'] ). '"></p>';
		}

		public static function stackPickerRows( array &$appData, array $args ): string {
			return '<div class="picker-rows" data-form="'. \Nino\Modules\Components::escape( $args['form'] ). '"></div>';
		}
	}
}
