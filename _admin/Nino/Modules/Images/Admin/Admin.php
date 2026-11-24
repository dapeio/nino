<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Images\Admin	Images panel: the image of every slot
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Images {

	/**
	 *	Nino										A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Images									Admin "Images" panel: the image slots of
	 *													/nino/html/images in config.php - the directory of them
	 *													with a record for each: this screen replaces a slot's
	 *													current image, takes it away and writes its alt texts;
	 *													creating and removing slots is the Image Slots tab's job
	 *													(see Slots/Slots.php)
	 *
	 *	@package								Dape/Nino
	 *	@author									David Perchermeier <mail@dape.io>
	 *	@link										https://github.com/dapeio/nino
	 */

	class Admin {

		public const string MANAGE_PERM = '/_admin/images/manage';

		public static function actions(): array {
			return [
				'images/list' 		=> [ self::class, 'apiList' ],
				'images/upload' 	=> [ self::class, 'apiUpload' ],
				'images/remove' 	=> [ self::class, 'apiRemove' ],
				'images/alt' 			=> [ self::class, 'apiAlt' ],
			];
		}

		public static function nav(): array {
			return [ 'images', '/_admin/nav/images', 40, 'content' ];
		}

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'slot', 'upload', 'alt', 'remove', 'scan', 'icon' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-images-icon lucide-images"><path d="m22 11-1.296-1.296a2.4 2.4 0 0 0-3.408 0L11 16"/><path d="M4 8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2"/><circle cx="13" cy="7" r="1" fill="currentColor"/><rect x="8" y="2" width="14" height="14" rx="2"/></svg>';
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'images-list' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// The slots these images fill are edited on a tab of this same
		// pane (see \Nino\Admin\Panels::collect()), with its own permission;
		// the icon set of the site is made on the other one, with this panel's
		public static function tabs(): array {
			return [ Slots::class, Favicon::class ];
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'images/upload'	=> 'Upload Image '. \Nino\Admin\Admin::postString( $data, 'uri' ),
				'images/remove'	=> 'Remove Image '. \Nino\Admin\Admin::postString( $data, 'uri' ),
				'images/alt'		=> 'Edit Image Alt '. \Nino\Admin\Admin::postString( $data, 'uri' ),
				default	=> '',
			};
		}

		/**
		 *	The size of a stored picture in bytes, 0 for a name that names none: a name that is empty, absolute, climbs out with '..' or holds
		 *	a control character is one a hand wrote into config.php, and is no file below /images/ (see \Nino\Images::read())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			A filename below /images/
		 *
		 *	@return 	int
		 */
		public static function bytes( array &$appData, string $filename ): int {

			if( self::named( $filename ) === false )
				return 0;

			$size = @filesize( \Nino\Filesystem::path( $appData, '/images/'. $filename ) );

			return $size === false ? 0 : $size;
		}

		/**
		 *	Whether a stored name is one of a file below /images/: not empty, not absolute, no '..' and no control character. A name
		 *	that is none was written into config.php by hand, and is no picture of the folder - the list shows it as no picture
		 *
		 *	@param		string		$filename
		 *
		 *	@return 	bool
		 */
		public static function named( string $filename ): bool {
			return $filename !== '' && str_contains( $filename, '..' ) === false && str_starts_with( $filename, '/' ) === false && preg_match( '/[\x00-\x1F\x7F]/', $filename ) !== 1;
		}

		/**
		 *	The group each slot stands under in the directory, by what a person calls the place it belongs to: the name of the page whose route
		 *	renders the template a slot of a template is named after (/template/page-home/... - the first route that renders it names it, in the
		 *	language the workbench shows the names of the pages in, as Slots::usage() does), 'general' for the slots of the head, the foot, the mail
		 *	and the other templates that no page is, and for every slot that is named after no place (/logo, /logo/dark) - the mail templates are the
		 *	site's and no page's, so their pictures are general -, the name of the feature for a slot with more than one word under the key of an
		 *	ACTIVE feature (/gallery/...; /gallery alone is general), and the template's own name, spelled out, for a template that no route renders.
		 *
		 *	A group is led by its key, never by its name: two pages that are called the same are two groups, and a page called like the general group
		 *	is not that group. The key is the template of a page, the key of a feature, or 'general'; where two groups would show the same name the
		 *	client adds the address - 'address' is the http uri of the page, '' for a group that is no page
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$uris					The slots' uris
		 *
		 *	@return 	array										uri => [ 'key' => string, 'type' => 'page'|'feature'|'general'|'other', 'name' => string, 'address' => string ] - the name of a general one is the client's word
		 */
		public static function groups( array &$appData, array $uris ): array {

			$locale = \Nino\Admin\Admin::sessionLocale( $appData );
			$text		= \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );
			$pages	= [];

			foreach( ( $appData['/nino/http/routes'] ?? [] ) as $routeKey => $route ) {

				if( str_starts_with( (string) $routeKey, 'GET://' ) === false || is_array( $route ) === false || preg_match( '#\[template /templates/([A-Za-z0-9._-]+)[\] ]#', (string) ( $route['body'] ?? '' ), $match ) !== 1 )
					continue;

				$httpUri = substr( (string) $routeKey, strlen( 'GET:/' ) );
				$name		 = (string) ( $text['[[/_nino/webpage'. (string) ( $route['uri'] ?? $httpUri ). '/name]]'] ?? '' );

				if( $name !== '' )
					$pages[ $match[1] ] ??= [ $name, $httpUri ];
			}

			$features = [];

			foreach( \Nino\Features::all( $appData ) as $key => $feature )
				if( ( $feature['active'] ?? false ) === true )
					$features[ (string) $key ] = \Nino\Features::localized( $feature['name'] ?? '', $locale ) ?: (string) $key;

			$general = [ 'key' => 'general', 'type' => 'general', 'name' => '', 'address' => '' ];
			$groups = [];

			foreach( $uris as $uri ) {

				$uri		= (string) $uri;
				$words	= array_values( array_filter( explode( '/', $uri ), static fn( string $part ): bool => $part !== '' ) );
				$first	= $words[0] ?? '';

				if( $first === 'template' && isset( $words[1] ) === true ) {

					$template = $words[1];

					if( isset( $pages[$template] ) === true )
						$groups[$uri] = [ 'key' => 'page:'. $template, 'type' => 'page', 'name' => $pages[$template][0], 'address' => $pages[$template][1] ];
					else if( preg_match( '/^(?:frame|html|mail|common)(?:-|$)/', $template ) === 1 )
						$groups[$uri] = $general;
					else
						$groups[$uri] = [ 'key' => 'other:'. $template, 'type' => 'other', 'name' => ucfirst( str_replace( '-', ' ', (string) preg_replace( '/^page-/', '', $template ) ) ), 'address' => '' ];
				}
				else if( count( $words ) > 1 && isset( $features[$first] ) === true )
					$groups[$uri] = [ 'key' => 'feature:'. $first, 'type' => 'feature', 'name' => $features[$first], 'address' => '' ];
				else
					$groups[$uri] = $general;
			}

			return $groups;
		}

		/**
		 *	The addresses of the pages of the website: its routes for GET that are no file (robots.txt, sitemap.xml) and no address of the
		 *	workbench (/_admin)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ http uri, ... ]
		 */
		public static function pageUris( array &$appData ): array {

			$uris = [];

			foreach( ( $appData['/nino/http/routes'] ?? [] ) as $routeKey => $route ) {

				if( str_starts_with( (string) $routeKey, 'GET://' ) === false || is_array( $route ) === false )
					continue;

				$httpUri = substr( (string) $routeKey, strlen( 'GET:/' ) );

				// A file (robots.txt) and the workbench's own address (/_admin) are no page of the website
				if( str_contains( basename( $httpUri ), '.' ) === false && str_starts_with( $httpUri, '/_' ) === false )
					$uris[] = $httpUri;
			}

			return array_values( array_unique( $uris ) );
		}

		/**
		 *	List every developer-fixed image slot - each with its alt texts per
		 *	language, where it is used (see Slots::usage()) - and whether that is
		 *	every page - the group it stands under (see groups()) and, for a slot
		 *	that has a picture, the file, its weight and the size it really has;
		 *	a stored name that is no file below /images/ is no picture here -
		 *	and the languages an alt text can be written in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$usage	= Slots::usage( $appData );
			$groups	= self::groups( $appData, array_map( 'strval', array_keys( \Nino\Images::getSlots( $appData ) ) ) );
			$every	= self::pageUris( $appData );

			$slots = [];
			foreach( \Nino\Images::getSlots( $appData ) as $uri => $slot ) {

				$filename = is_string( $slot['filename'] ?? null ) === true && self::named( $slot['filename'] ) === true ? $slot['filename'] : null;
				$stored 	= $filename === null ? false : \Nino\Images::dimensions( $appData, $filename );
				$where		= $usage[$uri] ?? Slots::NO_USAGE;

				$slots[] = [
					'uri' 			=> $uri,
					'label' 		=> $slot['label'] ?? $uri,
					'width' 		=> $slot['width'] ?? 0,
					'height' 		=> $slot['height'] ?? 0,
					'url' 			=> $filename === null ? null : \Nino\Images::getUrl( $appData, $filename ),
					'file' 			=> $filename,
					'bytes' 		=> $filename === null ? 0 : self::bytes( $appData, $filename ),
					'shown' 		=> $stored === false ? null : $stored,
					'alt' 			=> $slot['alt'] ?? [],
					'group' 		=> $groups[(string) $uri],
					'usage' 		=> $where,
					// A slot that every page shows - the logo of the head - is said so, and not as a list of all of them. A website of ONE page has no
					// "every": the slot stands on that page, and is said to be on it by its name
					'everywhere'=> count( $where['pages'] ) > 1 && array_diff( $every, array_column( $where['pages'], 'httpUri' ) ) === [],
				];
			}

			\Nino\Http::ok( $request, [ 'slots' => $slots, 'locales' => \Nino\Locales::getAvailableLocales( $appData ) ] );
		}

		/**
		 *	Upload, process and store a new image for one slot. The call commits,
		 *	same as \Nino\Modules\Elements\Admin::apiUploadImage() - the record of
		 *	the panel sends it from its Save, after the person chose a file and
		 *	looked at it in the browser, so that a file nobody saved never
		 *	arrives. Stored at a
		 *	deterministic path ("images/<uri>"), so a replace overwrites in
		 *	place; the previous file only needs deleting in the rare case the
		 *	output format itself changed - and only once the slot's record is
		 *	written. A config.php that cannot be written leaves the old file
		 *	standing: put back as it was where the new one overwrote it, the
		 *	new one removed where it has a name of its own. The answer carries the
		 *	weight of the stored file, says how large the picture is as it is shown
		 *	and whether that is smaller than the slot's target: crop mode
		 *	scales such a picture up, which the person should hear about
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUpload( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$uri 	= \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'uri' );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$failure = \Nino\Admin\Admin::uploadError( $_FILES['file'] ?? null );
			if( $failure !== null ) {
				\Nino\Http::fail( $request, $failure['status'], $failure['error'], $failure['code'], $failure['params'] );
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				\Nino\Http::fail( $request, 500, 'could not read upload', 'upload_server' );
				return;
			}

			// Said before the picture is touched, and in the terms the limits
			// are documented in - which of them it was is what the person has
			// to know to fix it
			$rejection = \Nino\Images::reject( $bytes );
			if( $rejection !== null ) {
				\Nino\Http::fail( $request, 400, 'the image was refused: '. $rejection['code'], $rejection['code'], $rejection['params'] );
				return;
			}

			$oldFilename	= $slot['filename'] ?? null;
			// A same-format replacement overwrites the old file in place, so
			// what a failed record write has to put back is read before that
			$oldBytes			= is_string( $oldFilename ) === true && $oldFilename !== '' ? \Nino\Images::read( $appData, $oldFilename ) : false;

			$filename = \Nino\Images::process( $appData, $bytes, (int) ( $slot['width'] ?? 0 ), (int) ( $slot['height'] ?? 0 ), ltrim( $uri, '/' ) );

			// The bytes are an image the kernel accepts, so this is gd or a
			// render callback giving up on one - or the file not being written,
			// which process() does not tell apart
			if( $filename === false ) {
				\Nino\Http::fail( $request, 400, 'the image could not be processed', 'image_unreadable' );
				return;
			}

			if( \Nino\Images::setSlotFilename( $appData, $uri, $filename ) === false ) {

				// The record still names the old file, which stays - and the new
				// one is a file nobody references, unless it overwrote the old
				// one in place under the same name: then the old bytes go back
				if( $filename === $oldFilename && is_string( $oldBytes ) === true )
					\Nino\Images::restore( $appData, $filename, $oldBytes );
				else
					\Nino\Images::delete( $appData, $filename );

				\Nino\Http::fail( $request, 500, 'could not save the slot' );
				return;
			}

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::deleteUnlessShared( $appData, $oldFilename );

			$body = [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ), 'bytes' => self::bytes( $appData, $filename ) ];

			// What the picture is shown at, not what it is stored at: a
			// photograph a camera stored on its side is measured upright
			$shown = \Nino\Images::size( $bytes );
			if( $shown !== false ) {
				$body['source']				= [ 'width' => $shown['width'], 'height' => $shown['height'] ];
				$body['belowTarget']	= $shown['width'] < (int) ( $slot['width'] ?? 0 ) || $shown['height'] < (int) ( $slot['height'] ?? 0 );
			}

			\Nino\Http::ok( $request, $body );
		}

		/**
		 *	Save the alt texts of one slot - { uri, alt : { <locale> : text } }.
		 *	Merged per posted language, an empty text removes that language's
		 *	entry (see \Nino\Images::setSlotAlt()), so the panel posts the
		 *	languages that were changed, in one call, with its Save: all of them
		 *	are written or none. An alt text is one short line: more than 250
		 *	characters once it is cleaned, a language the site does not have,
		 *	a value that is not a string and bytes that are not UTF-8 are all
		 *	a 400 and change nothing. Answers the slot's stored map
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiAlt( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data	= \Nino\Admin\Admin::postData();
			$uri 	= \Nino\Admin\Admin::postString( $data, 'uri' );

			if( \Nino\Images::getSlot( $appData, $uri ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$alt = $data['alt'] ?? null;
			if( is_array( $alt ) === false ) {
				\Nino\Http::fail( $request, 400, 'alt must be a list of texts by language' );
				return;
			}

			foreach( $alt as $locale => $value ) {

				if( is_string( $locale ) === false || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown language' );
					return;
				}

				$text = is_string( $value ) === true ? \Nino\Images::cleanAlt( $value ) : false;
				if( $text === false ) {
					\Nino\Http::fail( $request, 400, 'an alt text is plain text', 'images_alt_plain', [], 'alt' );
					return;
				}

				if( mb_strlen( $text ) > 250 ) {
					\Nino\Http::fail( $request, 400, 'an alt text is at most 250 characters', 'images_alt_long', [ 250 ], 'alt' );
					return;
				}
			}

			if( \Nino\Images::setSlotAlt( $appData, $uri, $alt ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the alt texts' );
				return;
			}

			\Nino\Http::ok( $request, [ 'alt' => \Nino\Images::getSlot( $appData, $uri )['alt'] ?? [] ] );
		}

		/**
		 *	Take a slot's image away: the slot stays, the website shows no
		 *	image there until a new one is uploaded. The record goes first and
		 *	the file second, as in Slots::apiDelete() - a write that does not
		 *	happen leaves the slot and its file as they were, and the worst
		 *	case the other way round is a file nobody references. Only a file
		 *	the slot owns is deleted: its name starts with the slot's own
		 *	deterministic base path, which is what apiUpload() writes, and no
		 *	other slot names it. A name an editor wrote into config.php by
		 *	hand - a picture a template includes literally - is cleared from the
		 *	slot and stays on disk. Answering [ 'filename' => null ] for a
		 *	slot that has none makes the call safe to repeat
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiRemove( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$uri 	= \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'uri' );
			$slot	= \Nino\Images::getSlot( $appData, $uri );

			if( $slot === false ) {
				\Nino\Http::fail( $request, 404, 'unknown slot' );
				return;
			}

			$old = $slot['filename'] ?? null;

			if( is_string( $old ) === false || $old === '' ) {
				\Nino\Http::ok( $request, [ 'filename' => null ] );
				return;
			}

			if( \Nino\Images::setSlotFilename( $appData, $uri, null ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the slot' );
				return;
			}

			if( str_starts_with( $old, ltrim( $uri, '/' ). '.' ) === true )
				\Nino\Images::deleteUnlessShared( $appData, $old );

			\Nino\Http::ok( $request, [ 'filename' => null ] );
		}
	}
}
