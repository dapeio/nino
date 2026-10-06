<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Features\Settings	Settings tab: a feature's settings inside its own panel
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Features {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Settings					The tab a feature's panel gets when the feature declares
	 *											settings - the form its manifest describes, next to the
	 *											screen it belongs to rather than behind the Features
	 *											panel. One class, many tabs: \Nino\Admin\Panels::collect()
	 *											builds one registry entry per feature (uri
	 *											<panel>-settings, mount feature-settings-<key>) and no
	 *											panel names this class in tabs(), because a panel cannot
	 *											know how many features will bring one. The form is the
	 *											Features panel's own: the same permission, the same
	 *											features/list and features/settings actions, the same
	 *											renderers - so the tab brings no action of its own, and
	 *											an account holding a feature's permission alone still
	 *											does not see its settings. Deleting this directory only
	 *											takes the tab away; the Features panel then shows the
	 *											form again
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Settings {

		// What a mount of this tab is called, followed by the feature's key
		public const string MOUNT_PREFIX = 'feature-settings-';

		public static function perm(): string {
			return Admin::MANAGE_PERM;
		}

		// The actions are the Features panel's - see features/list and
		// features/settings there
		public static function actions(): array {
			return [];
		}

		// The Features panel's words: the tab's name and the form's labels
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}
	}
}
