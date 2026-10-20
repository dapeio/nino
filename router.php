<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */

//  router.php - for local developement only

$parsedPath = @parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
$uri = is_string( $parsedPath ) === true ? urldecode( $parsedPath ) : '/';
// A run of slashes is one slash to Apache, nginx and php -S: judge it as one
$uri = (string) preg_replace( '#/{2,}#', '/', $uri );
// On a filesystem that ignores case (macOS, Windows) /_Admin/Templates/... is
// the same file as /_admin/templates/..., and a directory's .htaccess applies
// to it there as well: the rules below are then judged without regard to case
$i = is_file( __DIR__. '/INDEX.PHP' ) === true ? 'i' : '';

// The private half of the project is never served as a static file. In
// production private/.htaccess denies it (and a hardened setup points
// NINO_PRIVATE_DIR outside the webroot entirely), but this server applies
// no .htaccess at all - without this, a request for
// /private/templates/page-home.tpl would hand back the template source,
// which is exactly what moving those files out of the public root prevents
if( preg_match( '#^/private(?:/|$)#'. $i, $uri ) === 1 ) {
    http_response_code( 404 );
    return true;
}

// The installer's library is what the setup wizard copies a project out of,
// not a second public asset tree - and since 1.2 there is nothing public in
// it at all. Mirror _admin/install/library/.htaccess here because PHP's
// development server ignores Apache configuration entirely.
if( preg_match( '#^/_admin/install/library(?:/|$)#'. $i, $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// The application half is source, never a static tree: the project's own
// classes and modules with their install units - manifests, mail and page
// templates, text files. Mirrors app/.htaccess, for the same reason the
// rule above mirrors _admin/install/library/.htaccess
if( preg_match( '#^/app(?:/|$)#'. $i, $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// The suites are run from a shell, never from a browser - reachable over http,
// a suite of tests/ would run its checks for whoever asked.
// Mirrors tests/.htaccess
if( preg_match( '#^/tests(?:/|$)#'. $i, $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// The installed features are source as well - a feature's runtime class,
// its panel and its install unit (see \Nino\Features). Mirrors
// features/.htaccess
if( preg_match( '#^/features(?:/|$)#'. $i, $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// The tool folders' own source is no public tree either - the kernel's
// classes with their templates and texts (_nino/Nino/), the workbench's
// templates, texts and panels (_admin/templates/, _admin/text/, _admin/Nino/)
// and the wizard but for its assets/, which its page loads. Nothing a page
// loads is in them. Mirrors the .htaccess each of them carries, for the same
// reason as the rules above. What stays served of the tool folders is their
// public part: _nino/Nino.css, Nino.js and Nino.ui.js, _admin/assets/,
// _admin/.cache/, _admin/install/assets/
if( preg_match( '#^/(?:_nino/Nino|_admin/(?:templates|text|Nino))(?:/|$)#'. $i, $uri ) === 1
	|| preg_match( '#^/_admin/install(?:/(?!assets/)|$)#'. $i, $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// Nor are the checkout's manuals and its history, for as long as the
// directories are there: the .htaccess each of them carries is what this
// mirrors, and a project with a page of its own at /docs or /history deletes
// the directory, which makes the address a route again
if( preg_match( '#^/(docs|history)(?:/|$)#'. $i, $uri, $tree ) === 1 && is_dir( __DIR__. '/'. $tree[1] ) === true ) {
	http_response_code( 404 );
	return true;
}

// The checkout's metadata at its root - README.md, AGENTS.md, phpstan.neon,
// eslint.config.mjs - is nobody's page. Refused where it is a file, as the RewriteRule of
// .htaccess does: an address of that shape that is no file is a route
if( preg_match( '#^/[^/]+\.(?:md|neon|mjs)$#'. $i, $uri ) === 1 && is_file( __DIR__. $uri ) === true ) {
	http_response_code( 404 );
	return true;
}

// Dotfiles/dotdirs never get served as static files, .cache/ (the
// bundled/minified css+js the [assets ...] shortcode generates) and
// .demo/ (the bundled demo images) are the two exceptions - without
// this, a direct request could otherwise read a stub-protected file
// straight off disk, bypassing that protection. Dot-uris that are no
// files at all (/.newsletter, /.demo-sections, ...) fall through to
// index.php and resolve as ordinary routes
if( $uri !== '/' && preg_match( '#/\.(?!cache/|demo/)#', $uri ) !== 1 && is_file( __DIR__. $uri ) === true )
    return false;

if( str_starts_with( $uri, '/_admin' ) === true ) {
    chdir( __DIR__. '/_admin' );
    require __DIR__. '/_admin/index.php';
    return true;
}

require __DIR__. '/index.php';
return true;
