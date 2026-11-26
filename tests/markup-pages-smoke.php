<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	markup-pages-smoke.php	Test of what a page is drawn as: the four starter pages with their frame in
 *											both languages, the workbench shell with a panel open, the loops over
 *											the elements with components in them, the content of
 *											a mail and the maintenance page - each compared byte for byte with the
 *											file under tests/fixtures/markup/starter/. The setup wizard is run for real
 *											(Setup::apiApply) in a sandbox whose file times are fixed, so
 *											that the three sizes a page draws from chance - the token of the form, the
 *											nonce of the script block and the stamp of the favicons - are set at their
 *											source and nothing is replaced in an output. The fourth, the version of a
 *											bundle, is made of the path of the bundle's files, which is a directory of the
 *											run's own: a page and its fixture are compared with the versions told apart
 *											by name (pagesVersionless()), the same bundle under the same name in both.
 *											The year of the footer is fixed through a callback of the response, as the rest.
 *											What a template or a value can do to the page is checked by
 *											tests/markup-safety-smoke.php.
 *
 *	Usage: php tests/markup-pages-smoke.php
 *				 php tests/markup-pages-smoke.php --record=DIR	writes the outputs as fixtures into DIR, with the versions of the bundles told apart by name
 */

// Everything this run builds - the features directory and the site the pages are drawn from - stands in one directory of its own below the temporary directory, named by chance:
// two runs of this suite, in two copies or by two users, share no file, so none waits for another and none takes a site from under another. The directory is taken away when the
// process ends, however it ends. Its path goes into the version of a bundle, which is why the version is compared by name (pagesVersionless())
$GLOBALS['pagesRun'] = sys_get_temp_dir(). '/nino-markup-pages-'. bin2hex( random_bytes( 6 ) );
defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', $GLOBALS['pagesRun']. '/features' );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

if( mkdir( $GLOBALS['pagesRun'], 0755, true ) === false ) {
	fwrite( STDERR, "markup-pages-smoke: the directory of the run could not be made\n" );
	exit( 1 );
}

register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( $GLOBALS['pagesRun'] );
} );

// The one feature of the run is the test feature of the kernel, in a features directory made again for the run: the cases use its components as their material
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
mkdir( NINO_FEATURES_DIR, 0755, true );
\Nino\Filesystem::copyDir( __DIR__. '/fixtures/kit/Kit', NINO_FEATURES_DIR. '/Kit' );

// One time for every file, as for the site: the version of a bundle is made of file times
foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( NINO_FEATURES_DIR. '/Kit', \FilesystemIterator::SKIP_DOTS ) ) as $file )
	touch( $file->getPathname(), 1767225600 );

/**
 *	The directory the site of the pages is built in: one in the directory of the run, so that the run's end takes it away
 *
 *	@return 	string
 */
function pagesSiteDir(): string {
	return $GLOBALS['pagesRun']. '/site';
}

/**
 *	A page with the versions of its bundles told apart by name: the version a bundle is linked with is a hash over the path, the size and the time of its files, and the path is
 *	the run's own. The first version a page names becomes 000000000001, the second 000000000002, and so on, so that a page that links two bundles by one version, or one bundle by two,
 *	differs from a page that does not. The hash of nothing (a bundle none of whose files is there) is the same in every run and stays as it is. A text without such a version is
 *	returned as it is
 *
 *	@param		string		$page
 *
 *	@return 	string
 */
function pagesVersionless( string $page ): string {

	$names = [];

	return (string) preg_replace_callback( '#(/\.cache/[A-Za-z0-9._-]+\?v=)(?!da39a3ee5e6b)([0-9a-f]{12})#', static function( array $found ) use ( &$names ): string {
		$names[$found[2]] ??= sprintf( '%012x', count( $names ) + 1 );
		return $found[1]. $names[$found[2]];
	}, $page );
}

/**
 *	The site the wizard builds with every unit and the four starter pages, a page 'blank' at /new-webpage, the
 *	collection '/probe' with three elements and a few templates of the cases below. Every file has the same time,
 *	so the version of a bundle and the stamp of the favicons are the same in every run
 *
 *	@param		bool			$tools				Copy _admin and _nino into it, for the pages of the workbench
 *
 *	@return 	string										The directory
 */
function pagesSite( bool $tools ): string {

	$appData = ninoSandbox( 'markup-pages' );
	$sandbox = ninoSandboxDir( $appData );

	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

	$appData['/nino/html/navs'] = \Nino\AppData::DEFAULTS['/nino/html/navs'];
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf' ];

	foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $dir )
		mkdir( $sandbox. $dir, 0777, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/log'					=> false,
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> 'de_DE',
		'/nino/locales/available'	=> [ 'de_DE', 'en_US' ],
		'/nino/modules'						=> $appData['/nino/modules'],
		'/nino/html/assets'				=> [],
		'/nino/http/routes'				=> [],
	] );

	[ $status, $body ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => array_keys( \Nino\Install\Setup::units() ), 'native' => 'de_DE' ] );

	if( $status !== 200 )
		throw new \RuntimeException( 'Setup::apiApply: '. $status. ' '. json_encode( $body ) );

	$applied = \Nino\Install\StarterPages::apply( $appData );

	if( $applied !== true )
		throw new \RuntimeException( 'StarterPages::apply: '. $applied );

	// The page 'blank' as its unit proposes it
	$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
	$routes['GET://new-webpage'] = [ 'uri' => '/blank', 'body' => '[template /templates/page-blank]', 'navs' => [ 'main' => 4, 'footer' => 4 ] ];
	$appData['/nino/http/routes'] = $routes;
	\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );
	\Nino\Features::mergeText( $appData, '/text/global.php', [ '[[/_nino/webpage/blank/uri]]' => '/new-webpage' ] );

	$manifest = include __DIR__. '/../_admin/install/library/pages/blank/manifest.php';

	foreach( [ 'de_DE', 'en_US' ] as $locale ) {

		$meta = [];

		foreach( [ 'name', 'title', 'description' ] as $field )
			$meta['[[/_nino/webpage/blank/'. $field. ']]'] = \Nino\Text::sanitizeValue( \Nino\Features::localized( $manifest['suggest'][$field] ?? '', $locale ), 'plain' );

		\Nino\Features::mergeText( $appData, '/text/'. $locale. '.php', $meta );
	}

	// What the step "personal data" of the wizard writes: fixed values, stored as the Text panel stores a text
	$company = [
		'[[/project/company/general/name]]'					=> 'Muster & Söhne GmbH',
		'[[/project/company/general/description]]'	=> 'Probe-Beschreibung für N107.',
		'[[/project/company/contact/email]]'				=> 'kontakt@example.com',
		'[[/project/company/contact/phone]]'				=> '+49 30 000000',
		'[[/project/company/contact/address]]'			=> "Musterstraße 1\n10115 Berlin",
		'[[/project/company/contact/country]]'			=> 'DE',
		'[[/project/website/general/url]]'					=> 'example.com',
		'[[/project/website/general/author]]'				=> 'N107',
	];

	foreach( $company as $key => $value )
		$company[$key] = \Nino\Text::sanitizeValue( $value, 'plain' );

	\Nino\Features::mergeText( $appData, '/text/global.php', $company, true );

	// A collection to loop over: a note with a call and a fill in it is the point of the first
	\Nino\Elements::insertElementType( $appData, '/probe', [
		'title'		=> [ 'type' => 'string' ],
		'body'		=> [ 'type' => 'string', 'html' => true, 'blocks' => true ],
		'category'	=> [ 'type' => 'string' ],
		'price'		=> [ 'type' => 'double' ],
		'photo'		=> [ 'type' => 'image', 'width' => 40, 'height' => 30 ],
		'note'		=> [ 'type' => 'string' ],
	] );
	\Nino\Elements::insertElement( $appData, '/probe/eins', [ 'title' => 'Eins', 'body' => '<p>Text <strong>eins</strong> [csrf]</p>', 'category' => 'A', 'price' => 9.5, 'note' => 'A [title /x] and [csrf] and [[/project/company/general/name]] in a text' ], '*' );
	\Nino\Elements::insertElement( $appData, '/probe/zwei', [ 'title' => 'Zwei', 'body' => '<p>Text zwei</p>', 'category' => 'B', 'price' => 12.0 ], '*' );
	\Nino\Elements::insertElement( $appData, '/probe/drei', [ 'title' => 'Drei "quoted" & more', 'body' => '<p>Text drei</p>', 'category' => 'A', 'price' => 3.0 ], '*' );

	foreach( [
		'probe-empty'				=> '<p class="empty">[[/project/company/general/name]] has [prose text="nothing"]</p>',
		'probe-row'					=> '<li>[[/project/company/general/name]]</li>',
		'page-x.de_DE'			=> '<main>[prose text="Deutsch"]</main>',
		'page-x.en_US'			=> '<main>[prose text="English"]</main>',
	] as $name => $template )
		\Nino\Filesystem::putFileContent( $appData, '/templates/'. $name. '.tpl', $template );

	// The tools of the workbench live in their own folders; the sandbox finds them below its own root
	if( $tools === true )
		foreach( [ '_admin', '_nino' ] as $dir )
			exec( 'cp -a '. escapeshellarg( dirname( __DIR__ ). '/'. $dir ). ' '. escapeshellarg( $sandbox. '/'. $dir ) );

	// One time for every file: the version of a bundle and the stamp of the favicons are made of file times
	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox, \FilesystemIterator::SKIP_DOTS ) ) as $file )
		touch( $file->getPathname(), 1767225600 );

	// The site stands where the run's end takes it away
	if( is_dir( pagesSiteDir() ) === true )
		\Nino\Filesystem::removeDir( pagesSiteDir() );

	rename( $sandbox, pagesSiteDir() );

	return pagesSiteDir();
}

/**
 *	A request's own app data on the site, booted as \Nino\init() boots it
 *
 *	@param		string		$sandbox
 *	@param		string		$locale
 *	@param		bool			$kit					Whether the test feature is on, as a feature is in a project that switched it on in the Features panel: the starter site is plain html and has none
 *
 *	@return 	array
 */
function pagesBoot( string $sandbox, string $locale, bool $kit = false ): array {

	$_SESSION = [];

	// A cold request finds no tree kept of any template
	if( ( $GLOBALS['pagesCold'] ?? false ) === true )
		\Nino\Filesystem::removeDir( $sandbox. '/private/data/cache/trees' );

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );

	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	$appData['./nino/test/sandbox']						= $sandbox;

	\Nino\Filesystem::init( $appData );
	\Nino\AppData::init( $appData );
	$appData['/nino/dir'] = '';
	\Nino\Locales::init( $appData );
	\Nino\Csrf::init( $appData );
	\Nino\Html::init( $appData );
	\Nino\Auth::init( $appData );

	if( $kit === true )
		$appData['/nino/modules'][] = '\\Nino\\Modules\\Kit';

	\Nino\Features::registerAssets( $appData );
	\Nino\Features::registerComponents( $appData );
	\Nino\Modules::callModules( $appData, 'init' );
	\Nino\Features::registerParts( $appData );

	$appData['./nino/locales/current'] = $locale;

	// The year of the footer is the clock's, set at the start of the request: the response round that follows sets it again
	\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', static function( array &$appData, array &$request ): void {
		\Nino\Html::addFills( $appData, [ '/nino/date/year' => '2026' ], '*' );
	} );

	// The token of the form and the nonce of the script block are drawn from chance: fixed here, at their source
	\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', str_repeat( 'c', 64 ) );
	$appData['./nino/jstext/nonce'] = str_repeat( 'a', 32 );

	return $appData;
}

/**
 *	The counters of one request: the names a menu, a set of tabs and an accordion make up for themselves
 *
 *	@param		array 		&$appData
 *
 *	@return 	void
 */
function pagesReset( array &$appData ): void {
	unset( $appData['./nino/components/count'], $appData['./nino/navigation/count'] );
}

/**
 *	One page through the whole way of a request
 *
 *	@param		string		$sandbox
 *	@param		string		$locale
 *	@param		string		$uri
 *
 *	@return 	string
 */
function pagesPage( string $sandbox, string $locale, string $uri ): string {

	$appData = pagesBoot( $sandbox, $locale );
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );

	return (string) ( $request['/nino/http/response']['body'] ?? '' );
}

/**
 *	One page of the starter site as a visitor gets it: the status code and the body of a request
 *
 *	@param		string		$sandbox
 *	@param		string		$locale
 *	@param		string		$uri
 *
 *	@return 	array										[ status, body ]
 */
function pagesVisit( string $sandbox, string $locale, string $uri ): array {

	$appData = pagesBoot( $sandbox, $locale );
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );

	return [ (int) ( $request['/nino/http/response']['statusCode'] ?? 0 ), (string) ( $request['/nino/http/response']['body'] ?? '' ) ];
}

/**
 *	Source after source drawn the way a route body is, each one as the first thing of a request
 *
 *	@param		string		$sandbox
 *	@param		array			$cases				label => html+
 *	@param		string		$locale
 *
 *	@return 	string										'### label' and the output, one after the other
 */
function pagesCases( string $sandbox, array $cases, string $locale = 'de_DE' ): string {

	// The cases are about the machine - loops, includes, fills - and use the components of the test feature as their material, so it is on
	$appData = pagesBoot( $sandbox, $locale, true );
	$out = '';

	// A call that hands on what it was given as it is - a loop has escaped the values of its element - to read what a loop puts into the attributes of a call
	\Nino\Html::addShortcode( $appData, 'pages-echo', static fn( array &$appData, array $args ): string => '<a href="'. (string) ( $args['href'] ?? '' ). '">'. (string) ( $args['text'] ?? '' ). '</a>', false );

	\Nino\Html::addFills( $appData, [ '/nino/date/year' => '2026' ], '*' );

	// The fills of the response: a template that is named by one is a case of its own
	\Nino\Html::addFills( $appData, \Nino\Html::requestFills( [ '/nino/http/response' => [ 'uri' => '/', 'locale' => $locale ], '/nino/http/request' => [ 'uri' => '/' ] ], '' ), '*' );

	foreach( $cases as $label => $source ) {

		pagesReset( $appData );
		$out .= '### '. $label. "\n". \Nino\Html::renderHtml( $appData, $source ). "\n\n";
	}

	return $out;
}

/**
 *	The cases of a loop over the elements, and of the templates that are included
 *
 *	@return 	array
 */
function pagesLoopCases(): array {

	return [
		'elements, a call in the content'	=> '[elements /probe sort="title"]<h3>[[title]]</h3><span data-n="[[.id]]" data-s="[[.slug]]" data-u="[[.uri]]"[[.lang]]>[[price]]</span><i>[[note]]</i>[prose .body][prose .title tag="h4"][/elements]',
		'elements, a field in an attribute'	=> '[elements /probe sort="title" limit="2"][pages-echo text="Read [[title]]" href="/blog/[[.slug]]"][/elements]',
		'elements, empty with a template'		=> '[elements /probe query="category=Z" empty="probe-empty"]<b>[[title]]</b>[/elements]',
		'elements, empty with no template'	=> '[elements /probe query="category=Z"]<b>[[title]]</b>[/elements]',
		'elements, empty="" a path that is none'	=> '[elements /probe query="category=Z" empty="../x"]<b>[[title]]</b>[/elements]',
		'elements, a loop in a loop'				=> '[elements /probe sort="title"]<h4>[[title]]</h4>[elements /probe query="category=[[category]]" sort="title"]<i>[[title]]</i>[/elements][/elements]',
		'elements, a template in a loop'	=> '[elements /probe sort="title"][template /templates/probe-row][/elements]',
		'element'													=> '[element /probe/eins]<b>[[title]]</b> [[.id]] [[.slug]] [[category]] [[note]][prose .title][/element]',
		'element, a name that is none'		=> '[element /probe/nobody]<b>[[title]]</b>[/element]',
		'elementvalues'										=> '[elementvalues /probe key="category"]<b>[[.id]]:[[.value]]([[.count]])</b>[prose text="-"][/elementvalues]',
		'elementvalues, a loop in a value'	=> '[elementvalues /probe key="category"]<h5>[[.value]]</h5>[elements /probe query="category=[[.value]]" sort="title"]<i>[[title]]</i>[/elements][/elementvalues]',
		'template with a fill in the path'	=> '[template /templates/page-x.[[/nino/http/response/locale]]]',
		'template, a name that is none'		=> '[template /templates/nobody]after',
		'a fill in the text and in a call'	=> '<p>[[/project/company/general/name]]</p>[prose text="[[/project/company/general/name]] / [[/_nino/webpage/contact/title]]"]',
		'a fill that is none stays'				=> '<p>[[/project/nobody/nothing/here]] [[loose]]</p>',
		'a fill with a fill in its name'		=> '<p>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</p>',
		'csrf'														=> '<form>[csrf]</form>',
		'json'														=> '<script>[json /project/company/contact/address] [json /project/company/general/name]</script>',
		'navigation, lines of a menu'			=> '[navigation nav="main" id="m" class="c"][/navigation]',
		'navigation, lines by hand'				=> '[navigation id="hand"]/about:About us:class="x"'. "\n". 'https://example.com:Elsewhere'. "\n  \n". '<span>[prose text="A line"]</span>'. "\n". '[/navigation]',
		'navigation, a burger with a counter'	=> '[navigation nav="legal" burger][/navigation][navigation nav="footer" burger][/navigation]',
		'localepicker'										=> '[localepicker]',
		'legal'														=> '[legal]',
		'privacy'													=> '[privacy]',
		'assets and jstext'								=> '[assets /.cache/style.css][jstext]',
	];
}

/**
 *	The pages of the site the way a fixture holds them: file name => output
 *
 *	@param		string		$sandbox			The site, without the tools
 *
 *	@return 	array
 */
function pagesStarter( string $sandbox ): array {

	$files = [];

	foreach( [ 'home' => '/', 'contact' => '/contact', '404' => '/404', 'blank' => '/new-webpage' ] as $page => $uri )
		foreach( [ 'de_DE', 'en_US' ] as $locale )
			$files[$page. '.'. $locale. '.html'] = pagesPage( $sandbox, $locale, $uri );

	return $files;
}

/**
 *	The loops and the other cases: file name => output
 *
 *	@param		string		$sandbox			The site, without the tools
 *
 *	@return 	array
 */
function pagesCaseFiles( string $sandbox ): array {

	return [
		'loop.de_DE.html'				=> pagesCases( $sandbox, pagesLoopCases(), 'de_DE' ),
		'loop.en_US.html'				=> pagesCases( $sandbox, pagesLoopCases(), 'en_US' ),
	];
}

/**
 *	A mail as the form draws it: the owner, both subjects, the label of a field and the template of both mails
 *	before the placeholders are put in - which the form does after the drawing, with the time of the day in them
 *
 *	@param		string		$sandbox
 *
 *	@return 	string
 */
function pagesMail( string $sandbox ): string {

	$appData = pagesBoot( $sandbox, 'de_DE' );
	$form = \Nino\Form::form( $appData );
	$out = '';

	if( $form === null )
		return "no form\n";

	$fills = \Nino\Html::requestFills( [ '/nino/http/response' => [ 'uri' => '/contact', 'locale' => 'de_DE' ], '/nino/http/request' => [ 'uri' => '/contact' ] ], '' );
	\Nino\Html::addFills( $appData, $fills, '*' );

	$out .= "owner: ". \Nino\Html::renderHtml( $appData, '[[/project/mail/address/owner]]' ). "\n";
	$out .= "subject: ". ( $form['subject'] !== '' ? \Nino\Html::renderHtml( $appData, $form['subject'] ) : \Nino\Html::renderHtml( $appData, '[[/module/form/subject/owner]]' ) ). "\n";
	$out .= "subject user: ". \Nino\Html::renderHtml( $appData, '[[/module/form/subject/user]]' ). "\n";

	foreach( $form['fields'] as $field )
		$out .= "label ". $field['name']. ": ". \Nino\Html::renderHtml( $appData, $field['label'] ). "\n";

	foreach( [ 'ownerTemplate', 'userTemplate' ] as $role ) {

		pagesReset( $appData );
		$out .= "### ". $role. "\n". \Nino\Html::renderHtml( $appData, '[template '. $form[$role]. ']' ). "\n\n";
	}

	return $out;
}

/**
 *	The maintenance page, drawn by its own template, and the page of a signed-in account with the banner
 *
 *	@param		string		$sandbox
 *
 *	@return 	string
 */
function pagesMaintenance( string $sandbox ): string {

	$out = '';

	foreach( [ 'de_DE', 'en_US' ] as $locale ) {

		$appData = pagesBoot( $sandbox, $locale );
		$appData['/nino/maintenance/status'] = true;
		$request = [ '/nino/http/request' => [ 'uri' => '/', 'method' => 'GET' ], '/nino/http/response' => [ 'statusCode' => 200, 'uri' => '/', 'locale' => $locale ] ];

		$applied = \Nino\Modules\Maintenance::_prepare( $appData, $request );
		$out .= '### '. $locale. ' '. ( $applied === true ? 'drawn' : 'not drawn' ). ' '. $request['/nino/http/response']['statusCode']. "\n". (string) ( $request['/nino/http/response']['body'] ?? '' ). "\n\n";
	}

	// The banner a signed-in account sees on a page
	$appData = pagesBoot( $sandbox, 'de_DE' );
	$appData['/nino/maintenance/status'] = true;
	ninoUser( $appData, 'dev@example.com' );
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );
	$out .= "### banner\n". (string) ( $request['/nino/http/response']['body'] ?? '' ). "\n";

	return $out;
}

/**
 *	The workbench with one panel open, signed in
 *
 *	@param		string		$sandbox			The site with the tools
 *
 *	@return 	string
 */
function pagesWorkbench( string $sandbox ): string {

	$appData = pagesBoot( $sandbox, 'de_DE' );
	$appData['/nino/install/completed'] = true;
	ninoUser( $appData, 'dev@example.com' );
	\Nino\Admin\Admin::init( $appData );

	// The token of the workbench is the one of the session, and the version of its bundles is made of file times
	\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', str_repeat( 'c', 64 ) );
	$appData['./nino/jstext/nonce'] = str_repeat( 'a', 32 );
	unset( $_GET['session'], $_GET['follow'] );

	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/_admin', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );

	return (string) ( $request['/nino/http/response']['body'] ?? '' );
}

$record = getopt( '', [ 'record:' ] )['record'] ?? null;
$dir = __DIR__. '/fixtures/markup/starter';

/**
 *	The trees kept of the templates of a site: how many files the folder holds
 *
 *	@param		string		$sandbox
 *
 *	@return 	int
 */
function pagesTrees( string $sandbox ): int {
	return count( glob( $sandbox. '/private/data/cache/trees/*.php' ) ?: [] );
}

// The pages of the site, without the tools of the workbench: drawn by a request that finds no tree kept of a
// template (cold) and by one that finds them all (warm)
$site = pagesSite( false );
$GLOBALS['pagesCold'] = true;
$outputs = pagesStarter( $site ) + pagesCaseFiles( $site ) + [ 'mail.txt' => pagesMail( $site ), 'maintenance.html' => pagesMaintenance( $site ) ];
$kept = pagesTrees( $site );
$GLOBALS['pagesCold'] = false;
$warm = pagesStarter( $site ) + pagesCaseFiles( $site ) + [ 'mail.txt' => pagesMail( $site ), 'maintenance.html' => pagesMaintenance( $site ) ];
$keptAgain = pagesTrees( $site );
\Nino\Filesystem::removeDir( $site );

// The workbench, with the tools of the kernel next to the project
$site = pagesSite( true );
$GLOBALS['pagesCold'] = true;
$outputs['workbench.html'] = pagesWorkbench( $site );
$GLOBALS['pagesCold'] = false;
$keptWorkbench = pagesTrees( $site );
$warm['workbench.html'] = pagesWorkbench( $site );
\Nino\Filesystem::removeDir( $site );

if( is_string( $record ) === true ) {

	is_dir( $record ) === true || mkdir( $record, 0755, true );

	foreach( $outputs as $file => $output )
		file_put_contents( $record. '/'. $file, pagesVersionless( $output ) );

	echo 'recorded '. count( $outputs ). " files\n";
	exit( 0 );
}

echo "The pages of the site, byte for byte\n";

$names = array_keys( $outputs );
sort( $names );

check( 'the fixtures are the files of the outputs, no more and no fewer', array_values( array_map( 'basename', glob( $dir. '/*' ) ?: [] ) ) === $names );

foreach( $names as $name ) {

	$expected = (string) @file_get_contents( $dir. '/'. $name );
	$same = pagesVersionless( $expected ) === pagesVersionless( $outputs[$name] );

	check( $name. ' ('. strlen( $outputs[$name] ). ' bytes) is as the fixture has it', $same );

	if( $same === false ) {
		// The one thing a run leaves in the temporary directory: the outputs that differ, in a directory of the run's own
		is_dir( $GLOBALS['pagesRun']. '-actual' ) === true || mkdir( $GLOBALS['pagesRun']. '-actual', 0755 );
		$actual = $GLOBALS['pagesRun']. '-actual/'. $name;
		file_put_contents( $actual, $outputs[$name] );
		echo "      differs, the output is in $actual\n";
	}
}

check( 'every request that found no tree kept a tree of the templates it drew', $kept > 0 && $keptWorkbench > 0 );
check( 'the requests that found the trees drew the same pages, byte for byte, and the same trees stand', $warm === $outputs && $keptAgain >= $kept );

foreach( $outputs as $name => $output )
	if( str_ends_with( $name, '.html' ) === true && str_starts_with( $name, 'workbench' ) === false && str_starts_with( $name, 'loop' ) === false )
		check( $name. ' has no call and no fill left over', preg_match( '/\[(?:template|title|navigation|logo|image|localepicker|jstext|assets|json|csrf)[ \]]/', $output ) !== 1 );

// --- A fresh installation, as a visitor meets it: the structure of the pages, not their markup -------------------

echo "The starter site as a visitor meets it - the structure of its pages\n";

// With the tools next to the project, so that the files of the kernel are files of the site as they are of an installation
$site = pagesSite( true );
$ignored = ninoWarnings();
$visits = [];

foreach( [ 'de_DE' => [ '/', '/contact', '/impressum', '/gibt-es-nicht', '/datenschutz' ], 'en_US' => [ '/', '/contact', '/imprint', '/not-there', '/privacy' ] ] as $locale => $uris )
	foreach( $uris as $uri )
		$visits[$locale. ' '. $uri] = pagesVisit( $site, $locale, $uri );

$noticed = ninoWarnings();

// The same pages as a preview that asked for the trace: comments where the nodes are drawn, and none where a comment would be text
$traced = [];

foreach( [ '/', '/contact' ] as $uri ) {

	$appData = pagesBoot( $site, 'de_DE' );
	ninoUser( $appData, 'trace@example.com', [ '/_admin/builder/manage' ] );
	$token = \Nino\Preview::issue( $appData, 'builder', '/_admin/builder/manage', [] );
	\Nino\Callbacks::registerCallback( $appData, '/nino/http/request', static function( array &$appData, array &$request ): void {
		\Nino\Preview::held( $appData, $request, 'builder', '/_admin/builder/manage' ) !== null && \Nino\Preview::trace( $appData );
	} );
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri. '?nino-preview='. $token, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );
	$traced[$uri] = (string) ( $request['/nino/http/response']['body'] ?? '' );
}

// A page of the components of the test feature - a band with a title and a button, a band of three cards and one more - put in place of the page of the site, with the feature on,
// drawn with the trace as a preview asks for it
file_put_contents( $site. '/private/templates/page-home.tpl', implode( "\n", [
	'[band-wrap id="hero"][band-row][band-col]',
	'	[band-title tag="h1" text="One"]',
	'	[band-subtitle text="Two"]',
	'	[lever text="Three" href="/contact"]',
	'[/band-col][/band-row][/band-wrap]',
	'[band-wrap id="cards"][band-row]',
	'	[band-col variant="card"][band-title text="Card one"][prose text="Text one"][/band-col]',
	'	[band-col variant="card"][band-title text="Card two"][prose text="Text two"][/band-col]',
	'	[band-col variant="card"][band-title text="Card three"][prose text="Text three"][/band-col]',
	'[/band-row][/band-wrap]',
	'[band-wrap id="more"][band-row][band-col][band-title text="Four"][prose text="Five"][lever text="Six" href="/"][/band-col][/band-row][/band-wrap]',
] ) );
$appData = pagesBoot( $site, 'de_DE', true );
ninoUser( $appData, 'trace@example.com', [ '/_admin/builder/manage' ] );
$token = \Nino\Preview::issue( $appData, 'builder', '/_admin/builder/manage', [] );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/request', static function( array &$appData, array &$request ): void {
	\Nino\Preview::held( $appData, $request, 'builder', '/_admin/builder/manage' ) !== null && \Nino\Preview::trace( $appData );
} );
$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/?nino-preview='. $token, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );
$tracedKit = (string) ( $request['/nino/http/response']['body'] ?? '' );

$ignored = ninoWarnings();
\Nino\Filesystem::removeDir( $site );
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );

$misplaced = 0;
$invalid = 0;

// the pages of the html site carry no trace; the page of the test feature carries it everywhere a component is drawn
foreach( $traced + [ 'kit' => $tracedKit ] as $body ) {

	// The comments the templates carry of their own are text of the template, which names a textarea in one
	$body = (string) preg_replace( '/<!--(?!\/?nino:).*?-->/s', '', $body );
	$misplaced += preg_match_all( '#<(script|style|title|textarea)\b[^>]*>(?:(?!</\1>).)*?<!--/?nino:#is', $body ) + preg_match_all( '#<[a-z][^<>]*<!--/?nino:#i', $body );

	if( preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $body, $found ) > 0 )
		foreach( $found[1] as $json )
			$invalid += json_decode( $json, true ) === null ? 1 : 0;
}

check( 'the titles and the buttons of a page of components, drawn by their renderers, carry the mark of their source as the content of their element',
	preg_match_all( '#<h[1-3] class="band-title[^>]*><!--nino:s [^>]*-->#', $tracedKit ) >= 3 && preg_match_all( '#<a class="lever[^>]*><!--nino:s [^>]*-->#', $tracedKit ) >= 1 && substr_count( $tracedKit, '<!--nino:n' ) > 20 );
check( 'the pages of a preview that asked for the trace carry it where a component is drawn - the html starter site has none, and its pages hold no comment of the trace - and no comment stands in a script, a style, a title, a textarea or a tag, and the JSON-LD is JSON',
	$misplaced === 0 && $invalid === 0 && substr_count( implode( '', $traced ), '<!--nino:n' ) === 0 && substr_count( $traced['/'], 'application/ld+json' ) >= 1 );

check( 'the start page, the contact page and the imprint answer 200 and an address that is not there 404, in both languages',
	array_column( $visits, 0 ) === [ 200, 200, 200, 404, 200, 200, 200, 200, 404, 200 ] );

$structure = [];
$leftOver = [];
$classes = [];
$emptyNames = [];

foreach( $visits as $label => [ , $body ] ) {

	$structure[$label] = [ substr_count( $body, '<header' ), substr_count( $body, '<footer' ), substr_count( $body, '<section' ), substr_count( $body, '<h1' ) ];

	// What the page says in its comments is the template's own explanation
	if( preg_match( '/\[\[|\[(?:template|section-[a-z]+|header-[a-z]+|footer-[a-z]+|button|text|image|spacer|html|csrf|navigation|legal|localepicker)[ \]]/', (string) preg_replace( '/<!--.*?-->/s', '', $body ) ) === 1 )
		$leftOver[] = $label;

	preg_match_all( '/class="([^"]*)"/', $body, $found );

	foreach( explode( ' ', implode( ' ', $found[1] ) ) as $class )
		if( str_starts_with( $class, 'nino-' ) === true && preg_match( '/^nino-(?:sr-only|richtext|legal-section|form(?:-[a-z]+)?)$/', $class ) !== 1 )
			$classes[$class] = true;

	if( str_contains( $body, 'aria-label=""' ) === true )
		$emptyNames[] = $label;
}

check( 'the start page and the contact page have one header, one footer and at least two sections, and every page has one h1',
	$structure['de_DE /'][0] === 1 && $structure['de_DE /'][1] === 1 && $structure['de_DE /'][2] >= 2 && $structure['de_DE /contact'][0] === 1 && $structure['de_DE /contact'][1] === 1 && $structure['de_DE /contact'][2] >= 2
	&& $structure['en_US /'][2] >= 2 && $structure['en_US /contact'][2] >= 2 && array_filter( $structure, static fn( array $counts ): bool => $counts[3] !== 1 && $counts[0] !== 0 ) === [] );
check( '...the hero of the start page follows the header, in the main part, with its picture, its h1 and no link of a page is a telephone address the visitor\'s own spelling would break: no tel: link',
	preg_match( '/<header class="site-header">.*?<\/header>\s*<main id="nino-main" tabindex="-1">\s*<section class="hero hero--tall">\s*<img [^>]*>\s*<div class="wrap">\s*<h1>/s', $visits['de_DE /'][1] ) === 1 && preg_match( '/href="tel:/', $visits['de_DE /contact'][1] . $visits['de_DE /'][1] ) !== 1 );
// The fresh site, page by page: its language, its one main part, and the one entry of the menu that is the page shown - a link with the mark in the header and in the footer
$menuMarks = [];
$mains = [];
$languages = [];

foreach( $visits as $label => [ , $body ] ) {

	preg_match( '#<nav class="site-nav".*?</nav>#s', $body, $headerNav );
	preg_match( '#<footer class="site-footer">.*?<nav .*?</nav>#s', $body, $footerNav );
	preg_match_all( '#<a href="([^"]*)" aria-current="page"#', ( $headerNav[0] ?? '' ). ( $footerNav[0] ?? '' ), $marked );
	$menuMarks[$label] = $marked[1];
	$mains[$label] = substr_count( $body, '<main' );
	$languages[$label] = preg_match( '#<html lang="([a-z]+)">#', $body, $lang ) === 1 ? $lang[1] : '';
}

check( 'every page of the fresh site has one main part and says its language: de on the pages of the German visit, en on those of the English one', array_values( array_unique( $mains ) ) === [ 1 ]
	&& array_values( array_unique( array_map( static fn( string $label ): string => $languages[$label], array_filter( array_keys( $visits ), static fn( string $label ): bool => str_starts_with( $label, 'de_DE' ) ) ) ) ) === [ 'de' ]
	&& array_values( array_unique( array_map( static fn( string $label ): string => $languages[$label], array_filter( array_keys( $visits ), static fn( string $label ): bool => str_starts_with( $label, 'en_US' ) ) ) ) ) === [ 'en' ] );
check( '...the menu marks the start page on the start page and the contact page on the contact page, in the header and in the footer, and no entry on the pages that are in no menu',
	$menuMarks['de_DE /'] === [ '/', '/' ] && $menuMarks['de_DE /contact'] === [ '/contact', '/contact' ] && $menuMarks['en_US /contact'] === [ '/contact', '/contact' ]
	&& $menuMarks['de_DE /impressum'] === [] && $menuMarks['de_DE /datenschutz'] === [] && $menuMarks['de_DE /gibt-es-nicht'] === [] && $menuMarks['en_US /not-there'] === [] );
check( '...the imprint holds the sections of the imprint and the privacy policy those of the privacy policy, in both languages, under one h1 and h2s',
	array_filter( [ 'de_DE /impressum' => 'legal', 'en_US /imprint' => 'legal', 'de_DE /datenschutz' => 'privacy', 'en_US /privacy' => 'privacy' ], static function( string $type, string $label ) use ( $visits ): bool {
		$body = $visits[$label][1];
		$other = $type === 'legal' ? 'privacy' : 'legal';
		return substr_count( $body, 'id="'. $type. '-' ) < 2 || str_contains( $body, 'id="'. $other. '-' ) === true || substr_count( $body, '<h1' ) !== 1 || str_contains( $body, '<h3' ) === true || str_contains( $body, '<h2>' ) === false;
	}, ARRAY_FILTER_USE_BOTH ) === [] );
check( '...no page has a call or a fill left over, and no page has a menu or a button with no name'. ( $leftOver === [] ? '' : ' - '. implode( ', ', $leftOver ) ), $leftOver === [] && $emptyNames === [] );
check( '...the classes of the old stylesheet are in none of the pages: no class that starts with nino- but the ones the reset, the form and the legal texts name'. ( $classes === [] ? '' : ' - '. implode( ', ', array_keys( $classes ) ) ), $classes === [] );
check( '...and the log has no notice and no warning from drawing any of them'. ( $noticed === [] ? '' : ' - '. substr( implode( ' | ', array_unique( $noticed ) ), 0, 300 ) ), $noticed === [] );

$ignored = ninoWarnings();

$empty = [];
ninoDone( $empty );
