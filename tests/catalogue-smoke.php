<?php
declare(strict_types=1);

/**
 *	Nino
 *	catalogue-smoke.php	Contract test for \Nino\Fetch and \Nino\Catalogue: the
 *											https-only client behind a stub, the detached signature,
 *											what a catalogue document must say, what it offers this
 *											kernel, and an installation from archive bytes to a
 *											directory below features/ - including every refusal on
 *											the way (a wrong digest, a hostile archive, an archive
 *											that is not the feature it was promised to be).
 *
 *											No network: the stub under './nino/fetch/stub' answers
 *											from a map of urls. No shared key: a keypair is generated
 *											per run. No real features: the archives are built here,
 *											byte by byte, so a hostile one can be built too.
 *
 *	Usage: php tests/catalogue-smoke.php
 */

// Before the kernel loads: installs land in a directory of this run's own,
// never in the checkout's features/
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-catalogue-features-'. uniqid() );
mkdir( NINO_FEATURES_DIR, 0755, true );

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'catalogue' );
$sandbox = ninoSandboxDir( $appData );
\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native' ] );

register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( NINO_FEATURES_DIR ) );


// --- Helpers: a keypair, a tar writer, a catalogue builder, a stub ---------

$keypair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
openssl_pkey_export( $keypair, $privateKey );
$publicKey = openssl_pkey_get_details( $keypair )['key'];

$otherKeypair = openssl_pkey_new( [ 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ] );
$otherPublicKey = openssl_pkey_get_details( $otherKeypair )['key'];

/**
 *	Sign the way bin/build.php and `openssl dgst -sha256 -sign` do: DER, base64
 */
function sign( string $data, string $privateKey ): string {
	openssl_sign( $data, $signature, $privateKey, OPENSSL_ALGO_SHA256 );
	return base64_encode( $signature );
}

/**
 *	One ustar header - enough tar to build an archive PharData reads, and
 *	enough to name what a real build never would: a "../", a symlink
 */
function tarHeader( string $name, int $size, string $type, string $link = '' ): string {
	$h  = str_pad( $name, 100, "\0" );
	$h .= "0000644\0". "0000000\0". "0000000\0";
	$h .= str_pad( decoct( $size ), 11, '0', STR_PAD_LEFT ). "\0";
	$h .= str_pad( decoct( 1700000000 ), 11, '0', STR_PAD_LEFT ). "\0";
	$h .= '        '. $type. str_pad( $link, 100, "\0" ). "ustar\0". '00';
	$h  = str_pad( $h, 512, "\0" );
	$sum = 0;
	for( $i = 0; $i < 512; $i++ )
		$sum += ord( $h[$i] );
	return substr( $h, 0, 148 ). str_pad( decoct( $sum ), 6, '0', STR_PAD_LEFT ). "\0 ". substr( $h, 156 );
}

/**
 *	A .tar.gz from name => content (string: a file, null: a directory,
 *	[ 'link' => target ]: a symlink)
 */
function tarGz( array $entries ): string {
	$out = '';
	foreach( $entries as $name => $content ) {
		if( $content === null )
			$out .= tarHeader( $name, 0, '5' );
		elseif( is_array( $content ) === true )
			$out .= tarHeader( $name, 0, '2', $content['link'] );
		else
			$out .= tarHeader( $name, strlen( $content ), '0' ). str_pad( $content, (int) ( ceil( strlen( $content ) / 512 ) * 512 ), "\0" );
	}
	return gzencode( $out. str_repeat( "\0", 1024 ), 6 );
}

/**
 *	The files of a minimal feature, ready for tarGz(): a manifest and the
 *	class the autoloader expects, plus whatever else
 */
function featureFiles( string $directory, string $key, string $version, array $extra = [] ): array {
	$files = [
		$directory. '/'								=> null,
		$directory. '/feature.php'			=> '<?php return [ \'key\' => \''. $key. '\', \'name\' => \''. ucfirst( $key ). '\', \'version\' => \''. $version. '\' ];',
		$directory. '/'. $directory. '.php'	=> '<?php namespace Nino\\Modules { class '. $directory. ' { public const string VERSION = \''. $version. '\'; } }',
	];
	foreach( $extra as $name => $content )
		$files[ $directory. '/'. $name ] = $content;
	return $files;
}

/**
 *	A catalogue entry for archive bytes published under a url
 */
function entry( string $key, string $version, string $bytes, array $more = [] ): array {
	return $more + [
		'key'					=> $key,
		'name'				=> ucfirst( $key ),
		'description'	=> [ 'en_US' => 'A '. $key, 'de_DE' => 'Ein '. $key ],
		'version'			=> $version,
		'nino'				=> '^1.0',
		'php'					=> [ 'ext' => [] ],
		'requires'		=> [],
		'directory'		=> ucfirst( $key ),
		'archive'			=> 'https://catalogue.test/features/'. $key. '-'. $version. '.tar.gz',
		'sha256'			=> hash( 'sha256', $bytes ),
		'size'				=> strlen( $bytes ),
		'released'		=> '2026-09-07',
	];
}

$remote		= [];		// url => body
$requests	= [];		// every url the stub was asked for, in order

$appData['./nino/fetch/stub'] = static function( string $url, array $options ) use ( &$remote, &$requests ): array {
	$requests[] = [ 'url' => $url, 'options' => $options ];
	if( isset( $remote[$url] ) === false )
		return [ 'ok' => false, 'status' => 404, 'error' => 'http 404' ];
	if( strlen( $remote[$url] ) > $options['maxBytes'] )
		return [ 'ok' => false, 'status' => 200, 'error' => 'the answer exceeds '. $options['maxBytes']. ' bytes' ];
	return [ 'ok' => true, 'status' => 200, 'body' => $remote[$url] ];
};

/**
 *	Publish a catalogue - signed with the run's key unless told otherwise -
 *	under the default url, and forget what an earlier request had read
 */
function publish( array &$appData, array &$remote, array $features, ?string $signWith, string $privateKey ): string {
	$json = json_encode( [ 'format' => 1, 'generated' => '2026-09-07T12:00:00Z', 'features' => $features ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	$remote[ \Nino\Catalogue::DEFAULT_URL ]					= $json;
	$remote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ]	= $signWith === null ? sign( $json, $privateKey ) : $signWith;
	unset( $appData['./nino/catalogue'] );
	return $json;
}

$appData['/nino/catalogue/key'] = $publicKey;


// --- Fetch -------------------------------------------------------------------

echo "Fetch - one https GET, on request\n";

check( 'an absolute https url with a host passes', \Nino\Fetch::isHttpsUrl( 'https://catalogue.getnino.dev/catalogue.json' ) === true && \Nino\Fetch::isHttpsUrl( 'HTTPS://example.org' ) === true );
check( 'http, a relative path, a url without a host, credentials, whitespace and control characters do not',
	\Nino\Fetch::isHttpsUrl( 'http://getnino.dev/x' ) === false
	&& \Nino\Fetch::isHttpsUrl( '/features/catalogue.json' ) === false
	&& \Nino\Fetch::isHttpsUrl( 'https://' ) === false
	&& \Nino\Fetch::isHttpsUrl( 'https://user:pw@getnino.dev/' ) === false
	&& \Nino\Fetch::isHttpsUrl( 'https://user@getnino.dev/' ) === false
	&& \Nino\Fetch::isHttpsUrl( "https://getnino.dev/a b" ) === false
	&& \Nino\Fetch::isHttpsUrl( "https://getnino.dev/a\nb" ) === false
	&& \Nino\Fetch::isHttpsUrl( 'https://getnino.dev/'. str_repeat( 'a', 2048 ) ) === false );

$requests = [];
$answer = \Nino\Fetch::get( $appData, 'http://catalogue.test/x' );
check( 'a url that is not https is refused before any request', $answer['ok'] === false && $answer['error'] === 'only an https url is fetched' && $requests === [] );

$remote['https://catalogue.test/hello'] = 'hello';
$answer = \Nino\Fetch::get( $appData, 'https://catalogue.test/hello' );
check( 'the stub answers in the shape the client returns', $answer === [ 'ok' => true, 'status' => 200, 'body' => 'hello', 'error' => '' ] );
check( 'the defaults reach the request', $requests[0]['options'] === [ 'timeout' => \Nino\Fetch::DEFAULT_TIMEOUT, 'maxBytes' => \Nino\Fetch::DEFAULT_MAX_BYTES ] );

\Nino\Fetch::get( $appData, 'https://catalogue.test/hello', [ 'timeout' => 9999, 'maxBytes' => -5 ] );
check( 'timeout and byte cap are clamped', end( $requests )['options'] === [ 'timeout' => \Nino\Fetch::MAX_TIMEOUT, 'maxBytes' => 1 ] );

$answer = \Nino\Fetch::get( $appData, 'https://catalogue.test/missing' );
check( 'a missing answer comes back as not ok with the error, never as a body', $answer['ok'] === false && $answer['status'] === 404 && $answer['body'] === '' && $answer['error'] === 'http 404' );

echo "\n";


// --- Catalogue::url / key ----------------------------------------------------

echo "Catalogue::url / key - configuration\n";

$config = $appData;
check( 'the default url is Nino\'s catalogue', \Nino\Catalogue::url( $config ) === \Nino\Catalogue::DEFAULT_URL && \Nino\Catalogue::url( $config ) === 'https://catalogue.getnino.dev/catalogue.json' );
$config['/nino/catalogue/url'] = 'https://example.org/own/catalogue.json';
check( 'a catalogue of your own replaces it', \Nino\Catalogue::url( $config ) === 'https://example.org/own/catalogue.json' );
$config['/nino/catalogue/url'] = '';
check( 'an empty url switches the catalogue off', \Nino\Catalogue::url( $config ) === '' );
$config['/nino/catalogue/url'] = 'http://example.org/catalogue.json';
check( 'a url that is not https counts as switched off', \Nino\Catalogue::url( $config ) === '' );
$config['/nino/catalogue/url'] = [ 'https://example.org/' ];
check( 'so does anything that is not a string', \Nino\Catalogue::url( $config ) === '' );

$config['/nino/catalogue/key'] = '';
check( 'without a configured key the kernel\'s own is used - and it is empty until a key exists', \Nino\Catalogue::key( $config ) === \Nino\Catalogue::PUBLIC_KEY );
$config['/nino/catalogue/key'] = "  ". $publicKey. "\n\n";
check( 'a configured key is used, trimmed', \Nino\Catalogue::key( $config ) === trim( $publicKey ) );

echo "\n";


// --- Catalogue::verify -------------------------------------------------------

echo "Catalogue::verify - the detached signature\n";

$data			= '{"format":1,"features":[]}';
$signature	= sign( $data, $privateKey );

check( 'a signature over the exact bytes verifies with the public key', \Nino\Catalogue::verify( $data, $signature, $publicKey ) === true );
check( 'whitespace and line breaks around the base64 are tolerated', \Nino\Catalogue::verify( $data, "  ". chunk_split( $signature, 16, "\n" ). "\n", $publicKey ) === true );
check( 'one changed byte and it does not', \Nino\Catalogue::verify( $data. ' ', $signature, $publicKey ) === false && \Nino\Catalogue::verify( str_replace( '1', '2', $data ), $signature, $publicKey ) === false );
check( 'another key does not', \Nino\Catalogue::verify( $data, $signature, $otherPublicKey ) === false );
check( 'an empty key verifies nothing', \Nino\Catalogue::verify( $data, $signature, '' ) === false );
check( 'a key that is not a key verifies nothing', \Nino\Catalogue::verify( $data, $signature, 'not a pem' ) === false );
check( 'an empty, a truncated or a non-base64 signature does not verify', \Nino\Catalogue::verify( $data, '', $publicKey ) === false
	&& \Nino\Catalogue::verify( $data, substr( $signature, 0, 20 ), $publicKey ) === false
	&& \Nino\Catalogue::verify( $data, '!!not base64!!', $publicKey ) === false );

echo "\n";


// --- Catalogue::parse --------------------------------------------------------

echo "Catalogue::parse - what a catalogue must say\n";

$good = entry( 'helper', '1.0.0', 'bytes' );

check( 'not json', \Nino\Catalogue::parse( '{' ) === 'the catalogue is not valid json' );
check( 'the wrong format is named with the one this kernel reads', \Nino\Catalogue::parse( '{"format":2,"features":[]}' ) === 'the catalogue has format 2, this kernel reads format 1'
	&& \Nino\Catalogue::parse( '{"features":[]}' ) === 'the catalogue has format null, this kernel reads format 1' );
check( 'no features list', \Nino\Catalogue::parse( '{"format":1}' ) === 'the catalogue lists no features' );

$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'generated' => 'now', 'features' => [ $good ] ] ) );
check( 'a valid document parses to format, generated and the entries', is_array( $parsed ) && $parsed['format'] === 1 && $parsed['generated'] === 'now' && count( $parsed['features'] ) === 1 );
check( 'an entry is normalized: every key present, in order', array_keys( $parsed['features'][0] ) === [ 'key', 'name', 'description', 'category', 'version', 'nino', 'php', 'requires', 'directory', 'archive', 'sha256', 'size', 'released' ] );

$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'features' => [ [ 'key' => 'x', 'name' => 'X', 'version' => '1.0.0', 'directory' => 'X', 'archive' => 'https://c.test/x.tar.gz', 'sha256' => strtoupper( str_repeat( 'ab', 32 ) ), 'size' => 1, 'php' => [ 'ext' => [ 'Mbstring' ] ], 'released' => str_repeat( 'd', 40 ) ] ] ] ) );
check( 'what is missing gets its default, digests and extensions are lowercased, released is cut', is_array( $parsed )
	&& $parsed['features'][0]['nino'] === '*' && $parsed['features'][0]['description'] === '' && $parsed['features'][0]['requires'] === []
	&& $parsed['features'][0]['category'] === ''
	&& $parsed['features'][0]['sha256'] === str_repeat( 'ab', 32 ) && $parsed['features'][0]['php']['ext'] === [ 'mbstring' ] && strlen( $parsed['features'][0]['released'] ) === 32 );

// The one field a bad value does not cost the catalogue. Everything else here
// refuses the whole document (see the checks below) because everything else
// is something the kernel acts on; a category is a heading in a list, and a
// kernel that refused a signed catalogue over a category it had never heard
// of would stop reading the catalogue the day a newer one publishes one
$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'features' => [
	entry( 'named', '1.0.0', 'a', [ 'category' => 'security' ] ),
	entry( 'later', '1.0.0', 'b', [ 'category' => 'somethingnewerthanthis' ] ),
	entry( 'broken', '1.0.0', 'c', [ 'category' => [ 'not', 'a', 'slug' ] ] ),
] ] ) );
check( 'a category travels with the entry, one this kernel never heard of included - and one that is not a slug at all is dropped rather than costing the catalogue',
	is_array( $parsed ) && count( $parsed['features'] ) === 3
	&& $parsed['features'][0]['category'] === 'security' && $parsed['features'][1]['category'] === 'somethingnewerthanthis' && $parsed['features'][2]['category'] === '' );

$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'features' => [ $good ] ] ) );

$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'features' => [ $good, $good, entry( 'helper', '1.1.0', 'other' ) ] ] ) );
check( 'the same key and version twice counts once, another version counts', is_array( $parsed ) && count( $parsed['features'] ) === 2 );

$refusals = [
	'"key" must be a slug'																			=> [ 'key' => 'Helper' ],
	'"name" must be a string or a locale => string map'						=> [ 'name' => '' ],
	'"description" must be a string or a locale => string map'		=> [ 'description' => [ 'en_US' => 5 ] ],
	'"version" must be major.minor.patch'													=> [ 'version' => '1.0' ],
	'"nino" must be a version constraint'													=> [ 'nino' => 'latest' ],
	'"php" => "ext" must list extension names'										=> [ 'php' => [ 'ext' => [ 'no such' ] ] ],
	'"requires" must list feature keys'														=> [ 'requires' => [ 'Nope' ] ],
	'"directory" must be a class name segment'										=> [ 'directory' => 'helper' ],
	'"archive" must be an https url'															=> [ 'archive' => 'http://catalogue.test/x.tar.gz' ],
	'"sha256" must be the hex digest of the archive'							=> [ 'sha256' => 'abc' ],
	'"size" must be the archive size in bytes, at most 20971520'	=> [ 'size' => '5' ],
];
$allRefused = true;
foreach( $refusals as $why => $wrong ) {
	$result = \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'features' => [ $good, array_merge( $good, $wrong ) ] ] ) );
	if( $result !== 'catalogue entry 1: '. $why ) {
		$allRefused = false;
		echo "        got: ". json_encode( $result ). "\n";
	}
}
check( 'every wrong field refuses the whole catalogue, naming the entry and the field', $allRefused === true );
check( 'an oversized archive is refused', is_string( \Nino\Catalogue::parse( json_encode( [ 'format' => 1, 'features' => [ array_merge( $good, [ 'size' => 20 * 1024 * 1024 + 1 ] ) ] ] ) ) ) );
check( 'an entry that is not an object is refused', \Nino\Catalogue::parse( '{"format":1,"features":["helper"]}' ) === 'catalogue entry 0: must be an object' );

echo "\n";


// --- Catalogue::fetch --------------------------------------------------------

echo "Catalogue::fetch - two requests, believed only with the signature\n";

$helper100 = tarGz( featureFiles( 'Helper', 'helper', '1.0.0', [ 'only-in-1.txt' => 'gone after the update' ] ) );
$helper110 = tarGz( featureFiles( 'Helper', 'helper', '1.1.0' ) );
$sample300 = tarGz( featureFiles( 'Sample', 'sample', '3.0.0' ) );

$remote['https://catalogue.test/features/helper-1.0.0.tar.gz']	= $helper100;
$remote['https://catalogue.test/features/helper-1.1.0.tar.gz']	= $helper110;
$remote['https://catalogue.test/features/sample-3.0.0.tar.gz']	= $sample300;

$features = [
	entry( 'helper', '1.0.0', $helper100 ),
	entry( 'helper', '1.1.0', $helper110 ),
	entry( 'helper', '9.0.0', 'never built', [ 'nino' => '^9.0' ] ),
	entry( 'sample', '3.0.0', $sample300 ),
	entry( 'ancient', '1.0.0', 'never built', [ 'nino' => '^0.9' ] ),
	entry( 'needy', '1.0.0', 'never built', [ 'php' => [ 'ext' => [ 'no_such_extension' ] ] ] ),
];

$off = $appData;
$off['/nino/catalogue/url'] = '';
check( 'switched off', \Nino\Catalogue::fetch( $off ) === 'the catalogue is switched off' );

// An empty configured key falls back to the kernel's own. While that one
// is empty too there is a keyless state, and nothing may be fetched in it;
// once the kernel ships a key, there is none - the fallback is the check
$noKey = $appData;
$noKey['/nino/catalogue/key'] = '';
$requests = [];
if( \Nino\Catalogue::PUBLIC_KEY === '' )
	check( 'without a key nothing is fetched at all', \Nino\Catalogue::fetch( $noKey ) === 'no catalogue key is configured, so no catalogue can be trusted' && $requests === [] );
else
	check( 'the kernel ships a key: an empty configured key falls back to it', \Nino\Catalogue::key( $noKey ) === \Nino\Catalogue::PUBLIC_KEY && openssl_pkey_get_public( \Nino\Catalogue::PUBLIC_KEY ) !== false );

unset( $remote[ \Nino\Catalogue::DEFAULT_URL ], $remote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
unset( $appData['./nino/catalogue'] );
check( 'a catalogue that cannot be fetched says why', \Nino\Catalogue::fetch( $appData ) === 'the catalogue could not be fetched: http 404' );

publish( $appData, $remote, $features, null, $privateKey );
unset( $remote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
check( 'a missing signature is a missing catalogue', \Nino\Catalogue::fetch( $appData ) === 'the catalogue signature could not be fetched: http 404' );

publish( $appData, $remote, $features, sign( 'something else', $privateKey ), $privateKey );
check( 'a signature over other bytes does not verify', \Nino\Catalogue::fetch( $appData ) === 'the catalogue signature does not verify' );

$json = publish( $appData, $remote, $features, null, $privateKey );
$remote[ \Nino\Catalogue::DEFAULT_URL ] = str_replace( 'helper-1.1.0.tar.gz', 'helper-1.1.0-evil.tar.gz', $json );
check( 'a document changed after signing does not verify', \Nino\Catalogue::fetch( $appData ) === 'the catalogue signature does not verify' );

$otherKey = $appData;
publish( $otherKey, $remote, $features, null, $privateKey );
$otherKey['/nino/catalogue/key'] = $otherPublicKey;
check( 'a signature by another key does not verify', \Nino\Catalogue::fetch( $otherKey ) === 'the catalogue signature does not verify' );

publish( $appData, $remote, [ array_merge( $good, [ 'version' => 'one' ] ) ], null, $privateKey );
check( 'a signed document that does not parse is refused with what parse() says', \Nino\Catalogue::fetch( $appData ) === 'catalogue entry 0: "version" must be major.minor.patch' );

publish( $appData, $remote, $features, null, $privateKey );
$requests = [];
$catalogue = \Nino\Catalogue::fetch( $appData );
check( 'a signed, valid catalogue is fetched with two requests: the document and the signature', is_array( $catalogue )
	&& array_column( $requests, 'url' ) === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
check( 'the signature is read with a small cap, the document with a larger one', $requests[0]['options']['maxBytes'] === 1024 * 1024 && $requests[1]['options']['maxBytes'] === 4096 );
check( 'the catalogue carries its url and every entry', $catalogue['url'] === \Nino\Catalogue::DEFAULT_URL && count( $catalogue['features'] ) === 6 );
$requests = [];
check( 'read once per request', \Nino\Catalogue::fetch( $appData ) === $catalogue && $requests === [] );

echo "\n";


// --- Catalogue::cached --------------------------------------------------------

echo "Catalogue::cached - the fetched catalogue kept under data/, no network\n";

$cachePath = \Nino\Filesystem::path( $appData, '/data/catalogue.php' );
check( 'a successful fetch left the catalogue cached to disk', is_file( $cachePath ) === true );

$stored = include $cachePath;
check( 'the file holds when it was fetched, the configured url and the parsed document', is_array( $stored ) && array_keys( $stored ) === [ 'fetched', 'url', 'catalogue' ]
	&& is_int( $stored['fetched'] ) && $stored['fetched'] <= time() && $stored['fetched'] > time() - 30
	&& $stored['url'] === \Nino\Catalogue::DEFAULT_URL && $stored['catalogue'] === $catalogue );

$requests = [];
$cached = \Nino\Catalogue::cached( $appData );
check( 'cached() answers it without any request', $requests === [] && is_array( $cached ) );
check( 'the same shape fetch() returns, plus fetched', array_keys( $cached ) === [ 'format', 'generated', 'features', 'url', 'fetched' ]
	&& $cached['url'] === \Nino\Catalogue::DEFAULT_URL && $cached['fetched'] === $stored['fetched'] && count( $cached['features'] ) === 6
	&& $cached['format'] === $catalogue['format'] && $cached['generated'] === $catalogue['generated'] && $cached['features'] === $catalogue['features'] );

$movedUrl = $appData;
$movedUrl['/nino/catalogue/url'] = 'https://example.org/own/catalogue.json';
$requests = [];
check( 'a changed catalogue url invalidates the cache, and cached() never fetches either', \Nino\Catalogue::cached( $movedUrl ) === null && $requests === [] );

$offCached = $appData;
$offCached['/nino/catalogue/url'] = '';
check( 'switching the catalogue off makes it null too - \'\' never matches a url a fetch was ever made under', \Nino\Catalogue::cached( $offCached ) === null );

\Nino\Filesystem::putFileContent( $appData, '/data/catalogue.php', [ 'fetched' => time(), 'url' => \Nino\Catalogue::DEFAULT_URL, 'catalogue' => 'not a parsed catalogue' ] );
check( 'a broken file (one that does not hold what fetch() writes) makes it null', \Nino\Catalogue::cached( $appData ) === null );

\Nino\Filesystem::putFileContent( $appData, '/data/catalogue.php', [ 'url' => \Nino\Catalogue::DEFAULT_URL, 'catalogue' => $catalogue ] );
check( 'a file missing \'fetched\' makes it null too', \Nino\Catalogue::cached( $appData ) === null );

// Left as fetch() itself would leave it, for what follows below
\Nino\Filesystem::putFileContent( $appData, '/data/catalogue.php', [ 'fetched' => $stored['fetched'], 'url' => \Nino\Catalogue::DEFAULT_URL, 'catalogue' => $catalogue ] );

echo "\n";


// --- Catalogue::offers -------------------------------------------------------

echo "Catalogue::offers - what fits this kernel, beside what is on disk\n";

$offers = \Nino\Catalogue::offers( $appData, $catalogue );

check( 'one offer per key, sorted', array_keys( $offers ) === [ 'ancient', 'helper', 'needy', 'sample' ] );
check( 'the highest version this kernel can run is offered, not the highest there is', $offers['helper']['version'] === '1.1.0' && $offers['helper']['fits'] === true );
check( 'nothing on disk: available', $offers['helper']['state'] === 'available' && $offers['helper']['local'] === null && $offers['helper']['active'] === false && $offers['sample']['state'] === 'available' );
check( 'a feature no version of which fits is incompatible, shown with its newest version and what it asks for', $offers['ancient']['state'] === 'incompatible' && $offers['ancient']['fits'] === false && $offers['ancient']['nino'] === '^0.9'
	&& $offers['needy']['state'] === 'incompatible' && $offers['needy']['php']['ext'] === [ 'no_such_extension' ] );
check( 'the offer is the catalogue entry plus fits, local, active and state', array_keys( $offers['helper'] ) === [ 'key', 'name', 'description', 'category', 'version', 'nino', 'php', 'requires', 'directory', 'archive', 'sha256', 'size', 'released', 'fits', 'local', 'active', 'state' ] );

echo "\n";


// --- Catalogue::install ------------------------------------------------------

echo "Catalogue::install - from archive bytes to a directory below features/\n";

$staging = \Nino\Filesystem::path( $appData, '/data/.features' );

function stagingClean( string $staging ): bool {
	return is_dir( $staging ) === false || ( scandir( $staging ) ?: [] ) === [ '.', '..' ];
}

check( 'the features directory is writable here', \Nino\Catalogue::writable() === true );

check( 'a key the catalogue does not list', \Nino\Catalogue::install( $appData, 'nope', '1.0.0' ) === 'the catalogue does not list "nope" in version 1.0.0' );
check( 'a version the catalogue does not list', \Nino\Catalogue::install( $appData, 'helper', '1.0.1' ) === 'the catalogue does not list "helper" in version 1.0.1' );
check( 'a version that does not fit names what it asks for', \Nino\Catalogue::install( $appData, 'helper', '9.0.0' ) === '"helper" 9.0.0 requires Nino ^9.0'
	&& \Nino\Catalogue::install( $appData, 'needy', '1.0.0' ) === '"needy" 1.0.0 requires Nino ^1.0 and the php extensions no_such_extension' );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
$requests = [];
$result = \Nino\Catalogue::install( $appData, 'helper', '1.0.0' );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
check( 'without a writable features directory nothing is downloaded and the archive url is given for the manual way', $result === 'the features directory is not writable - download https://catalogue.test/features/helper-1.0.0.tar.gz and unpack it there by hand' && $requests === [] );

$requests = [];
$result = \Nino\Catalogue::install( $appData, 'helper', '1.0.0' );
check( 'helper 1.0.0 installs', $result === true );
check( 'the archive was fetched from the url the catalogue names, capped at its size', count( $requests ) === 1 && $requests[0]['url'] === 'https://catalogue.test/features/helper-1.0.0.tar.gz' && $requests[0]['options']['maxBytes'] === strlen( $helper100 ) && $requests[0]['options']['timeout'] === 60 );
check( 'the feature directory is in place with every file', is_file( NINO_FEATURES_DIR. '/Helper/feature.php' ) && is_file( NINO_FEATURES_DIR. '/Helper/Helper.php' ) && is_file( NINO_FEATURES_DIR. '/Helper/only-in-1.txt' ) );
check( 'the staging directory below data/ is left empty', stagingClean( $staging ) === true );

$all = \Nino\Features::all( $appData );
check( 'the registry sees it in the same request', isset( $all['helper'] ) && $all['helper']['version'] === '1.0.0' && $all['helper']['active'] === false && $all['helper']['problems'] === [] );

unset( $appData['./nino/catalogue'] );
$offers = \Nino\Catalogue::offers( $appData, \Nino\Catalogue::fetch( $appData ) );
check( 'the offer now says upgrade, with the local version', $offers['helper']['state'] === 'upgrade' && $offers['helper']['local'] === '1.0.0' && $offers['helper']['version'] === '1.1.0' );

check( 'the installed feature activates like one copied by hand', \Nino\Features::activate( $appData, 'helper' ) === true && \Nino\Features::get( $appData, 'helper' )['active'] === true );
check( 'its class is served from the features directory', class_exists( '\\Nino\\Modules\\Helper' ) === true && \Nino\Modules\Helper::VERSION === '1.0.0' );

echo "\n";


// --- Update ------------------------------------------------------------------

echo "Catalogue::install - an update replaces the directory\n";

$result = \Nino\Catalogue::install( $appData, 'helper', '1.1.0' );
check( 'helper 1.1.0 installs over 1.0.0', $result === true );
check( 'the new files are there, the file only 1.0.0 had is gone', is_file( NINO_FEATURES_DIR. '/Helper/Helper.php' ) && str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.1.0' ) && file_exists( NINO_FEATURES_DIR. '/Helper/only-in-1.txt' ) === false );
check( 'the old directory did not stay behind in staging', stagingClean( $staging ) === true );
$all = \Nino\Features::all( $appData );
check( 'the registry sees the new version and, since 1.0.0 was recorded on activation, an update to apply', $all['helper']['version'] === '1.1.0' && $all['helper']['installed'] === '1.0.0' && $all['helper']['update'] === true && $all['helper']['active'] === true );
unset( $appData['./nino/catalogue'] );
$offers = \Nino\Catalogue::offers( $appData, \Nino\Catalogue::fetch( $appData ) );
check( 'the offer says current', $offers['helper']['state'] === 'current' && $offers['helper']['local'] === '1.1.0' && $offers['helper']['active'] === true );

echo "\n";


// --- Refusals on the way ----------------------------------------------------

echo "Catalogue::install - what is refused, and that nothing is touched\n";

$before = (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' );

/**
 *	Publish one more entry pointing at bytes of its own, install it, and
 *	answer with the result - the catalogue is restored afterwards
 */
function tryInstall( array &$appData, array &$remote, array $features, string $privateKey, array $entry, ?string $bytes ): true|string {
	if( $bytes !== null )
		$remote[ $entry['archive'] ] = $bytes;
	publish( $appData, $remote, array_merge( $features, [ $entry ] ), null, $privateKey );
	$result = \Nino\Catalogue::install( $appData, $entry['key'], $entry['version'] );
	publish( $appData, $remote, $features, null, $privateKey );
	return $result;
}

$helper120 = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) );

$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $helper120. 'tampered' ), $helper120 );
check( 'an archive whose size is not what the catalogue says', $result === 'the archive does not match what the catalogue promised' );

$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $helper120, [ 'sha256' => str_repeat( '0', 64 ) ] ), $helper120 );
check( 'an archive whose digest is not what the catalogue says', $result === 'the archive does not match what the catalogue promised' );

$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $helper120, [ 'archive' => 'https://catalogue.test/features/gone.tar.gz' ] ), null );
check( 'an archive that cannot be fetched', $result === 'the archive could not be fetched: http 404' );

$bytes = 'this is not a tar.gz';
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'bytes that are not an archive', str_starts_with( (string) $result, 'the archive could not be read' ) === true );

$bytes = tarGz( [] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an empty archive', str_starts_with( (string) $result, 'the archive ' ) === true && $result !== true );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) + [ 'Other/' => null, 'Other/x.php' => '<?php' ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive holding something beside the feature directory', $result === 'the archive holds "Other" outside "Helper/"' );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) + [ '../evil.php' => '<?php' ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive with a path that climbs out of it', $result === 'the archive unpacked to more than the directory "Helper"' && file_exists( $staging. '/evil.php' ) === false && file_exists( dirname( $staging ). '/evil.php' ) === false );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) + [ 'Helper/link.php' => [ 'link' => '/etc/passwd' ] ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive with a symlink is refused or the link is disarmed - nothing links out of features/', ( $result === true && is_link( NINO_FEATURES_DIR. '/Helper/link.php' ) === false ) || ( $result !== true && file_exists( NINO_FEATURES_DIR. '/Helper/link.php' ) === false ) );
if( $result === true ) {
	// Put 1.1.0 back for the checks below
	check( '(restoring 1.1.0)', \Nino\Catalogue::install( $appData, 'helper', '1.1.0' ) === true );
}

$bytes = tarGz( featureFiles( 'Helper', 'other', '1.2.0' ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive holding another feature than promised', $result === 'the archive holds "other" 1.2.0, the catalogue promised "helper" 1.2.0' );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.1' ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive holding another version than promised', $result === 'the archive holds "helper" 1.2.1, the catalogue promised "helper" 1.2.0' );

$bytes = tarGz( [ 'Helper/' => null, 'Helper/feature.php' => '<?php return "nope";' ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive whose manifest does not validate', $result === 'the archive does not hold a valid feature' && ninoWarnings() === [] );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0', [ 'feature.php' => '<?php return [ \'key\' => \'helper\', \'name\' => \'Helper\', \'version\' => \'1.2.0\', \'nino\' => \'^9.0\', \'php\' => [ \'ext\' => [ \'no_such_extension\' ] ] ];' ] ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive whose manifest asks for more than the entry said, and does not fit here', $result === 'the archive holds "helper" 1.2.0, which requires Nino ^9.0 and the php extensions no_such_extension' );

$bytes = tarGz( featureFiles( 'Other', 'helper', '1.2.0' ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive whose directory is not the one the catalogue names', $result === 'the archive holds "Other" outside "Helper/"' );

check( 'after all of that the installed feature is untouched', (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ) === $before && file_exists( NINO_FEATURES_DIR. '/Helper/link.php' ) === false );
check( 'and staging is empty', stagingClean( $staging ) === true );
check( 'nothing but Helper below features/', ( scandir( NINO_FEATURES_DIR ) ?: [] ) === [ '.', '..', 'Helper' ] );

$requests = [];
publish( $appData, $remote, $features, sign( 'tampered', $privateKey ), $privateKey );
check( 'an install re-reads the catalogue and refuses one whose signature no longer holds', \Nino\Catalogue::install( $appData, 'sample', '3.0.0' ) === 'the catalogue signature does not verify' && count( $requests ) === 2 );

publish( $appData, $remote, $features, null, $privateKey );
check( 'a second feature installs beside the first', \Nino\Catalogue::install( $appData, 'sample', '3.0.0' ) === true && ( scandir( NINO_FEATURES_DIR ) ?: [] ) === [ '.', '..', 'Helper', 'Sample' ] );

echo "\n";

// --- The Features panel ------------------------------------------------------

echo "Features panel - catalogue and install over the same kernel\n";

/**
 *	Dispatch one action directly against the panel class, the way
 *	tests/features-smoke.php's callFeatures() does: the payload as the json
 *	"data" post field, the answer as [ statusCode, body ]
 *
 *	@param		array 		&$appData
 *	@param		string		$method				eg. "apiCatalogue"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body ]
 */
function callFeatures( array &$appData, string $method, array $data = [] ): array {
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	$_POST['data'] = json_encode( $data );
	\Nino\Modules\Features\Admin::{$method}( $appData, $request );
	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response']['body'] ];
}

/**
 *	One of the panel's own words, as its text file has it - what the panel
 *	answers where it phrases a message itself
 *
 *	@param		string		$locale				Interface language
 *	@param		string		$key					eg. '/_admin/features/error/catalogue-off'
 *	@param		string		$reason				What goes into its %s, if it has one
 *
 *	@return		string
 */
function panelWord( string $locale, string $key, string $reason = '' ): string {
	$fills = (array) include dirname( __DIR__ ). '/_admin/Nino/Modules/Features/text/'. $locale. '.php';
	return str_replace( '%s', $reason, (string) ( $fills['[['. $key. ']]'] ?? '' ) );
}

// The state the sections above left behind: Helper 1.1.0 on disk and active,
// recorded as 1.0.0 (so an update waits), Sample 3.0.0 on disk and off, and
// the catalogue listing helper 1.0.0/1.1.0/9.0.0, sample 3.0.0, ancient and
// needy. The panel reads its own words through the project path
// (Admin::textFills()), which here has to be the checkout's. The daily backup
// and the activity log the gate would otherwise run are off - not what this
// tests; the two accounts are the minimum: one without the permission, one
// with it
$appData['./nino/filesystem/path']	= dirname( __DIR__ );
$appData['/nino/admin/backups']			= false;
$appData['/nino/admin/logs']				= false;
$appData['/nino/auth/user']					= [];
$appData['/nino/auth/roles']				= [];
\Nino\Auth::insertUser( $appData, 'editor@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::insertUser( $appData, 'dev@example.com', 'correct horse battery staple', [ \Nino\Modules\Features\Admin::MANAGE_PERM ] );

$panelActions = [
	'apiCatalogue'	=> [],
	'apiInstall' 		=> [ 'key' => 'helper', 'version' => '1.1.0' ],
];

$requests = [];
foreach( $panelActions as $method => $data )
	check( $method. ' is 401 without an account', callFeatures( $appData, $method, $data ) === [ 401, [ 'error' => 'not logged in' ] ] );

\Nino\Auth::loginUser( $appData, 'editor@example.com', 'correct horse battery staple' );
foreach( $panelActions as $method => $data )
	check( $method. ' is 403 without the permission', callFeatures( $appData, $method, $data ) === [ 403, [ 'error' => 'not allowed' ] ] );
check( 'and neither asked the catalogue for anything', $requests === [] );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// The catalogue as the panel phrases it
publish( $appData, $remote, $features, null, $privateKey );
$requests = [];
[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'apiCatalogue reads the catalogue - two requests - and answers its url, its stamp, when it was fetched, whether features/ is writable, and one offer per key, sorted', $status === 200
	&& array_keys( $body ) === [ 'url', 'generated', 'fetched', 'writable', 'offers' ] && $body['url'] === \Nino\Catalogue::DEFAULT_URL && $body['generated'] === '2026-09-07T12:00:00Z' && $body['writable'] === true
	&& is_string( $body['fetched'] ) && $body['fetched'] !== '' && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $body['fetched'] ) === 1
	&& array_column( $requests, 'url' ) === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] && array_column( $body['offers'], 'key' ) === [ 'ancient', 'helper', 'needy', 'sample' ] );
$offers = array_column( $body['offers'], null, 'key' );

// apiList carries the same catalogue, straight from what apiCatalogue just
// cached - no further request, and its offers answer the same as apiCatalogue's
$requests = [];
[ $listStatus, $listBody ] = callFeatures( $appData, 'apiList' );
check( 'apiList carries the cached catalogue too - url, fetched and the same offers - with no request of its own', $listStatus === 200 && $requests === []
	&& is_array( $listBody['catalogue'] ) && array_keys( $listBody['catalogue'] ) === [ 'url', 'fetched', 'offers' ]
	&& $listBody['catalogue']['url'] === \Nino\Catalogue::DEFAULT_URL && $listBody['catalogue']['fetched'] === $body['fetched'] && $listBody['catalogue']['offers'] === $body['offers'] );
check( 'every offer has the same keys, the extension list flattened to ext', array_keys( $offers['helper'] ) === [ 'key', 'name', 'description', 'category', 'version', 'nino', 'ext', 'requires', 'directory', 'archive', 'size', 'released', 'state', 'fits', 'local', 'active' ] );
check( 'names and descriptions arrive in the session locale - de_DE, the native language, since none was chosen', $offers['helper']['name'] === 'Helper' && $offers['helper']['description'] === 'Ein helper' && $offers['sample']['description'] === 'Ein sample' );
check( 'the state travels with each offer: helper current and active, sample current and off, ancient and needy incompatible with what they ask for',
	$offers['helper']['state'] === 'current' && $offers['helper']['version'] === '1.1.0' && $offers['helper']['local'] === '1.1.0' && $offers['helper']['active'] === true && $offers['helper']['fits'] === true
	&& $offers['sample']['state'] === 'current' && $offers['sample']['local'] === '3.0.0' && $offers['sample']['active'] === false
	&& $offers['ancient']['state'] === 'incompatible' && $offers['ancient']['fits'] === false && $offers['ancient']['local'] === null && $offers['ancient']['nino'] === '^0.9' && $offers['ancient']['ext'] === []
	&& $offers['needy']['state'] === 'incompatible' && $offers['needy']['nino'] === '^1.0' && $offers['needy']['ext'] === [ 'no_such_extension' ] );
check( 'the archive url, size, directory and release date pass through', $offers['sample']['archive'] === 'https://catalogue.test/features/sample-3.0.0.tar.gz' && $offers['sample']['size'] === strlen( $sample300 )
	&& $offers['sample']['directory'] === 'Sample' && $offers['sample']['released'] === '2026-09-07' && $offers['sample']['requires'] === [] );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
unset( $appData['./nino/features/all'] );
[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
unset( $appData['./nino/features/all'] );
check( 'without a writable features directory the answer says so, and what is not seen on disk is available', $status === 200 && $body['writable'] === false && array_column( $body['offers'], 'state', 'key' )['helper'] === 'available' );

// The two ways the configuration rules the catalogue out are the panel's
// own words, in the interface language; anything else is the kernel's
// sentence behind the panel's phrase
$off = $appData;
$off['/nino/catalogue/url'] = '';
check( 'switched off: a 400 in the panel\'s own words, in the session locale', callFeatures( $off, 'apiCatalogue' ) === [ 400, [ 'error' => panelWord( 'de_DE', '/_admin/features/error/catalogue-off' ) ] ] && panelWord( 'de_DE', '/_admin/features/error/catalogue-off' ) !== '' );

// The keyless refusal exists only while the kernel ships no key of its own
// (see the same case above); with one, an empty configured key is no error
$noKey = $appData;
$noKey['/nino/catalogue/key'] = '';
$requests = [];
if( \Nino\Catalogue::PUBLIC_KEY === '' )
	check( 'no key: a 400 in the panel\'s words, and nothing was fetched', callFeatures( $noKey, 'apiCatalogue' ) === [ 400, [ 'error' => panelWord( 'de_DE', '/_admin/features/error/catalogue-key' ) ] ] && $requests === [] );
else
	check( 'the kernel ships a key, so the panel has no keyless refusal to give - but the phrase for it exists', panelWord( 'de_DE', '/_admin/features/error/catalogue-key' ) !== '' );

\Nino\Runtime::setSessionValue( $appData, './admin/locale', 'en_US' );
check( 'the words follow the interface language', callFeatures( $off, 'apiCatalogue' ) === [ 400, [ 'error' => panelWord( 'en_US', '/_admin/features/error/catalogue-off' ) ] ] && panelWord( 'en_US', '/_admin/features/error/catalogue-off' ) !== panelWord( 'de_DE', '/_admin/features/error/catalogue-off' ) );
\Nino\Runtime::unsetSessionValue( $appData, './admin/locale' );

publish( $appData, $remote, $features, sign( 'tampered', $privateKey ), $privateKey );
check( 'any other reason is the kernel\'s own sentence behind the panel\'s phrase', callFeatures( $appData, 'apiCatalogue' ) === [ 400, [ 'error' => panelWord( 'de_DE', '/_admin/features/error/catalogue-reason', 'the catalogue signature does not verify' ) ] ] );

unset( $remote[ \Nino\Catalogue::DEFAULT_URL ], $remote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
unset( $appData['./nino/catalogue'] );
check( '...a catalogue that is not there included', callFeatures( $appData, 'apiCatalogue' ) === [ 400, [ 'error' => panelWord( 'de_DE', '/_admin/features/error/catalogue-reason', 'the catalogue could not be fetched: http 404' ) ] ] );

// Install through the action
publish( $appData, $remote, $features, null, $privateKey );

$requests = [];
check( 'a malformed or missing key or version is a 400 before the kernel is asked', callFeatures( $appData, 'apiInstall', [ 'key' => 'Helper', 'version' => '1.1.0' ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => '../etc', 'version' => '1.1.0' ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.1' ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.1.0; rm' ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'helper' ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'version' => '1.1.0' ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => [ 'helper' ], 'version' => [ '1.1.0' ] ] ) === [ 400, [ 'error' => 'no feature key and version posted' ] ]
	&& $requests === [] );
check( 'a version the catalogue does not list is a 400 carrying the kernel\'s reason', callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.0.1' ] ) === [ 400, [ 'error' => 'the catalogue does not list "helper" in version 1.0.1' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'nope', 'version' => '1.0.0' ] ) === [ 400, [ 'error' => 'the catalogue does not list "nope" in version 1.0.0' ] ] );
check( 'so is one that does not fit this kernel', callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '9.0.0' ] ) === [ 400, [ 'error' => '"helper" 9.0.0 requires Nino ^9.0' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'needy', 'version' => '1.0.0' ] ) === [ 400, [ 'error' => '"needy" 1.0.0 requires Nino ^1.0 and the php extensions no_such_extension' ] ] );
check( 'none of that downloaded anything', array_filter( $requests, static fn( array $r ): bool => str_ends_with( $r['url'], '.tar.gz' ) ) === [] );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
unset( $appData['./nino/features/all'] );
$result = callFeatures( $appData, 'apiInstall', [ 'key' => 'sample', 'version' => '3.0.0' ] );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
unset( $appData['./nino/features/all'] );
check( 'without a writable features directory the kernel\'s refusal, naming the archive for the manual way, passes through', $result === [ 400, [ 'error' => 'the features directory is not writable - download https://catalogue.test/features/sample-3.0.0.tar.gz and unpack it there by hand' ] ] );

// A feature that is not on disk yet: placed, and nothing more
$extra100 = tarGz( featureFiles( 'Extra', 'extra', '1.0.0' ) );
$remote['https://catalogue.test/features/extra-1.0.0.tar.gz'] = $extra100;
$withExtra = array_merge( $features, [ entry( 'extra', '1.0.0', $extra100, [ 'name' => [ 'en_US' => 'Extra', 'de_DE' => 'Zusatz' ] ] ) ] );
publish( $appData, $remote, $withExtra, null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'a feature not on disk is offered as available, its name in the session locale', $status === 200 && array_column( $body['offers'], 'state', 'key' )['extra'] === 'available' && array_column( $body['offers'], 'name', 'key' )['extra'] === 'Zusatz' );

$requests = [];
[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'extra', 'version' => '1.0.0' ] );
check( 'installing it answers its entry as the list shows it now - on disk, off, nothing recorded - and that no update was applied', $status === 200 && array_keys( $body ) === [ 'feature', 'updated', 'required' ] && $body['updated'] === false && $body['required'] === []
	&& array_keys( $body['feature'] ) === [ 'key', 'name', 'description', 'manual', 'category', 'version', 'installed', 'active', 'update', 'requires', 'problems', 'settings' ]
	&& $body['feature']['key'] === 'extra' && $body['feature']['name'] === 'Extra' && $body['feature']['version'] === '1.0.0' && $body['feature']['active'] === false && $body['feature']['installed'] === null && $body['feature']['update'] === false && $body['feature']['problems'] === [] );
check( 'the directory is in place, the archive was fetched once, and nothing was switched on', is_file( NINO_FEATURES_DIR. '/Extra/feature.php' ) && is_file( NINO_FEATURES_DIR. '/Extra/Extra.php' )
	&& count( array_filter( $requests, static fn( array $r ): bool => $r['url'] === 'https://catalogue.test/features/extra-1.0.0.tar.gz' ) ) === 1
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [ '\\Nino\\Modules\\Helper' ] && isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['extra'] ) === false );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'and the catalogue now says installed', $status === 200 && array_column( $body['offers'], 'state', 'key' )['extra'] === 'current' && array_column( $body['offers'], 'local', 'key' )['extra'] === '1.0.0' && array_column( $body['offers'], 'active', 'key' )['extra'] === false );

// An active feature: the new version replaces the directory and, since it
// is active, its update is applied in the same request - the record moves
$helper120 = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) );
$remote['https://catalogue.test/features/helper-1.2.0.tar.gz'] = $helper120;
$withHelper120 = array_merge( $withExtra, [ entry( 'helper', '1.2.0', $helper120 ) ] );
publish( $appData, $remote, $withHelper120, null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'the offer for an active feature on disk in an older version says upgrade, with the local version', $status === 200 && array_column( $body['offers'], 'state', 'key' )['helper'] === 'upgrade'
	&& array_column( $body['offers'], 'local', 'key' )['helper'] === '1.1.0' && array_column( $body['offers'], 'version', 'key' )['helper'] === '1.2.0' && array_column( $body['offers'], 'active', 'key' )['helper'] === true );
check( 'before: on disk as 1.1.0, recorded as 1.0.0, an update waiting', \Nino\Features::get( $appData, 'helper' )['version'] === '1.1.0' && \Nino\Features::get( $appData, 'helper' )['installed'] === '1.0.0' && \Nino\Features::get( $appData, 'helper' )['update'] === true );

[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.2.0' ] );
check( 'updating an active feature places the new version and applies the update: the entry is 1.2.0, recorded as 1.2.0, no update waiting, still active', $status === 200 && $body['updated'] === true
	&& $body['feature']['version'] === '1.2.0' && $body['feature']['installed'] === '1.2.0' && $body['feature']['update'] === false && $body['feature']['active'] === true && $body['feature']['problems'] === [] );
check( 'config.php records the version, and the files are the new ones', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['helper']['version'] === '1.2.0'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'] === [ '\\Nino\\Modules\\Helper' ]
	&& str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.2.0' ) && stagingClean( $staging ) === true );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'the offer says current now', $status === 200 && array_column( $body['offers'], 'state', 'key' )['helper'] === 'current' && array_column( $body['offers'], 'local', 'key' )['helper'] === '1.2.0' );

// An update whose new version cannot be activated here: the files are
// placed - the archive fits this kernel, but its manifest now requires a
// feature that is not in the directory, which only an activation can
// know - and the answer says both, in the panel's words
$helper130 = tarGz( featureFiles( 'Helper', 'helper', '1.3.0', [ 'feature.php' => '<?php return [ \'key\' => \'helper\', \'name\' => \'Helper\', \'version\' => \'1.3.0\', \'requires\' => [ \'nowhere\' ] ];' ] ) );
$remote['https://catalogue.test/features/helper-1.3.0.tar.gz'] = $helper130;
publish( $appData, $remote, array_merge( $withHelper120, [ entry( 'helper', '1.3.0', $helper130 ) ] ), null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.3.0' ] );
check( 'an update the kernel refuses to apply is a 400 saying the new files are in place, with the kernel\'s reason', $status === 400
	&& str_starts_with( $body['error'], panelWord( 'de_DE', '/_admin/features/error/update-after-install', 'feature "helper" cannot be activated: ' ) ) && str_contains( $body['error'], '"nowhere"' ) );
check( 'the directory is the new one, the record stayed at 1.2.0, and the list shows the problem', str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.3.0' )
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['helper']['version'] === '1.2.0'
	&& \Nino\Features::get( $appData, 'helper' )['version'] === '1.3.0' && \Nino\Features::get( $appData, 'helper' )['update'] === true && \Nino\Features::get( $appData, 'helper' )['problems'] !== [] );

check( 'the activity log names the feature and the version, and a catalogue read logs nothing', \Nino\Modules\Features\Admin::log( 'features/install', [ 'key' => 'helper', 'version' => '1.2.0' ] ) === 'Install feature "helper" 1.2.0'
	&& \Nino\Modules\Features\Admin::log( 'features/catalogue', [] ) === '' );

echo "\n";


// --- Requirements, resolved by the install ---------------------------------

echo "Catalogue::install - a feature brings what it requires\n";

// 'top' requires 'middle', 'middle' requires 'bottom', and none of the three
// is on disk. One Install has to place all of them, deepest first
$bottom	= tarGz( featureFiles( 'Bottom', 'bottom', '1.0.0' ) );
$middle	= tarGz( featureFiles( 'Middle', 'middle', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'middle\', \'name\' => \'Middle\', \'version\' => \'1.0.0\', \'requires\' => [ \'bottom\' ] ];' ] ) );
$top		= tarGz( featureFiles( 'Top', 'top', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'top\', \'name\' => \'Top\', \'version\' => \'1.0.0\', \'requires\' => [ \'middle\' ] ];' ] ) );

$remote['https://catalogue.test/features/bottom-1.0.0.tar.gz'] = $bottom;
$remote['https://catalogue.test/features/middle-1.0.0.tar.gz'] = $middle;
$remote['https://catalogue.test/features/top-1.0.0.tar.gz']		= $top;

$chained = array_merge( $withHelper120, [
	entry( 'helper', '1.3.0', $helper130 ),
	entry( 'bottom', '1.0.0', $bottom ),
	entry( 'middle', '1.0.0', $middle, [ 'requires' => [ 'bottom' ], 'directory' => 'Middle' ] ),
	entry( 'top', '1.0.0', $top, [ 'requires' => [ 'middle' ], 'directory' => 'Top' ] ),
] );
publish( $appData, $remote, $chained, null, $privateKey );

$requests = [];
[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'top', 'version' => '1.0.0' ] );
check( 'one Install places the whole chain, and the answer names what came along', $status === 200 && $body['required'] === [ 'bottom', 'middle' ]
	&& is_file( NINO_FEATURES_DIR. '/Top/feature.php' ) && is_file( NINO_FEATURES_DIR. '/Middle/feature.php' ) && is_file( NINO_FEATURES_DIR. '/Bottom/feature.php' ) );
// Deepest first, because the panel activates what it installed and an
// activation refuses a feature whose requirement is not in the directory
check( 'deepest first, one archive each', array_values( array_map( static fn( array $r ): string => basename( (string) $r['url'] ), array_filter( $requests, static fn( array $r ): bool => str_ends_with( (string) $r['url'], '.tar.gz' ) ) ) ) === [ 'bottom-1.0.0.tar.gz', 'middle-1.0.0.tar.gz', 'top-1.0.0.tar.gz' ] );
check( 'and the chain can be switched on in one step, which is what placing it in that order was for', \Nino\Features::activate( $appData, 'top' ) === true
	&& \Nino\Features::get( $appData, 'top' )['active'] === true && \Nino\Features::get( $appData, 'bottom' )['active'] === true );

// A requirement already on disk is left exactly as it is - an install is not
// the moment to update something a project chose to keep
$requests = [];
$another = tarGz( featureFiles( 'Another', 'another', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'another\', \'name\' => \'Another\', \'version\' => \'1.0.0\', \'requires\' => [ \'bottom\' ] ];' ] ) );
$remote['https://catalogue.test/features/another-1.0.0.tar.gz'] = $another;
publish( $appData, $remote, array_merge( $chained, [ entry( 'another', '1.0.0', $another, [ 'requires' => [ 'bottom' ], 'directory' => 'Another' ] ) ] ), null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'another', 'version' => '1.0.0' ] );
check( 'a requirement the project already carries is not fetched again', $status === 200 && $body['required'] === []
	&& count( array_filter( $requests, static fn( array $r ): bool => str_ends_with( (string) $r['url'], '.tar.gz' ) ) ) === 1 );

// A requirement the catalogue cannot serve: refused whole, with nothing
// placed - half an installation is worse than none
$orphan = tarGz( featureFiles( 'Orphan', 'orphan', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'orphan\', \'name\' => \'Orphan\', \'version\' => \'1.0.0\', \'requires\' => [ \'nowhere\' ] ];' ] ) );
$remote['https://catalogue.test/features/orphan-1.0.0.tar.gz'] = $orphan;
publish( $appData, $remote, array_merge( $chained, [ entry( 'orphan', '1.0.0', $orphan, [ 'requires' => [ 'nowhere' ], 'directory' => 'Orphan' ] ) ] ), null, $privateKey );

$requests = [];
$result = callFeatures( $appData, 'apiInstall', [ 'key' => 'orphan', 'version' => '1.0.0' ] );
check( 'a requirement the catalogue does not list refuses the whole install, naming it', $result === [ 400, [ 'error' => 'required feature "nowhere": the catalogue lists no "nowhere" this kernel can run' ] ]
	&& is_dir( NINO_FEATURES_DIR. '/Orphan' ) === false
	&& array_filter( $requests, static fn( array $r ): bool => str_ends_with( (string) $r['url'], '.tar.gz' ) ) === [] );

echo "\n";


// --- Removing a feature's directory ----------------------------------------

echo "Features::remove - the step deactivating leaves out\n";

check( 'an active feature is refused, and its directory stays', callFeatures( $appData, 'apiRemove', [ 'key' => 'top' ] ) === [ 400, [ 'error' => 'feature "top" is active - switch it off before removing it' ] ]
	&& is_dir( NINO_FEATURES_DIR. '/Top' ) === true );
check( 'a key nothing carries is a 400 before the kernel is asked', callFeatures( $appData, 'apiRemove', [ 'key' => 'nowhere' ] ) === [ 400, [ 'error' => 'unknown feature' ] ]
	&& callFeatures( $appData, 'apiRemove', [ 'key' => '../../etc' ] ) === [ 400, [ 'error' => 'unknown feature' ] ] );

\Nino\Features::deactivate( $appData, 'top' );

[ $status, $body ] = callFeatures( $appData, 'apiRemove', [ 'key' => 'top' ] );
check( 'an inactive one goes, directory and all', $status === 200 && $body === [ 'removed' => 'top' ]
	&& is_dir( NINO_FEATURES_DIR. '/Top' ) === false && \Nino\Features::get( $appData, 'top' ) === null );
// The same rule deactivation follows: what a feature left behind is the
// project's now, and putting the feature back finds it again
check( 'what it recorded stays - a removal is not an uninstall', isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['top'] ) === true );
check( 'and the class is not in /nino/modules either way', in_array( '\\Nino\\Modules\\Top', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === false );
check( 'the activity log names it', \Nino\Modules\Features\Admin::log( 'features/remove', [ 'key' => 'top' ] ) === 'Remove the directory of feature "top"' );

echo "\n";

ninoDone( $appData );
