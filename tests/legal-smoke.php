<?php
declare(strict_types=1);

/**
 *	Nino
 *	legal-smoke.php		Test of \Nino\Modules\Legal: the imprint and the privacy
 *										policy as the element types 'legal' and 'privacy', applied
 *										by the setup wizard's unit, drawn by [legal] and [privacy]
 *										with placeholders that can be nothing but text, routed per
 *										language with 'maintenance' => false, linked from the menu
 *										'legal', completed for a language added later, extended by
 *										the sections of a feature's unit - add-only, with the
 *										tombstone of a section deleted for good - and checked for
 *										the dashboard.
 *
 *										The wizard is run for real (Setup::apiApply) in a sandbox;
 *										the feature is the one of tests/fixtures/features.
 *
 *	Usage: php tests/legal-smoke.php
 */

define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

$appData = ninoSandbox( 'legal' );
$sandbox = ninoSandboxDir( $appData );

$appData['/nino/dir'] = '';
$appData['/nino/locales/textfiles'] = '/text';
foreach( [ 'private/templates', 'private/text', 'private/assets', 'public/images' ] as $directory )
	mkdir( $sandbox. '/'. $directory, 0777, true );

\Nino\Filesystem::putFileContent( $appData, '/config.php', [
	'/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [],
	'/nino/html/assets' => [], '/nino/http/routes' => [],
] );

// The setup wizard, as it runs on a fresh project: nothing is picked, and the
// Legal unit is applied all the same
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
$setupRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
ob_start();
\Nino\Install\Setup::apiApply( $appData, $setupRequest );
ob_end_clean();
ninoWarnings();

$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$appData['/nino/modules'] = $config['/nino/modules'];

// What a project fills in, which the placeholders name
\Nino\Filesystem::mutate( $appData, '/text/global.php', static function( array $texts ): array {
	return $texts;
}, [] );
foreach( [ 'de_DE', 'en_US' ] as $locale )
	\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', static function( array $texts ): array {
		$texts['[[/project/company/general/name]]']		= 'Müller & Söhne <GmbH>';
		$texts['[[/project/company/contact/address]]']	= "Hauptstraße 1\n12345 Musterstadt";
		$texts['[[/project/company/contact/phone]]']		= '+49 123 456';
		$texts['[[/project/company/contact/email]]']		= 'info@example.org';
		$texts['[[/project/website/general/author]]']		= 'Max <b>Muster</b>';
		$texts['[[/project/website/general/url]]']			= 'example.org';
		return $texts;
	}, [] );

\Nino\Html::init( $appData );
\Nino\Modules\Legal::init( $appData );
\Nino\Locales::useLocale( $appData, 'de_DE' );
ninoWarnings();

$legalClass = '\\Nino\\Modules\\Legal';

/** @return array The type file as it is on disk */
function legalType( array &$appData, string $type ): array {
	return \Nino\Filesystem::getFileContent( $appData, '/elements/'. $type. '.php', [] );
}


// --- The unit the wizard applied ----------------------------------------------

echo "The unit\n";

$manifest = include __DIR__. '/../_nino/Nino/Modules/Legal/install/manifest.php';
$legal		= legalType( $appData, 'legal' );
$privacy	= legalType( $appData, 'privacy' );

check( 'the class is active though nothing picked it', in_array( $legalClass, $appData['/nino/modules'], true ) === true );
check( 'both types exist with the model of title, text, order and hidden', array_keys( $legal['model'] ?? [] ) === [ 'title', 'text', 'order', 'hidden' ] && array_keys( $privacy['model'] ?? [] ) === [ 'title', 'text', 'order', 'hidden' ] );
check( 'the text has the blocks profile of the sanitizer, so a section is paragraphs and lists', ( $legal['model']['text']['blocks'] ?? null ) === true && ( $privacy['model']['text']['blocks'] ?? null ) === true );
check( 'the imprint has sections in both languages, the privacy policy a dozen', count( $legal['de_DE'] ?? [] ) >= 2 && count( $legal['en_US'] ?? [] ) === count( $legal['de_DE'] ?? [] )
	&& count( $privacy['de_DE'] ?? [] ) >= 12 && count( $privacy['en_US'] ?? [] ) === count( $privacy['de_DE'] ?? [] ) );
check( 'every section has a position, and none is hidden', array_filter( array_merge( $legal['*'] ?? [], $privacy['*'] ?? [] ), static fn( array $section ): bool => ( $section['hidden'] ?? true ) !== false && isset( $section['order'] ) === false ) === [] );
check( 'the type\'s defaults stand in its \'*\' element, and no section repeats one: none stores the hidden flag', ( $privacy['*']['*'] ?? null ) === [ 'hidden' => false ] && array_filter( array_diff_key( $privacy['*'], [ '*' => 1 ] ), static fn( array $section ): bool => array_key_exists( 'hidden', $section ) ) === [] );

$positions = array_filter( array_map( static fn( array $section ): ?int => $section['order'] ?? null, array_diff_key( $privacy['*'], [ '*' => 1 ] ) ), 'is_int' );
check( 'the positions of the privacy policy are distinct, and a hundred apart', count( array_unique( $positions ) ) === count( $positions ) && count( array_filter( $positions, static fn( int $order ): bool => $order % 10 !== 0 ) ) === 0 );
check( 'the unit names its pages by Element-URI and its menu with both of them', $manifest['navs'] === [ 'legal' => [ '/legal/imprint', '/legal/privacy' ] ]
	&& array_column( \Nino\Modules\Legal::PAGES, 'uri' ) === [ '/legal/imprint', '/legal/privacy' ] );
check( 'the menu legal exists, with the pages by Element-URI', ( $config['/nino/html/navroutes']['/legal/imprint']['legal'] ?? null ) === 1 && ( $config['/nino/html/navroutes']['/legal/privacy']['legal'] ?? null ) === 2
	&& in_array( 'legal', $config['/nino/html/navs'], true ) === true );

// A unit that asks for a menu that is no usable key, or puts something into it that is no Element-URI,
// is told so when the wizard applies it - once - and the rest of what it asks for is still applied
mkdir( $sandbox. '/navunit' );
file_put_contents( $sandbox. '/navunit/manifest.php', '<?php return [ \'navs\' => [ \'Bad Key\' => [ \'/a/b\' ], \'fine\' => [ \'/a/b\', \'no-slash\', 42 ] ] ];' );
$navApp = [ '/nino/html/navs' => [ 'main' ], '/nino/html/navroutes' => [] ];
$applyNavs = new ReflectionMethod( \Nino\Install\Setup::class, '_applyNavs' );
ninoWarnings();
$navKeys = $applyNavs->invokeArgs( null, [ &$navApp, [ 'navunit' ], [ 'navunit' => $sandbox. '/navunit' ] ] );
$navWarnings = ninoWarnings();
check( 'a menu with a key that is no slug and an entry that is no Element-URI are each told, and the good ones are applied', count( $navWarnings ) === 3
	&& str_contains( $navWarnings[0], '"Bad Key"' ) === true && str_contains( $navWarnings[1], '"fine"' ) === true && str_contains( $navWarnings[2], '"fine"' ) === true
	&& $navKeys === [ '/nino/html/navs', '/nino/html/navroutes' ] && $navApp['/nino/html/navs'] === [ 'main', 'fine' ] && $navApp['/nino/html/navroutes'] === [ '/a/b' => [ 'fine' => 1 ] ] );
ninoWarnings();

// The starting texts are written for this module, in the form the sanitizer gives
$offenders = [];
$entityOrBracket = [];
$english = [ 'de_DE' => [], 'en_US' => [] ];
foreach( [ 'legal' => $legal, 'privacy' => $privacy ] as $typeName => $type )
	foreach( [ 'de_DE', 'en_US' ] as $locale )
		foreach( $type[$locale] as $id => $section ) {

			foreach( [ 'title', 'text' ] as $field ) {

				$value = (string) ( $section[$field] ?? '' );

				if( $value === '' )
					$offenders[] = $typeName. '/'. $id. ' '. $locale. ' '. $field;

				if( str_contains( $value, '&' ) === true || str_contains( $value, '[' ) === true || str_contains( $value, ']' ) === true )
					$entityOrBracket[] = $typeName. '/'. $id. ' '. $locale. ' '. $field;

				if( $locale === 'en_US' && preg_match( '/\b(und|der|die|das|nicht|Deine|Dein|Dich)\b/u', (string) preg_replace( '/<[^>]*>/', ' ', $value ) ) === 1 )
					$english['en_US'][] = $typeName. '/'. $id. ' '. $field;
			}
		}
check( 'every section has a title and a text in both languages'. ( $offenders === [] ? '' : ' - '. implode( ', ', $offenders ) ), $offenders === [] );
check( 'no text carries an ampersand, an entity or a bracket: what a placeholder names is put in when a page is drawn'. ( $entityOrBracket === [] ? '' : ' - '. implode( ', ', $entityOrBracket ) ), $entityOrBracket === [] );
check( 'the English texts are English'. ( $english['en_US'] === [] ? '' : ' - '. implode( ', ', $english['en_US'] ) ), $english['en_US'] === [] );

// Every anchor a section links to is a section of the same type
$brokenAnchors = [];
foreach( [ 'legal' => $legal, 'privacy' => $privacy ] as $typeName => $type )
	foreach( [ 'de_DE', 'en_US' ] as $locale )
		foreach( $type[$locale] as $id => $section )
			if( preg_match_all( '/href="#([a-z-]+)"/', (string) $section['text'], $found ) > 0 )
				foreach( $found[1] as $anchor )
					if( isset( $type[$locale][ substr( $anchor, strlen( $typeName ) + 1 ) ] ) === false || str_starts_with( $anchor, $typeName. '-' ) === false )
						$brokenAnchors[] = $typeName. '/'. $id. ' '. $locale. ' -> #'. $anchor;
check( 'every link inside a text goes to a section that exists'. ( $brokenAnchors === [] ? '' : ' - '. implode( ', ', $brokenAnchors ) ), $brokenAnchors === [] );

// The placeholders of the texts are all of the form the module replaces
$names = [];
foreach( [ $legal, $privacy ] as $type )
	foreach( [ 'de_DE', 'en_US' ] as $locale )
		foreach( $type[$locale] as $section )
			if( preg_match_all( '/#(\/[^#\s<>]+)#/', (string) $section['text'], $found ) > 0 )
				$names = array_merge( $names, $found[1] );
$names = array_unique( $names );
check( 'every placeholder in the unit is a key below the prefixes the module replaces, in the form of the key grammar', count( $names ) > 4
	&& array_filter( $names, static fn( string $key ): bool => \Nino\Text::isGrammarKey( $key ) === false || array_filter( \Nino\Modules\Legal::PREFIXES, static fn( string $prefix ): bool => str_starts_with( $key, $prefix ) ) === [] ) === [] );

$unitText = include __DIR__. '/../_nino/Nino/Modules/Legal/install/text/en_US.php';
check( 'the unit delivers the page details of both pages and the words of the Elements panel, and blacklists the latter', isset( $unitText['[[/_nino/webpage/legal/imprint/name]]'], $unitText['[[/_nino/webpage/legal/privacy/title]]'], $unitText['[[/_admin/elements/type/legal/hint]]'] )
	&& count( array_filter( $manifest['blacklist'], static fn( string $key ): bool => str_starts_with( $key, '/_admin/elements/' ) ) ) === count( $manifest['blacklist'] ) && count( $manifest['blacklist'] ) === 10 );
check( 'the notice to operators is a sentence of the unit - no legal advice - in both languages', str_contains( $unitText['[[/_admin/elements/type/privacy/hint]]'], 'not legal advice' )
	&& str_contains( ( include __DIR__. '/../_nino/Nino/Modules/Legal/install/text/de_DE.php' )['[[/_admin/elements/type/privacy/hint]]'], 'keine Rechtsberatung' ) );

echo "\n";


// --- [legal] and [privacy] -----------------------------------------------------

echo "[legal] and [privacy]\n";

$imprintHtml = \Nino\Html::renderHtml( $appData, '[legal]' );
$privacyHtml = \Nino\Html::renderHtml( $appData, '[privacy]' );

check( '[legal] draws one section element per section, each with its anchor', substr_count( $imprintHtml, '<section class="nino-legal-section"' ) === count( $legal['de_DE'] )
	&& str_contains( $imprintHtml, 'id="legal-'. array_key_first( $legal['de_DE'] ). '"' ) );
check( '...with a heading and the text in the richtext wrapper', substr_count( $privacyHtml, '<h3>' ) === count( $privacy['de_DE'] ) && substr_count( $privacyHtml, '<div class="nino-richtext">' ) === count( $privacy['de_DE'] ) );
check( '[privacy] draws the privacy policy, not the imprint', str_contains( $privacyHtml, 'id="privacy-hosting"' ) && str_contains( $imprintHtml, 'id="privacy-hosting"' ) === false );

$orderedIds = [];
preg_match_all( '/id="privacy-([a-z-]+)"/', $privacyHtml, $found );
$orderedIds = $found[1];
$expectedOrder = array_keys( array_diff_key( $privacy['*'], [ '*' => 1 ] ) );
usort( $expectedOrder, static fn( string $a, string $b ): int => $privacy['*'][$a]['order'] <=> $privacy['*'][$b]['order'] );
check( 'the sections stand in the order of their position, not of the file', $orderedIds === $expectedOrder );

check( 'a section carries no lang attribute where it is in the language of the page', str_contains( $privacyHtml, ' lang="' ) === false );
check( 'the page is in German', str_contains( $privacyHtml, 'Datenschutz' ) === true || str_contains( $privacyHtml, 'Deine Rechte' ) === true );

\Nino\Locales::useLocale( $appData, 'en_US' );
$englishPrivacy = \Nino\Html::renderHtml( $appData, '[privacy]' );
check( 'the same sections are drawn in English in an English request', str_contains( $englishPrivacy, 'Your rights' ) && str_contains( $englishPrivacy, 'Deine Rechte' ) === false );
\Nino\Locales::useLocale( $appData, 'de_DE' );

// A section without a version in the language of the request is drawn in the native one, and says so
\Nino\Filesystem::mutate( $appData, '/elements/privacy.php', static function( array $type ): array {
	unset( $type['en_US']['hosting'] );
	return $type;
}, [] );
unset( $appData['./nino/elements/cache'] );
\Nino\Locales::useLocale( $appData, 'en_US' );
$fallbackHtml = \Nino\Html::renderHtml( $appData, '[privacy]' );
check( 'a section with no version in this language is drawn in the native one, marked with its language', str_contains( $fallbackHtml, '<section class="nino-legal-section" id="privacy-hosting" lang="de-DE">' )
	&& str_contains( $fallbackHtml, 'id="privacy-overview">' ) === true );
\Nino\Locales::useLocale( $appData, 'de_DE' );

// hidden takes a section off the page without deleting it
\Nino\Elements::updateElement( $appData, '/privacy/cookies', [ 'hidden' => true ], '*' );
$hiddenHtml = \Nino\Html::renderHtml( $appData, '[privacy]' );
check( 'a section set to hidden is not drawn', str_contains( $hiddenHtml, 'id="privacy-cookies"' ) === false && substr_count( $hiddenHtml, '<section' ) === count( $privacy['de_DE'] ) - 1 );
\Nino\Elements::updateElement( $appData, '/privacy/cookies', [ 'hidden' => false ], '*' );

check( 'the render of an unknown type is empty, and a type nobody asked for is not read', \Nino\Modules\Legal::render( $appData, 'posts' ) === '' && \Nino\Modules\Legal::render( $appData, '../legal' ) === '' );

// The listener of a section may add to it - what Consent does for its own
$sawSection = null;
\Nino\Callbacks::registerCallback( $appData, \Nino\Modules\Legal::SECTION, static function( array &$appData, array &$section ) use ( &$sawSection ): void {
	if( $section['id'] === 'cookies' ) {
		$sawSection = [ $section['type'], $section['id'], str_contains( $section['html'], '<p>' ) ];
		$section['html'] .= '<p><button type="button">Settings</button></p>';
	}
} );
$callbackHtml = \Nino\Html::renderHtml( $appData, '[privacy]' );
check( 'a listener of /nino/legal/section gets type, id and the text, and may add to it', $sawSection === [ 'privacy', 'cookies', true ] && str_contains( $callbackHtml, '<button type="button">Settings</button></p></div></section>' ) );
unset( $appData['./nino/callbacks'][ \Nino\Modules\Legal::SECTION ] );

// A type that was deleted draws nothing, and is no section
$typeFile = $sandbox. '/private/elements/legal.php';
rename( $typeFile, $typeFile. '.away' );
unset( $appData['./nino/elements/cache'] );
check( 'a deleted type draws nothing', \Nino\Html::renderHtml( $appData, '[legal]' ) === '' );
rename( $typeFile. '.away', $typeFile );
unset( $appData['./nino/elements/cache'] );

echo "\n";


// --- Placeholders ------------------------------------------------------------

echo "Placeholders\n";

$replace = static function( string $html ) use ( &$appData ): string {
	return \Nino\Modules\Legal::placeholders( $appData, $html );
};

check( 'a placeholder is replaced by what the key says in the language of the page', $replace( '<p>#/project/company/contact/email#</p>' ) === '<p>info@example.org</p>' );
check( '...as text: a line end of the value is a break, an ampersand is escaped', $replace( '<p>#/project/company/contact/address#</p>' ) === '<p>Hauptstraße 1<br>12345 Musterstadt</p>'
	&& $replace( '<p>#/project/company/general/name#</p>' ) === '<p>Müller &amp; Söhne</p>' );
check( '...tags of the value are taken out, and an entity that would decode to a tag stays an entity', $replace( '<p>#/project/website/general/author#</p>' ) === '<p>Max Muster</p>' && ( function() use ( &$appData, $replace ) {
	\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
		$texts['[[/project/website/general/author]]'] = '&lt;script&gt;alert(1)&lt;/script&gt;<script>alert(2)</script>';
		return $texts;
	}, [] );
	return $replace( '#/project/website/general/author#' ) === '&lt;script&gt;alert(1)&lt;/script&gt;alert(2)';
} )() );
check( 'a key of another namespace stays as written', $replace( '<p>#/feature/consent/cookie/name#</p>' ) === '<p>#/feature/consent/cookie/name#</p>'
	&& $replace( '<p>#/project/mail/address/owner#</p>' ) === '<p>#/project/mail/address/owner#</p>' && $replace( '<p>#/_admin/common/button/save#</p>' ) === '<p>#/_admin/common/button/save#</p>' );
check( 'a key that is not in the grammar - not four segments, an upper case letter - stays as written', $replace( '#/project/company/email#' ) === '#/project/company/email#'
	&& $replace( '#/project/Company/contact/email#' ) === '#/project/Company/contact/email#' && $replace( '#/project/company/contact/email/x#' ) === '#/project/company/contact/email/x#' );
check( 'a key without a value stays as written, so that it shows', $replace( '<p>#/project/company/contact/fax#</p>' ) === '<p>#/project/company/contact/fax#</p>' );
check( 'it is no placeholder inside a tag: an attribute is not touched', $replace( '<a href="#/project/company/contact/email#">x</a>' ) === '<a href="#/project/company/contact/email#">x</a>' );
check( 'a heading, an id and a second placeholder in one text are all replaced', $replace( '<p>#/project/company/contact/phone# / #/project/company/contact/email#</p>' ) === '<p>+49 123 456 / info@example.org</p>' );
check( 'a text with no placeholder is returned as it is', $replace( '<p>Nichts &amp; nichts.</p>' ) === '<p>Nichts &amp; nichts.</p>' );
check( 'a doubled hash is no placeholder', $replace( '##/project/company/contact/email##' ) === '##/project/company/contact/email##' );

// A value that names another fill or a shortcode cannot reach the page as one
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/email]]']	= 'x[[/project/company/contact/phone]]y';
	$texts['[[/project/company/contact/phone]]']	= '[legal] [privacy]';
	$texts['[[/project/company/general/name]]']		= 'a';
	return $texts;
}, [] );
$attack = \Nino\Html::renderHtml( $appData, '[privacy]' );
check( 'a value with a fill or a shortcode in it is shown as text: the fill is resolved, every bracket is an entity', $replace( '<p>#/project/company/contact/email#</p>' ) === '<p>x&#91;legal&#93; &#91;privacy&#93;y</p>'
	&& $replace( '<p>#/project/company/contact/phone#</p>' ) === '<p>&#91;legal&#93; &#91;privacy&#93;</p>' );
check( '...and the whole page, rendered through the kernel\'s second pass, carries none of it as markup', substr_count( $attack, '<section' ) === count( $privacy['de_DE'] ) && str_contains( $attack, '[legal]' ) === false );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/email]]']	= 'info@example.org';
	$texts['[[/project/company/contact/phone]]']	= '+49 123 456';
	$texts['[[/project/company/general/name]]']		= 'Müller & Söhne <GmbH>';
	return $texts;
}, [] );

// What a value that is nested says: the one [[key]] would give
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/mobile]]']	= '[[/project/company/contact/phone]]';
	return $texts;
}, [] );
check( 'a value that is a fill is resolved the way [[key]] resolves it, then made text', $replace( '#/project/company/contact/mobile#' ) === '+49 123 456' );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/fax]]'] = '';
	return $texts;
}, [] );
check( 'an empty value is replaced by nothing', $replace( '<p>Fax: #/project/company/contact/fax#</p>' ) === '<p>Fax: </p>' );

// What the sanitizer hands over, and what a value looks like that was stored in a form of its own
$sanitized = \Nino\Html::sanitizeHtml( '<p><a href="https://example.org/?a=>#/project/company/general/name#">x</a> #/project/company/general/name#</p>', 'blocks' );
check( 'the sanitizer gives a \'>\' that is no tag\'s as an entity, so a placeholder behind it is no tag\'s either - and it is not replaced inside the href', str_contains( $sanitized, 'a=&gt;#/project/company/general/name#' ) === true
	&& $replace( $sanitized ) === str_replace( ' #/project/company/general/name#', ' Müller &amp; Söhne', $sanitized ) );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/mobile]]']	= 'Max &quot;M&quot; &#91;x&#93;';
	$texts['[[/project/company/contact/fax]]']		= '<p>a</p><p>b</p>';
	$texts['[[/project/company/contact/mail]]']		= 'Müller &copy Söhne';
	return $texts;
}, [] );
check( 'a value stored with an entity is text with the entity gone - a quote is a quote, a bracket is a bracket again as an entity', $replace( '<p>#/project/company/contact/mobile#</p>' ) === '<p>Max &quot;M&quot; &#91;x&#93;</p>' );
check( 'a value of html loses its tags, and its blocks are lines', $replace( '<p>#/project/company/contact/fax#</p>' ) === '<p>a<br>b</p>' );
check( 'an entity without its semicolon is no entity, and its ampersand is escaped', $replace( '<p>#/project/company/contact/mail#</p>' ) === '<p>Müller &amp;copy Söhne</p>' );
check( 'a placeholder in a title is replaced', $replace( '<h3>Der Verantwortliche: #/project/company/general/name#</h3>' ) === '<h3>Der Verantwortliche: Müller &amp; Söhne</h3>' );
check( 'the keys that are no business of the module stay text: a template, the system, a project key of another category', $replace( '#/project/website/html/lang# #/_nino/webpage/legal/name# #/template/page-home/welcome/title#' ) === '#/project/website/html/lang# #/_nino/webpage/legal/name# #/template/page-home/welcome/title#' );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', static function( array $texts ): array {
	unset( $texts['[[/project/company/contact/mobile]]'], $texts['[[/project/company/contact/fax]]'], $texts['[[/project/company/contact/mail]]'] );
	return $texts;
}, [] );

// An element's text cannot reach the kernel as a shortcode, a fill or a script: written
// into a section of the file, as an editor - or an import - could, and drawn by the page
$overviewText = legalType( $appData, 'privacy' )['de_DE']['overview']['text'];
\Nino\Filesystem::mutate( $appData, '/elements/privacy.php', static function( array $type ): array {
	$type['de_DE']['overview']['text'] = '<p>[template /templates/mail-owner] [[/project/mail/address/owner]] [privacy] #/project/mail/address/owner#</p><script>alert(1)</script><p><a href="javascript:alert(2)">x</a> <a href="https://example.org/?a=>#/project/company/general/name#">y</a></p>';
	return $type;
}, [] );
unset( $appData['./nino/elements/cache'] );
$elementAttack = \Nino\Html::renderHtml( $appData, '[privacy]' );
check( 'what a section\'s text says is no shortcode, no fill, no script and no javascript: link - the page carries the brackets as entities and every section once', str_contains( $elementAttack, '<script' ) === false && str_contains( $elementAttack, 'javascript:' ) === false
	&& str_contains( $elementAttack, '[template' ) === false && str_contains( $elementAttack, '[[/project/mail/address/owner]]' ) === false && substr_count( $elementAttack, '<section' ) === count( $privacy['de_DE'] )
	&& str_contains( $elementAttack, 'mail-owner' ) === true && str_contains( $elementAttack, '#/project/mail/address/owner#' ) === true );
\Nino\Filesystem::mutate( $appData, '/elements/privacy.php', static function( array $type ) use ( $overviewText ): array {
	$type['de_DE']['overview']['text'] = $overviewText;
	return $type;
}, [] );
unset( $appData['./nino/elements/cache'] );

echo "\n";


// --- Routes and addresses --------------------------------------------------------

echo "Routes\n";

$routes = $appData['/nino/http/routes'];
check( 'every page has one route per language, at the path of that language, with the Element-URI, the language and the maintenance exception',
	( $routes['GET://impressum'] ?? null ) === [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ]
	&& ( $routes['GET://imprint'] ?? null ) === [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ]
	&& ( $routes['GET://datenschutz'] ?? null ) === [ 'uri' => '/legal/privacy', 'locale' => 'de_DE', 'body' => '[template /templates/page-legal-privacy]', 'maintenance' => false ]
	&& ( $routes['GET://privacy'] ?? null ) === [ 'uri' => '/legal/privacy', 'locale' => 'en_US', 'body' => '[template /templates/page-legal-privacy]', 'maintenance' => false ] );
check( 'the routes live in the live array only: nothing of them is in config.php', isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://impressum'] ) === false );
check( 'url() is the address of a page in the current language, and in a language asked for', \Nino\Modules\Legal::url( $appData, 'imprint' ) === '/impressum' && \Nino\Modules\Legal::url( $appData, 'privacy', 'en_US' ) === '/privacy'
	&& \Nino\Modules\Legal::url( $appData, 'nothing' ) === '' );
$dirBefore = $appData['/nino/dir'];
$appData['/nino/dir'] = '/sub';
check( 'url() carries the directory the project is served from in front of the path', \Nino\Modules\Legal::url( $appData, 'imprint' ) === '/sub/impressum' && \Nino\Modules\Legal::url( $appData, 'privacy', 'en_US' ) === '/sub/privacy' );
$appData['/nino/dir'] = $dirBefore;
check( 'a language that has no route is given the page of the native one', \Nino\Modules\Legal::url( $appData, 'imprint', 'fr_FR' ) === '/impressum' );
check( 'the module answers a request for the page: the route is found for the language', \Nino\Http::findRouteUri( $appData, '/legal/imprint', 'en_US' ) === 'GET://imprint' );

$templateImprint = (string) \Nino\Filesystem::getFileContent( $appData, '/templates/page-legal-imprint.tpl', '' );
check( 'the page template draws the shortcode in a section of the page', str_contains( $templateImprint, '[legal]' ) && str_contains( $templateImprint, '<section class="nino-section">' ) );

// A path that is not free, invalid, shared or missing
$planWith = static function( array $paths, array $locales = [ 'de_DE', 'en_US' ], array $routesBefore = [] ) use ( &$appData ): array {
	$copy = $appData;
	$copy['/nino/http/routes']	= $routesBefore;
	$copy['/nino/locales/available'] = $locales;
	$copy['/nino/legal/paths']	= $paths;
	\Nino\Modules\Legal::routes( $copy );
	return $copy['/nino/http/routes'];
};
$sharedRoutes = $planWith( [ 'imprint' => [ 'de_DE' => '/legal', 'en_US' => '/legal' ], 'privacy' => [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ] ] );
check( 'languages that share a path share one route, without a language', ( $sharedRoutes['GET://legal'] ?? null ) === [ 'uri' => '/legal/imprint', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ] );

$takenRoutes = $planWith( [ 'imprint' => [ 'de_DE' => '/kontakt', 'en_US' => '/imprint' ], 'privacy' => [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ] ], [ 'de_DE', 'en_US' ], [ 'GET://kontakt' => [ 'uri' => '/contact', 'body' => 'mine' ] ] );
check( 'a path another route holds is left alone: not overwritten, and the language gets the one under its own code', ( $takenRoutes['GET://kontakt']['body'] ?? null ) === 'mine'
	&& ( $takenRoutes['GET://de-de/imprint']['locale'] ?? null ) === 'de_DE' && ( $takenRoutes['GET://de-de/imprint']['uri'] ?? null ) === '/legal/imprint' );

$invalidRoutes = $planWith( [ 'imprint' => [ 'de_DE' => '/../etc', 'en_US' => '/_admin/x' ], 'privacy' => [ 'de_DE' => '/a b', 'en_US' => '/.hidden' ] ] );
check( 'a path that is no path - a dot, a space, a climb, the workbench - is no route, and no route has it', array_filter( array_keys( $invalidRoutes ), static fn( string $key ): bool => str_contains( $key, '..' ) || str_contains( $key, '_admin' ) || str_contains( $key, ' ' ) || str_contains( $key, '.hidden' ) ) === []
	&& $invalidRoutes === [] );

$threeRoutes = $planWith( [ 'imprint' => [ 'de_DE' => '/impressum', 'en_US' => '/imprint' ], 'privacy' => [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ] ], [ 'de_DE', 'en_US', 'fr_FR' ] );
check( 'a language without a path of its own is routed under its code, in front of the path of the native language', ( $threeRoutes['GET://fr-fr/impressum']['locale'] ?? null ) === 'fr_FR' && ( $threeRoutes['GET://fr-fr/datenschutz']['locale'] ?? null ) === 'fr_FR'
	&& ( $threeRoutes['GET://fr-fr/impressum']['maintenance'] ?? null ) === false );
check( 'and the language of a route is what the language switch leads to: findRouteUri() answers it for the language', ( function() use ( &$appData ) {
	$copy = $appData;
	$copy['/nino/http/routes'] = [];
	$copy['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
	\Nino\Modules\Legal::routes( $copy );
	return \Nino\Http::findRouteUri( $copy, '/legal/privacy', 'fr_FR' ) === 'GET://fr-fr/datenschutz';
} )() );
check( 'calling routes() twice changes nothing: the second pass finds its own routes', $planWith( [ 'imprint' => [ 'de_DE' => '/impressum', 'en_US' => '/imprint' ], 'privacy' => [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ] ] ) === array_intersect_key( $routes, array_flip( [ 'GET://impressum', 'GET://imprint', 'GET://datenschutz', 'GET://privacy' ] ) ) );

// The Seo feature is told about the pages
$seoPages = [];
\Nino\Modules\Legal::callbackSeoPages( $appData, $seoPages );
check( 'the Seo listener adds one entry per route and language, with the path, the Element-URI, the language and the details of the page', count( $seoPages ) === 4
	&& ( array_values( array_filter( $seoPages, static fn( array $page ): bool => $page['externalPath'] === '/impressum' ) )[0] ?? [] )['uri'] === '/legal/imprint'
	&& ( array_values( array_filter( $seoPages, static fn( array $page ): bool => $page['externalPath'] === '/impressum' ) )[0] ?? [] )['locale'] === 'de_DE'
	&& ( array_values( array_filter( $seoPages, static fn( array $page ): bool => $page['externalPath'] === '/privacy' ) )[0] ?? [] )['title'] === 'Privacy policy' );

echo "\n";


// --- Tombstones and what a unit adds ----------------------------------------------

echo "Sections deleted for good, and sections a unit adds\n";

check( 'the module listens on the committed elements', in_array( [ \Nino\Modules\Legal::class, 'callbackCommitted' ], \Nino\Callbacks::registered( $appData, '/nino/elements/committed' ), true ) === true );

$deleted = \Nino\Elements::deleteElement( $appData, '/privacy/tls', '*' );
$removed = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ] ?? [];
check( 'a section deleted in every language is on the tombstone list, in config.php', $deleted !== false && ( $removed['privacy'] ?? null ) === [ 'tls' ] );

$unitDir	= __DIR__. '/../_nino/Nino/Modules/Legal/install';
$routesOut	= [];
$blacklistOut = [];
$configOut	= [];
check( 'applying the unit again - as the wizard does, overwrite on - does not bring it back', \Nino\Features::applyUnit( $appData, $unitDir, [ 'de_DE', 'en_US' ], $routesOut, $blacklistOut, $configOut, true ) === true
	&& isset( legalType( $appData, 'privacy' )['de_DE']['tls'] ) === false && isset( legalType( $appData, 'privacy' )['*']['tls'] ) === false );
check( '...nor does it touch the section somebody changed', ( function() use ( &$appData ) {
	\Nino\Elements::updateElement( $appData, '/privacy/hosting', [ 'title' => 'Mein Hosting' ], 'de_DE' );
	$routesOut = []; $blacklistOut = []; $configOut = [];
	\Nino\Features::applyUnit( $appData, __DIR__. '/../_nino/Nino/Modules/Legal/install', [ 'de_DE', 'en_US' ], $routesOut, $blacklistOut, $configOut, true );
	return ( legalType( $appData, 'privacy' )['de_DE']['hosting']['title'] ?? null ) === 'Mein Hosting';
} )() );
check( 'a section deleted in one language only is no tombstone: the other language still has it', ( function() use ( &$appData ) {
	\Nino\Elements::deleteElement( $appData, '/privacy/objection', 'de_DE' );
	return ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ]['privacy'] ?? [] ) === [ 'tls' ];
} )() );
check( 'creating the element again by hand takes it off the list', ( function() use ( &$appData ) {
	$made = \Nino\Elements::insertElement( $appData, '/privacy/tls', [ 'title' => 'Neu', 'text' => '<p>Neu.</p>', 'order' => 900 ], 'de_DE' );
	return $made !== false && ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ] ?? [] ) === [];
} )() );
check( 'a section of the project\'s own, deleted, is no business of the module: nothing is remembered', ( function() use ( &$appData ) {
	\Nino\Elements::insertElement( $appData, '/privacy/mine', [ 'title' => 'Mein', 'text' => '<p>Mein.</p>' ], 'de_DE' );
	\Nino\Elements::deleteElement( $appData, '/privacy/mine', '*' );
	return ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ] ?? [] ) === [];
} )() );
check( 'a section of another type is no business of the module either', ( function() use ( &$appData ) {
	\Nino\Elements::insertElementType( $appData, '/notes', [ 'title' => [ 'type' => 'string', 'locale' => true ] ] );
	\Nino\Elements::insertElement( $appData, '/notes/hosting', [ 'title' => 'x' ], 'de_DE' );
	\Nino\Elements::deleteElement( $appData, '/notes/hosting', '*' );
	return ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ] ?? [] ) === [];
} )() );

echo "\n";


// --- A language added later --------------------------------------------------------

echo "A language added later\n";

// fr_FR: no unit text, so it gets the names of the native language
\Nino\Filesystem::putFileContent( $appData, '/text/fr_FR.php', [] );
$appData['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
check( 'a language the unit has no text for gets the names of the two pages in the native language, and nothing else of them', \Nino\Modules\Legal::addLocale( $appData, 'fr_FR' ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/fr_FR.php', [] )['[[/_nino/webpage/legal/imprint/name]]'] ?? null ) === 'Impressum'
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/fr_FR.php', [] )['[[/_nino/webpage/legal/imprint/title]]'] ) === false
	&& isset( legalType( $appData, 'privacy' )['fr_FR'] ) === false );

// A language that the unit has texts for: the project had only German
$lone = ninoSandbox( 'legal-lone' );
$loneDir = ninoSandboxDir( $lone );
$lone['/nino/dir'] = '';
$lone['/nino/locales/textfiles'] = '/text';
$lone['/nino/locales/available'] = [ 'de_DE' ];
foreach( [ 'private/templates', 'private/text', 'private/assets', 'public/images' ] as $directory )
	mkdir( $loneDir. '/'. $directory, 0777, true );
\Nino\Filesystem::putFileContent( $lone, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE' ], '/nino/modules' => [], '/nino/html/assets' => [], '/nino/http/routes' => [] ] );
$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE' ], 'modules' => [] ] );
$loneRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Install\Setup::apiApply( $lone, $loneRequest );
$lone['/nino/modules'] = \Nino\Filesystem::getFileContent( $lone, '/config.php', [] )['/nino/modules'];
\Nino\Elements::updateElement( $lone, '/privacy/hosting', [ 'title' => 'Mein Hosting' ], 'de_DE' );
\Nino\Elements::deleteElement( $lone, '/privacy/rights', '*' );
$germanBefore = legalType( $lone, 'privacy' )['de_DE'];
$lone['/nino/locales/available'] = [ 'de_DE', 'en_US' ];
\Nino\Filesystem::putFileContent( $lone, '/text/en_US.php', [] );
check( 'a language the unit has texts for gets the page details, the field labels and the hints from the unit', \Nino\Modules\Legal::addLocale( $lone, 'en_US' ) === true
	&& ( \Nino\Filesystem::getFileContent( $lone, '/text/en_US.php', [] )['[[/_nino/webpage/legal/privacy/name]]'] ?? null ) === 'Privacy'
	&& str_contains( (string) ( \Nino\Filesystem::getFileContent( $lone, '/text/en_US.php', [] )['[[/_admin/elements/type/privacy/hint]]'] ?? '' ), 'not legal advice' ) );
$englishAdded = legalType( $lone, 'privacy' );
check( '...and its version of every section the project has', isset( $englishAdded['en_US']['hosting']['title'] ) && count( $englishAdded['en_US'] ) === count( $germanBefore ) );
check( '...but no section the project deleted, nothing of the German versions changed, and no section is new', isset( $englishAdded['en_US']['rights'] ) === false && isset( $englishAdded['de_DE']['rights'] ) === false
	&& $englishAdded['de_DE'] === $germanBefore && ( $englishAdded['de_DE']['hosting']['title'] ?? null ) === 'Mein Hosting' );
check( 'a second call changes nothing', ( function() use ( &$lone, $englishAdded ) {
	\Nino\Modules\Legal::addLocale( $lone, 'en_US' );
	return legalType( $lone, 'privacy' ) === $englishAdded;
} )() );
check( 'a malformed code, or a module that is off, changes nothing and says true', \Nino\Modules\Legal::addLocale( $lone, '../x' ) === true && ( function() use ( &$lone ) {
	$off = $lone;
	$off['/nino/modules'] = [];
	return \Nino\Modules\Legal::addLocale( $off, 'fr_FR' ) === true && is_file( ninoSandboxDir( $off ). '/private/text/fr_FR.php' ) === false;
} )() );
\Nino\Filesystem::removeDir( $loneDir );
$_POST['data'] = json_encode( [] );

echo "\n";


// --- A feature's sections ---------------------------------------------------------

echo "A feature's sections\n";

// Back to the whole privacy policy
$routesOut = []; $blacklistOut = []; $configOut = [];
unset( $appData['./nino/elements/cache'] );
$sampleBefore = legalType( $appData, 'privacy' );
check( 'the feature is not active, and the project has no section of it yet', \Nino\Modules\Legal::contributions( $appData, 'sample' ) === [] && isset( $sampleBefore['*']['sample'] ) === false );

$appData['/nino/modules'] = array_values( array_unique( array_merge( $appData['/nino/modules'], [] ) ) );
$activated = \Nino\Features::activate( $appData, 'sample' );
$privacyWithSample = legalType( $appData, 'privacy' );
ninoWarnings();
check( 'activating the feature adds its sections, in both languages, at its positions', $activated === true && ( $privacyWithSample['de_DE']['sample']['title'] ?? null ) === 'Beispiel' && ( $privacyWithSample['en_US']['sample-more']['title'] ?? null ) === 'Sample more'
	&& ( $privacyWithSample['*']['sample']['order'] ?? null ) === 450 );
check( '...and nothing else of the policy changed', array_intersect_key( $privacyWithSample['de_DE'], $sampleBefore['de_DE'] ) === $sampleBefore['de_DE'] && array_keys( array_diff_key( $privacyWithSample['de_DE'], $sampleBefore['de_DE'] ) ) === [ 'sample', 'sample-more' ] );
check( 'contributions() names the sections a feature brought, in a language', array_column( \Nino\Modules\Legal::contributions( $appData, 'sample', 'en_US' ), 'title' ) === [ 'Sample', 'Sample more' ]
	&& array_column( \Nino\Modules\Legal::contributions( $appData, 'sample' ), 'id' ) === [ 'sample', 'sample-more' ] );
check( '...and not the ones that are hidden', ( function() use ( &$appData ) {
	\Nino\Elements::updateElement( $appData, '/privacy/sample-more', [ 'hidden' => true ], '*' );
	$left = array_column( \Nino\Modules\Legal::contributions( $appData, 'sample' ), 'id' );
	\Nino\Elements::updateElement( $appData, '/privacy/sample-more', [ 'hidden' => false ], '*' );
	return $left === [ 'sample' ];
} )() );
check( 'a feature that does not exist, or has no sections, has none', \Nino\Modules\Legal::contributions( $appData, 'nothing' ) === [] && \Nino\Modules\Legal::contributions( $appData, 'helper' ) === [] );

\Nino\Elements::updateElement( $appData, '/privacy/sample', [ 'title' => 'Mein Beispiel' ], 'de_DE' );
$routesOut = []; $blacklistOut = []; $configOut = [];
\Nino\Features::activate( $appData, 'sample' );
check( 'activating again changes nothing, and the section somebody changed stays', ( legalType( $appData, 'privacy' )['de_DE']['sample']['title'] ?? null ) === 'Mein Beispiel' && count( legalType( $appData, 'privacy' )['de_DE'] ) === count( $privacyWithSample['de_DE'] ) );

\Nino\Elements::deleteElement( $appData, '/privacy/sample-more', '*' );
$removedNow = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[ \Nino\Elements::REMOVED ] ?? [];
check( 'a section of the active feature, deleted for good, is on the tombstone list', in_array( 'sample-more', (array) ( $removedNow['privacy'] ?? [] ), true ) === true );
\Nino\Features::activate( $appData, 'sample' );
check( '...and an update of the feature - an activation - does not bring it back', isset( legalType( $appData, 'privacy' )['*']['sample-more'] ) === false );

echo "\n";


// --- What the dashboard says -------------------------------------------------------

echo "check() and notices()\n";

/**
 *	A project the wizard set up and the texts of which are filled in: what
 *	check() has nothing to say about
 *
 *	@param		string		$name
 *
 *	@return 	array
 */
function legalProject( string $name ): array {

	$appData = ninoSandbox( $name );
	$sandbox = ninoSandboxDir( $appData );
	$appData['/nino/dir'] = '';
	$appData['/nino/locales/textfiles'] = '/text';

	foreach( [ 'private/templates', 'private/text', 'private/assets', 'public/images' ] as $directory )
		mkdir( $sandbox. '/'. $directory, 0777, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [], '/nino/html/assets' => [], '/nino/http/routes' => [] ] );
	$_POST['data'] = json_encode( [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Install\Setup::apiApply( $appData, $request );
	$_POST['data'] = json_encode( [] );
	$appData['/nino/modules'] = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'];

	foreach( [ 'de_DE', 'en_US' ] as $locale )
		\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', static function( array $texts ): array {
			foreach( [ 'general/name' => 'Muster GmbH', 'contact/address' => 'Hauptstr. 1', 'contact/phone' => '+49 1', 'contact/email' => 'a@example.org' ] as $key => $value )
				$texts['[[/project/company/'. $key. ']]'] = $value;
			$texts['[[/project/website/general/author]]'] = 'Max';
			$texts['[[/project/website/general/url]]'] = 'example.org';
			return $texts;
		}, [] );

	\Nino\Html::init( $appData );
	\Nino\Modules\Legal::init( $appData );
	\Nino\Locales::useLocale( $appData, 'de_DE' );
	ninoWarnings();

	return $appData;
}

$kindsOf = static fn( array $findings ): array => array_column( $findings, 'kind' );

$clean = legalProject( 'legal-check' );
check( 'a project whose texts are filled in, whose pages stand in the menu of the footer and whose features are described has nothing to be told', \Nino\Modules\Legal::check( $clean ) === [] && \Nino\Modules\Legal::notices( $clean ) === [] );
check( 'and check() leaves the language of the request as it was', \Nino\Locales::getCurrentLocale( $clean ) === 'de_DE' );

// A placeholder nobody can fill, one that is empty, one the module never replaces
\Nino\Elements::updateElement( $clean, '/privacy/hosting', [ 'text' => '<p>#/project/company/contact/fax# #/project/company/contact/mobile# #/project/mail/address/owner#</p>' ], 'de_DE' );
\Nino\Filesystem::mutate( $clean, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/mobile]]'] = '';
	return $texts;
}, [] );
$findings = \Nino\Modules\Legal::check( $clean );
$byKind = [];
foreach( $findings as $finding )
	$byKind[ $finding['kind'] ][] = $finding;
check( 'unknown: a placeholder without a value, with the section and the language', ( $byKind['unknown'][0]['values'] ?? null ) === [ '#/project/company/contact/fax#', 'Hosting', 'de_DE' ] && ( $byKind['unknown'][0]['link'] ?? null ) === '#elements/privacy/hosting' );
check( 'empty: a placeholder whose value is empty', ( $byKind['empty'][0]['values'] ?? null ) === [ '#/project/company/contact/mobile#', 'Hosting', 'de_DE' ] );
check( 'ignored: something that looks like a placeholder and is never replaced', ( $byKind['ignored'][0]['values'] ?? null ) === [ '#/project/mail/address/owner#', 'Hosting', 'de_DE' ] );
check( 'the same finding is said once, whatever the number of languages that find it', count( $byKind['unknown'] ?? [] ) === 1 && array_sum( array_map( 'count', $byKind ) ) === 3 );

// A fill a page puts in at runtime is a value, though no text file has it; one that resolves to nothing is empty
\Nino\Html::addFills( $clean, [ '[[/project/company/contact/live]]' => 'runtime' ] );
\Nino\Filesystem::mutate( $clean, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/project/company/contact/nested]]'] = '[[/project/company/contact/mobile]]';
	return $texts;
}, [] );
\Nino\Elements::updateElement( $clean, '/privacy/hosting', [ 'text' => '<p>#/project/company/contact/live# #/project/company/contact/nested#</p>' ], 'de_DE' );
$resolved = \Nino\Modules\Legal::check( $clean );
check( 'a placeholder for a runtime fill is no unknown one, and a value that resolves to nothing is an empty one', array_column( $resolved, 'kind' ) === [ 'empty' ] && $resolved[0]['values'] === [ '#/project/company/contact/nested#', 'Hosting', 'de_DE' ] );
\Nino\Filesystem::mutate( $clean, '/text/de_DE.php', static function( array $texts ): array {
	unset( $texts['[[/project/company/contact/nested]]'] );
	return $texts;
}, [] );

// A section with nothing in a language
\Nino\Elements::updateElement( $clean, '/privacy/hosting', [ 'text' => '<p>Hosting.</p>' ], 'de_DE' );
\Nino\Filesystem::mutate( $clean, '/elements/privacy.php', static function( array $type ): array {
	unset( $type['en_US']['rights'] );
	return $type;
}, [] );
unset( $clean['./nino/elements/cache'] );
$translation = array_values( array_filter( \Nino\Modules\Legal::check( $clean ), static fn( array $finding ): bool => $finding['kind'] === 'translation' ) );
check( 'translation: a section with neither title nor text in a language, named by its title in the native language', count( $translation ) === 1 && $translation[0]['values'] === [ 'Deine Rechte', 'en_US' ] && $translation[0]['link'] === '#elements/privacy/rights' );
\Nino\Filesystem::mutate( $clean, '/elements/privacy.php', static function( array $type ): array {
	$type['en_US']['rights'] = [ 'title' => 'Your rights', 'text' => '<p>Rights.</p>' ];
	return $type;
}, [] );
unset( $clean['./nino/elements/cache'] );

// A type that is gone: the finding says which file of the unit to copy back
$privacyFile = ninoSandboxDir( $clean ). '/private/elements/privacy.php';
$kept = (string) file_get_contents( $privacyFile );
unlink( $privacyFile );
unset( $clean['./nino/elements/cache'] );
$nothing = array_values( array_filter( \Nino\Modules\Legal::check( $clean ), static fn( array $finding ): bool => $finding['kind'] === 'nothing' ) );
check( 'nothing: a type that is missing, with the file of the unit to copy back', count( $nothing ) === 1 && $nothing[0]['values'][0] === 'privacy' && str_ends_with( $nothing[0]['values'][1], 'install/elements/privacy.php' ) && is_file( $nothing[0]['values'][1] ) === true );
file_put_contents( $privacyFile, $kept );
unset( $clean['./nino/elements/cache'] );
check( '...and gone when the file is back', \Nino\Modules\Legal::check( $clean ) === [] );

// A type with nothing to be seen: every section hidden
foreach( array_keys( array_diff_key( legalType( $clean, 'legal' )['*'], [ '*' => 1 ] ) ) as $id )
	\Nino\Elements::updateElement( $clean, '/legal/'. $id, [ 'hidden' => true ], '*' );
check( 'nothing: a type with no section to be seen', array_column( array_filter( \Nino\Modules\Legal::check( $clean ), static fn( array $finding ): bool => $finding['kind'] === 'nothing' ), 'values' )[0][0] === 'legal' );
foreach( array_keys( array_diff_key( legalType( $clean, 'legal' )['*'], [ '*' => 1 ] ) ) as $id )
	\Nino\Elements::updateElement( $clean, '/legal/'. $id, [ 'hidden' => false ], '*' );

// Routes: a path that is no path, a stored route that carries a page's Element-URI
$withBadPath = $clean;
$withBadPath['/nino/legal/paths'] = [ 'imprint' => [ 'de_DE' => '/a b', 'en_US' => '/imprint' ], 'privacy' => [ 'de_DE' => '/datenschutz', 'en_US' => '/privacy' ] ];
$routeFindings = array_values( array_filter( \Nino\Modules\Legal::check( $withBadPath ), static fn( array $finding ): bool => str_starts_with( $finding['kind'], 'route' ) ) );
check( 'route: a path that is invalid, and the one the language is routed under instead - each with words of its own', count( $routeFindings ) === 2
	&& $routeFindings[0] === [ 'kind' => 'route', 'values' => [ 'Impressum', 'de_DE', '/a b' ], 'link' => '' ]
	&& $routeFindings[1] === [ 'kind' => 'route-derived', 'values' => [ 'Impressum', 'de_DE', '/de-de/imprint' ], 'link' => '' ] );
\Nino\Filesystem::mutate( $clean, '/config.php', static function( array $config ): array {
	$config['/nino/http/routes']['GET://mine'] = [ 'uri' => '/legal/imprint', 'body' => 'mine' ];
	return $config;
}, [] );
check( 'route: a stored route that carries the Element-URI of a page - the route first, then the page', array_column( array_filter( \Nino\Modules\Legal::check( $clean ), static fn( array $finding ): bool => str_starts_with( $finding['kind'], 'route' ) ), 'values', 'kind' ) === [ 'route-stored' => [ 'GET://mine', 'Impressum' ] ] );
\Nino\Filesystem::mutate( $clean, '/config.php', static function( array $config ): array {
	unset( $config['/nino/http/routes']['GET://mine'] );
	return $config;
}, [] );

// Navigation: no menu a template outputs has the pages
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '<footer>[navigation nav="footer"][/navigation]</footer>' );
$navFindings = array_values( array_filter( \Nino\Modules\Legal::check( $clean ), static fn( array $finding ): bool => $finding['kind'] === 'nav' ) );
check( 'nav: a page in no menu of the website, with the languages', count( $navFindings ) === 2 && $navFindings[0]['values'] === [ 'Impressum', 'de_DE, en_US' ] && $navFindings[1]['values'] === [ 'Datenschutz', 'de_DE, en_US' ] && $navFindings[0]['link'] === '#navs' );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '<footer>[navigation nav="legal" id="legal__nav"][/navigation]</footer>' );
check( '...and no finding once a template outputs the menu that has them', \Nino\Modules\Legal::check( $clean ) === [] );
$noNavigation = $clean;
$noNavigation['/nino/modules'] = array_values( array_diff( $clean['/nino/modules'], [ '\\Nino\\Modules\\Navigation' ] ) );
check( '...but one when the Navigation module is off: there is no menu', $kindsOf( \Nino\Modules\Legal::check( $noNavigation ) ) === [ 'nav', 'nav' ] );

// Features: a section of one that is off, and one that is on with nothing to be seen
$sampleKinds = static fn( array $project ): array => array_values( array_filter( \Nino\Modules\Legal::check( $project ), static fn( array $finding ): bool => $finding['kind'] === 'feature' || $finding['kind'] === 'undescribed' ) );
ninoWarnings();
check( 'a feature that is off and has no section in the project is nothing to tell', $sampleKinds( $clean ) === [] );
check( 'switching it on brings its sections, and nothing is to be told', \Nino\Features::activate( $clean, 'sample' ) === true && $sampleKinds( $clean ) === [] );
\Nino\Features::deactivate( $clean, 'sample' );
$off = $sampleKinds( $clean );
check( 'feature: switched off, its sections are still to be seen - the finding names it and them', count( $off ) === 1 && $off[0]['kind'] === 'feature' && $off[0]['values'][1] === 'Beispiel, Beispiel mehr' && $off[0]['link'] === '#elements/privacy' );
\Nino\Features::activate( $clean, 'sample' );
\Nino\Elements::updateElement( $clean, '/privacy/sample', [ 'hidden' => true ], '*' );
\Nino\Elements::updateElement( $clean, '/privacy/sample-more', [ 'hidden' => true ], '*' );
$on = $sampleKinds( $clean );
check( 'undescribed: switched on, and every section of it hidden or deleted', count( $on ) === 1 && $on[0]['kind'] === 'undescribed' && $on[0]['values'] === [ 'Beispiel-Feature' ] );
ninoWarnings();

// notices(): the first eight, then one that says how many more
$many = legalProject( 'legal-many' );
\Nino\Elements::updateElement( $many, '/privacy/hosting', [ 'text' => '<p>'. implode( ' ', array_map( static fn( int $n ): string => '#/project/company/contact/x'. $n. '#', range( 1, 11 ) ) ). '</p>' ], 'de_DE' );
$notices = \Nino\Modules\Legal::notices( $many );
check( 'notices() are the findings as the dashboard shows them: a text key, the values, a link - eight of them, then one that counts the rest', count( $notices ) === 9
	&& $notices[0]['text'] === '/_admin/dashboard/notice/legal-unknown' && $notices[0]['values'][0] === '#/project/company/contact/x1#' && $notices[0]['link'] === '#elements/privacy/hosting'
	&& $notices[8] === [ 'text' => '/_admin/dashboard/notice/legal-more', 'values' => [ '3' ], 'link' => '' ] );
$wordsEnglish = include __DIR__. '/../_admin/Nino/Modules/Dashboard/text/en_US.php';
$wordsGerman = include __DIR__. '/../_admin/Nino/Modules/Dashboard/text/de_DE.php';
$missingWords = [];
foreach( [ 'unknown', 'empty', 'ignored', 'translation', 'nothing', 'route', 'route-derived', 'route-none', 'route-stored', 'nav', 'feature', 'undescribed', 'more' ] as $kind )
	foreach( [ $wordsEnglish, $wordsGerman ] as $words )
		if( isset( $words['[[/_admin/dashboard/notice/legal-'. $kind. ']]'] ) === false )
			$missingWords[] = $kind;
check( 'every kind of finding has its words in both languages'. ( $missingWords === [] ? '' : ' - '. implode( ', ', $missingWords ) ), $missingWords === [] );

// The module off: nothing to tell, no address, no section
$off = $clean;
$off['/nino/modules'] = array_values( array_diff( $clean['/nino/modules'], [ '\\Nino\\Modules\\Legal' ] ) );
check( 'with the module off there is nothing to tell and no address', \Nino\Modules\Legal::check( $off ) === [] && \Nino\Modules\Legal::notices( $off ) === [] && \Nino\Modules\Legal::url( $off, 'imprint' ) === '' );

foreach( [ $clean, $many ] as $project )
	\Nino\Filesystem::removeDir( ninoSandboxDir( $project ) );

echo "\n";

ninoDone( $appData );
