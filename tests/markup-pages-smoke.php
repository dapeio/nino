<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	markup-pages-smoke.php	Test of what a page is drawn as: the four starter pages with their frame in
 *											both languages, the workbench shell with a panel open, a loop with
 *											components in it, the components and stacks one by one, the content of
 *											a mail and the maintenance page - each compared byte for byte with the
 *											file under tests/fixtures/markup/starter/. The setup wizard is run for real
 *											(Setup::apiApply) in a sandbox whose path and file times are fixed, so
 *											that the four sizes a page draws from chance - the token of the form, the
 *											nonce of the script block, the version of a bundle and the stamp of the
 *											favicons - are set at their source and nothing is replaced in an output.
 *											The year of the footer is fixed through a callback of the response, as the rest.
 *											What a template or a value can do to the page is checked by
 *											tests/markup-safety-smoke.php.
 *
 *	Usage: php tests/markup-pages-smoke.php
 *				 php tests/markup-pages-smoke.php --record=DIR	writes the outputs as fixtures into DIR
 */

defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-markup-pages-features-'. getmypid() );
is_dir( NINO_FEATURES_DIR ) === true || mkdir( NINO_FEATURES_DIR, 0755, true );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

/**
 *	The directory the site of the pages is built in: the path goes into the version of a bundle, so it is one
 *
 *	@return 	string
 */
function pagesSiteDir(): string {
	return sys_get_temp_dir(). '/nino-markup-pages-site';
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
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf', '\\Nino\\Modules\\Components' ];

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
		'probe-empty'				=> '<p class="empty">[[/project/company/general/name]] has [title text="nothing"]</p>',
		'probe-row'					=> '<li>[[/project/company/general/name]]</li>',
		'page-x.de_DE'			=> '<main>[title text="Deutsch"]</main>',
		'page-x.en_US'			=> '<main>[title text="English"]</main>',
	] as $name => $template )
		\Nino\Filesystem::putFileContent( $appData, '/templates/'. $name. '.tpl', $template );

	// The tools of the workbench live in their own folders; the sandbox finds them below its own root
	if( $tools === true )
		foreach( [ '_admin', '_nino' ] as $dir )
			exec( 'cp -a '. escapeshellarg( dirname( __DIR__ ). '/'. $dir ). ' '. escapeshellarg( $sandbox. '/'. $dir ) );

	// One time for every file: the version of a bundle and the stamp of the favicons are made of file times
	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox, \FilesystemIterator::SKIP_DOTS ) ) as $file )
		touch( $file->getPathname(), 1767225600 );

	// The path goes into the version of a bundle
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
 *
 *	@return 	array
 */
function pagesBoot( string $sandbox, string $locale ): array {

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
 *	Source after source drawn the way a route body is, each one as the first thing of a request
 *
 *	@param		string		$sandbox
 *	@param		array			$cases				label => html+
 *	@param		string		$locale
 *
 *	@return 	string										'### label' and the output, one after the other
 */
function pagesCases( string $sandbox, array $cases, string $locale = 'de_DE' ): string {

	$appData = pagesBoot( $sandbox, $locale );
	$out = '';

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
 *	Every case of the component pages: what a card, a stack or a loop is drawn as
 *
 *	@return 	array
 */
function pagesComponentCases(): array {

	$slot = '/template/page-home/hero/background';

	return [
		'title'									=> '[title /template/probe/x/plain tag="h2"]',
		'title, a raw value in the file'	=> '[title /template/probe/x/roh]',
		'title, a fill in text='		=> '[title text="[[/project/company/general/name]]"]',
		'subtitle'							=> '[subtitle /template/probe/x/plain]',
		'text, blocks'					=> '[text /template/probe/x/blocks]',
		'line, a fill in text='	=> '[line text="[[/project/company/general/name]] - fixed"]',
		'card-title with a link'	=> '[card-title /template/probe/x/plain href="/x"]',
		'card-subtitle'					=> '[card-subtitle /template/probe/x/plain]',
		'card-text'							=> '[card-text /template/probe/x/blocks]',
		'card-image'						=> '[card-image '. $slot. ' alt="x"]',
		'card, sorted children'	=> '[card padding="2"][card-title /template/probe/x/plain][card-image '. $slot. '][card-text /template/probe/x/blocks][/card]',
		'card, no end'					=> '[card padding="2"]',
		'button'								=> '[button /template/probe/x/plain href="/_nino/webpage/contact/uri" style="primary"]',
		'image'									=> '[image '. $slot. ' alt=""]',
		'image, with content'		=> '[image '. $slot. ']<meta property="og:image" content="[[url]]" data-w="[[width]]">[/image]',
		'image, a call in its content'	=> '[image '. $slot. ']<i data-w="[[width]]">[spacer size="2"][[/project/company/general/name]]</i>[/image]',
		'logo'									=> '[logo alt=""]',
		'logo with a slot'			=> '[logo light="'. $slot. '" dark="" alt=""]',
		'html with a call and a fill'	=> '[html class="nino-mt-2"]<p>Fixed [spacer size="1"] and [[/template/probe/x/plain]]</p>[/html]',
		'spacer'								=> '[spacer size="3"]',
		'scroll-hint'						=> '[scroll-hint]',
		'col outside a table'		=> '[col /template/probe/x/plain field="title"]',
		'panel outside'					=> '[panel title="A"]x[/panel]',
		'stack'									=> '[stack /probe sort="title" cols="50"][card][card-title title][card-text body][button .uri text="More of [[title]]"][/card][/stack]',
		'stack, no end'					=> '[stack /probe]',
		'repeat'								=> '[repeat /probe sort="title"]<p data-n="[[.id]]">[[title]] ([[.slug]])</p>[/repeat]',
		'slider'								=> '[slider /probe sort="title"][title title][/slider]',
		'filter'								=> '[filter /probe by="category" sort="title"][title title][/filter]',
		'tabs, a loop'					=> '[tabs /probe sort="title" title="title"][text body][/tabs]',
		'tabs, fixed with panels'	=> '[tabs id="t"][panel title="One"][title /template/probe/x/plain][/panel][panel title="Two"]<p>[[/project/company/general/name]]</p>[/panel][/tabs]',
		'tabs, fixed with no id'	=> '[tabs][panel title="One"][spacer size="1"][/panel][panel title="Two"][spacer size="2"][/panel][/tabs]',
		'accordion, fixed'			=> '[accordion][panel title="A"][spacer size="1"][/panel][/accordion]',
		'accordion, a loop'			=> '[accordion /probe sort="title" text="&#91;&#91;title&#93;&#93; costs &#91;&#91;price&#93;&#93;"][text body][/accordion]',
		'list'									=> '[list /probe sort="title" style="check"][line text="[[title]]: [[price]] EUR"][/list]',
		'table'									=> '[table /probe sort="title"][col /template/probe/x/plain field="title"][col field="price" align="right"][col][button .uri text="To [[title]]"][/col][/table]',
		'sections'							=> '[sections /probe sort="title" alternate="1"]<div class="nino-grid-row">[title title]</div>[/sections]',
		'stack in a loop'				=> '[repeat /probe sort="title"]<h4>[[title]]</h4>[elements /probe query="category=[[category]]"]<i>[[title]]</i>[/elements][/repeat]',
	];
}

/**
 *	The cases of a loop over the elements, and of the templates that are included
 *
 *	@return 	array
 */
function pagesLoopCases(): array {

	return [
		'elements, a call in the content'	=> '[elements /probe sort="title"]<h3>[[title]]</h3><span data-n="[[.id]]" data-s="[[.slug]]" data-u="[[.uri]]"[[.lang]]>[[price]]</span><i>[[note]]</i>[text body][title text="[[title]]" tag="h4"][/elements]',
		'elements, a field in an attribute'	=> '[elements /probe sort="title" limit="2"][button .uri text="Read [[title]]" href="/blog/[[.slug]]"][/elements]',
		'elements, empty with a template'		=> '[elements /probe query="category=Z" empty="probe-empty"]<b>[[title]]</b>[/elements]',
		'elements, empty with no template'	=> '[elements /probe query="category=Z"]<b>[[title]]</b>[/elements]',
		'elements, empty="" a path that is none'	=> '[elements /probe query="category=Z" empty="../x"]<b>[[title]]</b>[/elements]',
		'elements, a loop in a stack'			=> '[repeat /probe sort="title"]<h4>[[title]]</h4>[elements /probe query="category=[[category]]" sort="title"]<i>[[title]]</i>[/elements][/repeat]',
		'elements, a template in a loop'	=> '[elements /probe sort="title"][template /templates/probe-row][/elements]',
		'element'													=> '[element /probe/eins]<b>[[title]]</b> [[.id]] [[.slug]] [[category]] [[note]][title text="[[title]]"][/element]',
		'element, a name that is none'		=> '[element /probe/nobody]<b>[[title]]</b>[/element]',
		'elementvalues'										=> '[elementvalues /probe key="category"]<b>[[.id]]:[[.value]]([[.count]])</b>[spacer size="1"][/elementvalues]',
		'elementvalues, a loop in a value'	=> '[elementvalues /probe key="category"]<h5>[[.value]]</h5>[elements /probe query="category=[[.value]]" sort="title"]<i>[[title]]</i>[/elements][/elementvalues]',
		'template with a fill in the path'	=> '[template /templates/page-x.[[/nino/http/response/locale]]]',
		'template, a name that is none'		=> '[template /templates/nobody]after',
		'a fill in the text and in a call'	=> '<p>[[/project/company/general/name]]</p>[title text="[[/project/company/general/name]] / [[/_nino/webpage/contact/title]]"]',
		'a fill that is none stays'				=> '<p>[[/project/nobody/nothing/here]] [[loose]]</p>',
		'a fill with a fill in its name'		=> '<p>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</p>',
		'csrf'														=> '<form>[csrf]</form>',
		'json'														=> '<script>[json /project/company/contact/address] [json /project/company/general/name]</script>',
		'navigation, lines of a menu'			=> '[navigation nav="main" id="m" class="c"][/navigation]',
		'navigation, lines by hand'				=> '[navigation id="hand"]/about:About us:class="x"'. "\n". 'https://example.com:Elsewhere'. "\n  \n". '<span>[title text="A line"]</span>'. "\n". '[/navigation]',
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
 *	The loop, the components and the other cases: file name => output
 *
 *	@param		string		$sandbox			The site, without the tools
 *
 *	@return 	array
 */
function pagesCaseFiles( string $sandbox ): array {

	return [
		'components.de_DE.html'	=> pagesCases( $sandbox, pagesComponentCases(), 'de_DE' ),
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
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );

if( is_string( $record ) === true ) {

	is_dir( $record ) === true || mkdir( $record, 0755, true );

	foreach( $outputs as $file => $output )
		file_put_contents( $record. '/'. $file, $output );

	echo 'recorded '. count( $outputs ). " files\n";
	exit( 0 );
}

echo "The pages of the site, byte for byte\n";

$names = array_keys( $outputs );
sort( $names );

check( 'the fixtures are the files of the outputs, no more and no fewer', array_values( array_map( 'basename', glob( $dir. '/*' ) ?: [] ) ) === $names );

foreach( $names as $name ) {

	$expected = (string) @file_get_contents( $dir. '/'. $name );
	$same = $expected === $outputs[$name];

	check( $name. ' ('. strlen( $outputs[$name] ). ' bytes) is as the fixture has it', $same );

	if( $same === false ) {
		$actual = sys_get_temp_dir(). '/nino-markup-pages-actual-'. $name;
		file_put_contents( $actual, $outputs[$name] );
		echo "      differs, the output is in $actual\n";
	}
}

check( 'every request that found no tree kept a tree of the templates it drew', $kept > 0 && $keptWorkbench > 0 );
check( 'the requests that found the trees drew the same pages, byte for byte, and the same trees stand', $warm === $outputs && $keptAgain >= $kept );

foreach( $outputs as $name => $output )
	if( str_ends_with( $name, '.html' ) === true && str_starts_with( $name, 'workbench' ) === false && str_starts_with( $name, 'components' ) === false && str_starts_with( $name, 'loop' ) === false )
		check( $name. ' has no call and no fill left over', preg_match( '/\[(?:template|title|navigation|logo|image|localepicker|jstext|assets|json|csrf)[ \]]/', $output ) !== 1 );

$ignored = ninoWarnings();

$empty = [];
ninoDone( $empty );
