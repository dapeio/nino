<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Modules\\Form				see _nino/Nino/Modules/Modules.php for the
 *											package-level docblock
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino\Modules {

	/**
	 *	Nino								A compact filesystembased php framework
	 *	Modules 						Optional Modules
	 *	Form								Owns the contact form's endpoint: it registers the route
	 *												POST /.form and hands every submission to \Nino\Form,
	 *												which is where the work is - which forms there are,
	 *												what a submission has to look like, the mail pair it
	 *												sends and the record it leaves.
	 *
	 *												The split is what lets a project have more than one
	 *												form without a second endpoint answering the same uri:
	 *												the forms live under '/nino/form/forms' in config.php
	 *												and anything may write them - a developer by hand, or
	 *												the catalogue's Forms feature through its builder. A
	 *												project that defines none gets \Nino\Form::DEFAULT_FORM,
	 *												the contact form this module has always been.
	 *
	 *												A module or a feature that wants to refuse a submission
	 *												- a spam guard, a rate limit - registers on this same
	 *												route callback ahead of this one and leaves a status
	 *												behind; \Nino\Form::handle() returns without sending or
	 *												writing anything - which is why there is no callback name
	 *												of its own for it. \Nino\Csrf refuses the same way one step
	 *												earlier, on the global /nino/http/response at priority 1.
	 *
	 *												The module costs a page without a form nothing: the
	 *												stylesheet and the script of the form, and the words the
	 *												script says, are put on a page that draws one - it says
	 *												so with [csrf], the token field every form that posts
	 *												carries (see draws()) - and the engine \Nino\Form loads
	 *												where a form is built or a submission arrives.
	 *
	 *												Submissions are visible in the Submissions panel
	 *												(Admin/Admin.php beside this), which reads
	 *												\Nino\Form::entries() - the same /data/forms.<Y-m>.php
	 *												files this framework has always written.
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Form {

		/**
		 *	The /_admin screen this module brings along - collected by
		 *	Admin::panels() through Modules::collect(), so it appears in the
		 *	workbench's rail exactly while this module is active and vanishes with it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Panel class names
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Form\Admin::class ];
		}

		/**
		 *	The bundles of the form: its stylesheet and its script, each its own file in /public/.cache/ - they follow the
		 *	style bundle and the script bundle of a page (see \Nino\Modules\Assets::follow()) and are linked from the pages
		 *	that draw a form only
		 */
		public const string STYLE		= '/.cache/form.css';
		public const string SCRIPT	= '/.cache/form.js';

		/**
		 *	The text keys the script of the form reads its words from, as the inline block of the page carries them
		 *	(see \Nino\Modules\Jstext::publish()): the words of the contact form, and the ones of the newsletter's signup, which the
		 *	same script drives
		 */
		public const array WORDS	= [ '/module/form/info/', '/feature/newsletter/info/' ];

		/**
		 *	Register the POST handler, the files of the form's two bundles and the way a page says it has a form. Nothing
		 *	of the form is put on a page here: draws() does it, for the pages that have one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			\Nino\Html::addAsset( $appData, self::STYLE, '/_nino/Nino/Modules/Form/assets/form.css' );
			\Nino\Html::addAsset( $appData, self::SCRIPT, '/_nino/Nino/Modules/Form/assets/form.js' );
			\Nino\Modules\Assets::follow( $appData, '/.cache/style.css', self::STYLE );
			\Nino\Modules\Assets::follow( $appData, '/.cache/script.js', self::SCRIPT );
			$appData['/nino/http/routes']['POST://.form'] = [ 'uri' => '/.form' ];
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', [ self::class, 'callbackResponse' ] );
			\Nino\Callbacks::registerCallback( $appData, \Nino\Modules\Csrf::FIELD, [ self::class, 'callbackField' ] );
		}

		/**
		 *	Say that the page of this request has a form: its stylesheet and its script are linked where the page links the
		 *	bundles it follows, and the words of the script are in the inline block of the page. This is the one place that
		 *	decides what "a page with a form" is. Every form that posts carries a token field, and [csrf] draws it, so
		 *	the field says it (callbackField()): the contact page of the starter, the catalogue's forms and a form a project
		 *	writes by hand need nothing more. A form that has no [csrf] - its token travels in a header - calls this
		 *	from the code that draws it. Called again, it does nothing
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function draws( array &$appData ): void {

			if( ( $appData['./nino/form/draws'] ?? false ) === true )
				return;

			$appData['./nino/form/draws'] = true;

			\Nino\Modules\Assets::need( $appData, self::STYLE );
			\Nino\Modules\Assets::need( $appData, self::SCRIPT );
			\Nino\Modules\Jstext::publish( $appData, self::WORDS );
		}

		/**
		 *	A token field was drawn: the page has a form
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		mixed			$field				The field, as the page gets it
		 *
		 *	@return 	void
		 */
		public static function callbackField( array &$appData, mixed $field ): void {
			self::draws( $appData );
		}

		/**
		 *	Hand the submission to the engine. Kept as a method of its own
		 *	rather than registering \Nino\Form::handle() directly: the route
		 *	callback is this module's, and a project that switches the module
		 *	off must take the endpoint with it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function callbackResponse( array &$appData, array &$request ): void {
			\Nino\Form::handle( $appData, $request );
		}
	}

}
