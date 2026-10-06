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
	 *	Types							Manage element types (elements/<type>.php): title + model
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

		// The kernel's list, under the name the panel and its script have
		// always read it by: sent as fieldTypes with every type list
		public const array FIELD_TYPES = \Nino\Elements::FIELD_TYPES;

		// The field types a fixed unit or suffix applies to: every type that
		// renders an input a unit can sit next to. Not boolean (a "Yes"/"No"
		// choice has nothing to append), not image (its own preview and
		// upload area), not element (a select of elements). Stated here once:
		// cleanModel() keeps a suffix for these types only, apiList() hands
		// the list to the type editor, which offers the input for them only
		public const array SUFFIX_TYPES = [ 'string', 'integer', 'double', 'array', 'date', 'datetime' ];

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
			return [ 'value' => count( Admin::types( $appData ) ), 'label' => '/_admin/dashboard/label/types' ];
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
		 *	\Nino\Images::process()'s basePath check, before it ever reaches a filesystem call
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
		 *	uri - what apiList() answers
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ [ 'uri', 'title', 'fieldCount' ], ... ]
		 */
		public static function summaries( array &$appData ): array {

			$types = [];
			foreach( Admin::types( $appData ) as $typeUri ) {

				$typeData = Admin::typeData( $appData, $typeUri );

				$types[] = [
					'uri' 				=> $typeUri,
					'title' 			=> $typeData['title'] ?? $typeUri,
					'fieldCount' 	=> count( $typeData['model'] ?? [] ),
				];
			}

			// strcmp, not the sort() Admin::types() applies: sort() orders
			// numeric-looking names as numbers, '9' before '10', and this list
			// has always been ordered as plain strings - a hand-written type
			// file may carry such a name (the panel only creates ones that start
			// with a letter, see isValidTypeUri())
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

			\Nino\Http::ok( $request, [ 'types' => self::summaries( $appData ), 'fieldTypes' => self::FIELD_TYPES, 'suffixTypes' => self::SUFFIX_TYPES ] );
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
				\Nino\Http::fail( $request, 400, 'invalid type uri', 'types_invalid_uri', [], 'uri' );
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
		 *	same rules as \Nino\Elements::insertElementType(), plus width/height for
		 *	image fields, maxlength and inputsize for string fields, the referenced type for
		 *	element fields, a fixed unit suffix for every type but boolean/
		 *	image/element, a plain string list for options, for an image
		 *	field the string field that holds its alt text (see below), and for
		 *	a string field what goes with its html - 'blocks' for paragraphs and
		 *	lists - or, without it, 'breaks' for the line breaks of its text
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

				// What the html of a field may hold beyond the inline tags
				// (paragraphs and lists), and - for a field without html - whether
				// the line breaks of its text are kept on the page. Each only where
				// it can mean something, and never both: a field is rich text or it
				// is plain text (see \Nino\Html::fieldFormat())
				if( isset( $field['html'] ) === true && ( $data['blocks'] ?? false ) === true )
					$field['blocks'] = true;

				if( $data['type'] === 'string' && isset( $field['html'] ) === false && ( $data['breaks'] ?? false ) === true )
					$field['breaks'] = true;

				// Never on an image, whatever was posted: its file is uploaded
				// separately, once the element already exists and has a uri to
				// attach it to (see assets/admin.js's image branch), so a
				// required image could not be satisfied by the very save that
				// creates the element - the type would be impossible to add an
				// element to at all. The frontend stops offering the checkbox
				// for image fields (see assets/types.js), and this drops
				// it from a hand-written or older model on the next save
				if( $data['type'] !== 'image' && ( $data['required'] ?? false ) === true )
					$field['required'] = true;

				if( $data['type'] === 'image' ) {
					$field['width'] 	= max( 1, (int) ( $data['width'] ?? 0 ) );
					$field['height'] 	= max( 1, (int) ( $data['height'] ?? 0 ) );

					// The field that holds this picture's alt text, per language.
					// Only kept as the name here: whether it names a field that can
					// hold one is decided below, once the whole model is clean
					$alt = trim( (string) ( $data['alt'] ?? '' ) );
					if( $alt !== '' )
						$field['alt'] = $alt;
				}

				// Which type an element reference may point at. Kept as posted
				// rather than checked against the types on disk here - this
				// method has no $appData; apiSave()/apiCreate() reject an
				// unknown one outright (see _unknownReferencedType()) so the
				// author gets told, instead of the field silently vanishing
				if( $data['type'] === 'element' ) {

					/*	Stored without its slashes, because that is the spelling
						everything downstream reads: \Nino\Elements builds the
						prefix a reference has to start with as
						'/'. trim( elementType, '/' ). '/', referencedBy() below
						compares the same way, and the element form builds an
						option value as '/'+ elementType+ '/'+ uri - which is
						'//pages/x' for a type written as '/pages'. A model
						written by hand with the leading slash therefore ran
						perfectly well and could not be saved from this panel at
						all: the dangling-reference check compared it against the
						bare type uris on disk and refused every save, including
						one that only changed the title.	*/
					$field['elementType'] = trim( trim( (string) ( $data['elementType'] ?? '' ) ), '/' );

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

				// Only rendered for a string field (admin.js's maxlength+counter and
				// html-editor branches) - 0/absent falls back to DEFAULT_MAXLENGTH client-side
				if( $data['type'] === 'string' ) {
					$maxlength = (int) ( $data['maxlength'] ?? 0 );
					if( $maxlength > 0 )
						$field['maxlength'] = $maxlength;

					// How many rows the field's input opens with: the textarea's
					// rows, or the rich-text area's minimum height in lines (see
					// admin.js and html-editor.js). A size is a hint for the form,
					// never a limit on the value, so 0/absent leaves the input at
					// the stylesheet's default height
					$inputsize = (int) ( $data['inputsize'] ?? 0 );
					if( $inputsize > 0 )
						$field['inputsize'] = $inputsize;
				}

				// A fixed unit/label shown next to the input (eg. a "price" field's
				// "€") - for the types that render an input a unit can sit next
				// to, see SUFFIX_TYPES
				if( in_array( $data['type'], self::SUFFIX_TYPES, true ) === true ) {
					$suffix = trim( (string) ( $data['suffix'] ?? '' ) );
					if( $suffix !== '' )
						$field['suffix'] = $suffix;
				}

				// Never on an element reference: its choices are the referenced
				// type's elements, and a second fixed list next to them would
				// be two selects fighting over the same value (admin.js
				// renders the options list ahead of the type's own branches)
				if( $data['type'] !== 'element' && is_array( $data['options'] ?? null ) === true && count( $data['options'] ) > 0 )
					$field['options'] = array_values( array_map( 'strval', $data['options'] ) );

				$clean[$key] = $field;
			}

			// An alt link is kept only where it can be satisfied: it names a
			// field of this model, other than the image itself, that is a plain
			// string and written per language. A global field has one text for
			// every language, and html would put markup in an attribute -
			// neither is an alt text. A link to anything else is dropped,
			// not an error: the model is saved without it
			foreach( $clean as $key => $field ) {

				if( isset( $field['alt'] ) === false )
					continue;

				$target = $clean[ $field['alt'] ] ?? null;

				if( $field['alt'] === $key || $target === null || $target['type'] !== 'string' || ( $target['locale'] ?? false ) !== true || ( $target['html'] ?? false ) === true )
					unset( $clean[$key]['alt'] );
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
		 *	an empty, permanently unusable select in the element form, and
		 *	the author has no way of telling that from "this type simply has
		 *	no elements yet". A type deleted *later* is a different case and
		 *	stays tolerated - the forms show the dangling value rather than
		 *	discard it (see assets/admin.js's element branch).
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$model				Model as returned by cleanModel()
		 *
		 *	@return 	string|null								Error message, or null when every reference resolves
		 */
		private static function _unknownReferencedType( array &$appData, array $model ): ?string {

			$known = Admin::types( $appData );

			foreach( $model as $key => $field ) {

				if( ( $field['type'] ?? '' ) !== 'element' )
					continue;

				// Normalised here too rather than trusting the caller to have
				// been through cleanModel(): this is the method that decides
				// whether a model is refusable, and it answers for the spelling
				// the kernel accepts, not for one particular spelling of it
				$referenced = trim( trim( (string) ( $field['elementType'] ?? '' ) ), '/' );

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
		 *	migrated via _migrateFieldShape() the same way \Nino\Modules\Text\Keys::apiSave()
		 *	already migrates a text key's value(s) on the same kind of
		 *	change. Without that, a field switched to global keeps its old
		 *	per-locale value(s) sitting in the locale buckets - and since
		 *	_cacheElement() merges locale data over '*' data (so a locale
		 *	can legitimately override a global default), that stale locale
		 *	value would keep winning over the new global one forever.
		 *
		 *	A field can also be renamed: 'renames' is { old key: new key }, and
		 *	every value stored under the old key - in '*' and in every locale
		 *	bucket, the defaults entry of each included, whether the locale is
		 *	still available or not - moves to the new one, so renaming a field
		 *	is not the same as deleting one and adding another. Several renames
		 *	in one save are read against the type as it was, so a swap (a to
		 *	b, b to a) and a chain (a to b, b to c) both work. What it refuses,
		 *	by name and with a 409: two renames to the same key, a key an
		 *	unrenamed field already has, a key that still holds the values of a
		 *	field that was removed earlier (the editor never deletes data on
		 *	removal, and they would turn up in elements that never had them),
		 *	and an image field renamed onto the key of another one - an
		 *	upload's file name is made of the field key, so the two would
		 *	overwrite each other's pictures. An image's link to the field that
		 *	holds its alt text follows the rename (see _renamedAlts()). What the
		 *	rename cannot reach is reported instead of changed - see
		 *	_renameReferences().
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
				\Nino\Http::fail( $request, 400, 'invalid type uri', 'types_invalid_uri', [], 'uri' );
				return;
			}

			$renames = self::_postedRenames( $data['renames'] ?? [] );

			if( $renames === null ) {
				\Nino\Http::fail( $request, 400, 'renames must name fields', 'types_renames' );
				return;
			}

			$data['model'] = self::_renamedAlts( $data['model'] ?? [], $renames );

			$danglingReference = self::_unknownReferencedType( $appData, self::cleanModel( $data['model'] ) );

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
			$refused 				= false;
			$resultTypeData	= null;

			$written = \Nino\Filesystem::mutate( $appData, '/elements/'. $typeUri. '.php', function( mixed $typeData ) use ( $appData, $data, $typeUri, $renames, &$request, &$notFound, &$refused, &$resultTypeData ): ?array {

				if( $typeData === false ) {
					$notFound = true;
					return null;
				}

				$title 		= trim( (string) ( $data['title'] ?? '' ) );
				$oldModel = $typeData['model'] ?? [];
				$newModel = self::cleanModel( $data['model'] );

				if( $renames !== [] ) {

					$refused = self::_refuseRenames( $request, $typeData, $oldModel, $newModel, $renames );

					if( $refused === true )
						return null;

					self::_renameFields( $typeData, $renames );

					// The old model, as it reads now: what the shape check below
					// compares a field against is the field it was, under the key it
					// has been given
					$renamed = [];
					foreach( $oldModel as $key => $field )
						$renamed[ $renames[$key] ?? $key ] = $field;
					$oldModel = $renamed;
				}

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

			// The refusal is in the response already - said by _refuseRenames()
			// where it was found, inside the lock
			if( $refused === true )
				return;

			if( $written === false ) {
				\Nino\Http::fail( $request, 500, 'could not save the type file' );
				return;
			}

			\Nino\Http::ok( $request, [
				'uri' 					=> $typeUri,
				'title' 				=> $resultTypeData['title'],
				'model' 				=> $resultTypeData['model'],
				'autoincrement' => \Nino\Elements::readAutoincrement( $resultTypeData ) !== null,
				'renamed' 			=> $renames,
				'references' 		=> self::_renameReferences( $appData, $typeUri, $renames ),
			] );
		}

		/**
		 *	The renames of a save, as { old: new } with both sides trimmed like
		 *	cleanModel() trims a key. A rename to the name a field already has
		 *	is no rename and is left out.
		 *
		 *	@param		mixed			$posted				The posted 'renames'
		 *
		 *	@return 	array|null								null when it is not an object of non-empty strings
		 */
		private static function _postedRenames( mixed $posted ): ?array {

			if( is_array( $posted ) === false )
				return null;

			$renames = [];

			foreach( $posted as $old => $new ) {

				if( is_string( $new ) === false )
					return null;

				$old = trim( (string) $old );
				$new = trim( $new );

				if( $old === '' || $new === '' )
					return null;

				if( $old !== $new )
					$renames[$old] = $new;
			}

			return $renames;
		}

		/**
		 *	The posted model with every image's alt link under the new name of the
		 *	field it points at. The form names a field the way it was saved while
		 *	that field is in the form, so a rename in the same save leaves the
		 *	link at the old name - which cleanModel() would drop without a word,
		 *	and a rename is not a removal
		 *
		 *	@param		mixed			$model				The posted model
		 *	@param		array 		$renames			{ old: new }, as _postedRenames() returns them
		 *
		 *	@return 	mixed											The model, the links renamed
		 */
		private static function _renamedAlts( mixed $model, array $renames ): mixed {

			if( is_array( $model ) === false || $renames === [] )
				return $model;

			foreach( $model as $key => $field ) {

				if( is_array( $field ) === false || is_string( $field['alt'] ?? null ) === false )
					continue;

				$model[$key]['alt'] = $renames[ trim( $field['alt'] ) ] ?? $field['alt'];
			}

			return $model;
		}

		/**
		 *	Whether a set of renames can be applied to a type as it stands now
		 *	- see apiSave() for what is refused, and answered here with the
		 *	reason. Read inside the lock, against the file as it is, not as the
		 *	form last saw it
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		array 		$typeData			The type file's content
		 *	@param		array 		$oldModel			The model as saved
		 *	@param		array 		$newModel			The model that is being saved, cleaned
		 *	@param		array 		$renames			{ old: new }
		 *
		 *	@return 	bool											Whether one was refused
		 */
		private static function _refuseRenames( array &$request, array $typeData, array $oldModel, array $newModel, array $renames ): bool {

			$targets 		= [];
			$imageKeys 	= [];

			foreach( $oldModel as $key => $field )
				if( ( $field['type'] ?? '' ) === 'image' )
					$imageKeys[$key] = true;

			foreach( $renames as $old => $new ) {

				if( array_key_exists( $old, $oldModel ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown field "'. $old. '"', 'types_rename_unknown', [ $old ] );
					return true;
				}

				if( array_key_exists( $new, $newModel ) === false ) {
					\Nino\Http::fail( $request, 400, 'the field "'. $new. '" is not in the model', 'types_rename_missing', [ $new ] );
					return true;
				}

				// Two fields cannot become one, and a field that is not itself
				// renamed away keeps its name
				if( isset( $targets[$new] ) === true || ( array_key_exists( $new, $oldModel ) === true && array_key_exists( $new, $renames ) === false ) ) {
					\Nino\Http::fail( $request, 409, 'the field "'. $new. '" already exists', 'types_rename_collision', [ $new ] );
					return true;
				}

				$targets[$new] = true;

				if( isset( $imageKeys[$old] ) === true && isset( $imageKeys[$new] ) === true ) {
					\Nino\Http::fail( $request, 409, 'the image fields "'. $old. '" and "'. $new. '" would share their files', 'types_rename_image', [ $new ] );
					return true;
				}

				// A key that is in no field of the old model but still has values
				// in an element is what a removed field leaves behind
				if( array_key_exists( $new, $oldModel ) === false )
					foreach( $typeData as $bucket => $entries ) {

						if( $bucket === 'model' || is_array( $entries ) === false )
							continue;

						foreach( $entries as $fields )
							if( is_array( $fields ) === true && array_key_exists( $new, $fields ) === true ) {
								\Nino\Http::fail( $request, 409, 'the key "'. $new. '" still holds the values of a removed field', 'types_rename_values', [ $new ] );
								return true;
							}
					}
			}

			return false;
		}

		/**
		 *	Give every stored value its field's new key: in every bucket of the
		 *	file - '*' and each locale, available or not - and in every entry of
		 *	it, the defaults entry '*' included. Each entry is rebuilt from what
		 *	it held, key by key, so the order is kept and a swap or a chain
		 *	cannot read a value it has just written
		 *
		 *	@param		array 		&$typeData		(reference) The type file's content
		 *	@param		array 		$renames			{ old: new }, already checked by _refuseRenames()
		 *
		 *	@return 	void
		 */
		private static function _renameFields( array &$typeData, array $renames ): void {

			foreach( $typeData as $bucket => $entries ) {

				if( $bucket === 'model' || is_array( $entries ) === false )
					continue;

				foreach( $entries as $uri => $fields ) {

					if( is_array( $fields ) === false )
						continue;

					$renamed = [];

					foreach( $fields as $key => $value )
						$renamed[ $renames[$key] ?? $key ] = $value;

					$typeData[$bucket][$uri] = $renamed;
				}
			}
		}

		/**
		 *	What still says the old name after a rename, which the rename does not
		 *	touch and the save only reports: a template that fills [[old]] (it
		 *	would show literally on the page), a role that is granted
		 *	/_admin/elements/<type>/update/<old>, and a label fill
		 *	/_admin/elements/field/<type>/<old>. Moving those is the project's
		 *	decision, not the type editor's
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$typeUri
		 *	@param		array 		$renames			{ old: new }
		 *
		 *	@return 	array										[ [ 'kind' => 'template'|'role'|'label', 'name' => ..., 'field' => old key ], ... ]
		 */
		private static function _renameReferences( array &$appData, string $typeUri, array $renames ): array {

			$references = [];

			if( $renames === [] )
				return $references;

			$fills 		= \Nino\Html::getFills( $appData );
			$templates = glob( \Nino\Filesystem::path( $appData, '/templates' ). '/*.tpl' ) ?: [];

			foreach( array_keys( $renames ) as $old ) {

				foreach( $templates as $file )
					if( str_contains( (string) file_get_contents( $file ), '[['. $old. ']]' ) === true )
						$references[] = [ 'kind' => 'template', 'name' => basename( $file ), 'field' => $old ];

				$grant = Admin::SCOPE. $typeUri. '/update/'. $old;

				foreach( is_array( $appData['/nino/auth/roles'] ?? null ) === true ? $appData['/nino/auth/roles'] : [] as $id => $role )
					if( in_array( $grant, is_array( $role['perms'] ?? null ) === true ? $role['perms'] : [], true ) === true )
						$references[] = [ 'kind' => 'role', 'name' => (string) $id, 'field' => $old ];

				$label = '/_admin/elements/field/'. $typeUri. '/'. $old;

				if( array_key_exists( '[['. $label. ']]', $fills ) === true )
					$references[] = [ 'kind' => 'label', 'name' => $label, 'field' => $old ];
			}

			return $references;
		}

		/**
		 *	Move one field's stored value(s), across every element of this
		 *	type, between the '*' bucket and every locale bucket - called
		 *	from apiSave() when that field's model 'locale' flag just
		 *	changed. Same migrate-don't-discard reasoning as
		 *	\Nino\Modules\Text\Keys::_convertShape(): global -> per-locale copies the current
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
				\Nino\Http::fail( $request, 400, 'invalid type uri', 'types_invalid_uri', [], 'uri' );
				return;
			}

			if( \Nino\Filesystem::getFileContent( $appData, '/elements/'. $typeUri. '.php', '' ) !== '' ) {
				\Nino\Http::fail( $request, 409, 'type already exists', 'types_exists', [], 'uri' );
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
			// nothing yet to seed past (see \Nino\Elements::AUTOINCREMENT_PAD)
			if( ( $data['autoincrement'] ?? false ) === true )
				$typeData['autoincrement'] = 1;

			// Checked, same as apiSave() does: answering 200 on a failed write
			// leaves the frontend believing the type exists (types.js
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
				'types/save' 		=> 'Edit Element Type /'. ( $data['uri'] ?? '' ). self::_renamedLog( $data['renames'] ?? null ),
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
		 *	The part of a save's log line that names its renames, ' (renamed a
		 *	to b, c to d)', or nothing
		 *
		 *	@param		mixed			$posted				The posted 'renames'
		 *
		 *	@return 	string
		 */
		private static function _renamedLog( mixed $posted ): string {

			$renames = self::_postedRenames( $posted ?? [] ) ?? [];

			if( $renames === [] )
				return '';

			return ' (renamed '. implode( ', ', array_map( fn( int|string $old, string $new ): string => $old. ' to '. $new, array_keys( $renames ), $renames ) ). ')';
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
				\Nino\Http::fail( $request, 400, 'type the element type\'s uri to confirm', 'types_confirm', [], 'confirm' );
				return;
			}

			$referencedBy = self::referencedBy( $appData, $typeUri );

			if( $referencedBy !== [] ) {
				\Nino\Http::fail( $request, 409, 'still referenced by the element field(s) '. implode( ', ', $referencedBy ), 'types_referenced', [ implode( ', ', $referencedBy ) ] );
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
