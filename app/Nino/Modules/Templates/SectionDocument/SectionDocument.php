<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Nino\Modules\Templates\SectionDocument	Splits a page template into locked frame source and movable sections
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules\Templates {

	/**
	 *	Lossless top-level section splitter. It scans tags with quote-aware
	 *	boundaries, skips comments and raw script/style bodies, and never
	 *	serializes source. Rejoining unchanged segments reproduces the file.
	 */
	class SectionDocument {

		private const array RAW_TAGS = [ 'script', 'style', 'textarea' ];
		private const array VOID_TAGS = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];

		public static function split( string $source ): array {

			$segments = [];
			$cursor = 0;
			$offset = 0;
			$depth = 0;
			$sectionStart = null;
			$error = null;
			$sectionCount = 0;

			while( ( $tag = self::_nextTag( $source, $offset ) ) !== null ) {

				$offset = $tag['end'];

				if( $tag['comment'] === true )
					continue;

				if( in_array( $tag['name'], self::RAW_TAGS, true ) && $tag['closing'] === false ) {
					$pattern = '#</\s*'. preg_quote( $tag['name'], '#' ). '\s*>#i';
					if( preg_match( $pattern, $source, $close, PREG_OFFSET_CAPTURE, $offset ) === 1 )
						$offset = $close[0][1] + strlen( $close[0][0] );
					else
						$offset = strlen( $source );
					continue;
				}

				if( $tag['name'] !== 'section' )
					continue;
				if( $tag['closing'] === false && $tag['selfClosing'] === true ) {
					$error = 'self-closing <section> is not valid HTML';
					break;
				}

				if( $tag['closing'] === false ) {
					if( $depth === 0 )
						$sectionStart = $tag['start'];
					$depth++;

				} else {
					$depth--;
					if( $depth < 0 ) {
						$error = 'closing </section> without an opening tag';
						break;
					}
				}

				if( $depth !== 0 || $sectionStart === null )
					continue;

				$end = $tag['end'];
				while( $end < strlen( $source ) && str_contains( " \t\r\n", $source[$end] ) )
					$end++;

				if( $sectionStart > $cursor )
					$segments[] = [ 'type' => 'raw', 'source' => substr( $source, $cursor, $sectionStart - $cursor ) ];

				$sectionSource = substr( $source, $sectionStart, $end - $sectionStart );
				$sectionCount++;
				$segments[] = self::_segment( $sectionSource, 'section-'. $sectionCount );

				$cursor = $end;
				$offset = $end;
				$sectionStart = null;
			}

			if( $error === null && $depth !== 0 )
				$error = 'unclosed <section> element';

			if( $cursor < strlen( $source ) )
				$segments[] = [ 'type' => 'raw', 'source' => substr( $source, $cursor ) ];
			else if( $segments === [] )
				$segments[] = [ 'type' => 'raw', 'source' => '' ];

			if( $error === null )
				$segments = self::_splitTemplateIncludes( $segments );

			$componentCount = count( array_filter(
				$segments,
				fn( array $segment ): bool => in_array( $segment['type'] ?? '', [ 'section', 'template' ], true )
			) );

			return [
				'segments' => $segments,
				'error' => $error,
				'sectionCount' => $sectionCount,
				'componentCount' => $componentCount,
			];
		}

		public static function inspectSection( string $source ): array {

			$parsed = self::split( $source );
			$sections = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
			$templates = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'template' ) );
			$slots = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
			$raw = implode( '', array_map( fn( array $segment ): string => $segment['type'] === 'raw' ? $segment['source'] : '', $parsed['segments'] ) );
			$valid = $parsed['error'] === null && count( $sections ) === 1 && $templates === [] && $slots === [] && trim( $raw ) === '';

			return [
				'valid' => $valid,
				'error' => $valid ? null : ( $parsed['error'] ?? 'source must contain exactly one section' ),
				'segment' => $valid ? $sections[0] : null,
			];
		}

		public static function inspectTemplate( string $source ): array {

			$parsed = self::split( $source );
			$templates = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'template' ) );
			$sections = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'section' ) );
			$slots = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
			$raw = implode( '', array_map( fn( array $segment ): string => $segment['type'] === 'raw' ? $segment['source'] : '', $parsed['segments'] ) );
			$valid = $parsed['error'] === null && count( $templates ) === 1 && $sections === [] && $slots === [] && trim( $raw ) === '';

			return [
				'valid' => $valid,
				'error' => $valid ? null : ( $parsed['error'] ?? 'source must contain exactly one template shortcode' ),
				'segment' => $valid ? $templates[0] : null,
			];
		}

		public static function inspectSlot( string $source, string $slot ): array {

			$parsed = self::split( $source );
			$slots = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => $segment['type'] === 'slot' ) );
			$components = array_values( array_filter( $parsed['segments'], fn( array $segment ): bool => in_array( $segment['type'], [ 'section', 'template' ], true ) ) );
			$raw = implode( '', array_map( fn( array $segment ): string => $segment['type'] === 'raw' ? $segment['source'] : '', $parsed['segments'] ) );
			$valid = in_array( $slot, [ 'header', 'footer' ], true )
				&& $parsed['error'] === null
				&& count( $slots ) === 1
				&& ( $slots[0]['slot'] ?? '' ) === $slot
				&& $components === []
				&& trim( $raw ) === '';

			return [
				'valid' => $valid,
				'error' => $valid ? null : ( $parsed['error'] ?? 'source must contain exactly one page template slot' ),
				'segment' => $valid ? $slots[0] : null,
			];
		}

		public static function slotSource( string $slot, string $path = '' ): string {

			if( in_array( $slot, [ 'header', 'footer' ], true ) === false )
				throw new \InvalidArgumentException( 'invalid page template slot' );
			if( $path !== '' && preg_match( '#^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$#', $path ) !== 1 )
				throw new \InvalidArgumentException( 'invalid page template include' );

			return '<!-- nino:template-slot '. $slot. " -->\n"
				. ( $path === '' ? '' : '[template '. $path. "]\n" );
		}

		public static function rawSources( array $segments ): array {
			return array_values( array_map(
				fn( array $segment ): string => (string) ( $segment['source'] ?? '' ),
				array_filter( $segments, fn( array $segment ): bool => ( $segment['type'] ?? '' ) === 'raw' )
			) );
		}

		private static function _segment( string $source, string $id ): array {

			$openingEnd = self::_tagEnd( $source, 0 ) ?? 0;
			$opening = substr( $source, 0, $openingEnd );
			$htmlId = '';

			if( preg_match( '/\bid\s*=\s*(["\'])(.*?)\1/is', $opening, $match ) === 1 )
				$htmlId = html_entity_decode( $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			$spec = null;
			if( preg_match( '/<!--\s*nino:section\s+(\{.*?\})\s*-->/s', $source, $match ) === 1 ) {
				$decoded = json_decode( $match[1], true );
				if( is_array( $decoded ) )
					$spec = $decoded;
			}

			$fills = [];
			if( preg_match_all( '#\[\[(\/page-[a-z0-9_-]+\/[a-z0-9-]+\/[a-z0-9-]+)\]\]#i', $source, $matches ) > 0 )
				$fills = array_values( array_unique( $matches[1] ) );

			$elementTypes = [];
			if( preg_match_all( '#\[elements\s+/([a-z][a-z0-9_-]*)#i', $source, $matches ) > 0 )
				$elementTypes = array_values( array_unique( $matches[1] ) );

			$imageSlots = [];
			if( preg_match_all( '#\[image\s+(/[a-z0-9_/-]+)#i', $source, $matches ) > 0 )
				$imageSlots = array_values( array_unique( $matches[1] ) );

			return [
				'type'			=> 'section',
				'id'			=> $id,
				'htmlId'		=> $htmlId,
				'source'		=> $source,
				'spec'			=> $spec,
				'fills'			=> $fills,
				'elementTypes'	=> $elementTypes,
				'imageSlots'	=> $imageSlots,
			];
		}

		private static function _splitTemplateIncludes( array $segments ): array {

			$result = [];
			$templateCount = 0;
			$openTags = [];

			foreach( $segments as $segment ) {
				if( ( $segment['type'] ?? '' ) !== 'raw' ) {
					$result[] = $segment;
					continue;
				}

				$raw = (string) ( $segment['source'] ?? '' );
				$searchable = self::_templateSearchSource( $raw, $openTags );
				$cursor = 0;
				$pattern = '~^[\t ]*(?:<!--\s*nino:template-slot\s+(header|footer)\s*-->[\t ]*(?:\r?\n)?(?:[\t ]*\[template[\t ]+(\/templates\/[A-Za-z0-9][A-Za-z0-9._-]*)\][\t ]*(?:\r?\n|$))?|\[template[\t ]+(\/templates\/[A-Za-z0-9][A-Za-z0-9._-]*)\][\t ]*(?:\r?\n|$))~m';
				if( preg_match_all( $pattern, $searchable, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL ) === false ) {
					$result[] = $segment;
					continue;
				}

				foreach( $matches as $match ) {
					$start = $match[0][1];
					if( $start > $cursor )
						$result[] = [ 'type' => 'raw', 'source' => substr( $raw, $cursor, $start - $cursor ) ];

					$slot = (string) ( $match[1][0] ?? '' );
					$path = (string) ( $slot === '' ? ( $match[3][0] ?? '' ) : ( $match[2][0] ?? '' ) );
					$templateCount++;
					$result[] = $slot === ''
						? self::_templateSegment( $match[0][0], $path, 'template-'. $templateCount )
						: self::_slotSegment( $match[0][0], $slot, $path, 'slot-'. $slot );
					$cursor = $start + strlen( $match[0][0] );
				}

				if( $cursor < strlen( $raw ) )
					$result[] = [ 'type' => 'raw', 'source' => substr( $raw, $cursor ) ];
				else if( $raw === '' )
					$result[] = $segment;
			}

			return $result === [] ? [ [ 'type' => 'raw', 'source' => '' ] ] : $result;
		}

		private static function _templateSearchSource( string $source, array &$openTags ): string {

			$searchable = $source;
			$offset = 0;
			$protectedStart = $openTags === [] ? null : 0;

			while( ( $tag = self::_nextTag( $source, $offset ) ) !== null ) {
				$offset = $tag['end'];

				if( $tag['comment'] === true ) {
					$comment = substr( $source, $tag['start'], $tag['end'] - $tag['start'] );
					if( preg_match( '/^<!--\s*nino:template-slot\s+(?:header|footer)\s*-->$/i', trim( $comment ) ) === 1 )
						continue;
					$searchable = self::_maskRange( $searchable, $tag['start'], $tag['end'] );
					continue;
				}

				if( in_array( $tag['name'], self::RAW_TAGS, true ) && $tag['closing'] === false ) {
					$pattern = '#</\s*'. preg_quote( $tag['name'], '#' ). '\s*>#i';
					$end = strlen( $source );
					if( preg_match( $pattern, $source, $close, PREG_OFFSET_CAPTURE, $offset ) === 1 )
						$end = $close[0][1] + strlen( $close[0][0] );
					$searchable = self::_maskRange( $searchable, $tag['start'], $end );
					$offset = $end;
					continue;
				}

				if( $tag['closing'] === false ) {
					if( $tag['selfClosing'] === true || in_array( $tag['name'], self::VOID_TAGS, true ) )
						continue;
					if( $openTags === [] && $protectedStart === null )
						$protectedStart = $tag['start'];
					$openTags[] = $tag['name'];
					continue;
				}

				for( $index = count( $openTags ) - 1; $index >= 0; $index-- )
					if( $openTags[$index] === $tag['name'] ) {
						array_splice( $openTags, $index );
						break;
					}

				if( $openTags === [] && $protectedStart !== null ) {
					$searchable = self::_maskRange( $searchable, $protectedStart, $tag['end'] );
					$protectedStart = null;
				}
			}

			if( $protectedStart !== null )
				$searchable = self::_maskRange( $searchable, $protectedStart, strlen( $source ) );

			return $searchable;
		}

		private static function _maskRange( string $source, int $start, int $end ): string {
			$length = max( 0, $end - $start );
			$masked = preg_replace( '/[^\r\n]/', ' ', substr( $source, $start, $length ) );
			return substr_replace( $source, $masked ?? '', $start, $length );
		}

		private static function _templateSegment( string $source, string $path, string $id ): array {
			return [
				'type' => 'template',
				'id' => $id,
				'template' => basename( $path ),
				'path' => $path,
				'htmlId' => '',
				'source' => $source,
				'spec' => null,
				'fills' => [],
				'elementTypes' => [],
				'imageSlots' => [],
			];
		}

		private static function _slotSegment( string $source, string $slot, string $path, string $id ): array {
			return [
				'type' => 'slot',
				'id' => $id,
				'slot' => $slot,
				'template' => $path === '' ? '' : basename( $path ),
				'path' => $path,
				'htmlId' => '',
				'source' => $source,
				'spec' => null,
				'fills' => [],
				'elementTypes' => [],
				'imageSlots' => [],
			];
		}

		private static function _nextTag( string $source, int $offset ): ?array {

			$length = strlen( $source );

			while( $offset < $length ) {
				$start = strpos( $source, '<', $offset );
				if( $start === false )
					return null;

				if( substr( $source, $start, 4 ) === '<!--' ) {
					$close = strpos( $source, '-->', $start + 4 );
					$end = $close === false ? $length : $close + 3;
					return [ 'start' => $start, 'end' => $end, 'name' => '', 'closing' => false, 'selfClosing' => false, 'comment' => true ];
				}

				// PHP is not part of HTML+'s normal authoring path, but custom
				// templates can still contain it. A string literal such as
				// "<section>" inside that block must never become a visual card.
				if( substr( $source, $start, 2 ) === '<?' ) {
					$close = strpos( $source, '?>', $start + 2 );
					$end = $close === false ? $length : $close + 2;
					return [ 'start' => $start, 'end' => $end, 'name' => '', 'closing' => false, 'selfClosing' => false, 'comment' => true ];
				}

				$end = self::_tagEnd( $source, $start );
				if( $end === null )
					return null;

				$raw = substr( $source, $start, $end - $start );
				if( preg_match( '/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9:-]*)\b/', $raw, $match ) !== 1 ) {
					$offset = $end;
					continue;
				}

				return [
					'start'		=> $start,
					'end'		=> $end,
					'name'		=> strtolower( $match[2] ),
					'closing'	=> $match[1] === '/',
					'selfClosing' => preg_match( '#/\s*>$#', $raw ) === 1,
					'comment'	=> false,
				];
			}

			return null;
		}

		private static function _tagEnd( string $source, int $start ): ?int {

			$quote = '';
			for( $i = $start + 1, $length = strlen( $source ); $i < $length; $i++ ) {
				$char = $source[$i];
				if( $quote !== '' ) {
					if( $char === $quote )
						$quote = '';
					continue;
				}
				if( $char === '"' || $char === "'" ) {
					$quote = $char;
					continue;
				}
				if( $char === '>' )
					return $i + 1;
			}

			return null;
		}
	}

}
