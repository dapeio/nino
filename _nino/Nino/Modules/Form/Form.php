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
	 *												writing anything. \Nino\Csrf::init() does exactly that
	 *												at priority 1, and is why there is no callback name of
	 *												its own for it.
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
		 *	editor exactly while this module is active and vanishes with it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Panel class names
		 */
		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Form\Admin::class ];
		}

		/**
		 *	Register the POST handler
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	void
		 */
		public static function init( array &$appData ): void {
			$appData['/nino/http/routes']['POST://.form'] = [ 'uri' => '/.form' ];
			\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', [ self::class, 'callbackResponse' ] );
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
