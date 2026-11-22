<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	markup-render-smoke.php	Test of how a page is read once and drawn from the tree: what \Nino\Html::renderHtml()
 *											and renderSource() do with a source - the names that are containers, leaves and both, what
 *											a call of a shortcode is handed, the context a shortcode is called in, a name nobody
 *											registered, an apostrophe, a container inside a container of its name -, the loops
 *											of the elements with their tokens, the menu with its lines and its counter, the picker of
 *											the languages, the template of a panel of the workbench and the answers of the shortcodes
 *											of the kernel that used to be read again. The pages themselves are compared byte for
 *											byte by tests/markup-pages-smoke.php.
 *											Runs against an isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/markup-render-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'markup-render' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

foreach( [ 'private/templates', 'private/text', 'private/elements', 'public/images' ] as $directory )
	mkdir( $sandbox. '/'. $directory, 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

\Nino\Csrf::init( $appData );
\Nino\Modules\Csrf::init( $appData );
\Nino\Html::init( $appData );
\Nino\Modules\Template::init( $appData );
\Nino\Modules\Elements::init( $appData );
\Nino\Modules\Navigation::init( $appData );
\Nino\Modules\Localepicker::init( $appData );
\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', str_repeat( 'c', 64 ) );

\Nino\Html::addFills( $appData, [ '/project/company/general/name' => 'ACME', '/template/render/fill/amp' => 'a &amp; b' ], '*' );

$render = static function( string $source ) use ( &$appData ): string {
	return \Nino\Html::renderHtml( $appData, $source );
};

// What a shortcode of the tests is handed and the context it stands in
$seen = new \ArrayObject();
$probe = static function( array &$appData, array $args ) use ( $seen ): string {

	$seen->append( [ $args, \Nino\Html::context( $appData ) ] );

	return '<'. ( $args['content'] ?? '' ). '>';
};
\Nino\Html::addShortcode( $appData, 'probe', $probe, null );
\Nino\Html::addShortcode( $appData, 'box', static function( array &$appData, array $args ): string {
	return '['. \Nino\Html::renderSource( $appData, (string) ( $args['content'] ?? '' ), \Nino\Html::context( $appData ) ). ']';
}, true );
\Nino\Html::addShortcode( $appData, 'leaf', static fn( array &$appData, array $args ): string => 'leaf', false );

echo "The names that have an end\n";

check( 'a container that is closed holds what stands in it, and one of its own name inside it is a container of its own: the inner one does not close the outer', $render( '[box]a[box]b[/box]c[/box]' ) === '[a[b]c]' );
check( 'a container with no end is closed where the page ends', $render( 'x [box]a [box]b' ) === 'x [a [b]]' );
check( 'a leaf has no end, and one that is written is no part of the page', $render( 'a [leaf] b [/leaf] c' ) === 'a leaf b  c' );
check( 'a name that is both is a leaf where the document has no end of it and a container where it has - and [name /] is a leaf whatever the document says', $render( '[probe]' ) === '<>' && $render( '[probe]x[/probe]' ) === '<x>' && $render( '[probe /][probe]x[/probe]' ) === '<><x>' );
check( 'the answer of a container is finished: the calls it was written around are the ones it draws or none', $render( '[box][leaf][/box]' ) === '[leaf]' );

\Nino\Html::addShortcode( $appData, 'late', static fn( array &$appData, array $args ): string => 'late', false );
check( 'a shortcode that is registered after the first page is read by the next one with its flag', $render( '[late]' ) === 'late' );

echo "\nWhat a call is handed\n";

$seen->exchangeArray( [] );
$render( '[probe /a/b flag name="x y" empty="" other /][probe /c/d]inner [[/project/company/general/name]][/probe]' );
check( 'the source is entry 0, a switch the next entry, a value under its name, an empty value is a value, and the content is the text it was written in', $seen[0][0] === [ '/a/b', 'flag', 'name' => 'x y', 'empty' => '', 2 => 'other' ] );
check( '...with no key content where it holds nothing, and a content that keeps its fills as fills', array_key_exists( 'content', $seen[0][0] ) === false && $seen[1][0]['content'] === 'inner [[/project/company/general/name]]' );

$seen->exchangeArray( [] );
$render( '[probe /x a="[[/project/company/general/name]]" b="[[/template/render/fill/amp]]" c="[[/nothing/is/here]]" d="[[/project/company/general/[[/nothing/is/here]]]]"]' );
check( 'the fills of the site in the source and in a value are put in before the call is made, as they are: a fill nobody has stays written', $seen[0][0]['a'] === 'ACME' && $seen[0][0]['b'] === 'a &amp; b' && $seen[0][0]['c'] === '[[/nothing/is/here]]' );

$seen->exchangeArray( [] );
$render( '[probe /a]' );
check( 'a call is told where it stands: the context of the page has no template, no scope, the place page, no root and depth 0, no element, no tokens', $seen[0][1] === [ 'template' => '', 'scope' => '', 'place' => 'page', 'root' => '', 'element' => null, 'fills' => [], 'depth' => 0 ] );
check( '...and outside a call there is the context of nothing', \Nino\Html::context( $appData ) === [ 'template' => '', 'scope' => '', 'place' => 'page', 'root' => '', 'element' => null, 'fills' => [], 'depth' => 0 ] );

\Nino\Filesystem::putFileContent( $appData, '/templates/page-ctx.tpl', '[probe /ctx]' );
\Nino\Filesystem::putFileContent( $appData, '/templates/plain.name.tpl', '[probe /dot]' );
$seen->exchangeArray( [] );
$render( '[probe /outer][template /templates/page-ctx][template /templates/plain.name]' );
check( 'a template is drawn with its own name as the template of its context, and one whose name is no word of a key with none', $seen[1][1]['template'] === 'page-ctx' && $seen[2][1]['template'] === '' && $seen[0][1]['template'] === '' );

echo "\nA name nobody registered, and what the reader makes of the notation\n";

ninoWarnings();
$html = $render( 'before [nosuch x="1"]in [leaf][/nosuch] after [nosuch2 /] and [nosuch3 /] [nosuch3 /]' );
$notices = ninoWarnings();
check( 'a name nobody registered draws nothing, and nor does what it holds', $html === 'before  after  and  ' );
check( '...and the log is told once for a name', count( $notices ) === 3 && str_contains( $notices[0], '"nosuch"' ) === true && str_contains( $notices[1], 'nosuch2' ) === true && str_contains( $notices[2], 'nosuch3' ) === true );

$seen->exchangeArray( [] );
$render( '[probe text="it\'s" href="#" /][probe text=\'it\' href="#" /]' );
check( 'an apostrophe is the character it is, and no quote: the value of a call is what stands between the double quotes', $seen[0][0]['text'] === "it's" && $seen[0][0]['href'] === '#' );
check( '...a value in single quotes is no value: the attribute is not handed on and the call is made without it', array_key_exists( 'text', $seen[1][0] ) === false && $seen[1][0]['href'] === '#' );
check( '\\[ in front of a call is the text of the call, with the backslash gone', $render( '\\[leaf] [leaf]' ) === '[leaf] leaf' );
check( 'a call with a line break in its value is no call: it is the text it is', $render( "[leaf a=\"x\ny\"]" ) === "[leaf a=\"x\ny\"]" );
check( 'a fill whose key is none of a key is text, and one with a field name and no loop stays written', $render( '[[ ]] [[title]] [[/nothing/here/at/all]]' ) === '[[ ]] [[title]] [[/nothing/here/at/all]]' );

echo "\nOnce for a page\n";

$count = new \ArrayObject();
\Nino\Callbacks::registerCallback( $appData, '/nino/html/render', static function( array &$appData, string $html ) use ( $count ): ?string {
	$count->append( $html );
	return null;
} );
$count->exchangeArray( [] );
$render( '[template /templates/page-ctx] [template /templates/page-ctx][leaf][leaf][leaf]' );
check( 'the callbacks of /nino/html/render are called once for a page, whatever it holds, with the page', count( $count ) === 1 && str_contains( $count[0], 'leaf' ) === true );
$count->exchangeArray( [] );
\Nino\Html::renderSource( $appData, '[leaf]' );
check( '...and renderSource() calls none', count( $count ) === 0 );

$trees = static function() use ( &$appData ): int {
	return count( $appData['./nino/html/cache']['trees'] ?? [] );
};
\Nino\Html::renderSource( $appData, 'a [leaf] b' );
$before = $trees();
\Nino\Html::renderSource( $appData, 'a [leaf] b' );
check( 'a source is read once in a request: the second drawing of the same text adds no tree', $before >= 1 && $trees() === $before );
\Nino\Html::addShortcode( $appData, 'another', static fn( array &$appData, array $args ): string => '', false );
check( '...and a registration makes the reader be told again', $trees() === 0 );

echo "\nThe loops\n";

\Nino\Elements::insertElementType( $appData, '/loop', [ 'title' => [ 'type' => 'string' ], 'kind' => [ 'type' => 'string' ] ] );
foreach( [ [ 'a', 'One', 'x' ], [ 'b', 'Two', 'y' ], [ 'c', 'Three', 'x' ] ] as [ $name, $title, $kind ] )
	\Nino\Elements::insertElement( $appData, '/loop/'. $name, [ 'title' => $title, 'kind' => $kind ], '*' );

check( 'a loop draws its content once for each element, with the tokens of the round', $render( '[elements /loop sort="title"]<[[.id]]:[[title]]>[/elements]' ) === '<0:One><1:Three><2:Two>' );
check( 'a loop in a loop: the tokens of the inner one are its own where it names them, and the ones of the outer one are there for its arguments and what it does not name',
	$render( '[elements /loop sort="title"]<[[title]]>[elements /loop query="kind=[[kind]]" sort="title"](*[[title]]/[[kind]]*)[/elements][/elements]' ) === '<One>(*One/x*)(*Three/x*)<Three>(*One/x*)(*Three/x*)<Two>(*Two/y*)' );
\Nino\Elements::insertElementType( $appData, '/other', [ 'label' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/other/o1', [ 'label' => 'L' ], '*' );
check( 'the tokens of the outer loop stay visible in the inner one, where the inner one has no field of that name',
	$render( '[elements /loop sort="title"]<[[title]]>[elements /other]([[title]]|[[label]])[/elements][/elements]' ) === '<One>(One|L)<Three>(Three|L)<Two>(Two|L)' );
check( 'a token that is no field of the type and no token of a loop stays written, as an unknown fill does', $render( '[elements /loop limit="1"][[nope]][/elements]' ) === '[[nope]]' );
check( 'outside a loop a token is nothing of a loop', $render( '[[title]] [elements /loop limit="1"]([[title]])[/elements] [[title]]' ) === '[[title]] (One) [[title]]' );
check( 'a loop with no element is nothing, and a list that names a template that is none is told so', $render( '[elements /loop query="kind=none"]x[/elements]' ) === '' );
\Nino\Filesystem::putFileContent( $appData, '/templates/none-here.tpl', 'EMPTY [[/project/company/general/name]]' );
check( '...with empty= it is the template of that name, drawn', $render( '[elements /loop query="kind=none" empty="none-here"]x[/elements]' ) === 'EMPTY ACME' );
ninoWarnings();
check( '...and a name that climbs out of /templates is told in the log and draws nothing', $render( '[elements /loop query="kind=none" empty="../x"]x[/elements]' ) === '' && count( ninoWarnings() ) === 1 );
check( 'elementvalues loops the values with their tokens', $render( '[elementvalues /loop key="kind" sort="value"]<[[.id]]:[[.value]]:[[.count]]>[/elementvalues]' ) === '<0:x:2><1:y:1>' );
check( 'a template in a loop sees the tokens of the round', ( static function() use ( &$appData, $render ): bool {
	\Nino\Filesystem::putFileContent( $appData, '/templates/row.tpl', '{[[title]]}' );
	return $render( '[elements /loop sort="title" limit="2"][template /templates/row][/elements]' ) === '{One}{Three}';
} )() );

echo "\nThe shortcodes of the kernel\n";

\Nino\Filesystem::putFileContent( $appData, '/templates/menu.tpl', 'unused' );
$html = $render( '[navigation id="m"]/one:One:class="x"'. "\n". '[leaf] and [leaf]'. "\n". '/two:[[/project/company/general/name]]'. "\n". '[/navigation]' );
check( 'a menu draws its lines by hand: the call in a line, the fill in a title, the attributes as they are written', $html === '<div>leaf and leaf</div><ul id="m"><li><a href="/one"class="x">One</a></li><li><a href="/two">ACME</a></li></ul>' );
$html = $render( '[navigation burger][/navigation]x[navigation][leaf][/navigation][navigation burger id="own"]/a:A[/navigation]' );
check( 'a menu without an entry draws no list, and the word burger is no switch: every menu is a bare list, which carries no label of a key and no class of its own',
	$html === 'x<div>leaf</div><ul id="own"><li><a href="/a">A</a></li></ul>' );

$_SERVER['REQUEST_URI'] = '/';
\Nino\Html::addFills( $appData, [ '/module/localepicker/menu/title' => 'Sprache [csrf]', '/_nino/locale/de_DE/name' => 'Deutsch [template /templates/menu]', '/_nino/locale/en_US/name' => 'English' ], '*' );
$html = $render( '[localepicker]' );
check( 'the picker of the languages has the words of its keys as they are - a call or a csrf field in a name is text - and no key of the fragment is left',
	str_contains( $html, 'Sprache [csrf]' ) === true && str_contains( $html, 'Deutsch [template /templates/menu]' ) === true && str_contains( $html, 'name="_csrf"' ) === false && str_contains( $html, '[[/' ) === false );


echo "\nThe shortcodes of the modules are registered with what they are\n";

$flags = [];
foreach( \Nino\Html::shortcodes( $appData ) as $name )
	$flags[$name] = $appData['./nino/html/shortcodes'][$name];
$pick = static fn( array $names ): array => array_map( static fn( string $name ): mixed => array_key_exists( $name, $flags ) === true ? $flags[$name] : 'not registered', array_combine( $names, $names ) );
check( 'the kernel says what each of its shortcodes is: a leaf, a container, or both',
	array_values( array_unique( $pick( [ 'template', 'csrf', 'json', 'localepicker' ] ), SORT_REGULAR ) ) === [ false ]
	&& array_values( array_unique( $pick( [ 'element', 'elements', 'elementvalues', 'imagevalues' ] ), SORT_REGULAR ) ) === [ true ]
	&& array_values( array_unique( $pick( [ 'navigation' ] ), SORT_REGULAR ) ) === [ null ] );

check( 'a feature that registers a shortcode says what it is', ( static function() use ( &$appData ): bool {
	\Nino\Feature::shortcode( $appData, 'fx-leaf', static fn( array &$appData, array $args ): string => 'L', false );
	\Nino\Feature::shortcode( $appData, 'fx-box', static fn( array &$appData, array $args ): string => 'B', true );
	return $appData['./nino/html/shortcodes']['fx-leaf'] === false && $appData['./nino/html/shortcodes']['fx-box'] === true;
} )() );

echo "\nWhat the fix round pins\n";

\Nino\Html::addFills( $appData, [ '/template/render/key/part' => 'name', '/nino/http/response/uri' => '/contact', '/_nino/webpage/contact/title' => 'Kontakt', '/template/rv/x/html' => '<b onclick="x">b</b><script>a()</script>' ], '*' );
$seen->exchangeArray( [] );
$render( '[probe /x d="[[/project/company/general/[[/template/render/key/part]]]]" /]' );
check( 'a fill with a fill in its name is put into the value of a call: the inner one first', $seen[0][0]['d'] === 'ACME' );

\Nino\Html::addShortcode( $appData, 'ctxafter', static function( array &$appData, array $args ): string {
	$before = \Nino\Html::context( $appData );
	\Nino\Html::renderSource( $appData, (string) ( $args['content'] ?? '' ), $before );
	return $before === \Nino\Html::context( $appData ) ? 'same' : 'changed';
}, true );
check( 'the context of a call is the one it was called in after what it drew has ended: a call inside a call restores the one of the outer', $render( '[elements /loop limit="1"][ctxafter][ctxafter]x[/ctxafter][/ctxafter][/elements]' ) === 'same' );

\Nino\Html::addShortcode( $appData, 'addfill', static function( array &$appData, array $args ): string {
	\Nino\Html::addFills( $appData, [ '/template/render/added/x' => 'v' ], '*' );
	return '';
}, false );
check( 'a fill a shortcode adds is read by the nodes that follow it, though the fills were read before', $render( '[[/project/company/general/name]][addfill][[/template/render/added/x]]' ) === 'ACMEv' );

$parts = \Nino\Html::shortcodeParts( $appData, 'x [probe]a[/probe] [probe /] y' );
check( 'the parts of a text hold a container with its content and its end, and a leaf of a name that is a container elsewhere as [name /]', array_column( $parts, 'name' ) === [ '', 'probe', '', 'probe', '' ] && $parts[1]['text'] === '[probe]a[/probe]' && $parts[3]['text'] === '[probe /]' );

check( 'a loop in a loop reads the tokens of its own element for what it names: the inner ones win', $render( '[elements /loop sort="title"][elements /loop query="kind=[[kind]]" sort="title"]<u>[[title]]</u>[/elements][/elements]' ) === '<u>One</u><u>Three</u><u>One</u><u>Three</u><u>Two</u>' );

$seen->exchangeArray( [] );
$render( '[elements /loop sort="title" limit="1"][probe text="[[/_nino/webpage[[/nino/http/response/uri]]/title]] / [[title]]" /][/elements]' );
check( 'a call in a loop with a fill in the name of a fill puts it in, and the token of the element too', $seen[0][0]['text'] === 'Kontakt / One' );

$trees = static function() use ( &$appData ): int {
	return count( $appData['./nino/html/cache']['trees'] ?? [] );
};
\Nino\Html::renderSource( $appData, 'x [leaf]' );
for( $i = 0; $i < 400; $i++ )
	\Nino\Html::renderSource( $appData, 'text '. $i. ' [leaf]' );
check( 'the request keeps a number of trees and no more', $trees() === \Nino\Html::MAX_TREES );
check( '...and a text past that is drawn all the same, and read each time', \Nino\Html::renderSource( $appData, 'one more [leaf]' ) === 'one more leaf' && $trees() === \Nino\Html::MAX_TREES );
unset( $appData['./nino/html/cache'] );
\Nino\Html::renderSource( $appData, 'kept [leaf]' );
$before = $trees();
check( 'a text that is not to be kept is not kept', \Nino\Html::renderSource( $appData, 'not kept [leaf]', [], false ) === 'not kept leaf' && $trees() === $before );

\Nino\Html::addShortcode( $appData, 'treeprobe', static function( array &$appData, array $args ) use ( $seen ): string {
	$seen->append( [ \Nino\Html::tree( $appData, (string) ( $args['content'] ?? '' ) ), \Nino\Html::tree( $appData, 'b [leaf] c', false ) ] );
	return '';
}, true );
$seen->exchangeArray( [] );
$render( '[treeprobe]a [leaf x="1"] [[/project/company/general/name]][/treeprobe]' );
check( 'tree() answers the nodes of a source as the reader reads them: text, a call with its source, attributes and children, a fill', array_map( static fn( array $node ): array => array_diff_key( $node, [ 'o' => 1 ] ), $seen[0][0] ) === [ [ 't' => 'a ' ], [ 'n' => 'leaf', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null ], [ 't' => ' ' ], [ 'f' => '/project/company/general/name' ] ] && \Nino\Html::tree( $appData, '' ) === [] );
$before = count( $appData['./nino/html/cache']['trees'] ?? [] );
\Nino\Html::tree( $appData, 'tree kept [leaf]' );
\Nino\Html::tree( $appData, 'tree not kept [leaf]', false );
check( '...for the content a call was just handed it answers the nodes the call was made of, not a second reading of its text: they keep the offsets of the page', $seen[0][0][1]['o'] === strpos( '[treeprobe]a [leaf x="1"] [[/project/company/general/name]][/treeprobe]', '[leaf' ) );
check( '...keeps the tree of a text unless it is told not to, and keeps no more than the request keeps', count( $appData['./nino/html/cache']['trees'] ) === $before + 1 );
for( $i = 0; $i < 300; $i++ )
	\Nino\Html::tree( $appData, 'many '. $i. ' [leaf]' );
check( '...and the limit is the one of drawing', count( $appData['./nino/html/cache']['trees'] ) === \Nino\Html::MAX_TREES );

echo "\nThe template of a panel of the workbench is drawn where the panes are made\n";

class MarkupRenderPanel {
	public static function actions(): array { return [ 'mrender/list' => [ self::class, 'apiList' ] ]; }
	public static function nav(): array { return [ 'mrender', 'Render', 62 ]; }
	public static function template(): string { return '/templates/mrender-pane'; }
	public static function apiList( array &$appData, array &$request ): void { \Nino\Http::ok( $request, [] ); }
}
class MarkupRenderModule {
	public static function adminPanels( array &$appData ): array { return [ 'MarkupRenderPanel' ]; }
}
\Nino\Filesystem::putFileContent( $appData, '/templates/mrender-pane.tpl', '<p>PANE [leaf] [[/project/company/general/name]]</p>' );
$withPanel = $appData;
$withPanel['/nino/modules'] = [ 'MarkupRenderModule' ];
unset( $withPanel['./_admin/panels'] );
$panes = \Nino\Admin\Admin::panesHtml( $withPanel );
check( 'the template of a panel is in its pane, drawn, and no call of it is left for anybody to read', str_contains( $panes, '<p>PANE leaf ACME</p>' ) === true && str_contains( $panes, '[template' ) === false );
check( '...and the pane that is the value of a fill is put into the page as it is', ( static function() use ( &$withPanel ): bool {
	\Nino\Html::addFills( $withPanel, [ '/_admin/panes' => \Nino\Admin\Admin::panesHtml( $withPanel ) ], '*' );
	return str_contains( \Nino\Html::renderHtml( $withPanel, '<main>[[/_admin/panes]]</main>' ), '<p>PANE leaf ACME</p>' ) === true;
} )() );

$ignored = ninoWarnings();

// The name of a field may hold an underscore, a hyphen and a capital, and a loop has a token for each
\Nino\Elements::insertElementType( $appData, '/render-fields', [ 'title' => [ 'type' => 'string' ], 'long_name' => [ 'type' => 'string' ], 'long-name' => [ 'type' => 'string' ], 'Camel' => [ 'type' => 'string' ] ] );
\Nino\Elements::insertElement( $appData, '/render-fields/a', [ 'title' => 'plain', 'long_name' => 'underscored', 'long-name' => 'hyphenated', 'Camel' => 'capital' ], 'de_DE' );
$html = \Nino\Html::renderHtml( $appData, '[elements /render-fields][[title]] [[long_name]] [[long-name]] [[Camel]][/elements]' );
check( 'a field named with an underscore, a hyphen or a capital is a token of the element in a loop', $html === 'plain underscored hyphenated capital' );

ninoDone( $appData );
