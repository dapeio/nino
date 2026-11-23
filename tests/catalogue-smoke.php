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
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-catalogue-features-'. bin2hex( random_bytes( 6 ) ) );
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
		'nino'				=> '^2.0',
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
function publish( array &$appData, array &$remote, array $features, ?string $signWith, string $privateKey, array $packages = [] ): string {
	$json = ninoCatalogue( $features, [], $packages, '2026-09-07T12:00:00Z' );
	$remote[ \Nino\Catalogue::DEFAULT_URL ]					= $json;
	$remote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ]	= $signWith === null ? sign( $json, $privateKey ) : $signWith;
	unset( $appData['./nino/catalogue'] );
	return $json;
}

/**
 *	Publish a document as it is, signed with the run's key
 */
function publishRaw( array &$appData, array &$remote, string $json, string $privateKey ): void {
	$remote[ \Nino\Catalogue::DEFAULT_URL ]					= $json;
	$remote[ \Nino\Catalogue::DEFAULT_URL. '.sig' ]	= sign( $json, $privateKey );
	unset( $appData['./nino/catalogue'] );
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

/*	The other client: where the curl extension is not there, Fetch falls back
	to php's own stream wrapper - and that half had no answer for a server that
	sends its headers, part of a body and then goes quiet. fread() reports a
	used-up read timeout by answering false, the loop took that for the end of
	the body, and the truncated answer came back as ok with status 200: a
	catalogue that is half a json document, an archive that is half an archive,
	both described as complete. The curl half reports the same stall as a
	failed transfer (curl_exec() answers false), with no body and the reason in
	'error', which is the shape this one answers in now.

	Driven against a local socket server rather than a stub: a read timeout is
	the socket's own behaviour and nothing above it can stand in for it. The
	server is given as a script because it has to stall in a process of its
	own while this one reads	*/
$stallServer = $sandbox. '/stall-server.php';
file_put_contents( $stallServer, <<<'PHP'
<?php
declare(strict_types=1);
// One connection, answered as argv[1] says: 'stall' sends headers and half a
// body and then goes quiet past any timeout, 'complete' answers in full
$server = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr );
if( $server === false )
	exit( 1 );

echo stream_socket_get_name( $server, false ), "\n";

$client = stream_socket_accept( $server, 10 );
if( $client === false )
	exit( 1 );

stream_set_timeout( $client, 5 );
while( ( $line = fgets( $client, 8192 ) ) !== false )
	if( trim( $line ) === '' )
		break;

if( ( $argv[1] ?? '' ) === 'stall' ) {
	fwrite( $client, "HTTP/1.1 200 OK\r\nContent-Length: 100\r\n\r\nHALF-A-BODY" );
	sleep( 20 );
	exit( 0 );
}

fwrite( $client, "HTTP/1.1 200 OK\r\nContent-Length: 12\r\n\r\nWHOLE-ANSWER" );
fclose( $client );
PHP );

/**
 *	Start the server above and answer the address it is listening on
 *
 *	@param		string		$mode					'stall' or 'complete'
 *	@param		mixed			&$process			(reference) The handle, for proc_terminate()
 *
 *	@return 	string									'127.0.0.1:<port>', or '' where it did not start
 */
function stallServer( string $script, string $mode, mixed &$process ): string {

	$pipes		= [];
	$process	= proc_open( [ PHP_BINARY, $script, $mode ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );

	if( is_resource( $process ) === false )
		return '';

	$address = trim( (string) fgets( $pipes[1] ) );

	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return $address;
}

$streamClient = new ReflectionMethod( '\Nino\Fetch', '_stream' );
$streamClient->setAccessible( true );

$stalledProcess	= null;
$stalledAddress	= stallServer( $stallServer, 'stall', $stalledProcess );
$stalled				= ( $stalledAddress !== '' ) ? (array) $streamClient->invoke( null, 'http://'. $stalledAddress. '/', 1, \Nino\Fetch::DEFAULT_MAX_BYTES ) : [];

if( is_resource( $stalledProcess ) === true ) {
	proc_terminate( $stalledProcess );
	proc_close( $stalledProcess );
}

check( 'an answer that stops arriving mid-body is not ok', ( $stalled['ok'] ?? null ) === false );
check( '...and carries the reason instead of the half it did read', ( $stalled['body'] ?? 'unset' ) === ''
	&& str_contains( (string) ( $stalled['error'] ?? '' ), 'timed out' ) === true );
check( '...while still reporting the status the server did send', ( $stalled['status'] ?? null ) === 200 );

$completeProcess	= null;
$completeAddress	= stallServer( $stallServer, 'complete', $completeProcess );
$complete					= ( $completeAddress !== '' ) ? (array) $streamClient->invoke( null, 'http://'. $completeAddress. '/', 5, \Nino\Fetch::DEFAULT_MAX_BYTES ) : [];

if( is_resource( $completeProcess ) === true ) {
	proc_terminate( $completeProcess );
	proc_close( $completeProcess );
}

check( 'an answer that does arrive is still ok, with the body', ( $complete['ok'] ?? null ) === true
	&& ( $complete['body'] ?? '' ) === 'WHOLE-ANSWER' && ( $complete['error'] ?? 'x' ) === '' );

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
check( 'without a configured key the kernel\'s own is used', \Nino\Catalogue::key( $config ) === \Nino\Catalogue::PUBLIC_KEY );
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

/**
 *	One entry of a feature as the catalogue has it, from one written flat the way the helpers of this suite do
 */
function flat2( array $flat ): array {
	return json_decode( ninoCatalogue( [ $flat ] ), true )['packages'][0];
}

/**
 *	A document of format 2 from entries that are written out in full
 */
function document( array $packages, array $more = [] ): string {
	return (string) json_encode( [ 'format' => 2, 'generated' => 'now', 'packages' => $packages ] + $more, JSON_UNESCAPED_SLASHES );
}

/**
 *	The entry of a theme and of a package of blocks
 */
function packageEntry( string $kind, string $key, string $version, string $bytes, array $more = [] ): array {
	$preview = 'RIFF'. pack( 'V', 12 ). 'WEBPVP8 ';
	return $more + [
		'kind'				=> $kind,
		'key'					=> $key,
		'version'			=> $version,
		'name'				=> [ 'en_US' => ucfirst( $key ), 'de_DE' => ucfirst( $key ) ],
		'description'	=> [ 'en_US' => 'A '. $key ],
		'requires'		=> [ 'nino' => '^2.0', 'features' => [] ],
		'blocks'			=> [ [ 'key' => 'one', 'category' => 'offer' ] ],
		'preview'			=> [ 'url' => 'https://catalogue.test/previews/'. $key. '.webp', 'sha256' => hash( 'sha256', $preview ), 'size' => strlen( $preview ) ],
		'archive'			=> 'https://catalogue.test/'. $kind. '/'. $key. '-'. $version. '.tar.gz',
		'sha256'			=> hash( 'sha256', $bytes ),
		'size'				=> strlen( $bytes ),
		'released'		=> '2026-11-17',
	];
}

$good = entry( 'helper', '1.0.0', 'bytes' );
$goodEntry = flat2( $good );

check( 'not json', \Nino\Catalogue::parse( '{' ) === 'the catalogue is not valid json' );
check( 'FORMAT is 2', \Nino\Catalogue::FORMAT === 2 );
check( 'a catalogue of format 1 is the sentence that says which format this kernel reads - and lists nothing',
	\Nino\Catalogue::parse( '{"format":1,"features":[]}' ) === 'the catalogue has format 1, this kernel reads format 2'
	&& \Nino\Catalogue::parse( '{"packages":[]}' ) === 'the catalogue has format null, this kernel reads format 2' );
check( 'no list of packages', \Nino\Catalogue::parse( '{"format":2}' ) === 'the catalogue lists no packages' );

$parsed = \Nino\Catalogue::parse( document( [ $goodEntry ] ) );
check( 'a valid document parses to format, generated, the entries, the ones left out and the applications', is_array( $parsed ) && array_keys( $parsed ) === [ 'format', 'generated', 'packages', 'unreadable', 'applications' ]
	&& $parsed['format'] === 2 && $parsed['generated'] === 'now' && count( $parsed['packages'] ) === 1 && $parsed['unreadable'] === [] );
check( 'a feature is normalized: every key present, in order', array_keys( $parsed['packages'][0] ) === [ 'kind', 'key', 'name', 'description', 'version', 'nino', 'php', 'requires', 'archive', 'sha256', 'size', 'released', 'preview', 'category', 'maturity', 'directory' ]
	&& $parsed['packages'][0]['kind'] === 'feature' && $parsed['packages'][0]['preview'] === null );

$parsed = \Nino\Catalogue::parse( document( [ [ 'kind' => 'feature', 'key' => 'x', 'name' => 'X', 'version' => '1.0.0', 'directory' => 'X', 'archive' => 'https://c.test/x.tar.gz', 'sha256' => strtoupper( str_repeat( 'ab', 32 ) ), 'size' => 1,
	'requires' => [ 'php' => [ 'Mbstring' ] ], 'released' => str_repeat( 'd', 40 ) ] ] ) );
check( 'what is missing gets its default, digests and extensions are lowercased, released is cut', is_array( $parsed ) && count( $parsed['packages'] ) === 1
	&& $parsed['packages'][0]['nino'] === '*' && $parsed['packages'][0]['description'] === '' && $parsed['packages'][0]['requires'] === []
	&& $parsed['packages'][0]['category'] === '' && $parsed['packages'][0]['maturity'] === ''
	&& $parsed['packages'][0]['sha256'] === str_repeat( 'ab', 32 ) && $parsed['packages'][0]['php']['ext'] === [ 'mbstring' ] && strlen( $parsed['packages'][0]['released'] ) === 32 );

/*	A catalogue entry is a published manifest, so this reader and
	Features::manifest() have to agree about the fields they both read:
	a feature that lists itself, or lists the same key twice, is the panel
	showing a requirement on itself and a requirement twice	*/
$selfNeedy = \Nino\Catalogue::parse( ninoCatalogue( [ [ 'key' => 'x', 'name' => 'X', 'version' => '1.0.0', 'directory' => 'X',
	'archive' => 'https://c.test/x.tar.gz', 'sha256' => str_repeat( 'ab', 32 ), 'size' => 1, 'requires' => [ 'x', 'helper', 'helper', 'other' ] ] ] ) );
check( 'a requirement on itself, and a requirement listed twice, are both dropped - the same two steps Features::manifest() takes', is_array( $selfNeedy )
	&& $selfNeedy['packages'][0]['requires'] === [ 'helper', 'other' ] );

// And the vocabulary itself is one definition rather than two. A feature's directory, key, version, category and extension names are spelled once, as
// \Nino\Features' public patterns; this class, the manifest reader and the Features panel name the constant
$catalogueClass = new ReflectionClass( '\Nino\Catalogue' );
check( 'a key, a version and a category are \Nino\Features\' vocabulary, not a second copy of it', array_filter( [ 'KEY_PATTERN', 'VERSION_PATTERN', 'CATEGORY_PATTERN' ],
	static fn( string $name ): bool => $catalogueClass->getReflectionConstant( $name ) !== false ) === [] );

$vocabulary = array_filter( ( new ReflectionClass( '\Nino\Features' ) )->getConstants( ReflectionClassConstant::IS_PUBLIC ),
	static fn( mixed $value, string $name ): bool => str_ends_with( $name, '_PATTERN' ) === true && is_string( $value ) === true, ARRAY_FILTER_USE_BOTH );
$respelled = [];
foreach( [ '_nino/Nino/Features/Features.php' => 1, '_nino/Nino/Catalogue/Catalogue.php' => 0, '_admin/Nino/Modules/Features/Admin/Admin.php' => 0 ] as $file => $allowed ) {
	$source = (string) file_get_contents( dirname( __DIR__ ). '/'. $file );
	foreach( $vocabulary as $name => $pattern )
		if( substr_count( $source, "'". $pattern. "'" ) > $allowed )
			$respelled[] = $file. ' '. $name;
}
check( 'a feature\'s directory, key, version, category and extension names are spelled once, and every reader names the constant'. ( $respelled === [] ? '' : ' - written out again: '. implode( ', ', $respelled ) ),
	isset( $vocabulary['DIRECTORY_PATTERN'], $vocabulary['EXTENSION_PATTERN'] ) === true && $respelled === [] );

// The fields a bad value does not cost the entry: a category is a heading in a list, a maturity a word beside a name
$parsed = \Nino\Catalogue::parse( ninoCatalogue( [
	entry( 'named', '1.0.0', 'a', [ 'category' => 'security' ] ),
	entry( 'later', '1.0.0', 'b', [ 'category' => 'somethingnewerthanthis' ] ),
	entry( 'broken', '1.0.0', 'c', [ 'category' => [ 'not', 'a', 'slug' ] ] ),
	entry( 'tagged', '1.0.0', 'a', [ 'maturity' => 'Beta' ] ),
	entry( 'tagmap', '1.0.0', 'b', [ 'maturity' => [ 'en_US' => 'Example', 'de_DE' => 'Beispiel' ] ] ),
	entry( 'toolong', '1.0.0', 'c', [ 'maturity' => str_repeat( 'a', 25 ) ] ),
	entry( 'notstring', '1.0.0', 'e', [ 'maturity' => [ 'nested' => [ 'Beta' ] ] ] ),
] ) );
check( 'a category and a maturity travel with the entry, and an unusable one is dropped rather than costing the entry', is_array( $parsed ) && count( $parsed['packages'] ) === 7 && $parsed['unreadable'] === []
	&& array_column( $parsed['packages'], 'category' ) === [ 'security', 'somethingnewerthanthis', '', '', '', '', '' ]
	&& $parsed['packages'][3]['maturity'] === 'Beta' && $parsed['packages'][4]['maturity'] === [ 'en_US' => 'Example', 'de_DE' => 'Beispiel' ]
	&& array_column( array_slice( $parsed['packages'], 5 ), 'maturity' ) === [ '', '' ] );

$parsed = \Nino\Catalogue::parse( document( [ $goodEntry, $goodEntry, flat2( entry( 'helper', '1.1.0', 'other' ) ) ] ) );
check( 'the same kind, key and version twice counts once, another version counts', is_array( $parsed ) && count( $parsed['packages'] ) === 2 );

// What a kind this reader does not know is, and what an entry that is not what its kind says
$parsed = \Nino\Catalogue::parse( document( [ $goodEntry, [ 'kind' => 'font', 'key' => 'lora', 'anything' => [ 'at', 'all' ] ], flat2( entry( 'other', '1.0.0', 'o' ) ) ] ) );
check( 'a kind this kernel does not know is skipped: no entry, and not an entry that was left out', is_array( $parsed ) && array_column( $parsed['packages'], 'key' ) === [ 'helper', 'other' ] && $parsed['unreadable'] === [] );

$refusals = [
	'"key" must be a slug'																								=> [ 'key' => 'Helper' ],
	'"name" must be a string or a locale => string map'							=> [ 'name' => '' ],
	'"description" must be a string or a locale => string map'			=> [ 'description' => [ 'en_US' => 5 ] ],
	'"version" must be major.minor.patch'														=> [ 'version' => '1.0' ],
	'"requires" => "nino" must be a version constraint'							=> [ 'requires' => [ 'nino' => 'latest' ] ],
	'"requires" => "php" must list extension names'									=> [ 'requires' => [ 'php' => [ 'no such' ] ] ],
	'"requires" => "features" must list feature keys'								=> [ 'requires' => [ 'features' => [ 'Nope' ] ] ],
	'"requires" must be an object'																	=> [ 'requires' => [ 'nino', 'php' ] ],
	'"directory" must be a class name segment'											=> [ 'directory' => 'helper' ],
	'"archive" must be an https url'																=> [ 'archive' => 'http://catalogue.test/x.tar.gz' ],
	'"sha256" must be the hex digest of the archive'								=> [ 'sha256' => 'abc' ],
	'"size" must be the archive size in bytes, at most 8388608'			=> [ 'size' => 8 * 1024 * 1024 + 1 ],
];
$allRefused = true;
foreach( $refusals as $why => $wrong ) {
	$result = \Nino\Catalogue::parse( document( [ $goodEntry, array_merge( $goodEntry, $wrong ) ] ) );
	$expectKey = isset( $wrong['key'] ) === true ? '' : 'helper';
	if( is_array( $result ) === false || count( $result['packages'] ) !== 1 || $result['unreadable'] !== [ [ 'index' => 1, 'kind' => 'feature', 'key' => $expectKey, 'reason' => $why ] ] ) {
		$allRefused = false;
		echo "        got: ". json_encode( $result['unreadable'] ?? $result ). " for ". $why. "\n";
	}
}
check( 'every wrong field leaves the entry out, with the entry, its kind and key, and the field it was left out for - and the entry beside it is read', $allRefused === true );
$result = \Nino\Catalogue::parse( document( [ 'helper', array_merge( $goodEntry, [ 'kind' => 5 ] ), $goodEntry ] ) );
check( 'an entry that is no object, or has no kind, is left out with that, and the rest is read', is_array( $result ) && count( $result['packages'] ) === 1
	&& array_column( $result['unreadable'], 'reason' ) === [ 'must be an object', '"kind" must be one of feature, theme, blocks' ] && array_column( $result['unreadable'], 'kind' ) === [ '', '' ] );

// A theme and a package of blocks: what they carry, and what is left out
$theme = packageEntry( 'theme', 'craft', '1.1.0', 'bytes', [ 'blocks' => [ 'hero-soft' ], 'requires' => [ 'nino' => '^2.0', 'features' => [ 'themes' ], 'fonts' => [ 'lora', 'lora' ] ],
	'parts' => [ 'page' => [ 'contract' => 'kernel@^2.0' ], 'band' => [ 'feature' => 'kit', 'contract' => '^2.0' ] ] ] );
$pack = packageEntry( 'blocks', 'gastro', '1.0.0', 'bytes', [ 'requires' => [ 'features' => [ 'kit', 'cards' ] ] ] );
$parsed = \Nino\Catalogue::parse( document( [ $theme, $pack ] ) );
check( 'a theme is read with its parts, blocks, fonts, features and picture, and a package of blocks with its blocks and category', is_array( $parsed ) && $parsed['unreadable'] === [] && count( $parsed['packages'] ) === 2
	&& $parsed['packages'][0]['kind'] === 'theme' && $parsed['packages'][0]['fonts'] === [ 'lora' ] && $parsed['packages'][0]['requires'] === [ 'themes' ]
	&& $parsed['packages'][0]['parts'] === [ 'page' => [ 'feature' => '', 'contract' => 'kernel@^2.0' ], 'band' => [ 'feature' => 'kit', 'contract' => '^2.0' ] ]
	&& $parsed['packages'][0]['blocks'] === [ [ 'key' => 'hero-soft', 'category' => '' ] ] && $parsed['packages'][0]['preview']['size'] === strlen( 'RIFF'. pack( 'V', 12 ). 'WEBPVP8 ' )
	&& $parsed['packages'][1]['kind'] === 'blocks' && $parsed['packages'][1]['blocks'] === [ [ 'key' => 'one', 'category' => 'offer' ] ] && $parsed['packages'][1]['requires'] === [ 'kit', 'cards' ]
	&& isset( $parsed['packages'][1]['fonts'] ) === false && isset( $parsed['packages'][1]['parts'] ) === false );

$wrongPackages = [
	'a theme with no picture'												=> [ $theme, [ 'preview' => null ] ],
	'a picture of another address'									=> [ $theme, [ 'preview' => [ 'url' => 'http://c.test/x.webp' ] + $theme['preview'] ] ],
	'a picture that is too large'										=> [ $theme, [ 'preview' => [ 'size' => \Nino\Packages::MAX_PREVIEW + 1 ] + $theme['preview'] ] ],
	'a picture with a key it should not have'				=> [ $theme, [ 'preview' => $theme['preview'] + [ 'more' => 1 ] ] ],
	'an archive of more than 2 MB'									=> [ $theme, [ 'size' => 2 * 1024 * 1024 + 1 ] ],
	'a key of more than 40 characters'							=> [ $theme, [ 'key' => str_repeat( 'a', 41 ) ] ],
	'a font that is no slug'												=> [ $theme, [ 'requires' => [ 'fonts' => [ 'Lora' ] ] ] ],
	'a group that is no slug'												=> [ $theme, [ 'parts' => [ 'Section' => [ 'contract' => '^2.0' ] ] ] ],
	'a part without a contract'											=> [ $theme, [ 'parts' => [ 'band' => [ 'feature' => 'kit' ] ] ] ],
	'parts that are a list'													=> [ $theme, [ 'parts' => [ [ 'contract' => '^2.0' ] ] ] ],
	'a block that is no slug'												=> [ $theme, [ 'blocks' => [ 'Hero' ] ] ],
	'a block twice'																	=> [ $theme, [ 'blocks' => [ 'hero', 'hero' ] ] ],
	'a package of blocks with no block'							=> [ $pack, [ 'blocks' => [] ] ],
	'a package of blocks with no picture'						=> [ $pack, [ 'preview' => null ] ],
];
$allDropped = true;
foreach( $wrongPackages as $what => [ $base, $wrong ] ) {
	$result = \Nino\Catalogue::parse( document( [ array_merge( $base, $wrong ), $pack === $base ? $theme : $pack ] ) );
	if( is_array( $result ) === false || count( $result['packages'] ) !== 1 || count( $result['unreadable'] ) !== 1 || $result['unreadable'][0]['kind'] !== $base['kind'] ) {
		$allDropped = false;
		echo "        not left out: ". $what. "\n";
	}
}
check( 'an entry of a theme or a package of blocks that is not what its kind says is left out alone', $allDropped === true );

/*	The applications of the index: an optional key above the features. A catalogue
	without it has none; an application of the wrong shape is dropped on its own and
	the rest of the document is read	*/
$application = [ 'key' => 'site', 'name' => [ 'en_US' => 'Site', 'de_DE' => 'Seite' ], 'description' => [ 'en_US' => 'A site' ], 'features' => [ 'helper', 'sample' ], 'next' => '/_admin/#builder', 'start' => 'starter' ];
$withApplications = static fn( mixed ...$applications ): array|string => \Nino\Catalogue::parse( json_encode( [ 'format' => 2, 'packages' => [ $goodEntry ], 'applications' => $applications ] ) );

$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 2, 'packages' => [ $goodEntry ] ] ) );
check( 'an index without the key has no applications and is valid', is_array( $parsed ) && $parsed['applications'] === [] );
$parsed = \Nino\Catalogue::parse( json_encode( [ 'format' => 2, 'packages' => [ $goodEntry ], 'applications' => 'site' ] ) );
check( '...and so has one whose key is no list', is_array( $parsed ) && $parsed['applications'] === [] );
$parsed = $withApplications( $application );
check( 'an application is read with exactly the fields the wizard uses', is_array( $parsed ) && $parsed['applications'] === [ $application ] );
$parsed = $withApplications( [ 'key' => 'bare', 'name' => 'Bare', 'features' => [ 'helper' ] ] );
check( '...description, next and start are optional', is_array( $parsed ) && $parsed['applications'] === [ [ 'key' => 'bare', 'name' => 'Bare', 'description' => '', 'features' => [ 'helper' ], 'next' => null, 'start' => null ] ] );

$parsed = $withApplications( $application + [ 'menu' => 'folded' ] );
check( 'an application that starts the workbench with the menu folded carries that, and one that says nothing carries no key for it', is_array( $parsed ) && $parsed['applications'] === [ $application + [ 'menu' => 'folded' ] ]
	&& array_key_exists( 'menu', ( $withApplications( $application )['applications'] ?? [ [] ] )[0] ) === false );
$parsed = $withApplications( $application + [ 'menu' => null ], [ 'key' => 'a' ] + $application + [ 'menu' => 'open' ], [ 'key' => 'b' ] + $application + [ 'menu' => true ] );
check( '...any other statement is no statement: the application is kept and the key is not', is_array( $parsed ) && count( $parsed['applications'] ) === 3
	&& array_filter( $parsed['applications'], static fn( array $one ): bool => array_key_exists( 'menu', $one ) ) === [] );

$wrongApplications = [
	'a key that is no slug'													=> [ 'key' => 'Site' ],
	'a name that is empty'													=> [ 'name' => '' ],
	'a description of the wrong type'								=> [ 'description' => [ 'en_US' => 5 ] ],
	'no features'																		=> [ 'features' => [] ],
	'features that are no list'											=> [ 'features' => [ 'a' => 'helper' ] ],
	'a feature key that is no slug'									=> [ 'features' => [ 'helper', 'No' ] ],
	'a feature twice'																=> [ 'features' => [ 'helper', 'helper' ] ],
	'a start that is none of the three'							=> [ 'start' => 'always' ],
];
$allDropped = true;
foreach( $wrongApplications as $why => $wrong ) {
	$parsed = $withApplications( array_merge( $application, $wrong ), [ 'key' => 'other' ] + $application );
	if( is_array( $parsed ) === false || array_column( $parsed['applications'], 'key' ) !== [ 'other' ] ) {
		$allDropped = false;
		echo "        not dropped: ". $why. "\n";
	}
}
check( 'an application of the wrong shape is dropped and the one beside it is kept', $allDropped === true );
$parsed = $withApplications( $application, 'not an object', array_merge( $application, [ 'name' => 'Same key' ] ) );
check( '...a value that is no object too, and a key twice counts once, the first', is_array( $parsed ) && count( $parsed['applications'] ) === 1 && $parsed['applications'][0]['name'] === $application['name'] );

$allNone = true;
foreach( [ 'https://evil.test/x', '//evil.test/x', '/\\evil.test', 'relative', '/with space', "/a\nb", 'javascript:alert(1)', '/javascript:alert(1)', '', 5, str_repeat( '/a', 200 ) ] as $wrongNext ) {
	$parsed = $withApplications( array_merge( $application, [ 'next' => $wrongNext ] ) );
	if( is_array( $parsed ) === false || count( $parsed['applications'] ) !== 1 || $parsed['applications'][0]['next'] !== null )
		$allNone = false;
}
check( 'an entry point that is no path of the project - a scheme, a second slash, a space, a control character - is dropped and the application stays', $allNone === true );
$parsed = $withApplications( array_merge( $application, [ 'next' => '/crm?tab=1#list' ] ) );
check( '...while a query and an anchor belong to a path', is_array( $parsed ) && $parsed['applications'][0]['next'] === '/crm?tab=1#list' );

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
	entry( 'helper', '1.1.0', $helper110, [ 'maturity' => [ 'en_US' => 'Stable', 'de_DE' => 'Stabil' ] ] ),
	entry( 'helper', '9.0.0', 'never built', [ 'nino' => '^9.0' ] ),
	entry( 'sample', '3.0.0', $sample300 ),
	entry( 'ancient', '1.0.0', 'never built', [ 'nino' => '^0.9' ] ),
	entry( 'needy', '1.0.0', 'never built', [ 'php' => [ 'ext' => [ 'no_such_extension' ] ] ] ),
];

$off = $appData;
$off['/nino/catalogue/url'] = '';
check( 'switched off', \Nino\Catalogue::fetch( $off ) === 'the catalogue is switched off' );

// An empty configured key falls back to the kernel's own, so key() never
// answers '' and there is no keyless state for anything to refuse. A fork
// that empties PUBLIC_KEY still fails closed: verify() refuses an empty key,
// and the catalogue is "the catalogue signature does not verify"
$noKey = $appData;
$noKey['/nino/catalogue/key'] = '';
check( 'the kernel ships a key: an empty configured key falls back to it', \Nino\Catalogue::key( $noKey ) === \Nino\Catalogue::PUBLIC_KEY && openssl_pkey_get_public( \Nino\Catalogue::PUBLIC_KEY ) !== false );
$keyless = array_filter( [ '_nino/Nino/Catalogue/Catalogue.php', '_admin/Nino/Modules/Features/Admin/Admin.php', '_admin/Nino/Modules/Features/text/en_US.php', '_admin/Nino/Modules/Features/text/de_DE.php' ],
	static fn( string $file ): bool => preg_match( '/no catalogue key|catalogue-key/', (string) file_get_contents( dirname( __DIR__ ). '/'. $file ) ) === 1 );
check( '...so neither the kernel nor the panel has a refusal for a missing key, and an empty one verifies nothing'. ( $keyless === [] ? '' : ' - still in '. implode( ', ', $keyless ) ),
	$keyless === []
	&& \Nino\Catalogue::verify( 'catalogue', sign( 'catalogue', $privateKey ), $publicKey ) === true
	&& \Nino\Catalogue::verify( 'catalogue', sign( 'catalogue', $privateKey ), '' ) === false );

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

publishRaw( $appData, $remote, '{"format":1,"features":[]}', $privateKey );
check( 'a signed document that does not parse is refused with what parse() says: a catalogue of format 1 lists nothing', \Nino\Catalogue::fetch( $appData ) === 'the catalogue has format 1, this kernel reads format 2' );

publish( $appData, $remote, $features, null, $privateKey );
$requests = [];
$catalogue = \Nino\Catalogue::fetch( $appData );
check( 'a signed, valid catalogue is fetched with two requests: the document and the signature', is_array( $catalogue )
	&& array_column( $requests, 'url' ) === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] );
check( 'the signature is read with a small cap, the document with a larger one', $requests[0]['options']['maxBytes'] === 1024 * 1024 && $requests[1]['options']['maxBytes'] === 4096 );
check( 'the catalogue carries its url and every entry', $catalogue['url'] === \Nino\Catalogue::DEFAULT_URL && count( $catalogue['packages'] ) === 6 );

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
check( 'the same shape fetch() returns, plus fetched', array_keys( $cached ) === [ 'format', 'generated', 'packages', 'unreadable', 'applications', 'url', 'fetched' ]
	&& $cached['url'] === \Nino\Catalogue::DEFAULT_URL && $cached['fetched'] === $stored['fetched'] && count( $cached['packages'] ) === 6
	&& $cached['format'] === $catalogue['format'] && $cached['generated'] === $catalogue['generated'] && $cached['packages'] === $catalogue['packages'] && $cached['applications'] === [] );

/*	A private catalogue behind a token: url() takes it (isHttpsUrl() has no
	opinion on a query string), and the signature belongs beside the
	document - '.sig' on the path, not on the end of the url. Appended to
	the whole thing it asked for '...?token=abc.sig', a 404 reported as "the
	signature could not be fetched" with nothing saying the url shape was
	the cause	*/
$tokenUrl = 'https://catalogue.example.org/private/catalogue.json?token=abc#part';
$tokenData = $appData;
$tokenData['/nino/catalogue/url'] = $tokenUrl;
unset( $tokenData['./nino/catalogue'] );
$remote[ $tokenUrl ] = $json;
$remote[ 'https://catalogue.example.org/private/catalogue.json.sig?token=abc#part' ] = sign( $json, $privateKey );
$requests = [];
check( 'a catalogue behind a query string fetches its signature from the path beside it', is_array( \Nino\Catalogue::fetch( $tokenData ) )
	&& array_column( $requests, 'url' ) === [ $tokenUrl, 'https://catalogue.example.org/private/catalogue.json.sig?token=abc#part' ] );

// ...and the cache on disk goes back to the one the checks above read
unset( $appData['./nino/catalogue'] );
\Nino\Catalogue::fetch( $appData );

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

$offers = \Nino\Catalogue::offers( $appData, $catalogue, 'feature' );

check( 'one offer per key, sorted', array_keys( $offers ) === [ 'ancient', 'helper', 'needy', 'sample' ] );
check( 'the highest version this kernel can run is offered, not the highest there is', $offers['helper']['version'] === '1.1.0' && $offers['helper']['fits'] === true );
check( 'nothing on disk: available', $offers['helper']['state'] === 'available' && $offers['helper']['local'] === null && $offers['helper']['active'] === false && $offers['sample']['state'] === 'available' );
check( 'a feature no version of which fits is incompatible, shown with its newest version and what it asks for', $offers['ancient']['state'] === 'incompatible' && $offers['ancient']['fits'] === false && $offers['ancient']['nino'] === '^0.9'
	&& $offers['needy']['state'] === 'incompatible' && $offers['needy']['php']['ext'] === [ 'no_such_extension' ] );
check( 'the offer is the catalogue entry plus fits, local, active, needs, blocked and state', array_keys( $offers['helper'] ) === [ 'kind', 'key', 'name', 'description', 'version', 'nino', 'php', 'requires', 'archive', 'sha256', 'size', 'released', 'preview', 'category', 'maturity', 'directory', 'fits', 'local', 'active', 'needs', 'blocked', 'state' ] );

/*	An application is offered whole or not at all: every one of its features has a
	version this kernel can run (the rule of offers()), or it stays out	*/
$catalogueOfApplications = $catalogue;
$catalogueOfApplications['applications'] = [
	[ 'key' => 'fine', 'name' => 'Fine', 'description' => '', 'features' => [ 'helper', 'sample' ], 'next' => null, 'start' => null ],
	[ 'key' => 'old', 'name' => 'Old', 'description' => '', 'features' => [ 'helper', 'ancient' ], 'next' => null, 'start' => null ],
	[ 'key' => 'ext', 'name' => 'Ext', 'description' => '', 'features' => [ 'needy' ], 'next' => null, 'start' => null ],
	[ 'key' => 'ghost', 'name' => 'Ghost', 'description' => '', 'features' => [ 'helper', 'unlisted' ], 'next' => null, 'start' => null ],
	[ 'key' => 'last', 'name' => 'Last', 'description' => '', 'features' => [ 'sample' ], 'next' => null, 'start' => null ],
];
check( 'applications() keeps the ones whose features all fit, in the catalogue\'s order',
	array_column( \Nino\Catalogue::applications( $appData, $catalogueOfApplications ), 'key' ) === [ 'fine', 'last' ] );
check( '...one with a feature no version of which fits, with a missing php extension, or that the catalogue does not list is left out',
	array_diff( [ 'old', 'ext', 'ghost' ], array_column( \Nino\Catalogue::applications( $appData, $catalogueOfApplications ), 'key' ) ) === [ 'old', 'ext', 'ghost' ] );
check( '...and a catalogue read before the key existed offers none', \Nino\Catalogue::applications( $appData, [ 'features' => $catalogue['packages'] ] ) === [] );

echo "\n";


// --- Catalogue::install ------------------------------------------------------

echo "Catalogue::install - from archive bytes to a directory below features/\n";

$staging = \Nino\Filesystem::path( $appData, '/data/.packages' );

function stagingClean( string $staging ): bool {
	return is_dir( $staging ) === false || ( scandir( $staging ) ?: [] ) === [ '.', '..' ];
}

check( 'the features directory is writable here', \Nino\Catalogue::writable() === true );

check( 'a key the catalogue does not list', \Nino\Catalogue::install( $appData, 'feature', 'nope', '1.0.0' ) === 'the catalogue does not list "nope" in version 1.0.0' );
check( 'a version the catalogue does not list', \Nino\Catalogue::install( $appData, 'feature', 'helper', '1.0.1' ) === 'the catalogue does not list "helper" in version 1.0.1' );
check( 'a version that does not fit names what it asks for', \Nino\Catalogue::install( $appData, 'feature', 'helper', '9.0.0' ) === '"helper" 9.0.0 requires Nino ^9.0'
	&& \Nino\Catalogue::install( $appData, 'feature', 'needy', '1.0.0' ) === '"needy" 1.0.0 requires Nino ^2.0 and the php extensions no_such_extension' );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
$requests = [];
$result = \Nino\Catalogue::install( $appData, 'feature', 'helper', '1.0.0' );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
check( 'without a writable features directory nothing is downloaded and the archive url is given for the manual way', $result === 'the features directory is not writable - download https://catalogue.test/features/helper-1.0.0.tar.gz and unpack it there by hand' && $requests === [] );

$requests = [];
$result = \Nino\Catalogue::install( $appData, 'feature', 'helper', '1.0.0' );
check( 'helper 1.0.0 installs', $result === true );
check( 'the archive was fetched from the url the catalogue names, capped at its size', count( $requests ) === 1 && $requests[0]['url'] === 'https://catalogue.test/features/helper-1.0.0.tar.gz' && $requests[0]['options']['maxBytes'] === strlen( $helper100 ) && $requests[0]['options']['timeout'] === 60 );
check( 'the feature directory is in place with every file', is_file( NINO_FEATURES_DIR. '/Helper/feature.php' ) && is_file( NINO_FEATURES_DIR. '/Helper/Helper.php' ) && is_file( NINO_FEATURES_DIR. '/Helper/only-in-1.txt' ) );
check( 'the staging directory below data/ is left empty', stagingClean( $staging ) === true );

$all = \Nino\Features::all( $appData );
check( 'the registry sees it in the same request', isset( $all['helper'] ) && $all['helper']['version'] === '1.0.0' && $all['helper']['active'] === false && $all['helper']['problems'] === [] );

unset( $appData['./nino/catalogue'] );
$offers = \Nino\Catalogue::offers( $appData, \Nino\Catalogue::fetch( $appData ), 'feature' );
check( 'the offer now says upgrade, with the local version', $offers['helper']['state'] === 'upgrade' && $offers['helper']['local'] === '1.0.0' && $offers['helper']['version'] === '1.1.0' );

check( 'the installed feature activates like one copied by hand', \Nino\Features::activate( $appData, 'helper' ) === true && \Nino\Features::get( $appData, 'helper' )['active'] === true );
check( 'its class is served from the features directory', class_exists( '\\Nino\\Modules\\Helper' ) === true && \Nino\Modules\Helper::VERSION === '1.0.0' );

echo "\n";


// --- Update ------------------------------------------------------------------

echo "Catalogue::install - an update replaces the directory\n";

$result = \Nino\Catalogue::install( $appData, 'feature', 'helper', '1.1.0' );
check( 'helper 1.1.0 installs over 1.0.0', $result === true );
check( 'the new files are there, the file only 1.0.0 had is gone', is_file( NINO_FEATURES_DIR. '/Helper/Helper.php' ) && str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.1.0' ) && file_exists( NINO_FEATURES_DIR. '/Helper/only-in-1.txt' ) === false );
check( 'the old directory did not stay behind in staging', stagingClean( $staging ) === true );
$all = \Nino\Features::all( $appData );
check( 'the registry sees the new version and, since 1.0.0 was recorded on activation, an update to apply', $all['helper']['version'] === '1.1.0' && $all['helper']['installed'] === '1.0.0' && $all['helper']['update'] === true && $all['helper']['active'] === true );
unset( $appData['./nino/catalogue'] );
$offers = \Nino\Catalogue::offers( $appData, \Nino\Catalogue::fetch( $appData ), 'feature' );
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
	$result = \Nino\Catalogue::install( $appData, 'feature', $entry['key'], $entry['version'] );
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

ninoWarnings();
$bytes = 'this is not a tar.gz';
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
$logged = ninoWarnings();
check( 'bytes that are not an archive are refused by what \Nino\Archive says of them', $result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::GZIP ] );
// The archive is read in memory: no php function that names a path of this server is asked, and nothing is written for it
check( '...the answer holds no path of this server, and nothing was logged or written for it',
	is_string( $result ) === true && str_contains( $result, $sandbox ) === false && str_contains( $result, sys_get_temp_dir() ) === false && str_contains( $result, 'phar' ) === false
	&& $logged === [] && stagingClean( $staging ) === true );

$bytes = tarGz( [] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an empty archive is a package of no file', $result === 'the package holds no file' );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) + [ 'Other/' => null, 'Other/x.php' => '<?php' ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive holding something beside the feature directory', $result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::FOLDER ] );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) + [ '../evil.php' => '<?php' ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive with a path that climbs out of it', $result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::PATH ] && file_exists( $staging. '/evil.php' ) === false && file_exists( dirname( $staging ). '/evil.php' ) === false );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0' ) + [ 'Helper/link.php' => [ 'link' => '/etc/passwd' ] ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
// Refused, not disarmed. The look before the extraction used to ask
// isLink(), which PharData answers false to for every entry a tar can
// hold - so the guard never fired, the link came out as a 0-byte plain
// file, and the look after the extraction found no link to object to
// either. Inert, but not what docs/_admin.md promises ("plain files and
// directories only"), and the archive got as far as being unpacked
check( 'an archive with a symlink is refused, before anything is written', $result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::TYPE ] );
check( 'nothing of it reaches features/, and the staging directory is gone', file_exists( NINO_FEATURES_DIR. '/Helper/link.php' ) === false && stagingClean( $staging ) === true );
check( 'the version that was installed before it is untouched', str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.1.0' ) === true );

// An archive whose one top-level entry is a file of the feature's name: the
// look before the extraction accepted it (a file may be named like the
// directory), and the look after it opened the file as a directory - an
// exception out of install(), and the staging directory left behind
$bytes = tarGz( [ 'Helper' => '<?php' ] );
try { $result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes ); }
catch( \Throwable $e ) { $result = 'threw '. get_class( $e ); }
check( 'an archive holding a file where the feature directory should be is refused, not thrown at', $result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::FOLDER ] && stagingClean( $staging ) === true );

// PharData names every entry by the archive's canonical path, symlinks
// resolved, while _unpack() cut the prefix by the length of the path it
// had been given. A private root reached through a symlink - a hosting
// home directory, a bind mount, macOS's /var -> /private/var - made every
// entry path garbage, and every install a refusal blaming the archive
$linkedPrivate = $sandbox. '/private-link';
symlink( 'private', $linkedPrivate );
$realPaths = [];
foreach( [ 'configpath', 'contentpath' ] as $pathKey ) {
	$realPaths[$pathKey] = $appData['./nino/filesystem/'. $pathKey];
	$appData['./nino/filesystem/'. $pathKey] = $linkedPrivate;
}
$result = \Nino\Catalogue::install( $appData, 'feature', 'helper', '1.1.0' );
check( 'an install through a symlinked private root succeeds - the entries are cut by the archive\'s own path', $result === true && stagingClean( $staging ) === true && str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.1.0' ) === true );
foreach( $realPaths as $pathKey => $realPath )
	$appData['./nino/filesystem/'. $pathKey] = $realPath;
unlink( $linkedPrivate );

$bytes = tarGz( featureFiles( 'Helper', 'other', '1.2.0' ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive holding another feature than promised', $result === 'the archive holds "other" 1.2.0, the catalogue promised "helper" 1.2.0' );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.1' ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive holding another version than promised', $result === 'the archive holds "helper" 1.2.1, the catalogue promised "helper" 1.2.0' );

ninoWarnings();
$bytes = tarGz( [ 'Helper/' => null, 'Helper/feature.php' => '<?php return "nope";' ] );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive whose manifest does not validate', $result === 'the archive does not hold a valid feature' && count( ninoWarnings() ) === 1 );

// A manifest is php the archive brought, and php that does not parse throws
// where an invalid one returns null. The throw left out of the include
// inside manifest() - past the restore_error_handler() below the read, so
// the closure that silences a bad manifest stayed on the handler stack for
// the rest of the process, and past the removeDir() that clears the staging
// directory, so a copy of the archive was left below data/ for good. In the
// panel it was a 500 rather than the refusal the comment there promises.
// The trigger_error() is what pins the handler half: the harness records
// warnings, so an empty list means the swallowing closure is still on top
$bytes = tarGz( [ 'Helper/' => null, 'Helper/feature.php' => '<?php return [ \'key\' => \'helper\',' ] );
try { $result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes ); }
catch( \Throwable $e ) { $result = 'threw '. get_class( $e ); }
ninoWarnings();
trigger_error( 'the handler is back', E_USER_WARNING );
check( 'an archive whose manifest does not parse is refused, not thrown at', $result === 'the archive does not hold a valid feature' );
check( 'and it leaves no staging directory and no error handler of its own behind', stagingClean( $staging ) === true && ninoWarnings() === [ 'the handler is back' ] );

$bytes = tarGz( featureFiles( 'Helper', 'helper', '1.2.0', [ 'feature.php' => '<?php return [ \'key\' => \'helper\', \'name\' => \'Helper\', \'version\' => \'1.2.0\', \'nino\' => \'^9.0\', \'php\' => [ \'ext\' => [ \'no_such_extension\' ] ] ];' ] ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive whose manifest asks for more than the entry said, and does not fit here', $result === 'the archive holds "helper" 1.2.0, which requires Nino ^9.0 and the php extensions no_such_extension' );

$bytes = tarGz( featureFiles( 'Other', 'helper', '1.2.0' ) );
$result = tryInstall( $appData, $remote, $features, $privateKey, entry( 'helper', '1.2.0', $bytes ), $bytes );
check( 'an archive whose directory is not the one the catalogue names', $result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::FOLDER ] );

check( 'after all of that the installed feature is untouched', (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ) === $before && file_exists( NINO_FEATURES_DIR. '/Helper/link.php' ) === false );
check( 'and staging is empty', stagingClean( $staging ) === true );
check( 'nothing but Helper below features/', ( scandir( NINO_FEATURES_DIR ) ?: [] ) === [ '.', '..', 'Helper' ] );

$requests = [];
publish( $appData, $remote, $features, sign( 'tampered', $privateKey ), $privateKey );
check( 'an install re-reads the catalogue and refuses one whose signature no longer holds', \Nino\Catalogue::install( $appData, 'feature', 'sample', '3.0.0' ) === 'the catalogue signature does not verify' && count( $requests ) === 2 );

publish( $appData, $remote, $features, null, $privateKey );
check( 'a second feature installs beside the first', \Nino\Catalogue::install( $appData, 'feature', 'sample', '3.0.0' ) === true && ( scandir( NINO_FEATURES_DIR ) ?: [] ) === [ '.', '..', 'Helper', 'Sample' ] );

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
	return array_slice( ninoCall( $appData, [ \Nino\Modules\Features\Admin::class, $method ], $data ), 0, 2 );
}

/**
 *	What the panel answers where the kernel refuses an install or a removal: the kernel's
 *	reason for a log or a developer, and the code the browser says it by with the reason in it
 */
function failedWith( string $code, string $reason ): array {
	return [ 400, [ 'error' => $reason, 'code' => $code, 'params' => [ $reason ] ] ];
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
	check( $method. ' is 401 without an account', callFeatures( $appData, $method, $data ) === [ 401, [ 'error' => 'not logged in', 'code' => 'session' ] ] );

\Nino\Auth::loginUser( $appData, 'editor@example.com', 'correct horse battery staple' );
foreach( $panelActions as $method => $data )
	check( $method. ' is 403 without the permission', callFeatures( $appData, $method, $data ) === [ 403, [ 'error' => 'not allowed' ] ] );
check( 'and neither asked the catalogue for anything', $requests === [] );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// The catalogue as the panel phrases it
publish( $appData, $remote, $features, null, $privateKey );
$requests = [];
[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'apiCatalogue reads the catalogue - two requests - and answers when it was fetched, whether features/ is writable, and one offer per key, sorted: what the script reads, and nothing else', $status === 200
	&& array_keys( $body ) === [ 'fetched', 'writable', 'offers', 'applications' ] && $body['writable'] === true
	&& is_string( $body['fetched'] ) && $body['fetched'] !== '' && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $body['fetched'] ) === 1
	&& array_column( $requests, 'url' ) === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] && array_column( $body['offers'], 'key' ) === [ 'ancient', 'helper', 'needy', 'sample' ] );
$offers = array_column( $body['offers'], null, 'key' );

// apiList carries the same catalogue, straight from what apiCatalogue just
// cached - no further request, and its offers answer the same as apiCatalogue's
$requests = [];
[ $listStatus, $listBody ] = callFeatures( $appData, 'apiList' );
check( 'apiList carries the cached catalogue too - fetched and the same offers, phrased the same way - with no request of its own', $listStatus === 200 && $requests === []
	&& is_array( $listBody['catalogue'] ) && array_keys( $listBody['catalogue'] ) === [ 'fetched', 'offers', 'applications' ]
	&& $listBody['catalogue']['fetched'] === $body['fetched'] && $listBody['catalogue']['offers'] === $body['offers'] );
check( 'every offer has the same keys, the extension list flattened to ext - the members the script reads, and no other', array_keys( $offers['helper'] ) === [ 'key', 'name', 'description', 'category', 'maturity', 'version', 'nino', 'ext', 'requires', 'archive', 'released', 'state', 'local' ] );
check( 'the maturity arrives localized like the name, empty for an offer that names none', $offers['helper']['maturity'] === 'Stabil' && $offers['sample']['maturity'] === '' );
// The cache is read back as it was written: one written before the key existed
// carries none, and the panel has to answer rather than fail on the missing key
$cacheFile = \Nino\Filesystem::getFileContent( $appData, '/data/catalogue.php', [] );
foreach( $cacheFile['catalogue']['packages'] as $i => $cachedEntry )
	unset( $cacheFile['catalogue']['packages'][$i]['maturity'] );
\Nino\Filesystem::putFileContent( $appData, '/data/catalogue.php', $cacheFile );
$requests = [];
ninoWarnings();
[ $oldStatus, $oldBody ] = callFeatures( $appData, 'apiList' );
check( 'a catalogue cached before the maturity existed still lists, every offer with an empty one', $oldStatus === 200 && $requests === [] && ninoWarnings() === [] && count( $oldBody['catalogue']['offers'] ) === count( $body['offers'] )
	&& array_unique( array_column( $oldBody['catalogue']['offers'], 'maturity' ) ) === [ '' ] );
publish( $appData, $remote, $features, null, $privateKey );
callFeatures( $appData, 'apiCatalogue' );
check( 'names and descriptions arrive in the session locale - de_DE, the native language, since none was chosen', $offers['helper']['name'] === 'Helper' && $offers['helper']['description'] === 'Ein helper' && $offers['sample']['description'] === 'Ein sample' );
check( 'the state travels with each offer: helper and sample current, ancient and needy incompatible with what they ask for',
	$offers['helper']['state'] === 'current' && $offers['helper']['version'] === '1.1.0' && $offers['helper']['local'] === '1.1.0'
	&& $offers['sample']['state'] === 'current' && $offers['sample']['local'] === '3.0.0'
	&& $offers['ancient']['state'] === 'incompatible' && $offers['ancient']['local'] === null && $offers['ancient']['nino'] === '^0.9' && $offers['ancient']['ext'] === []
	&& $offers['needy']['state'] === 'incompatible' && $offers['needy']['nino'] === '^2.0' && $offers['needy']['ext'] === [ 'no_such_extension' ] );
check( 'the archive url and the release date pass through', $offers['sample']['archive'] === 'https://catalogue.test/features/sample-3.0.0.tar.gz'
	&& $offers['sample']['released'] === '2026-09-07' && $offers['sample']['requires'] === [] );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
unset( $appData['./nino/features/all'] );
[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
unset( $appData['./nino/features/all'] );
check( 'without a writable features directory the answer says so, and what is not seen on disk is available', $status === 200 && $body['writable'] === false && array_column( $body['offers'], 'state', 'key' )['helper'] === 'available' );

// A catalogue switched off is the panel's own words, in the interface
// language; anything else is the kernel's sentence behind the panel's phrase
$off = $appData;
$off['/nino/catalogue/url'] = '';
check( 'switched off: a 400 in the panel\'s own words, in the session locale', callFeatures( $off, 'apiCatalogue' ) === [ 400, [ 'error' => panelWord( 'de_DE', '/_admin/features/error/catalogue-off' ) ] ] && panelWord( 'de_DE', '/_admin/features/error/catalogue-off' ) !== '' );

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
check( 'a malformed or missing key or version is a 400 before the kernel is asked', callFeatures( $appData, 'apiInstall', [ 'key' => 'Helper', 'version' => '1.1.0' ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => '../etc', 'version' => '1.1.0' ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.1' ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.1.0; rm' ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'helper' ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'version' => '1.1.0' ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& callFeatures( $appData, 'apiInstall', [ 'key' => [ 'helper' ], 'version' => [ '1.1.0' ] ] ) === [ 400, [ 'error' => 'no feature key and version posted', 'code' => 'features_invalid' ] ]
	&& $requests === [] );
check( 'a version the catalogue does not list is a 400 carrying the kernel\'s reason', callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.0.1' ] ) === failedWith( 'features_install_failed', 'the catalogue does not list "helper" in version 1.0.1' )
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'nope', 'version' => '1.0.0' ] ) === failedWith( 'features_install_failed', 'the catalogue does not list "nope" in version 1.0.0' ) );
check( 'so is one that does not fit this kernel', callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '9.0.0' ] ) === failedWith( 'features_install_failed', '"helper" 9.0.0 requires Nino ^9.0' )
	&& callFeatures( $appData, 'apiInstall', [ 'key' => 'needy', 'version' => '1.0.0' ] ) === failedWith( 'features_install_failed', '"needy" 1.0.0 requires Nino ^2.0 and the php extensions no_such_extension' ) );
check( 'none of that downloaded anything', array_filter( $requests, static fn( array $r ): bool => str_ends_with( $r['url'], '.tar.gz' ) ) === [] );

rename( NINO_FEATURES_DIR, NINO_FEATURES_DIR. '.away' );
unset( $appData['./nino/features/all'] );
$result = callFeatures( $appData, 'apiInstall', [ 'key' => 'sample', 'version' => '3.0.0' ] );
rename( NINO_FEATURES_DIR. '.away', NINO_FEATURES_DIR );
unset( $appData['./nino/features/all'] );
check( 'without a writable features directory the kernel\'s refusal, naming the archive for the manual way, passes through', $result === failedWith( 'features_install_failed', 'the features directory is not writable - download https://catalogue.test/features/sample-3.0.0.tar.gz and unpack it there by hand' ) );

// A feature that is not on disk yet: placed, and switched on with it
$extra100 = tarGz( featureFiles( 'Extra', 'extra', '1.0.0' ) );
$remote['https://catalogue.test/features/extra-1.0.0.tar.gz'] = $extra100;
$withExtra = array_merge( $features, [ entry( 'extra', '1.0.0', $extra100, [ 'name' => [ 'en_US' => 'Extra', 'de_DE' => 'Zusatz' ] ] ) ] );
publish( $appData, $remote, $withExtra, null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'a feature not on disk is offered as available, its name in the session locale', $status === 200 && array_column( $body['offers'], 'state', 'key' )['extra'] === 'available' && array_column( $body['offers'], 'name', 'key' )['extra'] === 'Zusatz' );

$requests = [];
[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'extra', 'version' => '1.0.0' ] );
/*	Install means install: a feature the project did not have is switched on
	in the same request, and the answer says which of the two happened to it -
	'pending' for one that was already running - its update is applied by the
	request the panel makes next - 'activated' for one that was
	not there at all. Leaving it in the Inactive tab made one intention take
	two presses.	*/
check( 'installing a feature the project did not have answers its entry as the list shows it now - on disk, on, recorded', $status === 200 && array_keys( $body ) === [ 'feature', 'activated', 'pending', 'required', 'switchedOn', 'skipped' ] && $body['skipped'] === [] && $body['pending'] === false && $body['activated'] === true && $body['required'] === []
	&& array_keys( $body['feature'] ) === [ 'key', 'name', 'description', 'manual', 'manualSections', 'category', 'maturity', 'version', 'installed', 'active', 'update', 'requires', 'dependents', 'circle', 'problems', 'components', 'calledIn', 'unit', 'data', 'settings', 'settingsTab' ]
	&& $body['switchedOn'] === [ [ 'key' => 'extra', 'name' => 'Extra' ] ]
	&& $body['feature']['key'] === 'extra' && $body['feature']['name'] === 'Extra' && $body['feature']['version'] === '1.0.0' && $body['feature']['active'] === true && $body['feature']['installed'] === '1.0.0' && $body['feature']['update'] === false && $body['feature']['problems'] === [] );
check( 'the directory is in place, the archive was fetched once, and the class is in the module list', is_file( NINO_FEATURES_DIR. '/Extra/feature.php' ) && is_file( NINO_FEATURES_DIR. '/Extra/Extra.php' )
	&& count( array_filter( $requests, static fn( array $r ): bool => $r['url'] === 'https://catalogue.test/features/extra-1.0.0.tar.gz' ) ) === 1
	&& in_array( '\\Nino\\Modules\\Extra', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['extra']['version'] ?? '' ) === '1.0.0' );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'and the catalogue now says installed, and running', $status === 200 && array_column( $body['offers'], 'state', 'key' )['extra'] === 'current' && array_column( $body['offers'], 'local', 'key' )['extra'] === '1.0.0' && \Nino\Features::get( $appData, 'extra' )['active'] === true );

/*	...and the one case it does not switch on: a feature the project has
	deliberately switched off. A newer version of it is not somebody changing
	their mind about that, so it is placed and left alone, with its Activate
	where it was.	*/
check( 'it can be switched off again', callFeatures( $appData, 'apiDeactivate', [ 'key' => 'extra' ] )[0] === 200
	&& \Nino\Features::get( $appData, 'extra' )['active'] === false );

$extra110 = tarGz( featureFiles( 'Extra', 'extra', '1.1.0' ) );
$remote['https://catalogue.test/features/extra-1.1.0.tar.gz'] = $extra110;
publish( $appData, $remote, array_merge( $withExtra, [ entry( 'extra', '1.1.0', $extra110, [ 'name' => [ 'en_US' => 'Extra', 'de_DE' => 'Zusatz' ] ] ) ] ), null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'extra', 'version' => '1.1.0' ] );
check( 'a newer version of a feature somebody switched off is placed and left off', $status === 200
	&& $body['pending'] === false && $body['activated'] === false
	&& $body['feature']['version'] === '1.1.0' && $body['feature']['active'] === false );
check( '...and its class is out of the module list, where deactivating put it', in_array( '\\Nino\\Modules\\Extra', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === false );

/*	A feature the archive can place but the activation cannot switch on - its
	manifest requires something that is not in the directory, which only an
	activation knows. The files are in place either way, and the answer has to
	say both halves or the list shows a feature sitting there for no stated
	reason	*/
$lonely = tarGz( featureFiles( 'Lonely', 'lonely', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'lonely\', \'name\' => \'Lonely\', \'version\' => \'1.0.0\', \'requires\' => [ \'nowhere\' ] ];' ] ) );
$remote['https://catalogue.test/features/lonely-1.0.0.tar.gz'] = $lonely;
publish( $appData, $remote, array_merge( $withExtra, [ entry( 'lonely', '1.0.0', $lonely ) ] ), null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'lonely', 'version' => '1.0.0' ] );
check( 'a new feature that cannot be switched on is a 400 saying it is in place, in the panel\'s own words', $status === 400
	&& str_starts_with( $body['error'], panelWord( 'de_DE', '/_admin/features/error/activate-after-install', 'feature "lonely" cannot be activated: ' ) ) && str_contains( $body['error'], '"nowhere"' ) );
check( '...and the directory really is there, with nothing recorded and nothing in the module list', is_file( NINO_FEATURES_DIR. '/Lonely/feature.php' )
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['lonely'] ) === false
	&& in_array( '\\Nino\\Modules\\Lonely', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === false );

// An active feature: the new version replaces the directory, and the update
// is applied by activating again - in a request of its own, not this one.
// This request booted with the previous version's class, and the hook the
// new version brought can only run in a request that loads the new class.
// The 1.2.0 class brings one, and writes where it ran from
$upgradeMarker = $sandbox. '/upgrade-ran.txt';
$helper120 = tarGz( featureFiles( 'Helper', 'helper', '1.2.0', [ 'Helper.php' => '<?php namespace Nino\\Modules { class Helper { public static function upgrade( array &$appData, string $from ): bool { file_put_contents( '. var_export( $upgradeMarker, true ). ', $from ); return true; } } }' ] ) );
$remote['https://catalogue.test/features/helper-1.2.0.tar.gz'] = $helper120;
$withHelper120 = array_merge( $withExtra, [ entry( 'helper', '1.2.0', $helper120 ) ] );
publish( $appData, $remote, $withHelper120, null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'the offer for an active feature on disk in an older version says upgrade, with the local version', $status === 200 && array_column( $body['offers'], 'state', 'key' )['helper'] === 'upgrade'
	&& array_column( $body['offers'], 'local', 'key' )['helper'] === '1.1.0' && array_column( $body['offers'], 'version', 'key' )['helper'] === '1.2.0' && \Nino\Features::get( $appData, 'helper' )['active'] === true );
check( 'before: on disk as 1.1.0, recorded as 1.0.0, an update waiting', \Nino\Features::get( $appData, 'helper' )['version'] === '1.1.0' && \Nino\Features::get( $appData, 'helper' )['installed'] === '1.0.0' && \Nino\Features::get( $appData, 'helper' )['update'] === true );

/*	Measured before this was the rule: apiInstall() activated again in the
	same request, method_exists() answered for the class already in memory -
	the 1.1.0 one, which has no upgrade() - and 1.2.0 was recorded with its
	hook never called, and never to be called, since the record now said it
	was current. The class in memory stays the old one for the rest of this
	process; that is the condition itself, not an artefact of the test	*/
[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.2.0' ] );
check( 'updating an active feature places the new version and answers that the update is pending: on disk 1.2.0, still recorded 1.0.0, update waiting, still active', $status === 200 && array_keys( $body ) === [ 'feature', 'activated', 'pending', 'required', 'switchedOn', 'skipped' ] && $body['skipped'] === [] && $body['pending'] === true && $body['activated'] === false && $body['switchedOn'] === []
	&& $body['feature']['version'] === '1.2.0' && $body['feature']['installed'] === '1.0.0' && $body['feature']['update'] === true && $body['feature']['active'] === true && $body['feature']['problems'] === [] );
check( 'the files are the new ones, the class still in the module list', in_array( '\\Nino\\Modules\\Helper', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === true
	&& str_contains( (string) file_get_contents( NINO_FEATURES_DIR. '/Helper/feature.php' ), '1.2.0' ) && stagingClean( $staging ) === true );
check( '...and nothing called the new version\'s hook in this request - the class in memory is the one without it', is_file( $upgradeMarker ) === false && method_exists( '\\Nino\\Modules\\Helper', 'upgrade' ) === false );

// The kernel refuses to apply it here, whoever asks: the version it would
// record is one whose hook it cannot call
check( 'activate() in the same request refuses to apply the update and says why', \Nino\Features::activate( $appData, 'helper' ) === 'feature "helper" was replaced in this request while its previous version is loaded - the update is applied by activating it in a new request'
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['helper']['version'] === '1.0.0' );

// The request the panel makes next, as the fresh process it is: the new class
// is the one that loads, and its hook runs from the recorded version
$applyDriver = $sandbox. '/apply-update.php';
file_put_contents( $applyDriver, '<?php
declare(strict_types=1);
define( "NINO_FEATURES_DIR", '. var_export( NINO_FEATURES_DIR, true ). ' );
require '. var_export( __DIR__. '/harness.php', true ). ';
$appData = [ "./nino/uid" => '. var_export( $sandbox, true ). ' ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"]				= '. var_export( $sandbox, true ). ';
$appData["./nino/filesystem/configpath"]	= '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/contentpath"] = '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/publicpath"]	= '. var_export( $sandbox. '/public', true ). ';
$appData["/nino/dir"] = "";
\Nino\AppData::init( $appData );
echo json_encode( [ "result" => \Nino\Features::activate( $appData, "helper" ), "hook" => method_exists( "\\\\Nino\\\\Modules\\\\Helper", "upgrade" ) ] );
' );
$applied = json_decode( (string) shell_exec( PHP_BINARY. ' '. escapeshellarg( $applyDriver ). ' 2>/dev/null' ), true ) ?? [];

// What that request wrote, read back into this one: config.php afresh - a
// same-second rewrite of the same size is invisible to the cache's
// fingerprint - and the registry with it
unset( $appData['./nino/filesystem/cache']['/config.php'], $appData['./nino/features/all'] );
$appData['/nino/features'] = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features'];

check( 'a new request loads the new class and applies the update: the hook ran, from the recorded version', ( $applied['result'] ?? null ) === true && ( $applied['hook'] ?? false ) === true && is_file( $upgradeMarker ) === true && file_get_contents( $upgradeMarker ) === '1.0.0' );
check( 'config.php records the version now, no update waiting', $appData['/nino/features']['helper']['version'] === '1.2.0' && \Nino\Features::get( $appData, 'helper' )['installed'] === '1.2.0' && \Nino\Features::get( $appData, 'helper' )['update'] === false );

[ $status, $body ] = callFeatures( $appData, 'apiCatalogue' );
check( 'the offer says current now', $status === 200 && array_column( $body['offers'], 'state', 'key' )['helper'] === 'current' && array_column( $body['offers'], 'local', 'key' )['helper'] === '1.2.0' );

// An update whose new version cannot be activated here: the files are
// placed - the archive fits this kernel, but its manifest now requires a
// feature that is not in the directory, which only an activation can
// know - and it is the activation the panel makes next that says so
$helper130 = tarGz( featureFiles( 'Helper', 'helper', '1.3.0', [ 'feature.php' => '<?php return [ \'key\' => \'helper\', \'name\' => \'Helper\', \'version\' => \'1.3.0\', \'requires\' => [ \'nowhere\' ] ];' ] ) );
$remote['https://catalogue.test/features/helper-1.3.0.tar.gz'] = $helper130;
publish( $appData, $remote, array_merge( $withHelper120, [ entry( 'helper', '1.3.0', $helper130 ) ] ), null, $privateKey );

[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'helper', 'version' => '1.3.0' ] );
check( 'an update the kernel will refuse is placed and answered as pending all the same - what an activation knows, the placement cannot', $status === 200 && $body['pending'] === true
	&& $body['feature']['version'] === '1.3.0' && $body['feature']['installed'] === '1.2.0' && $body['feature']['problems'] !== [] );

// The request the panel makes next carries no note of what this one replaced
unset( $appData['./nino/features/replaced'] );
[ $status, $body ] = callFeatures( $appData, 'apiActivate', [ 'key' => 'helper' ] );
check( 'the activation the panel makes next is a 400 with the kernel\'s reason', $status === 400 && str_contains( $body['error'], 'cannot be activated' ) && str_contains( $body['error'], '"nowhere"' ) );
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
	&& $body['switchedOn'] === [ [ 'key' => 'top', 'name' => 'Top' ], [ 'key' => 'bottom', 'name' => 'Bottom' ], [ 'key' => 'middle', 'name' => 'Middle' ] ]
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
check( '...and one that is on already is not named as switched on', $body['switchedOn'] === [ [ 'key' => 'another', 'name' => 'Another' ] ] );

// A requirement that is in the directory but switched off is not placed, so
// 'required' says nothing - and it is switched on all the same, which is the
// one thing this answer is there to say
foreach( [ 'another', 'top', 'middle', 'bottom' ] as $off )
	\Nino\Features::deactivate( $appData, $off );
$offBottom	= tarGz( featureFiles( 'Offtop', 'offtop', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'offtop\', \'name\' => \'Offtop\', \'version\' => \'1.0.0\', \'requires\' => [ \'bottom\' ] ];' ] ) );
$remote['https://catalogue.test/features/offtop-1.0.0.tar.gz'] = $offBottom;
publish( $appData, $remote, array_merge( $chained, [ entry( 'offtop', '1.0.0', $offBottom, [ 'requires' => [ 'bottom' ], 'directory' => 'Offtop' ] ) ] ), null, $privateKey );
[ $status, $body ] = callFeatures( $appData, 'apiInstall', [ 'key' => 'offtop', 'version' => '1.0.0' ] );
check( 'a requirement on disk but off: nothing placed for it, and it is named as switched on, after the feature asked for', $status === 200 && $body['required'] === []
	&& $body['switchedOn'] === [ [ 'key' => 'offtop', 'name' => 'Offtop' ], [ 'key' => 'bottom', 'name' => 'Bottom' ] ] && \Nino\Features::get( $appData, 'bottom' )['active'] === true );
// The state the sections below expect: the chain on, as it was
foreach( [ 'top', 'another' ] as $on )
	\Nino\Features::activate( $appData, $on );

// A requirement the catalogue cannot serve: refused whole, with nothing
// placed - half an installation is worse than none
$orphan = tarGz( featureFiles( 'Orphan', 'orphan', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'orphan\', \'name\' => \'Orphan\', \'version\' => \'1.0.0\', \'requires\' => [ \'nowhere\' ] ];' ] ) );
$remote['https://catalogue.test/features/orphan-1.0.0.tar.gz'] = $orphan;
publish( $appData, $remote, array_merge( $chained, [ entry( 'orphan', '1.0.0', $orphan, [ 'requires' => [ 'nowhere' ], 'directory' => 'Orphan' ] ) ] ), null, $privateKey );

$requests = [];
$result = callFeatures( $appData, 'apiInstall', [ 'key' => 'orphan', 'version' => '1.0.0' ] );
check( 'a requirement the catalogue does not list refuses the whole install, naming it', $result === failedWith( 'features_install_failed', 'required feature "nowhere": the catalogue lists no "nowhere" this kernel can run' )
	&& is_dir( NINO_FEATURES_DIR. '/Orphan' ) === false
	&& array_filter( $requests, static fn( array $r ): bool => str_ends_with( (string) $r['url'], '.tar.gz' ) ) === [] );

// ...and a refusal on the feature that was asked for is not a requirement's
// refusal. _plan() puts the missing requirements first and the feature the
// project pressed Install on last, and the loop used to label by the plan's
// SIZE: with one requirement along, every refusal in it - the last entry's
// included - was announced as a required feature, naming the feature the
// project asked for as a requirement of itself
$sidekick		= tarGz( featureFiles( 'Sidekick', 'sidekick', '1.0.0' ) );
$brokentop	= tarGz( featureFiles( 'Brokentop', 'brokentop', '1.0.0', [ 'feature.php' => '<?php return [ \'key\' => \'brokentop\', \'name\' => \'Brokentop\', \'version\' => \'1.0.0\', \'requires\' => [ \'sidekick\' ] ];' ] ) );
$remote['https://catalogue.test/features/sidekick-1.0.0.tar.gz']		= $sidekick;
$remote['https://catalogue.test/features/brokentop-1.0.0.tar.gz']	= $brokentop;
publish( $appData, $remote, array_merge( $chained, [
	entry( 'sidekick', '1.0.0', $sidekick ),
	entry( 'brokentop', '1.0.0', $brokentop. 'tampered', [ 'requires' => [ 'sidekick' ], 'directory' => 'Brokentop' ] ),
] ), null, $privateKey );

check( 'a refusal on the feature asked for is its own, not a required feature\'s', \Nino\Catalogue::install( $appData, 'feature', 'brokentop', '1.0.0' ) === 'the archive does not match what the catalogue promised'
	&& is_dir( NINO_FEATURES_DIR. '/Brokentop' ) === false );

echo "\n";


// --- Removing a feature's directory ----------------------------------------

echo "Features::remove - the step deactivating leaves out\n";

check( 'an active feature is refused, and its directory stays', callFeatures( $appData, 'apiRemove', [ 'key' => 'top' ] ) === [ 400, [ 'error' => 'feature "top" is active - switch it off before removing it', 'code' => 'features_remove_active' ] ]
	&& is_dir( NINO_FEATURES_DIR. '/Top' ) === true );
check( 'a key nothing carries is a 400 before the kernel is asked', callFeatures( $appData, 'apiRemove', [ 'key' => 'nowhere' ] ) === [ 400, [ 'error' => 'unknown feature', 'code' => 'features_unknown' ] ]
	&& callFeatures( $appData, 'apiRemove', [ 'key' => '../../etc' ] ) === [ 400, [ 'error' => 'unknown feature', 'code' => 'features_unknown' ] ] );

\Nino\Features::deactivate( $appData, 'top' );

[ $status, $body ] = callFeatures( $appData, 'apiRemove', [ 'key' => 'top' ] );
check( 'an inactive one goes, directory and all - and nothing of the privacy policy is left to remind of', $status === 200 && $body === [ 'removed' => 'top', 'privacy' => [] ]
	&& is_dir( NINO_FEATURES_DIR. '/Top' ) === false && \Nino\Features::get( $appData, 'top' ) === null );
// The same rule deactivation follows: what a feature left behind is the
// project's now, and putting the feature back finds it again
check( 'what it recorded stays - a removal is not an uninstall', isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/features']['top'] ) === true );
check( 'and the class is not in /nino/modules either way', in_array( '\\Nino\\Modules\\Top', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === false );
check( 'the activity log names it', \Nino\Modules\Features\Admin::log( 'features/remove', [ 'key' => 'top' ] ) === 'Remove the directory of feature "top"' );

// A feature reached through a symlink - a checkout linked into features/,
// which is how one is developed - goes by dropping the link, and what the
// link points at is not this project's to delete. removeDir() will not
// follow a link, which is right, and it did not remove the link either: the
// feature stayed, is_dir() followed the link and still said yes, and the
// answer blamed the file permissions for it. The Linked.php beside the
// manifest is load-bearing - manifest() refuses a feature whose class file
// is missing, and without it this would fail as an unknown feature
$linked = sys_get_temp_dir(). '/nino-linked-feature-'. bin2hex( random_bytes( 6 ) );
mkdir( $linked, 0755, true );
file_put_contents( $linked. '/feature.php', '<?php return [ \'key\' => \'linked\', \'name\' => \'Linked\', \'version\' => \'1.0.0\' ];' );
file_put_contents( $linked. '/Linked.php', '<?php namespace Nino\Modules { class Linked { public const string VERSION = \'1.0.0\'; } }' );
symlink( $linked, NINO_FEATURES_DIR. '/Linked' );
unset( $appData['./nino/features/all'] );

[ $status, $body ] = callFeatures( $appData, 'apiRemove', [ 'key' => 'linked' ] );
check( 'a symlinked feature goes by its link, and what the link points at stays', $status === 200 && $body === [ 'removed' => 'linked', 'privacy' => [] ]
	&& is_link( NINO_FEATURES_DIR. '/Linked' ) === false && is_dir( $linked ) === true );

\Nino\Filesystem::removeDir( $linked );

// What the privacy policy still says about a feature is named when its
// directory goes too - asked before the directory is deleted, because the
// sections are read from the feature's own unit, and nothing finds them after
$described = NINO_FEATURES_DIR. '/Described';
mkdir( $described. '/install/elements', 0755, true );
file_put_contents( $described. '/feature.php', '<?php return [ \'key\' => \'described\', \'name\' => \'Described\', \'version\' => \'1.0.0\' ];' );
file_put_contents( $described. '/Described.php', '<?php namespace Nino\\Modules { class Described {} }' );
file_put_contents( $described. '/install/manifest.php', '<?php return [ \'elements\' => [ \'privacy\' => \'elements/privacy.php\' ] ];' );
file_put_contents( $described. '/install/elements/privacy.php', '<?php return '. var_export( [
	'title' => 'Privacy policy',
	'model' => [ 'title' => [ 'type' => 'string', 'locale' => true ], 'text' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ], 'hidden' => [ 'type' => 'boolean' ] ],
	'*' => [ 'described' => [] ],
	'de_DE' => [ 'described' => [ 'title' => 'Beschrieben', 'text' => '<p>x</p>' ] ],
], true ). ';' );
unset( $appData['./nino/features/all'] );
check( 'the feature that brings a section is switched on, and the section is there', \Nino\Features::activate( $appData, 'described' ) === true
	&& ( \Nino\Elements::getElement( $appData, '/privacy/described', 'de_DE' )['title'] ?? '' ) === 'Beschrieben' );
\Nino\Features::deactivate( $appData, 'described' );

check( 'while the Legal module is not on, nothing is said of the policy', \Nino\Modules\Legal::contributions( $appData, 'described' ) === [] );
$appData['/nino/modules'][] = '\\Nino\\Modules\\Legal';
\Nino\AppData::writeContentData( $appData, [ '/nino/modules' ] );
[ $status, $body ] = callFeatures( $appData, 'apiRemove', [ 'key' => 'described' ] );
check( 'with it on, removing the feature names the sections of the policy that still describe it, and they stay', $status === 200 && $body === [ 'removed' => 'described', 'privacy' => [ 'Beschrieben' ] ]
	&& is_dir( $described ) === false && ( \Nino\Elements::getElement( $appData, '/privacy/described', 'de_DE' )['title'] ?? '' ) === 'Beschrieben' );
$appData['/nino/modules'] = array_values( array_diff( $appData['/nino/modules'], [ '\\Nino\\Modules\\Legal' ] ) );

echo "\n";

// --- A host of the public internet --------------------------------------------

echo "Fetch::isPublic and the catalogue - an address that is no host of the public internet is not asked\n";

$spelled = $appData;
unset( $spelled['./nino/fetch/stub'] );
$spellings = [
	'a name of the public internet'				=> [ 'https://catalogue.getnino.dev/catalogue.json', true ],
	'a name with a port and a query'			=> [ 'https://files.example.org:8443/a/b.tar.gz?x=1', true ],
	'localhost'														=> [ 'https://localhost/catalogue.json', false ],
	'a name of a local network'						=> [ 'https://nas.local/catalogue.json', false ],
	'another one'													=> [ 'https://files.intranet/x', false ],
	'a name of one label'									=> [ 'https://catalogue/x', false ],
	'an address in place of a name'				=> [ 'https://127.0.0.1/x', false ],
	'a private one'												=> [ 'https://10.0.0.5/x', false ],
	'a whole number'											=> [ 'https://2130706433/x', false ],
	'a hexadecimal one'										=> [ 'https://0x7f000001/x', false ],
	'an IPv6 one'													=> [ 'https://[::1]/x', false ],
	'a name that ends in a number'				=> [ 'https://files.example.123/x', false ],
	'http'																=> [ 'http://catalogue.getnino.dev/x', false ],
];
$wrong = [];
foreach( $spellings as $what => [ $url, $expected ] )
	if( \Nino\Fetch::isPublic( $spelled, $url, false ) !== $expected )
		$wrong[] = $what;
check( 'by its spelling: a name of two labels or more, no address, no ending of a local network, https'. ( $wrong === [] ? '' : ' - wrong for '. implode( ', ', $wrong ) ), $wrong === [] );

$lookups = [];
$resolved = [ '93.184.216.34' ];
$spelled['./nino/fetch/resolve'] = static function( string $host ) use ( &$resolved, &$lookups ): array|false {
	$lookups[] = $host;
	return $resolved;
};
check( 'a name whose every address is public is public, and the lookup was asked of the host alone', \Nino\Fetch::isPublic( $spelled, 'https://files.example.org/x' ) === true && $lookups === [ 'files.example.org' ] );
$wrong = [];
foreach( [ [ '10.1.2.3' ], [ '127.0.0.1' ], [ '169.254.169.254' ], [ '100.64.0.1' ], [ '192.168.1.1' ], [ '::1' ], [ 'fe80::1' ], [ '::ffff:10.0.0.1' ], [ '93.184.216.34', '10.0.0.1' ], [], false ] as $answer ) {
	$resolved = $answer;
	if( \Nino\Fetch::isPublic( $spelled, 'https://files.example.org/x' ) !== false )
		$wrong[] = json_encode( $answer );
}
check( 'a name that has one address that is not public - private, loopback, link local, shared, an IPv6 one, an IPv4 one in an IPv6 one - or none, is not'. ( $wrong === [] ? '' : ' - public for '. implode( ' ', $wrong ) ), $wrong === [] );
$resolved = [ '2606:2800:220:1:248:1893:25c8:1946' ];
check( 'a public IPv6 address is public', \Nino\Fetch::isPublic( $spelled, 'https://files.example.org/x' ) === true );

// The catalogue: its index, its archives and its pictures, none of them asked of a host that is not public
$nowhere = $appData;
unset( $nowhere['./nino/catalogue'] );
$nowhere['/nino/catalogue/url'] = 'https://nas.local/catalogue.json';
$requests = [];
check( 'a catalogue at a host of a local network is not fetched, and nothing is asked', \Nino\Catalogue::fetch( $nowhere ) === 'the catalogue could not be fetched: the address is no host of the public internet' && $requests === [] );

$appData['./nino/fetch/resolve'] = static fn( string $name ): array => [ '10.0.0.5' ];
unset( $appData['./nino/catalogue'] );
$requests = [];
check( 'nor one whose name the system gives a private address', str_contains( (string) \Nino\Catalogue::fetch( $appData ), 'public internet' ) === true && $requests === [] );

// The request goes to the address that was checked: the index, its signature and an archive are asked for with it
$appData['./nino/fetch/resolve'] = static fn( string $name ): array => [ '93.184.216.34' ];
unset( $appData['./nino/catalogue'] );
$requests = [];
$fetched = \Nino\Catalogue::fetch( $appData );
check( 'a name that was found public is asked for at the addresses that were found: the options of every request name them', is_array( $fetched ) === true && count( $requests ) === 2
	&& array_column( array_column( $requests, 'options' ), 'addresses' ) === [ [ '93.184.216.34' ], [ '93.184.216.34' ] ] );

$appData['./nino/fetch/resolve'] = static fn( string $name ): false => false;
unset( $appData['./nino/catalogue'] );
$requests = [];
check( 'a name the server cannot resolve is said so, not called no public host, and nothing is asked', \Nino\Catalogue::fetch( $appData ) === 'the catalogue could not be fetched: this server cannot resolve the name of the host' && $requests === [] );
unset( $appData['./nino/fetch/resolve'] );
unset( $appData['./nino/catalogue'] );
\Nino\Catalogue::fetch( $appData );

// The ranges that are not global, one of each class, decided by this class and by no list of php: the same on every version
$notGlobal = [ '192.0.0.1', '198.18.0.1', '198.19.255.1', '224.0.0.1', '255.255.255.255', '192.0.2.1', '198.51.100.1', '203.0.113.1', '192.88.99.1', '0.0.0.1', 'fec0::1', 'ff02::1', '2002:a00:1::1', '64:ff9b:1::a00:1',
	'64:ff9b::a00:1', '::ffff:0:a00:1', '::ffff:10.0.0.1', '2001:db8::1', '2001:10::1', '2001::1', 'fc00::1', 'fe80::1', '::1', '::' ];
$global = [ '93.184.216.34', '8.8.8.8', '2606:2800:220:1:248:1893:25c8:1946', '64:ff9b::5db8:d822', '2a00:1450:4001::1' ];
$wrong = [];
foreach( array_merge( $notGlobal, $global ) as $address ) {
	$spelled['./nino/fetch/resolve'] = static fn( string $name ): array => [ $address ];
	if( \Nino\Fetch::isPublic( $spelled, 'https://files.example.org/x' ) !== in_array( $address, $global, true ) )
		$wrong[] = $address;
}
check( 'every range that is not global - the ones of the documentation, of benchmarks, of multicast, of site local, of 6to4 and of the NAT64 that is local, mapped and embedded - is refused, and a global address is not'. ( $wrong === [] ? '' : ' - wrong for '. implode( ' ', $wrong ) ), $wrong === [] );

// What config.php can name is no closure: a string that is callable is not a stub of the network and not a lookup
/**
 *	A function that answers as a stub of the network and as a lookup, to be named by a string
 */
function ninoCallableString( string $first = '', array $second = [] ): array {
	return $second === [] ? [ '93.184.216.34' ] : [ 'ok' => true, 'status' => 200, 'body' => 'stubbed', 'error' => '' ];
}

$config = [ './nino/fetch/stub' => 'strval', './nino/fetch/resolve' => 'strval' ];
$closure = [ './nino/fetch/stub' => $appData['./nino/fetch/stub'] ];
check( 'a stub and a lookup that are no closure are not taken for one: the name is looked up by the system, and the name that nobody has is not public',
	\Nino\Fetch::isPublic( $config, 'https://nonexistent.invalid/x' ) === false
	&& \Nino\Fetch::isPublic( $closure, 'https://nonexistent.invalid/x' ) === true );
$config = [ './nino/fetch/resolve' => 'ninoCallableString' ];
check( '...and a string that names a function is no lookup', \Nino\Fetch::isPublic( $config, 'https://nonexistent.invalid/x' ) === false );
$config = [ './nino/fetch/stub' => 'ninoCallableString' ];
check( '...nor a stub of the network: the string is not called', \Nino\Fetch::get( $config, 'https://nonexistent.invalid/x', [ 'timeout' => 1 ] )['body'] !== 'stubbed' );

publish( $appData, $remote, array_merge( $features, [ entry( 'inner', '1.0.0', 'bytes', [ 'archive' => 'https://files.intranet/inner.tar.gz' ] ) ] ), null, $privateKey );
$requests = [];
check( 'an archive at a host that is not public is refused before it is asked for', \Nino\Catalogue::install( $appData, 'feature', 'inner', '1.0.0' ) === 'the archive could not be fetched: the address is no host of the public internet'
	&& array_filter( $requests, static fn( array $request ): bool => str_contains( $request['url'], 'intranet' ) ) === [] );
publish( $appData, $remote, $features, null, $privateKey );

echo "\n";


// --- Themes and packages of blocks -------------------------------------------

echo "Catalogue::install - a theme and a package of blocks, from the catalogue to private/packages\n";

$webp = (string) base64_decode( 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==' );
$png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#f00"/></svg>';

/**
 *	A folder with everything below it, copied
 */
function packageCopy( string $from, string $to ): void {

	mkdir( $to, 0755, true );

	foreach( array_diff( (array) scandir( $from ), [ '.', '..' ] ) as $entry )
		if( is_dir( $from. '/'. $entry ) === true )
			packageCopy( $from. '/'. $entry, $to. '/'. $entry );
		else
			copy( $from. '/'. $entry, $to. '/'. $entry );
}

/**
 *	A project with the test feature of the kernel on, as \Nino\init() registers it, its own to put packages in
 */
function packageSite( string $name ): array {

	// The feature is a directory below features/, as a project has one: the copy of the one of the fixtures
	if( is_dir( NINO_FEATURES_DIR. '/Kit' ) === false )
		packageCopy( __DIR__. '/fixtures/kit/Kit', NINO_FEATURES_DIR. '/Kit' );

	$app = ninoSandbox( $name );
	$app['./nino/filesystem/path'] = dirname( __DIR__ );
	$app['/nino/modules'] = [ '\\Nino\\Modules\\Kit' ];
	$app['/nino/html/navs'] = [ 'main', 'footer' ];

	\Nino\Features::registerComponents( $app );

	foreach( [ 'template', 'assets', 'jstext', 'legal', 'privacy', 'json', 'imagevalues', 'csrf' ] as $shortcode )
		\Nino\Html::addShortcode( $app, $shortcode, static fn(): string => '', null );

	return $app;
}

/**
 *	The files of a good block, with a picture of a svg and one of a png
 */
function blockFiles( string $svg, string $png ): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
	$slot = static fn( string $label ): array => [ 'placeholder' => '/nino/placeholder/landscape', 'width' => 800, 'height' => 600, 'label' => $words( $label, $label ) ];

	return [
		'block.json'				=> json_encode( [ 'name' => $words( 'Probe', 'Probe' ), 'description' => $words( 'Eine Probe', 'A probe' ), 'category' => 'content', 'rank' => 5,
			'images' => [ 'background' => $slot( 'Hintergrund' ), '1-photo' => $slot( 'Foto' ) ] ] ),
		'block.tpl'					=> "[band-wrap id=\"probe\" image=\"~background\"]\n\t[band-row]\n\t\t[band-col id=\"probe-1\"]\n\t\t\t[band-title ~title]\n\t\t\t[pic ~photo alt=\"\"]\n\t\t[/band-col]\n\t[/band-row]\n[/band-wrap]\n",
		'text/de_DE.json'		=> json_encode( [ '1-title' => 'Titel' ] ),
		'text/en_US.json'		=> json_encode( [ '1-title' => 'Title' ] ),
		'images/background.svg'	=> $svg,
		'images/1-photo.png'		=> $png,
	];
}

/**
 *	The files of a good theme: the page, a button, with a block
 */
function themeFiles( string $key, string $version, array $block, string $preview ): array {

	$words = static fn( string $de, string $en ): array => [ 'de_DE' => $de, 'en_US' => $en ];
	$files = [
		'theme.json'							=> json_encode( [ 'key' => $key, 'version' => $version, 'name' => $words( 'Craft', 'Craft' ), 'description' => $words( 'Weich', 'Soft' ), 'brand' => [] ] ),
		'parts/page/part.json'		=> json_encode( [ 'contract' => 'kernel@^2.0', 'knobs' => [ 'radius' => [ 'label' => $words( 'Rundung', 'Radius' ), 'ladder' => [
			[ '--page-radius' => '.375rem' ], [ '--page-radius' => '.625rem' ], [ '--page-radius' => '1rem' ], [ '--page-radius' => '1.5rem' ], [ '--page-radius' => '2rem' ] ] ] ] ] ),
		'parts/lever/part.json'	=> json_encode( [ 'contract' => 'kit@^1.0' ] ),
		'parts/lever/part.css'		=> "/*	levers - a soft shadow	*/\n.lever:not( .lever--tone-quiet ) { box-shadow: 0 2px 6px rgb( 0 0 0 / .15 ); }\n",
		'preview.webp'						=> $preview,
		'LICENSE.txt'							=> "Probe licence\n",
	];

	foreach( $block as $file => $bytes )
		$files['blocks/probe/'. $file] = $bytes;

	return $files;
}

/**
 *	The files of a package of blocks
 */
function packFiles( string $key, string $version, array $block, string $preview ): array {

	$files = [
		'pack.json'			=> json_encode( [ 'key' => $key, 'version' => $version, 'name' => [ 'de_DE' => 'Probepaket', 'en_US' => 'Probe pack' ], 'description' => 'Probe' ] ),
		'preview.webp'	=> $preview,
		'LICENSE.txt'		=> "Probe licence\n",
	];

	foreach( $block as $file => $bytes )
		$files['probe/'. $file] = $bytes;

	return $files;
}

/**
 *	The archive of a package: the files below the folder of its key
 */
function packageArchive( string $key, array $files ): string {

	$entries = [ $key. '/' => null ];

	foreach( $files as $path => $bytes )
		$entries[ $key. '/'. $path ] = (string) $bytes;

	return tarGz( $entries );
}

/**
 *	The entry of a package for archive bytes, which are put in the remote with the picture
 */
function packageOffer( array &$remote, string $kind, string $key, string $version, string $bytes, string $preview, array $more = [] ): array {

	$entry = packageEntry( $kind, $key, $version, $bytes, $more + [ 'preview' => [ 'url' => 'https://catalogue.test/previews/'. $key. '-'. $version. '.webp', 'sha256' => hash( 'sha256', $preview ), 'size' => strlen( $preview ) ] ] );
	$remote[ $entry['archive'] ] = $bytes;
	$remote[ $entry['preview']['url'] ] = $preview;

	return $entry;
}

/**
 *	Everything that lies below a folder, as the paths below it
 */
function packagesFound( string $dir ): array {

	$found = [];

	if( is_dir( $dir ) === false )
		return $found;

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST ) as $entry )
		$found[] = substr( $entry->getPathname(), strlen( $dir ) + 1 );

	sort( $found );

	return $found;
}

$site = packageSite( 'catalogue-packages' );
$site['/nino/catalogue/key'] = $publicKey;
$site['./nino/fetch/stub'] = $appData['./nino/fetch/stub'];
$siteDir = ninoSandboxDir( $site );
$siteStage = \Nino\Filesystem::path( $site, '/data/.packages' );
$packages = $siteDir. '/private/packages';

$block = blockFiles( $svg, $png );
$looks100 = tarGz( featureFiles( 'Looks', 'looks', '1.0.0' ) );
$remote['https://catalogue.test/features/looks-1.0.0.tar.gz'] = $looks100;
$looksEntry = entry( 'looks', '1.0.0', $looks100 );

$craft100 = packageArchive( 'craft-probe', themeFiles( 'craft-probe', '1.0.0', $block, $webp ) );
$gastro100 = packageArchive( 'gastro', packFiles( 'gastro', '1.0.0', $block, $webp ) );
$theme = packageOffer( $remote, 'theme', 'craft-probe', '1.0.0', $craft100, $webp, [ 'blocks' => [ 'probe' ], 'requires' => [ 'nino' => '^2.0', 'features' => [ 'looks' ], 'fonts' => [ 'lora' ] ] ] );
$pack = packageOffer( $remote, 'blocks', 'gastro', '1.0.0', $gastro100, $webp, [ 'blocks' => [ [ 'key' => 'probe', 'category' => 'content' ] ] ] );
$orphan = packageOffer( $remote, 'theme', 'orphan', '1.0.0', 'x', $webp, [ 'requires' => [ 'nino' => '^2.0', 'features' => [ 'unlisted' ] ] ] );
$ancient = packageOffer( $remote, 'theme', 'ancient', '1.0.0', 'x', $webp, [ 'requires' => [ 'nino' => '^0.9' ] ] );
$listed = [ $looksEntry ];

publish( $site, $remote, $listed, null, $privateKey, [ $theme, $pack, $orphan, $ancient ] );
$siteCatalogue = \Nino\Catalogue::fetch( $site );
$siteOffers = \Nino\Catalogue::offers( $site, $siteCatalogue, 'theme' );

check( 'the offers of a kind are those of the kind, sorted by key', array_keys( $siteOffers ) === [ 'ancient', 'craft-probe', 'orphan' ] && array_keys( \Nino\Catalogue::offers( $site, $siteCatalogue, 'blocks' ) ) === [ 'gastro' ]
	&& array_keys( \Nino\Catalogue::offers( $site, $siteCatalogue, 'feature' ) ) === [ 'looks' ] );
check( 'a theme is available, with what it asks for that is not there as a hint: the feature, which comes with it, and the font, which does not',
	$siteOffers['craft-probe']['state'] === 'available' && $siteOffers['craft-probe']['local'] === null && $siteOffers['craft-probe']['needs'] === [ 'features' => [ 'looks' ], 'fonts' => [ 'lora' ] ] && $siteOffers['craft-probe']['blocked'] === [] );
check( 'a theme that asks for a feature that is not there and not listed is incompatible, with the feature that cannot be had; so is one for a Nino that is not this one',
	$siteOffers['orphan']['state'] === 'incompatible' && $siteOffers['orphan']['blocked'] === [ 'unlisted' ] && $siteOffers['ancient']['state'] === 'incompatible' && $siteOffers['ancient']['nino'] === '^0.9' && $siteOffers['ancient']['blocked'] === [] );

$requests = [];
check( 'a theme that cannot be had is refused with what is wrong, before a byte is asked for', \Nino\Catalogue::install( $site, 'theme', 'orphan', '1.0.0' ) === 'required feature "unlisted": the catalogue lists no "unlisted" this kernel can run'
	&& \Nino\Catalogue::install( $site, 'theme', 'ancient', '1.0.0' ) === 'theme "ancient" 1.0.0 requires Nino ^0.9' && \Nino\Catalogue::install( $site, 'theme', 'none', '1.0.0' ) === 'the catalogue does not list theme "none" in version 1.0.0'
	&& \Nino\Catalogue::install( $site, 'font', 'lora', '1.0.0' ) === 'there is no such kind of package' && array_filter( $requests, static fn( array $request ): bool => str_contains( $request['url'], '.tar.gz' ) ) === [] );

$requests = [];
$result = \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.0.0' );
check( 'a theme installs, with the feature it asks for that the project has not - the feature first', $result === true
	&& array_column( $site['./nino/catalogue/placed'], 'key' ) === [ 'looks', 'craft-probe' ] && array_column( $site['./nino/catalogue/placed'], 'kind' ) === [ 'feature', 'theme' ] );
check( '...its files lie below private/packages/themes/<key> and the feature below features/, and the staging folder is empty',
	is_file( $packages. '/themes/craft-probe/theme.json' ) === true && is_file( $packages. '/themes/craft-probe/blocks/probe/images/1-photo.png' ) === true && is_file( NINO_FEATURES_DIR. '/Looks/feature.php' ) === true && stagingClean( $siteStage ) === true );
check( '...and what was asked for is two archives, each capped at the size of its entry', count( array_filter( $requests, static fn( array $request ): bool => str_ends_with( $request['url'], '.tar.gz' ) ) ) === 2
	&& array_values( array_column( array_filter( $requests, static fn( array $request ): bool => str_contains( $request['url'], 'craft-probe' ) ), 'options' ) )[0]['maxBytes'] === strlen( $craft100 ) );
check( 'a theme that was installed is current, and the hint of what it asks for is empty', \Nino\Catalogue::offers( $site, $siteCatalogue, 'theme' )['craft-probe']['state'] === 'current' && \Nino\Packages::installed( $site, 'theme' ) === [ 'craft-probe' => '1.0.0' ]
	&& \Nino\Catalogue::offers( $site, $siteCatalogue, 'theme' )['craft-probe']['needs']['features'] === [] );

$ignored = ninoWarnings();
check( 'a package of blocks installs, and its block is among the blocks in the same request, with the key of the package as its origin',
	\Nino\Catalogue::install( $site, 'blocks', 'gastro', '1.0.0' ) === true && ( \Nino\Blocks::all( $site )['probe']['origin'] ?? '' ) === 'pack:gastro' && is_file( $packages. '/blocks/gastro/pack.json' ) === true && stagingClean( $siteStage ) === true );

// An update replaces the folder whole
$craft110 = packageArchive( 'craft-probe', themeFiles( 'craft-probe', '1.1.0', $block, $webp ) + [ 'README.md' => "# new\n" ] );
$theme110 = packageOffer( $remote, 'theme', 'craft-probe', '1.1.0', $craft110, $webp, [ 'blocks' => [ 'probe' ], 'requires' => [ 'nino' => '^2.0', 'features' => [ 'looks' ] ] ] );
publish( $site, $remote, $listed, null, $privateKey, [ $theme, $theme110, $pack ] );
$siteCatalogue = \Nino\Catalogue::fetch( $site );
$siteOffers = \Nino\Catalogue::offers( $site, $siteCatalogue, 'theme' );
check( 'a newer version is an upgrade, and the newer one is what is offered', $siteOffers['craft-probe']['state'] === 'upgrade' && $siteOffers['craft-probe']['version'] === '1.1.0' && $siteOffers['craft-probe']['local'] === '1.0.0' );

// An exchange that fails puts the folder that was there back
$site['./nino/packages/move'] = static fn( string $from, string $to ): bool => false;
$ignored = ninoWarnings();
check( 'an update whose exchange fails says so, and the version that was there is there', \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.1.0' ) === 'the folder of the package cannot be exchanged'
	&& \Nino\Packages::installed( $site, 'theme' ) === [ 'craft-probe' => '1.0.0' ] && is_file( $packages. '/themes/craft-probe/README.md' ) === false && stagingClean( $siteStage ) === true );
unset( $site['./nino/packages/move'] );

check( 'it installs over the one that is there: the new files in, the folder whole', \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.1.0' ) === true && is_file( $packages. '/themes/craft-probe/README.md' ) === true
	&& \Nino\Packages::installed( $site, 'theme' ) === [ 'craft-probe' => '1.1.0' ] && array_column( $site['./nino/catalogue/placed'], 'key' ) === [ 'craft-probe' ] && stagingClean( $siteStage ) === true );

// One change at a time: another request holds the lock of the features
$holder = $site;
check( 'the lock of the features is taken by another request', \Nino\Features::lock( $holder ) === true );
check( 'an install and a removal that find it taken say so and change nothing',
	\Nino\Catalogue::install( $site, 'blocks', 'gastro', '1.0.0' ) === \Nino\Features::BUSY && \Nino\Catalogue::remove( $site, 'theme', 'craft-probe' ) === \Nino\Features::BUSY && \Nino\Packages::installed( $site, 'theme' ) === [ 'craft-probe' => '1.1.0' ] );
\Nino\Features::unlock( $holder );

// What is taken away: a theme and a package of blocks, and not a feature
check( 'a theme is taken away, and so is a package of blocks - with its blocks, in the same request',
	\Nino\Catalogue::remove( $site, 'theme', 'craft-probe' ) === true && is_dir( $packages. '/themes/craft-probe' ) === false && \Nino\Packages::installed( $site, 'theme' ) === []
	&& \Nino\Catalogue::remove( $site, 'blocks', 'gastro' ) === true && isset( \Nino\Blocks::all( $site )['probe'] ) === false );
check( 'one that is not there, a feature and a name that is none are refused with a sentence',
	\Nino\Catalogue::remove( $site, 'theme', 'craft-probe' ) === 'the package is not installed' && str_contains( (string) \Nino\Catalogue::remove( $site, 'feature', 'looks' ), 'Features::remove()' ) === true
	&& str_contains( (string) \Nino\Catalogue::remove( $site, 'theme', '../x' ), 'no key of a package' ) === true && is_dir( NINO_FEATURES_DIR. '/Looks' ) === true );

echo "\n";


// --- What a package may not be -----------------------------------------------

echo "Catalogue::install - a theme that is signed and still no theme: nothing on the disk\n";

$base = themeFiles( 'craft-probe', '1.0.0', $block, $webp );
$bare = packagesFound( $packages );

/**
 *	Publish a package whose files are the good ones of a theme changed as told - signed, with the digest and the size of the archive it really is - and install it
 *
 *	@return 	true|string
 */
$tryTheme = static function( array $change, string $kind = 'theme', string $key = 'craft-probe' ) use ( &$site, &$remote, &$listed, $privateKey, $base, $webp ): true|string {

	$bytes = packageArchive( $key, $change + $base );
	$entry = packageOffer( $remote, $kind, $key, '1.0.0', $bytes, $webp );
	publish( $site, $remote, $listed, null, $privateKey, [ $entry ] );

	return \Nino\Catalogue::install( $site, $kind, $key, '1.0.0' );
};

$cases = [
	'a php file'											=> [ [ 'evil.php' => '<?php echo 1;' ], 'evil.php: the file is no file of a theme' ],
	'a php file below a part'					=> [ [ 'parts/lever/x.php' => '<?php' ], 'parts/lever/x.php: the file is no file of a theme' ],
	'an .htaccess'										=> [ [ '.htaccess' => 'Options +ExecCGI' ], 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::PATH ] ],
	'an @import'											=> [ [ 'parts/lever/part.css' => "@import 'x.css';\n.lever { color: red; }" ], 'parts/lever/part.css: ' ],
	'a token of another group, read'	=> [ [ 'parts/lever/part.css' => '.lever { color: var(--crown-wrap-ink); }' ], 'parts/lever/part.css: ' ],
	'a token of another group, set'		=> [ [ 'parts/lever/part.css' => ':root { --page-size: 1px; }' ], 'parts/lever/part.css: ' ],
	'a url()'													=> [ [ 'parts/lever/part.css' => '.lever { background: url(x.png); }' ], 'parts/lever/part.css: ' ],
	'a script of a block'							=> [ [ 'blocks/probe/block.tpl' => '[raw]<script>alert(1)</script>[/raw]' ], 'blocks/probe: ' ],
	'a picture that is no webp'				=> [ [ 'preview.webp' => 'this is no picture' ], 'preview.webp: ' ],
];
$problems = [];
foreach( $cases as $what => [ $change, $sentence ] ) {
	$ignored = ninoWarnings();
	$result = $tryTheme( $change );
	if( is_string( $result ) === false || str_starts_with( $result, $sentence ) === false || packagesFound( $packages ) !== $bare || stagingClean( $siteStage ) === false )
		$problems[] = $what. ' => '. json_encode( $result );
}
check( 'a theme with a php file, an @import, a token that is not its group\'s - read or set -, a url(), an .htaccess, a script in a block or a picture that is none is refused with the file and the rule, and nothing is on the disk'
	. ( $problems === [] ? '' : ' - '. implode( '; ', $problems ) ), $problems === [] );
check( '...the refusal is kept for a panel, as the code, the file and the words, with the kind, key and version of the entry', ( $site['./nino/catalogue/problem']['kind'] ?? '' ) === 'theme' && ( $site['./nino/catalogue/problem']['code'] ?? '' ) === 'preview'
	&& $site['./nino/catalogue/problem']['path'] === 'preview.webp' && $site['./nino/catalogue/problem']['key'] === 'craft-probe' && $site['./nino/catalogue/problem']['version'] === '1.0.0' );

$ignored = ninoWarnings();
$result = $tryTheme( [ 'theme.json' => json_encode( [ 'key' => 'other', 'version' => '1.0.0', 'name' => 'x', 'description' => 'x' ] ) ] );
check( 'a theme that says another key than the catalogue promised is refused - it is installed as the key of the entry -, and nothing is on the disk', $result === 'theme.json: "key" is "other" and the package is installed as "craft-probe"' && packagesFound( $packages ) === $bare && stagingClean( $siteStage ) === true );
$result = $tryTheme( [ 'theme.json' => json_encode( [ 'key' => 'craft-probe', 'version' => '1.0.1', 'name' => 'x', 'description' => 'x' ] ) ] );
check( '...and one that says another version', $result === 'the archive holds "craft-probe" 1.0.1, the catalogue promised "craft-probe" 1.0.0' && packagesFound( $packages ) === $bare && stagingClean( $siteStage ) === true );

$bytes = packageArchive( 'craft-probe', $base );
$entry = packageOffer( $remote, 'theme', 'craft-probe', '1.0.0', $bytes, $webp, [ 'sha256' => str_repeat( 'a', 64 ) ] );
publish( $site, $remote, $listed, null, $privateKey, [ $entry ] );
check( 'an archive whose digest is not the entry\'s is refused', \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.0.0' ) === 'the archive does not match what the catalogue promised' && packagesFound( $packages ) === $bare );
$entry = packageOffer( $remote, 'theme', 'craft-probe', '1.0.0', $bytes, $webp, [ 'size' => strlen( $bytes ) + 1 ] );
publish( $site, $remote, $listed, null, $privateKey, [ $entry ] );
$longer = \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.0.0' );
$entry = packageOffer( $remote, 'theme', 'craft-probe', '1.0.0', $bytes, $webp, [ 'size' => strlen( $bytes ) - 1 ] );
publish( $site, $remote, $listed, null, $privateKey, [ $entry ] );
check( '...and one whose size is not: more than the entry says is not read, less is not what was promised', $longer === 'the archive does not match what the catalogue promised'
	&& \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.0.0' ) === 'the archive could not be fetched: the answer exceeds '. ( strlen( $bytes ) - 1 ). ' bytes' && packagesFound( $packages ) === $bare );

$ignored = ninoWarnings();
$result = $tryTheme( [ 'evil.php' => '<?php' ], 'blocks', 'gastro' );
check( 'a package of blocks is checked as a package of blocks: the files of a theme are no files of it', is_string( $result ) === true && packagesFound( $packages ) === $bare );

$link = tarGz( [ 'craft-probe/' => null, 'craft-probe/theme.json' => [ 'link' => '/etc/passwd' ] ] );
$remote['https://catalogue.test/theme/link.tar.gz'] = $link;
publish( $site, $remote, $listed, null, $privateKey, [ packageEntry( 'theme', 'craft-probe', '1.0.0', $link, [ 'archive' => 'https://catalogue.test/theme/link.tar.gz' ] ) ] );
check( 'an archive with a link in it is refused for what it is, and nothing is on the disk', \Nino\Catalogue::install( $site, 'theme', 'craft-probe', '1.0.0' ) === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::TYPE ] && packagesFound( $packages ) === $bare );

$many = [];
for( $i = 0; $i < 401; $i++ )
	$many['blocks/probe/images/f'. $i. '.png'] = $png;
$result = $tryTheme( $many );
$big = $tryTheme( [ 'preview.webp' => str_repeat( 'x', \Nino\Blocks::MAX_PICTURE + 1 ) ] );
check( 'an archive of more entries than a theme may have, and one with a file that is larger than a picture of a block may be, are refused by the archive and nothing is on the disk',
	$result === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::COUNT ] && $big === 'the archive is refused: '. \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::FILE_SIZE ] && packagesFound( $packages ) === $bare && stagingClean( $siteStage ) === true );

check( 'after all of that there is no theme, no package of blocks and no staging folder', \Nino\Packages::installed( $site, 'theme' ) === [] && \Nino\Packages::installed( $site, 'blocks' ) === [] && packagesFound( $packages ) === $bare && stagingClean( $siteStage ) === true );

echo "\n";


check( 'an archive is read by \Nino\Archive in memory: the catalogue names no phar, and the size of an archive of each kind is a constant of it',
	str_contains( (string) file_get_contents( dirname( __DIR__ ). '/_nino/Nino/Catalogue/Catalogue.php' ), 'Phar' ) === false
	&& \Nino\Catalogue::ARCHIVE_BYTES === [ 'feature' => 8388608, 'theme' => 2097152, 'blocks' => 2097152 ] );

// A server without zlib is told so before anything is downloaded
$childFile = $siteDir. '/nozlib.php';
file_put_contents( $childFile, "<?php\ndefine( 'NINO_FEATURES_DIR', \$argv[1] );\nrequire \$argv[2];\n\$app = ninoSandbox( 'nozlib' );\n\$asked = 0;\n"
	. "\$app['./nino/fetch/stub'] = static function() use ( &\$asked ): array { \$asked++; return [ 'ok' => false, 'status' => 404, 'error' => 'http 404' ]; };\n"
	. "echo json_encode( [ \\Nino\\Catalogue::install( \$app, 'theme', 'craft-probe', '1.0.0' ), \$asked ] ), \"\\n\";\n\\Nino\\Filesystem::removeDir( ninoSandboxDir( \$app ) );\n" );
$child = proc_open( [ PHP_BINARY, '-d', 'disable_functions=inflate_init,inflate_add', $childFile, NINO_FEATURES_DIR, __DIR__. '/harness.php' ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
$said = json_decode( trim( (string) stream_get_contents( $pipes[1] ) ), true );
proc_close( $child );
check( 'without the functions of zlib an install is refused with the sentence for it, and nothing was asked of the network', $said === [ \Nino\Catalogue::ARCHIVE_WHY[ \Nino\Archive::UNSUPPORTED ], 0 ] );

echo "\n";


// --- The picture of a package ------------------------------------------------

echo "Catalogue::preview - fetched when it is asked for, checked, and kept\n";

$site2 = packageSite( 'catalogue-previews' );
$site2['/nino/catalogue/key'] = $publicKey;
$site2['./nino/fetch/stub'] = $appData['./nino/fetch/stub'];
$kept = ninoSandboxDir( $site2 ). '/private/data/.catalogue/previews';

$themeBare = packageOffer( $remote, 'theme', 'craft-probe', '1.0.0', $craft100, $webp );
publish( $site2, $remote, $listed, null, $privateKey, [ $themeBare ] );

$requests = [];
check( 'before the catalogue is loaded there is nothing to ask for', \Nino\Catalogue::preview( $site2, 'theme', 'craft-probe' ) === 'the catalogue has not been loaded' && $requests === [] );

\Nino\Catalogue::fetch( $site2 );
$requests = [];
$answer = \Nino\Catalogue::preview( $site2, 'theme', 'craft-probe' );
check( 'the picture is the bytes of the entry, kept below data/.catalogue/previews/ by its digest', is_array( $answer ) === true && $answer['bytes'] === $webp && $answer['sha256'] === hash( 'sha256', $webp )
	&& $answer['path'] === $kept. '/'. hash( 'sha256', $webp ). '.webp' && is_file( $answer['path'] ) === true );
check( '...asked for once, at the address of the entry, with no more bytes than it says', count( $requests ) === 1 && $requests[0]['url'] === $themeBare['preview']['url'] && $requests[0]['options']['maxBytes'] === strlen( $webp ) );
$requests = [];
check( '...and from the disk after that, with no request', \Nino\Catalogue::preview( $site2, 'theme', 'craft-probe' )['bytes'] === $webp && $requests === [] );
$requests = [];
unset( $site2['./nino/catalogue'] );
\Nino\Catalogue::fetch( $site2 );
check( 'the fetch of the catalogue asks for no picture: two requests, the index and its signature', array_column( $requests, 'url' ) === [ \Nino\Catalogue::DEFAULT_URL, \Nino\Catalogue::DEFAULT_URL. '.sig' ] );

file_put_contents( $answer['path'], 'tampered' );
$requests = [];
check( 'a file that is kept and is no longer what the entry says is fetched again', \Nino\Catalogue::preview( $site2, 'theme', 'craft-probe' )['bytes'] === $webp && count( $requests ) === 1 && (string) file_get_contents( $answer['path'] ) === $webp );
// The file of a request that is writing, and what no catalogue names
file_put_contents( $kept. '/.aaaaaaaaaaaa.tmp', 'half a picture' );
file_put_contents( $kept. '/'. str_repeat( 'b', 64 ). '.webp', 'a picture nobody names' );
file_put_contents( $answer['path'], 'tampered' );
\Nino\Catalogue::preview( $site2, 'theme', 'craft-probe' );
check( 'a picture that is kept is pruned to what the catalogue names, and the file another request is writing is left as it lies', is_file( $kept. '/.aaaaaaaaaaaa.tmp' ) === true && is_file( $kept. '/'. str_repeat( 'b', 64 ). '.webp' ) === false );
unlink( $kept. '/.aaaaaaaaaaaa.tmp' );

check( 'a package that is not there, a kind that is none and a key that is none are refused', \Nino\Catalogue::preview( $site2, 'theme', 'nothing' ) === 'the catalogue has no picture of "nothing"'
	&& is_string( \Nino\Catalogue::preview( $site2, 'font', 'craft-probe' ) ) === true && is_string( \Nino\Catalogue::preview( $site2, 'theme', '../x' ) ) === true );

// What the picture may not be: the digest, the type by its head, the size, the address
$tryPreview = static function( string $bytes, array $preview = [] ) use ( &$site2, &$remote, $privateKey, $craft100 ): array|string {

	$entry = packageEntry( 'theme', 'wrong', '1.0.0', $craft100, [ 'preview' => $preview + [ 'url' => 'https://catalogue.test/previews/wrong.webp', 'sha256' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ) ] ] );
	$remote[ $entry['preview']['url'] ] = $bytes;
	publish( $site2, $remote, [], null, $privateKey, [ $entry ] );
	\Nino\Catalogue::fetch( $site2 );

	return \Nino\Catalogue::preview( $site2, 'theme', 'wrong' );
};

check( 'a picture whose digest is not the one the catalogue names is refused', $tryPreview( $webp, [ 'sha256' => str_repeat( 'a', 64 ) ] ) === 'the picture does not match what the catalogue promised' );
check( 'a picture that has the digest and is a png, not a webp, is refused by its head', $tryPreview( $png ) === 'the picture does not match what the catalogue promised' );
$wave = substr( $webp, 0, 8 ). 'WAVE'. substr( $webp, 12 );
check( 'a RIFF that is no webp, with the length it says and the digest the entry names, is refused', $tryPreview( $wave ) === 'the picture does not match what the catalogue promised' );
check( 'a webp whose head says another length than it has is refused', $tryPreview( substr( $webp, 0, -2 ) ) === 'the picture does not match what the catalogue promised' );
check( 'a picture that is more than the entry says is not read to the end', $tryPreview( $webp, [ 'size' => 20 ] ) === 'the picture could not be fetched: the answer exceeds 20 bytes' );
$other = substr( $webp, 0, 4 ). pack( 'V', strlen( $webp ) - 6 ). substr( $webp, 8 ). "\0\0";
check( 'a picture at a host that is not public is not asked for', $tryPreview( $other, [ 'url' => 'https://files.intranet/wrong.webp' ] ) === 'the picture could not be fetched: the address is no host of the public internet' );
check( 'nothing of what was refused is kept', array_values( array_diff( (array) scandir( $kept ), [ '.', '..', hash( 'sha256', $webp ). '.webp' ] ) ) === [] );

echo "\n";


// --- The actions of the panel for the packages --------------------------------

echo "Features panel - the packages: offers of a kind, install, update, remove, the picture\n";

// The feature the theme asks for was placed by the install above: this project starts without it
\Nino\Filesystem::removeDir( NINO_FEATURES_DIR. '/Looks' );

$site3 = packageSite( 'catalogue-actions' );
$site3['/nino/catalogue/key'] = $publicKey;
$site3['./nino/fetch/stub'] = $appData['./nino/fetch/stub'];
$site3['/nino/admin/backups']	= false;
$site3['/nino/admin/logs']		= false;
$site3['/nino/auth/user']			= [];
$site3['/nino/auth/roles']		= [];
\Nino\Auth::insertUser( $site3, 'editor@example.com', 'correct horse battery staple', [ '/_admin/text/manage' ] );
\Nino\Auth::insertUser( $site3, 'dev@example.com', 'correct horse battery staple', [ \Nino\Modules\Features\Admin::MANAGE_PERM ] );
$site3Stage = \Nino\Filesystem::path( $site3, '/data/.packages' );
$site3Packages = ninoSandboxDir( $site3 ). '/private/packages';

// What the sections above put in the remote for these packages is what they are, again
$theme = packageOffer( $remote, 'theme', 'craft-probe', '1.0.0', $craft100, $webp, [ 'blocks' => [ 'probe' ], 'requires' => [ 'nino' => '^2.0', 'features' => [ 'looks' ], 'fonts' => [ 'lora' ] ] ] );
$pack = packageOffer( $remote, 'blocks', 'gastro', '1.0.0', $gastro100, $webp, [ 'blocks' => [ [ 'key' => 'probe', 'category' => 'content' ] ] ] );

// A package in the catalogue that is no package: left out, and the panel says it
$brokenTheme = [ 'kind' => 'theme', 'key' => 'broken', 'version' => '1.0.0', 'name' => '' ];
$requests = [];
publish( $site3, $remote, $listed, null, $privateKey, [ $theme, $pack, $brokenTheme ] );

$packageActions = [
	'apiPackages'				=> [ 'kind' => 'theme' ],
	'apiPackageInstall'	=> [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => '1.0.0' ],
	'apiPackageUpdate'	=> [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => '1.0.0' ],
	'apiPackageRemove'	=> [ 'kind' => 'theme', 'key' => 'craft-probe' ],
	'apiPackagePreview'	=> [ 'kind' => 'theme', 'key' => 'craft-probe' ],
];

foreach( $packageActions as $method => $data )
	check( $method. ' is 401 without an account', callFeatures( $site3, $method, $data ) === [ 401, [ 'error' => 'not logged in', 'code' => 'session' ] ] );

\Nino\Auth::loginUser( $site3, 'editor@example.com', 'correct horse battery staple' );
foreach( $packageActions as $method => $data )
	check( $method. ' is 403 without the permission', callFeatures( $site3, $method, $data ) === [ 403, [ 'error' => 'not allowed' ] ] );
check( '...and nothing was asked of the catalogue, and nothing is on the disk', $requests === [] && packagesFound( $site3Packages ) === [] );

\Nino\Auth::loginUser( $site3, 'dev@example.com', 'correct horse battery staple' );

[ $status, $body ] = callFeatures( $site3, 'apiPackages', [ 'kind' => 'font' ] );
check( 'a kind that is none is a 400 with its code', $status === 400 && ( $body['code'] ?? '' ) === 'features_package_invalid' && $requests === [] );
[ $status, $body ] = callFeatures( $site3, 'apiPackages', [ 'kind' => 'theme' ] );
check( 'before the catalogue was fetched there is none to show, and no request is made to show it', $status === 200 && $body['catalogue'] === null && $requests === [] );

[ $status, $body ] = callFeatures( $site3, 'apiPackages', [ 'kind' => 'theme', 'refresh' => true ] );
check( 'with refresh the catalogue is fetched - two requests - and the themes are answered: what the list reads, and not the address of the archive or the picture', $status === 200 && count( $requests ) === 2
	&& array_keys( $body ) === [ 'kind', 'catalogue', 'catalogueUrl', 'fetched', 'writable', 'offers', 'unreadable' ] && array_column( $body['offers'], 'key' ) === [ 'craft-probe']
	&& str_contains( (string) json_encode( $body ), 'tar.gz' ) === false && str_contains( (string) json_encode( $body ), 'previews' ) === false );
check( '...an offer has its state, what it asks for that is not there, its parts, fonts and blocks, and whether it has a picture', ( $body['offers'][0]['state'] ?? '' ) === 'available' && $body['offers'][0]['needs'] === [ 'features' => [ 'looks' ], 'fonts' => [ 'lora' ] ]
	&& $body['offers'][0]['blocks'] === [ [ 'key' => 'probe', 'category' => '' ] ] && $body['offers'][0]['fonts'] === [ 'lora' ] && $body['offers'][0]['parts'] === [] && $body['offers'][0]['preview'] === true
	&& $body['offers'][0]['name'] === 'Craft-probe' );
check( '...and the entry of a theme that is none is listed with the reason, the others of the kind are not', $body['unreadable'] === [ [ 'key' => 'broken', 'reason' => '"name" must be a string or a locale => string map' ] ]
	&& callFeatures( $site3, 'apiPackages', [ 'kind' => 'blocks' ] )[1]['unreadable'] === [] );

$requests = [];
[ $status, $body ] = callFeatures( $site3, 'apiPackages', [ 'kind' => 'feature' ] );
check( 'the features of the catalogue are answered the same way, with their category and maturity, from what was kept', $status === 200 && $requests === [] && array_column( $body['offers'], 'key' ) === [ 'looks' ] && array_key_exists( 'category', $body['offers'][0] ) === true );

// The picture: a file of the response, the one the kernel keeps
$requests = [];
$response = ninoCall( $site3, [ \Nino\Modules\Features\Admin::class, 'apiPackagePreview' ], [ 'kind' => 'theme', 'key' => 'craft-probe' ] );
$kept = ninoSandboxDir( $site3 ). '/private/data/.catalogue/previews/'. hash( 'sha256', $webp ). '.webp';
check( 'the picture is answered as a file - the one kept below data/, webp, private - and the request has chosen no path', $response[0] === 200 && is_file( $kept ) === true && count( $requests ) === 1
	&& ( $response[2]['/nino/http/response']['file'] ?? '' ) === $kept && ( $response[2]['/nino/http/response']['header']['Content-Type'] ?? '' ) === 'image/webp' && str_starts_with( (string) ( $response[2]['/nino/http/response']['header']['Cache-Control'] ?? '' ), 'private' ) === true );
check( '...inline, under a name of its own and not one of the request', ( $response[2]['/nino/http/response']['header']['Content-Disposition'] ?? '' ) === 'inline; filename="preview.webp"' );
$response = callFeatures( $site3, 'apiPackagePreview', [ 'kind' => 'theme', 'key' => '../x' ] );
check( 'a key that is none, and a package the catalogue has no picture of, are a 400 with the code of the action - no 404, which is for a file that is not there', $response[0] === 400 && ( callFeatures( $site3, 'apiPackagePreview', [ 'kind' => 'theme', 'key' => 'nothing' ] )[1]['code'] ?? '' ) === 'features_preview_failed' && callFeatures( $site3, 'apiPackagePreview', [ 'kind' => 'theme', 'key' => 'nothing' ] )[0] === 400 );

$requests = [];
[ $status, $body ] = callFeatures( $site3, 'apiPackageInstall', [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => '1.0.0' ] );
check( 'a theme is installed with the feature it asks for, which is switched on: the answer says what was placed, in order, and what is on now', $status === 200
	&& array_column( $body['placed'], 'key' ) === [ 'looks', 'craft-probe' ] && array_column( $body['switchedOn'], 'key' ) === [ 'looks' ] && ( $body['package']['state'] ?? '' ) === 'current' && ( $body['package']['local'] ?? '' ) === '1.0.0'
	&& is_file( $site3Packages. '/themes/craft-probe/theme.json' ) === true && \Nino\Features::get( $site3, 'looks' )['active'] === true && stagingClean( $site3Stage ) === true );
[ $status, $body ] = callFeatures( $site3, 'apiPackageInstall', [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => '1.0.0' ] );
check( 'a theme that is installed is not installed again: that is an update', $status === 400 && ( $body['code'] ?? '' ) === 'features_package_installed' );
[ $status, $body ] = callFeatures( $site3, 'apiPackageUpdate', [ 'kind' => 'blocks', 'key' => 'gastro', 'version' => '1.0.0' ] );
check( 'a package that is not installed is not updated: that is an install', $status === 400 && ( $body['code'] ?? '' ) === 'features_package_absent' );
[ $status, $body ] = callFeatures( $site3, 'apiPackageInstall', [ 'kind' => 'feature', 'key' => 'looks', 'version' => '1.0.0' ] );
check( 'a feature is not installed here: it has its own action', $status === 400 && ( $body['code'] ?? '' ) === 'features_package_invalid' );
foreach( [ [ 'kind' => 'theme', 'key' => 'Craft', 'version' => '1.0.0' ], [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => 'latest' ], [ 'kind' => 'theme', 'key' => [ 'a' ], 'version' => '1.0.0' ], [ 'kind' => 'theme', 'key' => str_repeat( 'a', 41 ), 'version' => '1.0.0' ] ] as $posted )
	$invalid[] = callFeatures( $site3, 'apiPackageInstall', $posted )[1]['code'] ?? '';
check( 'a key that is no slug, a version that is none and a key that is no string are refused before the kernel is asked', array_unique( $invalid ) === [ 'features_package_invalid' ] );

// A refused package: the sentence names the file, the rule is the kernel's, and nothing is on the disk
$evil = packageArchive( 'evil-one', themeFiles( 'evil-one', '1.0.0', [], $webp ) + [ 'x.php' => '<?php' ] );
$evilEntry = packageOffer( $remote, 'theme', 'evil-one', '1.0.0', $evil, $webp );
publish( $site3, $remote, $listed, null, $privateKey, [ $theme, $pack, $evilEntry ] );
\Nino\Catalogue::fetch( $site3 );
$ignored = ninoWarnings();
[ $status, $body ] = callFeatures( $site3, 'apiPackageInstall', [ 'kind' => 'theme', 'key' => 'evil-one', 'version' => '1.0.0' ] );
check( 'a theme with a php file is a 400 with the code of it and the sentence of the kernel - the file and the rule - and nothing of it is on the disk', $status === 400 && ( $body['code'] ?? '' ) === 'features_package_failed'
	&& $body['params'] === [ 'x.php: the file is no file of a theme' ] && is_dir( $site3Packages. '/themes/evil-one' ) === false && stagingClean( $site3Stage ) === true && str_contains( (string) json_encode( $body ), ninoSandboxDir( $site3 ) ) === false );

// One change at a time
$holder = $site3;
\Nino\Features::lock( $holder );
check( 'an install, an update and a removal that find the lock taken are a 409 with the code of it, and change nothing',
	callFeatures( $site3, 'apiPackageInstall', [ 'kind' => 'blocks', 'key' => 'gastro', 'version' => '1.0.0' ] )[0] === 409 && callFeatures( $site3, 'apiPackageUpdate', [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => '1.0.0' ] )[0] === 409
	&& callFeatures( $site3, 'apiPackageRemove', [ 'kind' => 'theme', 'key' => 'craft-probe' ] ) === [ 409, [ 'error' => \Nino\Features::BUSY, 'code' => 'features_busy' ] ] && \Nino\Packages::installed( $site3, 'blocks' ) === [] && is_dir( $site3Packages. '/themes/craft-probe' ) === true );
\Nino\Features::unlock( $holder );

[ $status, $body ] = callFeatures( $site3, 'apiPackageInstall', [ 'kind' => 'blocks', 'key' => 'gastro', 'version' => '1.0.0' ] );
check( 'a package of blocks is installed the same way, with nothing to switch on', $status === 200 && array_column( $body['placed'], 'key' ) === [ 'gastro' ] && $body['switchedOn'] === [] && is_file( $site3Packages. '/blocks/gastro/pack.json' ) === true );

$craft110 = packageArchive( 'craft-probe', themeFiles( 'craft-probe', '1.1.0', blockFiles( $svg, $png ), $webp ) );
$theme110 = packageOffer( $remote, 'theme', 'craft-probe', '1.1.0', $craft110, $webp, [ 'blocks' => [ 'probe' ] ] );
publish( $site3, $remote, $listed, null, $privateKey, [ $theme110, $pack ] );
[ $status, $body ] = callFeatures( $site3, 'apiPackages', [ 'kind' => 'theme', 'refresh' => true ] );
check( 'the list says a theme that is installed in an older version has an update', $status === 200 && $body['offers'][0]['state'] === 'upgrade' && $body['offers'][0]['local'] === '1.0.0' && $body['offers'][0]['version'] === '1.1.0' );
[ $status, $body ] = callFeatures( $site3, 'apiPackageUpdate', [ 'kind' => 'theme', 'key' => 'craft-probe', 'version' => '1.1.0' ] );
check( 'the update replaces it, and the answer says what it is now', $status === 200 && ( $body['package']['local'] ?? '' ) === '1.1.0' && ( $body['package']['state'] ?? '' ) === 'current' && \Nino\Packages::installed( $site3, 'theme' ) === [ 'craft-probe' => '1.1.0' ] );

[ $status, $body ] = callFeatures( $site3, 'apiPackageRemove', [ 'kind' => 'theme', 'key' => 'craft-probe' ] );
check( 'a theme is removed, and a package of blocks', $status === 200 && $body === [ 'removed' => 'craft-probe', 'kind' => 'theme' ] && is_dir( $site3Packages. '/themes/craft-probe' ) === false
	&& callFeatures( $site3, 'apiPackageRemove', [ 'kind' => 'blocks', 'key' => 'gastro' ] )[0] === 200 && \Nino\Packages::installed( $site3, 'blocks' ) === [] );
[ $status, $body ] = callFeatures( $site3, 'apiPackageRemove', [ 'kind' => 'theme', 'key' => 'craft-probe' ] );
check( 'one that is not there is a 400, a feature is not removed here', $status === 400 && ( $body['code'] ?? '' ) === 'features_package_absent' && callFeatures( $site3, 'apiPackageRemove', [ 'kind' => 'feature', 'key' => 'looks' ] )[0] === 400
	&& is_dir( NINO_FEATURES_DIR. '/Looks' ) === true );

$missing = [];
foreach( [ 'en_US', 'de_DE' ] as $locale )
	foreach( [ 'features_package_invalid', 'features_package_installed', 'features_package_absent', 'features_package_failed', 'features_package_remove_failed', 'features_preview_failed' ] as $code )
		if( panelWord( $locale, '/_admin/error/'. $code, 'x' ) === '' )
			$missing[] = $locale. ' '. $code;
check( 'the codes of these actions have a sentence in both interface languages', $missing === [] );

foreach( [ $site, $site2, $site3 ] as $gone )
	\Nino\Filesystem::removeDir( ninoSandboxDir( $gone ) );

echo "\n";

ninoDone( $appData );
