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

// The private half of the project is never served as a static file. In
// production private/.htaccess denies it (and a hardened setup points
// NINO_PRIVATE_DIR outside the webroot entirely), but this server applies
// no .htaccess at all - without this, a request for
// /private/templates/page-home.tpl would hand back the template source,
// which is exactly what moving those files out of the public root prevents
if( preg_match( '#^/private(?:/|$)#', $uri ) === 1 ) {
    http_response_code( 404 );
    return true;
}

// The installer's library is what the setup wizard copies a project out of,
// not a second public asset tree - and since 1.2 there is nothing public in
// it at all. Mirror _admin/install/library/.htaccess here because PHP's
// development server ignores Apache configuration entirely.
if( preg_match( '#^/_admin/install/library(?:/|$)#', $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// The application half is source, never a static tree: the project's own
// classes and modules with their install units - manifests, mail and page
// templates, text files. Mirrors app/.htaccess, for the same reason the
// rule above mirrors _admin/install/library/.htaccess
if( preg_match( '#^/app(?:/|$)#', $uri ) === 1 ) {
	http_response_code( 404 );
	return true;
}

// The installed features are source as well - a feature's runtime class,
// its panel and its install unit (see \Nino\Features). Mirrors
// features/.htaccess
if( preg_match( '#^/features(?:/|$)#', $uri ) === 1 ) {
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
