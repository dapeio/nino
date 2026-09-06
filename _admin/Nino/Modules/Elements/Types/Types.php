<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Elements\Types	Element Types tab: the schema of every element type
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Elements {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Dev								Manage element types (elements/<type>.php): title + model
	 *												(field definitions) only - never touches a type's actual
	 *												content ('*' and locale buckets), so a save here never puts
	 *												existing elements at risk. Deleting a type does, and is
	 *												offered all the same (see apiDelete()): doing it by hand
	 *												means deleting the same file with none of the checks and
	 *												none of the log line.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Types {

		public const string MANAGE_PERM = '/_admin/types/manage';

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public const array FIELD_TYPES = [ 'string', 'integer', 'double', 'boolean', 'array', 'date', 'datetime', 'image', 'element' ];

		/**
		 *	This module's action map, merged into \Nino\Admin\Admin::handlePost()'s dispatch
		 *
		 *	@return 	array
		 */
		public static function actions(): array {
			return [
				'types/list' 	=> [ self::class, 'apiList' ],
				'types/get' 		=> [ self::class, 'apiGet' ],
				'types/save' 	=> [ self::class, 'apiSave' ],
				'types/create' => [ self::class, 'apiCreate' ],
				'types/delete' => [ self::class, 'apiDelete' ],
			];
		}

		/**
		 *	A Dashboard tile - see \Nino\Admin\Panels
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function summary( array &$appData ): array {
			return [ 'value' => count( self::summaries( $appData ) ), 'label' => '/_admin/dashboard/label/types' ];
		}

		/**
		 *	A tab of the Elements pane (see \Nino\Modules\Elements\Admin::tabs()) - the uri names the
		 *	hash prefix and the script, the weight orders the strip, and the
		 *	group only says where the permission is listed
		 *
		 *	@return 	array										[ uri, label, weight, group ]
		 */
		public static function nav(): array {
			return [ 'types', '/_admin/nav/types', 10, 'structure' ];
		}

		public static function panes(): array {
			return [ 'types-list', 'types-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/types.js' ) ];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		/**
		 *	A type uri is always a single flat filename segment (elements/<type>.php,
		 *	no nesting) - reject anything else outright, same defensive spirit as
		 *	\Nino\Modules\Images\Admin::process()'s basePath check, before it ever reaches a filesystem call
		 *
		 *	@param		string		$typeUri
		 *
		 *	@return 	bool
		 */
		private static function isValidTypeUri( string $typeUri ): bool {
			return preg_match( '/^[a-z][a-z0-9_-]*$/', $typeUri ) === 1;
		}

		/**
		 *	Every element type with its title and field count, sorted by
		 *	uri - shared by apiList() and \Nino\Modules\Dashboard\Admin::apiSummary()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'uri', 'title', 'fieldCount' ], ... ]
		 */
		public static function summaries( array &$appData ): array {

			$types = [];
			foreach( glob( \Nino\Filesystem::path( $appData, '/elements' ). '/*.php' ) ?: [] as $file ) {

				$typeUri 	= basename( $file, '.php' );
				$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', [] );

				$types[] = [
					'uri' 				=> $typeUri,
					'title' 			=> $typeData['title'] ?? $typeUri,
					'fieldCount' 	=> count( $typeData['model'] ?? [] ),
				];
			}

			usort( $types, fn( array $a, array $b ) => strcmp( $a['uri'], $b['uri'] ) );

			return $types;
		}

		/**
		 *	List every element type with its title and field count
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'types' => self::summaries( $appData ), 'fieldTypes' => self::FIELD_TYPES ] );
		}

		/**
		 *	Read one type's title + model
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiGet( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$typeUri = (string) ( \Nino\Admin\Admin::postData()['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type uri' );
				return;
			}

			$typeData = \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', false );

			if( $typeData === false ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			\Nino\Http::ok( $request, [
				'uri' 					=> $typeUri,
				'title' 				=> $typeData['title'] ?? $typeUri,
				'model' 				=> $typeData['model'] ?? [],
				// The uri form of the type: named by whoever adds an element, or
				// numbered by the type itself. 'next' is what the following
				// element would be called, so the form can show it rather than
				// describe it.
				'autoincrement' => \Nino\Elements::readAutoincrement( $typeData ) !== null,
				'next' 					=> \Nino\Elements::autoincrementUri( \Nino\Elements::readAutoincrement( $typeData ) ?? \Nino\Elements::autoincrementSeed( $typeData ) ),
				// What deleting this type would cost, sent with the type itself
				// so the form can name it before it is clicked rather than after:
				// how many elements go with the file, and which other types'
				// element fields point here and therefore refuse the deletion
				// outright (see apiDelete())
				'elements' 			=> count( \Nino\Elements::queryElements( $appData, '/'. $typeUri, [], '*', [] ) ),
				'referencedBy' 	=> self::referencedBy( $appData, $typeUri ),
			] );
		}

		/**
		 *	Validate a posted model definition, dropping anything malformed -
		 *	same rules as \Nino\Modules\Elements\Admin::insertElementType(), plus width/height for
		 *	image fields, maxlength for string fields, the referenced type for
		 *	element fields, a fixed unit suffix for every type but boolean/
		 *	image/element, and a plain string list for options
		 *
		 *	@param		mixed			$model				Posted model, expected array<string,array>
		 *
		 *	@return 	array										Cleaned model
		 */
		private static function cleanModel( mixed $model ): array {

			if( is_array( $model ) === false )
				return [];

			$clean = [];

			foreach( $model as $key => $data ) {

				$key = trim( (string) $key );

				if( $key === '' || is_array( $data ) === false || in_array( $data['type'] ?? '', self::FIELD_TYPES, true ) === false )
					continue;

				$field = [ 'type' => $data['type'] ];

				if( ( $data['locale'] ?? false ) === true )
					$field['locale'] = true;

				if( $data['type'] === 'string' && ( $data['html'] ?? false ) === true )
					$field['html'] = true;

				// Never on an image, whatever was posted: its file is uploaded
				// separately, once the element already exists and has a uri to
				// attach it to (see assets/elements.js's image branch), so a
				// required image could not be satisfied by the very save that
				// creates the element - the type would be impossible to add an
				// element to at all. The frontend stops offering the checkbox
				// for image fields (see assets/elementtypes.js), and this drops
				// it from a hand-written or older model on the next save
				if( $data['type'] !== 'image' && ( $data['required'] ?? false ) === true )
					$field['required'] = true;

				if( $data['type'] === 'image' ) {
					$field['width'] 	= max( 1, (int) ( $data['width'] ?? 0 ) );
					$field['height'] 	= max( 1, (int) ( $data['height'] ?? 0 ) );
				}

				// Which type an element reference may point at. Kept as posted
				// rather than checked against the types on disk here - this
				// method has no $appData; apiSave()/apiCreate() reject an
				// unknown one outright (see _unknownReferencedType()) so the
				// author gets told, instead of the field silently vanishing
				if( $data['type'] === 'element' ) {

					$field['elementType'] = trim( (string) ( $data['elementType'] ?? '' ) );

					// The type editor asks whether the reference is a list and
					// where it stops as two controls; the model carries one int.
					// Folded here rather than client-side so a hand-written or
					// api-posted model goes through the same rule: the key is
					// written only when the list is actually wanted, because its
					// mere presence is what makes the field multi-valued
					// (\Nino\Elements::isMultiElement()) - an absent key is the
					// single reference every existing type still means
					if( ( $data['multiple'] ?? false ) === true )
						$field['multiple'] = max( 0, (int) ( $data['multipleMax'] ?? 0 ) );
				}

				// Only rendered for a string field (elements.js's maxlength+counter and
				// html-editor branches) - 0/absent falls back to DEFAULT_MAXLENGTH client-side
				if( $data['type'] === 'string' ) {
					$maxlength = (int) ( $data['maxlength'] ?? 0 );
					if( $maxlength > 0 )
						$field['maxlength'] = $maxlength;
				}

				// A fixed unit/label shown next to the input (eg. a "price" field's
				// "€") - elements.js applies it to every type except boolean,
				// image and element, none of which render an input a unit could
				// sit next to
				if( in_array( $data['type'], [ 'boolean', 'image', 'element' ], true ) === false ) {
					$suffix = trim( (string) ( $data['suffix'] ?? '' ) );
					if( $suffix !== '' )
						$field['suffix'] = $suffix;
				}

				// Never on an element reference: its choices are the referenced
				// type's elements, and a second fixed list next to them would
				// be two selects fighting over the same value (elements.js
				// renders the options list ahead of the type's own branches)
				if( $data['type'] !== 'element' && is_array( $data['options'] ?? null ) === true && count( $data['options'] ) > 0 )
					$field['options'] = array_values( array_map( 'strval', $data['options'] ) );

				$clean[$key] = $field;
			}

			return $clean;
		}

		/**
		 *	The first element field in a cleaned model whose referenced type
		 *	does not exist on disk, as a ready-made error message - or null
		 *	when every reference resolves.
		 *
		 *	Checked before the type file is written rather than silently
		 *	dropping the field: a reference nobody can satisfy would render as
		 *	an empty, permanently unusable select in both element forms, and
		 *	the author has no way of telling that from "this type simply has
		 *	no elements yet". A type deleted *later* is a different case and
		 *	stays tolerated - the forms show the dangling value rather than
		 *	discard it (see assets/elements.js's element branch).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$model				Model as returned by cleanModel()
		 *
		 *	@return 	string|null								Error message, or null when every reference resolves
		 */
		private static function _unknownReferencedType( array &$appData, array $model ): ?string {

			$known = array_column( self::summaries( $appData ), 'uri' );

			foreach( $model as $key => $field ) {

				if( ( $field['type'] ?? '' ) !== 'element' )
					continue;

				$referenced = (string) ( $field['elementType'] ?? '' );

				if( $referenced === '' )
					return 'field "'. $key. '" is an element reference without a type to point at';

				if( in_array( $referenced, $known, true ) === false )
					return 'field "'. $key. '" references the unknown element type "'. $referenced. '"';
			}

			return null;
		}

		/**
		 *	Save an existing type's title + model. '*' and every locale
		 *	bucket (the type's actual content) are read back and written
		 *	right along with them - untouched, EXCEPT for a field whose
		 *	locale/global shape just changed, whose stored value(s) are
		 *	migrated via _migrateFieldShape() the same way \Nino\Modules\Text\Admin::apiSave()
		 *	already migrates a text key's value(s) on the same kind of
		 *	change. Without that, a field switched to global keeps its old
		 *	per-locale value(s) sitting in the locale buckets - and since
		 *	_cacheElement() merges locale data over '*' data (so a locale
		 *	can legitimately override a global default), that stale locale
		 *	value would keep winning over the new global one forever.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$typeUri = (string) ( $data['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type uri' );
				return;
			}

			$danglingReference = self::_unknownReferencedType( $appData, self::cleanModel( $data['model'] ?? [] ) );

			if( $danglingReference !== null ) {
				\Nino\Http::fail( $request, 400, $danglingReference );
				return;
			}

			// Locking and re-reading through mutate() means the "unknown type"
			// 404 below can return null from inside the callback and let
			// mutate() release the lock itself - unlike the manual lock/read/
			// write this used to be, there is no early-return branch left that
			// could walk away holding the lock (see Filesystem::mutate()'s
			// docblock)
			$notFound 			= false;
			$resultTypeData	= null;

			$written = \Nino\Filesystem::mutate( $appData, '/elements/'. $typeUri. '.php', function( mixed $typeData ) use ( $appData, $data, $typeUri, &$notFound, &$resultTypeData ): ?array {

				if( $typeData === false ) {
					$notFound = true;
					return null;
				}

				$title 		= trim( (string) ( $data['title'] ?? '' ) );
				$oldModel = $typeData['model'] ?? [];
				$newModel = self::cleanModel( $data['model'] ?? [] );

				foreach( $newModel as $key => $field ) {
					if( array_key_exists( $key, $oldModel ) === false )
						continue;
					$wasLocale = ( $oldModel[$key]['locale'] ?? false ) === true;
					$isLocale 	= ( $field['locale'] ?? false ) === true;
					if( $wasLocale !== $isLocale )
						self::_migrateFieldShape( $appData, $typeData, $key, $isLocale );
				}

				$typeData['title'] = ( $title !== '' ) ? $title : ( $typeData['title'] ?? $typeUri );
				$typeData['model'] = $newModel;

				// Switching numbering on seeds the counter past every element this
				// type already has, so turning it on later - on a type that was
				// named by hand until now - cannot collide with one of them.
				// Switching it off drops the counter: the type stops numbering,
				// and re-enabling it seeds again from what is there. Set inside
				// this same mutation as the model, so the file never holds one
				// half of a save.
				if( ( $data['autoincrement'] ?? false ) === true ) {
					if( \Nino\Elements::readAutoincrement( $typeData ) === null )
						$typeData['autoincrement'] = \Nino\Elements::autoincrementSeed( $typeData );
				} else {
					unset( $typeData['autoincrement'] );
				}

				$resultTypeData = $typeData;

				return $typeData;
			}, false );

			if( $notFound === true ) {
				\Nino\Http::fail( $request, 404, 'unknown type' );
				return;
			}

			if( $written === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the type file' );
				return;
			}

			\Nino\Http::ok( $request, [
				'uri' 					=> $typeUri,
				'title' 				=> $resultTypeData['title'],
				'model' 				=> $resultTypeData['model'],
				'autoincrement' => \Nino\Elements::readAutoincrement( $resultTypeData ) !== null,
			] );
		}

		/**
		 *	Move one field's stored value(s), across every element of this
		 *	type, between the '*' bucket and every locale bucket - called
		 *	from apiSave() when that field's model 'locale' flag just
		 *	changed. Same migrate-don't-discard reasoning as
		 *	\Nino\Modules\Text\Admin::_convertShape(): global -> per-locale copies the current
		 *	'*' value into every locale; per-locale -> global keeps the
		 *	native locale's value (falling back to the first non-empty one)
		 *	and removes the now-stale per-locale copies so they can't keep
		 *	shadowing the new global value in _cacheElement()'s merge
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$typeData		(reference) This type's file content ('*' + locale buckets)
		 *	@param		string		$key					The field key whose shape just changed
		 *	@param		bool			$toLocale			Target shape (true = per-locale, false = global)
		 *
		 *	@return 	void
		 */
		private static function _migrateFieldShape( array &$appData, array &$typeData, string $key, bool $toLocale ): void {

			$locales = \Nino\Locales::getAvailableLocales( $appData );

			$elementUris = array_keys( $typeData['*'] ?? [] );
			foreach( $locales as $locale )
				$elementUris = array_merge( $elementUris, array_keys( $typeData[$locale] ?? [] ) );
			$elementUris = array_unique( $elementUris );

			if( $toLocale === true ) {

				foreach( $elementUris as $elementUri ) {

					if( isset( $typeData['*'][$elementUri][$key] ) === false )
						continue;

					foreach( $locales as $locale ) {
						$typeData[$locale][$elementUri] = $typeData[$locale][$elementUri] ?? [];
						$typeData[$locale][$elementUri][$key] = $typeData['*'][$elementUri][$key];
					}

					unset( $typeData['*'][$elementUri][$key] );
				}

			} else {

				$native = \Nino\Locales::getNativeLocale( $appData );

				foreach( $elementUris as $elementUri ) {

					$value = null;

					if( isset( $typeData[$native][$elementUri][$key] ) === true )
						$value = $typeData[$native][$elementUri][$key];
					else
						foreach( $locales as $locale )
							if( isset( $typeData[$locale][$elementUri][$key] ) === true && $typeData[$locale][$elementUri][$key] !== '' ) {
								$value = $typeData[$locale][$elementUri][$key];
								break;
							}

					if( $value !== null )
						$typeData['*'][$elementUri][$key] = $value;

					foreach( $locales as $locale )
						unset( $typeData[$locale][$elementUri][$key] );
				}
			}
		}

		/**
		 *	Create a brand new, empty element type
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$typeUri 	= (string) ( $data['uri'] ?? '' );

			if( self::isValidTypeUri( $typeUri ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid type uri' );
				return;
			}

			if( \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', '' ) !== '' ) {
				\Nino\Http::fail( $request, 409, 'type already exists' );
				return;
			}

			$title = trim( (string) ( $data['title'] ?? '' ) );
			$model = self::cleanModel( $data['model'] ?? [] );

			$danglingReference = self::_unknownReferencedType( $appData, $model );

			if( $danglingReference !== null ) {
				\Nino\Http::fail( $request, 400, $danglingReference );
				return;
			}

			$typeData = [
				'title' 	=> ( $title !== '' ) ? $title : $typeUri,
				'model' 	=> $model,
				'*' 			=> [ '*' => [] ],
			];

			// A type created numbered starts at the first number - there is
			// nothing yet to seed past (see \Nino\Modules\Elements\Admin::AUTOINCREMENT_PAD)
			if( ( $data['autoincrement'] ?? false ) === true )
				$typeData['autoincrement'] = 1;

			// Checked, same as apiSave() does: answering 200 on a failed write
			// leaves the frontend believing the type exists (elementtypes.js
			// clears _isNew and adopts the uri), so its next Save posts
			// against a file that was never created and comes back 404
			if( \Nino\Filesystem::putFileContent( $appData, '/elements/'. $typeUri. '.php', $typeData ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the type file' );
				return;
			}

			\Nino\Http::ok( $request, [
				'uri' 					=> $typeUri,
				'title' 				=> $typeData['title'],
				'model' 				=> $typeData['model'],
				'autoincrement' => isset( $typeData['autoincrement'] ),
			] );
		}

		/**
		 *	The activity-log line for a mutating action - see
		 *	\Nino\Admin\Admin::_logAction()
		 *
		 *	@param		string		$action				The dispatched action name
		 *	@param		array			$data					The posted data
		 *
		 *	@return 	string
		 */
		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'types/save' 		=> 'Edit Element Type /'. ( $data['uri'] ?? '' ),
				'types/create' 	=> 'Add Element Type /'. ( $data['uri'] ?? '' ),
				// Not types/delete: what makes that line worth reading is how many
				// elements went with the type, and this hook only ever sees the
				// posted body - uri and confirm. apiDelete() writes its own line
				// once it knows the counts (see the record() call there)
				'types/delete' 	=> '',
				default 				=> '',
			};
		}

		/**
		 *	Delete an element type: its elements, the images those elements
		 *	own, and the type file itself.
		 *
		 *	This destroys real content and is meant to. Doing it by hand means
		 *	deleting the same file with a shell, which is neither safer nor
		 *	reversible - it just moves the risk somewhere with no checks, no
		 *	confirmation and no log line. So the checks live here instead:
		 *
		 *	  - the operator types the type's uri to confirm, which is what
		 *	    makes this different from every other button in the workbench:
		 *	    a mis-click cannot reach it
		 *	  - a type another type's element field points at is refused, by
		 *	    name, because deleting it would leave those references pointing
		 *	    at nothing and no later save could tell
		 *	  - the images the elements own go with them, the same way deleting
		 *	    a single element takes its pictures (see the panel's
		 *	    imageFilenames()) - an orphaned upload is not a safety net,
		 *	    it is a file nobody can find again
		 *	  - the line it writes to the activity log names the type and how
		 *	    many elements went with it
		 *
		 *	What it does not do is call \Nino\Elements::deleteElement() per
		 *	element, so a project's '/nino/elements/delete<type>' callback never
		 *	gets to veto one of them. Deliberately: a veto on the fifth of ten
		 *	elements would arrive with four already destroyed and nothing to put
		 *	them back. Removing the type is one structural act on one file, and
		 *	either all of it happens or none of it does.
		 *
		 *	What it is not is a backup. The daily snapshot the workbench takes
		 *	on the first authenticated request of the day (see
		 *	\Nino\Modules\Backups) is what a restore comes from, and the
		 *	Backups panel is the way back.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$typeUri 	= (string) ( $data['uri'] ?? '' );
			$confirm 	= (string) ( $data['confirm'] ?? '' );

			if( in_array( $typeUri, Admin::types( $appData ), true ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown element type' );
				return;
			}

			// Typing the uri is the confirmation. A dialog answered with the
			// mouse is one wrong click; this one has to be meant
			if( $confirm !== $typeUri ) {
				\Nino\Http::fail( $request, 400, 'type the element type\'s uri to confirm' );
				return;
			}

			$referencedBy = self::referencedBy( $appData, $typeUri );

			if( $referencedBy !== [] ) {
				\Nino\Http::fail( $request, 409, 'still referenced by the element field(s) '. implode( ', ', $referencedBy ) );
				return;
			}

			// Collected before anything is removed: an image field's value is a
			// filename, and once the elements are gone there is nothing left to
			// read the filenames out of
			$filenames = [];
			$elements 	= \Nino\Elements::queryElements( $appData, '/'. $typeUri, [], '*', [] );

			foreach( $elements as $element )
				foreach( Admin::imageFilenames( $appData, $typeUri, (string) ( $element['.uri'] ?? '' ) ) as $filename )
					$filenames[$filename] = true;

			// No Filesystem::removeFile() to call - the kernel writes files and
			// reads them, and only \Nino\Images::delete() removes one, for the
			// uploads it owns. So the same shape as that one: resolve the
			// virtual path, then unlink it. @ for the identical TOCTOU reason -
			// is_file() and unlink() are two syscalls, and a second request
			// deleting the same type in between must not warn its way into a 500
			$path = \Nino\Filesystem::path( $appData, '/elements/'. $typeUri. '.php' );

			if( is_file( $path ) === true && @unlink( $path ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not remove the type file' );
				return;
			}

			// The same line \Nino\Elements::deleteElement() ends its mutation
			// with: elements read earlier in this request are still sitting in
			// the per-request cache, and nothing about a vanished file evicts them
			unset( $appData['./nino/elements/cache'] );

			foreach( array_keys( $filenames ) as $filename )
				\Nino\Images::delete( $appData, $filename );

			// Recorded here rather than through log(), which is handed the posted
			// body and could only ever say "with ? element(s)" - the counts exist
			// at this point and nowhere else
			$actor = \Nino\Auth::getCurrentUser( $appData );

			if( $actor !== false )
				\Nino\Admin\Admin::record( $appData, $actor['mail'],
					'Delete Element Type /'. $typeUri. ' with '. count( $elements ). ' element(s) and '. count( $filenames ). ' image(s)' );

			\Nino\Http::ok( $request, [ 'uri' => $typeUri, 'elements' => count( $elements ), 'images' => count( $filenames ) ] );
		}

		/**
		 *	Which element fields of which other types point at this one - the
		 *	references deleting it would leave dangling.
		 *
		 *	A type referencing itself does not count: it is going away with the
		 *	field that names it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$typeUri			The type about to be deleted
		 *
		 *	@return 	array										Eg. [ 'pages.teaser', 'offers.related' ]
		 */
		public static function referencedBy( array &$appData, string $typeUri ): array {

			$found = [];

			foreach( Admin::types( $appData ) as $other ) {

				if( $other === $typeUri )
					continue;

				foreach( Admin::typeData( $appData, $other )['model'] ?? [] as $key => $field )
					if( ( $field['type'] ?? '' ) === 'element' && trim( (string) ( $field['elementType'] ?? '' ), '/' ) === $typeUri )
						$found[] = $other. '.'. $key;
			}

			return $found;
		}
	}
}
