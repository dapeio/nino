<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The renderers of Boxy: a title and a box around what it holds
	class Boxy {

		public static function componentBoxyTitle( array &$appData, array $args ): string {
			return '<p class="boxy-title">'. $args['value']. '</p>';
		}

		public static function componentBoxyBox( array &$appData, array $args ): string {
			return '<div class="boxy-box">'. (string) ( $args['content'] ?? '' ). '</div>';
		}
	}
}
