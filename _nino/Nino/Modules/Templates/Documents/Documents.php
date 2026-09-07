<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\Documents	The page-*.tpl files: list, create, load, inspect, save, delete
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

	/**
	 *	Only page-*.tpl documents, with optimistic concurrency and locked raw
	 *	segments. The browser may rearrange section segments but cannot rewrite
	 *	the page frame surrounding them.
	 */
	class Documents {

		public static function actions(): array {
			return [
				'documents/list'	=> [ self::class, 'apiList' ],
				'documents/create'	=> [ self::class, 'apiCreate' ],
				'documents/delete'	=> [ self::class, 'apiDelete' ],
				'documents/includes'	=> [ self::class, 'apiIncludes' ],
				'documents/load'	=> [ self::class, 'apiLoad' ],
				'documents/save'	=> [ self::class, 'apiSave' ],
				'documents/inspect'	=> [ self::class, 'apiInspect' ],
			];
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'documents/create'	=> 'Created template "'. (string) ( $data['filename'] ?? '' ). '"',
				'documents/delete'	=> 'Deleted template "'. (string) ( $data['name'] ?? '' ). '"',
				'documents/save'		=> 'Saved template "'. (string) ( $data['name'] ?? '' ). '"',
				default	=> '',
			};
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$documents = [];
			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/page-*.tpl' ) ?: [] as $file ) {
				$name = basename( $file, '.tpl' );
				$source = (string) file_get_contents( $file );
				$parsed = self::_pageSegments( $source, $name );
				$documents[] = [
					'name' => $name,
					'filename' => $name. '.tpl',
					'displayName' => $parsed['displayName'],
					'pageId' => self::_pageId( $name ),
					'pageMotion' => $parsed['pageMotion'],
					'sections' => $parsed['sectionCount'],
					'components' => $parsed['componentCount'],
					'editable' => $parsed['error'] === null,
				];
			}

			usort( $documents, fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );
			\Nino\Http::ok( $request, [ 'documents' => $documents ] );
		}

		public static function apiCreate( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data = Admin::postData();
			$filename = trim( (string) ( $data['filename'] ?? '' ) );
			if( preg_match( '/^page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl$/', $filename ) !== 1 || str_contains( $filename, '..' ) ) {
				\Nino\Http::fail( $request, 400, 'filename must look like page-services.tpl and may contain only letters, numbers, dots, underscores and hyphens' );
				return;
			}

			$name = substr( $filename, 0, -4 );
			$path = self::_path( $appData, $name );
			if( $path === null ) {
				\Nino\Http::fail( $request, 400, 'invalid page template name' );
				return;
			}
			if( is_file( $path ) ) {
				\Nino\Http::fail( $request, 409, 'a page template with this filename already exists' );
				return;
			}

			$displayName = trim( (string) ( $data['displayName'] ?? self::_defaultDisplayName( $name ) ) );
			$pageMotion = (string) ( $data['pageMotion'] ?? 'off' );
			$header = array_key_exists( 'header', $data ) ? trim( (string) $data['header'] ) : '/templates/html-header';
			$footer = array_key_exists( 'footer', $data ) ? trim( (string) $data['footer'] ) : '/templates/html-footer';
			if( self::_validDisplayName( $displayName ) === false ) {
				\Nino\Http::fail( $request, 400, 'template name must contain 1–160 safe characters' );
				return;
			}
			if( in_array( $pageMotion, [ 'on', 'off' ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid VPA setting' );
				return;
			}
			if( self::_validIncludePath( $header ) === false || self::_validIncludePath( $footer ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid header or footer template' );
				return;
			}

			$source = self::_metadataSource( $displayName, $pageMotion )
				. SectionDocument::slotSource( 'header', $header )
				. SectionDocument::slotSource( 'footer', $footer );
			if( self::_write( $path, $source ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not create the page template' );
				return;
			}

			\Nino\Http::ok( $request, [
				'name' => $name,
				'filename' => $filename,
				'displayName' => $displayName,
				'pageId' => self::_pageId( $name ),
				'pageMotion' => $pageMotion,
				'revision' => hash( 'sha256', $source ),
			] );
		}

		public static function apiDelete( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data = Admin::postData();
			$name = (string) ( $data['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page template' );
				return;
			}
			if( !hash_equals( $name, (string) ( $data['confirmName'] ?? '' ) ) ) {
				\Nino\Http::fail( $request, 400, 'template deletion was not confirmed' );
				return;
			}

			$current = (string) file_get_contents( $path );
			if( !hash_equals( hash( 'sha256', $current ), (string) ( $data['revision'] ?? '' ) ) ) {
				\Nino\Http::fail( $request, 409, 'the page template changed on disk; reload before deleting it' );
				return;
			}
			if( @unlink( $path ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not delete the page template' );
				return;
			}

			clearstatcache( true, $path );
			\Nino\Http::ok( $request, [ 'name' => $name, 'filename' => $name. '.tpl' ] );
		}

		public static function apiIncludes( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$names = [ 'html-header' => true, 'html-footer' => true ];
			foreach( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [] as $file ) {
				$name = basename( $file, '.tpl' );
				if( preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name ) === 1 && str_starts_with( $name, 'page-' ) === false )
					$names[$name] = true;
			}

			$includes = [];
			foreach( array_keys( $names ) as $name ) {
				// A slug rather than a word: the panel shows this and compares
				// it (see script.js's includeKind()), and a comparison against
				// a translated label would only hold in one language
				$kind = in_array( $name, [ 'html-header', 'html-footer' ], true )
					? 'frame'
					: ( str_starts_with( $name, 'section-' ) ? 'section' : 'partial' );
				$includes[] = [
					'name' => $name,
					'path' => '/templates/'. $name,
					'label' => ucwords( str_replace( [ '-', '_' ], ' ', $name ) ),
					'kind' => $kind,
					'exists' => is_file( \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' ) ),
				];
			}

			usort( $includes, function( array $a, array $b ): int {
				$priority = [ 'html-header' => 0, 'html-footer' => 1 ];
				return ( $priority[$a['name']] ?? 2 ) <=> ( $priority[$b['name']] ?? 2 ) ?: strcmp( $a['name'], $b['name'] );
			} );
			\Nino\Http::ok( $request, [ 'includes' => $includes ] );
		}

		public static function apiLoad( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$name = (string) ( Admin::postData()['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page template' );
				return;
			}

			$source = (string) file_get_contents( $path );
			$parsed = self::_pageSegments( $source, $name );

			\Nino\Http::ok( $request, [
				'name'		=> $name,
				'filename'	=> $name. '.tpl',
				'displayName' => $parsed['displayName'],
				'pageId'	=> self::_pageId( $name ),
				'pageMotion' => $parsed['pageMotion'],
				'revision'	=> hash( 'sha256', $source ),
				'segments'	=> $parsed['segments'],
				'readonly'	=> $parsed['error'],
			] );
		}

		public static function apiInspect( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$source = (string) ( Admin::postData()['source'] ?? '' );
			if( strlen( $source ) > 1048576 ) {
				\Nino\Http::fail( $request, 413, 'section source is too large' );
				return;
			}

			$result = SectionDocument::inspectSection( $source );
			if( $result['valid'] !== true ) {
				\Nino\Http::fail( $request, 400, $result['error'] ?? 'invalid section source' );
				return;
			}

			\Nino\Http::ok( $request, [ 'segment' => $result['segment'] ] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( Admin::guard( $appData, $request ) === false )
				return;

			$data = Admin::postData();
			$name = (string) ( $data['name'] ?? '' );
			$path = self::_path( $appData, $name );

			if( $path === null || is_file( $path ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown page template' );
				return;
			}

			$current = (string) file_get_contents( $path );
			if( !hash_equals( hash( 'sha256', $current ), (string) ( $data['revision'] ?? '' ) ) ) {
				\Nino\Http::fail( $request, 409, 'the page template changed on disk; reload before saving' );
				return;
			}

			$segments = is_array( $data['segments'] ?? null ) ? $data['segments'] : null;
			if( $segments === null || count( $segments ) > 500 ) {
				\Nino\Http::fail( $request, 400, 'invalid page segments' );
				return;
			}
			foreach( $segments as $segment )
				if( is_array( $segment ) === false ) {
					\Nino\Http::fail( $request, 400, 'invalid page segment' );
					return;
				}

			$totalBytes = 0;
			foreach( $segments as $segment ) {
				$partBytes = strlen( (string) ( $segment['source'] ?? '' ) );
				$totalBytes += $partBytes;
				if( $partBytes > 4194304 || $totalBytes > 16777216 ) {
					\Nino\Http::fail( $request, 413, 'page template is too large' );
					return;
				}
			}

			$currentParsed = self::_pageSegments( $current, $name );
			if( $currentParsed['error'] !== null ) {
				\Nino\Http::fail( $request, 400, 'the current page template is not safely editable' );
				return;
			}
			if( SectionDocument::rawSources( $segments ) !== SectionDocument::rawSources( $currentParsed['segments'] ) ) {
				\Nino\Http::fail( $request, 400, 'locked page-frame source was changed' );
				return;
			}

			$displayName = trim( (string) ( $data['displayName'] ?? $currentParsed['displayName'] ) );
			$pageMotion = (string) ( $data['pageMotion'] ?? $currentParsed['pageMotion'] );
			if( self::_validDisplayName( $displayName ) === false ) {
				\Nino\Http::fail( $request, 400, 'template name must contain 1–160 safe characters' );
				return;
			}
			if( in_array( $pageMotion, [ 'on', 'off' ], true ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid VPA setting' );
				return;
			}

			$ids = [];
			$slots = [];
			$source = self::_metadataSource( $displayName, $pageMotion );
			foreach( $segments as $segment ) {
				$type = (string) ( $segment['type'] ?? '' );
				$part = (string) ( $segment['source'] ?? '' );

				if( $type === 'raw' ) {
					$source .= $part;
					continue;
				}

				if( $type === 'template' ) {
					$inspected = SectionDocument::inspectTemplate( $part );
					if( $inspected['valid'] !== true ) {
						\Nino\Http::fail( $request, 400, 'invalid template shortcode' );
						return;
					}
					$source .= $part;
					continue;
				}

				if( $type === 'slot' ) {
					$slot = (string) ( $segment['slot'] ?? '' );
					$inspected = SectionDocument::inspectSlot( $part, $slot );
					if( $inspected['valid'] !== true || isset( $slots[$slot] ) ) {
						\Nino\Http::fail( $request, 400, 'invalid or duplicate page template slot' );
						return;
					}
					$slots[$slot] = true;
					$source .= $part;
					continue;
				}

				if( $type !== 'section' ) {
					\Nino\Http::fail( $request, 400, 'unknown page segment type' );
					return;
				}

				$inspected = SectionDocument::inspectSection( $part );
				if( $inspected['valid'] !== true ) {
					\Nino\Http::fail( $request, 400, 'invalid section source' );
					return;
				}

				$htmlId = (string) $inspected['segment']['htmlId'];
				if( $htmlId !== '' && isset( $ids[$htmlId] ) ) {
					\Nino\Http::fail( $request, 409, 'duplicate section id: "'. $htmlId. '"' );
					return;
				}
				if( $htmlId !== '' )
					$ids[$htmlId] = true;

				$source .= $part;
			}

			if( isset( $slots['header'], $slots['footer'] ) === false ) {
				\Nino\Http::fail( $request, 400, 'page template header and footer slots are required' );
				return;
			}
			$components = array_values( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true )
			) );
			if( ( $components[0]['type'] ?? '' ) !== 'slot' || ( $components[0]['slot'] ?? '' ) !== 'header'
				|| ( $components[count( $components ) - 1]['type'] ?? '' ) !== 'slot' || ( $components[count( $components ) - 1]['slot'] ?? '' ) !== 'footer' ) {
				\Nino\Http::fail( $request, 400, 'header and footer slots must wrap every canvas component' );
				return;
			}

			if( trim( $source ) === '' ) {
				\Nino\Http::fail( $request, 400, 'refusing to write an empty page template' );
				return;
			}

			if( self::_write( $path, $source ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not write the page template' );
				return;
			}

			\Nino\Http::ok( $request, [
				'name' => $name,
				'filename' => $name. '.tpl',
				'displayName' => $displayName,
				'pageMotion' => $pageMotion,
				'bytes' => strlen( $source ),
				'revision' => hash( 'sha256', $source ),
			] );
		}

		private static function _pageId( string $name ): string {
			$id = strtolower( (string) preg_replace( '/[^a-zA-Z0-9-]+/', '-', substr( $name, 5 ) ) );
			$id = trim( $id, '-' ) ?: 'page';
			return preg_match( '/^[a-z]/', $id ) === 1 ? $id : 'p-'. $id;
		}

		/**
		 * Page-only normalization: marked shell includes become fixed slots. A
		 * hand-written page using the conventional exact html-header/html-footer
		 * pair is recognized in memory and receives markers when deliberately
		 * saved through the Builder.
		 */
		private static function _pageSegments( string $source, string $name = 'page' ): array {

			$metadata = self::_readMetadata( $source, $name );
			$parsed = SectionDocument::split( $metadata['source'] );
			$parsed['displayName'] = $metadata['displayName'];
			$parsed['pageMotion'] = $metadata['pageMotion'];
			$parsed['hasMetadata'] = $metadata['hasMetadata'];
			if( $parsed['error'] !== null )
				return $parsed;

			$segments = $parsed['segments'];
			$slotIndexes = [ 'header' => [], 'footer' => [] ];
			foreach( $segments as $index => $segment )
				if( ( $segment['type'] ?? '' ) === 'slot' && isset( $slotIndexes[$segment['slot'] ?? ''] ) )
					$slotIndexes[$segment['slot']][] = $index;

			foreach( $slotIndexes as $slot => $indexes )
				if( count( $indexes ) > 1 ) {
					$parsed['error'] = 'duplicate '. $slot. ' template slot';
					return $parsed;
				}

			$canvasIndexes = array_keys( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template' ], true )
			) );

			if( $slotIndexes['header'] === [] && $canvasIndexes !== [] ) {
				$first = $canvasIndexes[0];
				if( ( $segments[$first]['type'] ?? '' ) === 'template' && ( $segments[$first]['template'] ?? '' ) === 'html-header' ) {
					$segments[$first] = self::_slotSegment( 'header', '/templates/html-header' );
					$slotIndexes['header'] = [ $first ];
				}
			}

			if( $slotIndexes['footer'] === [] && $canvasIndexes !== [] ) {
				$last = $canvasIndexes[count( $canvasIndexes ) - 1];
				if( ( $segments[$last]['type'] ?? '' ) === 'template' && ( $segments[$last]['template'] ?? '' ) === 'html-footer' ) {
					$segments[$last] = self::_slotSegment( 'footer', '/templates/html-footer' );
					$slotIndexes['footer'] = [ $last ];
				}
			}

			if( $slotIndexes['header'] === [] ) {
				$firstComponent = null;
				foreach( $segments as $index => $segment )
					if( in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true ) ) {
						$firstComponent = $index;
						break;
					}
				$index = $firstComponent ?? 0;
				array_splice( $segments, $index, 0, [ self::_slotSegment( 'header', '' ) ] );
			}

			if( array_filter( $segments, fn( array $segment ): bool => ( $segment['type'] ?? '' ) === 'slot' && ( $segment['slot'] ?? '' ) === 'footer' ) === [] ) {
				$lastComponent = -1;
				foreach( $segments as $index => $segment )
					if( in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true ) )
						$lastComponent = $index;
				array_splice( $segments, $lastComponent + 1, 0, [ self::_slotSegment( 'footer', '' ) ] );
			}

			$components = array_values( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template', 'slot' ], true )
			) );
			if( ( $components[0]['type'] ?? '' ) !== 'slot' || ( $components[0]['slot'] ?? '' ) !== 'header'
				|| ( $components[count( $components ) - 1]['type'] ?? '' ) !== 'slot' || ( $components[count( $components ) - 1]['slot'] ?? '' ) !== 'footer' ) {
				$parsed['error'] = 'header and footer template slots must wrap every canvas component';
				return $parsed;
			}

			$parsed['segments'] = array_values( $segments );
			$parsed['componentCount'] = count( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template' ], true )
			) );
			if( $metadata['hasPageMotion'] === false )
				foreach( $segments as $segment )
					if( ( $segment['type'] ?? '' ) === 'section'
						&& is_array( $segment['spec'] ?? null )
						&& in_array( $segment['spec']['pageMotion'] ?? '', [ 'on', 'off' ], true ) ) {
						$parsed['pageMotion'] = $segment['spec']['pageMotion'];
						break;
					}

			return $parsed;
		}

		private static function _readMetadata( string $source, string $name ): array {

			$result = [
				'source' => $source,
				'displayName' => self::_defaultDisplayName( $name ),
				'pageMotion' => 'off',
				'hasMetadata' => false,
				'hasPageMotion' => false,
			];
			$pattern = '~\A<!--[\t ]*nino:template-name[\t ]+([^\r\n<>]+?)[\t ]*-->[\t ]*(?:\r?\n|$)(?:<!--[\t ]*nino:template-vpa[\t ]+(on|off)[\t ]*-->[\t ]*(?:\r?\n|$))?~';
			if( preg_match( $pattern, $source, $match ) !== 1 )
				return $result;

			$displayName = trim( (string) ( $match[1] ?? '' ) );
			if( self::_validDisplayName( $displayName ) === false )
				return $result;

			$result['source'] = substr( $source, strlen( $match[0] ) );
			$result['displayName'] = $displayName;
			$result['hasMetadata'] = true;
			if( isset( $match[2] ) && $match[2] !== '' ) {
				$result['pageMotion'] = $match[2];
				$result['hasPageMotion'] = true;
			}
			return $result;
		}

		private static function _metadataSource( string $displayName, string $pageMotion ): string {
			return '<!-- nino:template-name '. $displayName. " -->\n"
				. '<!-- nino:template-vpa '. $pageMotion. " -->\n";
		}

		private static function _defaultDisplayName( string $name ): string {
			$plain = preg_replace( '/[._-]+/', ' ', preg_replace( '/^page-/', '', $name ) );
			return ucwords( trim( (string) $plain ) ) ?: 'Page';
		}

		private static function _validDisplayName( string $displayName ): bool {
			return $displayName !== ''
				&& strlen( $displayName ) <= 160
				&& preg_match( '/[\x00-\x1F\x7F<>]/', $displayName ) === 0
				&& str_contains( $displayName, '--' ) === false;
		}

		private static function _validIncludePath( string $path ): bool {
			return $path === '' || preg_match( '~^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$~', $path ) === 1;
		}

		private static function _slotSegment( string $slot, string $path ): array {
			return [
				'type' => 'slot',
				'id' => 'slot-'. $slot,
				'slot' => $slot,
				'template' => $path === '' ? '' : basename( $path ),
				'path' => $path,
				'htmlId' => '',
				'source' => SectionDocument::slotSource( $slot, $path ),
				'spec' => null,
				'fills' => [],
				'elementTypes' => [],
				'imageSlots' => [],
			];
		}

		private static function _path( array &$appData, string $name ): ?string {
			if( preg_match( '/^page-[A-Za-z0-9][A-Za-z0-9._-]*$/', $name ) !== 1 || str_contains( $name, '..' ) )
				return null;
			return \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' );
		}

		private static function _write( string $path, string $content ): bool {

			$temp = $path. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';
			$handle = @fopen( $temp, 'wb' );
			if( $handle === false )
				return false;

			$written = fwrite( $handle, $content );
			$flushed = fflush( $handle );
			fclose( $handle );

			if( $written === false || $written !== strlen( $content ) || $flushed === false ) {
				@unlink( $temp );
				return false;
			}

			$mode = @fileperms( $path );
			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $path ) === false ) {
				@unlink( $temp );
				return false;
			}

			return true;
		}
	}

}
