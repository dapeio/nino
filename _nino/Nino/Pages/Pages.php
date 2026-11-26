<?php
declare(strict_types=1);
/**
 *	Nino								A compact filesystembased php framework
 *	Pages				The pages of a site: put one, list them
 *
 *	@package						Dape/Nino
 *	@author							David Perchermeier <mail@dape.io>
 *	@link								https://github.com/dapeio/nino
 */
namespace Nino {

	/**
	 *	Pages - a page is a route that draws a template of templates/page-*.tpl, with a name and a title in every language of
	 *	the site, an address for each language and a place in the menus. put() writes all of that in one call, under the one
	 *	lock of config.php, and all() reads it back; the Routes panel of the workbench and a feature that makes pages both
	 *	call them, and what a page has to be to be written is decided here and nowhere else.
	 *
	 *	The functions know no account: whoever calls them has decided that the account may. A panel checks its permission
	 *	(\Nino\Admin\Admin::guardPerm()) before it calls put(), and a feature does the same with the right of its own.
	 *
	 *	A page is the routes of one Element-URI: one route when the page has one address for every language, a route for each
	 *	language that has an address of its own - with its 'locale' - and a route without a 'locale' for the address that the
	 *	languages without one share. The names of the page, its title, its description and its address in each language are
	 *	the keys /_nino/webpage<uri>/{name,title,description,uri} of the text files
	 *
	 *	@package						Dape/Nino
	 *	@author							David Perchermeier <mail@dape.io>
	 *	@link								https://github.com/dapeio/nino
	 */
	class Pages {

		// The name of a page template: page-, then words of lower case letters and digits joined by hyphens - the shape of a name
		// a template can have as the category of a text key, with the prefix that makes it a page
		public const string TEMPLATE = '#^page-[a-z0-9]+(?:-[a-z0-9]+)*$#D';

		// The longest name of a template this makes: a file name has 255 bytes, and what the name is joined to - the extension, a
		// temporary name beside it - has to fit as well
		public const int MAX_NAME = 100;

		/**
		 *	Make a page, or save an existing one - the one whose Element-URI is $page['originalUri']; empty for a new page.
		 *	All of it or none of it: the routes of the page, the names, the title, the description and the address of each
		 *	language, the entry in the menus and - where the caller hands the source of a template over - the template file are
		 *	written under the one lock of config.php, which is held from the first check to the last write: the routes are the
		 *	last of the writes, and a write that fails takes back the ones before it - byte for byte, see _write() - so that
		 *	neither a file nor a route nor a key of the text is left of a page that was refused.
		 *
		 *	A page that is saved a second time keeps what its first route carries and the form does not edit - a 'maintenance' => false,
		 *	a 'header' - on every route of the page, and its priority in the menus; a hand-written body that picks the template
		 *	by a fill is kept as it is. A page that is renamed or given other addresses stays at the slot of its first route,
		 *	which is what breaks a tie between two equal priorities in a menu.
		 *
		 *	A save writes the files that hold what it changes and no other: a file whose content, as it would be written, is what it holds
		 *	already is left alone - not opened for writing, its bytes, its mtime and its inode stay -, so that a page that is saved as it
		 *	stands changes no file at all and one text of one language changes the file of that language. The comparison is made under the
		 *	lock, on the stored form, and the answer is that of a page that is written.
		 *
		 *	The template is a file of templates/ that is there: put() does not make one. Where $page['source'] is given it is
		 *	made, from the source, as the one file of that name - the name is a page's (TEMPLATE, at most MAX_NAME long, and none
		 *	the kernel draws by itself, see reserved()), the file is not there yet, and the source is no larger than
		 *	\Nino\Modules\Template::MAX_BYTES, the largest template that is drawn.
		 *
		 *	The source is not checked. It is written as the bytes it is and read by nobody here: a call in it is drawn when the
		 *	page is, with the rules every template has - free html and a call without a schema in it are drawn for every
		 *	visitor. Whoever hands over a source that an account or another party wrote checks it first, with the rules of the
		 *	right that account has (a feature: \Nino\Components::check() and its own rule), and checks the right itself.
		 *
		 *	$page is
		 *		uri 				the Element-URI of the page, eg. '/about' - the identity its texts are filed under
		 *		originalUri	the Element-URI of the page that is saved, '' (or none) for a new one
		 *		httpUris		the addresses: a language => its address, and '' => the address of every language that has none of
		 *								its own; each is a path that is no reserved one and no other route has
		 *		template 		the name of a template of templates/ - or of the one that source makes
		 *		text 				language => [ name, title, description ], a name and a title for every language of the site
		 *		navs 				the menus the page is in, eg. [ 'main' ]; a menu that is not registered is left out
		 *		statusCode 	the status the route answers; 200 where it is none
		 *		source 			the html+ of a new template, or none
		 *		confirm 		whether the save may leave the site without its home page or without its 404 page (see leaves()):
		 *								absent, the question is not asked and the page is written as it is posted; false, such a save is
		 *								refused (409, routes_leaves_home, routes_leaves_not_found or, for both, routes_leaves_both) and nothing is written; true, it is
		 *								written. Only the Routes panel asks - a caller that makes a new page has nothing to leave
		 *		stamp 			the state of the page as the form read it (see stamp()), for a page that exists: absent, the page is
		 *								written as it is posted - a page that is gone since is made anew -, which is what every caller but the
		 *								Routes panel does; a string, it is compared with the state of the page under the lock, and a page that
		 *								was changed since is refused (409, routes_conflict), a page that was removed since is refused (409,
		 *								routes_conflict_gone), and nothing is written; anything but a string, or a stamp for a new page, is
		 *								refused (400, routes_invalid_stamp)
		 *
		 *	The answer is that of a page that is written: [ 'uri' => the Element-URI, 'routes' => the route keys in the order
		 *	they stand in, 'template' => the name of the template or '' where the body of the page is a hand-written one,
		 *	'created' => whether the template file was made by this call ]. The answer of a page that is refused is [ 'error'
		 *	=> [ 'status' => the HTTP status that fits, 'message' => a sentence for a log, 'code' => the code a panel makes a
		 *	sentence of in the language of the account ('' where there is none), 'params' => what the sentence names, 'field'
		 *	=> the field of the form that is to blame, '' for none ] ] - the arguments of \Nino\Http::fail() - and nothing is
		 *	written. A failed write of config.php is such an answer as well, with the status 500 and no code. Whether the caller
		 *	may ask is for the caller to say; this knows no account.
		 *
		 *	The page that is written with the route GET://404 has the status 404, whatever the caller posts: a save that gives it
		 *	another one is refused (400, routes_not_found_status, field statusCode), and no confirm lifts that - every address
		 *	without a page would be answered with that status and the 404 page as a page that is there. It makes no difference
		 *	whether the page held the route before or takes it with this save. A save that takes the route GET://404 away from
		 *	the page in the same call holds it to nothing afterwards.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$page					The page, see above
		 *	@param		string|null	&$stamp			(reference) Set to the state of the page as this call wrote it (see stamp()), computed under the locks it wrote under: what a caller that answers a form takes as the state the form has, and not what a read after the call finds, which another writer may have changed meanwhile. Null where the call is refused
		 *
		 *	@return 	array										The page that was written, or its error
		 */
		public static function put( array &$appData, array $page, ?string &$stamp = null ): array {

			$stamp = null;


			// Read, checked and written under one lock: the whole route array is read, decided against and written back, so
			// two saves of two pages at the same moment would each write their own copy and the second would drop the first
			// one's page. writeContentData() locks for its own write only, which is too late to help - the decision is what
			// has to be under the lock. Taking it a second time inside is nothing to do (see lockFile())
			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
				return self::_refuse( 500, 'could not lock config.php for writing' );

			try {
				return self::_put( $appData, $page, [], $stamp );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	Take a page away whole: its routes in every language - and with them its entries in every menu -, its template, its
		 *	texts (the keys /template/<template>/... of every language and of global.php, with their format and limit in meta.php,
		 *	and the details of the page, /_nino/webpage<uri>/{name,title,description,uri}), its picture slots and the files of those
		 *	slots that no other slot reads. All of it or none of it, under the one lock of config.php, which is held from the first
		 *	check to the last write: config.php is written under it and does not let it go. What anything else reads of the page - a
		 *	text, a slot, a detail named in a template, a route or the value of a text - stays and is named in the answer under
		 *	'kept'; what links to it - with page:<uri>, or by reading its address, the detail .../uri - is named under 'linked' and holds
		 *	nothing. A template that no page draws but that
		 *	has its words, its slots or pictures below its folder, or is a file with no route, is a leftover and is taken as a page with
		 *	no routes ('remnant' => true). uses() asks all this without the act.
		 *
		 *	The order is what keeps the site whole at every step: the routes and the slots go first, in the one write of
		 *	config.php, so that no visitor is sent to a page that is half gone; then the texts, meta.php and the blacklist; the
		 *	template is the last of the writes that can be given back - a route never points at a template that is not there -,
		 *	and the files of the pictures are removed after it, when nothing can fail any more that would need them. Every file
		 *	that is written is given back byte for byte where a later step fails (see _undo()): the bytes it had are written
		 *	again where it still holds what this call wrote, and where somebody changed it meanwhile only what this call took out
		 *	is put in again, and nothing of theirs is touched. The files of the pictures are the last step and stay as they are
		 *	where one of them cannot be removed - the page is gone then, and what is left is named in the answer.
		 *
		 *	A page is taken by the name of its template - page- and lower case words, and no other template, as a leftover too - and
		 *	only the page whose template it is: a name no page draws and nothing is left of is refused (routes_unknown_page, 404). Refused as well, with 409 and nothing written:
		 *		routes_remove_home			the page that has the address '/'
		 *		routes_remove_not_found	the page of the status 404 - the route GET://404, or the Element-URI /404 -, which every
		 *														address that has no page is answered with
		 *		routes_remove_runtime		a page that a module or a feature routes by itself and no config.php has - the
		 *														imprint and the privacy policy among them -, and a template the kernel draws by
		 *														itself (reserved()): the code that routes it asks for it at every request
		 *		routes_remove_shared		a template that more than one page draws: the call takes one page and the template
		 *														would be gone from the others - the pages are named, and the caller takes the
		 *														others' routes away first
		 *		routes_remove_included	a template that another template or another route names in a [template ...] call:
		 *														that page would draw nothing where this one stood
		 *	The rule in one sentence: a page may go when the site does not ask for it at every request, nobody else draws or
		 *	includes its template, and it is not the page that answers '/' or '404'.
		 *
		 *	The picture files that go are the ones whose name is the slot's own - the uri of the slot without its first slash, a
		 *	dot and the rest, the name \Nino\Images::process() gives - and every file below images/template/<template>/, the folder
		 *	of the page, that no remaining slot names (\Nino\Images::deleteUnlessShared()): a file that stands under another name
		 *	outside that folder is nobody's to delete here.
		 *
		 *	When it is done, the hook '/nino/pages/removed' is fired once, after the lock is released, with the answer as its argument:
		 *	a feature that keeps state of its own for a page removes it there (the Builder: its last state below data/builder). A
		 *	callback cannot veto or change the answer, and one that throws is caught and logged and keeps no other from running (a
		 *	\Nino\Failure is thrown on) - the page is gone when it runs.
		 *
		 *	The answer of a page that was removed is [ 'template' => its name, 'uri' => the Element-URI, 'routes' => the route keys
		 *	it had, 'texts' => how many keys of the texts, counted once whatever the languages, 'slots' => the uris of the slots,
		 *	'files' => the names of the picture files that were removed, 'kept' => [ [ 'key' => a text, a slot or a detail that stays because
		 *	something else reads it, 'by' => [ who ] ] ], 'linked' => [ [ 'by' => who links to the page, 'key' => the text that holds
		 *	the link or '' ] ], 'remnant' => whether it was a leftover with no route ], that of a refusal is put()'s [ 'error' => ... ].
		 *	The functions know no account: whoever calls this has decided that the account may.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			The name of the template of the page, eg. 'page-news'
		 *
		 *	@return 	array										What was removed, or its error
		 */
		public static function remove( array &$appData, string $template ): array {

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
				return self::_refuse( 500, 'could not lock config.php for writing' );

			try {
				$done = self::_remove( $appData, $template );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

			if( isset( $done['error'] ) === false )
				foreach( \Nino\Callbacks::registered( $appData, '/nino/pages/removed' ) as $callback ) {

					$event = $done;

					// The page is gone: a callback that throws does not undo it and does not keep the others from hearing of it. A
					// \Nino\Failure ends the request, as it does everywhere
					try {
						call_user_func_array( $callback, [ &$appData, &$event ] );
					} catch( \Nino\Failure $failure ) {
						throw $failure;
					} catch( \Throwable $error ) {
						trigger_error( 'Pages: a callback of "/nino/pages/removed" threw '. $error::class. ': '. $error->getMessage(), E_USER_WARNING );
					}
				}

			return $done;
		}

		/**
		 *	What remove() would do, asked and not done: whether the page of a template may go at all - the refusals are remove()'s,
		 *	with the same codes - and, where it may, what it would keep and what links to it. A panel asks it before it asks a person
		 *	whether the page is to go. Nothing is written and no lock is taken: the answer is the state of the files as it is read,
		 *	and remove() decides again under the lock.
		 *
		 *	The answer is [ 'template' => its name, 'uri' => the Element-URI, '' for a leftover, 'routes' => the route keys, 'remnant'
		 *	=> whether it is a leftover, 'kept' => [ [ 'key' => a text, a slot or a detail of the page that something else reads and
		 *	that stays, 'by' => [ who reads it ] ], ... ], 'linked' => [ [ 'by' => who links to the page with page:<uri>, 'key' => the
		 *	key of the text that holds the link, '' where a template or a route does ], ... ], 'removable' => true ] - or [ 'error' =>
		 *	... ] as remove() answers a refusal.
		 *
		 *	Who reads a key: a fill, a source or the value of an attribute of a call in a template of templates/ or in the body of a
		 *	route, as \Nino\Markup reads it - a comment and the free text of a template read nothing -, and the value of a text of any
		 *	language or of global.php, where a pattern finds the key. A link is the same: page:<uri>, followed by nothing that goes on a
		 *	path.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			The name of the template of the page, eg. 'page-news'
		 *
		 *	@return 	array										See above
		 */
		public static function uses( array &$appData, string $template ): array {

			$plan = self::_uses( $appData, $template );

			if( isset( $plan['error'] ) === true )
				return $plan;

			return [ 'template' => $plan['template'], 'uri' => $plan['uri'], 'routes' => $plan['keys'], 'remnant' => $plan['remnant'], 'kept' => $plan['kept'], 'linked' => $plan['linked'], 'removable' => true ];
		}

		/**
		 *	Make a new page whose template is a copy of the one of another: put() with the source of the copy, and in the same
		 *	step, under the same lock and with the same giving back, the words and the pictures that go with it. $page is the page
		 *	as put() takes it - the Element-URI, the addresses, the name and the title of each language, the menus - with the name
		 *	of the new template in 'template'; a 'source', an 'originalUri' and a 'stamp' in it are not read. The refusals are put()'s, and the
		 *	new template's name is held to the same rules as the name of one put() makes.
		 *
		 *	What is copied. The source of the template, with every key of the namespace of the original - the text
		 *	/template/<template>/... of an absolute source, a fill or a fixed attribute - written for the new template. The ids stay
		 *	as they are: a key of a call that is written ~name is the key of the template it stands in, so the copy reads its own
		 *	words with no change, and an id is the scope of one template and nobody's else. Every text of that namespace in every
		 *	language, of global.php too, as the text of the copy - with its format and limit from meta.php. Every picture slot of the
		 *	namespace as a slot of the copy, with its label, size and alt texts, and its file as a file of its own under the name of
		 *	the new slot, so that removing the one never touches the other; a slot whose file is gone has none in the copy, and a
		 *	slot that stands for a picture of the kernel (a placeholder) is that placeholder again.
		 *
		 *	What is not copied. A source that is not in the namespace stays what it is, a reference: a text of /template/common or of
		 *	/project, a slot of another page, an element or a list of elements ([elements /services]) - the copy shows the same
		 *	ones. The name, title, description and address of the page are the caller's, and so are the menus. A fill or a link in
		 *	free html that names the original page by its address stays as written, and so does the text of a key outside the
		 *	namespace that the source reads. The entries of the blacklist and the last state a feature keeps of the original
		 *	(the Builder's) are not copied. A namespace that has a text, a meta entry, a slot or a picture file under the new name
		 *	already is refused (routes_namespace_taken, 409): what is there is nobody's to overwrite.
		 *
		 *	The answer is that of put() with 'copied' => [ 'texts' => the keys, 'slots' => the slots, 'files' => the picture files ].
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			The template to copy, eg. 'page-news'
		 *	@param		array 		$page					The new page, see put()
		 *
		 *	@return 	array										The page that was written, or its error
		 */
		public static function duplicate( array &$appData, string $template, array $page ): array {

			if( \Nino\Filesystem::lockFile( $appData, '/config.php' ) === false )
				return self::_refuse( 500, 'could not lock config.php for writing' );

			try {
				return self::_duplicate( $appData, $template, $page );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	The pages of the site, one entry for each Element-URI: the routes of one page are one entry, whatever their number,
		 *	and several pages may draw the same template - a caller that wants a template once groups the entries by 'template'.
		 *	The pages a module or feature routes at runtime and no config.php has (the imprint and the privacy policy of
		 *	\Nino\Modules\Legal) stand in the list as well, after the stored ones, with 'runtime' => true; what a panel lists
		 *	beyond this - the status code, the body, the titles - is its own to read (\Nino\Modules\Routes\Admin::pages()).
		 *
		 *	Each entry is [ 'uri' => the Element-URI, 'template' => the name of the template, '' where the body of the page is a
		 *	hand-written one, 'names' => language => the name of the page, the title where the page has no name, 'addresses' =>
		 *	language => the address that language is reached at, 'locales' => the languages the page has an address in, 'navs' =>
		 *	the menus it is in, 'runtime' => whether no config.php has it ] in the order of the routes. A page that has an
		 *	address of its own for some languages only has none for the others unless a route without a language serves them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ entry, ... ]
		 */
		public static function all( array &$appData ): array {

			$stored 	= \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$stored 	= is_array( $stored ) === true ? $stored : [];
			$locales 	= \Nino\Locales::getAvailableLocales( $appData );
			$navKeys 	= self::navKeys( $appData );
			$text 		= [];
			$pages 		= [];

			foreach( $locales as $locale )
				$text[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			foreach( [ [ false, $stored ], [ true, self::runtime( $appData ) ] ] as [ $runtime, $routes ] )
				foreach( self::blocks( $routes ) as $block ) {

					$first 	= (array) $routes[ $block['keys'][0] ];
					$bound 	= [];
					$shared = null;

					foreach( $block['keys'] as $routeKey ) {

						$httpUri 	= substr( $routeKey, strlen( 'GET:/' ) );
						$locale 	= is_string( $routes[$routeKey]['locale'] ?? null ) === true ? $routes[$routeKey]['locale'] : '';

						if( $locale === '' )
							$shared ??= $httpUri;
						else
							$bound[$locale] ??= $httpUri;
					}

					$names 			= [];
					$addresses 	= [];

					foreach( $locales as $locale ) {

						$name 					= (string) ( $text[$locale]['[[/_nino/webpage'. $block['uri']. '/name]]'] ?? '' );
						$names[$locale] = $name !== '' ? $name : (string) ( $text[$locale]['[[/_nino/webpage'. $block['uri']. '/title]]'] ?? '' );
						$address 				= $bound[$locale] ?? $shared;

						if( $address !== null )
							$addresses[$locale] = $address;
					}

					$pages[] = [
						'uri' 			=> $block['uri'],
						'template' 	=> self::templateOf( (string) ( $first['body'] ?? '' ) ) ?? '',
						'names' 		=> $names,
						'addresses' => $addresses,
						'locales' 	=> array_keys( $addresses ),
						'navs' 			=> array_values( array_intersect( $navKeys, array_keys( (array) ( $first['navs'] ?? [] ) ) ) ),
						'runtime' 	=> $runtime,
					];
				}

			return $pages;
		}

		/**
		 *	The page routes features and modules make at runtime and no config.php has: a route of the live route array that is a
		 *	page (see isPageRoute()) and whose key is not among the stored ones. A route whose Element-URI is no safe path (see
		 *	normalizeUri()) is left out: the keys it would be given are the system's, and not worth guessing at
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Route key => route, in route order
		 */
		public static function runtime( array &$appData ): array {

			$stored = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
			$stored = is_array( $stored ) === true ? $stored : [];
			$found 	= [];

			foreach( $appData['/nino/http/routes'] ?? [] as $routeKey => $route ) {

				$routeKey = (string) $routeKey;

				if( is_array( $route ) === false || isset( $stored[$routeKey] ) === true || self::isPageRoute( $routeKey, $route ) === false )
					continue;

				$uri = self::uriOf( $routeKey, $route );

				if( self::normalizeUri( $uri ) !== $uri )
					continue;

				$found[$routeKey] = $route;
			}

			return $found;
		}

		/**
		 *	The templates the kernel draws by itself, by name: the page of the maintenance and the pages of the legal module. A
		 *	template of one of these names is theirs, whoever made the page, so a source is never made under one
		 *
		 *	@return 	array										Template names, eg. 'page-maintenance'
		 */
		public static function reserved(): array {

			$names = [ 'page-maintenance' ];

			if( class_exists( '\\Nino\\Modules\\Legal' ) === true )
				$names = array_merge( $names, array_column( \Nino\Modules\Legal::PAGES, 'template' ) );

			return $names;
		}

		/**
		 *	Whether one route is a page: a GET route that draws a templates/page-*.tpl file - the thing that keeps robots.txt,
		 *	sitemap.xml and llms.txt, GET routes with headers of their own and no page template, out of the pages. Matched on the
		 *	prefix of the body rather than through templateOf(), which reports null for a body that picks its file at runtime: a
		 *	hand-written [template /templates/page-x.[[/nino/http/response/locale]]] is a page like any other
		 *
		 *	@param		string		$routeKey			Eg. 'GET://kontakt'
		 *	@param		array 		$route
		 *
		 *	@return 	bool
		 */
		public static function isPageRoute( string $routeKey, array $route ): bool {

			return str_starts_with( $routeKey, 'GET://' ) === true
				&& preg_match( '~^\[template /templates/page-~', trim( (string) ( $route['body'] ?? '' ) ) ) === 1;
		}

		/**
		 *	The pages of a route array, one block of routes each: the page routes of one Element-URI, in the order of the first
		 *	route of each page
		 *
		 *	@param		array 		$routes				The persisted route array
		 *
		 *	@return 	array										[ [ 'uri' => the Element-URI, 'keys' => [ route key, ... ] ], ... ]
		 */
		public static function blocks( array $routes ): array {

			$blocks = [];

			foreach( $routes as $routeKey => $route ) {

				if( is_array( $route ) === false || self::isPageRoute( (string) $routeKey, $route ) === false )
					continue;

				$uri 	= self::uriOf( (string) $routeKey, $route );
				$at 	= array_search( $uri, array_column( $blocks, 'uri' ), true );

				if( $at === false )
					$blocks[] = [ 'uri' => $uri, 'keys' => [ (string) $routeKey ] ];
				else
					$blocks[$at]['keys'][] = (string) $routeKey;
			}

			return $blocks;
		}

		/**
		 *	Whether a page is one the site cannot do without: the page that answers the address '/' and the page of the status 404 -
		 *	the route GET://404, or the Element-URI /404 -, which every address that has no page is answered with
		 *
		 *	@param		array 		$block				A page as blocks() gives it: [ 'uri' => the Element-URI, 'keys' => [ route key, ... ] ]
		 *	@param		string		$field				The field the refusal names
		 *
		 *	@return 	array|null								Null for any other page, else the refusal (409, routes_remove_home or routes_remove_not_found)
		 */
		public static function keeper( array $block, string $field = 'template' ): ?array {

			if( in_array( 'GET://', $block['keys'], true ) === true )
				return self::_refuse( 409, 'the page "'. $block['uri']. '" answers the address "/"', 'routes_remove_home', [ $block['uri'] ], $field );

			if( in_array( 'GET://404', $block['keys'], true ) === true || $block['uri'] === '/404' )
				return self::_refuse( 409, 'the page "'. $block['uri']. '" answers the addresses that have no page', 'routes_remove_not_found', [ $block['uri'] ], $field );

			return null;
		}

		/**
		 *	Which of the two pages the site cannot do without a route array leaves behind: the home page (the route GET://) and
		 *	the page of the status 404 (the route GET://404, which every address without a page is answered with). A role is
		 *	left where $before has its route and $after has none - the page that held it moved its address, and no other route
		 *	holds it after the save. The route is the role, as the kernel reads it (see keeper() for the page that holds it): a page that
		 *	keeps its Element-URI /404 and moves its address is left of it all the same
		 *
		 *	@param		array 		$before				The route array as it stands
		 *	@param		array 		$after				The route array as it is to be
		 *
		 *	@return 	array										The roles that are lost, 'home' and 'not_found' in that order; none for a save that leaves both
		 */
		public static function leaves( array $before, array $after ): array {

			$left = [];

			foreach( [ 'home' => 'GET://', 'not_found' => 'GET://404' ] as $role => $routeKey )
				if( isset( $before[$routeKey] ) === true && isset( $after[$routeKey] ) === false )
					$left[] = $role;

			return $left;
		}

		/**
		 *	The state of a page as the Routes panel can change it, as one opaque string: what a save of a page writes and what a form of it
		 *	shows - the route keys of the page in the order they stand in, each with its Element-URI, language, body, status code and the
		 *	menus it is in, and the name, title, description and address of the page in the text file of every language and in global.php,
		 *	each as the file holds it (the stored form, not the one a person types). What a save keeps and no form edits - a 'maintenance',
		 *	a 'header', the priority in a menu - and the place of the page among the others are no part of it. A page that is saved as it
		 *	stands has the state it had. The string is a hash, to be compared and to be posted back with a save or a delete (see stale()),
		 *	and says nothing else
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$files				The text files as they are held: language => the content of its file, '*' => the content of global.php
		 *	@param		string		$uri					The page's Element-URI
		 *
		 *	@return 	string|null								Null for a uri no page has
		 */
		public static function stamp( array $routes, array $files, string $uri ): ?string {

			$keys = self::keys( $routes, $uri );

			if( $keys === [] )
				return null;

			$state = [ 'routes' => [], 'text' => [] ];

			ksort( $files, SORT_STRING );

			foreach( $keys as $routeKey ) {

				$route = is_array( $routes[$routeKey] ?? null ) === true ? $routes[$routeKey] : [];
				$navs = is_array( $route['navs'] ?? null ) === true ? array_map( 'strval', array_keys( $route['navs'] ) ) : [];

				sort( $navs );

				$state['routes'][] = [ $routeKey, self::uriOf( $routeKey, $route ), (string) ( $route['locale'] ?? '' ), (string) ( $route['body'] ?? '' ), (int) ( $route['statusCode'] ?? 200 ), $navs ];
			}

			foreach( $files as $name => $content )
				$state['text'][(string) $name] = array_values( self::_pageKeys( $content, $uri ) );

			return hash( 'sha256', serialize( $state ) );
		}

		/**
		 *	Whether a form of a page that exists was read at the state the page has - the question of a save and of a delete that carry the
		 *	stamp of the page as it was read (see stamp()). Asked under the lock of config.php, of the routes that were read under it
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The persisted route array, as the lock holds it
		 *	@param		mixed			$stamp				What the request carries
		 *	@param		string		$uri					The Element-URI of the page that was read, '' for a new page
		 *
		 *	@return 	array|null								Null where the page is what the form read; else the refusal - 400 routes_invalid_stamp for a stamp that is no string and for one of a new page, 409 routes_conflict_gone for a page that is gone, 409 routes_conflict for one that was changed
		 */
		public static function stale( array &$appData, array $routes, mixed $stamp, string $uri ): ?array {

			$files = [];

			return self::_check( $appData, $routes, $stamp, $uri, $files );
		}

		/**
		 *	stale(), and the text files it read: language => the content of its file, '*' => the content of global.php
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The persisted route array
		 *	@param		mixed			$stamp				What the request carries
		 *	@param		string		$uri					The Element-URI of the page that was read
		 *	@param		array 		&$files				(reference) The files the state was computed from
		 *
		 *	@return 	array|null								See stale()
		 */
		private static function _check( array &$appData, array $routes, mixed $stamp, string $uri, array &$files ): ?array {

			if( is_string( $stamp ) === false || $uri === '' )
				return self::_refuse( 400, 'invalid stamp', 'routes_invalid_stamp', [], 'stamp' );

			$files = [];

			foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
				$files[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

			$files['*'] = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );

			$current = self::stamp( $routes, $files, $uri );

			if( $current === null )
				return self::_refuse( 409, 'the page "'. $uri. '" was removed since the form was read', 'routes_conflict_gone', [ $uri ] );

			if( hash_equals( $current, $stamp ) === false )
				return self::_refuse( 409, 'the page "'. $uri. '" was changed since the form was read', 'routes_conflict', [ $uri ] );

			return null;
		}

		/**
		 *	The route keys of one page, in the order they stand in
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string		$uri					The page's Element-URI
		 *
		 *	@return 	array										Route keys, none for a uri no page has
		 */
		public static function keys( array $routes, string $uri ): array {

			foreach( self::blocks( $routes ) as $block )
				if( $block['uri'] === $uri )
					return $block['keys'];

			return [];
		}

		/**
		 *	The Element-URI of a page route: its own 'uri', else its path
		 *
		 *	@param		string		$routeKey			Eg. 'GET://kontakt'
		 *	@param		array 		$route
		 *
		 *	@return 	string
		 */
		public static function uriOf( string $routeKey, array $route ): string {
			return (string) ( $route['uri'] ?? substr( $routeKey, strlen( 'GET:/' ) ) );
		}

		/**
		 *	'/foo', '/foo/', 'foo' all normalize to '/foo'; '/' stays '/'. Anything empty, with '..' in it or with a character
		 *	outside a plain path is none
		 *
		 *	@param		string		$uri
		 *
		 *	@return 	string|null							Null if $uri can't be normalized into a safe path
		 */
		public static function normalizeUri( string $uri ): ?string {

			$uri = trim( $uri );
			if( $uri === '' )
				return null;

			if( $uri[0] !== '/' )
				$uri = '/'. $uri;
			if( $uri !== '/' )
				$uri = rtrim( $uri, '/' );

			if( str_contains( $uri, '..' ) === true || preg_match( '#^/[a-zA-Z0-9\-_./]*$#', $uri ) !== 1 )
				return null;

			return $uri;
		}

		/**
		 *	The on-disk template a route body names, when it names exactly one. A body is not always a plain template reference
		 *	(a hand-written one may pick the file per language by [[/nino/http/response/locale]]); null says so, rather than
		 *	handing back something shaped like a file name that is none
		 *
		 *	@param		string		$body					A route's body
		 *
		 *	@return 	string|null							Null if $body isn't a plain template reference
		 */
		public static function templateOf( string $body ): ?string {
			return preg_match( '~^\[template /templates/([A-Za-z0-9._-]+)\]$~', trim( $body ), $match ) === 1 ? $match[1] : null;
		}

		/**
		 *	Every templates/page-*.tpl file on disk, sorted, stripped of its extension - the whitelist a template is checked
		 *	against, so that a page can never be pointed at an arbitrary file
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function templates( array &$appData ): array {

			$files = array_filter( glob( \Nino\Filesystem::path( $appData, '/templates' ). '/page-*.tpl' ) ?: [], 'is_file' );
			$names = array_map( fn( string $file ): string => basename( $file, '.tpl' ), $files );

			sort( $names );

			return $names;
		}

		/**
		 *	The menus this project offers, in the order a picker lists them: '/nino/html/navs', a plain list of keys. Empty while
		 *	the Navigation module is inactive, which is what tells a form to offer no menu at all. Modules\Navigation renders any
		 *	key a template asks for, registered or not - this is an editing affordance and nothing more
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Menu keys, eg. [ 'main', 'footer' ]
		 */
		public static function navKeys( array &$appData ): array {

			if( in_array( '\\Nino\\Modules\\Navigation', $appData['/nino/modules'] ?? [], true ) === false )
				return [];

			$navs = $appData['/nino/html/navs'] ?? [];

			return array_values( array_unique( array_map( 'strval', $navs ) ) );
		}

		/**
		 *	The names the menus of navKeys() have, '/nino/html/navnames': menu key =&gt; name. A menu without a name is not in it, and a
		 *	name whose menu is not registered is left out. A form shows the name before the key and the key where there is none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return		array										Menu key =&gt; name
		 */
		public static function navNames( array &$appData ): array {

			$stored = $appData['/nino/html/navnames'] ?? [];
			$names 	= [];

			if( is_array( $stored ) === false )
				return $names;

			foreach( self::navKeys( $appData ) as $key )
				if( is_string( $stored[$key] ?? null ) === true && $stored[$key] !== '' )
					$names[$key] = $stored[$key];

			return $names;
		}

		/**
		 *	The name, title and description a request posts, per language, trimmed and as plain text. A name and a title are
		 *	required in every language: a key nobody wrote renders as the raw [[...]] on the page and in the menu. The description
		 *	is optional. The first thing missing is the answer, and nothing is written
		 *
		 *	@param		array 		$rows					Language => [ name, title, description ]
		 *	@param		array 		$locales			The languages of the site
		 *
		 *	@return 	array										[ 'text' => language => [ name, title, description ] ], or [ 'error' => ... ] as put() answers it
		 */
		public static function texts( array $rows, array $locales ): array {

			$text = [];

			foreach( $locales as $locale ) {

				$row = is_array( $rows[$locale] ?? null ) === true ? $rows[$locale] : [];

				if( trim( is_string( $row['name'] ?? null ) === true ? $row['name'] : '' ) === '' )
					return self::_refuse( 400, 'missing name for '. $locale, 'routes_missing_name', [ $locale ], 'name' );

				if( trim( is_string( $row['title'] ?? null ) === true ? $row['title'] : '' ) === '' )
					return self::_refuse( 400, 'missing title for '. $locale, 'routes_missing_title', [ $locale ], 'title' );

				// Plain text, as the Text panel stores the same keys: the fill pass is a blind str_replace, and the title and
				// the description land in an attribute of the page head
				$text[$locale] = [
					'name' 				=> \Nino\Text::sanitizeValue( trim( $row['name'] ), 'plain' ),
					'title' 			=> \Nino\Text::sanitizeValue( trim( $row['title'] ), 'plain' ),
					'description' => \Nino\Text::sanitizeValue( trim( is_string( $row['description'] ?? null ) === true ? $row['description'] : '' ), 'plain' ),
				];
			}

			return [ 'text' => $text ];
		}

		/**
		 *	The refusal of a page, in the shape put() answers it
		 *
		 *	@param		int				$status				The HTTP status that fits
		 *	@param		string		$message			A sentence for a log
		 *	@param		string		$code					The code a panel makes a sentence of, '' for none
		 *	@param		array 		$params				What the sentence names
		 *	@param		string		$field				The field of the form to blame, '' for none
		 *
		 *	@return 	array										[ 'error' => [ status, message, code, params, field ] ]
		 */
		private static function _refuse( int $status, string $message, string $code = '', array $params = [], string $field = '' ): array {
			return [ 'error' => [ 'status' => $status, 'message' => $message, 'code' => $code, 'params' => $params, 'field' => $field ] ];
		}

		/**
		 *	put() under the lock: the checks, in the order a form is read in, and then the writes
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$page					See put()
		 *	@param		array 		$extra				What duplicate() writes in the same step: 'texts' => file => the keys to merge into it, 'slots' => uri => slot, 'files' => filename below /images/ => bytes
		 *
		 *	@return 	array
		 */
		private static function _put( array &$appData, array $page, array $extra = [], ?string &$stamp = null ): array {

			$rawUri 			= is_string( $page['uri'] ?? null ) === true ? $page['uri'] : '';
			$originalUri 	= is_string( $page['originalUri'] ?? null ) === true ? $page['originalUri'] : '';
			$template 		= is_string( $page['template'] ?? null ) === true ? $page['template'] : '';
			$source 			= is_string( $page['source'] ?? null ) === true ? $page['source'] : null;
			$uri 					= self::normalizeUri( $rawUri );

			if( $uri === null )
				return self::_refuse( 400, 'invalid uri: "'. $rawUri. '"', 'routes_invalid_uri', [ $rawUri ], 'uri' );

			$locales = \Nino\Locales::getAvailableLocales( $appData );
			$posted 	= self::_addresses( is_array( $page['httpUris'] ?? null ) === true ? $page['httpUris'] : [], $locales );

			if( isset( $posted['error'] ) === true )
				return $posted;

			[ $addresses, $fields ] = [ $posted['addresses'], $posted['fields'] ];

			// The imprint and the privacy policy are the Legal module's, with their routes, names and menu entries: a page of
			// the project's own under their Element-URI would share the details and the language switch with them, whatever
			// template it renders
			if( class_exists( '\\Nino\\Modules\\Legal' ) === true && in_array( $uri, array_column( \Nino\Modules\Legal::PAGES, 'uri' ), true ) === true )
				return self::_refuse( 409, 'the uri "'. $uri. '" belongs to the legal module', 'routes_reserved_uri', [ $uri ], 'uri' );

			$statusCode = (int) ( $page['statusCode'] ?? 200 );
			if( $statusCode < 100 || $statusCode > 599 )
				$statusCode = 200;

			$config 	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$routes 	= $config['/nino/http/routes'] ?? [];
			$navKeys 	= self::navKeys( $appData );

			// A form that carries the state it was read at is saved only if the page is in it: a page that was changed or taken
			// away since is a conflict, and a conflict is told before anything is held against what the stored routes and texts
			// have to say - the addresses and the Element-URI the page's own successor holds are not the form's fault
			$guard = null;

			if( array_key_exists( 'stamp', $page ) === true ) {

				$seen = [];
				$stale = self::_check( $appData, $routes, $page['stamp'], $originalUri, $seen );

				if( $stale !== null )
					return $stale;

				// What the state was computed from, file by file: _write() asks every one of them a second time under its own lock, before it writes the first
				$guard = [ 'uri' => $originalUri, 'files' => [] ];

				foreach( $seen as $name => $content )
					$guard['files'][ $name === '*' ? '/text/global.php' : '/text/'. $name. '.php' ] = self::_pageKeys( $content, $originalUri );
			}

			// The page as it stands: its routes in the order they stand in, the first of them leading. A page that is gone
			// since the form was opened is a new one - where the form carries no stamp
			$previousKeys = $originalUri !== '' ? self::keys( $routes, $originalUri ) : [];
			$first 				= $previousKeys !== [] && is_array( $routes[$previousKeys[0]] ?? null ) === true ? $routes[$previousKeys[0]] : [];

			if( $uri !== $originalUri && self::keys( $routes, $uri ) !== [] )
				return self::_refuse( 400, 'duplicate uri: "'. $uri. '"', 'routes_duplicate_uri', [ $uri ], 'uri' );

			$plan = self::_plan( $addresses );

			// Every route counts here, not just the page ones: a page may never take a uri robots.txt, a module or a developer
			// already answers on. Its own routes of before are the one place it may stay
			foreach( $plan as $entry ) {

				$routeKey = self::_routeKey( $entry['address'] );

				// The field of the key the request posted this address with
				$field = $fields[ $entry['address'] ];

				// A route that is only there at run time - one a module makes for the request, like the pages of the legal
				// module - holds its address as well: a page that took it would send the module's page to another address
				if( isset( $routes[$routeKey] ) === false && isset( $appData['/nino/http/routes'][$routeKey] ) === true && in_array( $routeKey, $previousKeys, true ) === false )
					return self::_refuse( 409, 'http uri already belongs to another route: "'. $entry['address']. '"', 'routes_http_uri_taken', [ $entry['address'] ], $field );

				if( isset( $routes[$routeKey] ) === false || in_array( $routeKey, $previousKeys, true ) === true )
					continue;

				if( is_array( $routes[$routeKey] ) === true && self::isPageRoute( $routeKey, $routes[$routeKey] ) === true )
					return self::_refuse( 400, 'duplicate http uri: "'. $entry['address']. '"', 'routes_duplicate_http_uri', [ $entry['address'] ], $field );

				return self::_refuse( 409, 'http uri already belongs to another route: "'. $entry['address']. '"', 'routes_http_uri_taken', [ $entry['address'] ], $field );
			}

			$body = '[template /templates/'. $template. ']';

			// A route body can be more than a plain template reference - a hand-written one may pick the template file per
			// locale via [[/nino/http/response/locale]] - and the template <select> has no way to spell that. Keep the body
			// such an entry already carries, rather than flattening it into whichever single option happened to be
			// preselected. A template that is made by this call is the body's, whatever the page had
			if( $source === null && isset( $first['body'] ) === true && self::templateOf( (string) $first['body'] ) === null ) {
				$body 		= (string) $first['body'];
				// ...and with it the template field, which for such an entry names nothing: the disabled <select> still posts
				// whichever option the browser preselected, and storing that would leave the list claiming a template this
				// page never uses
				$template = '';
			}

			// Checked here rather than up front: an entry whose body the <select> can't spell keeps the template field it
			// already had (empty), and posts an empty value from its own disabled option - neither of which names a real
			// file, and neither of which is an error
			if( $source !== null ) {

				// The name is the one of a page and the file is a new one: the source of a template that is there is not this
				// call's to replace, a name outside the pattern is no file of templates/ this could write, and a name the
				// kernel reads by itself - the page of the maintenance, the pages of the legal module - is the kernel's. A name
				// that only differs in case from a template there is is that template on a file system that folds case
				if( strlen( $template ) > self::MAX_NAME || preg_match( self::TEMPLATE, $template ) !== 1 )
					return self::_refuse( 400, 'invalid template: "'. $template. '"', 'routes_invalid_template', [ $template ], 'template' );

				if( in_array( $template, self::reserved(), true ) === true )
					return self::_refuse( 409, 'reserved template: "'. $template. '"', 'routes_reserved_template', [ $template ], 'template' );

				if( \Nino\Filesystem::fileExists( $appData, '/templates/'. $template. '.tpl' ) === true || in_array( $template, array_map( 'strtolower', self::templates( $appData ) ), true ) === true )
					return self::_refuse( 409, 'template already exists: "'. $template. '"', 'routes_template_exists', [ $template ], 'template' );

				// A page that was taken away leaves nothing a new one of that name would find
				if( self::_leftovers( $appData, $template ) === true )
					return self::_refuse( 409, 'there is something of an earlier page under "/template/'. $template. '/" already', 'routes_namespace_taken', [ $template ], 'template' );

				if( strlen( $source ) > \Nino\Modules\Template::MAX_BYTES )
					return self::_refuse( 400, 'template source is larger than '. \Nino\Modules\Template::MAX_BYTES. ' bytes', 'routes_template_too_large', [ \Nino\Modules\Template::MAX_BYTES ], 'source' );

			} else if( $body === '[template /templates/'. $template. ']' && in_array( $template, self::templates( $appData ), true ) === false ) {
				return self::_refuse( 400, 'unknown template: "'. $template. '"', 'routes_unknown_template', [ $template ], 'template' );
			}

			// A name and a title in every language, checked last of the refusals and before anything is written
			$text = self::texts( is_array( $page['text'] ?? null ) === true ? $page['text'] : [], $locales );

			if( isset( $text['error'] ) === true )
				return $text;

			$text = $text['text'];

			// Menu membership lives on the routes and nowhere else - that is what Modules\Navigation::routeLines() reads, and
			// the only copy that renders
			$navs = array_values( array_intersect( $navKeys, is_array( $page['navs'] ?? null ) === true ? $page['navs'] : [] ) );

			// What the first route carries and the form does not edit stays, on every route of the page: a 'maintenance' =>
			// false that keeps a page up while the site is down, a 'header' - put there by hand in config.php. Only the four
			// the form is about are set anew - and the 'locale' of each, which is the rule's -, and a status code of 200 is
			// no field
			$kept = array_diff_key( $first, [ 'uri' => true, 'locale' => true, 'body' => true, 'statusCode' => true, 'navs' => true ] );

			// A priority someone tuned on this page is not this save's to reset - only the set of keys is rewritten (see the
			// shortcode's own routeLines() for what the value means). A membership this save adds starts behind everything
			// already in that menu
			$prios 		= is_array( $first['navs'] ?? null ) === true ? $first['navs'] : [];
			$memberOf = [];
			foreach( $navs as $navKey )
				$memberOf[$navKey] = (int) ( $prios[$navKey] ?? self::_nextPrio( $routes, $navKey ) );

			$put = [];

			foreach( $plan as $entry ) {

				$routeData = [ 'uri' => $uri ] + ( $entry['locale'] !== '' ? [ 'locale' => $entry['locale'] ] : [] ) + [ 'body' => $body ] + $kept;

				if( $statusCode !== 200 )
					$routeData['statusCode'] = $statusCode;
				if( $memberOf !== [] )
					$routeData['navs'] = $memberOf;

				$put[ self::_routeKey( $entry['address'] ) ] = $routeData;
			}

			// The page's reachable path as a fill, so a template can link to it by name - [[/_nino/webpage/site-home/uri]] -
			// rather than repeating a path this form can change. Each language's own file says the address of that language;
			// this one is for what no language's file decides: the address that serves every language, else the native
			// language's, else the first
			$global = $addresses[ \Nino\Locales::getNativeLocale( $appData ) ] ?? (string) reset( $addresses );

			foreach( $plan as $entry )
				if( $entry['locale'] === '' ) {
					$global = $entry['address'];
					break;
				}

			$fragments = [];

			foreach( $locales as $locale )
				$fragments['/text/'. $locale. '.php'] = [
					'[[/_nino/webpage'. $uri. '/name]]' 				=> $text[$locale]['name'],
					'[[/_nino/webpage'. $uri. '/title]]' 			=> $text[$locale]['title'],
					'[[/_nino/webpage'. $uri. '/description]]' => $text[$locale]['description'],
					'[[/_nino/webpage'. $uri. '/uri]]' 				=> $addresses[$locale],
				];

			$fragments['/text/global.php'] = [ '[[/_nino/webpage'. $uri. '/uri]]' => $global ];

			// A page that exists and has one address for every language is read from global.php by every language whose file holds no
			// address of its own: the reader lays the file of the language over global.php, and takes what another language's file holds
			// for a key that is missing or empty in its own (\Nino\Html::_textFills()). Such a file is not given the key - a page the setup
			// wizard made has the address in global.php alone - unless another file of a language would hand its own value to the reader
			if( $previousKeys !== [] && count( array_unique( $addresses ) ) === 1 && reset( $addresses ) === $global ) {

				$key 		= '[[/_nino/webpage'. $uri. '/uri]]';
				$files 	= [];

				foreach( array_unique( array_merge( $locales, \Nino\Locales::getTextLocales( $appData ) ) ) as $locale )
					$files[$locale] = \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] );

				foreach( $locales as $locale ) {

					// A text counts for the reader where it is not blank: there is one, and it is no string of blanks - a number is a text (\Nino\Html::_blank())
					$others = array_filter( array_diff_key( $files, [ $locale => true ] ), static fn( array $content ): bool => ( $content[$key] ?? null ) !== null && ( is_string( $content[$key] ) === false || trim( $content[$key] ) !== '' ) && $content[$key] !== $global );

					if( array_key_exists( $key, $files[$locale] ) === false && $others === [] )
						unset( $fragments['/text/'. $locale. '.php'][$key] );
				}
			}

			foreach( $extra['texts'] ?? [] as $path => $keys )
				$fragments[$path] = array_merge( $fragments[$path] ?? [], $keys );

			// The page that is written with the route GET://404 has the status 404, whether it held that route before or takes it with
			// this save. It is the route as it is written that decides: a save that moves the address away holds no role of that page
			if( isset( $put['GET://404'] ) === true && $statusCode !== 404 )
				return self::_refuse( 400, 'the page "'. $uri. '" answers the addresses that have no page: its status is 404', 'routes_not_found_status', [ $uri ], 'statusCode' );

			$before = $routes;
			$routes = self::_putRoute( $routes, $previousKeys, $put );

			// A route that is what it was - the keys in another order, an explicit status 200, a status or a priority as a string - stays as it
			// stands in config.php: the save writes no route of a page it changes nothing of
			foreach( array_keys( $put ) as $routeKey )
				if( is_array( $before[$routeKey] ?? null ) === true && self::_canonical( $before[$routeKey] ) === self::_canonical( $routes[$routeKey] ) )
					$routes[$routeKey] = $before[$routeKey];

			// A save that takes the home page or the 404 page away from the site is asked for, where the caller asks
			if( array_key_exists( 'confirm', $page ) === true && $page['confirm'] !== true ) {

				$left = self::leaves( $before, $routes );

				if( $left === [ 'home', 'not_found' ] )
					return self::_refuse( 409, 'the site would have neither a page at the address "/" nor a page for the addresses that have no page', 'routes_leaves_both', [ $uri ] );

				if( in_array( 'home', $left, true ) === true )
					return self::_refuse( 409, 'the site would have no page at the address "/"', 'routes_leaves_home', [ $uri ] );

				if( in_array( 'not_found', $left, true ) === true )
					return self::_refuse( 409, 'the site would have no page for the addresses that have no page', 'routes_leaves_not_found', [ $uri ] );
			}

			return self::_write( $appData, $routes, $fragments, $uri, $template, $source, array_keys( $put ), $extra['slots'] ?? [], $extra['files'] ?? [], $guard, $stamp );
		}

		/**
		 *	The keys of one page in the content of a text file, as the file holds them: bracket key => the text, null where the file has none or no string
		 *
		 *	@param		mixed			$content			The content of a text file
		 *	@param		string		$uri					The page's Element-URI
		 *
		 *	@return 	array
		 */
		private static function _pageKeys( mixed $content, string $uri ): array {

			$keys = [];

			foreach( [ 'name', 'title', 'description', 'uri' ] as $field ) {

				$value = is_array( $content ) === true ? ( $content['[[/_nino/webpage'. $uri. '/'. $field. ']]'] ?? null ) : null;
				$keys['[[/_nino/webpage'. $uri. '/'. $field. ']]'] = is_string( $value ) === true ? $value : null;
			}

			return $keys;
		}

		/**
		 *	A route as put() means it, to be compared with another: the keys sorted at every depth, the status as the number put() makes of it with
		 *	200, the one a route has without it, left out, and the priorities in the menus as numbers. Compared strictly: a text that only reads
		 *	like the number another one is - '10' and '1e1' - is another
		 *
		 *	@param		array 		$route
		 *
		 *	@return 	array
		 */
		private static function _canonical( array $route ): array {

			if( array_key_exists( 'statusCode', $route ) === true ) {

				if( (int) $route['statusCode'] === 200 )
					unset( $route['statusCode'] );
				else
					$route['statusCode'] = (int) $route['statusCode'];
			}

			if( is_array( $route['navs'] ?? null ) === true ) {

				if( $route['navs'] === [] )
					unset( $route['navs'] );
				else
					$route['navs'] = array_map( 'intval', $route['navs'] );
			}

			return self::_sorted( $route );
		}

		/**
		 *	An array with its keys sorted, at every depth
		 *
		 *	@param		array 		$list
		 *
		 *	@return 	array
		 */
		private static function _sorted( array $list ): array {

			ksort( $list, SORT_STRING );

			foreach( $list as $key => $value )
				if( is_array( $value ) === true )
					$list[$key] = self::_sorted( $value );

			return $list;
		}

		/**
		 *	The writes of a page, in an order that lets each be taken back: the template, the text of each language, the address
		 *	in global.php, the blacklist - and the routes last. The lock of config.php is held from the first check of put() to
		 *	the write of the routes, which is why the routes are never written and taken back: a failure before them leaves
		 *	config.php as it is, and a failure of that write itself is an answer like any other. Each file of the text is read
		 *	under its own lock, in the same step that merges the keys of the page into it, and what the page changed there is
		 *	what a failure takes out again - its keys back to what they were, no key of anybody else touched - and the template
		 *	file is removed; the answer names the step that failed. A file that the page would write as it is - the keys of its fragment
		 *	hold what they are given, the routes and the slots are what config.php has - is not written, and is nothing to give back
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				The route array as it is to be
		 *	@param		array 		$fragments		File => the keys of the text that are to be merged into it
		 *	@param		string		$uri					The Element-URI of the page
		 *	@param		string		$template			The name of the template of the page, '' where its body is a hand-written one
		 *	@param		string|null	$source			The source of the template to make, null for none
		 *	@param		array 		$keys					The route keys of the page
		 *	@param		array 		$slots				Uri => slot: the picture slots that are written into config.php with the routes, in the one write
		 *	@param		array 		$files				Filename below /images/ => bytes: the files of those slots, made before the routes and removed again where a write fails
		 *
		 *	@return 	array										See put()
		 */
		private static function _write( array &$appData, array $routes, array $fragments, string $uri, string $template, ?string $source, array $keys, array $slots = [], array $files = [], ?array $guard = null, ?string &$stamp = null ): array {

			$held = [];
			$after = [];

			try {

				// A save that carries the state of the page it was read at asks, before it writes the first file, whether a text file still has what that
				// state was made of: the Texts panel writes a file under that file's lock alone, so the lock of config.php does not keep it out. Every file
				// of the page is locked in the same order and read; one that differs is the conflict, with nothing written. A file stays locked until its
				// own write is done
				if( $guard !== null ) {

					$paths = array_keys( $guard['files'] );

					sort( $paths );

					foreach( $paths as $path ) {

						if( \Nino\Filesystem::lockFile( $appData, $path ) === false )
							return self::_refuse( 500, 'could not lock '. $path );

						$held[] = $path;

						// The keys of the page as the form read it: under its Element-URI of then, which a save may change
						if( self::_pageKeys( \Nino\Filesystem::getFileContent( $appData, $path, [] ), $guard['uri'] ) !== $guard['files'][$path] )
							return self::_refuse( 409, 'the page "'. $guard['uri']. '" was changed since the form was read', 'routes_conflict', [ $guard['uri'] ] );
					}
				}

				$answer = self::_writeLocked( $appData, $routes, $fragments, $uri, $template, $source, $keys, $slots, $files, $after );

				// The state this call wrote, from the content each file had when it was written and the routes that were written
				if( isset( $answer['error'] ) === false )
					$stamp = self::stamp( $routes, $after, $uri );

				return $answer;

			} finally {

				foreach( $held as $path )
					\Nino\Filesystem::unlockFile( $appData, $path );
			}
		}

		/**
		 *	_write() with the locks held: the writes themselves
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$routes				See _write()
		 *	@param		array 		$fragments		See _write()
		 *	@param		string		$uri					See _write()
		 *	@param		string		$template			See _write()
		 *	@param		string|null	$source			See _write()
		 *	@param		array 		$keys					See _write()
		 *	@param		array 		$slots				See _write()
		 *	@param		array 		$files				See _write()
		 *	@param		array 		&$after				(reference) Language => the content of its file, '*' => that of global.php, as each was when this call was done with it
		 *
		 *	@return 	array										See put()
		 */
		private static function _writeLocked( array &$appData, array $routes, array $fragments, string $uri, string $template, ?string $source, array $keys, array $slots, array $files, array &$after ): array {

			$file 		= '/templates/'. $template. '.tpl';
			$listed 	= '/_nino/webpage'. $uri. '/uri';
			$blacklist = '/text/blacklist.php';
			$changed 	= [];
			$made 		= false;
			$fail 		= null;
			$pictures = [];

			if( $source !== null ) {

				// Made as a new file and by nobody else: a template another writer put there since the check is not this call's
				// to replace
				$made = self::_create( $appData, $file, $source );

				if( $made === null )
					return self::_refuse( 409, 'template already exists: "'. $template. '"', 'routes_template_exists', [ $template ], 'template' );

				if( $made === false )
					return self::_refuse( 500, 'could not write '. $file );
			}

			// What each file said of the keys of this page when it was merged into, and whether it was a file at all: the
			// state it is given back to
			foreach( $fragments as $path => $fragment ) {

				$real = \Nino\Filesystem::path( $appData, $path );
				$seen = [];
				$same = false;

				// A file that holds every key of the fragment as it is to be written is not written: its bytes, its mtime and its inode stay
				$written = \Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $fragment, $real, $path, &$seen, &$same, &$after ): ?array {

					$seen = [ 'existed' => is_file( $real ), 'prior' => array_intersect_key( $content, $fragment ) ];
					$merged = array_merge( $content, $fragment );
					$after[ $path === '/text/global.php' ? '*' : basename( $path, '.php' ) ] = $merged;

					if( $merged === $content ) {
						$same = true;
						return null;
					}

					return $merged;
				} );

				if( $written === false && $same === false ) {
					$fail = 'could not write '. $path;
					break;
				}

				if( $written === true )
					$changed[$path] = $seen;
			}

			if( $fail === null ) {

				$real = \Nino\Filesystem::path( $appData, $blacklist );
				$seen = [];

				$written = \Nino\Filesystem::mutate( $appData, $blacklist, function( array $list ) use ( $listed, $real, &$seen ): ?array {

					$seen = [ 'existed' => is_file( $real ), 'had' => in_array( $listed, $list, true ) ];

					if( $seen['had'] === true )
						return null;

					$list[] = $listed;

					return $list;
				} );

				if( $written === false && ( $seen['had'] ?? false ) === false )
					$fail = 'could not write '. $blacklist;
				else if( $written === true )
					$changed[$blacklist] = $seen;
			}

			// The files of the pictures a copy has, new names that nobody holds: they are there before the slots that name them
			if( $fail === null )
				foreach( $files as $name => $bytes ) {

					if( \Nino\Images::restore( $appData, (string) $name, (string) $bytes ) === false ) {
						$fail = 'could not write the picture '. $name;
						break;
					}

					$pictures[] = (string) $name;
				}

			if( $fail === null ) {

				$same = false;

				// config.php is written where the routes or the slots differ from what it holds
				$written = \Nino\Filesystem::mutate( $appData, '/config.php', function( array $content ) use ( $routes, $slots, &$same ): ?array {

					$new = $content;
					$new['/nino/http/routes'] = $routes;

					if( $slots !== [] )
						$new['/nino/html/images'] = array_merge( is_array( $content['/nino/html/images'] ?? null ) === true ? $content['/nino/html/images'] : [], $slots );

					if( $new === $content ) {
						$same = true;
						return null;
					}

					return $new;
				} );

				if( $written === false && $same === false )
					$fail = 'could not write config.php';
				else {
					$appData['/nino/http/routes'] = $routes;

					if( $slots !== [] )
						$appData['/nino/html/images'] = array_merge( is_array( $appData['/nino/html/images'] ?? null ) === true ? $appData['/nino/html/images'] : [], $slots );
				}
			}

			if( $fail === null )
				return [ 'uri' => $uri, 'routes' => $keys, 'template' => $template, 'created' => $source !== null ];

			foreach( $pictures as $name ) {
				\Nino\Images::delete( $appData, $name );
				self::_prune( $appData, $name );
			}

			$lost = [];

			foreach( $changed as $path => $seen )
				if( self::_giveBack( $appData, $path, $seen, $fragments[$path] ?? [ $listed ], $path === $blacklist ) === false )
					$lost[] = $path;

			if( $made === true && self::_bytes( $appData, $file ) === $source ) {

				if( @unlink( \Nino\Filesystem::path( $appData, $file ) ) === false )
					$lost[] = $file;

				unset( $appData['./nino/filesystem/cache'][$file] );
			}

			// What could not be given back is said: the answer names it, and the log does
			if( $lost !== [] ) {

				trigger_error( 'Pages: writing the page "'. $uri. '" failed ('. $fail. ') and '. implode( ', ', $lost ). ' could not be given back', E_USER_WARNING );
				$answer = self::_refuse( 500, $fail, 'routes_unrestored', [ implode( ', ', $lost ) ] );
				$answer['error']['unrestored'] = $lost;

				return $answer;
			}

			return self::_refuse( 500, $fail );
		}

		/**
		 *	What removing a template would take, keep and leave a link to, and whether it may go at all: the checks of remove() and
		 *	the plan it follows, with nothing written
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			See remove()
		 *
		 *	@return 	array										The plan, or [ 'error' => ... ]
		 */
		private static function _uses( array &$appData, string $template ): array {

			if( strlen( $template ) > self::MAX_NAME || preg_match( self::TEMPLATE, $template ) !== 1 )
				return self::_refuse( 400, 'invalid template: "'. $template. '"', 'routes_invalid_template', [ $template ], 'template' );

			// What the request read of the files before the lock may be older than the files
			unset( $appData['./nino/filesystem/cache'] );

			$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$routes = is_array( $config['/nino/http/routes'] ?? null ) === true ? $config['/nino/http/routes'] : [];
			$images = is_array( $config['/nino/html/images'] ?? null ) === true ? $config['/nino/html/images'] : [];
			$pages 	= [];

			foreach( self::blocks( $routes ) as $block )
				if( self::templateOf( (string) ( $routes[ $block['keys'][0] ]['body'] ?? '' ) ) === $template )
					$pages[] = $block;

			// A page the site asks for at every request: a template the kernel draws, or a route a module or a feature makes that
			// draws this one
			$asked = in_array( $template, self::reserved(), true ) === true;

			foreach( self::runtime( $appData ) as $route )
				if( preg_match( '#^\[template /templates/'. preg_quote( $template, '#' ). '(?![A-Za-z0-9-])#i', trim( (string) ( $route['body'] ?? '' ) ) ) === 1 )
					$asked = true;

			if( $asked === true )
				return self::_refuse( 409, 'the page of the template "'. $template. '" is one the site asks for by itself', 'routes_remove_runtime', [ $template ], 'template' );

			if( self::_alias( $appData, $template ) === true )
				return self::_refuse( 409, 'the template "'. $template. '" is a link or has a twin that differs in case only', 'routes_template_alias', [ $template ], 'template' );

			$namespace = '/template/'. $template. '/';
			$locales 	 = \Nino\Locales::getTextLocales( $appData );
			$remnant 	 = false;
			$dir 			 = \Nino\Filesystem::path( $appData, '/images/template/'. $template );
			$dirFiles  = [];

			// The files below the folder of the namespace: they are the page's by their path
			if( is_dir( $dir ) === true )
				foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
					if( $file->isFile() === true && $file->isLink() === false )
						$dirFiles[] = 'template/'. $template. substr( $file->getPathname(), strlen( $dir ) );

			// No page draws it: what is left of one - a template, words, slots or pictures under its name, after a run that was cut off
			// - is taken away like a page, with no routes
			if( $pages === [] ) {

				$remnant = \Nino\Filesystem::fileExists( $appData, '/templates/'. $template. '.tpl' ) === true || $dirFiles !== []
					|| array_filter( array_keys( $images ), static fn( mixed $slot ): bool => is_string( $slot ) === true && str_starts_with( $slot, $namespace ) === true ) !== []
					|| \Nino\Text::values( $appData, '/template/'. $template ) !== [];

				if( $remnant === false )
					return self::_refuse( 404, 'no page draws the template "'. $template. '"', 'routes_unknown_page', [ $template ], 'template' );
			}

			foreach( $pages as $block ) {

				$kept = self::keeper( $block, 'template' );

				if( $kept !== null )
					return $kept;
			}

			if( count( $pages ) > 1 )
				return self::_refuse( 409, 'more than one page draws the template "'. $template. '"', 'routes_remove_shared', [ $template, implode( ', ', array_column( $pages, 'uri' ) ) ], 'template' );

			$uri 	= $pages[0]['uri'] ?? '';
			$keys = $pages[0]['keys'] ?? [];
			$read = self::_sources( $appData, $template, $keys, $routes );
			$by 	= self::_readers( $read, '#/templates/'. preg_quote( $template, '#' ). '(?![A-Za-z0-9-])#i' );

			if( $by !== [] )
				return self::_refuse( 409, 'the template "'. $template. '" is named by '. $by[0], 'routes_remove_included', [ $template, $by[0] ], 'template' );

			// What is the page's: its namespace, and the four details of its Element-URI unless a route that runs only at runtime has
			// the same one. The details of a leftover whose page nobody knows are those of the two Element-URIs a page of that name
			// has by the rule of the pages the workbench makes, where no route answers for it
			$details = [];
			$uris 	 = [];

			if( $uri !== '' )
				$uris[] = $uri;
			else if( $remnant === true )
				foreach( [ '/'. substr( $template, 5 ), '/site-'. substr( $template, 5 ) ] as $guess )
					if( self::keys( $routes, $guess ) === [] && self::keys( $appData['/nino/http/routes'] ?? [], $guess ) === [] )
						$uris[] = $guess;

			foreach( $uris as $one )
				if( in_array( $one, array_column( self::blocks( self::runtime( $appData ) ), 'uri' ), true ) === false )
					foreach( [ 'name', 'title', 'description', 'uri' ] as $field )
						$details['/_nino/webpage'. $one. '/'. $field] = true;

			$own = static fn( string $key ): bool => str_starts_with( $key, $namespace ) === true || isset( $details[$key] ) === true;

			// What is read: the keys of the page and its details that a template, a route or the value of a text names - in a fill, a
			// source or an attribute of a call, where the source is a template, and by a pattern in a text - stay, and who reads them is
			// told. What links to the page with page:<uri> is told as well and does not hold it: a link to a page that is gone is drawn
			// without a target
			$readKeys = [];
			$linked 	= [];
			$keyPattern 	= '#(?<![A-Za-z0-9_./-])(/template/'. preg_quote( $template, '#' ). '(?:/[a-z0-9][a-z0-9_-]*)+)#';
			$pagePattern = $uri === '' ? null : '#(?<![A-Za-z0-9_-])page:'. preg_quote( $uri, '#' ). '(?![A-Za-z0-9_./-])#';
			$detailPattern = $details === [] ? null : '#(?<![A-Za-z0-9_./-])('. implode( '|', array_map( static fn( string $key ): string => preg_quote( $key, '#' ), array_keys( $details ) ) ). ')(?![A-Za-z0-9_-])#';
			$address = '/_nino/webpage'. $uri. '/uri';
			$scan = static function( string $name, string $key, array $strings ) use ( $keyPattern, $pagePattern, $detailPattern, $address, &$readKeys, &$linked ): void {

				foreach( $strings as $string ) {

					foreach( array_filter( [ $keyPattern, $detailPattern ] ) as $pattern )
						if( preg_match_all( $pattern, $string, $found ) > 0 )
							foreach( $found[1] as $read ) {

								$readKeys[$read][$name] = true;

								// The address of the page stays as a text and leads to no page: whoever reads it links to the page
								if( $read === $address )
									$linked[$name. "\0". $key] = [ 'by' => $name, 'key' => $key ];
							}

					if( $pagePattern !== null && preg_match( $pagePattern, $string ) === 1 )
						$linked[$name. "\0". $key] = [ 'by' => $name, 'key' => $key ];
				}
			};

			foreach( $read as $name => $bytes )
				$scan( (string) $name, '', self::_marks( $appData, (string) $bytes ) );

			$contents = [];

			foreach( array_merge( array_map( static fn( string $locale ): string => '/text/'. $locale. '.php', $locales ), [ '/text/global.php' ] ) as $path ) {

				$contents[$path] = \Nino\Filesystem::getFileContent( $appData, $path, [] );
				$label 					 = basename( $path, '.php' );

				foreach( is_array( $contents[$path] ) === true ? $contents[$path] : [] as $key => $value )
					if( is_string( $key ) === true && is_string( $value ) === true && str_starts_with( $key, '[[' ) === true && str_ends_with( $key, ']]' ) === true && $own( substr( $key, 2, -2 ) ) === false )
						$scan( substr( $key, 2, -2 ). ' ('. $label. ')', substr( $key, 2, -2 ), [ $value ] );
			}

			// A text of the page that stays because it is read is a reader itself: what it names of the page stays with it, and
			// so on until nothing more is found - a text that is kept never shows a key that went
			$followed = [];

			do {

				$more = false;

				foreach( $contents as $path => $content )
					foreach( is_array( $content ) === true ? $content : [] as $key => $value ) {

						if( is_string( $key ) !== true || is_string( $value ) !== true || str_starts_with( $key, '[[' ) !== true || str_ends_with( $key, ']]' ) !== true )
							continue;

						$bare = substr( $key, 2, -2 );

						if( $own( $bare ) !== true || isset( $readKeys[$bare] ) !== true || isset( $followed[$path. "\0". $bare] ) === true )
							continue;

						$followed[$path. "\0". $bare] = true;
						$more = true;
						$scan( $bare. ' ('. basename( $path, '.php' ). ')', $bare, [ $value ] );
					}

			} while( $more === true );

			$owns  = static fn( string $key ): bool => $own( $key ) === true && isset( $readKeys[$key] ) === false;
			$slots = array_values( array_filter( array_keys( $images ), static fn( mixed $slot ): bool => is_string( $slot ) === true && $owns( $slot ) === true ) );
			$files = [];

			foreach( $slots as $slot )
				if( is_string( $images[$slot]['filename'] ?? null ) === true && str_starts_with( $images[$slot]['filename'], ltrim( $slot, '/' ). '.' ) === true
					&& str_contains( $images[$slot]['filename'], '..' ) === false && preg_match( '/[\x00-\x1F\x7F]/', $images[$slot]['filename'] ) !== 1 )
					$files[ $images[$slot]['filename'] ] = $slot;

			foreach( $dirFiles as $name )
				$files[$name] ??= '';

			// The keys that go, counted once whatever the languages; the ones that stay because they are read
			$texts = [];
			$kept  = [];
			$paths = [];

			foreach( $contents as $path => $content )
				foreach( is_array( $content ) === true ? array_keys( $content ) : [] as $key )
					if( is_string( $key ) === true && str_starts_with( $key, '[[' ) === true && str_ends_with( $key, ']]' ) === true && $own( substr( $key, 2, -2 ) ) === true ) {

						if( $owns( substr( $key, 2, -2 ) ) === true ) {
							$texts[ substr( $key, 2, -2 ) ] = true;
							$paths[$path] = true;
						} else
							$kept[ substr( $key, 2, -2 ) ] = true;
					}

			foreach( array_keys( $images ) as $slot )
				if( is_string( $slot ) === true && $own( $slot ) === true && $owns( $slot ) === false )
					$kept[$slot] = true;

			ksort( $kept );
			ksort( $linked );

			return [
				'template' => $template, 'uri' => $uri, 'keys' => $keys, 'slots' => $slots, 'files' => $files, 'owns' => $owns, 'paths' => array_keys( $paths ), 'texts' => count( $texts ), 'remnant' => $remnant,
				'kept' => array_map( static fn( string $key ): array => [ 'key' => $key, 'by' => array_keys( $readKeys[$key] ) ], array_keys( $kept ) ), 'linked' => array_values( $linked ),
			];
		}

		/**
		 *	remove() under the lock: the plan, then the writes, each given back where a later one fails
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			See remove()
		 *
		 *	@return 	array										See remove()
		 */
		private static function _remove( array &$appData, string $template ): array {

			$plan = self::_uses( $appData, $template );

			if( isset( $plan['error'] ) === true )
				return $plan;

			[ 'uri' => $uri, 'keys' => $keys, 'slots' => $slots, 'files' => $files, 'owns' => $owns, 'paths' => $paths, 'remnant' => $remnant ] = $plan;

			$journal = [];
			$fail 	= null;

			try {

				// The routes and the slots, with the page's own memberships: one write, the first - from then on no visitor is sent to
				// the page. The lock of config.php is held through it and to the end, so nobody can put a route on the template meanwhile
				if( self::_edit( $appData, $journal, '/config.php', static function( array $state ) use ( $keys, $slots, $uri ): array {

					foreach( $keys as $routeKey )
						unset( $state['/nino/http/routes'][$routeKey] );

					foreach( $slots as $slot )
						unset( $state['/nino/html/images'][$slot] );

					if( $uri !== '' )
						unset( $state['/nino/html/navroutes'][$uri] );

					// A key that holds nothing is what the kernel has without it (AppData::DEFAULTS): config.php keeps what the
					// project decided
					foreach( [ '/nino/http/routes', '/nino/html/images', '/nino/html/navroutes' ] as $key )
						if( ( $state[$key] ?? null ) === [] )
							unset( $state[$key] );

					return $state;
				} ) === false )
					$fail = 'could not write config.php';

				foreach( $fail === null ? $paths : [] as $path )
					if( self::_edit( $appData, $journal, $path, static function( array $state ) use ( $owns ): ?array {

						$out = array_filter( $state, static fn( mixed $key ): bool => is_string( $key ) === false || str_starts_with( $key, '[[' ) === false || str_ends_with( $key, ']]' ) === false || $owns( substr( $key, 2, -2 ) ) === false, ARRAY_FILTER_USE_KEY );

						return count( $out ) === count( $state ) ? null : $out;
					}, $path === '/text/global.php' ) === false ) {
						$fail = 'could not write '. $path;
						break;
					}

				// What the keys had in meta.php, and the blacklist's entries for them
				foreach( $fail === null ? [ \Nino\Text::META_PATH, '/text/blacklist.php' ] : [] as $path )
					if( self::_edit( $appData, $journal, $path, static function( array $state ) use ( $owns, $path ): ?array {

						$list = $path !== \Nino\Text::META_PATH;
						$out 	= array_filter( $state, static fn( mixed $value, mixed $key ): bool => $owns( (string) ( $list === true ? $value : $key ) ) === false, ARRAY_FILTER_USE_BOTH );

						return count( $out ) === count( $state ) ? null : ( $list === true ? array_values( $out ) : $out );
					}, true ) === false ) {
						$fail = 'could not write '. $path;
						break;
					}

				// The template: the last that can be given back, and the one that is given back from its bytes
				if( $fail === null ) {

					$file = '/templates/'. $template. '.tpl';
					$real = \Nino\Filesystem::path( $appData, $file );

					if( file_exists( $real ) === true || is_link( $real ) === true ) {

						$entry = [ 'before' => self::_fileBytes( $real ), 'after' => null, 'mode' => @fileperms( $real ) ];

						if( @unlink( $real ) === false )
							$fail = 'could not remove '. $file;
						else
							$journal[$file] = $entry;

						self::_forget( $appData, $file );
					}
				}
			} catch( \Throwable $e ) {

				self::_undo( $appData, $journal );

				throw $e;
			}

			if( $fail !== null ) {

				$lost 	= self::_undo( $appData, $journal );
				$answer = self::_refuse( 500, $fail );

				if( $lost !== [] ) {

					trigger_error( 'Pages: removing the template "'. $template. '" failed and could not give back '. implode( ', ', $lost ), E_USER_WARNING );
					$answer = self::_refuse( 500, $fail, 'routes_unrestored', [ implode( ', ', $lost ) ] );
					$answer['error']['unrestored'] = $lost;
				}

				return $answer;
			}

			foreach( $keys as $routeKey )
				unset( $appData['/nino/http/routes'][$routeKey] );

			foreach( $slots as $slot )
				unset( $appData['/nino/html/images'][$slot] );

			if( $uri !== '' )
				unset( $appData['/nino/html/navroutes'][$uri] );

			// The files of the pictures, last: the slots are written without them, and what a slot that is left names stays. The
			// check reads config.php as it is now, which says more than the app data of a request that began before the write
			$held = [];

			foreach( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [] as $slot )
				if( is_array( $slot ) === true && is_string( $slot['filename'] ?? null ) === true )
					$held[ $slot['filename'] ] = true;

			$removed = [];

			foreach( array_keys( $files ) as $name )
				if( isset( $held[$name] ) === false && \Nino\Images::deleteUnlessShared( $appData, $name ) === true && is_file( \Nino\Filesystem::path( $appData, '/images/'. $name ) ) === false ) {
					$removed[] = $name;
					self::_prune( $appData, $name );
				}

			return [ 'template' => $template, 'uri' => $uri, 'routes' => $keys, 'texts' => $plan['texts'], 'slots' => $slots, 'files' => $removed, 'kept' => $plan['kept'], 'linked' => $plan['linked'], 'remnant' => $remnant ];
		}

		/**
		 *	Whether a template is no file of its own name: a link to another file, or one of two whose names differ in case only,
		 *	which a file system that folds case holds as one
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			The name of the template
		 *
		 *	@return 	bool
		 */
		private static function _alias( array &$appData, string $template ): bool {

			if( is_link( \Nino\Filesystem::path( $appData, '/templates/'. $template. '.tpl' ) ) === true )
				return true;

			foreach( self::templates( $appData ) as $name )
				if( $name !== $template && strtolower( $name ) === strtolower( $template ) )
					return true;

			return false;
		}

		/**
		 *	What else draws or reads in the project: the bodies of the routes that are not the page's own, and every template below
		 *	templates/ but its own file. A link that leads to the file of the template is named as the template it is
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$template			The name of the template, eg. 'page-news'
		 *	@param		array 		$own					The route keys that are the page's own
		 *	@param		array 		$stored				The stored routes
		 *
		 *	@return 	array										Name (a route key or a file of templates/) => its bytes; a link to the template itself holds its name as the bytes
		 */
		private static function _sources( array &$appData, string $template, array $own, array $stored ): array {

			$found = [];

			foreach( [ $stored, $appData['/nino/http/routes'] ?? [] ] as $routes )
				foreach( $routes as $routeKey => $route )
					if( in_array( (string) $routeKey, $own, true ) === false && is_array( $route ) === true && is_string( $route['body'] ?? null ) === true )
						$found[(string) $routeKey] = $route['body'];

			$root = \Nino\Filesystem::path( $appData, '/templates' );

			if( is_dir( $root ) === false )
				return $found;

			$self = realpath( $root. '/'. $template. '.tpl' );

			foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {

				if( $file->isFile() === false || $file->getExtension() !== 'tpl' || $file->getPathname() === $root. '/'. $template. '.tpl' )
					continue;

				$name = substr( $file->getPathname(), strlen( $root ) + 1 );

				// A link to the file is the template under another name
				if( $self !== false && $file->isLink() === true && realpath( $file->getPathname() ) === $self ) {
					$found[$name] = '/templates/'. $template;
					continue;
				}

				$bytes = @file_get_contents( $file->getPathname() );

				if( $bytes !== false )
					$found[$name] = $bytes;
			}

			return $found;
		}

		/**
		 *	Which of the sources match a pattern
		 *
		 *	@param		array 		$sources			See _sources()
		 *	@param		string		$pattern
		 *
		 *	@return 	array										The names
		 */
		private static function _readers( array $sources, string $pattern ): array {

			$names = [];

			foreach( $sources as $name => $bytes )
				if( preg_match( $pattern, (string) $bytes ) === 1 )
					$names[] = (string) $name;

			return $names;
		}

		/**
		 *	One write of remove(): a file of the project changed under its own lock, with what is needed to give it back noted - what
		 *	this call took out and where it stood, and nothing else. config.php is written under the lock the caller holds and keeps
		 *	it: Filesystem::mutate() lets the lock go with the write, and a window there is where another page could be put on the
		 *	template that is about to go
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$journal			(reference) Virtual path => what _undo() needs: 'before' and 'after' (the bytes, null for no file), 'mode', 'removed' and 'order'
		 *	@param		string		$path					A virtual path of a .php file
		 *	@param		callable	$change				function( array $state ): ?array - the new content, null where there is nothing to change
		 *	@param		bool			$dropEmpty		Whether a file that holds nothing after the change is no file: global.php, meta.php and the blacklist are none while they are empty, a file of a language is a language
		 *
		 *	@return 	bool										False where the file could not be written - a file with nothing to change is none
		 */
		private static function _edit( array &$appData, array &$journal, string $path, callable $change, bool $dropEmpty = false ): bool {

			$real 		= \Nino\Filesystem::path( $appData, $path );
			$entry 		= null;
			$nothing 	= false;
			$held 		= $path === '/config.php';

			$apply = function( mixed $state ) use ( $change, $real, &$entry, &$nothing ): ?array {

				$state = is_array( $state ) === true ? $state : [];
				$new 	 = $change( $state );

				if( $new === null ) {
					$nothing = true;
					return null;
				}

				// The bytes putFileContent() writes of an array: what the file holds when this is written, and what is compared to it
				$entry = [ 'before' => self::_fileBytes( $real ), 'after' => '<?php return '. var_export( $new, true ). ';', 'mode' => @fileperms( $real ) ] + self::_taken( $state, $new );

				return $new;
			};

			if( $held === true ) {

				unset( $appData['./nino/filesystem/cache'][$path] );

				$new = $apply( \Nino\Filesystem::getFileContent( $appData, $path, [] ) );

				if( $new === null )
					return true;

				$written = self::_replace( $real, $entry['after'], $entry['mode'] );

				self::_forget( $appData, $path );

			} else
				$written = \Nino\Filesystem::mutate( $appData, $path, $apply );

			if( $written === true ) {

				// Taken away only while it holds what was written: a writer that came in between has put its own in
				if( $dropEmpty === true && $entry['after'] === '<?php return '. var_export( [], true ). ';' && self::_fileBytes( $real ) === $entry['after'] && @unlink( $real ) === true ) {
					$entry['after'] = null;
					self::_forget( $appData, $path );
				}

				$journal[$path] = $entry;
			}

			return $written === true || $nothing === true;
		}

		/**
		 *	What a change took out of a file's content, and the order the content stood in: all that giving it back may put in again
		 *
		 *	@param		array 		$state				The content before
		 *	@param		array 		$new					The content after
		 *
		 *	@return 	array										[ 'removed' => ..., 'order' => ... ]: a list, a map or - for config.php - a map of the three keys that are written
		 */
		private static function _taken( array $state, array $new ): array {

			if( array_key_exists( '/nino/http/routes', $state ) === true || array_key_exists( '/nino/html/images', $state ) === true ) {

				$removed = [];
				$order 	 = [];

				foreach( [ '/nino/http/routes', '/nino/html/images', '/nino/html/navroutes' ] as $key ) {

					$was = is_array( $state[$key] ?? null ) === true ? $state[$key] : [];
					$now = is_array( $new[$key] ?? null ) === true ? $new[$key] : [];

					$removed[$key] = array_diff_key( $was, $now );
					$order[$key] 	 = array_keys( $was );
				}

				return [ 'removed' => $removed, 'order' => $order, 'kind' => 'config' ];
			}

			if( array_is_list( $state ) === true )
				return [ 'removed' => array_values( array_filter( $state, static fn( mixed $value ): bool => in_array( $value, $new, true ) === false ) ), 'order' => $state, 'kind' => 'list' ];

			return [ 'removed' => array_diff_key( $state, $new ), 'order' => array_keys( $state ), 'kind' => 'map' ];
		}

		/**
		 *	The content of a file that somebody else changed meanwhile, with what this call took out of it put in again where it
		 *	stood - and nothing else: what the other writer took out stays out
		 *
		 *	@param		array 		$now					What the file holds
		 *	@param		array 		$entry				See _edit()
		 *
		 *	@return 	array
		 */
		private static function _putBack( array $now, array $entry ): array {

			$merge = static function( array $now, array $removed, array $order ): array {

				$out = [];

				foreach( $order as $key )
					if( array_key_exists( $key, $now ) === true )
						$out[$key] = $now[$key];
					else if( array_key_exists( $key, $removed ) === true )
						$out[$key] = $removed[$key];

				return $out + $now;
			};

			if( $entry['kind'] === 'config' ) {

				foreach( $entry['removed'] as $key => $removed )
					if( $removed !== [] )
						$now[$key] = $merge( is_array( $now[$key] ?? null ) === true ? $now[$key] : [], $removed, $entry['order'][$key] );

				return $now;
			}

			if( $entry['kind'] === 'list' ) {

				$out = [];

				foreach( $entry['order'] as $value )
					if( in_array( $value, $now, true ) === true || in_array( $value, $entry['removed'], true ) === true )
						$out[] = $value;

				return array_values( array_unique( array_merge( $out, $now ), SORT_REGULAR ) );
			}

			return $merge( $now, $entry['removed'], $entry['order'] );
		}

		/**
		 *	Give back what remove() wrote, the last first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		$journal			See _edit()
		 *
		 *	@return 	array										The virtual paths that could not be given back
		 */
		private static function _undo( array &$appData, array $journal ): array {

			$lost = [];

			foreach( array_reverse( $journal, true ) as $path => $entry ) {

				$path = (string) $path;
				$real = \Nino\Filesystem::path( $appData, $path );

				if( \Nino\Filesystem::lockFile( $appData, $path ) === false ) {
					$lost[] = $path;
					continue;
				}

				try {

					// What this call wrote is still there: the file has the bytes it had. Anything else was written by another
					// writer since, whose keys stay, and only what this call took out is put in again
					if( self::_fileBytes( $real ) === $entry['after'] ) {

						if( $entry['before'] === null )
							$done = @unlink( $real );
						else
							$done = self::_replace( $real, $entry['before'], $entry['mode'] );

					} else if( isset( $entry['kind'] ) === true ) {

						$done = \Nino\Filesystem::mutate( $appData, $path, static function( mixed $now ) use ( $entry ): array {
							return self::_putBack( is_array( $now ) === true ? $now : [], $entry );
						} );
					} else
						$done = false;

					if( $done === false )
						$lost[] = $path;
				} finally {

					// config.php is the caller's lock to let go
					if( $path !== '/config.php' )
						\Nino\Filesystem::unlockFile( $appData, $path );
				}

				self::_forget( $appData, $path );
			}

			return $lost;
		}

		/**
		 *	The bytes of a file at its real path, null for none
		 *
		 *	@param		string		$real
		 *
		 *	@return 	string|null
		 */
		private static function _fileBytes( string $real ): ?string {

			clearstatcache( true, $real );
			$read = is_file( $real ) === true ? @file_get_contents( $real ) : false;

			return $read === false ? null : $read;
		}

		/**
		 *	A file put back as the bytes it had, beside it and renamed over: whole or not there, with the mode it had
		 *
		 *	@param		string		$real
		 *	@param		string		$bytes
		 *	@param		int|false	$mode
		 *
		 *	@return 	bool
		 */
		private static function _replace( string $real, string $bytes, int|false $mode ): bool {

			$temp = $real. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';

			if( @file_put_contents( $temp, $bytes ) !== strlen( $bytes ) ) {
				@unlink( $temp );
				return false;
			}

			if( $mode !== false )
				@chmod( $temp, $mode & 0777 );

			if( @rename( $temp, $real ) === false ) {
				@unlink( $temp );
				return false;
			}

			return true;
		}

		/**
		 *	What the request has read of a file that was written outside of Filesystem, forgotten
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A virtual path
		 *
		 *	@return 	void
		 */
		private static function _forget( array &$appData, string $path ): void {

			unset( $appData['./nino/filesystem/cache'][$path], $appData['./nino/html/filecache'], $appData['./nino/locales/text'] );

			$real = \Nino\Filesystem::path( $appData, $path );

			if( function_exists( 'opcache_invalidate' ) === true && is_file( $real ) === true )
				opcache_invalidate( $real, true );
		}

		/**
		 *	The directories a picture file was in, taken away while they are empty and below /images/: a slot has a directory of its
		 *	own, which holds nothing once its file is gone. rmdir() takes an empty one and nothing else
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$filename			A filename below /images/
		 *
		 *	@return 	void
		 */
		private static function _prune( array &$appData, string $filename ): void {

			$dir = dirname( $filename );

			while( $dir !== '.' && $dir !== '/' && $dir !== '' && str_contains( $dir, '..' ) === false && @rmdir( \Nino\Filesystem::path( $appData, '/images/'. $dir ) ) === true )
				$dir = dirname( $dir );
		}

		/**
		 *	duplicate() under the lock: what the copy reads is worked out first and handed to the one path that makes a page
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$from					The template to copy
		 *	@param		array 		$page					See duplicate()
		 *
		 *	@return 	array										See duplicate()
		 */
		private static function _duplicate( array &$appData, string $from, array $page ): array {

			$new = is_string( $page['template'] ?? null ) === true ? $page['template'] : '';
			$is  = '/template/'. $new. '/';

			if( strlen( $new ) > self::MAX_NAME || preg_match( self::TEMPLATE, $new ) !== 1 )
				return self::_refuse( 400, 'invalid template: "'. $new. '"', 'routes_invalid_template', [ $new ], 'template' );

			if( $new === $from )
				return self::_refuse( 409, 'template already exists: "'. $new. '"', 'routes_template_exists', [ $new ], 'template' );

			// What the request read of the files before the lock may be older than the files
			unset( $appData['./nino/filesystem/cache'] );

			$source = strlen( $from ) <= self::MAX_NAME && preg_match( self::TEMPLATE, $from ) === 1 && in_array( $from, self::templates( $appData ), true ) === true ? self::_bytes( $appData, '/templates/'. $from. '.tpl' ) : null;

			if( $source === null )
				return self::_refuse( 404, 'unknown template: "'. $from. '"', 'routes_unknown_template', [ $from ] );

			// A link to another file, or a twin that differs in case only, is two names for one file on some file systems: not a
			// template to copy from or to
			if( self::_alias( $appData, $from ) === true || self::_alias( $appData, $new ) === true )
				return self::_refuse( 409, 'the template "'. $from. '" is a link or has a twin that differs in case', 'routes_template_alias', [ $from ], 'template' );

			// Pictures under the new name that no slot names - what a feature kept of a page that was removed - are in the way too
			$leftover = \Nino\Filesystem::path( $appData, '/images/template/'. $new );

			if( is_dir( $leftover ) === true && count( scandir( $leftover ) ?: [] ) > 2 )
				return self::_refuse( 409, 'there are pictures under "'. $is. '" already', 'routes_namespace_taken', [ $new ], 'template' );

			$was 		= '/template/'. $from. '/';
			$meta 	= \Nino\Text::meta( $appData );
			$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
			$images = is_array( $config['/nino/html/images'] ?? null ) === true ? $config['/nino/html/images'] : [];

			// The words, in every language that has a file and in global.php: each key of the namespace as a key of the copy, with the
			// text it has. Under the new namespace there is nothing yet, whatever a leftover of an earlier page is - it is not ours to
			// overwrite
			$texts = [];
			$keys 	= [];

			foreach( array_merge( array_map( static fn( string $locale ): string => '/text/'. $locale. '.php', \Nino\Locales::getTextLocales( $appData ) ), [ '/text/global.php' ] ) as $path ) {

				$content = \Nino\Filesystem::getFileContent( $appData, $path, [] );

				foreach( is_array( $content ) === true ? $content : [] as $key => $text ) {

					if( is_string( $key ) === false || str_starts_with( $key, '[[' ) === false || str_ends_with( $key, ']]' ) === false )
						continue;

					$plain = substr( $key, 2, -2 );

					if( str_starts_with( $plain, $is ) === true )
						return self::_refuse( 409, 'the namespace "'. $is. '" is in use', 'routes_namespace_taken', [ $new ], 'template' );

					if( str_starts_with( $plain, $was ) === true ) {
						$texts[$path]['[['. $is. substr( $plain, strlen( $was ) ). ']]'] = $text;
						$keys[$is. substr( $plain, strlen( $was ) )] = true;
					}
				}
			}

			foreach( $meta as $key => $entry ) {

				if( str_starts_with( (string) $key, $is ) === true )
					return self::_refuse( 409, 'the namespace "'. $is. '" is in use', 'routes_namespace_taken', [ $new ], 'template' );

				if( str_starts_with( (string) $key, $was ) === true )
					$texts[\Nino\Text::META_PATH][$is. substr( (string) $key, strlen( $was ) )] = $entry;
			}

			// The slots, and a file of its own for each of them
			$slots = [];
			$files = [];

			foreach( $images as $uri => $slot ) {

				if( is_string( $uri ) === false || is_array( $slot ) === false )
					continue;

				if( str_starts_with( $uri, $is ) === true )
					return self::_refuse( 409, 'the namespace "'. $is. '" is in use', 'routes_namespace_taken', [ $new ], 'template' );

				if( str_starts_with( $uri, $was ) === false )
					continue;

				$copy 	= $is. substr( $uri, strlen( $was ) );
				$file 	= $slot['filename'] ?? null;

				// A file below /images/ is made again under the name of the new slot - the old name with the path of the slot
				// replaced - and a slot that has none, or none that can be read, has none; the reference of a picture of the kernel
				// is the slot's file in the copy as well
				if( is_string( $file ) === true && $file !== '' && str_starts_with( $file, '/' ) === false ) {

					$bytes = \Nino\Images::read( $appData, $file );

					if( $bytes === false )
						$slot['filename'] = null;
					else {

						$name = ltrim( $copy, '/' ). ( str_starts_with( $file, ltrim( $uri, '/' ). '.' ) === true ? substr( $file, strlen( ltrim( $uri, '/' ) ) ) : '.'. preg_replace( '/[^A-Za-z0-9]/', '', pathinfo( $file, PATHINFO_EXTENSION ) ) );

						if( is_file( \Nino\Filesystem::path( $appData, '/images/'. $name ) ) === true )
							return self::_refuse( 409, 'the picture "'. $name. '" is there', 'routes_namespace_taken', [ $new ], 'template' );

						$slot['filename'] = $name;
						$files[$name] = $bytes;
					}
				}

				$slots[$copy] = $slot;
			}

			// The source of the copy: what the parser reads as a fill, a source or the value of an attribute of a call and that names a
			// key of the original's namespace names the copy's - free text, a comment and the attributes of html stay as they are.
			// Where one page draws the original, its four details (and no detail of a page below it) and a link to it with page:<uri>
			// are the new page's: the copy shows and links to itself
			$old 	 = [];
			$routes = is_array( $config['/nino/http/routes'] ?? null ) === true ? $config['/nino/http/routes'] : [];

			foreach( self::blocks( $routes ) as $block )
				if( self::templateOf( (string) ( $routes[ $block['keys'][0] ]['body'] ?? '' ) ) === $from )
					$old[] = $block['uri'];

			$uri 	 = self::normalizeUri( is_string( $page['uri'] ?? null ) === true ? $page['uri'] : '' );
			$was_ 	= count( $old ) === 1 && $uri !== null ? $old[0] : null;
			$change = static function( string $value ) use ( $was, $is, $was_, $uri ): string {

				if( str_starts_with( $value, $was ) === true )
					return $is. substr( $value, strlen( $was ) );

				if( $was_ !== null && preg_match( '#^/_nino/webpage'. preg_quote( $was_, '#' ). '/(name|title|description|uri)$#D', $value, $field ) === 1 )
					return '/_nino/webpage'. $uri. '/'. $field[1];

				if( $was_ !== null && preg_match( '#^page:'. preg_quote( $was_, '#' ). '(?=$|[\#?])#', $value ) === 1 )
					return 'page:'. $uri. substr( $value, 5 + strlen( $was_ ) );

				return $value;
			};

			// The same in the words that are copied: a fill or a link in a text
			$words = static function( string $text ) use ( $was, $is, $was_, $uri ): string {

				$text = (string) preg_replace( '#(\[\[)'. preg_quote( $was, '#' ). '#', '$1'. $is, $text );

				if( $was_ !== null ) {
					$text = (string) preg_replace( '#\[\[/_nino/webpage'. preg_quote( $was_, '#' ). '/(name|title|description|uri)\]\]#', '[[/_nino/webpage'. addcslashes( (string) $uri, '\\$' ). '/$1]]', $text );
					$text = (string) preg_replace( '#(?<![A-Za-z0-9_-])page:'. preg_quote( $was_, '#' ). '(?=$|[\#?"\'\s])#', 'page:'. addcslashes( (string) $uri, '\\$' ), $text );
				}

				return $text;
			};

			foreach( $texts as $path => $entries )
				if( $path !== \Nino\Text::META_PATH )
					foreach( $entries as $key => $text )
						$texts[$path][$key] = is_string( $text ) === true ? $words( $text ) : $text;

			$containers = \Nino\Components::containers( $appData );
			$nodes 			= \Nino\Markup::parse( $source, $containers );

			if( \Nino\Markup::write( $nodes, $containers ) !== $source )
				return self::_refuse( 409, 'the source of "'. $from. '" is not one the parser writes back as it is', 'routes_source_unreadable', [ $from ], 'template' );

			$source = \Nino\Markup::write( self::_rewrite( $nodes, $change ), $containers );

			// The name the template has in the lists of the workbench is the new page's, in the language of the site
			$name = $page['text'][ \Nino\Locales::getNativeLocale( $appData ) ]['name'] ?? null;

			if( is_string( $name ) === true && trim( $name ) !== '' )
				$source = (string) preg_replace_callback( '~\A<!--[\t ]*nino:template-name[\t ]+[^\r\n<>]+?[\t ]*-->~', static fn(): string => '<!-- nino:template-name '. trim( (string) preg_replace( '/[\r\n<>]+/', ' ', $name ) ). ' -->', $source, 1 );

			$answer = self::_put( $appData, [ 'originalUri' => '', 'source' => $source ] + array_diff_key( $page, [ 'stamp' => true ] ), [ 'texts' => $texts, 'slots' => $slots, 'files' => $files ] );

			if( isset( $answer['error'] ) === false )
				$answer['copied'] = [ 'texts' => count( $keys ), 'slots' => count( $slots ), 'files' => count( $files ) ];

			return $answer;
		}

		/**
		 *	The strings in a template that name something: the textfills, the sources of the calls and the values of their attributes, as
		 *	\Nino\Markup reads them. A comment, a script and the text between the tags are none of them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$bytes				html+
		 *
		 *	@return 	array										The strings
		 */
		private static function _marks( array &$appData, string $bytes ): array {

			$out 	= [];
			$walk = null;
			$walk = static function( array $nodes ) use ( &$walk, &$out ): void {

				foreach( $nodes as $node ) {

					if( isset( $node['f'] ) === true )
						$out[] = (string) $node['f'];

					if( isset( $node['n'] ) === false )
						continue;

					if( is_string( $node['s'] ?? null ) === true )
						$out[] = $node['s'];

					foreach( is_array( $node['a'] ?? null ) === true ? $node['a'] : [] as $value )
						if( is_string( $value ) === true )
							$out[] = $value;

					if( is_array( $node['c'] ?? null ) === true )
						$walk( $node['c'] );
				}
			};

			$walk( \Nino\Markup::parse( $bytes, \Nino\Components::containers( $appData ) ) );

			return $out;
		}

		/**
		 *	Whether something stands under the name of a page template already: the template itself, a text of the namespace
		 *	/template/<name>/ in any language or in global.php, an entry of meta.php, a picture slot or a picture file below
		 *	images/template/<name>/. A page that was removed may have left what others read ('kept'), and a tool that writes the words
		 *	of a new page before it asks put() for the page would write over them: it asks here first and takes another name.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The name of a template, eg. 'page-news'
		 *
		 *	@return 	bool
		 */
		public static function taken( array &$appData, string $name ): bool {

			if( strlen( $name ) > self::MAX_NAME || preg_match( self::TEMPLATE, $name ) !== 1 )
				return true;

			if( in_array( $name, array_map( 'strtolower', self::templates( $appData ) ), true ) === true || in_array( $name, self::reserved(), true ) === true )
				return true;

			$namespace = '/template/'. $name. '/';

			foreach( array_merge( array_map( static fn( string $locale ): string => '/text/'. $locale. '.php', \Nino\Locales::getTextLocales( $appData ) ), [ '/text/global.php' ] ) as $path ) {

				$content = \Nino\Filesystem::getFileContent( $appData, $path, [] );

				foreach( is_array( $content ) === true ? array_keys( $content ) : [] as $key )
					if( is_string( $key ) === true && str_starts_with( $key, '[['. $namespace ) === true )
						return true;
			}

			foreach( array_keys( \Nino\Text::meta( $appData ) ) as $key )
				if( str_starts_with( (string) $key, $namespace ) === true )
					return true;

			foreach( array_keys( (array) ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [] ) ) as $slot )
				if( str_starts_with( (string) $slot, $namespace ) === true )
					return true;

			$dir = \Nino\Filesystem::path( $appData, '/images/template/'. $name );

			if( is_dir( $dir ) === true )
				foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
					if( $file->isFile() === true )
						return true;

			return false;
		}

		/**
		 *	Whether a picture of an earlier page is left under a name: a file below images/template/<name>/ that no slot names. A tool
		 *	that makes a page writes its words and slots before it asks put() for the page, so these are no leftover here - a copy,
		 *	which writes all of what it has itself, asks for them as well (see _duplicate()). What a feature kept of its own is not
		 *	the kernel's to know
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$name					The name of a template
		 *
		 *	@return 	bool
		 */
		private static function _leftovers( array &$appData, string $name ): bool {

			$dir 	= \Nino\Filesystem::path( $appData, '/images/template/'. $name );
			$held = [];

			foreach( (array) ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [] ) as $slot )
				if( is_array( $slot ) === true && is_string( $slot['filename'] ?? null ) === true )
					$held[ $slot['filename'] ] = true;

			if( is_dir( $dir ) === true )
				foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
					if( $file->isFile() === true && isset( $held[ 'template/'. $name. substr( $file->getPathname(), strlen( $dir ) ) ] ) === false )
						return true;

			return false;
		}

		/**
		 *	The nodes of a template with the keys that begin with one prefix begin with another: the textfills, the sources of the
		 *	calls and the values of their attributes - nothing the parser reads as text
		 *
		 *	@param		array 		$nodes
		 *	@param		callable	$change				function( string $value ): string - what a name is made
		 *
		 *	@return 	array
		 */
		private static function _rewrite( array $nodes, callable $change ): array {

			foreach( $nodes as $at => $node ) {

				if( isset( $node['f'] ) === true )
					$nodes[$at]['f'] = $change( (string) $node['f'] );

				if( isset( $node['n'] ) === false )
					continue;

				if( is_string( $node['s'] ?? null ) === true )
					$nodes[$at]['s'] = $change( $node['s'] );

				foreach( is_array( $node['a'] ?? null ) === true ? $node['a'] : [] as $name => $value )
					if( is_string( $value ) === true )
						$nodes[$at]['a'][$name] = $change( $value );

				if( is_array( $node['c'] ?? null ) === true )
					$nodes[$at]['c'] = self::_rewrite( $node['c'], $change );
			}

			return $nodes;
		}

		/**
		 *	The bytes of a file of the project, null where there is none
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A virtual path
		 *
		 *	@return 	string|null
		 */
		private static function _bytes( array &$appData, string $path ): ?string {

			$real = \Nino\Filesystem::path( $appData, $path );
			$read = is_file( $real ) === true ? @file_get_contents( $real ) : false;

			return $read === false ? null : $read;
		}

		/**
		 *	A file of a template, made as a new one and with nothing else: the bytes of $source
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$file					A virtual path, eg. '/templates/page-x.tpl'
		 *	@param		string		$source
		 *
		 *	@return 	bool|null								True where it was made, null where a file of that name is there, false where it could not be written
		 */
		private static function _create( array &$appData, string $file, string $source ): ?bool {

			\Nino\Filesystem::forceDir( $appData, dirname( ltrim( $file, '/' ) ) );

			$real 	= \Nino\Filesystem::path( $appData, $file );
			$temp 	= $real. '.'. bin2hex( random_bytes( 6 ) ). '.tmp';

			unset( $appData['./nino/filesystem/cache'][$file] );

			// Written beside it and linked into place: link() fails for any name that is taken - a link that points nowhere
			// as well, which fopen( 'x' ) follows - and the file is whole or not there
			if( @file_put_contents( $temp, $source ) !== strlen( $source ) ) {
				@unlink( $temp );
				return false;
			}

			$linked = @link( $temp, $real );

			@unlink( $temp );

			if( $linked === true )
				return true;

			if( file_exists( $real ) === true || is_link( $real ) === true )
				return null;

			// A filesystem that has no hard links: the name is free and is no link, so the file is made at its place, and only
			// where it is not there yet
			$handle = @fopen( $real, 'xb' );

			if( $handle === false )
				return is_file( $real ) === true ? null : false;

			$written = fwrite( $handle, $source );
			fclose( $handle );

			if( $written !== strlen( $source ) ) {
				@unlink( $real );
				return false;
			}

			return true;
		}

		/**
		 *	One file of the text of a failed page given back, under its own lock: the keys of the page in it are what they were
		 *	before - gone where they were new, their old value and their place where they stood - and every other key stays as
		 *	it is, whoever wrote it meanwhile. A file that was none and holds nothing else is removed
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$path					A virtual path
		 *	@param		array 		$seen					What the write saw: 'existed', and 'prior' - the keys of the page the file had, with their values - or, for the blacklist, 'had'
		 *	@param		array 		$fragment			The keys the page merged into the file, or the one entry of the blacklist
		 *	@param		bool			$list					Whether the file is the blacklist, a list of keys and no map
		 *
		 *	@return 	bool
		 */
		private static function _giveBack( array &$appData, string $path, array $seen, array $fragment, bool $list ): bool {

			$given = \Nino\Filesystem::mutate( $appData, $path, function( array $content ) use ( $seen, $fragment, $list ): array {

				if( $list === true )
					return array_values( array_diff( $content, $fragment ) );

				$out = [];

				foreach( $content as $key => $value )
					if( array_key_exists( $key, $fragment ) === false )
						$out[$key] = $value;
					else if( array_key_exists( $key, $seen['prior'] ) === true )
						$out[$key] = $seen['prior'][$key];

				return $out;
			} );

			if( $seen['existed'] === false && \Nino\Filesystem::getFileContent( $appData, $path, [] ) === [] ) {
				@unlink( \Nino\Filesystem::path( $appData, $path ) );
				unset( $appData['./nino/filesystem/cache'][$path], $appData['./nino/html/filecache'], $appData['./nino/locales/text'] );
			}

			return $given;
		}

		/**
		 *	The addresses a request posts, one for each language: a language maps to its address, and '' to the address of every
		 *	language that has none of its own. Every address is made a safe path and kept off what the workbench owns, and is
		 *	given once: an address posted for two keys is refused, the later key named, like an address another page has -
		 *	languages that are to be reached at the same address are the empty key's, not two entries that agree. An empty one is
		 *	none, so a language has an address of its own, else the shared one, else the request is refused for it. So is a key
		 *	that is no language of the site - the one a form drawn before a language was removed sends. The first thing wrong is
		 *	the answer
		 *
		 *	@param		array 		$posted				The posted addresses
		 *	@param		array 		$locales			The languages of the site
		 *
		 *	@return 	array										[ 'addresses' => language => its address, in the order of the languages, 'fields' => address => the field of the key it was posted with ], or [ 'error' => ... ]
		 */
		private static function _addresses( array $posted, array $locales ): array {

			$given 	= [];
			$fields = [];

			foreach( $posted as $key => $value ) {

				$key 		= (string) $key;
				$field 	= $key === '' ? 'httpUri' : 'httpUri-'. $key;

				if( $key !== '' && in_array( $key, $locales, true ) === false )
					return self::_refuse( 400, 'unknown language: "'. $key. '"', 'routes_unknown_locale', [ $key ], $field );

				$raw = is_string( $value ) === true ? trim( $value ) : '';

				if( $raw === '' )
					continue;

				$address = self::normalizeUri( $raw );

				if( $address === null )
					return self::_refuse( 400, 'invalid http uri: "'. $raw. '"', 'routes_invalid_http_uri', [ $raw ], $field );

				if( \Nino\Http::isReservedUri( $address ) === true )
					return self::_refuse( 409, 'reserved http uri: "'. $address. '"', 'routes_reserved_http_uri', [ $address ], $field );

				// Once: languages that are to share an address are the empty key's to say
				if( isset( $fields[$address] ) === true )
					return self::_refuse( 400, 'duplicate http uri: "'. $address. '"', 'routes_duplicate_http_uri', [ $address ], $field );

				$given[$key]			= $address;
				$fields[$address]	= $field;
			}

			$addresses = [];

			foreach( $locales as $locale ) {

				$address = $given[$locale] ?? $given[''] ?? null;

				if( $address === null )
					return self::_refuse( 400, 'missing http uri for '. $locale, 'routes_missing_http_uri', [ $locale ], 'httpUri-'. $locale );

				$addresses[$locale] = $address;
			}

			return [ 'addresses' => $addresses, 'fields' => $fields ];
		}

		/**
		 *	The routes a page gets from the address of each language: the languages are grouped by address, the rule
		 *	\Nino\Modules\Legal routes its pages by. An address of exactly one language is a route of that language, an address
		 *	several languages share is a route without one, and when every language shares the address - a page that has one, on
		 *	a site with one language as well - that is the one route that serves them all, with no language. An address is given
		 *	once in a request (see _addresses()), so the languages that share one are those the empty key serves, and a page has
		 *	at most one route without a language. The routes of one language stand first, as \Nino\Http::findRouteUri() answers
		 *	the first route that fits
		 *
		 *	@param		array 		$addresses		Locale => its address
		 *
		 *	@return 	array										[ [ 'address' => ..., 'locale' => the language, '' for every one ], ... ] in the order the routes are written
		 */
		private static function _plan( array $addresses ): array {

			$groups = [];

			foreach( $addresses as $locale => $address )
				$groups[$address][] = (string) $locale;

			if( count( $groups ) === 1 )
				return [ [ 'address' => (string) array_key_first( $groups ), 'locale' => '' ] ];

			$bound 	= [];
			$shared = [];

			foreach( $groups as $address => $sharing )
				if( count( $sharing ) === 1 )
					$bound[] = [ 'address' => (string) $address, 'locale' => $sharing[0] ];
				else
					$shared[] = [ 'address' => (string) $address, 'locale' => '' ];

			return array_merge( $bound, $shared );
		}

		/**
		 *	The /nino/http/routes array key one page entry occupies - always derived from its address (the real, reachable
		 *	path), never its Element-URI: \Nino\Http::requestRoute() matches a route by looking up '<METHOD>:/'.$httpUri as a
		 *	literal array key, not by scanning for a route whose own 'uri' field matches. So a page can only be reached at the
		 *	path its key names, and its Element-URI stays decoupled from it: the home page keeps a stable '/home' identifier
		 *	while it lives at '/'
		 *
		 *	@param		string		$httpUri
		 *
		 *	@return 	string
		 */
		private static function _routeKey( string $httpUri ): string {
			return 'GET://'. trim( $httpUri, '/' );
		}

		/**
		 *	Where a membership a save newly adds goes: behind everything already in that menu. Read across every route rather
		 *	than just the page ones, so a hand-written route in the same menu is counted too. The runtime-only memberships
		 *	('/nino/html/navroutes') are not read: a route that shares the number falls back to the order the routes stand in,
		 *	which the shortcode keeps for equal priorities
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		string		$navKey				Eg. 'main'
		 *
		 *	@return 	int
		 */
		private static function _nextPrio( array $routes, string $navKey ): int {

			$last = 0;
			foreach( $routes as $route )
				$last = max( $last, (int) ( $route['navs'][$navKey] ?? 0 ) );

			return $last + 1;
		}

		/**
		 *	The routes of one page written into the route array, keeping the slot the page already occupies even when its keys
		 *	change: the route array's own order is what breaks a tie between two equal menu priorities (see
		 *	Modules\Navigation::routeLines()) and what the list of a panel is sorted by, so renaming the address of a page must
		 *	not quietly move it to the bottom of both. The routes it had before go, and the new ones stand where the first of
		 *	them stood - behind everything for a page that had none
		 *
		 *	@param		array 		$routes				The persisted route array
		 *	@param		array 		$previousKeys	The keys of the page's routes as they stand, in the order they stand in; none for a new page
		 *	@param		array 		$put					Route key => route, the routes to write in the order they are to stand in
		 *
		 *	@return 	array
		 */
		private static function _putRoute( array $routes, array $previousKeys, array $put ): array {

			$present = array_values( array_filter( $previousKeys, fn( string $key ): bool => isset( $routes[$key] ) ) );

			if( $present === [] )
				return $routes + $put;

			$out = [];

			foreach( $routes as $key => $route )
				if( in_array( $key, $present, true ) === false )
					$out[$key] = $route;
				else if( $key === $present[0] )
					$out = $out + $put;

			return $out;
		}
	}
}
