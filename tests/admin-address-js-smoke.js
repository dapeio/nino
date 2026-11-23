/**
 *	Nino									A compact filesystembased php framework
 *	admin-address-js-smoke.js	The one form the workbench writes its addresses in, #panel/tab/part...,
 *										as the router of the shell (_admin/assets/script.js) writes and reads it:
 *										what a screen is called, a word and a key told apart at the segment as it
 *										is written, a key as ONE segment whatever it holds, and what is no address.
 *										The table the PHP side writes the same by is tests/fixtures/addresses.json
 *										(see admin-address-smoke.php).
 *
 *	Usage: node tests/admin-address-js-smoke.js
 */

'use strict';

const { check, done, shellRouter, addressFixture } = require( './harness.js' );

const fixture = addressFixture();

// What the table has as a part - a word is { word : 'new' }, a key a string - as the router is asked for it, and as it reads it back
const texts = parts => parts.map( part => typeof part === 'object' ? '@'+ part.word : part );
const kinds = parts => parts.map( part => typeof part === 'object' );


// --- the table both languages write by ---------------------------------------------------

console.log( 'Writing' );
{
	const shell = shellRouter();
	const word = name => shell.word( name );
	const parts = c => c.parts.map( part => typeof part === 'object' ? word( part.word ) : part );

	check( 'every case of the table is written as the table has it', fixture.cases.every( c => shell.href( c.screen, parts( c ) ) === c.hash ) );
	check( '...and every one is read back as the screen, the parts and the kind of each part it was written from', fixture.cases.every( c => {
		const back = shell.read( c.hash );
		return back.panel === c.screen && back.known === true && JSON.stringify( back.parts ) === JSON.stringify( texts( c.parts ) ) && JSON.stringify( back.parts.words ) === JSON.stringify( kinds( c.parts ) );
	} ) );
	check( 'the panel\'s own screen as it starts is the panel\'s name alone; a tab is a name of its panel, and with parts the own screen is named again',
		shell.href( 'elements', [] ) === '#elements' && shell.href( 'types', [] ) === '#elements/types' && shell.href( 'elements', [ 'blog' ] ) === '#elements/elements/blog' && shell.href( 'dashboard', [] ) === '#dashboard' && shell.href( 'dashboard', [ word('x') ] ) === '#dashboard/dashboard/@x' );
	check( 'a panel with no parts argument is the panel as it starts', shell.href( 'users' ) === '#users' && shell.href( 'roles' ) === '#users/roles' );

	// Every screen of the workbench - the panels that write no state of their own as well - is an address that reads back
	const screens = [];
	Object.keys( fixture.structure ).forEach( panel => fixture.structure[panel].length === 0 ? screens.push( [ panel, panel ] ) : fixture.structure[panel].forEach( tab => screens.push( [ tab[0], panel ] ) ) );
	check( 'every screen of the workbench - '+ screens.length+ ' of '+ Object.keys( fixture.structure ).length+ ' panels - is written as an address that is read back as that screen as it starts, and with a state of any kind', screens.length > 20 && screens.every( ( [ screen, panel ] ) => {
		const start = shell.read( shell.href( screen, [] ) );
		const state = shell.read( shell.href( screen, [ word('new'), 'some key', '/a/b' ] ) );
		return start.panel === screen && start.known === true && start.parts.length === 0 && state.panel === screen && state.known === true && JSON.stringify( state.parts ) === '["@new","some key","/a/b"]'
			&& JSON.stringify( state.parts.words ) === '[true,false,false]' && shell.href( screen, [] ).split('/')[0] === '#'+ panel;
	} ) );
	check( '...and the name of a screen is one place: the first screen of a panel is its name alone, every other is the name and its tab', screens.every( ( [ screen, panel ] ) => {
		const first = fixture.structure[panel].length === 0 || fixture.structure[panel][0][0] === screen;
		return shell.href( screen, [] ) === ( first ? '#'+ panel : '#'+ panel+ '/'+ fixture.structure[panel].find( tab => tab[0] === screen )[1] );
	} ) );
}


// --- the parts: words and keys --------------------------------------------------------------

console.log( 'Words and keys' );
{
	const shell = shellRouter();
	const word = name => shell.word( name );
	const back = ( screen, part ) => shell.read( shell.href( screen, [ part ] ) );

	check( 'a word is written as it is, with its at sign, and is made by word(): a name that is no lowercase word is none', shell.href( 'roles', [ word('new') ] ) === '#users/roles/@new' && shell.href( 'features', [ word('inactive'), word('a-b2') ] ) === '#features/features/@inactive/@a-b2'
		&& [ '', 'New', '1x', 'a b', 'a_b', '@new', 'a/b' ].every( name => { try { shell.word( name ); return false } catch( e ) { return true } } ) );
	check( 'an object that says it is a word is written as one where it is a word, and as the key of the text it says where it is not - one rule, the table of the PHP side holds the same', fixture.wordObjects.every( c => shell.router._segment( c.object ) === c.segment )
		&& shell.href( 'roles', [ { word : 'x/y' } ] ) === '#users/roles/x%2Fy' );
	check( 'a string is always a key: written encoded, with the at sign in it as %40 - a key that reads @new is the key and is never the word', shell.href( 'roles', [ '@new' ] ) === '#users/roles/%40new' && shell.href( 'roles', [ '@new' ] ) !== shell.href( 'roles', [ word('new') ] ) );
	check( '...a key that is called new is a key, written as it is: the word has an at sign, the key none, and %40new is the key @new', shell.href( 'roles', [ 'new' ] ) === '#users/roles/new'
		&& back( 'roles', 'new' ).parts[0] === 'new' && back( 'roles', 'new' ).parts.words[0] === false && back( 'roles', word('new') ).parts.words[0] === true && back( 'roles', '@new' ).parts[0] === '@new' && back( 'roles', '@new' ).parts.words[0] === false );
	check( '...read at the segment as it was written, before it is decoded: @new is the word, %40new the key @new, and a word and a key of the same letters are two things to isWord() and to wordOf()', ( () => {
		const w = shell.read( '#users/roles/@new' ).parts;
		const k = shell.read( '#users/roles/%40new' ).parts;
		const r = shell.router;
		return w[0] === '@new' && k[0] === '@new' && r.isWord( w, 0, 'new' ) === true && r.isWord( k, 0, 'new' ) === false && r.wordOf( w, 0 ) === '@new' && r.wordOf( k, 0 ) === ''
			&& r.isWord( [ '@new' ], 0, 'new' ) === true && r.isWord( [ 'new' ], 0, 'new' ) === false;
	} )() );
	check( '...so is every word of the workbench read as the word it is, not as a key, and the same letters as a key are none', [ 'new', 'scan', 'rename', 'feature', 'inactive', 'available', 'applications' ].every( name => {
		const w = shell.read( shell.href( 'keys', [ word( name ) ] ) ).parts;
		const k = shell.read( shell.href( 'keys', [ '@'+ name ] ) ).parts;
		return shell.router.isWord( w, 0, name ) === true && shell.router.isWord( k, 0, name ) === false && shell.href( 'keys', [ word( name ) ] ).endsWith( '/@'+ name ) && shell.href( 'keys', [ '@'+ name ] ).endsWith( '/%40'+ name );
	} ) );
	check( 'an at sign that begins no word is part of a key, written %40 as every at sign of a key is', shell.href( 'roles', [ '@New' ] ) === '#users/roles/%40New' && shell.href( 'roles', [ '@' ] ) === '#users/roles/%40' && shell.href( 'roles', [ '@a b' ] ) === '#users/roles/%40a%20b'
		&& back( 'roles', '@New' ).parts[0] === '@New' && back( 'roles', '@' ).parts[0] === '@' && back( 'roles', '@1x' ).parts[0] === '@1x' && back( 'roles', '@a_b' ).parts[0] === '@a_b' && shell.href( 'users', [ 'ada@example.com' ] ) === '#users/users/ada%40example.com' );
	check( 'a key is ONE segment, whatever it holds: a slash in it is %2F, so that a key of a path is not a path of keys', shell.href( 'text', [ 'template/page-home', '/template/page-home/title' ] ) === '#text/text/template%2Fpage-home/%2Ftemplate%2Fpage-home%2Ftitle'
		&& shell.read( '#text/text/template%2Fpage-home/%2Ftemplate%2Fpage-home%2Ftitle' ).parts.length === 2 && shell.read( '#text/text/template/page-home' ).parts.length === 2 );
	check( '...so that the segments of a key are two parts, which are not the key: the old way of writing one reads as two keys, and a panel finds none', JSON.stringify( shell.read( '#images/images/template/page-home' ).parts ) === '["template","page-home"]' );

	// Every printable character, alone and with letters round it, comes back the same
	const every = [];
	for( let code = 0x20; code < 0x7f; code++ )
		every.push( String.fromCharCode( code ), 'a'+ String.fromCharCode( code ), String.fromCharCode( code )+ 'a', String.fromCharCode( code ) + String.fromCharCode( code ) );
	check( 'every printable character of ASCII, alone, behind a letter, before one and twice, is a key that comes back as it was written, and as a key - no word is a key, and none is lost',
		every.every( key => {
			const read = shell.read( shell.href( 'elements', [ 'blog', key ] ) );
			return read.known === true && read.parts.length === 2 && read.parts[1] === key && read.parts.words[1] === false;
		} ) );
	const strange = [ 'ä ö ü ß', 'Ünïcödé', '日本語', '\u{1F600}', 'á', '50%', '%41', '%2F', '%', '+', 'a+b', '#', '?x=1&y=2', 'a b', ' leading', 'trailing ', '..', '.', '...', '~', '\\', '"', "'", '<script>', '__proto__', 'constructor', 'toString', 'hasOwnProperty', '0', '00', 'x'.repeat( 1000 ), '@new', '@scan', '@rename', '@feature', '@inactive' ];
	check( 'umlauts, scripts, an emoji, a combined letter, a percent sign, a piece of an escape, dots, names an object has, the words of the workbench: each is one key that comes back as it was written', strange.every( key => {
		const read = shell.read( shell.href( 'elements', [ 'blog', key ] ) );
		return read.known === true && read.parts.length === 2 && read.parts[1] === key && read.parts.words[1] === false;
	} ) );
	check( '...and none of it is a second segment: the address of such a key has the segments of its panel, its tab and its parts and no more', strange.every( key => shell.href( 'elements', [ 'blog', key ] ).split('/').length === 4 ) );
}


// --- what is no address ------------------------------------------------------------------

console.log( 'No address' );
{
	const shell = shellRouter();
	const none = hash => { const read = shell.read( hash ); return read.panel === '' && read.parts.length === 0 && read.known === false };
	const start = ( hash, screen ) => { const read = shell.read( hash ); return read.panel === screen && read.parts.length === 0 && read.known === false };
	const upTo = ( hash, screen, parts ) => { const read = shell.read( hash ); return read.panel === screen && read.known === true && JSON.stringify( read.parts ) === JSON.stringify( parts ) };

	check( 'no hash is no address, and so is the bare #', none( '' ) && none( '#' ) );
	check( 'a panel the page has not is none, with or without more behind it, and a panel is read as it is written: %65lements is not elements', none( '#nosuch' ) && none( '#nosuch/x/y' ) && none( '#Elements' ) && none( '#elements%20' ) && none( '#toString' ) && none( '#__proto__' ) && none( '#%65lements' ) );
	check( 'the old address of a tab, with no panel in front of it, is none: types, slots, keys, roles, lockout, favicon and the rest', [ 'types', 'slots', 'keys', 'roles', 'lockout', 'recoverypw', 'translations', 'favicon' ].every( tab => none( '#'+ tab ) && none( '#'+ tab +'/@new' ) ) );
	check( 'a tab the panel has not is the panel as it starts, not known, so that the shell writes the true address over it - the old forms of a panel with entries among them', [
		[ '#elements/blog', 'elements' ], [ '#elements/blog/post-1', 'elements' ], [ '#users/new', 'users' ], [ '#users/ada@example.com', 'users' ], [ '#text/template/page-home', 'text' ], [ '#images/template/page-home', 'images' ],
		[ '#routes/@new', 'routes' ], [ '#navs/footer', 'navs' ], [ '#features/inactive', 'features' ], [ '#features/@inactive', 'features' ], [ '#users/nope/x', 'users' ],
		[ '#elements/', 'elements' ], [ '#elements//elements', 'elements' ], [ '#elements/%65lements', 'elements' ] ].every( c => start( c[0], c[1] ) ) );
	check( 'a part that is no part ends the parts there - the tab and what stands before it are read, what stands from it on is dropped: an empty segment, a trailing slash', upTo( '#elements/elements/legal//x', 'elements', [ 'legal' ] ) && upTo( '#elements/elements/legal/', 'elements', [ 'legal' ] )
		&& upTo( '#elements/elements//legal', 'elements', [] ) && upTo( '#elements/elements/a/b//c/d', 'elements', [ 'a', 'b' ] ) );
	check( 'a stray percent sign and a piece of an escape drop that part and what follows - the panel and the tab stay, and what stands before is read', upTo( '#elements/elements/legal/x%', 'elements', [ 'legal' ] ) && upTo( '#elements/elements/50%', 'elements', [] ) && upTo( '#elements/elements/%zz', 'elements', [] )
		&& upTo( '#elements/elements/legal/%4', 'elements', [ 'legal' ] ) && upTo( '#elements/elements/legal/%C3%28/x', 'elements', [ 'legal' ] ) && upTo( '#elements/elements/%FF', 'elements', [] ) && upTo( '#users/roles/editor/%', 'roles', [ 'editor' ] ) );
	check( 'a control character - the null byte, a line break, a tab, a delete - drops that part and what follows, written in the hash or as an escape', [ '%00', '%0A', '%0D', '%09', '%1F', '%7F', '\u0000', '\n', '\t', '\u007f' ].every( bad => upTo( '#elements/elements/legal/a'+ bad+ 'b/x', 'elements', [ 'legal' ] ) && upTo( '#elements/elements/'+ bad, 'elements', [] ) ) );
	check( 'the parts that stay are still words and keys as they were written', shell.read( '#elements/elements/legal/@new/x%' ).parts.words.join() === 'false,true' );
	check( 'an address longer than the router reads is none, whatever it holds: 4096 characters are read, one more is not, and a hundred thousand are none', shell.read( '#elements/elements/'+ 'a'.repeat( 4096 - 18 ) ).known === true && none( '#elements/elements/'+ 'a'.repeat( 4096 - 17 ) ) && none( '#elements/elements/'+ 'a'.repeat( 100000 ) ) );
	check( 'markup and a script in an address are text, and a key at that', ( () => { const read = shell.read( '#elements/elements/<img%20src=x%20onerror=alert(1)>' ); return read.known === true && read.parts[0] === '<img src=x onerror=alert(1)>' } )() );
	check( 'the words of the object a map is are no panels: __proto__, constructor, hasOwnProperty', none( '#__proto__/x' ) && none( '#constructor' ) && none( '#hasOwnProperty/x' ) && start( '#elements/__proto__', 'elements' ) && start( '#elements/constructor', 'elements' ) );
}


// --- a panel with tabs of a feature: the name of a tab without the name of its panel ------------

console.log( 'Tabs of a feature' );
{
	const shell = shellRouter( Object.assign( {}, fixture.structure, {
		builder : [ [ 'builder', 'builder' ], [ 'builder-frames', 'frames' ] ],
		themes : [ [ 'themes', 'themes' ], [ 'themes-fonts', 'fonts' ], [ 'themes-css', 'css' ], [ 'themes-settings', 'settings' ] ],
		// An account that may not open a panel's own screen: its first screen is its first tab
		sites : [ [ 'sites-archive', 'archive' ], [ 'sites-settings', 'settings' ] ],
	} ) );

	check( 'a tab of a feature\'s panel is a name of its panel: #builder/frames, and its entries behind it', shell.href( 'builder-frames', [] ) === '#builder/frames' && shell.href( 'builder-frames', [ 'left' ] ) === '#builder/frames/left' && shell.href( 'builder', [ 'page', 'home' ] ) === '#builder/builder/page/home' );
	check( '...and is read as the tab, with the panel\'s uri before it', shell.read( '#builder/frames/left' ).panel === 'builder-frames' && JSON.stringify( shell.read( '#builder/frames/left' ).parts ) === '["left"]' && shell.read( '#themes/fonts' ).panel === 'themes-fonts' && shell.read( '#themes/settings' ).panel === 'themes-settings' );
	check( '...the uri of the tab as it was is none: #builder-frames, #themes-settings', shell.read( '#builder-frames' ).panel === '' && shell.read( '#themes-settings' ).panel === '' && shell.read( '#builder/builder-frames' ).known === false );
	check( 'the first screen of a panel is the panel\'s name alone, whichever screen that is: for a panel with no own screen the first tab', shell.href( 'sites-archive', [] ) === '#sites' && shell.read( '#sites' ).panel === 'sites-archive' && shell.href( 'sites-settings', [] ) === '#sites/settings' && shell.href( 'sites-archive', [ 'x' ] ) === '#sites/archive/x' );
	check( '...and #<panel>/<its first tab> is that screen as it starts too', shell.read( '#sites/archive' ).panel === 'sites-archive' && shell.read( '#sites/archive' ).known === true && shell.read( '#elements/elements' ).panel === 'elements' && shell.read( '#elements/elements' ).known === true );
	check( 'a screen the page has not has no address of a panel: it is written as it is named, and read as none', shell.href( 'nosuch', [ 'x' ] ) === '#nosuch/x' && shell.read( shell.href( 'nosuch', [ 'x' ] ) ).panel === '' );
}


// --- the router's own bookkeeping ----------------------------------------------------------

console.log( 'Writing and settling' );
{
	const shell = shellRouter();
	const { router, box } = shell;

	shell.show( 'roles' );
	router.set( 'roles', [ router.word('new') ] );
	check( 'a screen on show writes its state: the hash is the tab of its panel with the word', box.location.hash === '#users/roles/@new' && router.current().panel === 'roles' && router.isWord( router.current().parts, 0, 'new' ) === true );

	// settle(): what the shell writes after a switch
	shell.show( 'users' );
	box.location.hash = '#users/nope';
	router.settle( 'users' );
	check( 'settle() writes the true address over one that names a tab the panel has not', box.location.hash === '#users' );
	box.location.hash = '#users/roles/@new';
	shell.show( 'roles' );
	router.settle( 'roles' );
	check( '...and leaves one that is true, with its state', box.location.hash === '#users/roles/@new' );
	box.location.hash = '#users/roles/editor/x%';
	router.settle( 'roles' );
	check( '...and leaves one whose parts it read as far as they went: the panel writes what it shows when it shows it', box.location.hash === '#users/roles/editor/x%' );
	box.location.hash = '#nosuch';
	shell.show( 'dashboard' );
	router.settle( 'dashboard' );
	check( '...and writes the panel on show where the hash names none', box.location.hash === '#dashboard' );

	shell.show( 'users' );
	router.go( 'users', [ 'ada@example.com' ] );
	check( 'go() writes the same form: a mail address is a key, its at sign %40', box.location.hash === '#users/users/ada%40example.com' && router.href( 'users', [ 'ada@example.com' ] ) === box.location.hash );
}

done();
