<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	basis-safety-smoke.php	Test of what a value can do to the pages of the starter site, which are written in the components of Basis: the name of the
 *											company, the name of a page in the menu, the words of the form and the title of the hero - each one holding a call
 *											of a template, a csrf field, a fill of the site, markup that closes a tag, a bracket, a null byte and a megabyte
 *											of text - is drawn as what it is and never read as a call. What the register does with a value in general is
 *											tests/markup-safety-smoke.php.
 *											Runs against an isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/basis-safety-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'basis-safety' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$payloads = [
	'a call of a template'	=> '[template /templates/x]',
	'the csrf field'				=> '[csrf]',
	'a fill of the site'		=> '[[/project/company/general/name]]',
	'a tag that closes'			=> '"><script>alert(1)</script>',
	'a closing bracket'			=> ']',
	'a null byte'						=> "a\0b",
	'a megabyte'						=> '[template /templates/x] [csrf] '. str_repeat( 'abcdefgh', 1 << 17 ),
];

echo "The starter pages: what they draw of a value is the text it is\n";

/*	The frames and the pages the wizard delivers, written in the components of Basis, over a site whose company name, whose name of a page in the menu, whose words of a
	form and whose texts are each of the payloads below. A component draws a value escaped and reads none of it; a fill in a template is the project's own words, which stand
	in the page as they are and are read by nobody. So what a call or a csrf field would leave behind is never there - the one csrf field of the page is the form's own	*/
$starter = ninoSandbox( 'markup-safety-starter' );
$starterDir = ninoSandboxDir( $starter );
mkdir( $starterDir. '/private/templates', 0777, true );
mkdir( $starterDir. '/private/text', 0777, true );
mkdir( $starterDir. '/public/images', 0777, true );

$starter['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
$starter['/nino/html/navs'] = [ 'main', 'footer' ];
$starter['/nino/http/routes'] = [
	'GET://'				=> [ 'uri' => '/home', 'navs' => [ 'main' => 1, 'footer' => 1 ] ],
	'GET://contact'	=> [ 'uri' => '/contact', 'navs' => [ 'main' => 2, 'footer' => 2 ] ],
];

\Nino\Csrf::init( $starter );
\Nino\Html::init( $starter );
\Nino\Modules\Csrf::init( $starter );
\Nino\Modules\Template::init( $starter );
\Nino\Features::registerComponents( $starter );
\Nino\Runtime::setSessionValue( $starter, './nino/csrf/token', str_repeat( 'c', 64 ) );
\Nino\Filesystem::putFileContent( $starter, '/templates/x.tpl', 'TEMPLATE-X-RAN' );

// The scaffold is the kernel's, the frames and the pages are the starter site written in the components of Basis
foreach( [ 'base/templates/html-header', 'base/templates/html-footer' ] as $starterFile )
	copy( __DIR__. '/../_admin/install/library/'. $starterFile. '.tpl', $starterDir. '/private/templates/'. basename( $starterFile ). '.tpl' );

foreach( [ 'base/templates/frame-header', 'base/templates/frame-footer', 'pages/home/templates/page-home', 'pages/contact/templates/page-contact', 'pages/404/templates/page-404' ] as $starterFile )
	copy( __DIR__. '/../tests/fixtures/basis/starter/'. $starterFile. '.tpl', $starterDir. '/private/templates/'. basename( $starterFile ). '.tpl' );

$starterPages = [ 'page-home' => 0, 'page-contact' => 1, 'page-404' => 0 ];

foreach( $payloads as $what => $payload ) {

	// The company, the name of a page in the menu, a word of the form and the title of the hero: each is the payload in its turn
	$words = [
		'/project/company/general/name'	=> $payload,
		'/project/company/general/description' => $payload,
		'/project/company/contact/address' => $payload,
		'/project/company/contact/email' => 'a@example.com',
		'/project/company/contact/phone' => '+49 1',
		'/project/website/html/charset' => 'UTF-8',
		'/_nino/webpage/home/name'		=> $payload,
		'/_nino/webpage/contact/name'	=> $payload,
		'/_nino/webpage/home/title'		=> $payload,
		'/template/common/form/name'	=> $payload,
		'/template/common/form/submit'	=> $payload,
		'/template/page-home/hero/title'	=> $payload,
		'/template/page-home/start-1/text'	=> $payload,
		'/template/frame-header/header/button'	=> $payload,
	];
	\Nino\Html::addFills( $starter, $words, '*' );

	$seen = [];

	foreach( $starterPages as $page => $ownFields ) {

		$html = \Nino\Html::renderHtml( $starter, '[template /templates/'. $page. ']' );
		$seen[$page] = [ str_contains( $html, 'TEMPLATE-X-RAN' ), substr_count( $html, 'name="_csrf"' ) === $ownFields ];
	}

	$home = \Nino\Html::renderHtml( $starter, '[template /templates/page-home]' );

	check( 'the pages of the starter site draw no call of a template and no csrf field of a value that holds '. $what. ': the field of the contact form is the only one',
		array_filter( $seen, static fn( array $page ): bool => $page[0] === true || $page[1] === false ) === [] );

	// What a component draws is escaped text - the title of the hero, the title of an entry of the menu, the words of the button; a fill in a template is the project's own words
	if( $what === 'a tag that closes' ) {
		check( '...and what a component draws of it - the title of the hero, an entry of the menu - is text: a title is cleaned and holds no tag, an entry of the menu is escaped, and the tag of the payload is not in the page as a tag',
			preg_match( '#<h1[^>]*>[^<]*</h1>#', $home ) === 1 && str_contains( $home, '&lt;script&gt;alert(1)&lt;/script&gt;</a></li>' ) === true && preg_match( '#<li><a [^>]*><script#', $home ) !== 1 );
	}
}

\Nino\Filesystem::removeDir( $starterDir );

ninoDone( $appData );
