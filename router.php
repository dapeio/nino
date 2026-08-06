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

$uri = urldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

// Dotfiles/dotdirs never get served as static files, .cache/ (the
// bundled/minified css+js the [assets ...] shortcode generates) and
// .demo/ (the bundled demo images) are the two exceptions - without
// this, a direct request could otherwise read _admin/.lockout.json or
// _editor/.backups-*/ contents straight off disk, bypassing the .php
// stub protection those paths normally rely on. Dot-uris that are no
// files at all (/.newsletter, /.demo-sections, ...) fall through to
// index.php and resolve as ordinary routes
if( $uri !== '/' && preg_match( '#/\.(?!cache/|demo/)#', $uri ) !== 1 && is_file( __DIR__. $uri ) === true )
    return false;

if( str_starts_with( $uri, '/_editor' ) === true ) {
    chdir( __DIR__. '/_editor' );
    require __DIR__. '/_editor/index.php';
    return true;
}

if( str_starts_with( $uri, '/_admin' ) === true ) {
    chdir( __DIR__. '/_admin' );
    require __DIR__. '/_admin/index.php';
    return true;
}

if( str_starts_with( $uri, '/_install' ) === true ) {
    chdir( __DIR__. '/_install' );
    require __DIR__. '/_install/index.php';
    return true;
}

require __DIR__. '/index.php';
return true;