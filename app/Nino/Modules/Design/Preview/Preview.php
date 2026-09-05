<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Design\Preview	The example page the Design settings are judged on
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Design {

	/**
	 *	The page the pickers preview against.
	 *
	 *	This lives here rather than in the wizard because it is Design's own
	 *	question: what these settings actually produce. The wizard borrows it
	 *	the same way it borrows Tokens - through class_exists, so a delivery
	 *	shipping without this module still installs, with the preview gone
	 *	from the step rather than a fatal on a missing class.
	 *
	 *	Delivered as a complete document into a sandboxed iframe, because the
	 *	example styles bare element selectors and sets :root variables that
	 *	would otherwise land on the tool's own shell.
	 */
	class Preview {

		private const string EXAMPLE = __DIR__. '/../templates/preview-example.tpl';

		private const string BUNDLE_KEY = '/.cache/style.css';

		// A ceiling per picture. The demo images are half a kilobyte of svg,
		// so this is generous - it is here so a project that dropped a 12mb
		// photograph into the path cannot turn one preview request into one
		// too large to send
		private const int IMAGE_LIMIT = 262144;

		private const array IMAGE_TYPES = [
			'svg' => 'image/svg+xml', 'png' => 'image/png',
			'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
		];

		/**
		 *	One complete html document showing what these settings produce.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$settings			Design settings, normalized here if they are not already
		 *	@param		string		$mode					'light' or 'dark'
		 *
		 *	@return 	string									A document, or '' if the example is missing
		 */
		public static function document( array &$appData, array $settings, string $mode = 'light' ): string {

			$markup = @file_get_contents( self::EXAMPLE );

			if( $markup === false )
				return '';

			$mode = $mode === 'dark' ? 'dark' : 'light';
			$frames = self::frames( $appData );

			$css = self::css( $appData, $settings );

			foreach( $frames as $frame )
				$css .= "\n". $frame['css'];

			$markup = ( $frames['header']['markup'] ?? '' )
				. self::images( $appData, $markup )
				. ( $frames['footer']['markup'] ?? '' );

			return '<!doctype html><html lang="en" data-nino-mode="'. $mode. '"><head><meta charset="utf-8">'
				. '<meta name="viewport" content="width=device-width, initial-scale=1">'
				. '<style>'. $css. '</style>'
				. '<style>'. self::scaffold( isset( $frames['header'] ) ). '</style>'
				. '</head><body>'. $markup. '</body></html>';
		}

		/**
		 *	The header and footer this project actually has, ready to paste
		 *	around the example.
		 *
		 *	The pane used to draw a bar and a footer of its own out of framework
		 *	classes. That is a bar and a footer no site has, and on a look whose
		 *	whole idea is a vertical rail rather than a top bar it was not a
		 *	simplification but the wrong picture - the operator was judging the
		 *	colours of a layout the theme does not use. The real units are right
		 *	there in the library, and the wizard already knows how to prepare
		 *	one for a preview, so this asks rather than rebuilding.
		 *
		 *	A delivery may ship without the wizard, and then there are no
		 *	frames to have: the example stands on its own, as it did before.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array									kind => { frame, markup, css }
		 */
		private static function frames( array &$appData ): array {

			if( \Nino\Modules\Design::setup() === false
				|| method_exists( '\\Nino\\Install\\Themes', 'frameUnit' ) === false )
				return [];

			$frames = [];

			foreach( [ 'header', 'footer' ] as $kind ) {

				$key = \Nino\Install\Themes::previewFrameKey( $appData, $kind );

				if( $key === null )
					continue;

				$unit = \Nino\Install\Themes::frameUnit( $kind, $key );

				if( $unit !== null )
					$frames[$kind] = $unit;
			}

			return $frames;
		}

		/**
		 *	The pictures the example points at, carried into it.
		 *
		 *	The markup names them the way a page does - a path under the public
		 *	directory - because that is the whole rule this example lives by.
		 *	But a srcdoc frame has an opaque origin and fetches nothing, so the
		 *	file travels as data or not at all. The project's own copy first,
		 *	then the library's, so a preview has pictures before the page unit
		 *	that ships them has been installed.
		 *
		 *	Only files under the public directory, and only images: the src is
		 *	written in a template this project owns, but a template is still not
		 *	a reason to read an arbitrary path.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$markup				The example
		 *
		 *	@return 	string
		 */
		private static function images( array &$appData, string $markup ): string {

			return (string) preg_replace_callback( '~\[\[/nino/public\]\](/[a-z0-9./_-]+\.(?:svg|png|jpe?g|webp))~i', static function( array $match ) use ( &$appData ): string {

				$relative = $match[1];

				if( str_contains( $relative, '..' ) === true )
					return '';

				$candidates = [ (string) \Nino\Filesystem::path( $appData, $relative ) ];

				// The same file as the demo page unit ships, read from the
				// library when the project has not installed that unit
				$candidates[] = \Nino\Modules\Design::library(). '/pages/.demo-catalogue'. $relative;

				foreach( $candidates as $path )
					if( is_file( $path ) === true && filesize( $path ) <= self::IMAGE_LIMIT ) {

						$data = (string) @file_get_contents( $path );

						if( $data !== '' )
							return 'data:'. self::IMAGE_TYPES[strtolower( pathinfo( $path, PATHINFO_EXTENSION ) )]
								. ';base64,'. base64_encode( $data );
					}

				// Better an empty frame than a broken-image icon, which reads
				// as the design's fault rather than as a missing file
				return '';
			}, $markup );
		}

		/**
		 *	Everything a real page would load, in the order a real page loads
		 *	it: the framework, the values being previewed, then whatever the
		 *	project itself has bundled - its theme and its frames.
		 *
		 *	Reading the project's own bundle rather than the wizard's
		 *	library is what keeps this working after the wizard is deleted, and
		 *	it is also the more honest answer: the preview shows the site as it
		 *	is styled, not as a catalogue entry would style it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$settings			Design settings
		 *
		 *	@return 	string
		 */
		private static function css( array &$appData, array $settings ): string {

			// Nino.css is the floor and the generated file is spliced in directly
			// behind it, exactly as Design::bundle() orders them in a real project
			$css = (string) @file_get_contents( \Nino\Admin\Admin::ROOT. '/_nino/Nino.css' );
			$css .= "\n". Tokens::css( Tokens::normalize( $settings ) );

			foreach( self::stylesheets( $appData ) as $file )
				$css .= "\n". (string) @file_get_contents( $file );

			/*	The webfonts are the one thing the preview cannot have: a
				srcdoc iframe has an opaque origin, so every @font-face would
				be one CORS error and the family names would resolve to
				nothing - leaving the whole page in the browser's serif
				default, which is a worse lie than a system stack.	*/
			$css = (string) preg_replace( '~@font-face\s*\{[^{}]*\}~i', '', $css );

			// Anything a request would have resolved and this one did not.
			// Left in, a fill is a literal '[[...]]' in the middle of a value
			return (string) preg_replace( '~\[\[[^\]]*\]\]~', '', $css );
		}

		/**
		 *	The project's own bundled stylesheets as absolute paths, minus the
		 *	two this document supplies itself.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		private static function stylesheets( array &$appData ): array {

			$bundled = $appData['/nino/html/assets'][self::BUNDLE_KEY] ?? [];
			$files 	= [];

			foreach( $bundled as $entry ) {

				$entry = (string) $entry;

				// The framework is already the floor above, and the generated
				// file is the settings being previewed rather than the ones
				// last saved - taking the saved copy would show the operator
				// the design they are trying to change
				if( $entry === '/_nino/Nino.css' || $entry === \Nino\Modules\Design::STYLESHEET )
					continue;

				// A tool path is repo-relative; everything else is a project
				// file and lives under the public directory
				$path = str_starts_with( $entry, '/_' ) === true
					? ( (string) ( $appData['./nino/uid'] ?? '' ) ). $entry
					: \Nino\Filesystem::path( $appData, $entry );

				if( is_string( $path ) === true && is_file( $path ) === true )
					$files[] = $path;
			}

			return $files;
		}

		/**
		 *	The two things the document cannot get from the stylesheets it
		 *	loads, and nothing else.
		 *
		 *	The example itself is written in design-system classes, so there is
		 *	no layout to supply here - anything that needed a rule of its own
		 *	would be a component the framework is missing, and writing it here
		 *	would hide that rather than fix it.
		 *
		 *	@return 	string
		 */
		private static function scaffold( bool $framed = false ): string {

			/*	Two rules a real page gets from its header unit rather than from
				the framework: the padding that keeps a fixed bar off the first
				heading, and the row the nav links sit in. When the example is
				shown inside the project's actual frame that unit supplies both,
				and repeating them here would override what it decided - so they
				are only here for a delivery that has no frames at all.	*/
			$frame = $framed === true ? '' : 'body main{padding-top:clamp(5rem,var(--space-6),8rem)}'
				. 'header .nino-nav-content ul{display:flex;gap:var(--space-2)}'
				. 'header .nino-nav-content li a{display:inline-block;padding:var(--space-1) 0}';

			return 'body{margin:0}'. $frame
				/*	The pictures are placeholders, and they are here to show what
					a picture does to a layout - how much room it takes, where it
					crops, what a card looks like with one in it. Their own colours
					are not part of that and are the one thing on the page nobody
					picked: a blue-violet demo gradient beside a red brand reads as
					a clash the design does not have. Desaturated, so the only
					colours on screen are the generated ones.	*/
				. 'body :is(main,header,footer) img{filter:grayscale(1)}'
				// A face the iframe can actually resolve, since the theme's own
				// were stripped with the @font-face rules that declared them
				. ':root{'
				. '--fontfamily-text:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
				. '--fontfamily-title:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
				. '--fontfamily-subtitle:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}';
		}
	}
}
