<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Elements\Admin	Elements panel: the entries of every element type
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Elements {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Elements					Elements editor: fills the response for the /_admin/elements/*
	 *											routes registered by \Nino\Admin\Admin::init() - list types, list/get/save/
	 *											delete elements within a type. Element *type* creation stays a
	 *											developer-only task (\Nino\Elements::insertElementType), not
	 *											exposed here.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/elements/manage';

		/**
		 *	Everything this panel can be told to allow or refuse in detail
		 *	lives under this prefix, one permission per action:
		 *
		 *	  /_admin/elements/services/insert
		 *	  /_admin/elements/services/update/title
		 *	  /_admin/elements/services/delete
		 *
		 *	Read with \Nino\Auth::checkPermission() like every other
		 *	permission, so a role is described as coarsely or as finely as it
		 *	needs to be: '/_admin/elements/services/*' is all of one type,
		 *	'/_admin/elements/services/update/*' every field of it, and the
		 *	single string above one field alone. Whether any of it applies at
		 *	all is \Nino\Admin\Admin::scoped()'s call - a role holding none
		 *	of these keeps what MANAGE_PERM has always meant.
		 */
		public const string SCOPE = '/_admin/elements/';

		public static function actions(): array {
			return [
				'elements/types' 			=> [ self::class, 'apiTypes' ],
				'elements/list' 			=> [ self::class, 'apiList' ],
				'elements/get' 				=> [ self::class, 'apiGet' ],
				'elements/uploadimage' => [ self::class, 'apiUploadImage' ],
				'elements/save' 			=> [ self::class, 'apiSave' ],
				'elements/delete' 		=> [ self::class, 'apiDelete' ],
			];
		}

		public static function nav(): array {
			return [ 'elements', '/_admin/nav/elements', 20, 'content' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-component-icon lucide-component"><path d="M15.536 11.293a1 1 0 0 0 0 1.414l2.376 2.377a1 1 0 0 0 1.414 0l2.377-2.377a1 1 0 0 0 0-1.414l-2.377-2.377a1 1 0 0 0-1.414 0z"/><path d="M2.297 11.293a1 1 0 0 0 0 1.414l2.377 2.377a1 1 0 0 0 1.414 0l2.377-2.377a1 1 0 0 0 0-1.414L6.088 8.916a1 1 0 0 0-1.414 0z"/><path d="M8.916 17.912a1 1 0 0 0 0 1.415l2.377 2.376a1 1 0 0 0 1.414 0l2.377-2.376a1 1 0 0 0 0-1.415l-2.377-2.376a1 1 0 0 0-1.414 0z"/><path d="M8.916 4.674a1 1 0 0 0 0 1.414l2.377 2.376a1 1 0 0 0 1.414 0l2.377-2.376a1 1 0 0 0 0-1.414l-2.377-2.377a1 1 0 0 0-1.414 0z"/></svg>';
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		// Drill-down: types -> element list -> edit form, each level hiding
		// its parent (see assets/elements.js)
		public static function panes(): array {
			return [ 'elements-types', 'elements-list', 'elements-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
				'/_admin/assets/html-editor.js',
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		// The types these entries are made of are edited on a tab of this
		// same pane (see \Nino\Admin\Panels::collect()): the shape beside the content,
		// each screen with its own permission
		public static function tabs(): array {
			return [ Types::class ];
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'elements/save' 			=> ( ( $data['isNew'] ?? false ) === true ? 'Add Element /' : 'Edit Element /' ). ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ),
				'elements/uploadimage' => 'Upload Element Image /'. ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ). ' '. ( $data['key'] ?? '' ),
				'elements/delete' 		=> 'Delete Element /'. ( $data['type'] ?? '' ). '/'. ( $data['uri'] ?? '' ),
				default 							=> '',
			};
		}

		/**
		 *	List all element types with their model
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiTypes( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$types = [];
			foreach( self::types( $appData ) as $type ) {
				$typeData = self::typeData( $appData, $type );
				$next 		= \Nino\Elements::readAutoincrement( $typeData );
				// A numbered type (set up in /_admin, see Admin::AUTOINCREMENT_PAD)
				// has no uri to ask for - the form shows the number the next
				// element would get instead of a text field
				$types[] = [
					'type' 					=> $type,
					'title' 				=> $typeData['title'] ?? $type,
					'descr' 				=> self::typeDescr( $appData, $type ),
					'model' 				=> $typeData['model'] ?? [],
					// What this account may do here - the form draws itself from
					// it rather than offering controls the save would refuse
					'rights' 				=> self::rights( $appData, $type, $typeData['model'] ?? [] ),
					'autoincrement' => $next !== null,
					'nextUri' 			=> ( $next === null ) ? '' : \Nino\Elements::autoincrementUri( max( $next, \Nino\Elements::autoincrementSeed( $typeData ) ) ),
				];
			}

			\Nino\Http::ok( $request, [ 'types' => $types, 'locales' => \Nino\Locales::getAvailableLocales( $appData ), 'selectedLocale' => \Nino\Admin\Admin::sessionLocale( $appData ) ] );
		}

		/**
		 *	Element count per type, title included - shared by
		 *	\Nino\Modules\Dashboard\Admin::apiSummary
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'type', 'title', 'count' ], ... ]
		 */
		public static function typeCounts( array &$appData ): array {

			$counts = [];
			foreach( self::types( $appData ) as $type ) {
				$typeData = self::typeData( $appData, $type );
				$counts[] = [
					'type' 	=> $type,
					'title' => $typeData['title'] ?? $type,
					'count' => count( \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] ) ),
				];
			}

			return $counts;
		}

		/**
		 *	List all elements of a type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$type = (string) ( \Nino\Admin\Admin::postData()['type'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			$typeData = self::typeData( $appData, $type );
			$model = $typeData['model'] ?? [];
			$locale = (string) ( \Nino\Admin\Admin::postData()['locale'] ?? '' );

			// The table view reads one translation at a time: global fields plus
			// the picked locale's. Without one, fall back to the old "any locale"
			// behaviour, which is what the plain list wants
			$results 	= \Nino\Elements::queryElements( $appData, '/'. $type, [],
				$locale !== '' && \Nino\Locales::verifyLocale( $appData, $locale ) === true ? $locale : '*', [] );

			$columns = self::displayableColumns( $model );

			$elements = [];
			foreach( $results as $element ) {

				$uri 		= basename( $element['.uri'] ?? '' );
				$values = [];

				// Only the fields a cell can actually show, so a type with a
				// large rich-text or image field does not ship it to a list
				// that would never render it.
				//
				// Nested rather than merged into the row: a model is free to
				// name a field "label" or "uri", and flattening let such a field
				// overwrite the row's own identity
				foreach( $columns as $key )
					$values[$key] = $element[$key] ?? null;

				$elements[] = [
					'uri' 		=> $uri,
					'label' 	=> self::label( $model, $element, $uri ),
					'values' 	=> $values,
				];
			}

			\Nino\Http::ok( $request, [
				'model' 		=> $model,
				'columns' 	=> $columns,
				'elements' 	=> $elements,
				'total' 		=> count( $elements ),
			] );
		}

		/**
		 *	Get one element's global fields + per-locale field values, for editing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				\Nino\Http::fail( $request, 404, 'unknown type or element' );
				return;
			}

			$typeData = self::typeData( $appData, $type );
			$model = $typeData['model'] ?? [];
			[ $globalKeys, $localeKeys ] = self::splitModel( $model );

			$global 	= [];
			$locales	= [];
			$found		= false;

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {

				$element = \Nino\Elements::getElement( $appData, '/'. $type. '/'. $uri, $locale, [] );

				if( $element === [] )
					continue;

				$found = true;

				foreach( $globalKeys as $key )
					$global[$key] = $element[$key] ?? null;

				$locales[$locale] = [];
				foreach( $localeKeys as $key )
					$locales[$locale][$key] = $element[$key] ?? null;
			}

			if( $found === false ) {
				\Nino\Http::fail( $request, 404, 'element not found' );
				return;
			}

			\Nino\Http::ok( $request, [
				'model' 	=> $model,
				'global' 	=> $global,
				'locales' => $locales,
				'raw' 		=> self::rawBuckets( $typeData, $uri ),
			] );
		}

		/**
		 *	Every storage bucket that holds this element, verbatim - the
		 *	'*' bucket with its global fields and one bucket per locale. A
		 *	read-only view for whoever manages a type: what the form shows
		 *	is already the resolved value, this is what is actually on disk
		 *
		 *	@param		array 		$typeData			The type file's content
		 *	@param		string		$uri					Element uri within the type
		 *
		 *	@return 	array										[ bucket => stored entry ]
		 */
		public static function rawBuckets( array $typeData, string $uri ): array {

			$raw = [];

			foreach( $typeData as $bucket => $entries ) {

				if( $bucket === 'model' || is_array( $entries ) === false )
					continue;

				if( array_key_exists( $uri, $entries ) === true )
					$raw[(string) $bucket] = $entries[$uri];
			}

			return $raw;
		}

		/**
		 *	Upload, process and store a new image for one "image"-typed model
		 *	field of an already-saved element. Committed immediately (not tied
		 *	to the form's "Speichern" button, unlike every other field). Stored
		 *	at a deterministic path (elements/<type>/<uri>, disambiguated with
		 *	the key/locale only where a real collision is possible), so a
		 *	replace overwrites in place - the rare leftover (the output format
		 *	itself changed, eg. jpeg -> png) is cleaned up once the new file is
		 *	safely written and the element record actually updated
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiUploadImage( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$key 			= (string) ( $data['key'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type, uri or locale' );
				return;
			}

			$model = self::typeData( $appData, $type )['model'] ?? [];
			if( ( $model[$key]['type'] ?? '' ) !== 'image' ) {
				\Nino\Http::fail( $request, 400, 'not an image field' );
				return;
			}

			// An upload is this field's value being changed, only over a
			// different route than apiSave() - the same permission decides it
			if( self::mayUpdate( $appData, $type, $key ) === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed to change the field(s) '. $key );
				return;
			}

			$elementUri = '/'. $type. '/'. $uri;
			$isLocaleField = ( $model[$key]['locale'] ?? false ) === true;

			if( \Nino\Elements::getElement( $appData, $elementUri, '*' ) === false ) {
				\Nino\Http::fail( $request, 404, 'element not found - save it once before uploading an image' );
				return;
			}

			if( isset( $_FILES['file'] ) === false || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
				\Nino\Http::fail( $request, 400, 'no file uploaded' );
				return;
			}

			$bytes = file_get_contents( $_FILES['file']['tmp_name'] );
			if( $bytes === false ) {
				\Nino\Http::fail( $request, 400, 'could not read upload' );
				return;
			}

			// The value to overwrite, per the field's own scope (matches how
			// Admin::_writeElementData() itself routes a save for this key)
			$oldElement 	= \Nino\Elements::getElement( $appData, $elementUri, $isLocaleField ? $locale : '*' );
			$oldFilename 	= is_array( $oldElement ) ? ( $oldElement[$key] ?? null ) : null;
			$oldBytes 		= is_string( $oldFilename ) && $oldFilename !== '' ? \Nino\Images::read( $appData, $oldFilename ) : false;

			$width 		= (int) ( $model[$key]['width'] ?? 0 );
			$height 	= (int) ( $model[$key]['height'] ?? 0 );

			// Deterministic path - re-uploading the same slot overwrites in place,
			// so there's nothing to clean up beyond the rare case where the output
			// extension itself changes (handled below via the old/new filename diff).
			// Only disambiguated with "-key"/"-locale" where a collision is actually
			// possible (more than one image field on this type, or a per-locale one),
			// so the common case matches exactly "elements/<type>/<uri>.<ext>"
			$imageFieldCount = 0;
			foreach( $model as $modelField )
				if( ( $modelField['type'] ?? '' ) === 'image' )
					$imageFieldCount++;

			$basePath = 'elements/'. $type. '/'. $uri;
			if( $imageFieldCount > 1 )
				$basePath .= '-'. $key;
			if( $isLocaleField === true )
				$basePath .= '-'. $locale;

			$filename = \Nino\Images::process( $appData, $bytes, $width, $height, $basePath );

			if( $filename === false ) {
				\Nino\Http::fail( $request, 400, 'invalid or oversized image' );
				return;
			}

			$result = \Nino\Elements::updateElement( $appData, $elementUri, [ $key => $filename ], $locale );

			if( is_array( $result ) === false ) {
				// process() writes deterministic paths. A same-format replacement
				// therefore already overwrote the old file before the metadata update
				// above could be vetoed; restore that snapshot instead of deleting the
				// filename the unchanged element record still references.
				if( $filename === $oldFilename && is_string( $oldBytes ) === true )
					\Nino\Images::restore( $appData, $filename, $oldBytes );
				else
					\Nino\Images::delete( $appData, $filename );
				\Nino\Http::fail( $request, 400, 'save failed' );
				return;
			}

			if( is_string( $oldFilename ) === true && $oldFilename !== '' && $oldFilename !== $filename )
				\Nino\Images::delete( $appData, $oldFilename );

			\Nino\Http::ok( $request, [ 'filename' => $filename, 'url' => \Nino\Images::getUrl( $appData, $filename ) ] );
		}

		/**
		 *	Whether this account may add an element of a type - see SCOPE
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Element type (eg. "services")
		 *
		 *	@return 	bool
		 */
		public static function mayInsert( array &$appData, string $type ): bool {
			return \Nino\Admin\Admin::scoped( $appData, self::SCOPE, self::SCOPE. $type. '/insert' );
		}

		/**
		 *	Whether this account may change one field of a type - see SCOPE
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Element type
		 *	@param		string		$field				A model field key
		 *
		 *	@return 	bool
		 */
		public static function mayUpdate( array &$appData, string $type, string $field ): bool {
			return \Nino\Admin\Admin::scoped( $appData, self::SCOPE, self::SCOPE. $type. '/update/'. $field );
		}

		/**
		 *	Whether this account may delete an element of a type - see SCOPE
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Element type
		 *
		 *	@return 	bool
		 */
		public static function mayDelete( array &$appData, string $type ): bool {
			return \Nino\Admin\Admin::scoped( $appData, self::SCOPE, self::SCOPE. $type. '/delete' );
		}

		/**
		 *	What this account may do with one type, in the shape the form
		 *	renders itself from: whether it may add and delete elements at
		 *	all, and which fields it may change.
		 *
		 *	Sent with the types themselves (see apiTypes()) so the form can be
		 *	honest before anything is typed - a field nobody may change is
		 *	shown read-only rather than accepting edits the save would refuse.
		 *	The refusal itself stays server-side; this is only what the screen
		 *	needs to draw itself.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Element type
		 *	@param		array 		$model				That type's model
		 *
		 *	@return 	array										[ 'insert', 'delete', 'update' => [ field => bool ] ]
		 */
		public static function rights( array &$appData, string $type, array $model ): array {

			$update = [];
			foreach( array_keys( $model ) as $field )
				$update[$field] = self::mayUpdate( $appData, $type, (string) $field );

			return [
				'insert' => self::mayInsert( $appData, $type ),
				'delete' => self::mayDelete( $appData, $type ),
				'update' => $update,
			];
		}

		/**
		 *	Insert or update an element
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$type 		= (string) ( $data['type'] ?? '' );
			$uri 			= (string) ( $data['uri'] ?? '' );
			$locale 	= (string) ( $data['locale'] ?? '' );
			$isNew 		= ( $data['isNew'] ?? false ) === true;
			$fields 	= is_array( $data['fields'] ?? null ) ? $data['fields'] : [];

			// A numbered type assigns the uri, so an insert into one arrives
			// without one and the kernel allocates it under the type file's lock
			// (see Admin::AUTOINCREMENT_PAD). An update always names its
			// element - by then it has a uri like any other.
			$numbered = $isNew === true && $uri === ''
				&& in_array( $type, self::types( $appData ), true ) === true
				&& \Nino\Elements::readAutoincrement( self::typeData( $appData, $type ) ) !== null;

			if( in_array( $type, self::types( $appData ), true ) === false || ( $uri === '' && $numbered === false ) || str_contains( $uri, '/' ) === true || ( $isNew === true && $numbered === false && self::_validNewUri( $uri ) === false ) || \Nino\Locales::verifyLocale( $appData, $locale ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type, uri or locale' );
				return;
			}

			if( $isNew === true && self::mayInsert( $appData, $type ) === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed to add elements of this type' );
				return;
			}

			// Field by field, and refused rather than filtered: dropping the
			// fields the account may not write would answer 200 to a form that
			// then shows the old value back, which reads as the save having
			// failed silently. Naming them says what happened
			$refused = [];
			foreach( array_keys( $fields ) as $field )
				if( self::mayUpdate( $appData, $type, (string) $field ) === false )
					$refused[] = (string) $field;

			if( $refused !== [] ) {
				\Nino\Http::fail( $request, 403, 'not allowed to change the field(s) '. implode( ', ', $refused ) );
				return;
			}

			// A model field with 'html' => true gets the same whitelist-tag sanitizing Text
			// uses - never trust the client's html
			$model = self::typeData( $appData, $type )['model'] ?? [];
			foreach( $fields as $key => $value )
				if( is_string( $value ) === true && ( $model[$key]['html'] ?? false ) === true )
					$fields[$key] = \Nino\Html::sanitizeHtml( $value );

			// Required-field enforcement lives in the kernel itself
			// (Admin::insertElement()/updateElement()) - $errorMsg below
			// already captures its "Missing required element key '...'" message
			$errorMsg = null;
			set_error_handler( function( int $errno, string $errstr ) use ( &$errorMsg ): bool { $errorMsg = $errstr; return true; } );

			$result = ( $isNew === true )
				? \Nino\Elements::insertElement( $appData, '/'. $type. '/'. $uri, $fields, $locale )
				: \Nino\Elements::updateElement( $appData, '/'. $type. '/'. $uri, $fields, $locale );

			restore_error_handler();

			if( is_array( $result ) === false ) {
				\Nino\Http::fail( $request, 400, $errorMsg ?? 'save failed' );
				return;
			}

			// A numbered type's counter has just moved on, so the form's "saving
			// creates /type/00007" promise would otherwise still name the number
			// this very save consumed
			$next = \Nino\Elements::getAutoincrement( $appData, '/'. $type );

			\Nino\Http::ok( $request, [
				'element' => $result,
				'nextUri' => ( $next === null ) ? '' : \Nino\Elements::autoincrementUri( $next ),
			] );
		}

		/**
		 *	Delete an element (all locales)
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data = \Nino\Admin\Admin::postData();
			$type = (string) ( $data['type'] ?? '' );
			$uri	= (string) ( $data['uri'] ?? '' );

			if( in_array( $type, self::types( $appData ), true ) === false || $uri === '' || str_contains( $uri, '/' ) === true ) {
				\Nino\Http::fail( $request, 400, 'invalid type or uri' );
				return;
			}

			if( self::mayDelete( $appData, $type ) === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed to delete elements of this type' );
				return;
			}

			$elementUri = '/'. $type. '/'. $uri;
			$imageFilenames = self::imageFilenames( $appData, $type, $elementUri );

			$errorMsg = null;
			set_error_handler( function( int $errno, string $errstr ) use ( &$errorMsg ): bool { $errorMsg = $errstr; return true; } );

			$deleted = \Nino\Elements::deleteElement( $appData, $elementUri, '*' );

			restore_error_handler();

			if( $errorMsg !== null || $deleted !== true ) {
				\Nino\Http::fail( $request, 409, $errorMsg ?? 'delete vetoed' );
				return;
			}

			// Only once the element itself is actually gone - an "image" field's
			// value is just a filename reference, deleting the element wouldn't
			// otherwise touch the file it points to at all
			foreach( $imageFilenames as $filename )
				\Nino\Images::delete( $appData, $filename );

			\Nino\Http::ok( $request );
		}

		/**
		 *	Every uploaded image filename currently stored on an element - a
		 *	locale-scoped image field can differ per locale, so all of them
		 *	need checking, not just the global bucket
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Element type
		 *	@param		string		$elementUri		Full element uri (eg. "/portfolio/item1")
		 *
		 *	@return 	array										Distinct, non-empty filenames
		 */
		public static function imageFilenames( array &$appData, string $type, string $elementUri ): array {

			$model = self::typeData( $appData, $type )['model'] ?? [];
			$imageKeys = [];
			foreach( $model as $key => $field )
				if( ( $field['type'] ?? '' ) === 'image' )
					$imageKeys[] = $key;

			if( $imageKeys === [] )
				return [];

			$filenames = [];

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale ) {
				$element = \Nino\Elements::getElement( $appData, $elementUri, $locale, [] );
				foreach( $imageKeys as $key )
					if( is_string( $element[$key] ?? null ) === true && $element[$key] !== '' )
						$filenames[$element[$key]] = true;
			}

			return array_keys( $filenames );
		}

		/**
		 *	Return all element type uris (bare names, eg. "services") found on disk
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										List of type names
		 */
		public static function types( array &$appData ): array {

			$types = [];
			foreach( glob( \Nino\Filesystem::path( $appData, '/elements' ). '/*.php' ) ?: [] as $file )
				$types[] = basename( $file, '.php' );

			sort( $types );

			return $types;
		}

		/**
		 *	Read a type's data
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Type name (eg. "services")
		 *
		 *	@return 	array										Type data ('key' => [ 'type', 'locale', ... ])
		 */
		public static function typeData( array &$appData, string $type ): array {
			return \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
		}

		/**
		 *		Pick a human readable description for an type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$type					Type name (eg. "services")
		 *
		 *	@return 	string									Readable description
		 */
		private static function typeDescr( array &$appData, string $type ): string {

			$elements = \Nino\Elements::queryElements( $appData, '/'. $type, [], '*', [] );
			$info = '('. count( $elements ). ') ';

			foreach( $elements as $elData )
				$info .= str_replace( '/'.$type, '', $elData['.uri'] ). ', ';

			$info = ( strlen( $info ) > 150 ) ? substr( $info, 0, 150 ).' ..' : $info;

			return (string) $info;
		}

		/**
		 *	The model field keys a table cell can show: everything except an
		 *	image (a file), an array (a structure) and a rich-text string (its
		 *	value is markup, and a cell renders text).
		 *
		 *	Mirrored by Nino.adminUi.tableModel.isDisplayable() on the frontend -
		 *	the server decides what to send, the client what to draw, and the two
		 *	have to agree on the same rule.
		 *
		 *	@param		array 		$model				Type model definition
		 *
		 *	@return 	array										Field keys, in model order
		 */
		public static function displayableColumns( array $model ): array {

			$keys = [];

			foreach( $model as $key => $field ) {

				$type = (string) ( $field['type'] ?? '' );

				if( in_array( $type, [ 'image', 'array' ], true ) === true )
					continue;

				if( $type === 'string' && ( $field['html'] ?? false ) === true )
					continue;

				$keys[] = (string) $key;
			}

			return $keys;
		}

		/**
		 *	Pick a human readable label for an element (for the list view):
		 *	its 'title' field, or its own uri.
		 *
		 *	Deliberately only 'title'. Guessing - 'label'/'name', then the
		 *	first string field in model order - meant the list showed whichever
		 *	field happened to come first, so two types with the same content
		 *	labelled their rows differently and reordering a model silently
		 *	relabelled every row in it. A uri is not as pretty, but it is the
		 *	thing the row actually is, and it matches how the type overview
		 *	already lists its elements (see typeDescr()).
		 *
		 *	@param		array 		$model				Type model definition (unused, kept for call-site symmetry)
		 *	@param		array 		$element			Resolved element data
		 *	@param		string		$uri					Element uri, used when there is no title
		 *
		 *	@return 	string									Label
		 */
		private static function label( array $model, array $element, string $uri ): string {

			if( isset( $element['title'] ) === true && is_scalar( $element['title'] ) === true && (string) $element['title'] !== '' )
				return (string) $element['title'];

			return '/'. $uri;
		}

		/**
		 *	Split a model into its global-only and locale-specific field keys
		 *
		 *	@param		array 		$model				Type model definition
		 *
		 *	@return 	array										[ globalKeys[], localeKeys[] ]
		 */
		private static function splitModel( array $model ): array {

			$globalKeys = [];
			$localeKeys = [];

			foreach( $model as $key => $field )
				if( ( $field['locale'] ?? false ) === true )
					$localeKeys[] = $key;
				else
					$globalKeys[] = $key;

			return [ $globalKeys, $localeKeys ];
		}

		// A new element uri becomes both an array key and (for image fields) a
		// filename component, so it is held to the slug syntax the form's own
		// hint promises. Only new ones: reading and deleting never re-validate,
		// so a uri written by hand stays reachable.
		private static function _validNewUri( string $uri ): bool {
			return preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $uri ) === 1;
		}
	}
}
