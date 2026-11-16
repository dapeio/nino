<?php
declare(strict_types=1);

namespace Nino\Modules {

	// The class of the feature probe: the renderers the register finds by the name of a component, studly caps behind 'component'
	class Probe {

		public static function init( array &$appData ): void {

			// What the register holds when the init() of a module runs: the components of its own feature
			$appData['./nino/probe/seen'] = array_keys( \Nino\Components::components( $appData ) );

			// A theme that takes the standard part of one group away, where the process that boots says so
			if( defined( 'NINO_PROBE_THEME' ) === true )
				\Nino\Callbacks::registerCallback( $appData, \Nino\Components::PARTS, static fn( array &$appData, array $answer ): array => $answer['group'] === 'probe' ? [ 'css' => '' ] + $answer : $answer );
		}

		// probe-box: a container; the template is filled as the register fills it
		public static function componentProbeBox( array &$appData, array $args, string $content, array $context ): string {

			return \Nino\Components::fill( $appData, 'probe-box', $args, $content, [ '[[mark]]' => 'box' ] );
		}

		// probe-line: a token of its own, escaped by the renderer
		public static function componentProbeLine( array &$appData, array $args, string $content, array $context ): string {

			return \Nino\Components::fill( $appData, 'probe-line', $args, $content, [ '[[mark]]' => \Nino\Components::escape( $args['tone'] ) ] );
		}

		// The options of the attribute tone of probe-line, asked by the register when a tool or a call needs them
		public static function tones( array &$appData, string $attribute ): array {

			return [ 'calm' => 'Calm', 'loud' => 'Loud' ];
		}
	}
}
