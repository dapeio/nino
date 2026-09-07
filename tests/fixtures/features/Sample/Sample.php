<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The reference feature's runtime module - see tests/features-smoke.php
	class Sample {

		public static function init( array &$appData ): void {

			\Nino\Html::addShortcode( $appData, 'sample', [ self::class, 'doShortcode' ] );
		}

		public static function adminPanels( array &$appData ): array {

			return [ \Nino\Modules\Sample\Admin::class ];
		}

		public static function doShortcode( array &$appData, array $args ): string {

			return htmlspecialchars( (string) \Nino\Features::setting( $appData, 'sample', 'title', '' ), ENT_QUOTES, 'UTF-8' );
		}

		// Called by Features::activate() when the recorded version differs
		// from the manifest's. Records what it was asked, refuses one version
		// so the refusal path can be tested
		public static function upgrade( array &$appData, string $from ): bool {

			$appData['./sample/upgraded-from'] = $from;

			return $from !== '0.0.1';
		}
	}
}
