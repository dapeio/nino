<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-legal-smoke.php	Test of what \Nino\Modules\Legal::check() knows of the menu components of Basis: a page of the legal texts that stands in no menu
 *												a template outputs is a finding, and [footer-nav], [header-nav] and [footer-legal] are outputs of a menu - of the one they
 *												name or, where they name none, of the one they take, and of the menu legal - where Basis is on and they are registered.
 *												The module itself, with the shortcode of the menu, is tests/legal-smoke.php.
 *
 *	Usage: php tests/basis-legal-smoke.php
 */

define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

/**
 *	A project the wizard set up, with the feature Basis switched on as a project does in the Features panel, and the texts of which are filled in
 *
 *	@param		string		$name
 *
 *	@return 	array
 */
function basisLegalProject( string $name ): array {

	$appData = ninoSandbox( $name );
	$sandbox = ninoSandboxDir( $appData );
	$appData['/nino/dir'] = '';

	foreach( [ 'private/templates', 'private/text', 'private/assets', 'public/images' ] as $directory )
		mkdir( $sandbox. '/'. $directory, 0777, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [], '/nino/html/assets' => [], '/nino/http/routes' => [] ] );
	ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
	\Nino\Features::activate( $appData, 'basis' );
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
	\Nino\Features::registerComponents( $appData );
	\Nino\Modules\Legal::init( $appData );
	\Nino\Locales::useLocale( $appData, 'de_DE' );
	ninoWarnings();

	return $appData;
}

$kindsOf = static fn( array $findings ): array => array_column( $findings, 'kind' );

$clean = basisLegalProject( 'basis-legal' );

echo "check(): the menu components of Basis\n";

// ...and the menu components of Basis are outputs of a menu: [footer-nav] and [header-nav] of the menu they name or, where they name none, of the one they take, and [footer-legal] of the menu legal
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '[footer-wrap][footer-col][footer-nav][/footer-col][/footer-wrap]' );
check( 'nav: [footer-nav] with no menu named outputs the menu footer, which has not the pages', count( array_filter( \Nino\Modules\Legal::check( $clean ), static fn( array $finding ): bool => $finding['kind'] === 'nav' ) ) === 2 );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '[footer-wrap][footer-col][footer-nav menu="legal"][/footer-col][/footer-wrap]' );
check( '...[footer-nav menu="legal"] outputs the menu that has them', \Nino\Modules\Legal::check( $clean ) === [] );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '[footer-wrap][footer-legal][/footer-wrap]' );
check( '...and so does [footer-legal], which draws the legal pages itself', \Nino\Modules\Legal::check( $clean ) === [] );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '' );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-header.tpl', '[header-wrap][header-nav menu="legal"][/header-wrap]' );
check( '...and [header-nav menu="legal"] in the header outputs the menu as well', \Nino\Modules\Legal::check( $clean ) === [] );
// ...but only where they are registered: with Basis off a call of them draws nothing and no menu is output
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '[footer-wrap][footer-legal][footer-nav menu="legal"][/footer-wrap]' );
$noBasis = $clean;
unset( $noBasis[\Nino\Components::REGISTRY] );
check( '...but one for each page when Basis is off: its components draw no menu', $kindsOf( \Nino\Modules\Legal::check( $noBasis ) ) === [ 'nav', 'nav' ] && \Nino\Modules\Legal::check( $clean ) === [] );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-header.tpl', '' );
\Nino\Filesystem::putFileContent( $clean, '/templates/frame-footer.tpl', '<footer>[navigation nav="legal" id="legal__nav"][/navigation]</footer>' );

ninoDone( $clean );
