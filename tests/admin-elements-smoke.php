<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-elements-smoke.php	Dependency-free smoke test for the element panels of the workbench:
 *											the element-type editor - apiSave must not clobber a type's content -,
 *											the content of an element, the Translations round trip of Text and
 *											Elements, and the activity log those writes go to. Runs against an
 *											isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/admin-elements-smoke.php
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

$appData = ninoSandbox( 'admin-elements' );
$sandbox = ninoSandboxDir( $appData );
mkdir( $sandbox. '/private/elements', 0777, true );

// A hand-authored-shaped type file (top-level 'title' key, real locale content) -
// apiSave must only ever touch 'title'/'model', never this real content
file_put_contents( $sandbox. '/private/elements/testtype.php', '<?php return [
	\'title\'	=> \'Test Type\',
	\'model\'	=> [ \'name\' => [ \'type\' => \'string\', \'locale\' => true ] ],
	\'*\'			=> [ \'*\' => [] ],
	\'de_DE\'	=> [ \'item1\' => [ \'name\' => \'Hallo\' ] ],
];' );

// A set-up project with one developer account, logged in - the shell serves
// the workbench, every panel action runs against this session
ninoUser( $appData, 'dev@example.com' );

echo "Sandbox: $sandbox\n\n";


// --- ElementTypes::apiList / apiGet / apiSave / apiCreate -----------

echo "ElementTypes::apiList / apiGet / apiSave / apiCreate\n";

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiList' ] );
$types = $body['types'] ?? [];
check( 'apiList finds the sandbox type', count( $types ) === 1 && $types[0]['uri'] === 'testtype' );
check( 'apiList reports the real title', $types[0]['title'] === 'Test Type' );
check( 'apiList reports the real field count', $types[0]['fieldCount'] === 1 );
check( 'apiList exposes the allowed field types', in_array( 'image', $body['fieldTypes'] ?? [], true ) === true );
check( '...and they are the kernel\'s own list, stated once', defined( '\\Nino\\Elements::FIELD_TYPES' ) === true && \Nino\Modules\Elements\Types::FIELD_TYPES === \Nino\Elements::FIELD_TYPES && ( $body['fieldTypes'] ?? null ) === \Nino\Elements::FIELD_TYPES );
// The one place the rule lives: the editor offers a unit for the types the
// server names, and the server keeps a unit for those alone
$suffixTypes = $body['suffixTypes'] ?? null;
check( 'apiList names the types a unit applies to: every field type that renders an input a unit can sit next to', $suffixTypes === array_values( array_diff( \Nino\Modules\Elements\Types::FIELD_TYPES, [ 'boolean', 'image', 'element' ] ) ) );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiGet' ], [ 'uri' => 'testtype' ] );
$got = $body ?? [];
check( 'apiGet returns the real title', $got['title'] === 'Test Type' );
check( 'apiGet returns the real model', ( $got['model']['name']['type'] ?? null ) === 'string' );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiGet' ], [ 'uri' => 'not-a-real-type' ] );
check( 'apiGet 404s for an unknown type', $status === 404 );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiGet' ], [ 'uri' => '../escape' ] );
check( 'apiGet rejects a non-slug type uri before ever touching the filesystem', $status === 400 );

// Save: rename the field, add an image field and a suffixed/maxlength'd one,
// and slip in a field with an invalid type - the invalid one must be
// silently dropped, not crash or persist
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80, 'inputsize' => 8 ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'suffix' => 'ignored on image', 'required' => true ],
		'price' 	=> [ 'type' => 'double', 'suffix' => '€', 'inputsize' => 5 ],
		'active' 	=> [ 'type' => 'boolean', 'suffix' => 'ignored on boolean' ],
		'ref' 		=> [ 'type' => 'element', 'elementType' => 'testtype', 'suffix' => 'ignored on element' ],
		'bogus' 	=> [ 'type' => 'not-a-real-type' ],
	],
] );
check( 'apiSave succeeds', $status === 200 );

$afterSave = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'apiSave updates the title', $afterSave['title'] === 'Test Type Renamed' );
check( 'apiSave keeps the valid field', ( $afterSave['model']['name']['required'] ?? null ) === true );
check( 'apiSave sets a string field\'s maxlength', ( $afterSave['model']['name']['maxlength'] ?? null ) === 80 );
// The rows a field's input opens with belong to a string field, plain or
// rich text - the only ones rendered as a text area
check( 'apiSave sets a string field\'s inputsize', ( $afterSave['model']['name']['inputsize'] ?? null ) === 8 );
check( 'apiSave drops inputsize on a non-string field', isset( $afterSave['model']['price']['inputsize'] ) === false );
check( 'apiSave adds the new field', ( $afterSave['model']['photo']['width'] ?? null ) === 40 );
check( 'apiSave drops suffix on an image field', isset( $afterSave['model']['photo']['suffix'] ) === false );
// An image's file is uploaded only after the element exists, so a required
// image would make the type impossible to add an element to - the flag is
// dropped whatever was posted, and only for image fields
check( 'apiSave drops "required" on an image field', isset( $afterSave['model']['photo']['required'] ) === false );
check( 'apiSave sets a suffix on a non-boolean/image field', $afterSave['model']['price']['suffix'] === '€' );
check( 'apiSave drops suffix on a boolean field', isset( $afterSave['model']['active']['suffix'] ) === false );
check( 'apiSave drops suffix on an element field, which renders a select and no input', ( $afterSave['model']['ref']['elementType'] ?? null ) === 'testtype' && isset( $afterSave['model']['ref']['suffix'] ) === false );
check( 'apiSave silently drops a field with an unknown type', isset( $afterSave['model']['bogus'] ) === false );
check( 'apiSave never touches the "*" bucket', $afterSave['*'] === [ '*' => [] ] );
check( 'apiSave never touches real locale content', ( $afterSave['de_DE']['item1']['name'] ?? null ) === 'Hallo' );

// The order fields are posted in is the order they are written in, and the
// order every element form then renders them in - that is exactly what the
// editor's new ↑/↓ buttons change (see assets/elementtypes.js's _move())
check( 'apiSave keeps the posted field order', array_keys( $afterSave['model'] ) === [ 'name', 'photo', 'price', 'active', 'ref' ] );

ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [
		'price' 	=> [ 'type' => 'double' ],
		'name' 		=> [ 'type' => 'string', 'locale' => true, 'required' => true, 'maxlength' => 80 ],
		'active' 	=> [ 'type' => 'boolean' ],
		'photo' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40 ],
	],
] );
$afterReorder = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'apiSave rewrites the model in a reordered post\'s own order', array_keys( $afterReorder['model'] ) === [ 'price', 'name', 'active', 'photo' ] );
check( 'a reordered field keeps its own settings', ( $afterReorder['model']['name']['maxlength'] ?? null ) === 80 && ( $afterReorder['model']['photo']['width'] ?? null ) === 40 );
check( 'reordering never touches real locale content', ( $afterReorder['de_DE']['item1']['name'] ?? null ) === 'Hallo' );

// Switching a field's locale flag must migrate its stored value(s), not just
// the model - otherwise a stale per-locale value keeps shadowing the new
// '*' value forever (_cacheElement() merges locale data over '*' data)
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [ 'name' => [ 'type' => 'string' ] ],
] );
check( 'apiSave succeeds when switching a field to global', $status === 200 );

$afterGlobalize = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'switching to global migrates the native locale\'s value into "*"', ( $afterGlobalize['*']['item1']['name'] ?? null ) === 'Hallo' );
check( 'switching to global removes the stale value from the locale bucket', isset( $afterGlobalize['de_DE']['item1']['name'] ) === false );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [
	'uri' 		=> 'testtype',
	'title' 	=> 'Test Type Renamed',
	'model' 	=> [ 'name' => [ 'type' => 'string', 'locale' => true ] ],
] );
check( 'apiSave succeeds when switching a field back to per-locale', $status === 200 );

$afterLocalize = \Nino\Filesystem::getFileContent( $appData, '/elements/testtype.php', false );
check( 'switching back to per-locale copies the value into every locale', $afterLocalize['de_DE']['item1']['name'] === 'Hallo' && $afterLocalize['en_US']['item1']['name'] === 'Hallo' );
check( 'switching back to per-locale removes the stale value from "*"', isset( $afterLocalize['*']['item1']['name'] ) === false );

$_POST['data'] = json_encode( [ 'uri' => 'testtype', 'title' => 'x', 'model' => [] ] );
foreach( [ '../escape', 'Has Uppercase', '1startswithdigit', '' ] as $badUri ) {
	[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => $badUri, 'title' => 'x', 'model' => [] ] );
	check( "apiSave rejects an invalid type uri ('$badUri')", $status === 400 );
}

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'brandnewtype', 'title' => 'Brand New', 'model' => [ 'headline' => [ 'type' => 'string' ] ] ] );
check( 'apiCreate succeeds', $status === 200 );

$created = \Nino\Filesystem::getFileContent( $appData, '/elements/brandnewtype.php', false );
check( 'apiCreate writes the title', $created['title'] === 'Brand New' );
check( 'apiCreate writes the model', ( $created['model']['headline']['type'] ?? null ) === 'string' );
check( 'apiCreate starts with an empty shell, no fabricated content', $created['*'] === [ '*' => [] ] );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'brandnewtype', 'title' => 'Brand New', 'model' => [ 'headline' => [ 'type' => 'string' ] ] ] );
check( 'apiCreate rejects an already-existing type', $status === 409 );

// --- element reference fields ---
//
// The type a reference may point at is what the element form builds its
// select from. A reference nobody can satisfy would render as an empty,
// permanently unusable control - and look exactly like a type that simply has
// no elements yet - so the save is refused instead of the field being dropped

[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => 'nonexistenttype' ],
] ] );
check( 'apiCreate refuses a reference to a type that does not exist', $status === 400 );
check( '...and names the field and the missing type, so it can be fixed', str_contains( (string) ( $body['error'] ?? '' ), 'author' )
	&& str_contains( (string) ( $body['error'] ?? '' ), 'nonexistenttype' ) );
check( '...and writes no half-valid type file', \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', '' ) === '' );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '' ],
] ] );
check( 'apiCreate refuses a reference with no type at all', $status === 400 );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'suffix' => 'ignored', 'options' => [ 'a', 'b' ], 'required' => true, 'locale' => true ],
] ] );
check( 'apiCreate accepts a reference to a real type', $status === 200 );

$refCreated = \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false );
check( 'the referenced type is persisted on the field', ( $refCreated['model']['author']['elementType'] ?? null ) === 'brandnewtype' );
check( 'a reference can be required and per-translation like any other field', ( $refCreated['model']['author']['required'] ?? null ) === true && ( $refCreated['model']['author']['locale'] ?? null ) === true );
check( 'a reference gets no suffix - it renders as a select, not an input', isset( $refCreated['model']['author']['suffix'] ) === false );
// Two selects fighting over one value: the reference list is the choice
check( 'a reference gets no fixed options list either', isset( $refCreated['model']['author']['options'] ) === false );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => 'gone-since' ],
] ] );
check( 'apiSave refuses the same dangling reference', $status === 400 );
check( '...leaving the stored model untouched', ( \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false )['model']['author']['elementType'] ?? null ) === 'brandnewtype' );

// The type editor asks "several elements?" and "how many?" as two controls;
// the model carries one int, and its mere presence is what makes the field
// multi-valued. So the fold has to happen server-side too - a hand-written or
// api-posted model goes through exactly the same rule
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype' ],
	'tags' 		=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'multiple' => true, 'multipleMax' => '3' ],
	'crew' 		=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'multiple' => true, 'multipleMax' => '' ],
] ] );
check( 'apiSave accepts an element reference marked as a list', $status === 200 );

$multiSaved = \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false );
check( 'the cap is folded onto the field as a plain int', ( $multiSaved['model']['tags']['multiple'] ?? null ) === 3 );
check( 'an unfilled cap becomes 0 - the list is wanted, the ceiling is not', ( $multiSaved['model']['crew']['multiple'] ?? null ) === 0 );
// Presence is the switch, so writing the key on a field nobody asked to be a
// list would silently turn every existing single reference into one
check( 'a reference nobody marked keeps no key at all', isset( $multiSaved['model']['author']['multiple'] ) === false );
check( '...which is exactly what the kernel reads it as',
	\Nino\Elements::isMultiElement( $multiSaved['model']['author'] ) === false
	&& \Nino\Elements::isMultiElement( $multiSaved['model']['tags'] ) === true );

// Only an element reference can be a list: the key on any other type would be
// a promise nothing enforces
ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype' ],
	'headline' => [ 'type' => 'string', 'multiple' => true, 'multipleMax' => '2' ],
] ] );
check( 'a cap on a field that is not a reference is dropped',
	isset( \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false )['model']['headline']['multiple'] ) === false );

/*	A hand-written type file may spell the referenced type with the leading
	slash the rest of the framework accepts: \Nino\Elements builds its
	reference prefix as '/'. trim( elementType, '/' ). '/', and
	Types::referencedBy() compares the same way, so '/brandnewtype' is a
	working model that the kernel reads and the site renders. The panel
	compared the raw value against the bare type uris on disk, so it refused
	every save of that type - including one that changed nothing but the
	title - and named the type it had just been given as unknown.	*/
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' 	=> [ 'type' => 'element', 'elementType' => '/brandnewtype' ],
	'editor' 	=> [ 'type' => 'element', 'elementType' => 'brandnewtype/' ],
] ] );
check( 'a reference spelled with the slashes the kernel accepts is accepted here too', $status === 200 );

$slashSaved = \Nino\Filesystem::getFileContent( $appData, '/elements/refholder.php', false );
check( '...and is stored in the one spelling everything downstream reads',
	( $slashSaved['model']['author']['elementType'] ?? null ) === 'brandnewtype'
	&& ( $slashSaved['model']['editor']['elementType'] ?? null ) === 'brandnewtype' );
check( '...so the type knows it is referenced, which is what stops it being deleted',
	\Nino\Modules\Elements\Types::referencedBy( $appData, 'brandnewtype' ) === [ 'refholder.author', 'refholder.editor' ] );

// A slash is not a type either: '/' trimmed to nothing is the same "no type
// to point at" an empty value is, and still refused
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '/' ],
] ] );
check( 'a reference of nothing but a slash is still no reference', $status === 400 );

// ...and the slashes do not smuggle an unknown type past the check
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'refholder', 'title' => 'Ref Holder', 'model' => [
	'author' => [ 'type' => 'element', 'elementType' => '/nonexistenttype/' ],
] ] );
check( 'an unknown type is unknown however it is spelled', $status === 400 );

check( '"element" is offered as a field type by apiList', in_array( 'element', \Nino\Modules\Elements\Types::FIELD_TYPES, true ) === true );

// An image field may name the string field that holds its alt text per
// language. The name is kept only where it can be satisfied: a field of the
// model other than the image itself, plain string, written per language
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'alttype', 'title' => 'Alt Type', 'model' => [
	'photo' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'caption' ],
	'caption' 	=> [ 'type' => 'string', 'locale' => true ],
	'globalpic' => [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'globalcap' ],
	'globalcap' => [ 'type' => 'string' ],
	'richpic' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'richcap' ],
	'richcap' 	=> [ 'type' => 'string', 'locale' => true, 'html' => true ],
	'lostpic' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'nothere' ],
	'selfpic' 	=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'selfpic' ],
	'imgpic' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'photo' ],
	'note' 			=> [ 'type' => 'string', 'locale' => true, 'alt' => 'caption' ],
	'blank' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => '  ' ],
] ] );
$altModel = \Nino\Filesystem::getFileContent( $appData, '/elements/alttype.php', false )['model'] ?? [];
check( 'an image field keeps its alt link to a plain string field written per language', $status === 200 && ( $altModel['photo']['alt'] ?? null ) === 'caption' );
check( '...and loses it to a global field, a rich-text field, a field that is not there, itself, another image and nothing at all',
	array_filter( [ 'globalpic', 'richpic', 'lostpic', 'selfpic', 'imgpic', 'blank' ], static fn( string $key ): bool => array_key_exists( 'alt', $altModel[$key] ?? [ 'alt' => 'missing field' ] ) ) === [] );
check( '...and a field that is no image never carries one', array_key_exists( 'alt', $altModel['note'] ?? [ 'alt' => 1 ] ) === false && array_key_exists( 'alt', $altModel['caption'] ?? [ 'alt' => 1 ] ) === false );

// Saving the type again with its field renamed away loses the link: the model is checked as a whole each time
ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiSave' ], [ 'uri' => 'alttype', 'title' => 'Alt Type', 'model' => [
	'photo' 		=> [ 'type' => 'image', 'width' => 40, 'height' => 40, 'alt' => 'caption' ],
	'caption' 	=> [ 'type' => 'string' ],
] ] );
check( 'a link whose target stopped being a per-language field is dropped on the next save', array_key_exists( 'alt', \Nino\Filesystem::getFileContent( $appData, '/elements/alttype.php', false )['model']['photo'] ?? [ 'alt' => 1 ] ) === false );

echo "\n";


// --- ElementTypes::apiDelete ----------------------------------------------
//
// The one action in this module that destroys content, so every guard around
// it is worth a check of its own: the typed confirmation, the reference that
// refuses the deletion outright, and the images that go with the elements.
// Removing the file by hand has none of these - which is the argument for
// having the action at all, not against it.

echo "ElementTypes::apiDelete\n";

ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'deletable', 'title' => 'Deletable', 'model' => [
	'title' => [ 'type' => 'string' ],
	'photo' => [ 'type' => 'image' ],
] ] );

\Nino\Elements::insertElement( $appData, '/deletable/one', [ 'title' => 'One', 'photo' => 'elements/deletable/one.jpg' ], '*' );
\Nino\Elements::insertElement( $appData, '/deletable/two', [ 'title' => 'Two', 'photo' => '' ], '*' );

$deletableImage = \Nino\Filesystem::path( $appData, '/images/elements/deletable/one.jpg' );
@mkdir( dirname( $deletableImage ), 0777, true );
file_put_contents( $deletableImage, 'jpeg-bytes' );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiGet' ], [ 'uri' => 'deletable' ] );
// The form says what deleting costs before it is clicked, so the count has to
// travel with the type itself rather than being counted after the fact
check( 'apiGet reports how many elements the type holds', ( $body['elements'] ?? null ) === 2 );
check( '...and that nothing references it', ( $body['referencedBy'] ?? null ) === [] );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiDelete' ], [ 'uri' => 'deletable', 'confirm' => 'deletabel' ] );
check( 'apiDelete refuses a mistyped confirmation', $status === 400 );
check( '...and leaves the type file exactly where it was', \Nino\Filesystem::fileExists( $appData, '/elements/deletable.php' ) === true );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiDelete' ], [ 'uri' => 'no-such-type', 'confirm' => 'no-such-type' ] );
check( 'apiDelete 404s for a type that is not there', $status === 404 );

// refholder.author points at brandnewtype (created above), so deleting that
// type would leave the reference pointing at nothing - and no later save of
// refholder could tell the difference
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiDelete' ], [ 'uri' => 'brandnewtype', 'confirm' => 'brandnewtype' ] );
check( 'apiDelete refuses a type another type references', $status === 409 );
check( '...naming the field that holds the reference', str_contains( (string) ( $body['error'] ?? '' ), 'refholder.author' ) );
check( '...and the referenced type survives it', \Nino\Filesystem::fileExists( $appData, '/elements/brandnewtype.php' ) === true );

// The most destructive action in the module stands behind the same permission
// as the rest of the tab, and through the dispatcher rather than only when
// called directly - the ui never reaches apiDelete() any other way
check( 'types/delete is in the action map at all', isset( \Nino\Modules\Elements\Types::actions()['types/delete'] ) === true );

ninoUser( $appData, 'nodelete@example.com', [ '/_admin/text/manage' ] );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'uri' => 'deletable', 'confirm' => 'deletable' ], 'types/delete' );
check( 'an account without the tab\'s permission is refused the deletion', $status === 403 );
check( '...and the type is still there afterwards', \Nino\Filesystem::fileExists( $appData, '/elements/deletable.php' ) === true );
\Nino\Auth::deleteUser( $appData, 'nodelete@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiDelete' ], [ 'uri' => 'deletable', 'confirm' => 'deletable' ] );
check( 'a confirmed deletion goes through', $status === 200 );
// The count is what makes this line worth keeping, and log() - handed the posted
// body - never sees it: uri and confirm are all that is posted
check( '...and the activity log names how much went with the type', str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, 20 ) ), 'Delete Element Type /deletable with 2 element(s) and 1 image(s)' ) );
check( '...removing the type file', \Nino\Filesystem::fileExists( $appData, '/elements/deletable.php' ) === false );
check( '...and says what went with it', ( $body['elements'] ?? null ) === 2 && ( $body['images'] ?? null ) === 1 );
// An orphaned upload is not a safety net - nothing in the workbench can reach
// it again, it just sits in /images taking up room
check( '...taking the images the elements held with it', is_file( $deletableImage ) === false );
check( 'the type is gone from the list too', in_array( 'deletable', \Nino\Modules\Elements\Admin::types( $appData ), true ) === false );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiDelete' ], [ 'uri' => 'deletable', 'confirm' => 'deletable' ] );
check( 'deleting it a second time is a plain 404, not a crash', $status === 404 );

echo "\n";


// --- ElementTypes: blocks and breaks, rename ------------------------

echo "ElementTypes - paragraphs and line breaks, renaming a field\n";

// The two flags that go with html: paragraphs and lists for rich text, line
// breaks for plain text - each kept only where it can mean something
ninoCall( $appData, [ \Nino\Modules\Elements\Types::class, 'apiCreate' ], [ 'uri' => 'flagtype', 'title' => 'Flags', 'model' => [
	'rich'		=> [ 'type' => 'string', 'html' => true, 'blocks' => true, 'breaks' => true ],
	'inline'	=> [ 'type' => 'string', 'html' => true ],
	'plain'		=> [ 'type' => 'string', 'blocks' => true, 'breaks' => true ],
	'number'	=> [ 'type' => 'integer', 'blocks' => true, 'breaks' => true ],
] ] );
$flagModel = \Nino\Filesystem::getFileContent( $appData, '/elements/flagtype.php', false )['model'] ?? [];
check( 'blocks is kept on a rich text field, and breaks is dropped from it', ( $flagModel['rich'] ?? null ) === [ 'type' => 'string', 'html' => true, 'blocks' => true ] );
check( 'a rich field without blocks stays as it was', ( $flagModel['inline'] ?? null ) === [ 'type' => 'string', 'html' => true ] );
check( 'breaks is kept on a plain string field, blocks is dropped from it', ( $flagModel['plain'] ?? null ) === [ 'type' => 'string', 'breaks' => true ] );
check( 'neither means anything on any other type', ( $flagModel['number'] ?? null ) === [ 'type' => 'integer' ] );
unlink( \Nino\Filesystem::path( $appData, '/elements/flagtype.php' ) );

/*	A type with content in every bucket: the '*' defaults and an element, and
	a locale that is available, one that is not any more, and a default entry
	in a locale	*/
$typeFile = static fn( string $uri ): array|false => \Nino\Filesystem::getFileContent( $appData, '/elements/'. $uri. '.php', false );
$seedRenamer = static function() use ( &$appData ): void {
	\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', [
		'title'	=> 'Renamer',
		'model'	=> [
			'title'	=> [ 'type' => 'string', 'locale' => true ],
			'tags'	=> [ 'type' => 'array' ],
			'photo'	=> [ 'type' => 'image', 'width' => 10, 'height' => 10 ],
			'extra'	=> [ 'type' => 'string' ],
		],
		'*'			=> [ '*' => [ 'tags' => [ 'default' ] ], 'one' => [ 'tags' => [ 'a', 'b' ], 'photo' => 'one.jpg', 'extra' => 'kept' ] ],
		'de_DE'	=> [ '*' => [ 'title' => 'Vorgabe' ], 'one' => [ 'title' => 'Eins' ] ],
		'en_US'	=> [ 'one' => [ 'title' => 'One' ] ],
		'fr_FR'	=> [ 'one' => [ 'title' => 'Un' ] ],
	] );
};
$renamerModel = static fn( array $overrides = [] ): array => array_replace( [
	'title'	=> [ 'type' => 'string', 'locale' => true ],
	'tags'	=> [ 'type' => 'array' ],
	'photo'	=> [ 'type' => 'image', 'width' => 10, 'height' => 10 ],
	'extra'	=> [ 'type' => 'string' ],
], $overrides );
$saveRenamer = static function( array $model, mixed $renames ) use ( &$appData ): array {
	return callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [ 'uri' => 'renamer', 'title' => 'Renamer', 'model' => $model, 'renames' => $renames ] );
};

$seedRenamer();
$renamed = [ 'headline' => [ 'type' => 'string', 'locale' => true ], 'tags' => [ 'type' => 'array' ], 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ], 'extra' => [ 'type' => 'string' ] ];
[ $status, $body ] = $saveRenamer( $renamed, [ 'title' => 'headline' ] );
$after = $typeFile( 'renamer' );
check( 'a rename is saved', $status === 200 && ( $body['renamed'] ?? null ) === [ 'title' => 'headline' ] && array_keys( $after['model'] ) === [ 'headline', 'tags', 'photo', 'extra' ] );
check( 'every value moves to the new key: the elements of every bucket, a locale that is not available any more among them',
	( $after['de_DE']['one'] ?? null ) === [ 'headline' => 'Eins' ] && ( $after['en_US']['one'] ?? null ) === [ 'headline' => 'One' ] && ( $after['fr_FR']['one'] ?? null ) === [ 'headline' => 'Un' ] );
check( '...and the defaults of a locale, which are an entry like any other', ( $after['de_DE']['*'] ?? null ) === [ 'headline' => 'Vorgabe' ] );
check( '...the fields that were not renamed stay where they were, in the order they were', ( $after['*']['one'] ?? null ) === [ 'tags' => [ 'a', 'b' ], 'photo' => 'one.jpg', 'extra' => 'kept' ] && ( $after['*']['*'] ?? null ) === [ 'tags' => [ 'default' ] ] );
check( 'the old key is gone from a fresh read, and the new one is there', array_key_exists( 'title', $after['de_DE']['one'] ) === false && array_key_exists( 'headline', $after['de_DE']['one'] ) === true );
check( 'the log names the rename', \Nino\Modules\Elements\Types::log( 'types/save', [ 'uri' => 'renamer', 'renames' => [ 'title' => 'headline', 'tags' => 'labels' ] ] ) === 'Edit Element Type /renamer (renamed title to headline, tags to labels)'
	&& \Nino\Modules\Elements\Types::log( 'types/save', [ 'uri' => 'renamer' ] ) === 'Edit Element Type /renamer' );

// Renamed in the same save as its shape: the old model is read as it is now called
$seedRenamer();
[ $status ] = $saveRenamer( [ 'headline' => [ 'type' => 'string' ] ] + array_diff_key( $renamerModel(), [ 'title' => 1 ] ), [ 'title' => 'headline' ] );
$after = $typeFile( 'renamer' );
check( 'a rename and a switch to global in one save migrate the values under the new key',
	$status === 200 && ( $after['*']['one']['headline'] ?? null ) === 'Eins' && isset( $after['en_US']['one']['headline'] ) === false && isset( $after['de_DE']['one']['title'] ) === false );

// A swap and a chain are read against the type as it was
$seedRenamer();
[ $status ] = $saveRenamer( [ 'extra' => [ 'type' => 'array' ], 'tags' => [ 'type' => 'string' ] ], [ 'tags' => 'extra', 'extra' => 'tags' ] );
$after = $typeFile( 'renamer' );
check( 'two fields can swap their names', $status === 200 && ( $after['*']['one']['extra'] ?? null ) === [ 'a', 'b' ] && ( $after['*']['one']['tags'] ?? null ) === 'kept' );
$seedRenamer();
[ $status ] = $saveRenamer( [ 'tags' => [ 'type' => 'string' ], 'extra' => [ 'type' => 'array' ], 'last' => [ 'type' => 'string' ] ], [ 'extra' => 'last', 'tags' => 'extra' ] );
$after = $typeFile( 'renamer' );
check( 'a chain, a to b and b to c, moves each value once', $status === 200 && ( $after['*']['one']['last'] ?? null ) === 'kept' && ( $after['*']['one']['extra'] ?? null ) === [ 'a', 'b' ] && array_key_exists( 'tags', $after['*']['one'] ) === false );

// Refusals: nothing is written
$seedRenamer();
$before = $typeFile( 'renamer' );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'extra' => 'tags' ] );
check( 'a name another field keeps is a 409 that names it', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_collision' && ( $body['params'] ?? [] ) === [ 'tags' ] );
[ $status, $body ] = $saveRenamer( [ 'both' => [ 'type' => 'string' ] ], [ 'extra' => 'both', 'tags' => 'both' ] );
check( 'two fields renamed to one name are a 409 too', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_collision' );
// Values a removed field left behind: the editor never deletes data
$left = $typeFile( 'renamer' );
$left['*']['one']['removed'] = 'stale';
\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', $left );
[ $status, $body ] = $saveRenamer( [ 'removed' => [ 'type' => 'string' ] ] + array_diff_key( $renamerModel(), [ 'extra' => 1 ] ), [ 'extra' => 'removed' ] );
check( 'a name that still holds the values of a removed field is a 409: they would turn up in elements that never had them', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_values' && ( $body['params'] ?? [] ) === [ 'removed' ] );
$seedRenamer();
$twoImagesModel = $renamerModel( [ 'second' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ] ] );
\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', [ 'model' => $twoImagesModel ] + $typeFile( 'renamer' ) );
[ $status, $body ] = $saveRenamer( $twoImagesModel, [ 'photo' => 'second', 'second' => 'photo' ] );
check( 'a swap of two image fields is a 409: an upload\'s file is named after the field, so they would overwrite each other', $status === 409 && ( $body['code'] ?? '' ) === 'types_rename_image' );
[ $status, $body ] = $saveRenamer( [ 'pic' => [ 'type' => 'image', 'width' => 10, 'height' => 10 ] ] + array_diff_key( $twoImagesModel, [ 'photo' => 1 ] ), [ 'photo' => 'pic' ] );
check( '...a plain rename of an image field is fine, and the value - the file name - moves with it', $status === 200 && ( $typeFile( 'renamer' )['*']['one']['pic'] ?? null ) === 'one.jpg' );

$seedRenamer();
$before = $typeFile( 'renamer' );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'nothere' => 'title' ] );
check( 'a rename of a field the type does not have is a 400', $status === 400 && ( $body['code'] ?? '' ) === 'types_rename_unknown' && ( $body['params'] ?? [] ) === [ 'nothere' ] );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'title' => 'gone' ] );
check( '...so is a rename to a name the saved model does not have', $status === 400 && ( $body['code'] ?? '' ) === 'types_rename_missing' );
foreach( [ 'title', [ 'title' => 5 ], [ 'title' => '' ], [ 'title' => [ 'x' ] ], [ '' => 'x' ] ] as $malformed ) {
	[ $status, $body ] = $saveRenamer( $renamerModel(), $malformed );
	check( 'renames of '. json_encode( $malformed ). ' are a 400, not a guess', $status === 400 && ( $body['code'] ?? '' ) === 'types_renames' );
}
check( 'none of the refusals wrote anything', $typeFile( 'renamer' ) === $before );
[ $status, $body ] = $saveRenamer( $renamerModel(), [ 'title' => 'title' ] );
check( 'a rename to the name a field has is none', $status === 200 && ( $body['renamed'] ?? null ) === [] && $typeFile( 'renamer' )['de_DE']['one'] === [ 'title' => 'Eins' ] );

// An old client sends no renames
$seedRenamer();
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [ 'uri' => 'renamer', 'title' => 'Renamer', 'model' => $renamerModel() ] );
check( 'a save without renames behaves as before', $status === 200 && $typeFile( 'renamer' )['de_DE']['one'] === [ 'title' => 'Eins' ] );

// An image's alt link follows the rename of the field it points at: the form
// names it the way it was saved while that field is in the form
$altModel = [ 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10, 'alt' => 'photoAlt' ], 'photoAlt' => [ 'type' => 'string', 'locale' => true ] ];
\Nino\Filesystem::putFileContent( $appData, '/elements/renamer.php', [ 'title' => 'Renamer', 'model' => $altModel, '*' => [ '*' => [] ], 'de_DE' => [ 'one' => [ 'photoAlt' => 'Ein Bild' ] ] ] );
[ $status ] = $saveRenamer( [ 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10, 'alt' => 'photoAlt' ], 'altText' => [ 'type' => 'string', 'locale' => true ] ], [ 'photoAlt' => 'altText' ] );
$after = $typeFile( 'renamer' );
check( 'renaming the field that holds an image\'s alt text keeps the link, under the new name, and the values move', $status === 200 && ( $after['model']['photo']['alt'] ?? null ) === 'altText' && ( $after['de_DE']['one'] ?? null ) === [ 'altText' => 'Ein Bild' ] );
[ $status ] = $saveRenamer( [ 'photo' => [ 'type' => 'image', 'width' => 10, 'height' => 10, 'alt' => 'altText' ], 'altText' => [ 'type' => 'string', 'locale' => true ] ], [] );
check( '...and a link already at the new name is left alone', $status === 200 && ( $typeFile( 'renamer' )['model']['photo']['alt'] ?? null ) === 'altText' );

// What the rename cannot reach is reported, not changed
$seedRenamer();
mkdir( $sandbox. '/private/templates', 0777, true );
file_put_contents( $sandbox. '/private/templates/page-renamer.tpl', '[elements /renamer]<h1>[[title]]</h1>[/elements]' );
$appData['/nino/auth/roles']['renamer-editor'] = [ 'label' => 'Editor', 'perms' => [ '/_admin/elements/renamer/update/title' ] ];
\Nino\Html::addFills( $appData, [ '/_admin/elements/field/renamer/title' => 'Headline' ], '*' );
[ $status, $body ] = $saveRenamer( $renamed, [ 'title' => 'headline' ] );
$references = $body['references'] ?? [];
check( 'the save reports the template, the role and the label text that still use the old name',
	$status === 200
	&& in_array( [ 'kind' => 'template', 'name' => 'page-renamer.tpl', 'field' => 'title' ], $references, true ) === true
	&& in_array( [ 'kind' => 'role', 'name' => 'renamer-editor', 'field' => 'title' ], $references, true ) === true
	&& in_array( [ 'kind' => 'label', 'name' => '/_admin/elements/field/renamer/title', 'field' => 'title' ], $references, true ) === true );
check( '...and has changed none of them', str_contains( (string) file_get_contents( $sandbox. '/private/templates/page-renamer.tpl' ), '[[title]]' ) === true && $appData['/nino/auth/roles']['renamer-editor']['perms'] === [ '/_admin/elements/renamer/update/title' ] );
unlink( $sandbox. '/private/templates/page-renamer.tpl' );
rmdir( $sandbox. '/private/templates' );
unset( $appData['/nino/auth/roles']['renamer-editor'] );

// Permission
ninoUser( $appData, 'norename@example.com', [ '/_admin/text/manage' ] );
$seedRenamer();
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'uri' => 'renamer', 'title' => 'Renamer', 'model' => $renamed, 'renames' => [ 'title' => 'headline' ] ], 'types/save' );
check( 'an account without the tab\'s permission is refused a rename, and nothing moves', $status === 403 && ( $typeFile( 'renamer' )['de_DE']['one'] ?? null ) === [ 'title' => 'Eins' ] );
\Nino\Auth::deleteUser( $appData, 'norename@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Elements: element *content* CRUD ------------------------------------

echo "Elements - element content CRUD\n";

// The three languages the checks below expect
callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );

// A second type alongside testtype, with both a global and a locale field, so
// the global/locale split and the raw-bucket view have something real to show
file_put_contents( $sandbox. '/private/elements/contenttype.php', '<?php return [
	\'title\'	=> \'Content Type\',
	\'model\'	=> [
		\'title\'	=> [ \'type\' => \'string\', \'locale\' => true ],
		\'views\'	=> [ \'type\' => \'integer\', \'default\' => 0 ],
	],
];' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
check( 'apiTypes succeeds', $status === 200 );
check( 'apiTypes lists every type on disk', in_array( 'contenttype', array_column( $body['types'], 'type' ), true ) === true );
check( 'apiTypes carries each type\'s model along', ( $body['types'][array_search( 'contenttype', array_column( $body['types'], 'type' ), true )]['model']['views']['type'] ?? null ) === 'integer' );
// Not a literal list: the setup at the top of this section adds fr_FR to the
// available locales, and this module must simply report whatever is configured
// at the time it runs
check( 'apiTypes reports the currently available locales', $body['locales'] === \Nino\Locales::getAvailableLocales( $appData ) );
check( 'apiTypes seeds the locale select with the native locale', $body['selectedLocale'] === 'de_DE' );

// The line under a type names its elements and is cut at 150 bytes. An
// element uri may hold any character the kernel api accepts, so the cut could
// land inside a multibyte one - and json_encode() answers a string that is
// not valid utf-8 with false, which is the whole panel coming back empty
\Nino\Filesystem::putFileContent( $appData, '/elements/langtype.php', [
	'title'	=> 'Umlaute',
	'model'	=> [ 'title' => [ 'type' => 'string', 'locale' => true ] ],
	'*'			=> [ '*' => [] ],
] );
// Placed so the cut lands inside the 'ü': '(1) ' and the leading '/' are
// five bytes, so byte 150 of the line is byte 145 of the name
\Nino\Elements::insertElement( $appData, '/langtype/'. str_repeat( 'a', 144 ). 'über-uns', [ 'title' => 'Ü' ], 'de_DE' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
$langRow = $body['types'][ array_search( 'langtype', array_column( $body['types'], 'type' ), true ) ] ?? [];
check( 'a type whose element names are long and not ascii still answers', $status === 200 && ( $langRow['descr'] ?? '' ) !== '' );
check( '...with a line that is text, so the panel\'s answer can be encoded at all', mb_check_encoding( (string) ( $langRow['descr'] ?? '' ), 'UTF-8' ) === true
	&& json_encode( $body ) !== false );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'nope' ] );
check( 'apiList 404s for an unknown type', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'first', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'Erster', 'views' => 3 ],
] );
check( 'apiSave inserts a new element', $status === 200 );
check( 'apiSave returns the stored element', ( $body['element']['title'] ?? null ) === 'Erster' && ( $body['element']['views'] ?? null ) === 3 );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'not a slug', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'title' => 'x' ],
] );
check( 'apiSave rejects a new uri that is not a plain slug', $status === 400 );

// The second locale of the same element - what the frontend's _saveLocales()
// sends as a follow-up request, global fields omitted (position > 0)
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'contenttype', 'uri' => 'first', 'locale' => 'en_US', 'isNew' => false,
	'fields' => [ 'title' => 'First' ],
] );
check( 'apiSave updates a second locale of the same element', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'contenttype' ] );
check( 'apiList succeeds', $status === 200 );
check( 'apiList finds the element across locales', count( $body['elements'] ) === 1 && $body['elements'][0]['uri'] === 'first' );
check( 'apiList labels an element by its title', $body['elements'][0]['label'] === 'Erster' );

// Only 'title', and the element's own uri otherwise. Guessing a label from
// 'label'/'name' or from whichever string field came first in the model meant
// the list showed a different field per type, and reordering a model silently
// relabelled every row in it
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiCreate', [ 'uri' => 'labeltype', 'title' => 'Label Type', 'model' => [
	'name' 	=> [ 'type' => 'string' ],
	'label' => [ 'type' => 'string' ],
	'title' => [ 'type' => 'string' ],
] ] );
check( 'a type for the label rules is created', $status === 200 );

callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'titled', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'label' => 'A label', 'title' => 'A title' ] ] );
callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'untitled', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'label' => 'A label' ] ] );
callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [ 'type' => 'labeltype', 'uri' => 'blank-title', 'locale' => 'de_DE', 'isNew' => true,
	'fields' => [ 'name' => 'A name', 'title' => '' ] ] );

[ , $labelBody ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'labeltype' ] );
$labels = array_column( $labelBody['elements'], 'label', 'uri' );

check( 'a title wins', ( $labels['titled'] ?? null ) === 'A title' );
check( 'no title falls back to the element\'s own uri, not to "label" or "name"', ( $labels['untitled'] ?? null ) === '/untitled' );
check( 'an empty title counts as no title', ( $labels['blank-title'] ?? null ) === '/blank-title' );

// --- a type that numbers its own elements --------------------------------
//
// Some types have entries with no name worth putting in a url - a gallery
// image, a price row. Asking for one anyway is how a project ends up with
// "bild-2", "bild-2-neu", "bild-2-final". Such a type is switched to numbering
// in this editor; the kernel then allocates the uri (see
// Elements::AUTOINCREMENT_PAD), and the element form stops asking.

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiCreate', [
	'uri' => 'gallery', 'title' => 'Gallery', 'autoincrement' => true,
	'model' => [ 'caption' => [ 'type' => 'string' ] ],
] );
check( 'a type can be created numbered', $status === 200 && ( $body['autoincrement'] ?? null ) === true );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiGet', [ 'uri' => 'gallery' ] );
check( 'apiGet reports that this type numbers its elements', ( $body['autoincrement'] ?? null ) === true );
check( '...and names the uri the next element would get', ( $body['next'] ?? null ) === '00001' );

// The element form posts no uri at all for such a type - that is the request
// to be numbered, and it is the kernel that decides which number
[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'caption' => 'One' ] ] );
check( 'saving with no uri allocates the first number', $status === 200 && ( $body['element']['.uri'] ?? null ) === '/gallery/00001' );
check( '...and reports the next one back, so the form\'s promise stays true', ( $body['nextUri'] ?? null ) === '00002' );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'caption' => 'Two' ] ] );
check( 'the next save gets the next number', ( $body['element']['.uri'] ?? null ) === '/gallery/00002' );

[ , $listBody ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiList', [ 'type' => 'gallery' ] );
check( 'both numbered elements are listed', array_column( $listBody['elements'], 'uri' ) === [ '00001', '00002' ] );

[ , $typesBody ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
$galleryEntry = array_column( $typesBody['types'], null, 'type' )['gallery'] ?? [];
check( 'the element form is told which types are numbered', ( $galleryEntry['autoincrement'] ?? null ) === true );
check( '...and what the next uri would be, so it can show it', ( $galleryEntry['nextUri'] ?? null ) === '00003' );

// An update names its element like any other - only the insert is uri-less
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'gallery', 'uri' => '00001', 'locale' => 'de_DE', 'isNew' => false, 'fields' => [ 'caption' => 'Edited' ] ] );
check( 'a numbered element updates under the uri it was given', $status === 200 );
check( '...and the edit actually landed', ( \Nino\Elements::getElement( $appData, '/gallery/00001', 'de_DE' )['caption'] ?? null ) === 'Edited' );

// The same request against a type that names its own elements stays a 400 -
// numbering is a property of the type, not a way to skip validation
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'x' ] ] );
check( 'an empty uri is still rejected for a type that is not numbered', $status === 400 );

// Turning numbering on later must not be able to collide with an element the
// type already has - including one that happens to look like a number
callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '00007', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'seven' ] ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [
	'uri' => 'labeltype', 'title' => 'Label Type', 'autoincrement' => true,
	'model' => [ 'name' => [ 'type' => 'string' ], 'label' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ] );
check( 'numbering can be switched on for an existing type', ( $body['autoincrement'] ?? null ) === true );

[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'eight' ] ] );
$seeded = $body['element']['.uri'] ?? null;
check( 'the counter starts past the highest number already in the type', $seeded === '/labeltype/00008' );
check( 'the elements that were named by hand keep their uris',
	( \Nino\Elements::getElement( $appData, '/labeltype/titled', 'de_DE' )['title'] ?? null ) === 'A title' );

// Switching it back off returns the type to being named by hand
[ , $body ] = callDev( $appData, \Nino\Modules\Elements\Types::class, 'apiSave', [
	'uri' => 'labeltype', 'title' => 'Label Type', 'autoincrement' => false,
	'model' => [ 'name' => [ 'type' => 'string' ], 'label' => [ 'type' => 'string' ], 'title' => [ 'type' => 'string' ] ] ] );
check( 'numbering can be switched off again', ( $body['autoincrement'] ?? null ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiSave', [
	'type' => 'labeltype', 'uri' => '', 'locale' => 'de_DE', 'isNew' => true, 'fields' => [ 'name' => 'x' ] ] );
check( '...and an uri-less save is refused again', $status === 400 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'apiGet succeeds', $status === 200 );
check( 'apiGet splits global fields out of the locale buckets', $body['global'] === [ 'views' => 3 ] );
check( 'apiGet keeps each locale\'s own translation', $body['locales']['de_DE']['title'] === 'Erster' && $body['locales']['en_US']['title'] === 'First' );

// An untranslated locale still shows up in the form, with empty fields: the
// element exists there (getElement resolves the shared '*' bucket, which is
// not empty), it just has no translation of its own yet. Same behaviour as
// _editor's equivalent - the form is the resolved view, not the storage
check( 'a locale with no translation of its own is still offered, with empty fields', array_key_exists( 'fr_FR', $body['locales'] ) === true && $body['locales']['fr_FR']['title'] === null );

// ...and this is exactly what the raw view is for: it shows the storage, so
// the difference between "translated" and "only resolving through '*'" -
// invisible in the form above - becomes readable
check( 'apiGet exposes the raw per-bucket storage', isset( $body['raw'] ) === true );
check( 'the global field sits in the "*" bucket, not in a locale one', $body['raw']['*'] === [ 'views' => 3 ] );
check( 'each locale bucket holds only that locale\'s own fields', $body['raw']['de_DE'] === [ 'title' => 'Erster' ] && $body['raw']['en_US'] === [ 'title' => 'First' ] );
check( 'a locale that only resolves through "*" has no bucket of its own at all', array_key_exists( 'fr_FR', $body['raw'] ) === false );
check( 'the raw view never leaks the type\'s own non-bucket keys', isset( $body['raw']['model'] ) === false && isset( $body['raw']['title'] ) === false );


echo "\nTranslations - native Text + Elements JSON round-trip\n";

// Translation fixtures deliberately mix public/per-locale content with
// global, blacklisted and image values which must never enter the package.
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', function( array $content ): array {
	$content['[[/translation/title]]'] 		= '<code>Nino</code> Start';
	$content['[[/translation/plain]]'] 		= 'Willkommen';
	$content['[[/translation/technical]]'] = 'do-not-translate';
	return $content;
} );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', function( array $content ): array {
	$content['[[/translation/title]]'] 		= '<code>Nino</code> Old';
	$content['[[/translation/plain]]'] 		= 'Old';
	$content['[[/translation/technical]]'] = 'technical';
	return $content;
} );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $content ): array {
	$content['[[/translation/global]]'] = 'Shared';
	return $content;
} );
\Nino\Filesystem::mutate( $appData, '/text/blacklist.php', function( array $content ): array {
	$content[] = '/translation/technical';
	return array_values( array_unique( $content ) );
} );

\Nino\Filesystem::putFileContent( $appData, '/elements/translationtype.php', [
	'title' => 'Translation Type',
	'model' => [
		'title' 			=> [ 'type' => 'string', 'locale' => true ],
		'description' => [ 'type' => 'string', 'locale' => true, 'html' => true ],
		'features' 		=> [ 'type' => 'array', 'locale' => true ],
		'views' 			=> [ 'type' => 'integer' ],
		'image' 			=> [ 'type' => 'image', 'locale' => true ],
		'related' 		=> [ 'type' => 'element', 'elementType' => 'brandnewtype', 'locale' => true ],
		// A whitelisted translatable field: what the import lets through has
		// to be what the kernel takes, or one bad value fails the whole element
		'code' 				=> [ 'type' => 'string', 'locale' => true, 'whitelist' => [ '01', '02' ] ],
	],
	'*' => [
		'*' => [],
		'item' => [ 'views' => 7 ],
	],
	'de_DE' => [
		'item' => [
			'title' => 'Projekt',
			'description' => '<code>Native</code>',
			'features' => [ 'Schnell', 'Klein' ],
			'image' => 'native.webp',
			'related' => '/brandnewtype/de',
			'code' => '01',
		],
	],
	'en_US' => [
		'item' => [
			'title' => 'Old project',
			'description' => '<code>Old</code>',
			'features' => [ 'Old' ],
			'image' => 'english.webp',
			'related' => '/brandnewtype/en',
			'code' => '01',
		],
	],
] );
unset( $appData['./nino/elements/cache'] );

[ $status, $info ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiInfo' );
check( 'apiInfo succeeds and fixes the source to the native locale', $status === 200 && $info['nativeLocale'] === 'de_DE' );
check( 'apiInfo offers every configured import target', $info['locales'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'translations/info' );
check( 'Translations is registered in Admin\'s central action dispatcher', $status === 200 );

[ $status, $translation ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiExport' );
check( 'apiExport returns a versioned translation document', $status === 200 && $translation['format'] === 'nino.translation' && $translation['version'] === 1 );
check( 'apiExport includes public native text without [[ ]] around its key', ( $translation['text']['/translation/title'] ?? null ) === '<code>Nino</code> Start' );
check( 'apiExport excludes global and blacklisted text', isset( $translation['text']['/translation/global'] ) === false && isset( $translation['text']['/translation/technical'] ) === false );
check( 'apiExport includes locale-scoped element content', ( $translation['elements']['translationtype']['item']['features'] ?? null ) === [ 'Schnell', 'Klein' ] );
check( 'apiExport excludes global and image element fields', isset( $translation['elements']['translationtype']['item']['views'] ) === false && isset( $translation['elements']['translationtype']['item']['image'] ) === false );
// A reference is per-locale content, but a uri is not translatable text - it
// is picked in the element form, where it shows as the element it points at
check( 'apiExport excludes element reference fields too', isset( $translation['elements']['translationtype']['item']['related'] ) === false );

// Post only a small translated subset: import is allowed to be partial and
// must count every invalid path while still applying valid siblings.
$translation['text'] = [
	'/translation/title' 		=> 'Home <code>Nino</code>',
	'/translation/plain' 		=> '<b>Welcome</b>',
	'/translation/global' 		=> 'HACKED',
	'/translation/technical' => 'HACKED',
	'/translation/unknown' 		=> 'HACKED',
];
$translation['elements'] = [
	'translationtype' => [
		'item' => [
			'title' => '<b>Project</b>',
			'description' => '<code>Translated</code><img src=x onerror=alert(1)>',
			'features' => [ 'One <b>x</b>', [ 'Deep <em>y</em>' ] ],
			'views' => 99,
			'image' => 'hacked.webp',
			'related' => '/brandnewtype/hacked',
			// '1' == '01' loosely, and the kernel compares strictly: a
			// pre-check that let it through made the whole element fail
			'code' => '1',
			'unknown' => 'HACKED',
		],
		'ghost' => [ 'title' => 'HACKED' ],
	],
	'unknown-type' => [ 'ghost' => [ 'title' => 'HACKED' ] ],
];

[ $status, $result ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [
	'targetLocale' => 'en_US',
	'translation' => $translation,
] );
check( 'apiImport succeeds for a configured target locale', $status === 200 && $result['targetLocale'] === 'en_US' );
check( 'apiImport reports Text values and rejected keys separately', $result['text'] === [ 'imported' => 2, 'skipped' => 3 ] );
check( 'apiImport reports Element fields and rejected paths separately', $result['elements'] === [ 'imported' => 3, 'skipped' => 7 ] );

$translatedText = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$nativeText = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'Text import preserves allowed code markup and sanitizes plain text', $translatedText['[[/translation/title]]'] === 'Home <code>Nino</code>' && $translatedText['[[/translation/plain]]'] === 'Welcome' );
check( 'Text import leaves native source, global and technical values untouched', $nativeText['[[/translation/title]]'] === '<code>Nino</code> Start' && $translatedText['[[/translation/technical]]'] === 'technical' );

$translatedElements = \Nino\Filesystem::getFileContent( $appData, '/elements/translationtype.php', [] );
check( 'Element import sanitizes plain, HTML and nested array strings', $translatedElements['en_US']['item']['title'] === 'Project' && $translatedElements['en_US']['item']['description'] === '<code>Translated</code>' && $translatedElements['en_US']['item']['features'] === [ 'One x', [ 'Deep y' ] ] );
check( 'Element import cannot overwrite global or image fields', $translatedElements['*']['item']['views'] === 7 && $translatedElements['en_US']['item']['image'] === 'english.webp' );
check( '...nor an element reference, which is a choice rather than a translation', $translatedElements['en_US']['item']['related'] === '/brandnewtype/en' );
check( 'Element import leaves the native bucket untouched', $translatedElements['de_DE']['item']['title'] === 'Projekt' );
check( 'a value the kernel would refuse is skipped on its own, and its siblings are imported', $translatedElements['en_US']['item']['code'] === '01' && $translatedElements['en_US']['item']['title'] === 'Project' );

// A key that holds a line break and a field that keeps paragraphs: what comes
// back from a translator is held to the format, not to the inline tags alone
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $content ): array => $content + [ '[[/translation/closing]]' => 'Danke.<br>Grüße' ] );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', fn( array $content ): array => $content + [ '[[/translation/closing]]' => 'Thanks.<br>Regards' ] );
\Nino\Filesystem::putFileContent( $appData, '/elements/translationformats.php', [
	'title' => 'Translation Formats',
	'model' => [
		'body' => [ 'type' => 'string', 'locale' => true, 'html' => true, 'blocks' => true ],
		'note' => [ 'type' => 'string', 'locale' => true, 'breaks' => true ],
	],
	'*'			=> [ '*' => [] ],
	'de_DE'	=> [ 'item' => [ 'body' => '<p>Eins</p>', 'note' => "Zeile 1\nZeile 2" ] ],
	'en_US'	=> [ 'item' => [ 'body' => '<p>One</p>', 'note' => 'Line' ] ],
] );
unset( $appData['./nino/elements/cache'] );

$formatsTranslation = $translation;
$formatsTranslation['text'] = [ '/translation/closing' => "Thanks.\nBest regards,<script>x</script>" ];
$formatsTranslation['elements'] = [ 'translationformats' => [ 'item' => [
	'body' => '<p>One</p><ul><li>a</li><li>b</li></ul><script>x</script>',
	'note' => "L1\nL2 <b>x</b>",
] ] ];
[ $status, $result ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [ 'targetLocale' => 'en_US', 'translation' => $formatsTranslation ] );
$formatsText 			= \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
$formatsElements 	= \Nino\Filesystem::getFileContent( $appData, '/elements/translationformats.php', [] );
check( 'a text key that holds a line break takes the newlines of its translation as breaks', $status === 200 && ( $formatsText['[[/translation/closing]]'] ?? '' ) === 'Thanks.<br>Best regards,' );
check( 'a blocks field keeps the paragraphs and the list of its translation', ( $formatsElements['en_US']['item']['body'] ?? '' ) === '<p>One</p><ul><li>a</li><li>b</li></ul>' );
check( 'a breaks field keeps its newlines, and loses its tags like any plain field', ( $formatsElements['en_US']['item']['note'] ?? '' ) === "L1\nL2 x" );

$badTranslation = $translation;
$badTranslation['version'] = 99;
[ $status ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [ 'targetLocale' => 'en_US', 'translation' => $badTranslation ] );
check( 'apiImport rejects an incompatible format version', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiImport', [ 'targetLocale' => 'xx_XX', 'translation' => $translation ] );
check( 'apiImport rejects an unknown target locale', $status === 400 );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Translations::class, 'apiExport' );
check( 'Translations actions require an authed _admin session', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

check( 'Routes says where it sits: the structure group, right after Element Types, under a fill', \Nino\Modules\Routes\Admin::nav() === [ 'routes', '/_admin/nav/routes', 20, 'structure' ] );


[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'nope' ] );
check( 'apiGet 404s for an unknown element', $status === 404 );

// Regression guard for the exact shape that used to crash the kernel's own
// deleteElement()/queryElements(): a hand-authored type file's top-level
// 'title' is a plain string, not a bucket of entries. Fed in literally rather
// than read off disk - this is a pure function, and the fixture files earlier
// checks in this file mutate would make it a moving target
$handAuthored = [
	'title' => 'Hand Authored',
	'model' => [ 'name' => [ 'type' => 'string', 'locale' => true ] ],
	'*' 		=> [ '*' => [], 'item1' => [ 'sort' => 1 ] ],
	'de_DE' => [ 'item1' => [ 'name' => 'Hallo' ], 'item2' => [ 'name' => 'Zwei' ] ],
];
check( 'rawBuckets survives a type file whose top-level "title" is a plain string', \Nino\Modules\Elements\Admin::rawBuckets( $handAuthored, 'item1' ) === [ '*' => [ 'sort' => 1 ], 'de_DE' => [ 'name' => 'Hallo' ] ] );
check( 'rawBuckets never returns another element\'s buckets', \Nino\Modules\Elements\Admin::rawBuckets( $handAuthored, 'item2' ) === [ 'de_DE' => [ 'name' => 'Zwei' ] ] );
check( 'rawBuckets returns nothing for an element that does not exist', \Nino\Modules\Elements\Admin::rawBuckets( $handAuthored, 'nope' ) === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'apiDelete succeeds', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiGet', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'the deleted element is gone from every locale', $status === 404 );

// Deleting again is a no-op that still reports success: the kernel's
// deleteElement() unsets whatever is there and reports the write, it does not
// treat "already gone" as an error. Identical to _editor's own apiDelete -
// asserted here so a future change to either side has to be deliberate
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'contenttype', 'uri' => 'first' ] );
check( 'deleting an already-deleted element is an idempotent no-op, not an error', $status === 200 );

[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiDelete', [ 'type' => 'nope', 'uri' => 'first' ] );
check( 'apiDelete 400s for an unknown type', $status === 400 );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Elements\Admin::class, 'apiTypes' );
check( 'Elements actions require an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Every action this module registers must actually resolve through the
// dispatcher - a typo in the action map would otherwise only surface in the ui
$dispatchable = true;
foreach( array_keys( \Nino\Modules\Elements\Admin::actions() ) as $actionName ) {
	[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'type' => 'contenttype', 'uri' => 'first' ], $actionName );
	if( $status === 404 && ( $body['error'] ?? '' ) === 'unknown action' )
		$dispatchable = false;
}
check( 'every develements/* action is reachable through Admin::handlePost', $dispatchable === true );

echo "\n";


// --- Activity log: one entry is one line, and every write describes itself ---

echo "Activity log - one entry is one line, every write describes itself\n";

$appData['/nino/admin/logs'] = true;
$before = count( \Nino\Modules\Logs\Admin::recentLines( $appData, 500 ) );
// What a posted template name could carry: a line break, then a second
// line shaped exactly like a real one
\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', "Saved template \"x\"\n". date( 'Y-m-d H:i' ). "  root@example.com  Deleted every account" );
$lines = \Nino\Modules\Logs\Admin::recentLines( $appData, 500 );
check( 'a line break in a recorded value writes no second line', count( $lines ) === $before + 1 );
check( '...the whole value stays on the one line, attributed to who wrote it', count( array_filter( $lines, static fn( $l ): bool => str_starts_with( is_array( $l ) ? implode( ' ', $l ) : (string) $l, date( 'Y-m-d H:i' ). '  root@example.com' ) ) ) === 0 );

/*	...and one byte that is not valid utf-8, which is the reachable half of
	Http::_finalizeResponse()'s encoding contract (see http-smoke.php). A
	recorded line is built from what a panel posted (Admin::_logAction()),
	and a post is bytes: one latin-1 byte in an element name went into the
	log, json_encode() then refused the whole logs/list body, and false
	written into the body echoed as the empty string. The panel read that
	empty 200 as a success with nothing in it, showed a blank list, and said
	nothing - for every later opening too, until somebody edited the file by
	hand. _oneLine() strips control characters, not malformed utf-8	*/
\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', "Saved element \"Gru\xdfe\"" );

$finalizeLogResponse = new ReflectionMethod( '\Nino\Http', '_finalizeResponse' );
$finalizeLogResponse->setAccessible( true );

$logListRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '' ] ];
\Nino\Modules\Logs\Admin::apiList( $appData, $logListRequest );
check( 'logs/list answers with the lines', is_array( $logListRequest['/nino/http/response']['body']['lines'] ?? null ) === true );

$finalizeLogResponse->invokeArgs( null, [ &$logListRequest ] );
$logListBody = json_decode( (string) $logListRequest['/nino/http/response']['body'], true );
check( 'one malformed byte in a logged value does not blank the whole panel', is_array( $logListBody ) === true && $logListRequest['/nino/http/response']['statusCode'] === 200 );
check( '...and every other line is still there to read', count( array_filter( $logListBody['lines'] ?? [], static fn( $l ): bool => str_contains( is_array( $l ) ? implode( ' ', $l ) : (string) $l, 'Delete Element Type /deletable' ) === true ) ) === 1 );

/*	The write itself, where the day's file cannot be written: a directory
	standing in its place here, which is what a permission or a full disk
	does, provoked without either. Reading it and writing it both raised php's
	own warning, which the framework's handler ends the request on - so the
	action being logged died in its log line, and a panel listing the log died
	on the same file. And the lock taken for the write was released only on
	the way that succeeds	*/
$logsDir		= (string) ( new ReflectionClassConstant( '\Nino\Modules\Logs\Admin', 'LOGS_DIR' ) )->getValue();
$todayLog		= $logsDir. '/'. date( 'Y-m-d' ). '.php';
$todayPath	= \Nino\Filesystem::path( $appData, $todayLog );
$todayAside	= $todayPath. '.aside';

if( is_file( $todayPath ) === true )
	rename( $todayPath, $todayAside );
mkdir( $todayPath, 0755, true );

// What the code silenced with @ is not a warning anyone sees - the framework's
// handler and the harness's read error_reporting() the same way
ninoWarnings();
\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', 'Blocked write' );
$blockedList = \Nino\Modules\Logs\Admin::recentLines( $appData, 5 );
$logWarnings = ninoWarnings();

check( 'a day\'s file that cannot be written or read raises no engine warning - the action being logged goes on', array_filter( $logWarnings, static fn( string $w ): bool => str_contains( $w, 'file_put_contents' ) || str_contains( $w, 'file_get_contents' ) ) === [] );
check( '...the failure is reported through the framework\'s own channel instead', count( array_filter( $logWarnings, static fn( string $w ): bool => str_starts_with( $w, 'Activity log write failed' ) ) ) === 1 );
// A guard rather than a regression: without a throw the old code released
// the lock too - what it did not do was release it past one
check( '...with the lock released whichever way the write went', \Nino\Filesystem::unlockFile( $appData, $todayLog ) === false );
check( '...and the listing past that file still answers', is_array( $blockedList ) === true );

rmdir( $todayPath );
if( is_file( $todayAside ) === true )
	rename( $todayAside, $todayPath );

\Nino\Modules\Logs\Admin::record( $appData, 'editor@example.com', 'After the block' );
check( 'the next line is written once the file can be again', str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, 5 ) ), 'After the block' ) === true );

// The shell asks the class that ran an action for its line (see
// Admin::_logAction()): a line on any other class is one nobody reads, which
// is how the whole Templates panel logged nothing. So: the handler of every
// one of these actions is the class asked, and it answers
$described = [
	[ \Nino\Modules\Routes\Admin::class,						'routes/save',				[ 'httpUri' => '/x' ],							'/x' ],
	[ \Nino\Modules\Routes\Admin::class,						'routes/delete',			[ 'httpUri' => '/x' ],							'/x' ],
	[ \Nino\Modules\Routes\Admin::class,						'routes/savetexts',		[ 'uri' => '/.newsletter' ],				'/.newsletter' ],
	[ \Nino\Modules\Config\Admin::class,						'config/save',				[ 'fields' => [ '/nino/cache/ttl' => 1 ] ],	'/nino/cache/ttl' ],
	[ \Nino\Modules\Images\Slots::class,						'slots/delete',				[ 'uri' => '/home/hero' ],					'/home/hero' ],
	[ \Nino\Modules\Language\Translations::class,	'translations/import',	[ 'targetLocale' => 'fr_FR' ],			'fr_FR' ],
	[ \Nino\Modules\Backups\Admin::class,					'backups/restore',		[ 'date' => '2026-09-05' ],				'2026-09-05' ],
	[ \Nino\Modules\Backups\Admin::class,					'backups/now',				[],																	'Backup' ],
	[ \Nino\Modules\Users\RecoveryPassword::class,		'recoverypw/save',		[],																	'Recovery Password' ],
	[ \Nino\Modules\Navigation\Admin::class,				'navs/delete',				[ 'key' => 'main' ],								'main' ],
	[ \Nino\Modules\Features\Admin::class,					'features/activate',	[ 'key' => 'sample' ],							'sample' ],
];
$silent = [];
foreach( $described as [ $class, $action, $data, $needle ] ) {
	$handler = $class::actions()[$action][0] ?? '';
	if( $handler !== $class || method_exists( $class, 'log' ) === false || str_contains( (string) $class::log( $action, $data ), $needle ) === false )
		$silent[] = $action;
}
check( 'every one of these writes is described by the class that runs it'. ( $silent === [] ? '' : ' - silent: '. implode( ', ', $silent ) ), $silent === [] );

echo "\n";

ninoDone( $appData );
