<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-text-smoke.php	Dependency-free smoke test for the Text and Images panels of the
 *											workbench: the image slot definitions, the text key schema, a key's
 *											format and limit, the closed vocabulary of the words that name a part
 *											of a key, the scanners for missing keys and for hardcoded images, where
 *											a slot is used, and the Dashboard tiles that count them. Runs against
 *											an isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/admin-text-smoke.php
 */

require __DIR__. '/harness.php';

/**
 *	Dispatch one action directly against a Dev\* module class
 *
 *	A copy of the helper in admin-system-smoke.php, which the other admin suites
 *	carry as well: it is no helper the harness should hold
 *
 *	@param		array 		&$appData
 *	@param		string		$class				eg. "\Nino\Modules\Config\Admin"
 *	@param		string		$method				eg. "apiList"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body, request ]
 */
function callDev( array &$appData, string $class, string $method, array $data = [] ): array {
	return ninoCall( $appData, [ $class, $method ], $data );
}

$appData = ninoSandbox( 'admin-text' );
$sandbox = ninoSandboxDir( $appData );

$appData['/nino/dir']		= '';

// A set-up project with one developer account, logged in - the shell serves
// the workbench, every panel action runs against this session
ninoUser( $appData, 'dev@example.com' );

echo "Sandbox: $sandbox\n\n";


// --- Dev\Images -----------------------------------------------------------

echo "Images - image slot definitions (label/width/height only)\n";

// Three active languages: the Dashboard's legal notices follow each one
callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'Hero', 'width' => '1200', 'height' => '600' ] );
check( 'apiCreate creates a slot', $status === 200 );
check( 'the slot starts with no filename', array_key_exists( 'filename', $appData['/nino/html/images']['/home/hero'] ) === true && $appData['/nino/html/images']['/home/hero']['filename'] === null );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => 'not valid', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects an invalid uri', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/hero', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate rejects a uri that already exists', $status === 409 );

/*	A slot's width and height are the exact canvas \Nino\Images::process()
	renders onto, and the kernel holds no more than MAX_SOURCE_PIXELS of one
	picture in memory. Saved above that the slot cannot be filled by anything:
	measured against the old panel, apiCreate took 20000x20000 with a 200 and
	the upload meant to fill it came back "invalid or oversized image" - which
	sends the person looking at their photograph instead of at the size they
	typed.	*/
[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/wall', 'label' => 'Wall', 'width' => '20000', 'height' => '20000' ] );
check( 'apiCreate refuses a slot no upload could ever fill, with a reason', $status === 400 && ( $body['error'] ?? '' ) !== '' );
check( '...and stores nothing of it', isset( $appData['/nino/html/images']['/home/wall'] ) === false );

// The cap is the kernel's own budget, so the largest slot it can still
// render has to go through - one pixel more is where it stops
$edge = (int) floor( sqrt( \Nino\Images::MAX_SOURCE_PIXELS ) );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/large', 'label' => 'Large', 'width' => (string) $edge, 'height' => (string) $edge ] );
check( 'the largest slot the kernel can still render is accepted', $status === 200 && ( $appData['/nino/html/images']['/home/large']['width'] ?? 0 ) === $edge );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/large', 'label' => 'Large', 'width' => (string) \Nino\Images::MAX_SOURCE_PIXELS, 'height' => '2' ] );
check( 'apiSave refuses to grow an existing slot past the same budget', $status === 400 );
check( '...and leaves the size it had', ( $appData['/nino/html/images']['/home/large']['width'] ?? 0 ) === $edge );

callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/large' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the new slot', in_array( '/home/hero', array_column( $body['slots'], 'uri' ), true ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu', 'width' => '800', 'height' => '400' ] );
check( 'apiSave edits an existing slot', $status === 200 );
check( 'the slot metadata actually changed', $appData['/nino/html/images']['/home/hero']['label'] === 'Hero neu' && $appData['/nino/html/images']['/home/hero']['width'] === 800 );

\Nino\Images::setSlotFilename( $appData, '/home/hero', 'elements/home/hero.800x400.jpg' );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave never touches an existing filename', $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg'
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/home/hero']['filename'] ?? null ) === 'elements/home/hero.800x400.jpg' );

// The alt texts live on the slot and are the Images panel's: saving the slot's label and size keeps them
\Nino\Images::setSlotAlt( $appData, '/home/hero', [ 'de_DE' => 'Ein Bild' ] );
callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero neu 2', 'width' => '800', 'height' => '400' ] );
check( 'apiSave keeps the slot\'s alt texts', ( $appData['/nino/html/images']['/home/hero']['alt'] ?? null ) === [ 'de_DE' => 'Ein Bild' ]
	&& ( ( include \Nino\Filesystem::path( $appData, '/config.php' ) )['/nino/html/images']['/home/hero']['alt'] ?? null ) === [ 'de_DE' => 'Ein Bild' ] );
\Nino\Images::setSlotAlt( $appData, '/home/hero', [ 'de_DE' => '' ] );

/*	A slot is written alone, not the whole key from the copy this request
	booted with. Another request - the Images panel - saves an alt text and a
	new file after this one booted: $appData still has the slot as it was, and
	the old code wrote that copy over config.php, so the alt text and the file
	were gone again and the slot was filled by a file nobody referenced. Here
	the other request is played by a write straight to config.php, which
	leaves $appData as it booted	*/
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $config ): array {
	$config['/nino/html/images']['/home/hero']['alt'] = [ 'de_DE' => 'Gespeichert dazwischen' ];
	$config['/nino/html/images']['/home/hero']['filename'] = 'home/hero.other.jpg';
	$config['/nino/html/images']['/home/other'] = [ 'label' => 'Added meanwhile', 'width' => 10, 'height' => 10, 'filename' => null ];
	return $config;
} );
check( 'the other request left this one\'s copy as it booted', isset( $appData['/nino/html/images']['/home/hero']['alt'] ) === false && isset( $appData['/nino/html/images']['/home/other'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/home/hero', 'label' => 'Hero raced', 'width' => '800', 'height' => '400' ] );
$racedSlot = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'];
check( 'apiSave changes the three fields and nothing else: the alt text, the file and a slot saved since boot survive', $status === 200 && ( $racedSlot['/home/hero']['label'] ?? null ) === 'Hero raced'
	&& ( $racedSlot['/home/hero']['alt'] ?? null ) === [ 'de_DE' => 'Gespeichert dazwischen' ] && ( $racedSlot['/home/hero']['filename'] ?? null ) === 'home/hero.other.jpg' && isset( $racedSlot['/home/other'] ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/created', 'label' => 'Created', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate adds its slot alone: the one saved since boot is still there, and the new one has no file', $status === 200
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/home/other'] ) === true && array_key_exists( 'filename', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/home/created'] ?? [] ) === true );
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $config ): array {
	$config['/nino/html/images']['/home/late'] = [ 'label' => 'Late', 'width' => 10, 'height' => 10, 'filename' => null ];
	return $config;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/home/late', 'label' => 'Late again', 'width' => '10', 'height' => '10' ] );
check( 'apiCreate does not replace a slot another request created since boot: 409', $status === 409 && ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/home/late']['label'] ?? null ) === 'Late' );
callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/created' ] );
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $config ): array {
	unset( $config['/nino/html/images']['/home/other'], $config['/nino/html/images']['/home/late'] );
	$config['/nino/html/images']['/home/hero']['filename'] = 'elements/home/hero.800x400.jpg';
	unset( $config['/nino/html/images']['/home/hero']['alt'] );
	return $config;
} );
unset( $appData['/nino/html/images']['/home/other'], $appData['/nino/html/images']['/home/late'], $appData['/nino/html/images']['/home/hero']['alt'] );
$appData['/nino/html/images']['/home/hero']['filename'] = 'elements/home/hero.800x400.jpg';
$appData['/nino/html/images']['/home/hero']['label'] = 'Hero neu 2';

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiSave', [ 'uri' => '/does/not/exist', 'label' => 'x', 'width' => '10', 'height' => '10' ] );
check( 'apiSave 404s for an unknown slot', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown slot', $status === 404 );

/*	Deleting a slot deletes its uploaded file with it, and the order of those
	two is the whole of it. The file used to go first: a write that does not
	happen - a config.php that cannot be locked, a disk with nothing left -
	then left the slot standing in config.php pointing at a file that is gone,
	the public page rendering a broken <img>, and the panel with nothing to
	re-upload over. Measured against the old code with the sidecar lock of
	config.php made impossible to open: apiDelete answered 200, the file was
	gone, the slot was still in config.php, and the retry 404'd because the
	only copy that had lost it was the one in memory.	*/
\Nino\Filesystem::putFileContent( $appData, '/images/home/hero.800x400.jpg', 'stand-in for the uploaded bytes' );
$heroFile = \Nino\Filesystem::path( $appData, '/images' ). '/home/hero.800x400.jpg';
check( 'the slot has a file to lose', is_file( $heroFile ) === true
	&& $appData['/nino/html/images']['/home/hero']['filename'] === 'elements/home/hero.800x400.jpg' );
\Nino\Images::setSlotFilename( $appData, '/home/hero', 'home/hero.800x400.jpg' );

// A directory where config.php's sidecar lock file goes: lockFile() cannot
// open it, so writeContentData() refuses to write - the same answer a
// read-only or full disk gives, without needing either
$configLock = $sandbox. '/private/data/.locks/'. sha1( '/config.php' ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $configLock );
@mkdir( $configLock );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'a delete that could not be persisted says so rather than reporting success', $status === 500 );
check( '...and leaves the uploaded file exactly where it was', is_file( $heroFile ) === true );
check( '...and leaves the slot itself, so the delete can simply be repeated', isset( $appData['/nino/html/images']['/home/hero'] ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/home/hero'] ) === true );

@rmdir( $configLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiDelete', [ 'uri' => '/home/hero' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'the slot is gone from appData', isset( $appData['/nino/html/images']['/home/hero'] ) === false );
$imagesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [];
check( 'the slot is gone from config.php too', isset( $imagesAfterDelete['/home/hero'] ) === false );
check( '...and now that the record is persisted, the file went with it', is_file( $heroFile ) === false );

echo "\n";


// --- Dev\Text ---------------------------------------------------------------

echo "Text - text key schema (existence, global/per-locale, blacklist)\n";

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/template/page-home/welcome/subtitle', 'global' => false, 'value' => 'Start' ] );
check( 'apiCreate creates a per-locale key', $status === 200 );

$deDE = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUS = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the initial value is written into every available locale', $deDE['[[/template/page-home/welcome/subtitle]]'] === 'Start' && $enUS['[[/template/page-home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/company/general/tagline', 'global' => true, 'value' => 'Immer' ] );
check( 'apiCreate creates a global key', $status === 200 );

$global = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the initial value is written into global.php, not any locale file', $global['[[/project/company/general/tagline]]'] === 'Immer' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => 'not-a-valid-key', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects an invalid key', $status === 400 );

// The server decides what a key made by hand looks like - /<namespace>/<category>/<part>/<name>,
// whatever the form sent - and says so in a sentence that names the shape
$textBefore = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
foreach( [ '/foo/bar', '/template/page-home/title', '/_nino/webpage/x/title', '/_admin/x/y/z', '/Template/x/y/z', '/template/page_home/x/y', '/project/a/b/c/d' ] as $badKey ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => $badKey, 'global' => true, 'value' => 'x' ] );
	check( "apiCreate refuses $badKey with the code and the field", $status === 400 && ( $body['code'] ?? '' ) === 'keys_invalid' && ( $body['params'] ?? [] ) === [ $badKey ] && ( $body['field'] ?? '' ) === 'key' );
}
check( '...and writes nothing for any of them', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) === $textBefore );
foreach( [ 'en_US', 'de_DE' ] as $grammarLocale )
	check( "$grammarLocale: the sentence of the refusal names the form", str_contains( (string) ( include __DIR__. '/../_admin/Nino/Modules/Text/text/'. $grammarLocale. '.php' )['[[/_admin/error/keys_invalid]]'], '/<'. ( $grammarLocale === 'en_US' ? 'namespace' : 'namensraum' ). '>/<'. ( $grammarLocale === 'en_US' ? 'category' : 'kategorie' ). '>/<'. ( $grammarLocale === 'en_US' ? 'part' : 'teil' ). '>/<name>' ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/catalog/list/title', 'global' => false, 'value' => 'Katalog' ] );
check( 'a key that follows the grammar is created', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/catalog/list/title]]'] ?? null ) === 'Katalog' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/catalog/list/title' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/company/general/tagline', 'global' => true, 'value' => 'x' ] );
check( 'apiCreate rejects a key that already exists', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
$subtitleEntry = null;
foreach( $body['keys'] as $entry ) if( $entry['key'] === '/template/page-home/welcome/subtitle' ) $subtitleEntry = $entry;
check( 'apiList finds the new per-locale key, not blacklisted by default', $subtitleEntry !== null && $subtitleEntry['global'] === false && $subtitleEntry['blacklisted'] === false );

// What the form that creates or renames a key offers to choose from, per namespace
$templatesDir = $sandbox. '/private/templates';
$madeTemplatesDir = is_dir( $templatesDir ) === false && mkdir( $templatesDir, 0777, true );
foreach( [ 'page-extra', 'frame-header', 'page-Foo', 'page-legal.de_DE' ] as $categoryFile )
	file_put_contents( $templatesDir. '/'. $categoryFile. '.tpl', '' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/shop/list/title', 'global' => false, 'value' => 'Shop' ] );
$appData['./nino/features/all'] = [ 'sample' => [ 'key' => 'sample', 'dir' => $sandbox. '/sample-feature', 'name' => 'Sample' ], 'shop' => [ 'key' => 'shop', 'dir' => $sandbox. '/shop-feature', 'name' => 'Shop' ] ];
[ , $listBody ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
unset( $appData['./nino/features/all'] );
check( 'apiList names the language of the session, which the tab opens in', ( $listBody['selectedLocale'] ?? '' ) === \Nino\Admin\Admin::sessionLocale( $appData ) );
check( 'apiList offers /template the templates directly in templates/ whose name is a category, and "common" first',
	array_slice( $listBody['categories']['template'] ?? [], 0, 1 ) === [ 'common' ] && array_intersect( [ 'frame-header', 'page-extra' ], $listBody['categories']['template'] ?? [] ) === [ 'frame-header', 'page-extra' ] );
check( '...none whose name is no word of a key', in_array( 'page-Foo', $listBody['categories']['template'] ?? [], true ) === false && in_array( 'page-legal.de_DE', $listBody['categories']['template'] ?? [], true ) === false );
check( '/module is the kernel\'s modules, by their directory in lower case', in_array( 'form', $listBody['categories']['module'] ?? [], true ) === true && in_array( 'maintenance', $listBody['categories']['module'] ?? [], true ) === true
	&& in_array( 'Form', $listBody['categories']['module'] ?? [], true ) === false && in_array( 'modules.php', $listBody['categories']['module'] ?? [], true ) === false );
check( '/project is company, website and mail, then the categories keys already use', array_slice( $listBody['categories']['project'] ?? [], 0, 3 ) === [ 'company', 'website', 'mail' ] && in_array( 'shop', $listBody['categories']['project'] ?? [], true ) === true );
check( '/feature is the features that are installed, by their key', ( $listBody['categories']['feature'] ?? null ) === [ 'sample', 'shop' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/shop/list/title' ] );
foreach( [ 'page-extra', 'frame-header', 'page-Foo', 'page-legal.de_DE' ] as $categoryFile )
	unlink( $templatesDir. '/'. $categoryFile. '.tpl' );
if( $madeTemplatesDir === true )
	rmdir( $templatesDir );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/subtitle', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave converts a per-locale key to global', $status === 200 );

$deDEAfter = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'converting to global removes it from every locale file', isset( $deDEAfter['[[/template/page-home/welcome/subtitle]]'] ) === false );
check( 'converting to global migrates the value instead of discarding it', $globalAfter['[[/template/page-home/welcome/subtitle]]'] === 'Start' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/subtitle', 'global' => false, 'blacklisted' => true ] );
check( 'apiSave converts back to per-locale and blacklists it in the same call', $status === 200 );

$deDEAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$globalAfter2 = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$blacklist = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'converting back to per-locale migrates the value into every locale', $deDEAfter2['[[/template/page-home/welcome/subtitle]]'] === 'Start' );
check( 'converting back to per-locale removes it from global.php', isset( $globalAfter2['[[/template/page-home/welcome/subtitle]]'] ) === false );
check( 'the key is now blacklisted', in_array( '/template/page-home/welcome/subtitle', $blacklist, true ) === true );

/*	Per-locale -> global keeps the native locale's value and, says apiSave()'s
	docblock, falls back to the first non-empty one. '??' only steps aside for
	a null, and a locale file carrying the key with an empty string is not
	null (see \Nino\Text::entries()) - so a key nobody has written in the
	project's own language yet, which is what a fresh translation looks like
	until somebody gets to it, converted to global as '' and took the one
	language that did have text with it.	*/
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/template/page-home/welcome/claim', 'global' => false, 'value' => '' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/template/page-home/welcome/claim', 'locale' => 'en_US', 'value' => 'Nothing but the truth' ],
] ] );

$claimLocales = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the native locale carries the key with an empty value, which is not the same as not carrying it',
	array_key_exists( '[[/template/page-home/welcome/claim]]', $claimLocales ) === true && $claimLocales['[[/template/page-home/welcome/claim]]'] === '' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => true, 'blacklisted' => false ] );
check( 'an empty native value falls back to the first locale that has one, as the docblock says', $status === 200
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/template/page-home/welcome/claim]]'] ?? null ) === 'Nothing but the truth' );

callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => false, 'blacklisted' => false ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/template/page-home/welcome/claim', 'locale' => 'de_DE', 'value' => 'Nichts als die Wahrheit' ],
] ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => true, 'blacklisted' => false ] );
check( '...while a native locale that does have a value still wins over every other one',
	( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/template/page-home/welcome/claim]]'] ?? null ) === 'Nichts als die Wahrheit' );

// Empty in every language is the one case where '' really is the value: the
// key still exists, and converting it must not invent text for it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => false, 'blacklisted' => false ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => array_map(
	static fn( string $locale ): array => [ 'key' => '/template/page-home/welcome/claim', 'locale' => $locale, 'value' => '' ],
	\Nino\Locales::getAvailableLocales( $appData )
) ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-home/welcome/claim', 'global' => true, 'blacklisted' => false ] );
$claimEmpty = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'a key that is empty everywhere converts to an empty global value, not to no key at all',
	array_key_exists( '[[/template/page-home/welcome/claim]]', $claimEmpty ) === true && $claimEmpty['[[/template/page-home/welcome/claim]]'] === '' );

callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/template/page-home/welcome/claim' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/does/not/exist', 'global' => true, 'blacklisted' => false ] );
check( 'apiSave 404s for an unknown key', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
	[ 'key' => '/template/page-home/welcome/subtitle', 'locale' => 'de_DE', 'value' => 'Neuer Start' ],
] ] );
check( 'apiSaveBatch succeeds', $status === 200 );
check( 'apiSaveBatch saves a global value', $body['results']['/project/company/general/tagline']['ok'] === true );
check( 'apiSaveBatch saves a blacklisted key\'s value too - blacklist only hides it from _editor', $body['results']['/template/page-home/welcome/subtitle']['ok'] === true );

$globalAfterBatch = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$deDEAfterBatch 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the global value actually landed in global.php', $globalAfterBatch['[[/project/company/general/tagline]]'] === 'Immer und ewig' );
check( 'the per-locale value actually landed in the right locale file', $deDEAfterBatch['[[/template/page-home/welcome/subtitle]]'] === 'Neuer Start' );

// html is auto-detected from a key's *current* value (see _entries()) - create one
// that already holds a whitelisted tag, so this save actually exercises sanitizeHtml()
// rather than the plain strip_tags() path a fresh/plain-text key would get
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/company/general/note', 'global' => true, 'value' => '<strong>Wichtig</strong>' ] );
check( 'apiCreate creates the html-flagged fixture key', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/note', 'locale' => '*', 'value' => '<script>alert(1)</script><strong>Wichtig</strong><em>auch</em><code>x()</code>' ],
] ] );
check( 'apiSaveBatch sanitizes html and preserves inline code', $body['results']['/project/company/general/note']['value'] === '<strong>Wichtig</strong><em>auch</em><code>x()</code>' );

// An unbalanced closing tag is what pasting from a web page looks like, and
// the sanitizer parsed the value inside a <div> of its own - so the first
// stray </div> closed that wrapper and everything after it was read as
// standing outside the value and dropped, silently, on save
check( 'a stray closing div does not cut the rest of the value off',
	\Nino\Html::sanitizeHtml( 'pasted <strong>one</strong></div> and the rest' ) === 'pasted <strong>one</strong> and the rest' );
check( '...wherever it stands', \Nino\Html::sanitizeHtml( 'a</div>b <em>c</em>' ) === 'ab <em>c</em>' );
check( 'a balanced block still contributes its text', \Nino\Html::sanitizeHtml( 'before <div>inside</div> after' ) === 'before inside after' );

// A value a developer wrote as something other than a string - an int year,
// a list - used to reach strlen() under strict_types, which is a TypeError,
// which the error handler answers with a 500: the Text panel, the Keys panel
// and the Language panel all stopped opening until somebody found the line
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) + [
	'[[/company/founded]]'	=> 2024,
	'[[/project/company/general/list]]'			=> [ 'a', 'b' ],
] );
unset( $appData['./nino/filesystem/cache'] );

$oddEntries = \Nino\Text::entries( $appData, true );
$oddKeys		= array_column( $oddEntries, 'key' );
check( 'a text value that is not a string does not stop the panel opening', in_array( '/company/founded', $oddKeys, true ) === true );
check( '...and a number is shown as the text it stands for', ( $oddEntries[ array_search( '/company/founded', $oddKeys, true ) ]['values']['*'] ?? null ) === '2024' );
check( '...while a value that is no text at all is left out rather than rendered as one', in_array( '/project/company/general/list', $oddKeys, true ) === false );

// A plain-text value is substituted raw by Html::_renderFills(), attribute
// values included ('<meta name="author" content="[[/project/website/general/author]]">'), so
// a stored quote is an attribute break-out that strip_tags() never sees.
// Entities render as the character itself in both contexts, and re-encode to
// themselves on a re-save
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'x" onmouseover="alert(1)' ],
] ] );
check( 'apiSaveBatch entity-encodes quotes in a plain-text value', $body['results']['/project/company/general/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'x&quot; onmouseover=&quot;alert(1)' ],
] ] );
check( '...and saving that value again does not escape it a second time', $body['results']['/project/company/general/tagline']['value'] === 'x&quot; onmouseover=&quot;alert(1)' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/project/company/general/tagline', 'locale' => '*', 'value' => 'Immer und ewig' ],
] ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/does/not/exist', 'locale' => '*', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch reports an unknown key without failing the whole request', $status === 200 && $body['results']['/does/not/exist']['ok'] === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [
	[ 'key' => '/template/page-home/welcome/subtitle', 'locale' => 'xx_XX', 'value' => 'x' ],
] ] );
check( 'apiSaveBatch rejects an invalid locale for a per-locale key', $body['results']['/template/page-home/welcome/subtitle']['ok'] === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/tagline', 'newKey' => '/project/company/general/motto' ] );
check( 'apiRename succeeds for a global key', $status === 200 );

$globalAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the old key is gone from global.php', isset( $globalAfterRename['[[/project/company/general/tagline]]'] ) === false );
check( 'the value moved to the new key', $globalAfterRename['[[/project/company/general/motto]]'] === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/motto', 'newKey' => 'not-a-valid-key' ] );
check( 'apiRename rejects an invalid new key', $status === 400 );

// Renaming holds the new name to the grammar as well, and leaves a key of the
// system or the workbench where it is - its name is what the code that reads it asks for
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/webpage/legacy/name]]'] = 'Legacy';
	$global['[[/old/free/form]]'] = 'Frei';
	$global['[[/_admin/legacy/word/name]]'] = 'Wort';
	return $global;
} );
foreach( [ '/foo/bar', '/template/page-home/title', '/_nino/webpage/x/title', '/_admin/x/y/z' ] as $badKey ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/old/free/form', 'newKey' => $badKey ] );
	check( "apiRename refuses $badKey as the new name", $status === 400 && ( $body['code'] ?? '' ) === 'keys_invalid' && ( $body['field'] ?? '' ) === 'newKey' );
}
check( '...and the key is where it was', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/old/free/form]]'] ?? null ) === 'Frei' );
foreach( [ '/_nino/webpage/legacy/name', '/_admin/legacy/word/name' ] as $systemKey ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => $systemKey, 'newKey' => '/project/legacy/page/name' ] );
	check( "apiRename refuses to rename $systemKey, which is not a project's to name", $status === 400 && ( $body['code'] ?? '' ) === 'keys_system' && ( $body['params'] ?? [] ) === [ $systemKey ] );
}
check( '...and the value of a system key stays', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/legacy/name]]'] ?? null ) === 'Legacy' );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/_nino/webpage/legacy/name', 'global' => true, 'blacklisted' => false ] );
check( 'a key that does not follow the grammar stays saveable - what is there is not touched', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/old/free/form', 'global' => true, 'blacklisted' => true ] );
check( '...and can be hidden', $status === 200 && in_array( '/old/free/form', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true );
\Nino\Text::setBlacklisted( $appData, '/old/free/form', false );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/old/free/form', 'newKey' => '/project/old/free/form' ] );
check( '...and renamed to one that follows the grammar', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/old/free/form]]'] ?? null ) === 'Frei'
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/old/free/form]]'] ) === false );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/old/free/form]]'] = 'Frei';
	return $global;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/old/free/form' ] );
check( '...and deleted', $status === 200 && isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/old/free/form]]'] ) === false );
foreach( [ '/project/old/free/form', '/_nino/webpage/legacy/name', '/_admin/legacy/word/name' ] as $cleanKey )
	callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => $cleanKey ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/motto', 'newKey' => '/project/company/general/note' ] );
check( 'apiRename rejects a new key that already exists', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/does/not/exist', 'newKey' => '/project/also/not/new' ] );
check( 'apiRename 404s for an unknown key', $status === 404 );

// Renaming a key to the name it already has is a no-op, not a delete. The
// mutate pair below it ("write the new bracket, unset the old one") collapses
// into a plain unset when both are the same string, so this used to answer 200
// and drop the value - text.js guards it in the ui, the endpoint has to too
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/company/general/motto', 'newKey' => '/project/company/general/motto' ] );
check( 'apiRename accepts a rename to the key\'s own name', $status === 200 );
check( '...and leaves the value where it was instead of deleting it', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/company/general/motto]]'] ?? null ) === 'Immer und ewig' );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/company/general/note' ] );
check( 'apiDelete succeeds for a global key', $status === 200 );

$globalAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'the deleted global key is gone', isset( $globalAfterDelete['[[/project/company/general/note]]'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/template/page-home/welcome/subtitle' ] );
check( 'apiDelete succeeds for a blacklisted per-locale key', $status === 200 );

$deDEAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enUSAfterDelete 	= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$blacklistAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the deleted per-locale key is gone from every locale file', isset( $deDEAfterDelete['[[/template/page-home/welcome/subtitle]]'] ) === false && isset( $enUSAfterDelete['[[/template/page-home/welcome/subtitle]]'] ) === false );
check( 'the deleted key is also gone from the blacklist', in_array( '/template/page-home/welcome/subtitle', $blacklistAfterDelete, true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/does/not/exist' ] );
check( 'apiDelete 404s for an unknown key', $status === 404 );

echo "\n";


// --- Text keys: format and limit ----------------------------------------------

echo "Text keys - format and limit, per key\n";

$keyEntry = static function( array &$appData, string $key ): ?array {
	[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
	foreach( $body['keys'] as $entry )
		if( $entry['key'] === $key )
			return $entry;
	return null;
};
$meta = static fn(): array => \Nino\Filesystem::getFileContent( $appData, \Nino\Text::META_PATH, [] );

// A key that holds paragraphs, in a global file and in both locale files
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/body', 'global' => false, 'value' => '<p>Eins</p><ul><li>a</li></ul><p>Zwei<br>drei</p>' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/note', 'global' => true, 'value' => "Zeile 1\nZeile 2" ] );

check( 'a key created with paragraphs is read as blocks, from its value', ( $keyEntry( $appData, '/project/notes/page/body' )['format'] ?? '' ) === 'blocks' && ( $keyEntry( $appData, '/project/notes/page/body' )['formatSet'] ?? true ) === false );
check( '...and a plain one is stored as it was written', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/note]]'] ?? '' ) === "Zeile 1\nZeile 2" );

// An absent format or limit is "unchanged": the two checkboxes post without them
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false ] );
check( 'a save without a format or a limit writes no meta', $status === 200 && $meta() === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'blocks', 'maxlength' => 900 ] );
check( 'a format and a limit are written to /text/meta.php, keyed by the key', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 900 ] );
$legal = $keyEntry( $appData, '/project/notes/page/body' );
check( '...and the list reports them, and that they were set', ( $legal['format'] ?? '' ) === 'blocks' && ( $legal['maxlength'] ?? 0 ) === 900 && ( $legal['formatSet'] ?? false ) === true && ( $legal['maxlengthSet'] ?? false ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => true ] );
check( 'a later save that leaves them out leaves them as they are', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 900 ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => null ] );
check( 'a limit posted as null is automatic again; the format stays', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'blocks' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'auto' ] );
check( '"auto" forgets the format, and the entry that is left with nothing is dropped', $status === 200 && array_key_exists( '/project/notes/page/body', $meta() ) === false );
check( '...without touching the values: they are paragraphs still', ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === '<p>Eins</p><ul><li>a</li></ul><p>Zwei<br>drei</p>' );

// Refusals
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'sideways' ] );
check( 'a format that does not exist is a 400 with a code', $status === 400 && ( $body['code'] ?? '' ) === 'keys_format' && ( $body['field'] ?? '' ) === 'format' );
foreach( [ 0, -3, \Nino\Text::MAX_LIMIT + 1, 'many', 2.5, [ 5 ] ] as $badLimit ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => $badLimit ] );
	check( 'a limit of '. json_encode( $badLimit ). ' is a 400 that names the range', $status === 400 && ( $body['code'] ?? '' ) === 'keys_limit' && ( $body['params'] ?? [] ) === [ \Nino\Text::MAX_LIMIT ] );
}
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => 6 ] );
check( 'a limit below the longest text the key holds is refused, with that length - the editor would cut the text at it', $status === 400 && ( $body['code'] ?? '' ) === 'keys_limit_short' && ( $body['params'] ?? [] ) === [ 13 ] );
check( '...and nothing was written', $meta() === [] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => 13 ] );
check( 'a limit that is exactly as long as the text is fine', $status === 200 && ( $meta()['/project/notes/page/body'] ?? null ) === [ 'maxlength' => 13 ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'maxlength' => null ] );

// Narrowing converts every stored value, in every locale file
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSaveBatch', [ 'items' => [ [ 'key' => '/project/notes/page/body', 'locale' => 'en_US', 'value' => '<p>One</p><ol><li>a</li><li>b</li></ol><p><strong>Two</strong></p>' ] ] ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'plain' ] );
$deConverted = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '';
$enConverted = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/project/notes/page/body]]'] ?? '';
check( 'blocks to plain keeps the words and makes lines of the paragraphs, the items and the breaks, in the first locale', $deConverted === "Eins\na\nZwei\ndrei" );
check( '...and in every other', $enConverted === "One\na\nb\nTwo" );
check( '...and the format is the one that was asked for', ( $meta()['/project/notes/page/body'] ?? null ) === [ 'format' => 'plain' ] && ( $keyEntry( $appData, '/project/notes/page/body' )['html'] ?? true ) === false );

// Widening: a newline becomes what the format makes of it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'lines' ] );
check( 'plain to lines makes a <br> of every newline, in every locale file',
	( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === 'Eins<br>a<br>Zwei<br>drei'
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === 'One<br>a<br>b<br>Two' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'blocks' ] );
check( 'lines to blocks makes one paragraph of it', ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/body]]'] ?? '' ) === '<p>Eins<br>a<br>Zwei<br>drei</p>' );

// The same for a global key, and the shape change that comes with it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/note', 'global' => true, 'blacklisted' => false, 'format' => 'lines' ] );
check( 'a global key is converted in global.php', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/note]]'] ?? '' ) === 'Zeile 1<br>Zeile 2' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/note', 'global' => false, 'blacklisted' => false, 'format' => 'plain' ] );
check( 'a key that changes its shape and its format in one save lands in the new shape, converted',
	( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/project/notes/page/note]]'] ?? '' ) === "Zeile 1\nZeile 2"
	&& array_key_exists( '[[/project/notes/page/note]]', \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ) ) === false );

// The log line says what was posted
$logged = \Nino\Modules\Text\Keys::log( 'keys/save', [ 'key' => '/project/notes/page/body', 'format' => 'lines', 'maxlength' => 400 ] );
check( 'the log names the format and the limit of a save that carries them, and nothing for one that does not', $logged === 'Edit Text Key /project/notes/page/body (format lines, limit 400)' && \Nino\Modules\Text\Keys::log( 'keys/save', [ 'key' => '/project/notes/page/body', 'global' => true ] ) === '' );

// Rename: both branches move the meta; delete drops it
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/project/notes/page/body', 'global' => false, 'blacklisted' => false, 'format' => 'blocks', 'maxlength' => 700 ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/notes/page/body', 'newKey' => '/project/notes/page/text' ] );
check( 'a rename takes the format and the limit to the new name and leaves nothing behind', ( $meta()['/project/notes/page/text'] ?? null ) === [ 'format' => 'blocks', 'maxlength' => 700 ] && array_key_exists( '/project/notes/page/body', $meta() ) === false );

\Nino\Text::setBlacklisted( $appData, '/project/notes/page/retired', true );
\Nino\Text::setMeta( $appData, '/project/notes/page/retired', 'lines', null );
check( 'a retired key, which has no value anywhere, lists with the format it was given', ( $keyEntry( $appData, '/project/notes/page/retired' )['format'] ?? '' ) === 'lines' );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/project/notes/page/retired', 'newKey' => '/project/notes/page/gone' ] );
check( 'a rename of a retired key moves the meta too', ( $meta()['/project/notes/page/gone'] ?? null ) === [ 'format' => 'lines' ] && array_key_exists( '/project/notes/page/retired', $meta() ) === false );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/gone' ] );
check( 'deleting a retired key drops its meta', array_key_exists( '/project/notes/page/gone', $meta() ) === false );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/text' ] );
check( 'deleting a key drops its meta', array_key_exists( '/project/notes/page/text', $meta() ) === false );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/note' ] );

// Creating: through the format, like any value saved from the workbench
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/made', 'global' => true, 'value' => "a\nb<script>x</script>", 'format' => 'lines' ] );
check( 'apiCreate sanitizes the initial value with the format that was asked for, and remembers the choice', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/made]]'] ?? '' ) === 'a<br>b' && ( $meta()['/project/notes/page/made'] ?? null ) === [ 'format' => 'lines' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/read', 'global' => true, 'value' => '<em>x</em><script>y</script> [template /templates/mail-owner]' ] );
check( '...and without one reads it from the value - shortcodes and scripts are not stored, the choice is not remembered', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/project/notes/page/read]]'] ?? '' ) === '<em>x</em> &#91;template /templates/mail-owner&#93;' && array_key_exists( '/project/notes/page/read', $meta() ) === false );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiCreate', [ 'key' => '/project/notes/page/never', 'global' => true, 'value' => 'x', 'format' => 'sideways' ] );
check( 'apiCreate refuses a format that does not exist, and creates nothing', $status === 400 && ( $body['code'] ?? '' ) === 'keys_format' && \Nino\Text::entry( $appData, '/project/notes/page/never' ) === null );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/made' ] );
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/project/notes/page/read' ] );
check( 'both are cleaned up again', $meta() === [] );

echo "\n";


// --- The vocabulary: the words that name a part of a key -----------------------------

echo "Text - the closed vocabulary of the words /_admin/common/word/*\n";

$wordFills = [];
foreach( [ 'en_US', 'de_DE' ] as $vocabLocale ) {
	$wordFills[$vocabLocale] = [];
	foreach( include __DIR__. '/../_admin/text/'. $vocabLocale. '.php' as $bracketKey => $wordText )
		if( str_starts_with( $bracketKey, '[[/_admin/common/word/' ) === true )
			$wordFills[$vocabLocale][substr( $bracketKey, strlen( '[[/_admin/common/word/' ), -2 )] = $wordText;
}
$words = array_keys( $wordFills['en_US'] );
check( 'a word is a slug: lower-case words joined by hyphens, one for each slug, in the alphabet', array_filter( $words, static fn( string $slug ): bool => preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) !== 1 ) === [] && $words === array_values( array_unique( $words ) ) && $words === ( static function( array $sorted ): array { sort( $sorted ); return $sorted; } )( $words ) );
check( 'no word is an alias of another: no two slugs share a text in a language', count( array_unique( $wordFills['en_US'] ) ) === count( $words ) && count( array_unique( $wordFills['de_DE'] ) ) === count( $words ) );
check( 'a word names no key and is no key: no fill, no slash in any of them', array_filter( array_merge( $wordFills['en_US'], $wordFills['de_DE'] ), static fn( string $text ): bool => preg_match( '/\[\[|\//', $text ) === 1 ) === [] );

// Every segment of every key the kernel ships is a word, or falls apart into words: a part, a name, the entry of a list,
// a category that names a group. Excepted are the categories of the page templates with a route - the row of a page is
// named after its route - the keys of a feature, whose row is named in its manifest, and the two parts of an image slot a
// page's template shares with its text keys
$slugCovered = static function( string $slug ) use ( $words, &$slugCovered ): bool {
	if( in_array( $slug, $words, true ) === true )
		return true;
	if( preg_match( '/^(.+)-\d+$/', $slug, $numbered ) === 1 )
		return $slugCovered( $numbered[1] );
	for( $dash = strpos( $slug, '-' ); $dash !== false; $dash = strpos( $slug, '-', $dash + 1 ) )
		if( $dash > 0 && in_array( substr( $slug, $dash + 1 ), $words, true ) === true && in_array( substr( $slug, 0, $dash ), $words, true ) === true )
			return true;
	return false;
};
$shippedKeys = [];
foreach( array_merge( glob( dirname( __DIR__ ). '/_admin/install/library/*/text/*.php' ) ?: [], glob( dirname( __DIR__ ). '/_admin/install/library/pages/*/text/*.php' ) ?: [], glob( dirname( __DIR__ ). '/_admin/install/library/pages/.*/text/*.php' ) ?: [], glob( dirname( __DIR__ ). '/_nino/Nino/Modules/*/install/text/*.php' ) ?: [] ) as $fragment )
	foreach( array_keys( (array) include $fragment ) as $bracketKey )
		$shippedKeys[trim( $bracketKey, '[]' )] = true;
$uncovered = [];
$counted = 0;
foreach( array_keys( $shippedKeys ) as $shippedKey ) {
	if( preg_match( '#^/(template|project|feature|module)/([^/]+)/([^/]+)/([^/]+)$#', $shippedKey, $segments ) !== 1 )
		continue;
	$counted++;
	foreach( [ 'category' => $segments[2], 'part' => $segments[3], 'name' => $segments[4] ] as $role => $slug ) {
		if( $role === 'category' && ( $segments[1] === 'feature' || ( $segments[1] === 'template' && str_starts_with( $slug, 'page-' ) === true ) ) )
			continue;
		if( in_array( $slug, [ 'contact-form', 'fullscreen-image' ], true ) === true )
			continue;
		if( $slugCovered( $slug ) === false )
			$uncovered[$role. ' '. $slug] = $shippedKey;
	}
}
check( 'every part, name and group of the '. $counted. ' keys the kernel ships is a word of the vocabulary or falls apart into words'. ( $uncovered === [] ? '' : ' - '. implode( '; ', array_slice( array_keys( $uncovered ), 0, 8 ) ) ), $uncovered === [] && $counted > 50 );

// --- Text::apiScan / Images::apiScan: template scanners ---------------------

echo "Text::apiScan - missing [[/key]] placeholders in templates/*.tpl\n";

mkdir( $sandbox. '/private/templates', 0777, true );
file_put_contents( $sandbox. '/private/templates/scan-fixture.tpl', '<p>[[/template/page-scan-test/intro/heading]]</p><p>[[/template/page-scan-test/intro/heading]]</p><p>[[/project/company/general/name]]</p>' );
// A nested, dynamically-constructed fill (same shape as html-header.tpl's real
// [[/_nino/webpage[[/nino/http/response/uri]]/title]]) - only the inner, always-
// registered kernel fill should ever be visible to a static regex scan
file_put_contents( $sandbox. '/private/templates/scan-fixture-2.tpl', '<title>[[/_nino/webpage[[/nino/http/response/uri]]/title]]</title><img src="[[/nino/public]]/images/example.jpg">' );
// Every fill the kernel registers at runtime, as the kernel names them - the
// scan used to keep its own copy of that list, and the copy was one short:
// a template with the clean uri made the Dashboard report a missing key
$runtimeFillKeys = method_exists( '\\Nino\\Html', 'runtimeFillKeys' ) === true ? \Nino\Html::runtimeFillKeys( $appData ) : [];
file_put_contents( $sandbox. '/private/templates/scan-fixture-3.tpl', '<body class="p-[[/nino/http/response/uri/clean]]">'. implode( ' ', array_map( fn( string $key ): string => '[['. $key. ']]', $runtimeFillKeys ) ). '</body>' );

// /project/company/general/name is genuinely defined (unlike /template/page-scan-test/intro/heading) - proves
// a real key is correctly excluded, not just absent from the fixture by accident
$globalFixture = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
$globalFixture['[[/project/company/general/name]]'] = 'Acme';
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', $globalFixture );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );

$missingKeys = array_column( $body['missing'], 'key' );
check( 'a genuinely undefined key is reported', in_array( '/template/page-scan-test/intro/heading', $missingKeys, true ) === true );
check( 'an undefined key found twice in the same file is only reported once', count( array_filter( $missingKeys, fn( $k ) => $k === '/template/page-scan-test/intro/heading' ) ) === 1 );
check( 'an already-defined key is not reported', in_array( '/project/company/general/name', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/http/response/uri fill is never reported, despite appearing inside a nested [[...]] construct', in_array( '/nino/http/response/uri', $missingKeys, true ) === false );
check( 'the kernel-injected /nino/public fill is never reported as missing', in_array( '/nino/public', $missingKeys, true ) === false );
check( 'the clean uri the kernel fills at runtime is never reported as missing', in_array( '/nino/http/response/uri/clean', $missingKeys, true ) === false );
$reportedRuntimeFills = array_values( array_intersect( $runtimeFillKeys, $missingKeys ) );
check( 'no fill the kernel names as one it registers at runtime is reported - the scan reads that list, it keeps no copy'. ( $reportedRuntimeFills === [] ? '' : ' - reported: '. implode( ', ', $reportedRuntimeFills ) ), count( $runtimeFillKeys ) >= 8 && $reportedRuntimeFills === [] );

// A directory with the name of a template is no template: the scan skips it, where the read of it answered a warning and a 500
mkdir( $sandbox. '/private/templates/scan-dir.tpl' );
ninoWarnings();
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
$dirWarnings = ninoWarnings();
check( 'a directory called x.tpl among the templates is no 500 for the key scan, raises no warning, and the real ones are still read', $status === 200 && $dirWarnings === [] && in_array( '/template/page-scan-test/intro/heading', array_column( $body['missing'], 'key' ), true ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiScan' );
$dirWarnings = ninoWarnings();
check( '...nor for the Images panel\'s scan of them', $status === 200 && $dirWarnings === [] );
[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiList' );
$dirWarnings = ninoWarnings();
check( '...nor for the list of the slots and where they are used', $status === 200 && $dirWarnings === [] );
rmdir( $sandbox. '/private/templates/scan-dir.tpl' );

unlink( $sandbox. '/private/templates/scan-fixture.tpl' );
unlink( $sandbox. '/private/templates/scan-fixture-2.tpl' );
unlink( $sandbox. '/private/templates/scan-fixture-3.tpl' );

// --- what the scan says about each key, and what it leaves alone ----------
//
// Every key a template reads and nothing defines is a row, whether it can be
// created here or not: three kinds - 'create' (it follows the grammar),
// 'system' (/_nino, written by a panel) and 'grammar' (anything else). A
// placeholder with no leading slash is the template's own shortcode's and no key.
\Nino\Filesystem::putFileContent( $appData, '/templates/scan-kinds.tpl', implode( "\n", [
	'[[/template/scan-kinds/intro/title]]',
	'[[/feature/consent/banner/title]]',
	'[[/module/form/info/scan-extra]]',
	'[[/foo/bar]]',
	'[[/_admin/scan/word/name]]',
	'[[/_nino/webpage/contact/uri]]',
	'[[/_nino/webpage/about/team/title]]',
	'[[/_nino/locale/fr_CA/name]]',
	'[[/_nino/webpage/.blog/uri]]',
	'[[/_nino/webpage/.blog/name]]',
	'[[/_nino/something/else/here]]',
	'[[name]] [[email]] [[message]] [[date]] [[subject]] [[.rel]] [[category]] [[section:id]]',
	'[[/feature/consent/category-[[category]]/name]]',
] ) );
// A feature's page, routed at runtime: its Element-URI is /.blog
$appData['/nino/http/routes']['GET://blog/*'] = [ 'uri' => '/.blog', 'body' => '[template /templates/page-posts]' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
$kinds = array_column( $body['missing'], null, 'key' );
check( 'the scan reports a key that follows the grammar as one to create', $status === 200 && ( $kinds['/template/scan-kinds/intro/title']['kind'] ?? '' ) === 'create' && ( $kinds['/template/scan-kinds/intro/title']['files'] ?? [] ) === [ 'scan-kinds.tpl' ] );
check( '...a /feature key with the note where it normally comes from - and still to create', ( $kinds['/feature/consent/banner/title']['kind'] ?? '' ) === 'create' && ( $kinds['/feature/consent/banner/title']['hint'] ?? '' ) === 'feature' && ( $kinds['/feature/consent/banner/title']['owner'] ?? '' ) === 'consent' );
check( '...a /module key the same', ( $kinds['/module/form/info/scan-extra']['hint'] ?? '' ) === 'module' && ( $kinds['/module/form/info/scan-extra']['owner'] ?? '' ) === 'form' );
check( 'a key off the grammar is a "grammar" row, a key of the workbench too', ( $kinds['/foo/bar']['kind'] ?? '' ) === 'grammar' && ( $kinds['/_admin/scan/word/name']['kind'] ?? '' ) === 'grammar' && array_key_exists( 'hint', $kinds['/foo/bar'] ) === true && $kinds['/foo/bar']['hint'] === null );
check( 'a page\'s key is a "system" row whose writer is the Routes panel, however many slashes its Element-URI has', ( $kinds['/_nino/webpage/contact/uri']['kind'] ?? '' ) === 'system' && ( $kinds['/_nino/webpage/contact/uri']['writer'] ?? '' ) === 'routes'
	&& ( $kinds['/_nino/webpage/about/team/title']['writer'] ?? '' ) === 'routes' );
check( '...a language\'s name one whose writer is the Language panel', ( $kinds['/_nino/locale/fr_CA/name']['kind'] ?? '' ) === 'system' && ( $kinds['/_nino/locale/fr_CA/name']['writer'] ?? '' ) === 'language' );
check( '...the name of a feature\'s page one the Routes panel writes, but not its path, which the feature decides', ( $kinds['/_nino/webpage/.blog/name']['writer'] ?? '' ) === 'routes' && ( $kinds['/_nino/webpage/.blog/uri']['kind'] ?? '' ) === 'system' && array_key_exists( 'writer', $kinds['/_nino/webpage/.blog/uri'] ) === true && $kinds['/_nino/webpage/.blog/uri']['writer'] === null );
check( '...and another form of the system one nobody writes', ( $kinds['/_nino/something/else/here']['kind'] ?? '' ) === 'system' && array_key_exists( 'writer', $kinds['/_nino/something/else/here'] ) === true && $kinds['/_nino/something/else/here']['writer'] === null );
foreach( [ 'name', 'email', 'message', 'date', 'subject', '.rel', 'category', 'section:id', 'category-[[category' ] as $placeholder )
	check( "a placeholder of the template's own - [[$placeholder]] - is no key and is not reported", isset( $kinds[$placeholder] ) === false && isset( $kinds['/'. $placeholder] ) === false );
check( '...nor is the outer half of a key built from one', array_filter( array_keys( $kinds ), static fn( string $key ): bool => str_contains( $key, 'category-' ) ) === [] );
check( 'the Dashboard counts every row, the ones that cannot be created here as well', \Nino\Modules\Text\Keys::missingCount( $appData ) === count( $body['missing'] ) && count( $body['missing'] ) === 11 );

// What the apply does with each kind
[ $status, $applied ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [
	[ 'key' => '/template/scan-kinds/intro/title', 'value' => 'Titel', 'ignore' => false ],
	[ 'key' => '/foo/bar', 'value' => 'Nein', 'ignore' => false ],
	[ 'key' => '/_admin/scan/word/name', 'value' => '', 'ignore' => true ],
	[ 'key' => '/_nino/webpage/contact/uri', 'value' => '/kontakt', 'ignore' => false ],
	[ 'key' => '/_nino/locale/fr_CA/name', 'value' => '', 'ignore' => true ],
] ] );
$deScan = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$blacklistScan = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'apiScanApply creates only the keys that may be created here', $status === 200 && ( $deScan['[[/template/scan-kinds/intro/title]]'] ?? null ) === 'Titel' && isset( $deScan['[[/foo/bar]]'] ) === false
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/contact/uri]]'] ) === false );
check( '...retires a key off the grammar when asked, and leaves a key of the system out of the blacklist', in_array( '/_admin/scan/word/name', $blacklistScan, true ) === true && in_array( '/_nino/locale/fr_CA/name', $blacklistScan, true ) === false );
check( '...and counts what it did', ( $applied['created'] ?? null ) === 1 && ( $applied['ignored'] ?? null ) === 1 && ( $applied['skipped'] ?? null ) === 1 );
foreach( [ '/template/scan-kinds/intro/title' ] as $cleanKey )
	callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => $cleanKey ] );
\Nino\Text::setBlacklisted( $appData, '/_admin/scan/word/name', false );
unlink( $sandbox. '/private/templates/scan-kinds.tpl' );
unset( $appData['/nino/http/routes']['GET://blog/*'] );

// --- the note "also read by" -----------------------------------------------
//
// A word that one template carries and another reads as well is named, with
// the files - once, as a note: nothing is counted, offered or moved. By the
// rule a word several templates read lives in /template/common, which holds
// from the day the key is created and no day after
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/template/page-alpha/intro/title]]'] = 'Alpha';
	$global['[[/template/common/form/submit]]'] = 'Senden';
	return $global;
} );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-alpha.tpl', '<h1>[[/template/page-alpha/intro/title]]</h1><button>[[/template/common/form/submit]]</button>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-beta.tpl', '<h1>[[/template/page-alpha/intro/title]]</h1><button>[[/template/common/form/submit]]</button>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-legal.de_DE.tpl', '<h1>[[/template/page-alpha/intro/title]]</h1>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-gamma.tpl', '<h1>[[/project/company/general/name]]</h1>' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( 'apiScan names a key of one template that another reads as well, with the files that do', $status === 200 && ( $body['alsoUsed'] ?? [] ) === [ [ 'key' => '/template/page-alpha/intro/title', 'files' => [ 'page-beta.tpl', 'page-legal.de_DE.tpl' ] ] ] );
check( '...but not the template\'s own reading, a word of /template/common, or a key of another namespace', array_column( $body['alsoUsed'], 'key' ) === [ '/template/page-alpha/intro/title' ] );
check( '...and it is no row: the note changes no count', $body['missing'] === [] && \Nino\Modules\Text\Keys::missingCount( $appData ) === 0 );
foreach( [ 'page-alpha', 'page-beta', 'page-gamma', 'page-legal.de_DE' ] as $scratchTemplate )
	unlink( $sandbox. '/private/templates/'. $scratchTemplate. '.tpl' );
foreach( [ '/template/page-alpha/intro/title', '/template/common/form/submit' ] as $cleanKey )
	callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => $cleanKey ] );

// --- apiScanApply: the three answers a scanned key can get -----------------
//
// The scan is a list to work through, not an all-or-nothing pass: a row with
// a value becomes a key, a row left empty comes back next time, and a row
// ticked "ignore" is retired for good. All three in one request, so a long
// list can be answered in sittings.

file_put_contents( $sandbox. '/private/templates/scan-apply-fixture.tpl',
	'<h1>[[/template/page-apply/intro/heading]]</h1><p>[[/template/page-apply/intro/subtitle]]</p><small>[[/template/page-apply/intro/legal]]</small>' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [
	[ 'key' => '/template/page-apply/intro/heading', 	'value' => '  Willkommen  ', 	'ignore' => false ],
	[ 'key' => '/template/page-apply/intro/subtitle', 'value' => '', 							'ignore' => false ],
	[ 'key' => '/template/page-apply/intro/legal', 		'value' => '', 							'ignore' => true 	],
	// Real and defined, so not part of this scan at all - the form's rows come
	// from apiScan() and nowhere else, and this endpoint is not a way to
	// blacklist a key the screen never offered
	[ 'key' => '/project/company/general/name', 				'value' => '', 							'ignore' => true 	],
] ] );
check( 'apiScanApply succeeds', $status === 200 );
check( 'a row with a value is counted as created', ( $body['created'] ?? null ) === 1 );
check( 'a row ticked "ignore" is counted as ignored', ( $body['ignored'] ?? null ) === 1 );
check( 'a row left empty is counted as left for later', ( $body['skipped'] ?? null ) === 1 );

$deApply = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enApply = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'the created key holds its starting value in every language', ( $deApply['[[/template/page-apply/intro/heading]]'] ?? null ) === 'Willkommen' && ( $enApply['[[/template/page-apply/intro/heading]]'] ?? null ) === 'Willkommen' );
// A field holding nothing but spaces is the same "not decided yet" an empty
// one is, and a key created from it would look filled in without being it
check( 'the starting value is trimmed rather than stored as typed', str_contains( (string) ( $deApply['[[/template/page-apply/intro/heading]]'] ?? '' ), ' ' ) === false );
check( 'a row left empty writes nothing at all', isset( $deApply['[[/template/page-apply/intro/subtitle]]'] ) === false && isset( $enApply['[[/template/page-apply/intro/subtitle]]'] ) === false );

$blacklistAfterApply = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'the retired key is in the blacklist', in_array( '/template/page-apply/intro/legal', $blacklistAfterApply, true ) === true );
check( '...and a key this scan never offered is not, whatever the request said', in_array( '/project/company/general/name', $blacklistAfterApply, true ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
$missingAfterApply = array_column( $body['missing'], 'key' );
check( 'the created key is no longer missing', in_array( '/template/page-apply/intro/heading', $missingAfterApply, true ) === false );
// The whole point of "permanently": before this, an ignored key was simply
// skipped client-side and came straight back on the next scan
check( 'the retired key is no longer asked about', in_array( '/template/page-apply/intro/legal', $missingAfterApply, true ) === false );
check( 'the key left for later comes back', in_array( '/template/page-apply/intro/subtitle', $missingAfterApply, true ) === true );

// Retiring has to be reversible, or "permanently" is a one-way door with the
// filesystem as its only way out. The key has no value anywhere, so
// \Nino\Text::entries() cannot see it - the Text Keys list merges it back in
[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiList' );
$listedKeys = array_column( $body['keys'], 'key' );
check( 'a retired key with no value is still listed by the Text Keys tab', in_array( '/template/page-apply/intro/legal', $listedKeys, true ) === true );
check( '...flagged as hidden, so the checkbox that brings it back is ticked', ( array_column( $body['keys'], 'blacklisted', 'key' )['/template/page-apply/intro/legal'] ?? null ) === true );
check( '...with the limit \Nino\Text gives a key that holds nothing, so bringing it back does not change its counter', ( array_column( $body['keys'], 'maxlength', 'key' )['/template/page-apply/intro/legal'] ?? null ) === \Nino\Text::maxlength() );

[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiSave', [ 'key' => '/template/page-apply/intro/legal', 'global' => false, 'blacklisted' => false ] );
check( 'un-ticking "hidden" on it is a valid save, not a 404', $status === 200 );

[ , $body ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScan' );
check( '...and the scan offers it again afterwards', in_array( '/template/page-apply/intro/legal', array_column( $body['missing'], 'key' ), true ) === true );

// Same key, deleted rather than un-ignored: there is nothing but the
// blacklist line to remove, and removing it is the whole deletion
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [ [ 'key' => '/template/page-apply/intro/legal', 'value' => '', 'ignore' => true ] ] ] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiDelete', [ 'key' => '/template/page-apply/intro/legal' ] );
check( 'a retired key can be deleted outright too', $status === 200 );
check( '...which only removes its blacklist line', in_array( '/template/page-apply/intro/legal', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === false );

// Renaming one must move the blacklist line, not write the empty key into
// every locale file - which is what the value-carrying path below it would do
callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiScanApply', [ 'rows' => [ [ 'key' => '/template/page-apply/intro/legal', 'value' => '', 'ignore' => true ] ] ] );
[ $status ] = callDev( $appData, \Nino\Modules\Text\Keys::class, 'apiRename', [ 'key' => '/template/page-apply/intro/legal', 'newKey' => '/template/page-apply/intro/imprint' ] );
$blacklistAfterRename = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] );
check( 'renaming a retired key moves its blacklist line', $status === 200 && in_array( '/template/page-apply/intro/imprint', $blacklistAfterRename, true ) === true && in_array( '/template/page-apply/intro/legal', $blacklistAfterRename, true ) === false );
check( '...without creating the empty key in any locale file', isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/template/page-apply/intro/imprint]]'] ) === false );

// The Dashboard tile counts the same scan (see missingCount()), so a retired
// key has to leave the tile along with the list
\Nino\Text::setBlacklisted( $appData, '/template/page-apply/intro/imprint', false );
$countBeforeRetiring = \Nino\Modules\Text\Keys::missingCount( $appData );
\Nino\Text::setBlacklisted( $appData, '/template/page-apply/intro/legal', true );
check( 'retiring a key takes it out of the Dashboard count too', \Nino\Modules\Text\Keys::missingCount( $appData ) === $countBeforeRetiring - 1 );
\Nino\Text::setBlacklisted( $appData, '/template/page-apply/intro/legal', false );

unlink( $sandbox. '/private/templates/scan-apply-fixture.tpl' );

echo "\n";


echo "Images::apiScan - hardcoded <img> tags in templates/*.tpl, not backed by a slot\n";

// A real, tiny (3x2px) PNG so getimagesize() has something genuine to probe
$probeImg = imagecreatetruecolor( 3, 2 );
ob_start();
imagepng( $probeImg );
\Nino\Filesystem::forceDir( $appData, '/images' );
file_put_contents( \Nino\Filesystem::path( $appData, '/images/probe.png' ), ob_get_clean() );
imagedestroy( $probeImg );

file_put_contents( $sandbox. '/private/templates/scan-fixture-img.tpl', '<img src="[[/nino/public]]/images/probe.png"><img src="[[/nino/public]]/images/probe.png"><img src="https://example.com/x.jpg"><img src="data:image/svg+xml,%3Csvg/%3E">' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiScan' );
check( 'apiScan succeeds', $status === 200 );
check( 'exactly one missing image is reported (deduped, external/data-uri skipped)', count( $body['missing'] ) === 1 );
check( 'the reported filename is the local one, relative to /images/', ( $body['missing'][0]['filename'] ?? null ) === 'probe.png' );
check( 'width/height were probed from the real file since the <img> tag had none', ( $body['missing'][0]['width'] ?? null ) === 3 && ( $body['missing'][0]['height'] ?? null ) === 2 );
check( 'a suggested uri was derived from the filename', ( $body['missing'][0]['suggestedUri'] ?? null ) === '/probe' );

[ $status ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiCreate', [ 'uri' => '/probe', 'label' => 'Probe', 'width' => '3', 'height' => '2' ] );
check( 'the suggested slot can actually be created', $status === 200 );

// Once a slot's filename actually tracks it (the manual step _editor's upload
// flow would do next), the same <img> must disappear from the scan
$appData['/nino/html/images']['/probe']['filename'] = 'probe.png';
[ $status, $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiScan' );
check( 'once a slot tracks the file, it no longer shows up as missing', count( $body['missing'] ) === 0 );

unlink( $sandbox. '/private/templates/scan-fixture-img.tpl' );

// --- Slots::usage - where a slot is used -------------------------------------
//
// The pages whose templates show a slot, through [template] includes however
// deep, and the templates that name it - or nothing, which is what the Images
// panel warns about: an image uploaded to a slot no page shows never appears

$usageTemplates = [
	'usage-page' 	=> '<section>[image /use/hero alt=""]</section>[template /templates/usage-part][image uri="/use/viauri"]',
	'usage-part' 	=> '<p>[image /use/inner alt="[[/use/alt]]"]</p>[template /templates/usage-deeper]',
	'usage-deeper' => '[image /use/deep]',
	'usage-orphan' => '[image /use/orphan]',
	'usage-loop' 	=> '[image /use/loop][template /templates/usage-loop][template /templates/usage-loop2]',
	'usage-loop2' => '[template /templates/usage-loop]',
	'usage-de_DE' => '[image /use/german]',
	'usage-en_US' => '[image /use/english]',
];
foreach( $usageTemplates as $usageName => $usageSource )
	file_put_contents( $sandbox. '/private/templates/'. $usageName. '.tpl', $usageSource );

$usageRoutesBefore = $appData['/nino/http/routes'] ?? [];
$appData['/nino/http/routes']['GET://usage'] 	= [ 'uri' => '/usage-page', 'body' => '[template /templates/usage-page]' ];
$appData['/nino/http/routes']['GET://looped'] = [ 'uri' => '/looped', 'body' => '[template /templates/usage-loop]' ];
$appData['/nino/http/routes']['GET://lang'] 	= [ 'uri' => '/lang', 'body' => '[template /templates/usage-[[/nino/http/response/locale]]]' ];
$appData['/nino/http/routes']['POST://usage'] = [ 'uri' => '/usage-post', 'body' => '[image /use/post]' ];

$usageText = \Nino\Filesystem::getFileContent( $appData, '/text/'. \Nino\Admin\Admin::sessionLocale( $appData ). '.php', [] );
$usageText['[[/_nino/webpage/usage-page/name]]'] = 'Nutzung';
\Nino\Filesystem::putFileContent( $appData, '/text/'. \Nino\Admin\Admin::sessionLocale( $appData ). '.php', $usageText );

foreach( [ 'hero', 'viauri', 'inner', 'deep', 'orphan', 'loop', 'german', 'english', 'post', 'unused' ] as $usageSlot )
	$appData['/nino/html/images']['/use/'. $usageSlot] = [ 'label' => 'Use '. $usageSlot, 'width' => 10, 'height' => 10, 'filename' => null ];

$usage = \Nino\Modules\Images\Slots::usage( $appData );
$usagePage = [ 'httpUri' => '/usage', 'name' => 'Nutzung' ];
check( 'a slot in the template a route renders: the template, and the page by its name and http uri', ( $usage['/use/hero'] ?? null ) === [ 'templates' => [ 'usage-page' ], 'pages' => [ $usagePage ] ] );
check( '...the [image uri="..."] form is read like the bare one', ( $usage['/use/viauri']['pages'] ?? null ) === [ $usagePage ] );
check( 'a slot reached through a [template] include, and one further down that include\'s own', ( $usage['/use/inner'] ?? null ) === [ 'templates' => [ 'usage-part' ], 'pages' => [ $usagePage ] ]
	&& ( $usage['/use/deep'] ?? null ) === [ 'templates' => [ 'usage-deeper' ], 'pages' => [ $usagePage ] ] );
check( 'a template no route renders is listed with its templates and no pages', ( $usage['/use/orphan'] ?? null ) === [ 'templates' => [ 'usage-orphan' ], 'pages' => [] ] );
check( 'templates that include themselves, directly or through another, are read once and do not run away', ( $usage['/use/loop']['pages'] ?? null ) === [ [ 'httpUri' => '/looped', 'name' => '/looped' ] ] );
check( 'a body that names its template by language is read for every available language, and a page without a name of its own is named by its http uri',
	( $usage['/use/german']['pages'] ?? null ) === [ [ 'httpUri' => '/lang', 'name' => '/lang' ] ] && ( $usage['/use/english']['pages'] ?? null ) === [ [ 'httpUri' => '/lang', 'name' => '/lang' ] ] );
check( 'only what is served counts: a POST route is no page', ( $usage['/use/post'] ?? null ) === null );
check( 'a slot nothing mentions is not in the answer at all', array_key_exists( '/use/unused', $usage ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Images\Slots::class, 'apiList' );
$usageRows = array_column( $body['slots'], null, 'uri' );
check( 'apiList carries usage on every slot: the used one with its page, the unused one empty', ( $usageRows['/use/hero']['usage']['pages'] ?? null ) === [ $usagePage ] && ( $usageRows['/use/unused']['usage'] ?? null ) === [ 'templates' => [], 'pages' => [] ] );

foreach( array_keys( $usageTemplates ) as $usageName )
	unlink( $sandbox. '/private/templates/'. $usageName. '.tpl' );
$appData['/nino/http/routes'] = $usageRoutesBefore;
foreach( [ 'hero', 'viauri', 'inner', 'deep', 'orphan', 'loop', 'german', 'english', 'post', 'unused' ] as $usageSlot )
	unset( $appData['/nino/html/images']['/use/'. $usageSlot] );

echo "\n";


// --- Dashboard::apiSummary - the Text Keys and Image Slots tiles ----------

echo "Dashboard::apiSummary\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'apiSummary succeeds', $status === 200 );
$tile = static function( array $body, string $panel ): ?string {
	foreach( $body['tiles'] ?? [] as $entry )
		if( $entry['panel'] === $panel )
			return (string) $entry['value'];
	return null;
};
check( 'the Text Keys tile reports 0 missing - both scan fixtures above were already cleaned up', $tile( $body, 'keys' ) === '0' );
check( 'the Image Slots tile reports 0 missing - the scan fixture above was already cleaned up', $tile( $body, 'slots' ) === '0' );
check( 'the structure panels report their counts as tiles too', $tile( $body, 'types' ) !== null && $tile( $body, 'routes' ) !== null && $tile( $body, 'users' ) !== null );

// A fresh, self-contained fixture (one undefined key, one untracked image) to
// prove the two counts actually reflect Text/Images::_scanMissing(), not just
// always 0 - a new filename, since probe.png is already tracked by the slot
// created in the Images::apiScan section above
file_put_contents( $sandbox. '/private/templates/dashboard-fixture.tpl', '<p>[[/dashboard-scan-test/heading]]</p><img src="[[/nino/public]]/images/probe2.png">' );

[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'the Text Keys tile picks up the fresh undefined key', $tile( $body, 'keys' ) === '1' );
check( 'the Image Slots tile picks up the fresh untracked image (a new filename, not tracked by any slot)', $tile( $body, 'slots' ) === '1' );

unlink( $sandbox. '/private/templates/dashboard-fixture.tpl' );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'apiSummary requires an authed dev session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Notices: what needs attention, above the tiles. Mail that failed is shown to
// every account that opens the dashboard - the record holds no address - and
// the texts still to translate to the one that may open the Text panel
$noticesOf = static function( array $body, string $text ): array {
	return array_values( array_filter( $body['notices'] ?? [], static fn( array $notice ): bool => $notice['text'] === $text ) );
};
@unlink( \Nino\Filesystem::path( $appData, '/data/mail-status.php' ) );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'notices is an array, and carries no mail notice while nothing failed', is_array( $body['notices'] ?? null ) === true && $noticesOf( $body, '/_admin/dashboard/notice/mail' ) === [] );

\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = false; } );
$minuteBefore = date( 'Y-m-d H:i' );
\Nino\Mail::send( $appData, 'owner@example.com', 'a', 'b', '' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$mailNotice = $noticesOf( $body, '/_admin/dashboard/notice/mail' );
check( 'a failed mail raises one notice: since when, and how many calls failed - no link', count( $mailNotice ) === 1
	&& in_array( $mailNotice[0]['values'], [ [ $minuteBefore, '1' ], [ date( 'Y-m-d H:i' ), '1' ] ], true ) && $mailNotice[0]['link'] === '' );
check( '...which holds no address', str_contains( json_encode( $body['notices'] ), 'example.com' ) === false );

ninoUser( $appData, 'nopanels@example.com', [] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'an account with no panel permission sees the mail notice as well', $status === 200 && count( $noticesOf( $body, '/_admin/dashboard/notice/mail' ) ) === 1 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
\Nino\Mail::send( $appData, 'owner@example.com', 'a', 'b', '' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( 'the next send that delivered everything takes the notice away', $noticesOf( $body, '/_admin/dashboard/notice/mail' ) === [] );
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );

// A text the native language has and English does not
$english = static fn( array $body ): array => array_values( array_filter( $noticesOf( $body, '/_admin/dashboard/notice/untranslated' ), static fn( array $notice ): bool => $notice['values'][1] === 'en_US' ) );
$untranslatedBefore = $english( callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' )[1] );
$countBefore = (int) ( \Nino\Modules\Text\Admin::untranslatedCounts( $appData )['en_US'] ?? 0 );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => $texts + [ '[[/notice-test/gap]]' => 'Eine Lücke' ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$untranslated = $english( $body );
check( 'an account that may open the Text panel is told, per language, how many texts it still lacks, with a link to the panel', count( $untranslated ) === 1
	&& $untranslated[0]['values'] === [ (string) ( $countBefore + 1 ), 'en_US' ] && $untranslated[0]['link'] === '#text' );

\Nino\Auth::loginUser( $appData, 'nopanels@example.com', 'correct horse battery staple' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( '...and an account that may not is told nothing of it', $noticesOf( $body, '/_admin/dashboard/notice/untranslated' ) === [] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

callDev( $appData, \Nino\Modules\Text\Admin::class, 'apiSaveBatch', [ 'items' => [ [ 'key' => '/notice-test/gap', 'locale' => 'en_US', 'value' => 'A gap' ] ] ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$after = $english( $body );
check( '...and the notice follows the text being saved in that language', ( $after[0]['values'][0] ?? '0' ) === (string) $countBefore );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => array_diff_key( $texts, [ '[[/notice-test/gap]]' => 1 ] ) );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $texts ): array => array_diff_key( $texts, [ '[[/notice-test/gap]]' => 1 ] ) );

// What is wrong with the legal texts: told to an account that may edit elements,
// while the module is active - and to nobody else. What is told, and how much
// of it, is the module's (\Nino\Modules\Legal::notices()); the summary carries it
$legalModulesBefore = $appData['/nino/modules'] ?? [];
$appData['/nino/modules'] = array_merge( $legalModulesBefore, [ '\\Nino\\Modules\\Legal' ] );
$legalRoutes = $appData['/nino/http/routes'] ?? [];
$legalNotices = static fn( array $body ): array => array_values( array_filter( $body['notices'] ?? [], static fn( array $notice ): bool => str_starts_with( $notice['text'], '/_admin/dashboard/notice/legal-' ) ) );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
$missingTypes = $legalNotices( $body );
check( 'the module is active and its types are not there: the dashboard says so, once per type, with the file of the unit to copy back and a link to the type', count( array_filter( $missingTypes, static fn( array $notice ): bool => $notice['text'] === '/_admin/dashboard/notice/legal-nothing' ) ) === 2
	&& $missingTypes[0]['values'][0] === 'legal' && str_ends_with( $missingTypes[0]['values'][1], 'install/elements/legal.php' ) && $missingTypes[0]['link'] === '#elements/legal' );

\Nino\Auth::loginUser( $appData, 'nopanels@example.com', 'correct horse battery staple' );
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( '...and an account that may not edit elements is told nothing of it', $legalNotices( $body ) === [] );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$appData['/nino/modules'] = $legalModulesBefore;
[ , $body ] = callDev( $appData, \Nino\Modules\Dashboard\Admin::class, 'apiSummary' );
check( '...nor is anybody, once the module is off', $legalNotices( $body ) === [] );

// A language added later gets what the module brings for it - once the module
// is active, and the types it brings are there to get their sections
$appData['/nino/modules'] = array_merge( $legalModulesBefore, [ '\\Nino\\Modules\\Legal' ] );
$legalRoutesOut = []; $legalBlacklistOut = []; $legalConfigOut = [];
\Nino\Features::applyUnit( $appData, __DIR__. '/../_nino/Nino/Modules/Legal/install', [ 'de_DE' ], $legalRoutesOut, $legalBlacklistOut, $legalConfigOut, true );
$englishFile = \Nino\Filesystem::path( $appData, '/text/en_US.php' );
$englishBefore = is_file( $englishFile ) === true ? (string) file_get_contents( $englishFile ) : null;
@unlink( $englishFile );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'en_US' ] );
$englishTexts = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a language the Legal unit has texts for gets its page details and its versions of the sections with the new file', $status === 200 && ( $body['created'] ?? null ) === true
	&& ( $englishTexts['[[/_nino/webpage/legal/privacy/name]]'] ?? null ) === 'Privacy' && ( $englishTexts['[[/_nino/webpage/legal/privacy/title]]'] ?? null ) === 'Privacy policy'
	&& ( \Nino\Filesystem::getFileContent( $appData, '/elements/privacy.php', [] )['en_US']['hosting']['title'] ?? null ) === 'Hosting' );
$appData['/nino/modules'] = $legalModulesBefore;
if( $englishBefore !== null )
	file_put_contents( $englishFile, $englishBefore );
else
	@unlink( $englishFile );
@unlink( \Nino\Filesystem::path( $appData, '/elements/legal.php' ) );
@unlink( \Nino\Filesystem::path( $appData, '/elements/privacy.php' ) );
@unlink( \Nino\Filesystem::path( $appData, '/templates/page-legal-imprint.tpl' ) );
@unlink( \Nino\Filesystem::path( $appData, '/templates/page-legal-privacy.tpl' ) );
unset( $appData['./nino/elements/cache'] );
$appData['/nino/http/routes'] = $legalRoutes;

echo "\n";

ninoDone( $appData );
