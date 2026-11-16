<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	markup-safety-smoke.php	Test of what a value can do to the page it stands in: the text of a key, the value of a
 *											fill, the field of an element and the value of an attribute - each one holding a call of a
 *											template, a csrf field, a fill of the site, markup that closes a tag, a bracket, a null
 *											byte and a megabyte of text - is drawn as what it is and never read as a call. A
 *											template that includes itself, seventeen sources inside one another, the answer of a
 *											shortcode that holds a call, a template that is too large and a fill in the source of a
 *											call that is made to break out of it end where the kernel ends them.
 *											Runs against an isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/markup-safety-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'markup-safety' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );
mkdir( $sandbox. '/private/elements', 0777, true );
mkdir( $sandbox. '/public/images', 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

\Nino\Csrf::init( $appData );
\Nino\Modules\Csrf::init( $appData );
\Nino\Html::init( $appData );
\Nino\Modules\Template::init( $appData );
\Nino\Modules\Elements::init( $appData );
\Nino\Modules\Components::init( $appData );
\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', str_repeat( 'c', 64 ) );

\Nino\Html::addFills( $appData, [ '/project/company/general/name' => 'ACME-NAME' ], '*' );

// What the call of a template or of the csrf field would leave in a page
\Nino\Filesystem::putFileContent( $appData, '/templates/x.tpl', 'TEMPLATE-X-RAN' );

\Nino\Elements::insertElementType( $appData, '/safety', [ 'title' => [ 'type' => 'string' ] ] );

$render = static function( string $source ) use ( &$appData ): string {
	return \Nino\Html::renderHtml( $appData, $source );
};

// The value of an attribute written the way the notation writes one
$quoted = static fn( string $value ): string => '"'. str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $value ). '"';
$escaped = static fn( string $value ): string => str_replace( '[', '&#91;', htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );

$payloads = [
	'a call of a template'	=> '[template /templates/x]',
	'the csrf field'				=> '[csrf]',
	'a fill of the site'		=> '[[/project/company/general/name]]',
	'a tag that closes'			=> '"><script>alert(1)</script>',
	'a closing bracket'			=> ']',
	'a null byte'						=> "a\0b",
	'a megabyte'						=> '[template /templates/x] [csrf] '. str_repeat( 'abcdefgh', 1 << 17 ),
];

// Whether a page shows what a call of the template or of the csrf field leaves behind
$read = static fn( string $html ): bool => str_contains( $html, 'TEMPLATE-X-RAN' ) === true || str_contains( $html, 'name="_csrf"' ) === true;

echo "A value is the text it is\n";

$index = 0;

foreach( $payloads as $what => $payload ) {

	$index++;
	$key = '/template/safety/value'. $index. '/text';

	// The text of a key, drawn by a component
	\Nino\Html::addFills( $appData, [ $key => $payload ], '*' );
	$html = $render( '[title '. $key. ']' );
	$shown = $payload === '[[/project/company/general/name]]' ? 'ACME-NAME' : $payload;
	check( 'the text of a key that holds '. $what. ' is escaped text, whole, and no call', $read( $html ) === false && $html === '<h3 class="nino-section-title">'. $escaped( $shown ). '</h3>' );

	// The value of a fill, in the page: the project's own words go in as they are, and a fill inside is a fill - and nothing is a call
	$html = $render( '<p>[[/template/safety/value'. $index. '/text]]</p>' );
	$expected = $payload === '[[/project/company/general/name]]' ? 'ACME-NAME' : $payload;
	check( 'the value of a fill that holds '. $what. ' stands in the page as it is, and no call in it is read', $read( $html ) === false && $html === '<p>'. $expected. '</p>' );

	// The field of an element, in the text of a loop and in the argument of a call in it
	\Nino\Elements::insertElement( $appData, '/safety/e'. $index, [ 'title' => $payload ], '*' );
	$stored = (string) ( \Nino\Elements::getElement( $appData, '/safety/e'. $index, '' )['title'] ?? '' );
	$html = $render( '[element /safety/e'. $index. ']<i>[[title]]</i>[/element]' );
	check( 'the field of an element that holds '. $what. ' is escaped text in the text of a loop, and no call', $read( $html ) === false && $html === '<i>'. $escaped( $stored ). '</i>' );

	$html = $render( '[element /safety/e'. $index. '][button text="[[title]]" href="#"][/element]' );
	check( '...and the text of a call in it, which the field is put into', $read( $html ) === false && $html === '<a class="nino-btn" href="#">'. $escaped( $stored ). '</a>' );

	// The value of an attribute of a call of the page: a value, whatever it holds - a fill in it is put in, as the page has always had them, and nothing else is read
	$html = $render( '[button text='. $quoted( $payload ). ' href="#"]' );
	check( 'the value of an attribute that holds '. $what. ' is escaped text, and no call', $read( $html ) === false && $html === '<a class="nino-btn" href="#">'. $escaped( $shown ). '</a>' );

	// The alt text and the file name of a picture, as the tokens of what [imagevalues] draws
	$file = 'p/'. $payload. '.jpg';
	$appData['/nino/html/images']['/safety'] = [ 'label' => 'Safety', 'width' => 5, 'height' => 6, 'filename' => $file, 'alt' => [ \Nino\Locales::getCurrentLocale( $appData ) => $payload ] ];
	$html = $render( '[imagevalues /safety]<m s="[[src]]" a="[[alt]]">[[alt]][/imagevalues]' );
	check( 'the alt text and the address of a picture that hold '. $what. ' are escaped text, whole, in the attribute and in the text, and no call', $read( $html ) === false
		&& $html === '<m s="'. \Nino\Components::escape( \Nino\Images::getUrl( $appData, $file ) ). '" a="'. \Nino\Components::escape( $payload ). '">'. \Nino\Components::escape( $payload ) );
}
unset( $appData['/nino/html/images']['/safety'] );

echo "\nThe label of a field and the subject of a mail are texts\n";

\Nino\Filesystem::putFileContent( $appData, '/templates/mail-probe.tpl', '[[fields]]' );
$mails = new \ArrayObject();
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( $mails ): void {
	$mails->append( $mail );
	$mail['sent'] = true;
} );
\Nino\Html::addFills( $appData, [ '/module/form/subject/user' => 'Thanks [csrf]' ], '*' );

$index = 0;

foreach( $payloads as $what => $payload ) {

	$index++;
	$form = [ 'key' => 'probe', 'name' => 'Probe', 'to' => 'owner@example.com', 'subject' => $payload. ' von [[/project/company/general/name]]', 'confirm' => false, 'ownerTemplate' => '/templates/mail-probe', 'userTemplate' => '/templates/mail-probe',
		'fields' => [ [ 'name' => 'x', 'label' => $payload, 'type' => 'text', 'required' => false, 'options' => [] ] ] ];
	$shown = $payload === '[[/project/company/general/name]]' ? 'ACME-NAME' : $payload;

	$html = \Nino\Form::render( $appData, $form, [ 'x' => 'v' ], '/templates/mail-probe' );
	check( 'the label of a field that holds '. $what. ' is the text it is in the mail, its fills put in', $read( $html ) === false && str_starts_with( $html, '<table><tr><th>'. $shown. '</th><td>v</td></tr></table>' ) );

	$mails->exchangeArray( [] ); ninoWarnings();
	\Nino\Form::send( $appData, $form, [ 'x' => 'v' ] );
	// A subject with a null byte or of a megabyte is one the mail refuses to send at all
	check( '...and so is the subject, with the name of the site put in', count( $mails ) === 0 ? in_array( $what, [ 'a null byte', 'a megabyte' ], true ) : ( count( $mails ) === 1 && $read( (string) $mails[0]['subject'] ) === false && $mails[0]['subject'] === $shown. ' von ACME-NAME' ) );
}

$callbacks = new \ArrayObject();
\Nino\Callbacks::registerCallback( $appData, '/nino/html/render', static function( array &$appData, string $html ) use ( $callbacks ): ?string {
	$callbacks->append( $html );
	return null;
} );
\Nino\Form::render( $appData, [ 'name' => 'Probe', 'fields' => [ [ 'name' => 'x', 'label' => 'L', 'type' => 'text', 'required' => false, 'options' => [] ] ] ], [], '/templates/mail-probe' );
check( 'a label, a subject and an address are no page: the callbacks of /nino/html/render are called for the template alone', count( $callbacks ) === 1 );
$subject = \Nino\Html::putFills( $appData, 'Anfrage [web] von [[/project/company/general/name]]', [] );
check( 'a bracket that opens no call in a subject is the text it is', $subject === 'Anfrage [web] von ACME-NAME' );

echo "\nWhat a shortcode answers is finished\n";

\Nino\Html::addShortcode( $appData, 'safety-answer', static function( array &$appData, array $args ): string {
	return 'x [csrf] [[/project/company/general/name]] [template /templates/x] y';
}, false );
$html = $render( '[safety-answer]' );
check( 'a shortcode that answers a call, a fill and a csrf field is the text of them: nobody reads its answer', $html === 'x [csrf] [[/project/company/general/name]] [template /templates/x] y' );

\Nino\Html::addShortcode( $appData, 'safety-hold', static function( array &$appData, array $args ): string {
	return (string) ( $args['content'] ?? '' );
}, true );
$html = $render( '[safety-hold]a [csrf] b [[/project/company/general/name]][/safety-hold]' );
check( 'a container that answers what it holds answers the text it was written in: the calls in it are not drawn', $read( $html ) === false && $html === 'a [csrf] b [[/project/company/general/name]]' );

\Nino\Html::addFills( $appData, [ '/template/safety/json/value' => 'Wert mit [csrf] und [template /templates/x]' ], '*' );
$html = $render( '<script>[json /template/safety/json/value]</script>' );
check( '[json] answers the value as a string of json and nothing of it is a call: a value with a csrf field in it is no field', $read( $html ) === false && $html === '<script>"Wert mit [csrf] und [template \/templates\/x]"</script>' );

echo "\nA fill in the source of a call is a value of it\n";

\Nino\Html::addFills( $appData, [ '/template/safety/break/out' => '/templates/x] [csrf] [template /templates/x' ], '*' );
$html = $render( '[template /templates/[[/template/safety/break/out]]]' );
check( 'a fill that tries to break out of the source of [template] is the name of a file, and none is none', $html === '' );

ninoWarnings();
$html = $render( str_repeat( '[x]', 60000 ) );
check( 'a page with more brackets than the reader reads is the text it is, and the log says so', $html === str_repeat( '[x]', 60000 ) && count( ninoWarnings() ) === 1 );

\Nino\Html::addFills( $appData, [ '/template/safety/break/attribute' => 'x" [csrf] y="' ], '*' );
$html = $render( '[button text="[[/template/safety/break/attribute]]" href="#"]' );
check( 'a fill that tries to break out of the value of an attribute is that value', $read( $html ) === false && $html === '<a class="nino-btn" href="#">'. $escaped( 'x" [csrf] y="' ). '</a>' );

echo "\nWhat is too deep, too much or too large ends\n";

\Nino\Filesystem::putFileContent( $appData, '/templates/again.tpl', 'a[template /templates/again]' );
ninoWarnings();
$html = $render( '[template /templates/again]' );
$warnings = ninoWarnings();
check( 'a template that includes itself ends: it is drawn as often as the kernel lets sources stand inside one another', $html === str_repeat( 'a', 20 ) );
check( '...and the log says so once, whatever the page does after it', count( $warnings ) === 1 && str_contains( $warnings[0], 'deep' ) === true );
check( '...and the next page of the request draws as it always does', $render( '[template /templates/x]' ) === 'TEMPLATE-X-RAN' );

\Nino\Filesystem::putFileContent( $appData, '/templates/two-a.tpl', '1[template /templates/two-b]' );
\Nino\Filesystem::putFileContent( $appData, '/templates/two-b.tpl', '2[template /templates/two-a]' );
$html = $render( '[template /templates/two-a]' );
check( 'two templates that include each other end the same way', strlen( $html ) === 20 && str_starts_with( $html, '1212' ) === true );
ninoWarnings();

for( $level = 1; $level <= 17; $level++ )
	\Nino\Filesystem::putFileContent( $appData, '/templates/level'. $level. '.tpl', $level === 17 ? 'bottom' : '<'. $level. '>[template /templates/level'. ( $level + 1 ). ']' );
$html = $render( '[template /templates/level1]' );
check( 'seventeen templates inside one another are drawn down to the last, and nothing is said', str_starts_with( $html, '<1><2>' ) === true && str_ends_with( $html, '<16>bottom' ) === true && ninoWarnings() === [] );

\Nino\Html::addShortcode( $appData, 'safety-box', static function( array &$appData, array $args ): string {
	return '('. \Nino\Html::renderSource( $appData, (string) ( $args['content'] ?? '' ), \Nino\Html::context( $appData ) ). ')';
}, true );
$html = $render( str_repeat( '[safety-box]', 17 ). 'core'. str_repeat( '[/safety-box]', 17 ) );
check( 'seventeen containers inside one another end: the reader opens sixteen and the seventeenth is a leaf, whatever it would have held stands beside it', $html === str_repeat( '(', 16 ). '()core'. str_repeat( ')', 16 ) );

$html = $render( str_repeat( '[safety-box]', 40 ). 'core'. str_repeat( '[/safety-box]', 40 ) );
check( 'forty containers end the same way: every one past the sixteenth is a leaf', $html === str_repeat( '(', 16 ). str_repeat( '()', 24 ). 'core'. str_repeat( ')', 16 ) );
ninoWarnings();

\Nino\Filesystem::putFileContent( $appData, '/templates/large.tpl', str_repeat( 'a', ( 1 << 20 ) + 1 ) );
$html = $render( '[template /templates/large]' );
$warnings = ninoWarnings();
check( 'a template of more than a megabyte is not drawn, and the log says which', $html === '' && count( $warnings ) === 1 && str_contains( $warnings[0], 'large' ) === true );

\Nino\Filesystem::putFileContent( $appData, '/templates/limit.tpl', str_repeat( 'b', 1 << 20 ) );
$html = $render( '[template /templates/limit]' );
check( '...and one of exactly a megabyte is', strlen( $html ) === ( 1 << 20 ) && ninoWarnings() === [] );

$started = microtime( true );
$html = $render( str_repeat( '[safety-answer]', 20000 ) );
$seconds = microtime( true ) - $started;
echo "      20000 calls: ". sprintf( '%.2f', $seconds ). " s\n";
check( 'twenty thousand calls in one page are drawn, in a time that is no attack', strlen( $html ) === 20000 * strlen( 'x [csrf] [[/project/company/general/name]] [template /templates/x] y' ) && $seconds < 10 );

$ignored = ninoWarnings();

// An attribute named content is a value: the content of a call is what stands between its tags and nothing else
\Nino\Html::addFills( $appData, [ '/safety/attribute/content/value' => '<p>[csrf] [template /templates/safety-secret]</p>' ], '*' );
$seen = new \ArrayObject();
\Nino\Html::addShortcode( $appData, 'safety-draws', static function( array &$appData, array $args ) use ( $seen ): string {
	$seen[] = $args;
	return '<i>'. \Nino\Html::renderSource( $appData, (string) ( $args['content'] ?? '' ), \Nino\Html::context( $appData ) ). '</i>';
} );

$ignored = ninoWarnings();
$html = \Nino\Html::renderHtml( $appData, '[safety-draws content="[csrf]" /]|[safety-draws content="[[/safety/attribute/content/value]]"][/safety-draws]|[safety-draws content="[csrf]"]kept[/safety-draws]' );
check( 'an attribute named content never reaches a callback as its content: written out or through a fill, on a leaf and on an empty container, it is read by nobody, and the content between the tags is the content', $html === '<i></i>|<i></i>|<i>kept</i>'
	&& array_key_exists( 'content', $seen[0] ) === false && array_key_exists( 'content', $seen[1] ) === false && $seen[2]['content'] === 'kept' );

$html = '';

foreach( [ '[html content="<p>[csrf]</p>"][/html]', '[html content="[[/safety/attribute/content/value]]"][/html]', '[card content="<p>[csrf]</p>" /]', '[card content="[[/safety/attribute/content/value]]"][/card]', '[navigation id="safety-nav" content="[[/safety/attribute/content/value]]"]', '[image /safety/slot content="<b>[csrf]</b>"]' ] as $call )
	$html .= \Nino\Html::renderHtml( $appData, $call );

check( '...and the components that draw their content draw none of it: no token and no template, and a card with nothing between its tags has no content and draws nothing', str_contains( $html, '_csrf' ) === false && str_contains( $html, 'type="hidden"' ) === false
	&& \Nino\Html::renderHtml( $appData, '[card content="<p>x</p>" /]' ) === '' && \Nino\Html::renderHtml( $appData, '[card content="<p>x</p>"][/card]' ) === '' );

// The tree of a content is answered from the nodes of the call that is being drawn, and from nobody else's
$trees = new \ArrayObject();
\Nino\Html::addShortcode( $appData, 'safety-tree', static function( array &$appData, array $args ) use ( $trees ): string {
	$inner = \Nino\Html::renderSource( $appData, '[safety-draws]x[/safety-draws]', \Nino\Html::context( $appData ) );
	$trees[] = \Nino\Html::tree( $appData, (string) ( $args['content'] ?? '' ), false );
	return $inner;
} );
$html = \Nino\Html::renderHtml( $appData, '[safety-tree]a[safety-draws]b[/safety-draws][/safety-tree]' );
check( 'a call drawn inside a callback leaves the nodes of the outer call in place: the tree of its content is its own', $html === '<i>x</i>' && count( $trees[0] ) === 2 && $trees[0][0] === [ 't' => 'a' ] && $trees[0][1]['n'] === 'safety-draws' && $trees[0][1]['c'] === [ [ 't' => 'b' ] ] );

// The template of a mail is a name and nothing else, whoever calls
$form = [ 'name' => 'safety', 'fields' => [] ];
check( 'Form::render() draws nothing for a path that is more than the name of a template', \Nino\Form::render( $appData, $form, [], '/templates/safety-secret] [csrf] [template /templates/safety-secret' ) === '' && \Nino\Form::render( $appData, $form, [], '/templates/x y' ) === '' );

ninoDone( $appData );
