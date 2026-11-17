<?php
declare(strict_types=1);

/**
 *	Nino
 *	admin-fills-smoke.php	The textfills a field of the workbench offers behind its "[ ]" icon:
 *											\Nino\Text::fills() lists the keys a form of FILL_FORMS may name and no
 *											other, in the notation of the form, and the Elements panel's action
 *											elements/fills asks for the permission of the panel and the right to
 *											change that one field before it answers anything. Runs against an
 *											isolated sandbox.
 *
 *	Usage: php tests/admin-fills-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'admin-fills' );
$sandbox = ninoSandboxDir( $appData );

foreach( [ 'private/elements', 'private/text' ] as $directory )
	mkdir( $sandbox. '/'. $directory, 0777, true );

\Nino\Filesystem::putFileContent( $appData, '/config.php', [
	'/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [],
] );

// What the project says: facts of the company and the website, a word several templates share, and what no
// field may offer - the mailbox of the forms, a word of one template, a key outside the grammar, a hidden one
$texts = [
	'[[/project/company/general/name]]'		=> 'Müller <b>&amp; Söhne</b>',
	'[[/project/company/contact/email]]'		=> 'info@example.org',
	'[[/project/website/general/url]]'			=> 'example.org',
	'[[/template/common/label/phone]]'			=> 'Telefon',
	'[[/project/mail/address/owner]]'			=> 'owner@example.org',
	'[[/template/page-home/main-hero/title]]'	=> 'Willkommen',
	'[[/project/company/contact]]'				=> 'three segments',
	'[[/project/company/contact/fax]]'			=> 'hidden from the workbench',
	'[[/_admin/common/label/close]]'			=> 'Schließen',
	'[[/project/company/general/long]]'		=> str_repeat( 'x', 400 ),
];
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', $texts );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/project/company/general/name]]' => 'Miller' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/blacklist.php', [ '/project/company/contact/fax' ] );

\Nino\Filesystem::putFileContent( $appData, '/elements/legal.php', [
	'title' => 'Imprint',
	'model' => [
		'title' => [ 'type' => 'string', 'locale' => true ],
		'text' 	=> [ 'type' => 'string', 'locale' => true, 'html' => true ],
	],
	'*' => [ '*' => [] ],
] );
\Nino\Filesystem::putFileContent( $appData, '/elements/note.php', [
	'title' => 'Note',
	'model' => [ 'text' => [ 'type' => 'string', 'locale' => true, 'html' => true ] ],
	'*' => [ '*' => [] ],
] );

\Nino\Locales::useLocale( $appData, 'de_DE' );
ninoWarnings();

$keys = static fn( array $list ): array => array_column( $list, 'key' );

echo "\\Nino\\Text::fills\n";

$placeholders = \Nino\Text::fills( $appData, 'placeholder', 'de_DE' );
check( 'a placeholder form lists the facts of the company and of the website, and nothing else', $keys( $placeholders ) === [
	'/project/company/contact/email', '/project/company/general/long', '/project/company/general/name', '/project/website/general/url',
] );
check( '...each in the notation of the form: the key between two #', array_column( $placeholders, 'notation' ) === [
	'#/project/company/contact/email#', '#/project/company/general/long#', '#/project/company/general/name#', '#/project/website/general/url#',
] );

$textfills = \Nino\Text::fills( $appData, 'textfill', 'de_DE' );
check( 'a textfill form adds the words several templates share, in [[ ]]', in_array( '/template/common/label/phone', $keys( $textfills ), true ) === true
	&& in_array( '[[/template/common/label/phone]]', array_column( $textfills, 'notation' ), true ) === true );

$offered = array_merge( $keys( $placeholders ), $keys( $textfills ) );
check( 'no key outside the namespaces is ever offered: not the mailbox, not a word of one template, not the workbench\'s, not one outside the grammar, not a hidden one',
	array_intersect( $offered, [ '/project/mail/address/owner', '/template/page-home/main-hero/title', '/_admin/common/label/close', '/project/company/contact', '/project/company/contact/fax' ] ) === [] );

check( 'the value is plain words in the language asked for, and a language with no text of its own shows the fallback\'s', array_column( $placeholders, 'value', 'key' )['/project/company/general/name'] === 'Müller & Söhne'
	&& array_column( \Nino\Text::fills( $appData, 'placeholder', 'en_US' ), 'value', 'key' )['/project/company/general/name'] === 'Miller'
	&& array_column( \Nino\Text::fills( $appData, 'placeholder', 'en_US' ), 'value', 'key' )['/project/company/contact/email'] === 'info@example.org' );
check( '...and one that is long is cut', mb_strlen( array_column( $placeholders, 'value', 'key' )['/project/company/general/long'], 'UTF-8' ) < 200 );
check( 'a form that is none, and a language the site does not have, offer nothing', \Nino\Text::fills( $appData, 'anything', 'de_DE' ) === [] && \Nino\Text::fills( $appData, 'placeholder', 'xx_XX' ) === [] );


echo "\nelements/fills\n";

$ask = static function( array &$appData, array $data ): array {
	[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Admin::class, 'apiFills' ], $data );
	return [ $status, $body ];
};

[ $status ] = $ask( $appData, [ 'type' => 'legal', 'field' => 'text' ] );
check( 'without a session the action answers nothing', $status === 401 || $status === 403 );

ninoUser( $appData, 'dev@example.com' );

[ $status, $body ] = $ask( $appData, [ 'type' => 'legal', 'field' => 'text' ] );
check( 'an account that may change the field gets the placeholders', $status === 200 && $keys( $body['fills'] ?? [] ) === $keys( $placeholders ) );

foreach( [ [ 'legal', 'title' ], [ 'legal', 'missing' ], [ 'note', 'text' ] ] as [ $type, $field ] ) {
	[ $status, $body ] = $ask( $appData, [ 'type' => $type, 'field' => $field ] );
	check( 'a field that takes no textfills is refused, whatever it is - '. $type. '/'. $field, $status === 403 && isset( $body['fills'] ) === false );
}

[ $status ] = $ask( $appData, [ 'type' => 'nope', 'field' => 'text' ] );
check( 'a type nobody has is not found', $status === 404 );

[ $status ] = $ask( $appData, [ 'type' => '../legal', 'field' => 'text' ] );
check( '...nor one that spells a path', $status === 404 );

// The panel's permission, and then the right to change this one field: an account that holds the scope of
// one other field keeps nothing of the rest
\Nino\Auth::logoutUser( $appData );
ninoUser( $appData, 'nobody@example.com', [] );
[ $status, $body ] = $ask( $appData, [ 'type' => 'legal', 'field' => 'text' ] );
check( 'an account without the panel\'s permission gets nothing', $status === 403 && isset( $body['fills'] ) === false );

\Nino\Auth::logoutUser( $appData );
ninoUser( $appData, 'narrow@example.com', [ '/_admin/elements/manage', '/_admin/elements/legal/update/title' ] );
[ $status, $body ] = $ask( $appData, [ 'type' => 'legal', 'field' => 'text' ] );
check( 'an account that may change another field of the type, and not this one, gets nothing', $status === 403 && isset( $body['fills'] ) === false );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Admin::class, 'apiTypes' ] );
$legal = array_values( array_filter( $body['types'] ?? [], static fn( array $type ): bool => $type['type'] === 'legal' ) )[0] ?? [];
check( '...and its types list names no field with an icon for it', (array) ( $legal['fills'] ?? [ 'x' ] ) === [] );

\Nino\Auth::logoutUser( $appData );
ninoUser( $appData, 'dev2@example.com' );
[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Admin::class, 'apiTypes' ] );
$legal = array_values( array_filter( $body['types'] ?? [], static fn( array $type ): bool => $type['type'] === 'legal' ) )[0] ?? [];
$note = array_values( array_filter( $body['types'] ?? [], static fn( array $type ): bool => $type['type'] === 'note' ) )[0] ?? [];
check( 'the types list names the one field that takes placeholders, for an account that may change it, with the form', (array) ( $legal['fills'] ?? [] ) === [ 'text' => 'placeholder' ] && (array) ( $note['fills'] ?? [ 'x' ] ) === [] );

ninoDone( $appData );
