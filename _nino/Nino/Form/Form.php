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

		// How many submissions one month's file takes, and how many the whole
		// site takes in an hour, whatever the addresses they come from. The
		// mail cap counts per address, and an address costs a visitor nothing:
		// without these two a stream of submissions from many addresses grows
		// the month's file - which every submission and the panel read whole -
		// until the request no longer fits in memory. Set like the keys above,
		// by hand in config.php; a value outside 1..10000 is a hand edit gone
		// wrong and reads as the default, since neither cap can be switched off
		public const string MONTH_CAP	= '/nino/form/month-cap';
		public const string HOUR_CAP	= '/nino/form/hour-cap';

		public const int MONTH_CAP_DEFAULT	= 1000;
		public const int HOUR_CAP_DEFAULT		= 300;

		// The hour's counter: \Nino\Feature::count() under this name, one entry
		// for the whole site under this key (see capped())
		private const string HOUR_COUNTER	= 'form-hour';
		private const string HOUR_KEY			= 'site';
		private const int HOUR_WINDOW			= 3600;

		// The field types a form may declare. 'textarea' and 'select' are not
		// an <input type>, 'select' and 'radio' are the two that list
		// 'options'.
		//
		// What each posts is what the shared .nino-form script (Modules/Form/assets/form.js)
		// sends: a ticked 'checkbox' its value and an unticked one '', a 'radio'
		// group the value of whichever member is ticked, a 'date' the Y-m-d the
		// browser's own date input holds. A radio without one non-empty option
		// asks for an answer nobody can give, so normalize() leaves it out and
		// problems() says so; a select without options keeps taking any value
		public const array TYPES = [ 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'checkbox', 'radio', 'date' ];

		// Names a field may not take: the four the endpoint reads off the
		// post itself, and the four a record carries beside its values. A
		// form that wants a date asks for 'birthdate'
		public const array RESERVED = [ 'form', 'location', '_csrf', '_t', 'id', 'date', 'ip' ];

		// How a form says it is answered by the script of the page: the script sends this header with 1 on every
		// request, and a request without it comes from a browser that runs no script and posts the form natively
		public const string SCRIPT_HEADER = 'X-Nino-Form';

		// What a browser without the script is sent back with: the one query parameter of the page it came from, and
		// its value - one of these four words, the ones of the five lines of data-form-texts that are an outcome
		// ('required' is the script's own, before a request is made)
		public const string OUTCOME_PARAM = 'form';
		public const array OUTCOMES = [ 'success', 'invalid', 'email', 'error' ];

		// The posted field that names the form's own id on its page, which becomes the fragment of the address the
		// browser is sent back to; 'id' is no name a field may take (RESERVED), so no field can be it
		public const string ANCHOR_FIELD = 'id';
		private const string ANCHOR = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D';
		private const int REFERER_LENGTH = 2048;

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
				[ 'name' => 'name',			'label' => '[[/template/common/form/name]]',		'type' => 'text',			'required' => true,		'options' => [] ],
				[ 'name' => 'email',		'label' => '[[/template/common/form/email]]',		'type' => 'email',		'required' => true,		'options' => [] ],
				[ 'name' => 'cat',			'label' => '[[/template/common/form/reason]]',			'type' => 'text',			'required' => false,	'options' => [] ],
				[ 'name' => 'message',	'label' => '[[/template/common/form/message]]',	'type' => 'textarea',	'required' => true,		'options' => [] ],
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
		 *	The most submissions one month's file takes - '/nino/form/month-cap'
		 *	where a project sets one, MONTH_CAP_DEFAULT otherwise
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function monthCap( array &$appData ): int {

			$cap = (int) ( $appData[ self::MONTH_CAP ] ?? self::MONTH_CAP_DEFAULT );

			return ( $cap >= 1 && $cap <= 10000 ) ? $cap : self::MONTH_CAP_DEFAULT;
		}

		/**
		 *	The most submissions the whole site takes in an hour -
		 *	'/nino/form/hour-cap' where a project sets one, HOUR_CAP_DEFAULT
		 *	otherwise
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	int
		 */
		public static function hourCap( array &$appData ): int {

			$cap = (int) ( $appData[ self::HOUR_CAP ] ?? self::HOUR_CAP_DEFAULT );

			return ( $cap >= 1 && $cap <= 10000 ) ? $cap : self::HOUR_CAP_DEFAULT;
		}

		/**
		 *	Which cap, if any, is reached right now - read, not counted. The
		 *	hour's cap holds for every submission and refuses it. The month's
		 *	is reached only where submissions are recorded, since a site that
		 *	keeps none has no file to fill, and refuses none: a submission
		 *	that is mailed is taken and not recorded (see handle())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	string									'hour', 'month' or '' where both have room
		 */
		public static function capped( array &$appData ): string {

			if( \Nino\Feature::spent( $appData, self::HOUR_COUNTER, self::HOUR_KEY, self::hourCap( $appData ) ) === true )
				return 'hour';

			return self::monthFull( $appData ) === true ? 'month' : '';
		}

		/**
		 *	Whether this month's file has no place left for a submission -
		 *	never where nothing is recorded
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	bool
		 */
		public static function monthFull( array &$appData ): bool {

			return self::stores( $appData ) === true
				&& count( \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] ) ) >= self::monthCap( $appData );
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

			return self::_inspect( $entry )[0];
		}

		/**
		 *	What normalize() leaves out of a definition or replaces in it, one
		 *	entry for each of the cases below: the form is not usable (no key,
		 *	no field left), a field is dropped (not an array, a name that is no
		 *	identifier or a reserved one, a name already taken, a radio with no
		 *	option) or kept with another value (an unknown type, an address
		 *	that is none, a template path that is none). A stored definition
		 *	is read quietly;
		 *	a writer that takes a definition from a person asks this first and
		 *	says where it went wrong, rather than saving something that is not
		 *	what was typed. Both answer from one routine, so they cannot
		 *	disagree about those cases; normalize() also lowercases the key,
		 *	cuts a long label, option, name or subject, and drops an option that
		 *	is empty or no string, none of which is a problem
		 *
		 *	The codes: key, field, name, reserved, duplicate, type, options,
		 *	fields, to, ownerTemplate, userTemplate
		 *
		 *	@param		array 		$entry				A definition as stored or as posted
		 *
		 *	@return 	array									[ [ 'field' => int|null, 'code' => string ], ... ] -
		 *															field is the key in the fields list, null for a
		 *															problem of the definition itself; [] for a clean one
		 */
		public static function problems( array $entry ): array {

			return self::_inspect( $entry )[1];
		}

		/**
		 *	The one routine behind normalize() and problems()
		 *
		 *	@param		array 		$entry				A definition as stored or as posted
		 *
		 *	@return 	array									[ the normalized form or null, the problems ]
		 */
		private static function _inspect( array $entry ): array {

			$problems = [];
			$key = is_string( $entry['key'] ?? null ) === true ? strtolower( trim( $entry['key'] ) ) : '';

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $key ) !== 1 )
				$problems[] = [ 'field' => null, 'code' => 'key' ];

			$fields	= [];
			$seen		= [];

			foreach( (array) ( $entry['fields'] ?? [] ) as $index => $field ) {

				$at = is_int( $index ) === true ? $index : null;

				if( is_array( $field ) === false ) {
					$problems[] = [ 'field' => $at, 'code' => 'field' ];
					continue;
				}

				$name = is_string( $field['name'] ?? null ) === true ? trim( $field['name'] ) : '';
				$type = is_string( $field['type'] ?? null ) === true ? $field['type'] : 'text';

				// A field name becomes a posted key, a column of an export and
				// a placeholder in the mail - so it is an identifier, not a
				// label, and never one of the names something else owns. At
				// most 64 characters, the same bound posted() reads keys with:
				// a longer name described a field that could never be submitted
				if( preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $name ) !== 1 ) {
					$problems[] = [ 'field' => $at, 'code' => 'name' ];
					continue;
				}

				if( in_array( $name, self::RESERVED, true ) === true ) {
					$problems[] = [ 'field' => $at, 'code' => 'reserved' ];
					continue;
				}

				if( in_array( $name, $seen, true ) === true ) {
					$problems[] = [ 'field' => $at, 'code' => 'duplicate' ];
					continue;
				}

				$seen[] = $name;

				// An absent type is a text field; one that is there and not on
				// the list is repaired to one, which problems() reports
				if( ( $field['type'] ?? null ) !== null && ( is_string( $field['type'] ) === false || in_array( $type, self::TYPES, true ) === false ) )
					$problems[] = [ 'field' => $at, 'code' => 'type' ];

				$options = [];
				foreach( (array) ( $field['options'] ?? [] ) as $option )
					if( is_string( $option ) === true && trim( $option ) !== '' )
						$options[] = mb_strcut( trim( $option ), 0, 200, 'UTF-8' );

				if( $type === 'radio' && $options === [] ) {
					$problems[] = [ 'field' => $at, 'code' => 'options' ];
					continue;
				}

				$label = is_string( $field['label'] ?? null ) === true ? trim( $field['label'] ) : '';

				$fields[] = [
					'name'			=> $name,
					'label'			=> $label === '' ? $name : mb_strcut( $label, 0, 200, 'UTF-8' ),
					'type'			=> in_array( $type, self::TYPES, true ) === true ? $type : 'text',
					'required'	=> ( $field['required'] ?? false ) === true,
					'options'		=> $options,
				];
			}

			if( $fields === [] )
				$problems[] = [ 'field' => null, 'code' => 'fields' ];

			$to		= is_string( $entry['to'] ?? null ) === true ? trim( $entry['to'] ) : '';
			$name	= is_string( $entry['name'] ?? null ) === true ? trim( $entry['name'] ) : '';

			if( $to !== '' && filter_var( $to, FILTER_VALIDATE_EMAIL ) === false )
				$problems[] = [ 'field' => null, 'code' => 'to' ];

			$templates = [];

			foreach( [ 'ownerTemplate' => '/templates/mail-owner', 'userTemplate' => '/templates/mail-user' ] as $role => $default ) {

				$given						= is_string( $entry[ $role ] ?? null ) === true ? trim( $entry[ $role ] ) : '';
				$templates[ $role ]	= self::_template( $given, $default );

				if( $given !== '' && $templates[ $role ] !== $given )
					$problems[] = [ 'field' => null, 'code' => $role ];
			}

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $key ) !== 1 || $fields === [] )
				return [ null, $problems ];

			return [ [
				'key'						=> $key,
				'name'					=> $name === '' ? $key : mb_strcut( $name, 0, 100, 'UTF-8' ),
				'to'						=> filter_var( $to, FILTER_VALIDATE_EMAIL ) === false ? '' : $to,
				'subject'				=> is_string( $entry['subject'] ?? null ) === true ? mb_strcut( trim( $entry['subject'] ), 0, 200, 'UTF-8' ) : '',
				'confirm'				=> ( $entry['confirm'] ?? false ) === true,
				'ownerTemplate'	=> $templates['ownerTemplate'],
				'userTemplate'	=> $templates['userTemplate'],
				'fields'				=> $fields,
			], $problems ];
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
		 *	Every posted value, once - name-checked, trimmed and capped. The
		 *	cap is in bytes and lands on a character boundary: a cut through
		 *	the middle of a multibyte character left invalid utf-8, which the
		 *	escaping on the way out answered with nothing at all. A
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
					$posted[$key] = mb_strcut( trim( $value ), 0, self::MAX_FIELD_LENGTH, 'UTF-8' );

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
				'radio'		=> in_array( $value, $field['options'], true ) === true,
				'date'		=> ( $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) ) !== false && $date->format( 'Y-m-d' ) === $value,
				default		=> true,
			};
		}

		/**
		 *	Whether the CSRF guard refused this request. The answer is \Nino\Csrf::blocked()'s, which every response
		 *	of the kernel asks and which is why it lives there: this class is the engine of the contact form and loads
		 *	where a form is drawn or posted, not on every page. A callback of a route that writes or sends on a
		 *	state-changing request asks it first, from here or from there - the two count as one
		 *
		 *	@param		array 		$request			Current server request
		 *
		 *	@return 	bool
		 */
		public static function blocked( array $request ): bool {
			return \Nino\Csrf::blocked( $request );
		}

		/**
		 *	Start counting the asks of blocked() about a refused request, and say whether it has been asked since:
		 *	\Nino\Csrf::watched()
		 *
		 *	@param		bool			$start				True to start over, false to read
		 *
		 *	@return 	bool										Whether blocked() was asked about a refused request since the start
		 */
		public static function watched( bool $start ): bool {
			return \Nino\Csrf::watched( $start );
		}

		/**
		 *	The whole endpoint: which form this is, whether the submission
		 *	may pass, the mail pair it sends and the record it leaves.
		 *	\Nino\Modules\Form hands POST /.form here; a listener registered
		 *	ahead of that module on the same callback refuses a submission
		 *	simply by setting a status other than 200 (\Nino\Csrf refuses the
		 *	same way one step earlier, on the global /nino/http/response at
		 *	priority 1), and this
		 *	returns without sending or writing anything. What the post came to is
		 *	then told to the visitor by answer(): the status and the body for the
		 *	script of the page, a redirect back to the page for a browser without it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function handle( array &$appData, array &$request ): void {

			$outcome = self::_process( $appData, $request );

			if( $outcome !== '' )
				self::answer( $appData, $request, $outcome );
		}

		/**
		 *	The work of handle(): which form this is, whether the submission may pass, the mail pair it sends and the
		 *	record it leaves, with the status and the body the script reads
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	string										The outcome a browser without the script is told (one of OUTCOMES), '' where the
		 *																	request is left as a guard or another callback made it: the refusal of
		 *																	the csrf guard, and a status that is no refusal
		 */
		private static function _process( array &$appData, array &$request ): string {

			// Respect a rejection from an earlier callback: the global Csrf
			// guard sets a flag of its own, anything else simply left a status
			// behind. Two checks rather than one because the flag survives a
			// later handler resetting the status, which is what it is for.
			// The csrf refusal stays a 403 for a browser without the script as
			// well: no redirect is made for a post that carried no valid token
			if( self::blocked( $request ) === true )
				return '';

			$status = (int) ( $request['/nino/http/response']['statusCode'] ?? 200 );

			if( $status !== 200 )
				return $status >= 400 ? 'error' : '';

			$posted	= self::posted();
			$form		= self::form( $appData, (string) ( $posted['form'] ?? '' ) );

			// A key no form has: a page pointing at a form that was renamed,
			// which the visitor can do nothing about and which is not spam.
			// The key goes to form() as posted - a shape check ahead of it
			// used to collapse a miss to '', which is the first form, so a
			// page posting 'Quote' was mailed and recorded as the contact form
			if( $form === null ) {
				$request['/nino/http/response']['statusCode'] = 404;
				return 'error';
			}

			// The honeypot no person sees. 418 here and for every other
			// refusal a guard makes: the shared .nino-form script shows one
			// generic message for anything that is not 200 or 400, so a bot
			// never learns which check it tripped - and a browser without the
			// script is sent back with the same generic word, the one the
			// script says for 418, where 'success' would have told the bot
			// something the status does not
			if( ( $posted['location'] ?? '' ) !== '' ) {
				$request['/nino/http/response']['statusCode'] = 418;
				return 'error';
			}

			$values = self::validate( $form, $posted );

			if( $values === false ) {
				$request['/nino/http/response']['statusCode'] = 400;
				return self::_refusal( $form );
			}

			// The hour's cap of the site is reached: refused as the mail cap
			// refuses, before any mail goes out - the owner's mailbox is what
			// it protects, and the panel says it is reached. A full month does
			// not close the form: the mail still goes out, see below
			if( self::capped( $appData ) === 'hour' ) {
				$request['/nino/http/response']['statusCode'] = 429;
				return 'error';
			}

			$sent = self::send( $appData, $form, $values );

			// A submission whose mail the cap refused is not recorded - one
			// entry per request regardless would turn a throttled flood into
			// unthrottled disk growth from an unauthenticated endpoint - and
			// not answered ok either, which it used to be: nothing had gone
			// out and nothing was kept, and the visitor waited for a reply to
			// a message nobody received. A 429, which the shared .nino-form
			// script shows as the generic message, so the visitor tries later
			if( \Nino\Mail::rateLimited( $appData ) === true ) {
				$request['/nino/http/response']['statusCode'] = 429;
				return 'error';
			}

			// Counted once the mail layer has taken it, so that an address the
			// per-address cap refuses cannot use up the site's hour
			\Nino\Feature::count( $appData, self::HOUR_COUNTER, self::HOUR_KEY, self::hourCap( $appData ), self::HOUR_WINDOW );

			// What the visitor is told rests on the owner's mail alone - the
			// one that is the message. A confirmation nobody could deliver
			// (a mistyped address) changes nothing about that: the owner has
			// the inquiry, and a visitor sent back to the form would only
			// send it twice. Where the mail layer does not say (a transport
			// that left no result) the aggregate stands in for it
			$ownerSent = ( $appData['./nino/mail/results'][0] ?? $sent ) === true;

			// The inquiry is recorded whether or not its mail went out - it
			// did happen, it is in the panel, and losing it would be worse
			$monthFull = self::monthFull( $appData );

			if( self::stores( $appData ) === true )
				self::record( $appData, $form, $values );

			// A full month records nothing (see record()), so where the owner's
			// mail did not go out either nothing has the inquiry: refused as a
			// throttled one is, for the visitor to try again later, rather
			// than a 500 that reads as a failure of the mail alone
			if( $ownerSent === false && $monthFull === true ) {
				$request['/nino/http/response']['statusCode'] = 429;
				return 'error';
			}

			// But "sent" is not what the visitor is told when the owner's mail
			// did not go out - it used to be, and nobody learned that a form
			// had stopped reaching them. A 500, the same generic message on
			// the page (and the dashboard says why: \Nino\Mail::failure())
			if( $ownerSent === false ) {
				$request['/nino/http/response']['statusCode'] = 500;
				return 'error';
			}

			$request['/nino/http/response']['statusCode']	= 200;
			$request['/nino/http/response']['body']				= [ 'status' => 'ok' ];

			return 'success';
		}

		/**
		 *	Which of the words 'invalid' and 'email' tells a visitor the entries of a form were refused: the answer the
		 *	script of the page gives a 400 (form.js, formResponse()), so that a browser with it and one without say the
		 *	same - 'invalid' where the form holds a url, number, date, select, radio or checkbox or asks for no address,
		 *	'email' otherwise
		 *
		 *	@param		array 		$form					One normalized form
		 *
		 *	@return 	string
		 */
		private static function _refusal( array $form ): string {

			$types = array_column( $form['fields'], 'type' );

			if( array_intersect( $types, [ 'url', 'number', 'date', 'radio', 'checkbox', 'select' ] ) !== [] || in_array( 'email', $types, true ) === false )
				return 'invalid';

			return 'email';
		}

		/**
		 *	Answer a post of a form: to the script of the page with the status and the body the route has set, and to a
		 *	browser without it - a request that does not carry SCRIPT_HEADER - with a 303 back to the page the form
		 *	came from, so that the visitor is not left on an empty page. The address is a path of this site and
		 *	never anything else: the path of the Referer where its scheme, host and port are the site's own, else the
		 *	start page; the query of it is kept but for a parameter of the name OUTCOME_PARAM, which is set to the
		 *	outcome; and the fragment is the form's own id, where the post carries one in ANCHOR_FIELD and it is
		 *	the shape of an id. An outcome that is none of OUTCOMES is 'error'. A request the csrf guard refused
		 *	is left as the 403 it is, whatever is asked here. \Nino\Form::handle() calls this for the contact form; a
		 *	feature that serves a form of its own calls it from its route callback when the entries are settled,
		 *	after it has set the status and the body for the script, and a page reads the outcome back with
		 *	outcome()
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$outcome			One of OUTCOMES
		 *
		 *	@return 	void
		 */
		public static function answer( array &$appData, array &$request, string $outcome ): void {

			if( self::blocked( $request ) === true || ( $request['/nino/http/request']['header'][ self::SCRIPT_HEADER ] ?? '' ) === '1' )
				return;

			if( in_array( $outcome, self::OUTCOMES, true ) === false )
				$outcome = 'error';

			$page		= self::_page( $appData, $request );
			$query	= [];

			foreach( explode( '&', $page['query'] ) as $pair )
				if( $pair !== '' && urldecode( explode( '=', $pair, 2 )[0] ) !== self::OUTCOME_PARAM
					&& str_starts_with( urldecode( explode( '=', $pair, 2 )[0] ), self::OUTCOME_PARAM. '[' ) === false )
					$query[] = $pair;

			$query[]	= self::OUTCOME_PARAM. '='. $outcome;
			$anchor		= (string) ( self::posted()[ self::ANCHOR_FIELD ] ?? '' );

			$anchor		= preg_match( self::ANCHOR, $anchor ) === 1 ? '#'. $anchor : '';
			$to				= $page['path']. '?'. implode( '&', $query ). $anchor;

			// A query the page had may hold what no Location of this site may - a colon ahead of the first slash reads as a
			// scheme (\Nino\Http::isSitePath()) - and then the page is named without it: the visitor is sent back all the same
			if( \Nino\Http::isSitePath( $to ) === false )
				$to = $page['path']. '?'. self::OUTCOME_PARAM. '='. $outcome. $anchor;

			\Nino\Http::redirect( $appData, $request, $to, 303 );
		}

		/**
		 *	The outcome a visitor was sent back to a page with by answer()
		 *
		 *	@param		array 		$request			Current server request
		 *
		 *	@return 	string										One of OUTCOMES, '' where the address names none
		 */
		public static function outcome( array $request ): string {

			$word = $request['/nino/http/request']['query'][ self::OUTCOME_PARAM ] ?? '';

			return is_string( $word ) === true && in_array( $word, self::OUTCOMES, true ) === true ? $word : '';
		}

		/**
		 *	The page a post came from, as a path of this site: the path and the query of the Referer where it is an
		 *	address of this very site - the same scheme, host and port the request was made to - and the start page
		 *	otherwise. Anything that could put a Location somewhere else is not trusted but sent to the start page:
		 *	a control character, a space or a backslash anywhere in it, credentials, a path that is not under the
		 *	project's directory, one that is no path of this site (\Nino\Http::isSitePath()), and one too long
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$request			Current server request
		 *
		 *	@return 	array											'path' => below the project's directory, 'query' => without the '?'
		 */
		private static function _page( array &$appData, array $request ): array {

			$start	= [ 'path' => '/', 'query' => '' ];
			$header	= $request['/nino/http/request']['header'] ?? [];
			$from		= $header['Referer'] ?? '';
			$host		= $header['Host'] ?? '';

			if( is_string( $from ) === false || is_string( $host ) === false || $from === '' || $host === '' || strlen( $from ) > self::REFERER_LENGTH
				|| preg_match( '/[\x00-\x20\x7f\\\\]/', $from. $host ) === 1 )
				return $start;

			$secure	= ( $appData['/nino/session/force-secure-cookie'] ?? false ) === true || ( ( $_SERVER['HTTPS'] ?? '' ) !== '' && ( $_SERVER['HTTPS'] ?? '' ) !== 'off' );
			$own		= parse_url( ( $secure === true ? 'https' : 'http' ). '://'. $host );
			$url		= parse_url( $from );

			if( is_array( $own ) === false || is_array( $url ) === false || isset( $own['host'], $url['host'], $url['scheme'] ) === false
				|| isset( $url['user'] ) === true || isset( $url['pass'] ) === true )
				return $start;

			$port = static fn( array $parts, string $scheme ): int => (int) ( $parts['port'] ?? ( $scheme === 'https' ? 443 : 80 ) );

			if( strtolower( $url['scheme'] ) !== ( $secure === true ? 'https' : 'http' ) || strtolower( $url['host'] ) !== strtolower( $own['host'] )
				|| $port( $url, strtolower( $url['scheme'] ) ) !== $port( $own, $secure === true ? 'https' : 'http' ) )
				return $start;

			$path	= (string) ( $url['path'] ?? '/' );
			$dir	= rtrim( \Nino\Filesystem::getDir( $appData ), '/' );

			if( $dir !== '' )
				$path = $path === $dir ? '/' : ( str_starts_with( $path, $dir. '/' ) === true ? substr( $path, strlen( $dir ) ) : '' );

			return \Nino\Http::isSitePath( $path ) === true ? [ 'path' => $path, 'query' => (string) ( $url['query'] ?? '' ) ] : $start;
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
		 *	@return 	bool										Whether every mail went out - false for
		 *																	one the cap refused (see handle()) as
		 *																	much as for one no transport took
		 */
		public static function send( array &$appData, array $form, array $values ): bool {

			// [[/nino/dir]] and [[/nino/public]] are ordinary fills by the time
			// this runs - \Nino\request() registers them before
			// Http::response(), precisely so a mail rendered in here resolves
			// them instead of shipping the literal
			$owner = $form['to'] !== '' ? $form['to'] : \Nino\Html::putFills( $appData, '[[/project/mail/address/owner]]', [] );
			$reply = self::_firstEmail( $form, $values );

			/*	useLocale(), not setCurrentLocale(): the owner's mail goes out
				in the site's native locale, and that is this render's business
				and nobody's choice. setCurrentLocale() writes what it is given
				into the visitor's session, so both of these did - and the one
				below wrote whatever getCurrentLocale() answered, which for a
				visitor who never picked a locale is the project's default.
				Sending one inquiry pinned that default in their session, which
				Locales::init() then reads back and lets win over the project's
				own: change the native locale afterwards and the visitor who
				once wrote in keeps getting the old one. init()'s own comment
				states the rule this broke	*/
			$visitorLocale = \Nino\Locales::getCurrentLocale( $appData );
			\Nino\Locales::useLocale( $appData, \Nino\Locales::getNativeLocale( $appData ) );

			$mails = [ [
				'to'			=> $owner,
				'subject'	=> self::_subject( $appData, $form['subject'] !== '' ? $form['subject'] : '[[/module/form/subject/owner]]' ),
				'body'		=> self::render( $appData, $form, $values, $form['ownerTemplate'] ),
				'replyTo'	=> $reply !== '' ? $reply : $owner,
			] ];

			\Nino\Locales::useLocale( $appData, $visitorLocale );

			if( $form['confirm'] === true && $reply !== '' )
				$mails[] = [
					'to'			=> $reply,
					'subject'	=> self::_subject( $appData, '[[/module/form/subject/user]]' ),
					'body'		=> self::render( $appData, $form, $values, $form['userTemplate'] ),
					'replyTo'	=> $owner,
				];

			return \Nino\Mail::sendAll( $appData, $mails );
		}

		/**
		 *	The subject of a mail: the fills of the line resolved, and the result read as the characters it means. A word
		 *	of the Text panel is html - an apostrophe is &#039; there, a bracket &#91; - and a subject is plain text, so
		 *	the entities would reach the reader as they are written. An entity can spell a line break, so every control
		 *	character of the result is a space: no transport is handed a subject of two lines
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$line					The subject as the form or the module has it, fills included
		 *
		 *	@return 	string
		 */
		private static function _subject( array &$appData, string $line ): string {
			return trim( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', html_entity_decode( \Nino\Html::putFills( $appData, $line, [] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ?? '' );
		}

		/**
		 *	One mail body: the template rendered, then the placeholders
		 *	replaced in the result - that order, so a submitted value can
		 *	never be read as a fill, a shortcode or a template include - and
		 *	replaced in one pass (strtr(), not str_replace() pair by pair),
		 *	so a placeholder inside a submitted value is never read as one
		 *	either, not even inside the [[fields]] table.
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

			// ENT_SUBSTITUTE: a byte that is not utf-8 - a client posting
			// latin-1 - becomes the replacement character. Without it
			// htmlspecialchars() answers the whole value with '', and the
			// inquiry reached the owner as nothing
			$safe = static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			$rows = '';

			foreach( $form['fields'] as $field )
				$rows .= '<tr><th>'. \Nino\Html::putFills( $appData, $field['label'], [] ). '</th><td>'
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

			// The path stands in a call that is read: only the name of a template may stand there, whoever calls
			if( self::_template( $template, '' ) === '' )
				return '';

			return strtr( \Nino\Html::renderHtml( $appData, '[template '. $template. ']' ), $fills );
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

				// ENT_SUBSTITUTE for the same reason as in render()
				foreach( $values as $name => $value )
					$entry[$name] = htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

				$entry['ip'] = \Nino\Http::getClientIp( $appData );

				// A full month's file is left as it is: read under the lock, so
				// that parallel submissions cannot both take the last place
				$cap = self::monthCap( $appData );

				\Nino\Filesystem::mutate( $appData, '/data/forms.'. date( 'Y-m' ). '.php', function( array $entries ) use ( $entry, $cap ): ?array {

					if( count( $entries ) >= $cap )
						return null;

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
		 *	window than the admin activity log (see \Nino\Modules\Logs\Admin):
		 *	these are real business inquiries, not just an operational safety
		 *	net
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
		 *	Every recorded submission on disk, oldest first (as stored):
		 *	every /data/forms.<Y-m>.php there is, with no cutoff of its own.
		 *
		 *	The retention window is not this method's - prune() enforces it,
		 *	and record() is the only thing here that calls it. A month past
		 *	the window therefore keeps showing up here until the next
		 *	submission sweeps it, and on a form nobody submits to any more it
		 *	keeps showing up for good. A caller that has to honour the window
		 *	either prunes first or filters on the entry's own 'date'.
		 *
		 *	An entry from before there was more than one form is read as the
		 *	first form's, so nothing a project already has disappears from a
		 *	panel that filters by form
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
