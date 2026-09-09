<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Form							The form engine behind POST /.form - see the class docblock
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Form							Everything the one form endpoint does, as api rather than
	 *										as one hardcoded contact form: which forms there are, what
	 *										a submission has to look like, the mail pair it sends and
	 *										the record it leaves. \Nino\Modules\Form is the module
	 *										that owns the route and hands it here; the Forms feature
	 *										in the catalogue is a second writer of the definitions
	 *										and a spam guard in front of it, and carries no copy of
	 *										any of this.
	 *
	 *										A project defines its forms under '/nino/form/forms' in
	 *										config.php - the same place its routes and its image
	 *										slots live, so they are hand-editable, they travel in
	 *										every backup, and a form needs no file format of its own.
	 *										A project that defines none gets DEFAULT_FORM, which is
	 *										the contact form Nino has always shipped, field for
	 *										field.
	 *
	 *										One submission is one entry in /data/forms.<Y-m>.php,
	 *										the file this framework has written since the contact
	 *										form existed: the field values flat at the top level,
	 *										beside the date and the client ip. An entry written
	 *										before there was more than one form carries no 'form'
	 *										and no 'id' and is read as the contact form's, so a
	 *										project's history survives the update untouched.
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Form {

		// What one posted value may carry - a field is a line or a message,
		// never an upload
		public const int MAX_FIELD_LENGTH = 1000;

		// How many months of submissions stay on disk. Longer than the admin
		// activity log's window: these are real business inquiries, not an
		// operational safety net
		public const int RETENTION_MONTHS = 3;

		// Where a project's forms live. Absent means DEFAULT_FORM
		public const string FORMS = '/nino/form/forms';

		// How long a submission stays on disk, in months, and whether one is
		// written at all. Beside the definitions on purpose: a project decides
		// all three the same way - by hand in config.php, or through the
		// catalogue's Forms feature, which writes these very keys.
		//
		// Storing nothing is a position, not a mistake: a site that answers
		// its mail and keeps no copy has less to protect, and the panel then
		// stays empty because there is nothing to show rather than because
		// something failed
		public const string RETENTION	= '/nino/form/retention';
		public const string STORE			= '/nino/form/store';

		// The field types a form may declare. 'textarea' is the only one that
		// is not an <input type>.
		//
		// No 'checkbox': the shared .nino-form script posts each field's
		// .value unconditionally (see _nino/Nino.ui.js), and an unticked
		// checkbox's value is still the string "on" - a box nobody ticked
		// would be mailed and recorded as ticked
		public const array TYPES = [ 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select' ];

		// Names a field may not take: the four the endpoint reads off the
		// post itself, and the four a record carries beside its values. A
		// form that wants a date asks for 'birthdate'
		public const array RESERVED = [ 'form', 'location', '_csrf', '_t', 'id', 'date', 'ip' ];

		// The contact form Nino has always shipped, as data rather than as
		// five lines of code. The labels are the fills its install unit
		// writes, so a project that never opens a form builder sees exactly
		// what it saw before
		public const array DEFAULT_FORM = [
			'key'						=> 'contact',
			'name'					=> 'Contact',
			'to'						=> '',
			'subject'				=> '',
			'confirm'				=> true,
			'ownerTemplate'	=> '/templates/mail-owner',
			'userTemplate'	=> '/templates/mail-user',
			'fields'				=> [
				[ 'name' => 'name',			'label' => '[[/form/label/name]]',		'type' => 'text',			'required' => true,		'options' => [] ],
				[ 'name' => 'email',		'label' => '[[/form/label/email]]',		'type' => 'email',		'required' => true,		'options' => [] ],
				[ 'name' => 'cat',			'label' => '[[/form/label/cat]]',			'type' => 'text',			'required' => false,	'options' => [] ],
				[ 'name' => 'message',	'label' => '[[/form/label/message]]',	'type' => 'textarea',	'required' => true,		'options' => [] ],
			],
		];

		/**
		 *	Every form this project defines, validated - the definitions
		 *	under '/nino/form/forms', or the built-in contact form while
		 *	there are none. A stored definition that does not validate is
		 *	left out rather than half-read: a form nobody can submit is
		 *	better than one that mails to an address a hand edit mistyped
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Forms, in the order they are defined
		 */
		public static function forms( array &$appData ): array {

			$forms = [];

			foreach( (array) ( $appData[ self::FORMS ] ?? [] ) as $entry )
				if( is_array( $entry ) === true && ( $form = self::normalize( $entry ) ) !== null )
					$forms[] = $form;

			return $forms === [] ? [ self::normalize( self::DEFAULT_FORM ) ] : $forms;
		}

		/**
		 *	How many months of submissions stay on disk - '/nino/form/retention'
		 *	where a project sets one, RETENTION_MONTHS otherwise. A value
		 *	outside 1..60 is a hand edit gone wrong, and falls back to the
		 *	default rather than deleting a project's history to nothing or
		 *	keeping it forever
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function retention( array &$appData ): int {

			$months = (int) ( $appData[ self::RETENTION ] ?? self::RETENTION_MONTHS );

			return ( $months >= 1 && $months <= 60 ) ? $months : self::RETENTION_MONTHS;
		}

		/**
		 *	Whether a submission is recorded at all. On unless a project says
		 *	otherwise - what this framework has always done
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function stores( array &$appData ): bool {

			return ( $appData[ self::STORE ] ?? true ) !== false;
		}

		/**
		 *	One form by key, or - for an empty key - the first one defined,
		 *	which is the form a submission carrying no key belongs to
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$key					A form key, '' for the default
		 *
		 *	@return 	array | null						Null for a key no form has
		 */
		public static function form( array &$appData, string $key = '' ): ?array {

			$forms = self::forms( $appData );

			if( $key === '' )
				return $forms[0] ?? null;

			foreach( $forms as $form )
				if( $form['key'] === $key )
					return $form;

			return null;
		}

		/**
		 *	One definition in the shape everything here relies on - every key
		 *	present, every value of its declared type, the fields
		 *	deduplicated by name. Null for a definition with no usable key or
		 *	not one usable field
		 *
		 *	@param		array 		$entry				A definition as stored or as posted
		 *
		 *	@return 	array | null
		 */
		public static function normalize( array $entry ): ?array {

			$key = strtolower( trim( (string) ( $entry['key'] ?? '' ) ) );

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $key ) !== 1 )
				return null;

			$fields	= [];
			$seen		= [];

			foreach( (array) ( $entry['fields'] ?? [] ) as $field ) {

				if( is_array( $field ) === false )
					continue;

				$name = is_string( $field['name'] ?? null ) === true ? trim( $field['name'] ) : '';
				$type = is_string( $field['type'] ?? null ) === true ? $field['type'] : 'text';

				// A field name becomes a posted key, a column of an export and
				// a placeholder in the mail - so it is an identifier, not a
				// label, and never one of the names something else owns
				if( preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name ) !== 1 || in_array( $name, self::RESERVED, true ) === true )
					continue;

				if( in_array( $name, $seen, true ) === true )
					continue;

				$seen[] = $name;

				$options = [];
				foreach( (array) ( $field['options'] ?? [] ) as $option )
					if( is_string( $option ) === true && trim( $option ) !== '' )
						$options[] = substr( trim( $option ), 0, 200 );

				$label = is_string( $field['label'] ?? null ) === true ? trim( $field['label'] ) : '';

				$fields[] = [
					'name'			=> $name,
					'label'			=> $label === '' ? $name : substr( $label, 0, 200 ),
					'type'			=> in_array( $type, self::TYPES, true ) === true ? $type : 'text',
					'required'	=> ( $field['required'] ?? false ) === true,
					'options'		=> $options,
				];
			}

			if( $fields === [] )
				return null;

			$to		= is_string( $entry['to'] ?? null ) === true ? trim( $entry['to'] ) : '';
			$name	= is_string( $entry['name'] ?? null ) === true ? trim( $entry['name'] ) : '';

			return [
				'key'						=> $key,
				'name'					=> $name === '' ? $key : substr( $name, 0, 100 ),
				'to'						=> filter_var( $to, FILTER_VALIDATE_EMAIL ) === false ? '' : $to,
				'subject'				=> is_string( $entry['subject'] ?? null ) === true ? substr( trim( $entry['subject'] ), 0, 200 ) : '',
				'confirm'				=> ( $entry['confirm'] ?? false ) === true,
				'ownerTemplate'	=> self::_template( $entry['ownerTemplate'] ?? '', '/templates/mail-owner' ),
				'userTemplate'	=> self::_template( $entry['userTemplate'] ?? '', '/templates/mail-user' ),
				'fields'				=> $fields,
			];
		}

		/**
		 *	A template path as [template ...] takes one: absolute, no
		 *	traversal, no shortcode syntax of its own. Anything else falls
		 *	back to the default rather than being rendered
		 *
		 *	@param		mixed			$value				As stored
		 *	@param		string		$default
		 *
		 *	@return 	string
		 */
		private static function _template( mixed $value, string $default ): string {

			$path = is_string( $value ) === true ? trim( $value ) : '';

			return preg_match( '#^/templates/[a-zA-Z0-9_-]+$#', $path ) === 1 ? $path : $default;
		}

		/**
		 *	Every posted value, once - name-checked, trimmed and capped. A
		 *	value that is not a string is not taken: casting one (name[]=x)
		 *	raises an engine warning this framework treats as fatal, ie. an
		 *	unauthenticated 500 from a malformed post. Reading the whole post
		 *	here rather than field by field is what makes that one check
		 *	instead of one per field
		 *
		 *	@return 	array										name => value
		 */
		public static function posted(): array {

			$posted = [];

			foreach( $_POST as $key => $value )
				if( is_string( $key ) === true && is_string( $value ) === true
					&& preg_match( '/^[a-zA-Z_][a-zA-Z0-9_-]{0,63}$/', $key ) === 1 )
					$posted[$key] = substr( trim( $value ), 0, self::MAX_FIELD_LENGTH );

			return $posted;
		}

		/**
		 *	The submission a form's fields describe, read out of what was
		 *	posted - or false when a required field is empty or a value is
		 *	not of the shape its field declares. The browser ran these checks
		 *	too; a post does not have to come from a browser
		 *
		 *	@param		array 		$form					One normalized form
		 *	@param		array 		$posted				What posted() read
		 *
		 *	@return 	array | false						name => value, or false
		 */
		public static function validate( array $form, array $posted ): array|false {

			$values = [];
			$failed = false;

			foreach( $form['fields'] as $field ) {

				$value = $posted[ $field['name'] ] ?? '';

				if( $field['required'] === true && $value === '' )
					$failed = true;

				if( $value !== '' && self::_valid( $field, $value ) === false )
					$failed = true;

				$values[ $field['name'] ] = $value;
			}

			return $failed === true ? false : $values;
		}

		/**
		 *	Whether one posted value is of the shape its field declares
		 *
		 *	@param		array 		$field				One normalized field
		 *	@param		string		$value				The posted value, non-empty
		 *
		 *	@return 	bool
		 */
		private static function _valid( array $field, string $value ): bool {

			return match( $field['type'] ) {
				'email'		=> filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false,
				'url'			=> filter_var( $value, FILTER_VALIDATE_URL ) !== false,
				'number'	=> is_numeric( $value ) === true,
				'select'	=> $field['options'] === [] || in_array( $value, $field['options'], true ) === true,
				default		=> true,
			};
		}

		/**
		 *	The whole endpoint: which form this is, whether the submission
		 *	may pass, the mail pair it sends and the record it leaves.
		 *	\Nino\Modules\Form hands POST /.form here; a listener registered
		 *	ahead of that module on the same callback (see
		 *	\Nino\Csrf::init(), which does exactly this at priority 1) refuses
		 *	a submission simply by setting a status other than 200, and this
		 *	returns without sending or writing anything
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handle( array &$appData, array &$request ): void {

			// Respect a rejection from an earlier callback: the global Csrf
			// guard sets a flag of its own, anything else simply left a status
			// behind. Two checks rather than one because the flag survives a
			// later handler resetting the status, which is what it is for
			if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
				return;

			if( (int) ( $request['/nino/http/response']['statusCode'] ?? 200 ) !== 200 )
				return;

			$posted	= self::posted();
			$key		= (string) ( $posted['form'] ?? '' );
			$form		= self::form( $appData, preg_match( '/^[a-z][a-z0-9-]*$/', $key ) === 1 ? $key : '' );

			// A key no form has: a page pointing at a form that was renamed,
			// which the visitor can do nothing about and which is not spam
			if( $form === null ) {
				$request['/nino/http/response']['statusCode'] = 404;
				return;
			}

			// The honeypot no person sees. 418 here and for every other
			// refusal a guard makes: the shared .nino-form script shows one
			// generic message for anything that is not 200 or 400, so a bot
			// never learns which check it tripped
			if( ( $posted['location'] ?? '' ) !== '' ) {
				$request['/nino/http/response']['statusCode'] = 418;
				return;
			}

			$values = self::validate( $form, $posted );

			if( $values === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				return;
			}

			self::send( $appData, $form, $values );

			$request['/nino/http/response']['statusCode']	= 200;
			$request['/nino/http/response']['body']				= [ 'status' => 'ok' ];

			// A submission whose mail the cap refused is not recorded: one
			// entry per request regardless would turn a throttled flood into
			// unthrottled disk growth from an unauthenticated endpoint. A
			// mail() that simply failed still records - there the inquiry did
			// happen and losing it would be worse
			if( ( $appData['./nino/mail/ratelimited'] ?? false ) === true )
				return;

			if( self::stores( $appData ) === true )
				self::record( $appData, $form, $values );
		}

		/**
		 *	The owner notification and, where the form asks for one and the
		 *	submission carries an address, the visitor's confirmation. The
		 *	owner mail always goes out in the site's native locale, the
		 *	visitor's in the locale they filled the form in. Both are handed
		 *	to \Nino\Mail::sendAll(), so one visitor action costs one hit of
		 *	the per-ip cap rather than two
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$form					One normalized form
		 *	@param		array 		$values				name => value
		 *
		 *	@return 	void
		 */
		public static function send( array &$appData, array $form, array $values ): void {

			// [[/nino/dir]] and [[/nino/public]] are ordinary fills by the time
			// this runs - \Nino\request() registers them before
			// Http::response(), precisely so a mail rendered in here resolves
			// them instead of shipping the literal
			$owner = $form['to'] !== '' ? $form['to'] : \Nino\Html::renderHtml( $appData, '[[/form/email/owner]]' );
			$reply = self::_firstEmail( $form, $values );

			$visitorLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Locales::setCurrentLocale( $appData, \Nino\Locales::getNativeLocale( $appData ) );

			$mails = [ [
				'to'			=> $owner,
				'subject'	=> $form['subject'] !== ''
					? \Nino\Html::renderHtml( $appData, $form['subject'] )
					: \Nino\Html::renderHtml( $appData, '[[/form/subject/owner]]' ),
				'body'		=> self::render( $appData, $form, $values, $form['ownerTemplate'] ),
				'replyTo'	=> $reply !== '' ? $reply : $owner,
			] ];

			\Nino\Locales::setCurrentLocale( $appData, $visitorLocale );

			if( $form['confirm'] === true && $reply !== '' )
				$mails[] = [
					'to'			=> $reply,
					'subject'	=> \Nino\Html::renderHtml( $appData, '[[/form/subject/user]]' ),
					'body'		=> self::render( $appData, $form, $values, $form['userTemplate'] ),
					'replyTo'	=> $owner,
				];

			\Nino\Mail::sendAll( $appData, $mails );
		}

		/**
		 *	One mail body: the template rendered, then the placeholders
		 *	replaced in the result - that order, so a submitted value can
		 *	never be read as a fill, a shortcode or a template include.
		 *	[[fields]] is the whole submission as a table, which is what a
		 *	template for a form with fields nobody knew in advance needs;
		 *	[[name]], [[email]], [[message]] and [[subject]] are filled where
		 *	the form has a field of that name, so the templates this
		 *	framework has always shipped keep rendering
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$form					One normalized form
		 *	@param		array 		$values				name => value
		 *	@param		string		$template			Absolute template path
		 *
		 *	@return 	string
		 */
		public static function render( array &$appData, array $form, array $values, string $template ): string {

			$safe = static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
			$rows = '';

			foreach( $form['fields'] as $field )
				$rows .= '<tr><th>'. \Nino\Html::renderHtml( $appData, $field['label'] ). '</th><td>'
					. nl2br( $safe( (string) ( $values[ $field['name'] ] ?? '' ) ) ). '</td></tr>';

			$fills = [
				'[[fields]]'	=> '<table>'. $rows. '</table>',
				'[[form]]'		=> $safe( $form['name'] ),
				'[[date]]'		=> date( 'Y-m-d H:i:s' ),
				'[[subject]]'	=> $safe( (string) ( $values['cat'] ?? $values['subject'] ?? '' ) ),
				'[[name]]'		=> $safe( (string) ( $values['name'] ?? '' ) ),
				'[[email]]'		=> $safe( (string) ( $values['email'] ?? '' ) ),
				'[[message]]'	=> nl2br( $safe( (string) ( $values['message'] ?? '' ) ) ),
			];

			$html = \Nino\Html::renderHtml( $appData, '[template '. $template. ']' );

			return str_replace( array_keys( $fills ), array_values( $fills ), $html );
		}

		/**
		 *	The first address the submission carries - who a confirmation
		 *	goes to, and who a reply to the owner mail reaches
		 *
		 *	@param		array 		$form					One normalized form
		 *	@param		array 		$values				name => value
		 *
		 *	@return 	string									'' when the form asks for no address
		 */
		private static function _firstEmail( array $form, array $values ): string {

			foreach( $form['fields'] as $field )
				if( $field['type'] === 'email' && ( $values[ $field['name'] ] ?? '' ) !== '' )
					return (string) $values[ $field['name'] ];

			return '';
		}

		/**
		 *	Append one submission to this month's /data/forms.<Y-m>.php, then
		 *	prune the months past the retention window. Never thrown: a
		 *	failed record must not turn a delivered mail into a 500 for the
		 *	visitor.
		 *
		 *	The values are stored flat beside the date and the client ip -
		 *	the shape this file has had since the contact form existed, so
		 *	every entry a project already has stays readable. What is new is
		 *	'form' (which form it belongs to) and 'id' (an identity of its
		 *	own, so one entry can be deleted: a position in the file is not
		 *	one, every deletion above it would move the rest). An entry
		 *	written before those existed carries neither and is read as the
		 *	first form's.
		 *
		 *	Values are stored html-escaped, the way they always were; a
		 *	reader decodes them again on render
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$form					One normalized form
		 *	@param		array 		$values				name => value
		 *
		 *	@return 	void
		 */
		public static function record( array &$appData, array $form, array $values ): void {

			try {

				$entry = [
					'id'		=> bin2hex( random_bytes( 8 ) ),
					'date'	=> date( 'Y-m-d H:i:s' ),
					'form'	=> $form['key'],
				];

				foreach( $values as $name => $value )
					$entry[$name] = htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );

				$entry['ip'] = \Nino\Http::getClientIp();

				\Nino\Filesystem::mutate( $appData, '/data/forms.'. date( 'Y-m' ). '.php', function( array $entries ) use ( $entry ): array {
					$entries[] = $entry;
					return $entries;
				} );

				self::prune( $appData );

			} catch( \Throwable $e ) {
				trigger_error( 'Submission log write failed: '. $e->getMessage() );
			}
		}

		/**
		 *	Delete monthly files older than the retention window - a longer
		 *	window than the admin activity log (see Admin\Logs): these are
		 *	real business inquiries, not just an operational safety net
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function prune( array &$appData ): void {

			$cutoff = ( new \DateTime( 'first day of -'. self::retention( $appData ). ' months' ) )->setTime( 0, 0 );

			\Nino\RotatingLog::prune( \Nino\Filesystem::path( $appData, '/data' ), 'forms.', 'Y-m', '.php', $cutoff );
		}

		/**
		 *	Delete one recorded submission by its id - the one write this
		 *	engine does on a visitor's behalf rather than a visitor's action.
		 *	A person asking for their inquiry to be removed is an ordinary
		 *	request, and a list that cannot answer it is a list that has to
		 *	be edited by hand on the server
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id						The entry's id
		 *
		 *	@return 	bool										Whether an entry was removed
		 */
		public static function remove( array &$appData, string $id ): bool {

			// The id a record() has carried since submissions could belong to
			// more than one form. An entry written before that has none, and
			// there is nothing to address it by that stays true across a
			// deletion - so the panel offers no button on those rather than
			// offer one that could remove the wrong row
			if( preg_match( '/^[a-f0-9]{16}$/', $id ) !== 1 )
				return false;

			$dir		= \Nino\Filesystem::path( $appData, '/data' );
			$removed	= false;

			foreach( glob( $dir. '/forms.*.php' ) ?: [] as $file ) {

				$name = basename( $file );

				if( preg_match( '/^\d{4}-\d{2}$/', substr( $name, 6, -4 ) ) !== 1 )
					continue;

				// Read first, write only the one month that holds it: an entry
				// never moves between files, so this cannot pick the wrong one,
				// and every other month is left with its mtime intact
				$holds = false;
				foreach( \Nino\Filesystem::getFileContent( $appData, '/data/'. $name, [] ) as $entry )
					if( is_array( $entry ) === true && ( $entry['id'] ?? '' ) === $id )
						$holds = true;

				if( $holds === false )
					continue;

				\Nino\Filesystem::mutate( $appData, '/data/'. $name, function( array $entries ) use ( $id, &$removed ): array {

					$kept = [];

					foreach( $entries as $entry )
						if( is_array( $entry ) === true && ( $entry['id'] ?? '' ) === $id )
							$removed = true;
						else
							$kept[] = $entry;

					return $kept;
				} );

				return $removed;
			}

			return false;
		}

		/**
		 *	Every recorded submission within the retention window, oldest
		 *	first (as stored). An entry from before there was more than one
		 *	form is read as the first form's, so nothing a project already
		 *	has disappears from a panel that filters by form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function entries( array &$appData ): array {

			$entries	= [];
			$dir			= \Nino\Filesystem::path( $appData, '/data' );
			$files		= glob( $dir. '/forms.*.php' ) ?: [];
			$first		= ( self::forms( $appData )[0]['key'] ?? 'contact' );

			sort( $files );

			foreach( $files as $file ) {

				if( preg_match( '/^\d{4}-\d{2}$/', substr( basename( $file, '.php' ), 6 ) ) !== 1 )
					continue;

				foreach( \Nino\Filesystem::getFileContent( $appData, '/data/'. basename( $file ), [] ) as $entry )
					if( is_array( $entry ) === true )
						$entries[] = $entry + [ 'form' => $first, 'id' => '' ];
			}

			return $entries;
		}
	}

}
