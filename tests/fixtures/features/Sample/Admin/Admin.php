<?php
declare(strict_types=1);

namespace Nino\Modules\Sample {

	// The reference feature's workbench panel - it names no group, and a
	// feature's panel sits in the features group whatever it names, so the roles
	// tab offers its permission there like any other
	class Admin {

		public const string MANAGE_PERM = '/_admin/sample/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function actions(): array {
			return [ 'sample/list' => [ self::class, 'apiList' ] ];
		}

		public static function nav(): array {
			return [ 'sample', '/_admin/nav/sample', 70 ];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'settings' => \Nino\Features::settings( $appData, 'sample' ) ] );
		}
	}
}
