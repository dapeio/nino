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

			/*	What docs/features.md invites a hook to do: migrate the
				project's own config with the kernel's read-modify-write
				primitive. Gated on a marker, so the checks that only care
				about the hook being called are unaffected	*/
			if( isset( $appData['./sample/migrate-routes'] ) === true )
				\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ): array {

					$routes = is_array( $content['/nino/http/routes'] ?? null ) ? $content['/nino/http/routes'] : [];
					$routes['GET://sample-migrated'] = [ 'uri' => '/migrated', 'body' => 'written by the upgrade hook' ];
					$content['/nino/http/routes'] = $routes;

					return $content;
				} );

			return $from !== '0.0.1';
		}
	}
}
