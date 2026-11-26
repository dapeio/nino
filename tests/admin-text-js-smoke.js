/**
 *	Nino									A compact filesystembased php framework
 *	admin-text-js-smoke.js	DOM-light checks for the Text panel and its Keys tab
 *										(_admin/Nino/Modules/Text/assets): how keys are named and
 *										fall into rows, sections and fields, how they are searched,
 *										and what the Text panel does on the real shell
 *										(adminPage() of the harness), and the Keys tab on it too.
 *
 *										The groups of both tabs are the list of the workbench, with
 *										the same names and an address each; the language the
 *										texts are shown in is the workbench's own, there is no
 *										switch of it here. In the Text panel a group is the standard
 *										directory of its texts and ONE text is edited in the record
 *										over it: the working language first, the other languages
 *										below, saved one request for each language that was
 *										changed, none where nothing was. The Text Keys tab is the
 *										same on the real shell: a row of the list says how many keys
 *										it holds and how many are hidden, opens as a table of its
 *										keys, and ONE key is edited in the record over it - its text
 *										in every language and its settings - which saves what was
 *										changed and nothing else, in the order of the base: the text
 *										made shorter before a lowered limit, the shape by keys/save,
 *										each language by keys/savebatch, asks once for what a
 *										narrower format or a global key drops, says at the field
 *										which part a server turned down, and reads the keys again.
 *										A key is renamed in a dialog over the record and made in the
 *										same record, each with an address; the scan of the templates
 *										is a view of its own beside them.
 *
 *	Usage: node tests/admin-text-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');
const { check, done, fills, load, shellRouter, adminPage, element, findAll, fire } = require( './harness.js' );

// What the panels write into the address goes through the router's own codec: every state written, every link drawn, is read back
const codec = shellRouter();

// ============================================================================
// Finding and reading texts: the names of a key, the rows they fall into, the
// search, and the two panels that draw them
// ============================================================================

// The words of the workbench in one language, as the fills the panels ask for -
// read from the files themselves, so what is checked is what ships
// The rows of a list are the targets that open them; a row's key is on the <li> two levels above its target
function opens( list ) {
	return list.querySelectorAll('.nino-admin-row-open');
}

function keyOf( target ) {
	return target.parentNode.parentNode.dataset.row;
}

function workbenchWords( locale ) {
	const out = {};
	for( const file of [ '_admin/text/', '_admin/Nino/Modules/Text/text/' ] )
		Object.assign( out, fills( file+ locale+ '.php' ) );
	return out;
}

// A dom of plain objects, enough to draw the panels into and read them back
function fakeDom() {

	const matchOne = ( node, selector ) => {
		const parts = selector.trim().match( /(#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]|^[a-z0-9]+)/g ) ?? [];
		return parts.length > 0 && parts.every( part => {
			if( part[0] === '#' ) return node.id === part.slice( 1 );
			if( part[0] === '.' ) return node.classList.contains( part.slice( 1 ) );
			if( part[0] === '[' ) {
				const m = /\[([\w-]+)(?:="([^"]*)")?\]/.exec( part );
				const value = m[1].startsWith('data-') ? node.dataset[m[1].slice( 5 )] : ( node.attrs[m[1]] ?? ( node[m[1]] === '' ? undefined : node[m[1]] ) );
				return m[2] === undefined ? value !== null && value !== undefined : String( value ) === m[2];
			}
			return node.tagName === part;
		} );
	};
	// A selector may be a path - "legend code": every part matches an ancestor, in order
	const matchPath = ( node, selector ) => {
		const parts = selector.trim().split( /\s+/ );
		if( matchOne( node, parts.pop() ) === false )
			return false;
		let up = node.parentNode;
		while( parts.length > 0 ) {
			const want = parts[parts.length - 1];
			while( up && matchOne( up, want ) === false ) up = up.parentNode;
			if( ! up ) return false;
			parts.pop();
			up = up.parentNode;
		}
		return true;
	};
	const matches = ( node, selector ) => selector.split(',').some( one => matchPath( node, one ) );
	const walk = ( node, out = [] ) => { node.children.forEach( child => { out.push( child ); walk( child, out ) } ); return out };

	// The element that has the focus: a page has one, and loses it with the element
	let held = null;

	const make = tag => {
		let classes = new Set();
		const node = {
			tagName : tag, children : [], parentNode : null, attrs : {}, dataset : {}, listeners : {}, style : {},
			id : '', value : '', checked : false, disabled : false, hidden : false, type : '', href : '', readOnly : false, required : false, selected : false, focused : false, _text : '',
			classList : {
				add : ( ...k ) => k.forEach( c => classes.add( c ) ), remove : ( ...k ) => k.forEach( c => classes.delete( c ) ),
				contains : k => classes.has( k ), toggle : ( k, on ) => { if( on === undefined ? ! classes.has( k ) : on ) classes.add( k ); else classes.delete( k ); return classes.has( k ) },
			},
			get className() { return [ ...classes ].join(' ') }, set className( v ) { classes = new Set( String( v ).split( /\s+/ ).filter( Boolean ) ) },
			get textContent() { return this._text + this.children.map( c => c.textContent ).join('') }, set textContent( v ) { this.children = []; this._text = String( v ) },
			get innerHTML() { return '' }, set innerHTML( v ) { this.children.forEach( c => c.parentNode = null ); this.children = []; this._text = ''; String( v ).split( /(<[^>]+>)/ ).forEach( p => { const m = /^<(\w+)[^>]*>$/.exec( p ); if( m ) this.appendChild( make( m[1] ) ); else if( p.indexOf( '</' ) !== 0 ) this._text += p } ) },
			setAttribute( k, v ) { this.attrs[k] = String( v ) }, getAttribute( k ) { return this.attrs[k] ?? null }, hasAttribute( k ) { return k in this.attrs }, removeAttribute( k ) { delete this.attrs[k] },
			get isConnected() { for( let n = this; n; n = n.parentNode ) if( n === root ) return true; return false },
			appendChild( c ) { if( c.parentNode ) c.remove(); c.parentNode = this; this.children.push( c ); return c },
			removeChild( c ) { c.remove(); return c },
			after( other ) { const at = this.parentNode.children.indexOf( this ); other.remove(); other.parentNode = this.parentNode; this.parentNode.children.splice( at + 1, 0, other ) },
			insertBefore( c, ref ) { if( c.parentNode ) c.remove(); c.parentNode = this; const at = ref === null || ref === undefined ? this.children.length : this.children.indexOf( ref ); this.children.splice( at, 0, c ); return c },
			contains( other ) { for( let n = other; n; n = n.parentNode ) if( n === this ) return true; return false },
			get firstChild() { return this.children[0] ?? null },
			remove() { if( this.parentNode ) this.parentNode.children.splice( this.parentNode.children.indexOf( this ), 1 ); this.parentNode = null },
			replaceWith( other ) { const at = this.parentNode.children.indexOf( this ); other.parentNode = this.parentNode; this.parentNode.children.splice( at, 1, other ); this.parentNode = null },
			replaceChild( fresh, old ) { const at = this.children.indexOf( old ); fresh.parentNode = this; this.children.splice( at, 1, fresh ); old.parentNode = null; return old },
			addEventListener( type, fn, options ) {
				const own = this;
				const listener = options !== undefined && options.once === true ? ( ev => { own.listeners[type].splice( own.listeners[type].indexOf( listener ), 1 ); fn( ev ) } ) : fn;
				( this.listeners[type] = this.listeners[type] ?? [] ).push( listener );
			},
			// What is typed or chosen in a control is heard by everything round it, as in a page
			fire( type, ev = {} ) {
				const event = Object.assign( { target : this, preventDefault(){} }, ev );
				for( let n = this; n; n = n.parentNode ) {
					( n.listeners[type] ?? [] ).slice().forEach( fn => fn( event ) );
					if( type !== 'input' && type !== 'change' )
						break;
				}
			},
			click() { this.fire('click') },
			focus() { if( held !== null && held !== this ) held.focused = false; held = this; this.focused = true },
			scrollIntoView() { this.scrolled = true },
			querySelectorAll( selector ) { return walk( this ).filter( n => matches( n, selector ) ) },
			querySelector( selector ) { return this.querySelectorAll( selector )[0] ?? null },
			closest( selector ) { for( let n = this; n; n = n.parentNode ) if( matches( n, selector ) ) return n; return null },
		};
		// A select answers with the option that was chosen, as the real one does
		if( tag === 'select' )
			Object.defineProperty( node, 'value', {
				get() { if( this._value !== undefined ) return this._value; const chosen = this.children.find( o => o.selected === true ) ?? this.children[0]; return chosen ? chosen.value : '' },
				set( v ) { this._value = String( v ) },
			} );
		return node;
	};

	const root = make('body');
	const doc = {
		documentElement : make('html'), body : root, createElement : make,
		createTextNode : text => Object.assign( make('#text'), { _text : String( text ) } ),
		createElementNS : ( space, tag ) => make( tag ),
		getElementById : id => walk( root ).find( n => n.id === id ) ?? null,
		querySelector : selector => root.querySelector( selector ), querySelectorAll : selector => root.querySelectorAll( selector ),
		get activeElement() { return held !== null && held.isConnected === true ? held : null },
		addEventListener(){}, removeEventListener(){},
	};

	return { make, doc, root, walk };
}

// One page of the workbench with the scripts of the Text module loaded, in one
// interface language, its server stubbed: what the panels do is read from the dom
function world( locale, hash, session ) {

	const dom = fakeDom();
	const words = workbenchWords( locale );
	const box = { console : console, document : dom.doc };
	box.window = box;
	box.Event = function( type ) { this.type = type };
	const calls = [];
	// The counters of the shell (script.js): what the panels told it they wrote, and what they read
	const stamps = { templates : 0, texts : 0 }, touched = [], registry = {}, counts = { refresh : 0, guard : 0 };
	box.Nino = {
		editor : {}, events : { bindCallback(){} },
		content : { getText : key => words[key] ?? '' },
		admin : {
			touch : topic => { touched.push( topic ); stamps[topic] = ( stamps[topic] ?? 0 ) + 1 },
			stamp : topic => stamps[topic] ?? 0,
			router : { set : ( p, parts ) => { codec.check( p, parts ); calls.push( 'set '+ [ p ].concat( parts ).join('/') ) }, go : ( p, parts ) => { codec.check( p, parts ); calls.push( 'go '+ [ p ].concat( parts ).join('/') ) }, word : codec.word, wordOf : codec.wordOf, isWord : codec.isWord, current : () => hash ?? { panel : '', parts : [] }, leave : ( n, l, proceed ) => proceed(), href : ( p, parts ) => codec.check( p, parts ) },
			// The one source of the content language: set() moves it and calls the views that follow it, as the shell does
			workLocale : ( followers => ( { current : session === undefined ? locale : session, follow : ( name, handler ) => followers.push( handler ), set : code => { calls.push( 'locale '+ code ); box.Nino.admin.workLocale.current = code; followers.forEach( handler => handler( code ) ) }, choose : code => box.Nino.admin.workLocale.set( code ) } ) )( [] ),
			// The shell's registry of unsaved input: what the panels register under their names, and how often they asked it to look again
			dirty : { entries : registry, register : ( name, entry ) => { registry[name] = entry }, refresh : () => { counts.refresh++ }, guard : ( names, proceed ) => { counts.guard++; proceed() } },
			// The rich-text editor, which keeps what it is given and says what a field marked it with
			htmlEditor : { create : ( mount, value ) => { mount.setAttribute( 'data-editor', 'yes' ); mount.dataset.editor = 'yes'; let held = String( value ?? '' ); const marks = {}; return { getValue : () => held, setValue : v => { held = v }, focus(){}, mark : state => Object.assign( marks, state ), marks : marks, destroy(){} } } },
		},
	};

	const context = vm.createContext( box );
	[ '_admin/assets/Nino.admin.js', '_admin/Nino/Modules/Text/assets/textkeys.js', '_admin/Nino/Modules/Text/assets/admin.js' ]
		.forEach( file => load( file, context, file ) );

	const mount = ( ...ids ) => ids.map( id => { const el = dom.make('div'); el.id = id; dom.root.appendChild( el ); return el } );

	// What the shell draws round the levels of a panel: its name in the head, and the levels beside it
	const pane = ( panel, ...ids ) => {
		const el = dom.make('div'), head = dom.make('div'), title = dom.make('h2');
		el.dataset.panel = panel;
		head.className = 'admin-panel-head';
		title.className = 'admin-panel-title';
		head.appendChild( title );
		el.appendChild( head );
		dom.root.appendChild( el );
		return { title : title, levels : ids.map( id => { const level = dom.make('div'); level.id = id; el.appendChild( level ); return level } ) };
	};

	return { dom, box, calls, words, touched, stamps, registry, counts, Nino : box.Nino, T : box.Nino.admin.textKeys, U : box.Nino.adminUi, mount, pane };
}

// --- the names of a key ---------------------------------------------------------

{
	const de = world( 'de_DE' );
	const en = world( 'en_US' );

	check( 'describeKey: the four segments of a key of the grammar',
		JSON.stringify( de.U.describeKey( '/template/page-home/welcome/title' ) ) === '{"kind":"grammar","namespace":"template","category":"page-home","part":"welcome","name":"title"}' );
	check( '...and the list and the entry of a part with a hyphen', JSON.stringify( de.U.describeKey( '/feature/consent/category-necessary/name' ) ) === '{"kind":"grammar","namespace":"feature","category":"consent","part":"category-necessary","name":"name","list":"category","id":"necessary"}'
		&& de.U.describeKey( '/result-unsubscribe-requested' ).kind === 'free' && de.U.describeKey( '/feature/newsletter/result-unsubscribe-requested/title' ).id === 'unsubscribe-requested' );
	check( 'the details of a page, the uri read from the right: slashes and dots belong to it',
		JSON.stringify( de.U.describeKey( '/_nino/webpage/about/team/title' ) ) === '{"kind":"webpage","uri":"/about/team","field":"title"}'
		&& de.U.describeKey( '/_nino/webpage/.demo-catalogue/name' ).uri === '/.demo-catalogue' && de.U.describeKey( '/_nino/webpage/blog/uri' ).field === 'uri' );
	check( 'the name of a language: its code, unchanged', JSON.stringify( de.U.describeKey( '/_nino/locale/de_DE/name' ) ) === '{"kind":"locale","code":"de_DE"}' );
	check( 'anything else is free - a key somebody made up, one that is too short or too long, an old form, the legal link, a word of the workbench',
		[ '/website/contact/uri', '/home/plain', '/company/name', '/template/page-home/welcome', '/template/page-home/a/b/c', '/Template/page-home/a/b', '/template/page_home/a/b', '/_admin/common/word/title', '/nino/dir', 'x' ].every( key => de.U.describeKey( key ).kind === 'free' ) );

	// slugLabel: the vocabulary, a number, a compound, a humanized slug - in the language of the interface
	const label = ( w, slug ) => w.U.slugLabel( slug );
	check( 'slugLabel: a word of the vocabulary', label( de, 'intro' ) === 'Einleitung' && label( en, 'intro' ) === 'Introduction' && label( de, 'accept-all' ) === 'Alle akzeptieren' && label( de, 'line-height' ) === 'Zeilenhöhe' );
	check( '...<word>-<number>', label( de, 'item-3' ) === 'Eintrag 3' && label( en, 'item-3' ) === 'Item 3' );
	check( '...the entry of a list: the list and the entry, each a word', label( de, 'category-necessary' ) === 'Kategorie · Notwendig' && label( de, 'unit-day' ) === 'Einheit · Tag' && label( de, 'result-unsubscribe-requested' ) === 'Ergebnis · Abmeldung angefordert' );
	check( '...what the builder makes of an id and a property: the left side looked up if it is a word, humanized if not', label( de, 'cta-label' ) === 'Cta · Beschriftung' && label( de, 'hero-image-alt' ) === 'Hero image · Alternativtext' && label( de, 'button-label' ) === 'Schaltfläche · Beschriftung' );
	check( '...the category of a page nothing routes: "Seite" and what follows', label( de, 'page-posts' ) === 'Seite · Beiträge' && label( de, 'page-protected' ) === 'Seite · Geschützter Bereich' && label( de, 'page-newsletter-unsubscribe' ) === 'Seite · Newsletter-Abmeldung' );
	check( '...a word that is not in the vocabulary, humanized', label( de, 'tagline' ) === 'Tagline' && label( de, 'get-in-touch' ) === 'Get in touch' && label( en, 'tagline' ) === 'Tagline' );
	check( 'a group has a word of its own', label( de, 'frame-header' ) === workbenchWords( 'de_DE' )['/_admin/common/word/frame-header'] && label( de, 'mail-user' ) === 'Mail an den Besucher' && label( de, 'common' ) === 'Allgemeine Wörter' && label( de, 'company' ) === 'Unternehmen' && label( de, 'mail' ) === 'E-Mail-Versand' );
	check( 'humanize: capital first letter, hyphens and underscores as spaces', de.U.humanize( 'price_default' ) === 'Price default' && de.U.humanize( 'get-in-touch' ) === 'Get in touch' );

	// isWord: the rule admin-text-smoke holds the shipped keys to, as a function of the workbench - a word, <word>-<number>, or two words with a hyphen between them
	check( 'isWord: a word of the vocabulary, <word>-<number>, <word>-<word> - in either language', [ 'intro', 'section', 'section-2', 'item-3', 'accept-all', 'unit-day', 'button-label', 'category-necessary' ].every( slug => de.U.isWord( slug ) === true && en.U.isWord( slug ) === true ) );
	check( '...and nothing else: a word nobody made, a number after it, a made-up word beside a real one on either side, three words, a number alone, nothing', [ 'cta', 'cta-2', 'cta-button', 'button-cta', 'accept-all-now', 'item-3-x', '2', '', null, undefined ].every( slug => de.U.isWord( slug ) === false && en.U.isWord( slug ) === false ) );
}

// --- the rows, the sections and their order ------------------------------------------

const textEntry = ( key, values, extra ) => Object.assign( { key : key, global : '*' in values, values : values, html : false, format : 'plain', maxlength : 150, blacklisted : false, writable : true }, extra ?? {} );
const homeKeys = [
	textEntry( '/template/page-services/item-1/title', { de_DE : 'Beratung', en_US : 'Consulting' } ),
	textEntry( '/template/page-services/item-1/text', { de_DE : '<p>Wir beraten Sie gern</p>', en_US : 'We are happy to advise you' }, { html : true, format : 'blocks' } ),
	textEntry( '/template/page-services/item-2/title', { de_DE : 'Planung', en_US : 'Planning' } ),
	textEntry( '/template/page-services/intro/title', { de_DE : 'Leistungen', en_US : 'Services' } ),
	textEntry( '/template/page-services/intro/text', { de_DE : 'Das bieten wir an', en_US : '' } ),
	textEntry( '/template/page-services/legal/note', { de_DE : 'Alle Angaben ohne Gewähr', en_US : 'No guarantee' } ),
	textEntry( '/_nino/webpage/services/name', { de_DE : 'Leistungen', en_US : 'Services' } ),
	textEntry( '/_nino/webpage/services/title', { de_DE : 'Unsere Leistungen', en_US : 'Our services' } ),
	textEntry( '/_nino/webpage/services/description', { de_DE : 'Was wir können', en_US : 'What we can do' } ),
	textEntry( '/_nino/webpage/services/uri', { '*' : '/services' }, { blacklisted : true } ),
	textEntry( '/_nino/webpage/legal/name', { de_DE : 'Rechtliches', en_US : 'Legal' } ),
	textEntry( '/_nino/webpage/blog/name', { de_DE : 'Blog', en_US : 'Blog' } ),
	textEntry( '/_nino/webpage/blog/title', { de_DE : 'Unser Blog', en_US : 'Our blog' } ),
	textEntry( '/_nino/locale/de_DE/name', { '*' : 'Deutsch' } ),
	textEntry( '/_nino/locale/fr_FR/name', { '*' : 'fr_FR' } ),
	textEntry( '/project/company/general/name', { '*' : 'Acme' } ),
	textEntry( '/project/company/general/description', { de_DE : 'Wir bauen Dinge', en_US : 'We build things' } ),
	textEntry( '/project/company/contact/email', { '*' : 'mail@acme.test' } ),
	textEntry( '/project/company/contact/country', { de_DE : 'Deutschland', en_US : 'Germany' } ),
	textEntry( '/project/mail/color/backdrop', { '*' : '#fff' }, { blacklisted : true } ),
	textEntry( '/template/common/form/submit', { de_DE : 'Absenden', en_US : 'Submit' } ),
	textEntry( '/template/common/label/phone', { de_DE : 'Telefon', en_US : 'Phone' } ),
	textEntry( '/template/frame-header/navigation/label', { de_DE : 'Hauptnavigation', en_US : 'Main navigation' } ),
	textEntry( '/template/mail-user/intro/title', { de_DE : 'Danke für Deine Nachricht', en_US : 'Thanks for your message' } ),
	textEntry( '/module/form/info/success', { de_DE : 'Gesendet', en_US : 'Sent' } ),
	textEntry( '/feature/consent/category-necessary/name', { de_DE : 'Notwendig', en_US : 'Necessary' } ),
	textEntry( '/feature/consent/banner/title', { de_DE : 'Cookies', en_US : 'Cookies' } ),
	textEntry( '/website/contact/uri', { '*' : '/contact' } ),
	textEntry( '/_admin/elements/field/social/title', { '*' : 'Netzwerk' }, { blacklisted : true } ),
];
const homePages = [
	{ uri : '/services', httpUri : '/services', template : 'page-services', category : 'page-services', templateName : null },
	{ uri : '/legal', httpUri : '/legal', template : '', category : null, templateName : null },
];
const homeOrder = { '/template/page-services/intro/title' : 100, '/template/page-services/intro/text' : 140, '/template/page-services/item-1/title' : 200, '/template/page-services/item-1/text' : 240, '/template/page-services/item-2/title' : 300,
	'/feature/consent/banner/title' : 10, '/feature/consent/category-necessary/name' : 20 };
const homeData = ( extra ) => Object.assign( { entries : homeKeys.filter( e => e.blacklisted === false ), pages : homePages,
	templates : { 'page-services' : { file : 'page-services.tpl', name : null }, 'page-hello' : { file : 'page-hello.tpl', name : 'Hello World' } },
	features : { consent : 'Cookie-Einwilligung' }, order : homeOrder, locale : 'de_DE' }, extra ?? {} );

{
	const w = world( 'de_DE' );
	const model = w.T.build( homeData() );
	const labels = ids => ids.map( id => w.T.rowLabel( model, model.rows[id] ) );

	check( 'two blocks: the pages, then everything else', model.blocks.map( b => b.id ).join() === 'pages,general' );
	check( 'a page is a row named after its route in the language, the legal page - a template without a category - a row of its own, and neither is a row of the site\'s other texts',
		JSON.stringify( model.blocks[0].rows ) === '["template/page-services","_pages/legal"]' && labels( model.blocks[0].rows ).join() === 'Leistungen,Rechtliches' );
	check( 'the others are in groups, in this order: the project, the common words, the building blocks, modules, features, the system, the rest',
		model.blocks[1].groups.map( g => g.id ).join() === 'project,common,blocks,modules,features,system,other' );
	check( '...the project in the order of the company, the website, the mail; the blocks and modules by name',
		JSON.stringify( model.blocks[1].groups[0].rows ) === '["project/company"]' && labels( model.blocks[1].groups[2].rows ).join() === workbenchWords( 'de_DE' )['/_admin/common/word/frame-header']+ ',Mail an den Besucher' && labels( model.blocks[1].groups[3].rows ).join() === 'Formular' );
	check( '...a feature named after its manifest, the system as the words of the panel', labels( model.blocks[1].groups[4].rows ).join() === 'Cookie-Einwilligung' && labels( model.blocks[1].groups[5].rows ).join() === 'Sprachnamen,Seitenangaben ohne Seite' );
	check( 'a key somebody made up is a row by its first segment, in "Weitere" - the address of the contact page among them', JSON.stringify( model.blocks[1].groups[6].rows ) === '["website"]' && model.rowOf['/website/contact/uri'] === 'website' );
	check( 'the details of a page without a stored route - a feature\'s - are the system\'s row, a section for each', model.rowOf['/_nino/webpage/blog/name'] === '_nino/webpage'
		&& w.T.sections( model, model.rows['_nino/webpage'] ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join('+') ).join() === '/blog:Name im Menü+Seitentitel' );
	check( 'the names of the languages are the system\'s row too, a section for each language', w.T.sections( model, model.rows['_nino/locale'] ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join() ).join() === 'de_DE:Name,fr_FR:Name' );
	check( 'the words of the workbench are no row of the site\'s texts - nor does the Keys tab lose them, which asks for them', Object.keys( w.T.build( homeData( { entries : homeKeys } ) ).rowOf ).every( key => key.indexOf( '/_admin/' ) !== 0 )
		&& w.T.build( homeData( { entries : homeKeys, admin : true } ) ).rowOf['/_admin/elements/field/social/title'] === '_admin' );

	// A page: its details first, then its template in the order the template reads it
	const page = w.T.sections( model, model.rows['template/page-services'] );
	check( 'a page begins with the details of its route - name, title, description, in that order, the path small - and "uri" is not among them', page[0].label === 'Seitenangaben' && page[0].route === '/services' );
	check( '...the three fields of the route are Name im Menü, Seitentitel, Beschreibung für Suchmaschinen',
		page[0].fields.map( f => f.label ).join() === 'Name im Menü,Seitentitel,Beschreibung für Suchmaschinen' );
	check( 'then the sections in the order a template reads them - Einleitung (Titel, Text), Eintrag 1 (Titel, Text), Eintrag 2 - and what no template reads, in the alphabet',
		page.slice( 1 ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join('+') ).join() === 'Einleitung:Titel+Text,Eintrag 1:Titel+Text,Eintrag 2:Titel,Legal:Anmerkung' );

	// The project: general first, the fields that are the same in every language first in a section
	const company = w.T.sections( model, model.rows['project/company'] );
	check( 'in the project, "general" is the first section; in a section the fields that are the same in every language come first',
		company.map( s => s.id ).join() === 'general,contact' && company[0].fields.map( f => f.label+ ( f.entry.global ? '*' : '' ) ).join() === 'Name*,Beschreibung' && company[1].fields.map( f => f.label+ ( f.entry.global ? '*' : '' ) ).join() === 'E-Mail*,Land' );
	check( 'a feature follows the order its templates read: Banner before Kategorie · Notwendig', w.T.sections( model, model.rows['feature/consent'] ).map( s => s.label ).join() === 'Banner,Kategorie · Notwendig' );

	// Without the Routes module there are no pages: the templates are blocks, the details have no page
	const bare = w.T.build( homeData( { pages : null } ) );
	check( 'without pages there is no block of pages: the templates are building blocks and the details of a page belong to no page',
		bare.blocks.map( b => b.id ).join() === 'general' && bare.rows['template/page-services'].kind === 'block' && bare.rowOf['/_nino/webpage/services/name'] === '_nino/webpage' );
	// A project with the module and no stored page: its page templates are still pages
	const noRoutes = w.T.build( homeData( { pages : [] } ) );
	check( 'with the Routes module but no stored page route, a page-*.tpl is still a row of the pages and the details of a page are the system\'s',
		noRoutes.blocks.map( b => b.id ).join() === 'pages,general' && noRoutes.rows['template/page-services'].kind === 'page' && noRoutes.rowOf['/_nino/webpage/services/name'] === '_nino/webpage' );
	// ...and a template that no route includes but whose name is a page's is a page, with its own row
	const loose = w.T.build( homeData( { pages : [ homePages[1] ] } ) );
	check( 'a page-*.tpl with keys of its own and no route is a row of the pages, named by its category, in the pages after the ones with a route', loose.rows['template/page-services'].kind === 'page'
		&& w.T.rowLabel( loose, loose.rows['template/page-services'] ) === 'Page services' && JSON.stringify( loose.blocks[0].rows ) === '["_pages/legal","template/page-services"]' );

	// The name of a row, by its routes, its own name, its category
	const two = w.T.build( homeData( { pages : [ homePages[0], { uri : '/dienste', httpUri : '/dienste', template : 'page-services', category : 'page-services', templateName : null } ],
		entries : homeKeys.concat( [ textEntry( '/_nino/webpage/dienste/name', { de_DE : 'Dienste', en_US : 'Services' } ) ] ).filter( e => e.blacklisted === false ) } ) );
	check( 'two routes on one template are one row, named by both and with the details of each', w.T.rowLabel( two, two.rows['template/page-services'] ) === 'Leistungen · Dienste'
		&& w.T.sections( two, two.rows['template/page-services'] ).filter( s => s.label === 'Seitenangaben' ).map( s => s.route ).join() === '/dienste,/services' );
	check( 'a row is named in the language it is asked for', w.T.rowLabel( two, two.rows['template/page-services'], 'en_US' ) === 'Services · Services' );
	const hello = w.T.build( { entries : [ textEntry( '/template/page-hello/hero/title', { de_DE : 'Hallo' } ), textEntry( '/template/page-posts/list/title', { de_DE : 'Beiträge' } ), textEntry( '/template/page-2026-home/hero/title', { de_DE : 'Neu' } ) ],
		pages : [ homePages[0] ], templates : { 'page-hello' : { file : 'page-hello.tpl', name : 'Hello World' } }, locale : 'de_DE' } );
	check( 'a page without a route is named by what its template calls itself, else by its category: "Seite · Beiträge"',
		w.T.rowLabel( hello, hello.rows['template/page-hello'] ) === 'Hello World' && w.T.rowLabel( hello, hello.rows['template/page-posts'] ) === 'Seite · Beiträge' && w.T.rowLabel( hello, hello.rows['template/page-2026-home'] ) === 'Page 2026 home' );
	check( 'a row is summed up in the language: how many keys, the beginning of their texts', w.T.rowSummary( model.rows['project/company'], 'de_DE' ) === '(4) Acme, Wir bauen Dinge, mail@acme.test, Deutschland' );
}

// The demo catalogue is a route to a template that is no page-* and has no category: a page of the site, with a row of its own
{
	const w = world( 'de_DE' );
	const catalogue = { uri : '/.demo-catalogue', httpUri : '/.demo-catalogue', template : '.demo-catalogue', category : null, templateName : null };
	const model = w.T.build( homeData( { pages : homePages.concat( [ catalogue ] ),
		entries : homeKeys.filter( e => e.blacklisted === false ).concat( [ textEntry( '/_nino/webpage/.demo-catalogue/name', { de_DE : 'Katalog', en_US : 'Catalogue' } ), textEntry( '/_nino/webpage/.demo-catalogue/title', { de_DE : 'Katalog: Presets', en_US : 'Catalogue: presets' } ) ] ) } ) );
	check( 'the details of the demo catalogue are the row of a page - not the system\'s "page details without a page" - named by its route',
		model.rowOf['/_nino/webpage/.demo-catalogue/name'] === '_pages/.demo-catalogue' && model.rows['_pages/.demo-catalogue'].kind === 'page' && model.blocks[0].rows.includes('_pages/.demo-catalogue')
		&& w.T.rowLabel( model, model.rows['_pages/.demo-catalogue'] ) === 'Katalog' && model.rows['_nino/webpage'].entries.every( e => e.key.indexOf( '/.demo-catalogue/' ) === -1 ) );
	check( '...with the details of its route as the only section, the path not shown',
		w.T.sections( model, model.rows['_pages/.demo-catalogue'] ).map( s => s.label+ ':'+ s.fields.map( f => f.label ).join('+') ).join() === 'Seitenangaben:Name im Menü+Seitentitel' );
	const orphan = w.T.build( homeData( { pages : homePages } ) );
	check( 'without the route in the list - an older server, or the page deleted - its details are the system\'s, as before', orphan.rowOf['/_nino/webpage/blog/name'] === '_nino/webpage' );
}

// A part, a category or a first segment that is a member of Object.prototype is a name like any other
{
	const w = world( 'de_DE' );
	const entries = [ textEntry( '/project/shop/constructor/name', { de_DE : 'Konstrukteur' } ), textEntry( '/project/shop/constructor/title', { de_DE : 'Titel' } ), textEntry( '/constructor/x', { de_DE : 'Frei' } ),
		textEntry( '/toString/a/b', { de_DE : 'Zeichen' } ), textEntry( '/template/constructor/hero/title', { de_DE : 'Held' } ), textEntry( '/feature/constructor/hero/title', { de_DE : 'Feature' } ),
		textEntry( '/module/constructor/hero/title', { de_DE : 'Modul' } ), textEntry( '/_nino/webpage/constructor/name', { de_DE : 'Seite' } ) ];
	const model = w.T.build( { entries : entries, pages : [], templates : {}, features : {}, locale : 'de_DE' } );
	const everySection = Object.keys( model.rows ).map( id => w.T.sections( model, model.rows[id] ) );
	check( 'a row, a part or a category named constructor or toString is a row, a section and a field - the sections of every row are built',
		model.rowOf['/project/shop/constructor/name'] === 'project/shop' && model.rowOf['/constructor/x'] === 'constructor' && model.rowOf['/toString/a/b'] === 'toString' && everySection.length === Object.keys( model.rows ).length
		&& w.T.sections( model, model.rows['project/shop'] ).map( s => s.id ).join() === 'constructor' && w.T.sections( model, model.rows['project/shop'] )[0].fields.length === 2 );
	check( '...none of them is named by something Object.prototype has: the name of a category that is no template or feature is its word', w.T.rowLabel( model, model.rows['template/constructor'] ) === 'Constructor'
		&& w.T.rowLabel( model, model.rows['feature/constructor'] ) === 'Constructor' && w.T.rowLabel( model, model.rows['module/constructor'] ) === 'Constructor' );
	check( '...and the search finds them, for any query', [ '', 'a', 'konstrukteur', 'constructor', '/toString' ].every( q => Array.isArray( w.T.search( model, { query : q, locale : 'de_DE' } ) ) ) && w.T.search( model, { query : 'konstrukteur', locale : 'de_DE' } ).length === 1
		&& w.T.search( model, { query : '', locale : 'de_DE' } ).length === entries.length );
	check( '...and an address that names them resolves', w.T.resolve( model, [ 'project/shop', '/project/shop/constructor/name' ] ).row === 'project/shop' && w.T.resolve( model, [ 'constructor' ] ).row === 'constructor' && w.T.resolve( model, [ 'toString' ] ).row === 'toString' );
}

// --- the search ------------------------------------------------------------------------

{
	const w = world( 'de_DE' );
	const model = w.T.build( homeData() );
	const search = ( query, extra ) => w.T.search( model, Object.assign( { query : query, locale : 'de_DE' }, extra ?? {} ) );
	const keysOf = hits => hits.map( h => h.item.field.entry.key );

	check( 'a word is found in the text of the language on screen, marked in the piece shown', ( () => { const hits = search( 'beraten' ); return hits.length === 1 && keysOf( hits )[0] === '/template/page-services/item-1/text'
		&& hits[0].locale === 'de_DE' && hits[0].snippet.filter( p => p.mark ).map( p => p.text ).join() === 'beraten'; } )() );
	check( '...in the text of another language, which the hit names', ( () => { const hits = search( 'consulting' ); return hits.length === 1 && hits[0].locale === 'en_US' && keysOf( hits )[0] === '/template/page-services/item-1/title'; } )() );
	check( '...in the key, in the name of the row, the section and the field', keysOf( search( 'banner' ) ).join() === '/feature/consent/banner/title' && keysOf( search( 'eintrag 2' ) ).join() === '/template/page-services/item-2/title'
		&& keysOf( search( workbenchWords( 'de_DE' )['/_admin/common/word/frame-header'].toLowerCase() ) ).join() === '/template/frame-header/navigation/label' && search( 'welcome' ).length === 0 );
	check( 'every word has to occur, in any part of what is searched, in any order', keysOf( search( 'titel eintrag 1' ) ).join() === '/template/page-services/item-1/title' && search( 'beraten consulting' ).length === 0 );
	check( 'accents and capitals do not matter - on either side', keysOf( search( 'GEWAHR' ) ).join() === '/template/page-services/legal/note' && keysOf( search( 'gewähr' ) ).join() === '/template/page-services/legal/note' && keysOf( search( 'DANKE fur' ) ).join() === '/template/mail-user/intro/title' );
	check( 'a word that begins with a slash is searched in the key alone', search( '/template/page-services' ).length === 6 && keysOf( search( '/common/' ) ).join() === '/template/common/form/submit,/template/common/label/phone'
		&& search( '/leistungen' ).length === 0 && keysOf( search( '/submit' ) ).join() === '/template/common/form/submit' );
	check( '...and may be combined with words that are searched everywhere - and the hits come in the order the rows show them', keysOf( search( '/template/page-services titel' ) ).join() === '/template/page-services/intro/title,/template/page-services/item-1/title,/template/page-services/item-2/title' );
	check( 'the tags of a text are not searched, nor are the entities', search( 'strong' ).length === 0 && search( '<p>' ).length === 0 );
	check( 'a field this account may not write is found as well, and the words of the workbench are not', ( () => { const own = w.T.build( homeData( { entries : homeKeys.map( e => e.key === '/project/company/general/name' ? Object.assign( {}, e, { writable : false } ) : e ) } ) );
		return w.T.search( own, { query : 'acme', locale : 'de_DE' } ).length === 2 && w.T.search( own, { query : '/general/name', locale : 'de_DE' } ).length === 1 && w.T.search( own, { query : 'netzwerk', locale : 'de_DE' } ).length === 0 } )() );
	check( 'a search that finds nothing finds nothing, and no words at all finds every field', search( 'xyzzy' ).length === 0 && search( '' ).length === homeData().entries.length );
	check( 'the filter keeps the keys with no text in the language - the ones that are one per language, empty or not there', keysOf( search( '', { emptyIn : 'en_US' } ) ).join() === '/template/page-services/intro/text'
		&& keysOf( search( '', { emptyIn : 'de_DE' } ) ).length === 0 );
	check( '...and combines with a search', keysOf( search( 'leistungen', { emptyIn : 'en_US' } ) ).join() === '/template/page-services/intro/text' && search( 'planung', { emptyIn : 'en_US' } ).length === 0 );
	check( 'only the hidden keys, for the Keys tab, which has them in its rows', ( () => { const all = w.T.build( homeData( { entries : homeKeys, admin : true } ) );
		return w.T.search( all, { query : '', locale : 'de_DE', onlyHidden : true } ).map( h => h.item.field.entry.key ).sort().join() === '/_admin/elements/field/social/title,/_nino/webpage/services/uri,/project/mail/color/backdrop'; } )() );

	// A key that holds more than a page of hits: the list says so, 50 at a time, in order
	const many = w.T.build( { entries : Array.from( { length : 120 }, ( v, i ) => textEntry( '/project/many/item/n'+ String( i ).padStart( 3, '0' ), { de_DE : 'Treffer '+ i } ) ), locale : 'de_DE' } );
	const manyHits = w.T.search( many, { query : 'treffer', locale : 'de_DE' } );
	check( 'every hit is returned - the panel draws the first 50 and offers more', manyHits.length === 120 && manyHits[0].item.field.entry.key.endsWith( 'n000' ) && manyHits[119].item.field.entry.key.endsWith( 'n119' ) );

	// The piece of text: from the original, accents and capitals as written
	const snip = w.T.snippet( 'Über uns: Wir sind äußerst nett und bieten die Beratung an. '.repeat( 3 ), [ 'uber' ], 60 );
	check( 'a piece of a text is cut from the original, the word marked as it is written there, cut off with a dot of ellipsis',
		snip[0].mark === true && snip[0].text === 'Über' && snip.map( p => p.text ).join('').length <= 66 && snip[snip.length - 1].text.endsWith(' …') );
	check( '...and a hit in the middle of a long text begins with one', ( () => { const parts = w.T.snippet( 'a '.repeat( 100 )+ 'Zielwort '+ 'b '.repeat( 100 ), [ 'zielwort' ], 40 ); return parts[0].text.startsWith('… ') && parts.some( p => p.mark && p.text === 'Zielwort' ) } )() );
	check( 'plain: tags and entities gone, the end of a paragraph a space', w.T.plain( '<p>Eins</p><p>Zwei &amp; <strong>drei</strong>&nbsp;vier</p>' ) === 'Eins Zwei & drei vier' );

	// The hash
	check( 'resolve: a key of a row, a row, a row with a key it does not hold', JSON.stringify( w.T.resolve( model, [ 'template/page-services', '/template/page-services/item-1/title' ] ) ) === '{"row":"template/page-services","key":"/template/page-services/item-1/title"}'
		&& JSON.stringify( w.T.resolve( model, [ 'template/page-services' ] ) ) === '{"row":"template/page-services","key":null}'
		&& JSON.stringify( w.T.resolve( model, [ 'project/company', '/template/page-services/item-1/title' ] ) ) === '{"row":"project/company","key":null}' && w.T.resolve( model, [ 'nothing' ] ) === null && w.T.resolve( model, [] ) === null );
	check( '...what is no row and key is nothing: the segments of a row, the start of a key, a third part', w.T.resolve( model, [ 'template', 'page-services' ] ) === null && w.T.resolve( model, [ '/template/page-services/item-1/title' ] ) === null
		&& w.T.resolve( model, [ 'template/page-services', '/template/page-services/item-1/title', 'x' ] ) === null );
	check( '...the deep links of the concept: a text of the home page, the details of a page', w.T.resolve( model, [ 'template/page-services', '/template/page-services/intro/title' ] ).row === 'template/page-services'
		&& JSON.stringify( w.T.resolve( model, [ 'template/page-services', '/_nino/webpage/services/title' ] ) ) === '{"row":"template/page-services","key":"/_nino/webpage/services/title"}'
		&& w.T.resolve( model, [ '_nino/webpage', '/_nino/webpage/blog/name' ] ).row === '_nino/webpage' );
	check( 'hashParts: the row\'s id, and the key as it is - each one part, whatever slashes it holds', JSON.stringify( w.T.hashParts( 'template/page-services' ) ) === '["template/page-services"]' && JSON.stringify( w.T.hashParts( 'x', '/a/b/c' ) ) === '["x","/a/b/c"]' );
}
// --- the Text panel on the real shell: the list, the texts of a row as a directory, the record of one text -------------

// The words of the workbench in German, by their fills: what a check asks for is the fill, never the wording
const copies = list => list.map( e => Object.assign( {}, e, { values : Object.assign( {}, e.values ) } ) );
const D = workbenchWords('de_DE');
const W = key => D[key];
const F = ( key, ...params ) => { let at = 0; return W( key ).replace( /%[sdn]/g, token => at < params.length ? String( params[at++] ) : token ) };
const stem = key => W( key ).split('%')[0];
const group = id => W('/_admin/text/group/'+ id);

const textResponse = ( extra ) => Object.assign( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ), locales : [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true } ], selectedLocale : 'de_DE', order : homeOrder, pages : homePages,
	templates : homeData().templates, features : homeData().features }, extra ?? {} );

/** A page of the workbench with the Text panel open on a stubbed server: the keys the way the first level of the page is checked in the Keys part */
function openedText( response, hash, locale ) {
	const w = world( locale ?? 'de_DE', hash );
	const { title, levels : [ list, form ] } = w.pane( 'text', 'text-list', 'text-form' );
	title.textContent = 'Texte';
	form.classList.add('admin-hidden');
	w.Nino.adminUi.panelName = () => 'Texte';
	const text = w.Nino.admin.text;
	text._apiCall = ( endpoint, payload, callback ) => callback( 200, endpoint === 'keys' ? response : {} );
	text.init();
	return Object.assign( w, { list, form, title, text } );
}

// What the checks read the drawn page with
const has = ( el, name ) => el.classList.contains( name );
const byClass = ( root, name ) => findAll( root, el => has( el, name ) );
const tag = ( root, name ) => findAll( root, el => el.tagName === name.toUpperCase() );

// The home page of the checks: a text that is the same in every language, one the account may not write, one that is longer than its limit, one of several lines
const EXTRAS = () => [
	textEntry( '/template/page-services/intro/badge', { '*' : 'Neu' } ),
	textEntry( '/template/page-services/item-2/body', { de_DE : 'Zeile eins\nZeile zwei', en_US : '' } ),
	textEntry( '/template/page-services/legal/locked', { de_DE : 'Gesperrt', en_US : 'Locked' }, { writable : false } ),
	textEntry( '/template/page-services/legal/long', { de_DE : 'Dieser Satz ist länger als sein Limit erlaubt', en_US : '' }, { maxlength : 30 } ),
];
const fullResponse = ( extra ) => textResponse( Object.assign( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ).concat( EXTRAS() ) }, extra ?? {} ) );

const ROW = 'template/page-services';
const ORDER = [ '/_nino/webpage/services/name', '/_nino/webpage/services/title', '/_nino/webpage/services/description', '/template/page-services/intro/badge', '/template/page-services/intro/title', '/template/page-services/intro/text',
	'/template/page-services/item-1/title', '/template/page-services/item-1/text', '/template/page-services/item-2/title', '/template/page-services/item-2/body', '/template/page-services/legal/locked', '/template/page-services/legal/long', '/template/page-services/legal/note' ];
const TITLE = '/template/page-services/intro/title', INTRO = '/template/page-services/intro/text', LONG = '/template/page-services/legal/long', LOCKED = '/template/page-services/legal/locked', BADGE = '/template/page-services/intro/badge', BODY = '/template/page-services/item-2/body', RICH = '/template/page-services/item-1/text';
// What a field is named: the words of the span at its head (the text is a node of its own in the stand-in)
// All the words under an element (a stand-in's textContent is its own text only)
const allText = el => el.textContent+ el.children.map( allText ).join('');
const nameOf = el => { const span = tag( el, 'span' )[0]; return span.textContent+ span.children.map( c => c.textContent ).join('') };
const addressOf = ( row, key ) => '#text/text/'+ encodeURIComponent( row )+ ( key === undefined ? '' : '/'+ encodeURIComponent( key ) );

function textPage( response, options ) {

	options = options ?? {};

	const h = adminPage();
	const words = workbenchWords( options.words ?? 'de_DE' );
	const list = element('div'), form = element('div');
	const sent = [], told = [], touches = [], held = [], editors = [];
	const handle = { hold : false, refuse : {}, fail : null, sanitize : null, typed : null, limits : {}, keyLimits : {}, listed : 0 };

	list.id = 'text-list';
	form.id = 'text-form';
	form.classList.add('admin-hidden');
	h.page.appendChild( list );
	h.page.appendChild( form );
	h.show('text');
	h.router.start();
	h.Nino.content.getText = key => words[key] ?? '';
	h.ui.locales.names = { de_DE : 'Deutsch', en_US : 'English' };
	h.ui.locales.native = 'de_DE';
	h.admin.workLocale.current = options.work ?? 'de_DE';

	// The server: the keys it holds, written as the real one does - a text read as something else than what is stored now is not written unless it is forced
	const stored = ( key, locale ) => { const e = response.keys.find( one => one.key === key ); return e === undefined ? undefined : String( ( e.global === true ? e.values['*'] : e.values[locale] ) ?? '' ) };
	const serve = ( action, payload ) => {
		if( action === 'text/keys' ) {
			handle.listed++;
			return [ 200, JSON.parse( JSON.stringify( response ) ) ];
		}
		if( action === 'text/fills' )
			return [ 200, { fills : [ { key : '/project/company/general/name', notation : '[[/project/company/general/name]]', value : 'Acme' } ] } ];
		if( handle.fail !== null )
			return [ handle.fail.status, handle.fail.body ];
		const results = {};
		payload.items.forEach( item => {
			const entry = response.keys.find( one => one.key === item.key );
			const changed = item.force !== true && item.was !== undefined && stored( item.key, item.locale ) !== item.was;
			if( entry === undefined )
				results[item.key] = { ok : false, error : 'unknown key' };
			else if( changed === true )
				results[item.key] = { ok : false, error : 'changed' };
			else if( handle.refuse[item.key + ' ' + item.locale] !== undefined )
				results[item.key] = handle.refuse[item.key + ' ' + item.locale];
			else {
				const value = handle.sanitize === null ? item.value : handle.sanitize( item.value );
				entry.values[item.locale] = value;
				results[item.key] = handle.typed === null ? { ok : true, value : value } : { ok : true, value : value, typed : handle.typed( value ) };
				if( handle.limits[item.key + ' ' + item.locale] !== undefined )
					results[item.key].limit = handle.limits[item.key + ' ' + item.locale];
				if( handle.keyLimits[item.key + ' ' + item.locale] !== undefined )
					results[item.key].keyLimit = handle.keyLimits[item.key + ' ' + item.locale];
			}
		} );
		return [ 200, { results : results } ];
	};
	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		const payload = JSON.parse( data.data );
		sent.push( { endpoint : data.action.replace( 'text/', '' ), payload : payload } );
		const answer = () => { const [ status, body ] = serve( data.action, payload ); callback( { status : status, responseJSON : body } ) };
		if( handle.hold === true && data.action === 'text/savebatch' )
			held.push( answer );
		else
			answer();
	} };

	const say = h.ui.say;
	h.ui.say = function( words, opts ) { told.push( words ); return say.call( this, words, opts ) };
	const touch = h.admin.touch;
	h.admin.touch = function( topic, uri ) { touches.push( topic ); return touch.call( this, topic, uri ) };

	// Every address the panel writes or links to is read back by the router as the same screen and the same parts
	[ 'set', 'go', 'href' ].forEach( name => {
		const real = h.router[name];
		h.router[name] = function( screen, parts ) { codec.check( screen, parts ); return real.call( this, screen, parts ) };
	} );

	// The rich-text editor, which keeps what it is given and says what a field marked it with
	h.admin.htmlEditor = { create : ( mount, value, maxlength, rows, format ) => {
		mount.dataset.editor = 'yes';
		const box = element('div');
		box.setAttribute( 'contenteditable', 'true' );
		mount.appendChild( box );
		let kept = String( value ?? '' );
		const marks = {};
		const editor = { maxlength : maxlength, format : format, given : String( value ?? '' ), marks : marks, destroyed : false, mount : mount, box : box,
			getValue : () => kept, setValue : v => { kept = v }, focus(){}, mark : state => Object.assign( marks, state ), destroy : () => { editor.destroyed = true } };
		editors.push( editor );
		return editor;
	} };

	if( options.hash !== undefined )
		h.box.location.hash = options.hash;
	else
		h.router.set( 'text', [] );

	load( '_admin/Nino/Modules/Text/assets/textkeys.js', h.context );
	load( '_admin/Nino/Modules/Text/assets/admin.js', h.context );

	const panel = h.admin.text;
	const dialog = () => h.dialogs.filter( d => d.open ).pop() ?? null;
	const rows = () => findAll( form, el => el.tagName === 'TR' && el.dataset.row !== undefined );
	const w = { h, panel, list, form, sent, told, touches, held, editors, handle, response, dialog, rows,
		open : () => { panel.showCurrent(); return w },
		listRows : () => findAll( list, el => el.tagName === 'LI' && el.dataset.row !== undefined ),
		listRow : id => w.listRows().find( li => li.dataset.row === id ),
		linkOf : id => byClass( w.listRow( id ), 'nino-admin-row-open' )[0],
		lineOf : id => tag( w.listRow( id ), 'small' )[0].textContent,
		ids : () => rows().map( el => el.dataset.row ),
		row : key => rows().find( el => el.dataset.row === key ),
		cells : key => w.row( key ).children.filter( el => el.tagName === 'TD' ),
		heads : () => findAll( form, el => el.tagName === 'TH' && el.dataset.key !== undefined ),
		selects : () => tag( form, 'select' ).filter( el => has( el, 'nino-admin-filterbar-select' ) && has( el, 'nino-admin-table-sortby' ) === false ),
		search : () => tag( form, 'input' ).find( el => el.type === 'search' ),
		listSearch : () => tag( list, 'input' ).find( el => el.type === 'search' ),
		pencil : key => byClass( w.row( key ), 'nino-admin-row-actions' )[0]?.children[0],
		writes : () => sent.filter( c => c.endpoint === 'savebatch' ),
		titleOf : d => byClass( d, 'nino-admin-dialog-title' )[0]?.textContent ?? null,
		subtitleOf : d => byClass( d, 'nino-admin-dialog-subtitle' )[0]?.textContent ?? '',
		fieldOf : ( d, code ) => findAll( d, el => el.dataset.locale === code )[0],
		fields : d => findAll( d, el => el.dataset.locale !== undefined ),
		controlOf : ( d, code ) => findAll( w.fieldOf( d, code ), el => el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' )[0],
		counterOf : ( d, code ) => byClass( w.fieldOf( d, code ), 'nino-admin-char-counter' )[0],
		errorsOf : d => byClass( d, 'nino-admin-field-error' ),
		button : ( d, key ) => tag( d, 'button' ).find( b => b.textContent === W( key ) ),
		lineText : d => byClass( d, 'nino-admin-status' )[0]?.textContent ?? '',
		type : ( d, code, value ) => {
			const control = w.controlOf( d, code );
			control.value = value;
			// What is typed is heard by everything round the control, as in a page
			for( let up = control; up !== null && up !== undefined; up = up.parentNode )
				fire( up, 'input', { target : control } );
			h.dirty.refresh();
		},
		save : d => fire( tag( d, 'form' )[0], 'submit' ),
		openRow : key => { const p = w.pencil( key ); fire( p, 'click', { currentTarget : p } ); return dialog() },
		cross : d => tag( d, 'button' ).find( b => has( b, 'nino-admin-dialog-close' ) ),
		close : d => { const asked = h.went.length; fire( w.cross( d ), 'click' ); if( h.went.length > asked && dialog() === null ) w.back() },
		back : () => { h.back(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		step : address => { h.box.location.hash = address; h.router.arrive(); panel.showCurrent(); h.hashchange() },
		go : code => { h.admin.workLocale.current = code; h.admin.workLocale._followers.forEach( follower => follower.handler( code ) ) },
		asking : () => { const top = dialog(); return top !== null && has( top, 'nino-admin-record' ) === false ? top : null },
		answerAsk : key => { const top = dialog(); const asked = h.went.length; fire( tag( top, 'button' ).find( b => b.textContent === W( key ) ), 'click' ); if( h.went.length > asked && dialog() === null ) w.back(); return top },
		external : ( key, locale, value ) => { const e = response.keys.find( one => one.key === key ); e.values[e.global === true ? '*' : locale] = value },
		hash : () => h.hash(),
	};

	return w;
}

// The page, shown and its keys read: the list is up, nothing is open
const shown = ( response, options ) => textPage( response ?? fullResponse(), options ).open();
// ... and a row of it open
const inRow = ( id, response, options ) => { const w = shown( response, options ); fire( w.linkOf( id ), 'click', { target : w.linkOf( id ) } ); return w };

{
	const w = shown();

	check( 'the first read asks for the keys, once, and for nothing else', w.sent.length === 1 && w.sent[0].endpoint === 'keys' && JSON.stringify( w.sent[0].payload ) === '{}' );
	check( 'the list is two blocks: the pages, and the rest in groups - each with its heading', tag( w.list, 'h2' ).map( h => h.textContent ).join() === [ 'pages', 'general' ].map( group ).join()
		&& tag( w.list, 'h3' ).map( h => h.textContent ).join() === [ 'project', 'blocks', 'modules', 'features', 'system', 'other' ].map( group ).join() );
	check( '...a row for each page, named after the route, and one for each group of the others - the rows of the workbench\'s list', w.listRows().length === Object.keys( w.panel._model.rows ).length && w.listRows()[0].dataset.row === ROW
		&& w.listRows().slice( 0, 2 ).map( li => tag( li, 'strong' )[0].textContent ).join() === 'Leistungen,Rechtliches' );
	check( 'every row is a link to an address of its own, which writes it when it is pressed', w.listRows().every( li => byClass( li, 'nino-admin-row-open' )[0].href === addressOf( li.dataset.row ) ) );
	check( 'the bar of the list has the search and the filter and no switch of a language: the language is the workbench\'s', w.listSearch().attributes['aria-label'] === W('/_admin/text/label/search') && tag( w.list, 'select' ).length === 0
		&& findAll( w.list, el => el.id === 'text-list-empty' ).length === 1 );
	check( '...and the filter names the language the way the workbench does, and is off', findAll( w.list, el => el.id === 'text-list-empty' )[0].textContent === F('/_admin/text/label/empty-in','Deutsch') && findAll( w.list, el => el.id === 'text-list-empty' )[0].attributes['aria-pressed'] === 'false' );
	check( 'the words of the workbench are not among the rows', w.listRows().every( li => li.dataset.row !== '_admin' ) );
	check( 'the page is at the address of the list, and the table of a row is not shown', w.hash() === '#text' && has( w.form, 'admin-hidden' ) === true && has( w.list, 'admin-hidden' ) === false );

	// P1: the second line of a row is counts in place of running text
	check( 'the second line of a row is how many texts it holds - and no running text, and nothing about a text over its limit although the row has one', w.lineOf( ROW ) === F('/_admin/text/count/texts',13) && w.lineOf( ROW ).indexOf( 'Beratung' ) === -1
		&& w.panel._model.rows[ROW].entries.some( e => w.h.admin.textKeys.lengthOf( e, w.h.admin.textKeys.valueOf( e, 'de_DE' ) ) > e.maxlength ) );
	check( '...a row that has nothing empty says only how many', w.lineOf('project/company') === F('/_admin/text/count/texts',4) && w.lineOf('template/common') === F('/_admin/text/count/texts',2) );
	check( '...one text is the singular, in the words of the panel', w.lineOf('template/frame-header') === F('/_admin/text/count/text',1) && W('/_admin/text/count/text') !== W('/_admin/text/count/texts') );
	check( '...a text that is the same in every language is never empty in one', w.panel._model.rows['project/company'].entries.some( e => e.global === true ) && w.lineOf('project/company').indexOf(' leer in ') === -1 );

	// the counts follow the working language
	w.go( 'en_US' );
	check( 'the counts follow the working language: the texts that are empty in it, named in it', w.lineOf( ROW ) === F('/_admin/text/count/texts',13)+ ' · '+ F('/_admin/text/count/empty',3,'English')
		&& tag( w.listRow( ROW ), 'strong' )[0].textContent === 'Services' && findAll( w.list, el => el.id === 'text-list-empty' )[0].textContent === F('/_admin/text/label/empty-in','English') );
	w.go( 'de_DE' );
	check( '...and back', w.lineOf( ROW ) === F('/_admin/text/count/texts',13) );
}

{
	// the characters are counted the way the field counts: the characters of a plain text, the visible characters of a text with markup
	const w = shown( fullResponse( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ).concat( [
		textEntry( '/template/page-hello/hero/rich', { de_DE : '<p><strong>abcdefghij</strong></p>', en_US : '' }, { html : true, format : 'blocks', maxlength : 20 } ),
		textEntry( '/template/page-hello/hero/rich-long', { de_DE : '<p>abcdefghijklm</p><p>nopqrstuvw</p>', en_US : '' }, { html : true, format : 'blocks', maxlength : 20 } ),
		textEntry( '/template/page-hello/hero/plain', { de_DE : 'abcdefghijklmnopqrstu', en_US : '' }, { maxlength : 20 } ),
		textEntry( '/template/page-hello/hero/exact', { de_DE : 'abcdefghijklmnopqrst', en_US : '' }, { maxlength : 20 } ),
		textEntry( '/template/page-hello/hero/entity', { de_DE : '<p>a&amp;b&nbsp;c</p>', en_US : '' }, { html : true, format : 'blocks', maxlength : 4 } ),
	] ) } ) );
	const U = w.h.admin.textKeys;
	check( 'a text with markup is counted by what a person sees: the tags are no characters, the entities are one each, nothing is collapsed', U.lengthOf( { html : true }, '<p><strong>abcdefghij</strong></p>' ) === 10 && U.lengthOf( { html : true }, '<p>a&amp;b&nbsp;c</p>' ) === 5
		&& U.lengthOf( { html : true }, '<p>abc</p><p>def</p>' ) === 6 && U.lengthOf( { html : false }, '<p>abc</p>' ) === 10 );
	check( 'the line of a row with texts over their limit says only how many texts it holds', w.lineOf('template/page-hello') === F('/_admin/text/count/texts',5) );
	check( '...and, for the language on screen, how many of them are empty: the other has none of them', ( () => { w.go( 'en_US' ); const line = w.lineOf('template/page-hello'); w.go( 'de_DE' ); return line === F('/_admin/text/count/texts',5)+ ' · '+ F('/_admin/text/count/empty',5,'English') } )() );
}

{
	// the search: hits, the language of a hit, the filter - the list is the list
	const w = shown();
	const box = w.listSearch();
	const type = value => { box.value = value; fire( box, 'input', { target : box } ) };

	type('beraten');
	check( 'a search replaces the rows by hits: the path, the piece of text with the word marked, the key behind it', w.listRows().length === 1 && tag( w.list, 'strong' )[0].textContent === 'Leistungen › Eintrag 1 › Text' && tag( w.list, 'mark' )[0].textContent === 'beraten'
		&& byClass( w.list, 'admin-text-hit-key' )[0].textContent === RICH && tag( w.list, 'small' )[0].textContent.startsWith( 'Wir ' ) );
	check( '...each a link to the text, at its own address - the row and the key, each one part', byClass( w.list, 'nino-admin-row-open' )[0].href === addressOf( ROW, RICH ) && addressOf( ROW, RICH ) === '#text/text/template%2Fpage-services/%2Ftemplate%2Fpage-services%2Fitem-1%2Ftext' );
	check( '...and says how many there are, where a screen reader hears it', byClass( w.list, 'admin-text-count' )[0].textContent === F('/_admin/text/msg/result',1) && byClass( w.list, 'admin-text-count' )[0].attributes['aria-live'] === 'polite' );
	check( 'a hit in the language on screen has no code of a language', byClass( w.list, 'nino-admin-row-actions' ).length === 0 );
	type('xyzzy');
	check( 'nothing found says so, with the empty state of the workbench', w.listRows().length === 0 && byClass( w.list, 'nino-admin-empty' )[0].textContent === W('/_admin/text/msg/nothing') );
	type('');
	check( 'an empty box is the rows again', w.listRows().length === Object.keys( w.panel._model.rows ).length && byClass( w.list, 'admin-text-count' )[0].textContent === '' );

	type('consulting');
	const code = tag( byClass( w.list, 'nino-admin-row-actions' )[0], 'button' )[0];
	check( 'a hit in another language shows that language\'s code as a small button of the workbench, named for what it does', code.textContent === 'en_US' && has( code, 'nino-admin-btn--small' ) && code.attributes['aria-label'] === F('/_admin/text/label/switch-language','English') );
	const chosen = [];
	const choose = w.h.admin.workLocale.choose;
	w.h.admin.workLocale.choose = c => chosen.push( c );
	fire( code, 'click' );
	check( '...which asks the workbench for it - as every change of the language is, so that a view with unsaved input is asked about first - and opens nothing', chosen.join() === 'en_US' && w.dialog() === null && has( w.form, 'admin-hidden' ) === true );
	w.h.admin.workLocale.choose = choose;
	type('');

	w.go( 'en_US' );
	fire( findAll( w.list, el => el.id === 'text-list-empty' )[0], 'click' );
	check( 'the filter for the texts with no text in the language shows them as hits, and says it is on', findAll( w.list, el => el.id === 'text-list-empty' )[0].attributes['aria-pressed'] === 'true'
		&& byClass( w.list, 'admin-text-hit-key' ).map( k => k.textContent ).sort().join() === [ INTRO, LONG, BODY ].sort().join() );
	fire( findAll( w.list, el => el.id === 'text-list-empty' )[0], 'click' );
	w.go( 'de_DE' );

	// P3: a hit opens the record of its text
	type('beraten');
	fire( byClass( w.list, 'nino-admin-row-open' )[0], 'click', { target : byClass( w.list, 'nino-admin-row-open' )[0] } );
	const d = w.dialog();
	check( 'a hit opens the record of its text, over the table of its row, at the address of the text', d !== null && w.titleOf( d ) === 'Eintrag 1 › Text' && w.subtitleOf( d ) === RICH && w.hash() === addressOf( ROW, RICH ) && has( w.form, 'admin-hidden' ) === false && has( w.list, 'admin-hidden' ) === true );
}

{
	// more hits than a page: fifty, then "Weitere anzeigen", fifty at a time
	const many = Array.from( { length : 120 }, ( v, i ) => textEntry( '/project/many/item/n'+ String( i ).padStart( 3, '0' ), { de_DE : 'Treffer '+ i, en_US : '' } ) );
	const w = shown( textResponse( { keys : many, pages : [], order : {} } ) );
	const box = w.listSearch();
	const type = value => { box.value = value; fire( box, 'input', { target : box } ) };
	const more = () => tag( w.list, 'button' ).find( b => b.textContent === W('/_admin/text/label/more') );
	type('treffer');
	check( 'a search with many hits draws fifty and offers more', w.listRows().length === 50 && more() !== undefined && byClass( w.list, 'admin-text-count' )[0].textContent === F('/_admin/text/msg/results',120) );
	fire( more(), 'click' );
	check( '...fifty more', w.listRows().length === 100 && more() !== undefined );
	fire( more(), 'click' );
	check( '...until there are no more, and no button', w.listRows().length === 120 && more() === undefined );
	type('treffer 119');
	check( 'typing again begins at the first fifty', w.listRows().length === 1 && byClass( w.list, 'admin-text-count' )[0].textContent === F('/_admin/text/msg/result',1) );
	w.go( 'en_US' );
	fire( findAll( w.list, el => el.id === 'text-list-empty' )[0], 'click' );
	type('');
	check( 'the filter alone is a list of hits as well: 50 of the 120 texts that are empty in English', w.listRows().length === 50 && byClass( w.list, 'admin-text-count' )[0].textContent === F('/_admin/text/msg/results',120) );
}

{
	// without the Routes module there are no pages, and the list has no block of them
	const w = shown( textResponse( { pages : null } ) );
	check( 'without pages the list has the one block, and the details of a page are among the system\'s rows', tag( w.list, 'h2' ).map( h => h.textContent ).join() === group('general') && w.listRows().some( li => li.dataset.row === '_nino/webpage' ) );
}

// --- the texts of a row: the standard directory -------------------------------------------------------------------------

{
	const w = inRow( ROW );

	check( 'a row opens as its own view at its address, under the way back and the name of the row - one link, to the panel by its name', w.hash() === addressOf( ROW ) && has( w.form, 'admin-hidden' ) === false && has( w.list, 'admin-hidden' ) === true
		&& byClass( w.form, 'nino-admin-back-link' ).length === 1 && byClass( w.form, 'nino-admin-back-link' )[0].textContent === F('/_admin/common/label/back-to',W('/_admin/text/label/back-name')) && byClass( w.form, 'nino-admin-contextbar-title' )[0].textContent === 'Leistungen' );
	check( '...and under it the standard directory: no list bar, no box to choose rows by, one directory', byClass( w.form, 'nino-admin-directory' ).length === 1 && byClass( w.form, 'nino-admin-list-actions' ).length === 0
		&& tag( w.form, 'input' ).filter( el => el.type === 'checkbox' ).length === 0 );
	// P4: the order is the templates'
	check( 'the rows are the texts of the row in the order of its sections - the details of the page first - and of the templates, a text that is the same in every language first in its section', w.ids().join() === ORDER.join() );
	check( '...the columns are the name, the section, the text, the characters and the languages', w.heads().map( el => el.dataset.key ).join() === 'name,place,text,chars,states' );
	check( '...none of them sorts: the order is the content', w.heads().every( el => el.attributes['aria-sort'] === undefined || el.attributes['aria-sort'] === 'none' ) && tag( w.form, 'th' ).every( th => tag( th, 'button' ).length === 0 ) );
	check( '...the heads say what they hold, the one of the text in the language on screen', w.heads().map( el => el.textContent ).join() === [ W('/_admin/text/col/name'), W('/_admin/text/col/section'), F('/_admin/text/col/text','Deutsch'), W('/_admin/text/col/chars'), W('/_admin/text/col/languages') ].join() );

	// the cells
	const title = w.cells( TITLE );
	check( 'a row says the name of the text strong, its key under it in the face of an identifier, as text', byClass( title[0], 'admin-text-name-label' )[0].tagName === 'STRONG' && byClass( title[0], 'admin-text-name-label' )[0].textContent === 'Titel' && byClass( title[0], 'admin-text-name-key' )[0].textContent === TITLE );
	check( '...the section by its name - for the details of a page with the path - and the text of the working language, clamped, as plain text', title[1].textContent === 'Einleitung' && w.cells('/_nino/webpage/services/name')[1].textContent === W('/_admin/text/label/page-details')+ ' (/services)'
		&& byClass( title[2], 'nino-admin-table-clamp' )[0].textContent === 'Leistungen' && byClass( w.cells( RICH )[2], 'nino-admin-table-clamp' )[0].textContent === 'Wir beraten Sie gern' );
	check( '...the characters against the limit, counted as the field counts them: the text with markup by what is seen', byClass( title[3], 'nino-admin-table-limit' )[0].textContent === '10 / 150' && byClass( w.cells( RICH )[3], 'nino-admin-table-limit' )[0].textContent === '20 / 150' );
	// P5
	check( '...a text over its limit says the word, as a badge that warns - the number is the characters it holds', ( () => { const cell = byClass( w.cells( LONG )[3], 'nino-admin-table-limit' )[0]; const badge = byClass( cell, 'nino-admin-badge' )[0];
		return cell.textContent.startsWith( '45 / 30' ) && cell.dataset.over === 'true' && badge.textContent === W('/_admin/text/col/long') && badge.dataset.state === 'warn' } )() && byClass( title[3], 'nino-admin-badge' ).length === 0 );
	// P6
	const states = key => byClass( w.cells( key )[4], 'nino-admin-badge' ).map( b => b.textContent+ ':'+ b.dataset.state );
	check( 'a badge for each language of the texts - a tick where it has a text, a dash where not, the sign and not the colour alone', states( TITLE ).join() === 'DE ✓:ok,EN ✓:ok' && states( INTRO ).join() === 'DE ✓:ok,EN –:warn' && states( LONG ).join() === 'DE ✓:ok,EN –:warn' );
	check( '...each named by its language in the tooltip, and a text that is the same in every language has the one badge that says so', byClass( w.cells( INTRO )[4], 'nino-admin-badge' ).map( b => b.title ).join() === 'Deutsch: '+ W('/_admin/text/col/lang-ok')+ ',English: '+ W('/_admin/text/col/lang-missing')
		&& states( BADGE ).join() === W('/_admin/text/label/all-languages')+ ' ✓:ok' );
	check( 'a markup-only text is empty in the language, as the panel says it: the badge', ( () => { const e = w.panel._model.rows[ROW].entries.find( x => x.key === INTRO ); const before = e.values.en_US; e.values.en_US = '<p>&nbsp;</p>'; const dash = w.panel._rows( ROW ).find( r => r.key === INTRO ).states.map( s => s.ok ).join(); e.values.en_US = before; return dash === 'true,false' } )() );

	// the pencil
	check( 'the pencil of a text is named for it, one for each text the account may write', w.pencil( TITLE ).attributes['aria-label'] === F('/_admin/text/row/open','Einleitung › Titel') && w.pencil( LOCKED ) === undefined && ORDER.filter( k => w.pencil( k ) !== undefined ).length === 12 );
	// P7
	fire( w.row( LOCKED ), 'click', { target : w.row( LOCKED ) } );
	check( 'a text the account may not change opens no record: a click on its row says so over the table, and nothing is sent', w.dialog() === null && byClass( w.form, 'nino-admin-notice' ).some( n => n.textContent === W('/_admin/text/error/denied') ) && w.writes().length === 0 );
	fire( w.row( TITLE ), 'click', { target : w.row( TITLE ) } );
	check( '...and a text it may change opens, which takes the notice away', w.dialog() !== null && byClass( w.form, 'nino-admin-notice' ).length === 0 );
}

{
	// the category, the select "Show", the search
	const w = inRow( ROW );
	const [ category, show ] = w.selects();

	check( 'the section is the category, left of the search: all sections, then the sections in their order, the details of a page with its path', category !== undefined && show !== undefined && w.selects().length === 2
		&& tag( category, 'option' ).map( o => o.textContent ).join() === [ W('/_admin/text/filter/sections'), W('/_admin/text/label/page-details')+ ' (/services)', 'Einleitung', 'Eintrag 1', 'Eintrag 2', 'Legal' ].join() );
	check( '...and one select "Show" after it: all texts, empty in the working language, empty in a language - three options, none for the texts that are too long', ( show.attributes['aria-label'] === W('/_admin/text/filter/show') || findAll( w.form, el => el.textContent === W('/_admin/text/filter/show') ).length > 0 )
		&& tag( show, 'option' ).map( o => o.textContent ).join() === [ W('/_admin/text/filter/all'), F('/_admin/text/label/empty-in','Deutsch'), W('/_admin/text/filter/empty-any') ].join() );
	const choose = ( select, value ) => { select.value = value; fire( select, 'change', { target : select } ) };

	choose( category, 'item-1' );
	check( 'a section narrows the rows to its texts, in their order', w.ids().join() === [ '/template/page-services/item-1/title', RICH ].join() );
	choose( category, '' );
	choose( show, 'any' );
	check( '...Show "empty in a language": the texts that have none in one language of the site - a text of every language never', w.ids().join() === [ INTRO, BODY, LONG ].join() );
	choose( show, 'empty' );
	check( '...Show "empty in <the working language>": none, in German', w.ids().length === 0 && byClass( w.form, 'nino-admin-empty' ).length === 1 );
	w.go( 'en_US' );
	check( '...the language of the table follows the working language: the head, the text, the words of the select - and what was chosen is kept', w.heads()[2].textContent === F('/_admin/text/col/text','English') && w.ids().join() === [ INTRO, BODY, LONG ].join()
		&& tag( w.selects()[1], 'option' ).map( o => o.textContent )[1] === F('/_admin/text/label/empty-in','English') && w.selects()[1].value === 'empty' && w.cells( INTRO )[2].textContent === '' );
	choose( w.selects()[1], '' );
	check( '...and its text is the one of that language', byClass( w.cells( TITLE )[2], 'nino-admin-table-clamp' )[0].textContent === 'Services' );
	w.go( 'de_DE' );

	// the search: the name, the key, the section, the text of every language
	const found = query => { const box = w.search(); box.value = query; fire( box, 'input', { target : box } ); return w.ids().join() };
	check( 'the search finds a text by its text in the language on screen', found( 'Beratung' ) === '/template/page-services/item-1/title' );
	check( '...by its text in another language', found( 'consulting' ) === '/template/page-services/item-1/title' && found( 'Locked' ) === LOCKED );
	check( '...by its name, its key and its section', found( 'eintrag 2' ) === [ '/template/page-services/item-2/title', BODY ].join() && found( 'legal/long' ) === LONG && found( 'Anmerkung' ) === '/template/page-services/legal/note' );
	check( '...and says it finds nothing in the words of the panel', found( 'zzz' ) === '' && byClass( w.form, 'nino-admin-empty' )[0].textContent === W('/_admin/text/msg/nothing') );
	found( '' );

	// the state is kept per row, while the panel lives
	choose( w.selects()[1], 'any' );
	fire( byClass( w.form, 'nino-admin-back-link' )[0], 'click' );
	check( 'Back is the list, the focus on the row that was open, the address the list\'s', has( w.list, 'admin-hidden' ) === false && w.hash() === '#text' && w.h.doc.activeElement === w.linkOf( ROW ) );
	fire( w.linkOf( ROW ), 'click', { target : w.linkOf( ROW ) } );
	check( 'the row opened again is as the person left it: the select "Show" holds what it held', w.ids().join() === [ INTRO, BODY, LONG ].join() && w.selects()[1].value === 'any' );
	fire( byClass( w.form, 'nino-admin-back-link' )[0], 'click' );
	fire( w.linkOf('template/frame-header'), 'click', { target : w.linkOf('template/frame-header') } );
	check( '...and another row has its own state: none, and no category where it has one section', w.ids().length === 1 && w.selects().length === 1 && w.selects()[0].value === '' );
}

{
	// text from the server is text: in the table, the head of the record, its subtitle and the plain fields; a rich text goes to the editor as it is
	const BAD = 'x &lt;img src=x onerror=alert(1)&gt; "quoted" [[/key]]';
	const SHOWN = 'x <img src=x onerror=alert(1)> "quoted" [[/key]]';
	const KEYBAD = '/project/<img src=x onerror=alert(2)>/"q"/n[[/key]]';
	const RICHBAD = '<p>Hallo <b>Welt</b> <img src=x onerror=alert(3)></p>';
	const response = textResponse( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ).concat( [
		textEntry( KEYBAD, { de_DE : BAD, en_US : BAD } ),
		textEntry( '/project/shop/xss/rich', { de_DE : RICHBAD, en_US : '' }, { html : true, format : 'blocks' } ),
	] ) } );
	const w = inRow( 'project', response );
	const cell = byClass( w.cells( KEYBAD )[2], 'nino-admin-table-clamp' )[0];
	// P22
	check( 'a text, a key and the names made of it that hold markup are text in the table - no element is made of any of them', cell.textContent === SHOWN && byClass( w.cells( KEYBAD )[0], 'admin-text-name-key' )[0].textContent === KEYBAD
		&& w.cells( KEYBAD )[1].textContent.indexOf( '<img src=x onerror=alert(2)>' ) !== -1 && findAll( w.form, el => el.tagName === 'IMG' || el.tagName === 'B' ).length === 0 );
	const d = w.openRow( KEYBAD );
	check( 'the head of the record has the place, the name and the key as text, and the fields hold the text as a value', w.titleOf( d ).indexOf( '<img src=x onerror=alert(2)>' ) !== -1 && w.subtitleOf( d ) === KEYBAD && w.controlOf( d, 'de_DE' ).value === BAD && w.controlOf( d, 'en_US' ).value === BAD
		&& findAll( d, el => el.tagName === 'IMG' || el.tagName === 'B' ).length === 0 );
	w.close( d );
	fire( byClass( w.form, 'nino-admin-back-link' )[0], 'click' );
	fire( w.linkOf('project/shop'), 'click', { target : w.linkOf('project/shop') } );
	const rich = w.openRow( '/project/shop/xss/rich' );
	check( 'a rich text is given to the editor as it is, which is the only thing that makes elements of it - nothing else of the record does', w.editors.length === 2 && w.editors[0].given === RICHBAD && findAll( rich, el => el.tagName === 'IMG' || el.tagName === 'B' ).length === 0 );
	w.close( rich );
	check( 'a table that shows a rich text shows its words, as text', byClass( w.cells('/project/shop/xss/rich')[2], 'nino-admin-table-clamp' )[0].textContent === 'Hallo Welt' );
}

// --- the record of one text ---------------------------------------------------------------------------------------------------

{
	const w = inRow( ROW );
	const position = w.h.index();
	const d = w.openRow( TITLE );

	// P8
	check( 'a pencil opens the record of that text, over the table: the place and the name in its head, the key under it, at its address as one step of the history', d !== null && has( d, 'nino-admin-record' ) && w.titleOf( d ) === 'Einleitung › Titel' && w.subtitleOf( d ) === TITLE
		&& w.hash() === addressOf( ROW, TITLE ) && w.h.index() === position + 1 && w.h.router.marked() === 'text' );
	check( '...the key is ONE part: the row and the key, each encoded alone', w.hash() === '#text/text/template%2Fpage-services/%2Ftemplate%2Fpage-services%2Fintro%2Ftitle' );
	check( '...the table stands under it as it was', w.ids().join() === ORDER.join() && byClass( w.form, 'nino-admin-directory' ).length === 1 );
	check( '...with the step to the previous and the next text, named in the words of the panel, and the place in the rows the table shows', tag( d, 'button' ).some( b => b.attributes['aria-label'] === W('/_admin/text/record/prev') ) && tag( d, 'button' ).some( b => b.attributes['aria-label'] === W('/_admin/text/record/next') )
		&& byClass( d, 'nino-admin-record-count' )[0].textContent === F('/_admin/common/record/position',5,12) );

	// the fields: the working language first and marked, then the others
	check( 'a field for each language of the text - the working language first, marked as it, then the other - each named by the name of its language', w.fields( d ).map( f => f.dataset.locale ).join() === 'de_DE,en_US'
		&& byClass( w.fieldOf( d, 'de_DE' ), 'nino-admin-field' ).length === 0 && nameOf( w.fieldOf( d, 'de_DE' ) ) === 'Deutsch ('+ W('/_admin/text/label/working')+ ')' && nameOf( w.fieldOf( d, 'en_US' ) ) === 'English' );
	check( '...all of them fields to type in, with what each language holds, and a counter against the limit under each', w.controlOf( d, 'de_DE' ).value === 'Leistungen' && w.controlOf( d, 'en_US' ).value === 'Services' && w.counterOf( d, 'de_DE' ).textContent === '10 / 150' && w.counterOf( d, 'en_US' ).textContent === '8 / 150'
		&& w.controlOf( d, 'de_DE' ).maxLength === 150 && w.controlOf( d, 'en_US' ).maxLength === 150 && w.controlOf( d, 'de_DE' ).readOnly !== true );
	check( '...a line, for a text of a line - and no key under the field: the head says it', w.controlOf( d, 'de_DE' ).tagName === 'INPUT' && findAll( d, el => el.tagName === 'CODE' ).length === 0 );
	check( '...and no textfill at a line', byClass( d, 'nino-admin-fills-button' ).length === 0 );
	// P9
	check( 'the typing counter follows what is typed, in the field of that language', ( () => { w.type( d, 'en_US', 'Services!' ); const ok = w.counterOf( d, 'en_US' ).textContent === '9 / 150' && w.counterOf( d, 'de_DE' ).textContent === '10 / 150'; w.type( d, 'en_US', 'Services' ); return ok } )() );
	check( 'nothing is sent by opening it', w.sent.filter( c => c.endpoint !== 'keys' ).length === 0 );
	w.close( d );
	check( 'Back - the cross - closes the record: the address is the row\'s, the table is as it was and the focus is on the row', w.dialog() === null && w.hash() === addressOf( ROW ) && w.ids().join() === ORDER.join() && w.h.index() === position );

	// a text of several lines, the textfill, an empty language, the rich text
	const body = w.openRow( BODY );
	check( 'a text of several lines is a field of several lines, in every language, with the textfills at its name - asked for in the language of THAT field', w.controlOf( body, 'de_DE' ).tagName === 'TEXTAREA' && w.controlOf( body, 'en_US' ).tagName === 'TEXTAREA'
		&& byClass( w.fieldOf( body, 'de_DE' ), 'nino-admin-fills-button' ).length === 1 && byClass( w.fieldOf( body, 'en_US' ), 'nino-admin-fills-button' ).length === 1 );
	// P10
	check( '...an empty language is an empty field: the text of another language is not put in as its own, and the line breaks stay', w.controlOf( body, 'en_US' ).value === '' && w.controlOf( body, 'de_DE' ).value === 'Zeile eins\nZeile zwei' );
	fire( byClass( w.fieldOf( body, 'en_US' ), 'nino-admin-fills-button' )[0], 'click' );
	fire( byClass( w.fieldOf( body, 'de_DE' ), 'nino-admin-fills-button' )[0], 'click' );
	const asked = w.sent.filter( c => c.endpoint === 'fills' ).map( c => c.payload.locale ).join();
	check( '...the list of the textfills is the server\'s, asked when the button is pressed, for the language of the field it stands at', asked === 'en_US,de_DE' );
	w.close( w.dialog() );

	const rich = w.openRow( RICH );
	check( 'a text with markup is the rich-text editor in every language - with the limit and the format of the key - and the textfills at its name', w.editors.length === 2 && w.editors.every( e => e.maxlength === 150 && e.format === 'blocks' ) && w.editors[0].given === '<p>Wir beraten Sie gern</p>' && w.editors[1].given === 'We are happy to advise you'
		&& byClass( w.fieldOf( rich, 'de_DE' ), 'nino-admin-fills-button' ).length === 1 && byClass( w.fieldOf( rich, 'en_US' ), 'nino-admin-fills-button' ).length === 1 && w.fieldOf( rich, 'de_DE' ).attributes['role'] === 'group' );
	w.close( rich );
	check( 'the editors of a record that is closed are taken down when the table is shown again', w.editors.length === 2 && w.editors.every( e => e.destroyed === true ) );

	// a text that is the same in every language
	const one = w.openRow( BADGE );
	check( 'a text that is the same in every language is ONE field, named for that - and no field of a language', w.fields( one ).length === 1 && w.fields( one )[0].dataset.locale === '*' && nameOf( w.fields( one )[0] ) === W('/_admin/text/label/all-languages') && w.controlOf( one, '*' ).value === 'Neu' );
	w.close( one );
}

{
	// the address: a reload opens the record over its table; one that names no text, or one that may not be written, says so and is the row's
	const w = textPage( fullResponse(), { hash : addressOf( ROW, TITLE ) } ).open();
	const d = w.dialog();
	// P11
	check( 'a reload at the address of a text opens the record of it over the table of its row - no step of the history of its own', d !== null && w.titleOf( d ) === 'Einleitung › Titel' && w.ids().join() === ORDER.join() && w.hash() === addressOf( ROW, TITLE ) && w.h.router.marked() !== 'text' );
	w.close( d );
	check( '...closing it writes the address of the row over it', w.dialog() === null && w.hash() === addressOf( ROW ) );

	const missing = textPage( fullResponse(), { hash : addressOf( ROW, '/template/page-services/intro/nope' ) } ).open();
	check( 'a key the row does not hold says so over the table, in the words of the panel, and the address is the row\'s', missing.dialog() === null && byClass( missing.form, 'nino-admin-notice' ).some( n => n.textContent === W('/_admin/text/record/missing') ) && missing.hash() === addressOf( ROW ) && missing.ids().length === 13 );
	const locked = textPage( fullResponse(), { hash : addressOf( ROW, LOCKED ) } ).open();
	check( 'a text the account may not change is no record at its address either: the notice, and the address is the row\'s - nothing is asked of the server', locked.dialog() === null && byClass( locked.form, 'nino-admin-notice' ).some( n => n.textContent === W('/_admin/text/error/denied') ) && locked.hash() === addressOf( ROW ) && locked.sent.length === 1 );
	const row = textPage( fullResponse(), { hash : addressOf( ROW ) } ).open();
	check( 'the address of the row alone is its table', row.dialog() === null && row.ids().join() === ORDER.join() && has( row.list, 'admin-hidden' ) === true );
	const none = textPage( fullResponse(), { hash : '#text/text/template%2Fpage-nope' } ).open();
	check( 'a row there is not is the list, and the address is the list\'s', has( none.list, 'admin-hidden' ) === false && none.hash() === '#text' && none.dialog() === null );
	const other = textPage( fullResponse(), { hash : '#images/images/x' } ).open();
	check( 'the address of another panel leaves the list where it is', has( other.list, 'admin-hidden' ) === false && other.dialog() === null );
}

{
	// Back and Forward through the history: the record opens and closes with the address
	const w = inRow( ROW );
	w.openRow( TITLE );
	w.back();
	check( 'Back closes the record: the table of the row, at its address', w.dialog() === null && w.hash() === addressOf( ROW ) && w.ids().join() === ORDER.join() );
	w.back();
	check( '...Back again is the list', has( w.list, 'admin-hidden' ) === false && w.hash() === '#text' );
	w.h.forward();
	w.h.router.arrive();
	w.panel.showCurrent();
	w.h.hashchange();
	check( '...and Forward is the table again', has( w.form, 'admin-hidden' ) === false && w.hash() === addressOf( ROW ) );
}

// --- saving -------------------------------------------------------------------------------------------------------------------

{
	const w = inRow( ROW );
	const d = w.openRow( TITLE );

	// P12: nothing changed - nothing sent
	w.save( d );
	check( 'a save with nothing changed sends nothing, and says it is saved', w.writes().length === 0 && w.told.length > 0 && w.told.every( t => t === W('/_admin/common/msg/saved') ) && w.touches.length === 0 );
	check( '...and the record is not unsaved', w.h.dirty.isDirty() === false );

	// P13: one language changed - one request with one item
	w.type( d, 'de_DE', 'Leistungen neu' );
	check( 'a field that differs from what it held is unsaved input for the shell, typing it back takes that away', w.h.dirty.isDirty() === true && ( w.type( d, 'de_DE', 'Leistungen' ), w.h.dirty.isDirty() === false ) );
	w.type( d, 'de_DE', 'Leistungen neu' );
	w.told.length = 0;
	w.save( d );
	check( 'one language changed is ONE request with ONE item: the key, the language, the text as typed and the text it was read as', w.writes().length === 1 && JSON.stringify( w.writes()[0].payload ) === JSON.stringify( { items : [ { key : TITLE, locale : 'de_DE', value : 'Leistungen neu', was : 'Leistungen' } ] } ) );
	check( '...a toast says it, the record stays and is not unsaved, and the shell is told that texts were written - once', w.told.length > 0 && w.told.every( t => t === W('/_admin/common/msg/saved') ) && w.dialog() === d && w.h.dirty.isDirty() === false && w.touches.join() === 'texts' );
	check( '...what it wrote itself it holds as read: the stamp it asked at moved with the shell\'s, so shown once more it asks for nothing', w.panel._stamp === w.panel._stampNow() && w.h.admin.stamp( 'texts' ) === 1 );
	check( '...what the server answered is what the table and the model hold', byClass( w.cells( TITLE )[2], 'nino-admin-table-clamp' )[0].textContent === 'Leistungen neu' && byClass( w.cells( TITLE )[3], 'nino-admin-table-limit' )[0].textContent === '14 / 150'
		&& w.panel._groups[ROW].find( e => e.key === TITLE ).values.de_DE === 'Leistungen neu' && has( w.row( TITLE ), 'is-fresh' ) );
	w.save( d );
	check( 'saved again as it is, it sends nothing more', w.writes().length === 1 );

	// P14: two languages changed - two requests, in the order of the fields
	w.type( d, 'en_US', 'Services new' );
	w.type( d, 'de_DE', 'Leistungen zwei' );
	w.touches.length = 0;
	w.save( d );
	check( 'two languages changed are TWO requests, one after the other in the order of the fields, each with one item', w.writes().length === 3 && w.writes().slice( 1 ).map( c => c.payload.items.length+ c.payload.items[0].locale ).join() === '1de_DE,1en_US'
		&& w.writes()[1].payload.items[0].was === 'Leistungen neu' && w.writes()[2].payload.items[0].was === 'Services' );
	check( '...each a write: the shell is told for each, and both are saved', w.touches.join() === 'texts,texts' && w.h.dirty.isDirty() === false && w.panel._groups[ROW].find( e => e.key === TITLE ).values.en_US === 'Services new' );

	// only the language that was changed
	w.type( d, 'en_US', 'Services third' );
	w.save( d );
	check( 'the other language is not sent where only one was changed', w.writes().length === 4 && w.writes()[3].payload.items[0].locale === 'en_US' );
	w.close( d );
}

{
	// P15: the sanitized value comes back into the field and the row
	const w = inRow( ROW );
	w.handle.sanitize = value => value.replace( /"/g, '&quot;' );
	const d = w.openRow( TITLE );
	w.type( d, 'de_DE', 'Sag "Hallo"' );
	w.save( d );
	check( 'what the server kept is what the field shows and what the row holds - the field is rewritten where it differs, and is not unsaved', w.controlOf( d, 'de_DE' ).value === 'Sag &quot;Hallo&quot;' && w.panel._groups[ROW].find( e => e.key === TITLE ).values.de_DE === 'Sag &quot;Hallo&quot;'
		&& w.h.dirty.isDirty() === false && w.counterOf( d, 'de_DE' ).textContent === '21 / 150' );
	w.sent.length = 0;
	w.save( d );
	check( '...and a save of that is no change', w.writes().length === 0 );
}

{
	// P15b: a plain text is stored with its quotes as entities and the field shows what was typed
	const server = fullResponse();
	const held = server.keys.find( e => e.key === TITLE );
	held.values.de_DE = 'Sag &quot;Hallo&quot; & it&#039;s';
	held.typed = { de_DE : 'Sag "Hallo" & it\'s' };
	const w = inRow( ROW, server );
	w.handle.sanitize = value => value.replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	w.handle.typed = value => value.replace( /&quot;/g, '"' ).replace( /&#039;/g, "'" );
	const d = w.openRow( TITLE );
	check( 'the field of a plain text shows what was typed, the quotes as they are, and its counter counts the characters it shows', w.controlOf( d, 'de_DE' ).value === 'Sag "Hallo" & it\'s' && w.counterOf( d, 'de_DE' ).textContent === '18 / 150' );
	check( '...a language that has no typed form shows the stored text', w.controlOf( d, 'en_US' ).value === 'Services' );
	check( '...and the record is not unsaved', w.h.dirty.isDirty() === false );
	w.save( d );
	check( 'a save of the record as it was drawn sends nothing', w.writes().length === 0 );
	w.type( d, 'de_DE', 'Sag "Hallo" & it\'s mehr' );
	w.save( d );
	check( 'a changed text is sent as it stands in the field, and the text it was read as is the STORED text, never the typed one', w.writes().length === 1
		&& JSON.stringify( w.writes()[0].payload.items ) === JSON.stringify( [ { key : TITLE, locale : 'de_DE', value : 'Sag "Hallo" & it\'s mehr', was : 'Sag &quot;Hallo&quot; & it&#039;s' } ] ) );
	check( '...what the server kept is held in both forms: the model has the stored text and the typed one, the field is not rewritten and is not unsaved', w.panel._groups[ROW].find( e => e.key === TITLE ).values.de_DE === 'Sag &quot;Hallo&quot; & it&#039;s mehr'
		&& w.panel._groups[ROW].find( e => e.key === TITLE ).typed.de_DE === 'Sag "Hallo" & it\'s mehr' && w.controlOf( d, 'de_DE' ).value === 'Sag "Hallo" & it\'s mehr' && w.h.dirty.isDirty() === false );
	w.type( d, 'de_DE', 'Neu' );
	w.save( d );
	check( '...the next change is read as the stored text of the save before', w.writes().length === 2 && w.writes()[1].payload.items[0].was === 'Sag &quot;Hallo&quot; & it&#039;s mehr' && w.panel._groups[ROW].find( e => e.key === TITLE ).typed.de_DE === undefined );
}

{
	// P16: a text that is the same in every language is written to the one text
	const w = inRow( ROW );
	const d = w.openRow( BADGE );
	w.type( d, '*', 'Neu!' );
	w.save( d );
	check( 'a text that is the same in every language is written to the one text: the locale is *, the text it was read as is the one text', w.writes().length === 1 && JSON.stringify( w.writes()[0].payload.items ) === JSON.stringify( [ { key : BADGE, locale : '*', value : 'Neu!', was : 'Neu' } ] ) );
}

{
	// P17: the second language is refused: the first is saved, the sentence stands at the second, what was typed there stays
	const w = inRow( ROW );
	const d = w.openRow( TITLE );
	w.handle.refuse[TITLE+ ' en_US'] = { ok : false, error : 'could not be written' };
	w.type( d, 'de_DE', 'Eins' );
	w.type( d, 'en_US', 'One' );
	w.save( d );
	check( 'the first language is saved - the model, the table - and the record says it failed', w.writes().length === 2 && w.panel._groups[ROW].find( e => e.key === TITLE ).values.de_DE === 'Eins' && byClass( w.cells( TITLE )[2], 'nino-admin-table-clamp' )[0].textContent === 'Eins' && w.told.length === 0 );
	check( '...the sentence stands at the field of the second, which is marked, and what was typed there stays', w.errorsOf( d ).length === 1 && w.errorsOf( d )[0].parentNode === w.fieldOf( d, 'en_US' ).parentNode && w.fieldOf( d, 'en_US' ).parentNode.children.indexOf( w.errorsOf( d )[0] ) === w.fieldOf( d, 'en_US' ).parentNode.children.indexOf( w.fieldOf( d, 'en_US' ) ) + 1 && w.controlOf( d, 'en_US' ).attributes['aria-invalid'] === 'true'
		&& w.errorsOf( d )[0].textContent === W('/_admin/text/error/key') && w.controlOf( d, 'en_US' ).value === 'One' );
	check( '...the shell is told once that texts were written - for the language that was, not for the one that was refused', w.touches.join() === 'texts' );
	check( '...the line of the bar says how many were not saved, and the record is unsaved', w.lineText( d ) === W('/_admin/text/error/one') && w.h.dirty.isDirty() === true );
	w.type( d, 'en_US', 'One!' );
	check( '...typing into the field takes its sentence away', w.errorsOf( d ).length === 0 );
	w.handle.refuse = {};
	w.sent.length = 0;
	w.save( d );
	check( 'the next save sends the language that is still changed, and only that', w.writes().length === 1 && w.writes()[0].payload.items[0].locale === 'en_US' && w.h.dirty.isDirty() === false );
	w.close( d );

	// denied is said as the words for it
	const e = w.openRow( INTRO );
	w.handle.refuse[INTRO+ ' de_DE'] = { ok : false, error : 'not allowed' };
	w.type( e, 'de_DE', 'Neu' );
	w.save( e );
	check( 'a language the server says the account may not write is said in the words of the panel', w.errorsOf( e )[0].textContent === W('/_admin/text/error/denied') );
	w.handle.refuse = {};
	w.close( w.dialog() );
	check( 'a record that is closed with a language that is not saved asks about it first - the text is not given up silently', w.asking() !== null && w.writes().length === 2 );
	w.answerAsk('/_admin/common/label/discard');
	check( '...and a Discard closes it', w.dialog() === null && w.hash() === addressOf( ROW ) );
}

{
	// The count: the table of values the server's Text::length() is held to is held to by the script too, and the limit a field counts to is the server's for its language
	const U = textPage( fullResponse() ).h.admin.textKeys;
	const table = JSON.parse( fs.readFileSync( path.join( __dirname, 'fixtures', 'text-rules.json' ), 'utf8' ) ).lengths;
	const wrong = table.filter( row => U.lengthOf( { html : row.format !== 'plain' }, row.text ) !== row.count );
	check( 'the script counts every text of the table the server counts - plain, inline, lines and blocks, an emoji as two, a line break as one'+ ( wrong.length === 0 ? '' : ' - not '+ wrong.map( row => JSON.stringify( row.text ) ).join() ), table.length > 25 && wrong.length === 0 );

	const response = fullResponse();
	const entry = response.keys.find( e => e.key === TITLE );
	entry.maxlength = 30;
	entry.limits = { de_DE : 30, en_US : 12 };
	const w = inRow( ROW, response );
	const d = w.openRow( TITLE );
	check( 'the field of a language counts to the limit the server names for that language and takes as many characters: the counter and the maxlength of its control',
		w.counterOf( d, 'de_DE' ).textContent === '10 / 30' && w.counterOf( d, 'en_US' ).textContent === '8 / 12' && w.controlOf( d, 'de_DE' ).maxLength === 30 && w.controlOf( d, 'en_US' ).maxLength === 12 );

	// A text the server turns down as too long: the sentence of its code stands at the field of that language, the other language is written, the focus goes to the field
	w.handle.refuse[TITLE+ ' en_US'] = { ok : false, error : 'too long', code : 'text_too_long', params : [ 12, 14 ], locale : 'en_US' };
	w.handle.limits[TITLE+ ' de_DE'] = 28;
	w.type( d, 'de_DE', 'Eins' );
	w.type( d, 'en_US', 'Fourteen chars' );
	w.save( d );
	check( 'a text that is too long is said at the field of its language, with the limit and the length the server names, in the words of the workbench - and nothing is made of it in the other language, which is saved',
		w.errorsOf( d ).length === 1 && w.errorsOf( d )[0].textContent === F( '/_admin/error/text_too_long', 12, 14 ) && W('/_admin/error/text_too_long').indexOf('%s') !== -1 && w.errorsOf( d )[0].parentNode === w.fieldOf( d, 'en_US' ).parentNode
		&& w.panel._groups[ROW].find( e => e.key === TITLE ).values.de_DE === 'Eins' && w.controlOf( d, 'en_US' ).value === 'Fourteen chars' && w.lineText( d ) === W('/_admin/text/error/one') && w.h.dirty.isDirty() === true );
	check( '...the focus goes to the field that was turned down, the text typed there stays', w.h.doc.activeElement === w.controlOf( d, 'en_US' ) );
	check( '...and the language that was written counts to the limit the server names after the write', w.controlOf( d, 'de_DE' ).maxLength === 28 && w.counterOf( d, 'de_DE' ).textContent === '4 / 28' && w.panel._groups[ROW].find( e => e.key === TITLE ).limits.de_DE === 28 );
	w.type( d, 'en_US', 'Short' );
	check( '...typing into the field takes its sentence away', w.errorsOf( d ).length === 0 );
}

{
	// A text that is longer than the limit of its key: counted against the limit of the key, which is what it is over, with the hint that it can be shortened and not lengthened;
	// the field still takes what the language holds, and no more
	const response = fullResponse();
	const entry = response.keys.find( e => e.key === TITLE );
	entry.values.de_DE = 'x'.repeat( 29 );
	entry.maxlength = 29;
	entry.keyLimit = 10;
	entry.limits = { de_DE : 29, en_US : 10 };
	const w = inRow( ROW, response );
	const d = w.openRow( TITLE );
	const hintOf = code => byClass( w.fieldOf( d, code ), 'admin-text-over' )[0];
	check( 'a text over the limit of its key is counted against that limit: "29 / 10", and the hint says the text can be shortened and not lengthened, with the limit of the key; the control takes what the language holds',
		w.counterOf( d, 'de_DE' ).textContent === '29 / 10' && has( w.counterOf( d, 'de_DE' ), 'is-limit' ) === true && hintOf( 'de_DE' ).hidden === false && hintOf( 'de_DE' ).textContent === F( '/_admin/text/hint/over-limit', 10 )
		&& w.controlOf( d, 'de_DE' ).maxLength === 29 );
	check( '...a text within the limit of its key has no hint, and counts to its own', hintOf( 'en_US' ).hidden === true && hintOf( 'en_US' ).textContent === '' && w.counterOf( d, 'en_US' ).textContent === '8 / 10' );
	w.type( d, 'de_DE', 'x'.repeat( 9 ) );
	check( '...and shortened to the limit of the key the hint is gone and the count is against the limit of the key, which is below what the language holds', hintOf( 'de_DE' ).hidden === true && w.counterOf( d, 'de_DE' ).textContent === '9 / 10' && w.controlOf( d, 'de_DE' ).maxLength === 29 );
	w.type( d, 'de_DE', 'x'.repeat( 15 ) );
	check( '...lengthened once more it is over the limit of the key, with the hint', hintOf( 'de_DE' ).hidden === false && w.counterOf( d, 'de_DE' ).textContent === '15 / 10' && has( w.counterOf( d, 'de_DE' ), 'is-limit' ) === true );
	w.type( d, 'de_DE', 'x'.repeat( 9 ) );
	check( '...the column of the table says what the record says: the characters of the text against the limit of the key', byClass( w.cells( TITLE )[3], 'nino-admin-table-limit' )[0].textContent === '29 / 10' );
	w.type( d, 'de_DE', 'x'.repeat( 28 ) );
	w.handle.limits[TITLE+ ' de_DE'] = 28;
	w.handle.keyLimits[TITLE+ ' de_DE'] = 8;
	w.save( d );
	check( 'after a save the server names the limit of the language and of the key, and the text is counted against the limit of the key the answer names, with the hint there', w.counterOf( d, 'de_DE' ).textContent === '28 / 8' && hintOf( 'de_DE' ).hidden === false && w.controlOf( d, 'de_DE' ).maxLength === 28
		&& w.panel._groups[ROW].find( e => e.key === TITLE ).keyLimit === 8 );
	check( 'the words of the hint are in both languages, with a place for the limit', [ 'de_DE', 'en_US' ].every( locale => workbenchWords( locale )['/_admin/text/hint/over-limit'].split('%s').length === 2 ) );
}

{
	// The rich-text editor counts to the limit of its language, not to the key's largest
	const response = fullResponse();
	const entry = response.keys.find( e => e.key === RICH );
	entry.maxlength = 25;
	entry.limits = { de_DE : 25, en_US : 12 };
	const w = inRow( ROW, response );
	w.openRow( RICH );
	check( 'the editor of each language is built with the limit of that language', w.editors.length === 2 && w.editors[0].maxlength === 25 && w.editors[1].maxlength === 12 );
}

{
	// A global key is read by its star: the field counts to the limit of the one text
	const response = fullResponse();
	const entry = response.keys.find( e => e.key === BADGE );
	entry.maxlength = 20;
	entry.limits = { '*' : 7 };
	const w = inRow( ROW, response );
	const d = w.openRow( BADGE );
	check( 'a text that is the same in every language counts to the limit named for its star', w.controlOf( d, '*' ).maxLength === 7 && w.counterOf( d, '*' ).textContent.split(' / ')[1] === '7' );
}

{
	// a request that fails leaves the record as it is; what is typed while the request runs is not in what was sent
	const w = inRow( ROW );
	const d = w.openRow( TITLE );
	w.type( d, 'de_DE', 'Drei' );
	w.handle.fail = { status : 500, body : null };
	w.save( d );
	check( 'a request that fails leaves the record as it is, unsaved, and says so in the line', w.h.dirty.isDirty() === true && w.lineText( d ).indexOf( '500' ) !== -1 && w.touches.length === 0 );
	w.handle.fail = null;
	// P23: the fields take no input while a save runs - what the record takes as saved when it ends is what the form holds then
	w.handle.hold = true;
	w.type( d, 'de_DE', 'Vier' );
	w.type( d, 'en_US', 'Four' );
	w.controlOf( d, 'de_DE' ).focus();
	const before = w.writes().length;
	w.save( d );
	check( 'while the requests of a save run the fields take no input - the one that was sent and the one that waits', w.controlOf( d, 'de_DE' ).disabled === true && w.controlOf( d, 'en_US' ).disabled === true && w.held.length === 1 && w.writes().length === before + 1 );
	w.held.shift()();
	check( '...the second request goes out when the first is answered, and the fields stay closed until the save is over', w.writes().length === before + 2 && w.held.length === 1 && w.controlOf( d, 'de_DE' ).disabled === true );
	w.held.shift()();
	check( '...and when the save is over they are open again, saved, the focus where it was', w.controlOf( d, 'de_DE' ).disabled === false && w.controlOf( d, 'en_US' ).disabled === false && w.h.dirty.isDirty() === false && w.h.doc.activeElement === w.controlOf( d, 'de_DE' ) );
	w.handle.hold = false;
}

{
	// P18: another tab or person wrote a language since: the question of a conflict, Overwrite, Reload, Cancel
	const make = () => {
		const w = inRow( ROW );
		const d = w.openRow( TITLE );
		w.external( TITLE, 'de_DE', 'Fremder Text' );
		w.type( d, 'de_DE', 'Mein Text' );
		return Object.assign( w, { d : d } );
	};

	const a = make();
	a.save( a.d );
	const q = a.asking();
	check( 'a language that another wrote meanwhile asks the question of a refused save, naming the text by its place, its key and its language - nothing is saved, the text stays typed and unsaved', q !== null && a.writes().length === 1 && a.writes()[0].payload.items[0].was === 'Leistungen' && a.writes()[0].payload.items[0].force === undefined
		&& allText( q ).indexOf( TITLE ) !== -1 && allText( q ).indexOf( 'Deutsch' ) !== -1 && allText( q ).indexOf( ' › ' ) !== -1 && a.told.length === 0 && a.h.dirty.isDirty() === true && a.controlOf( a.d, 'de_DE' ).value === 'Mein Text' );
	// P19
	a.answerAsk('/_admin/common/conflict/overwrite');
	const forced = a.writes().at( -1 ).payload.items;
	check( 'Overwrite writes that language again - once, forced, without the text it was read as - and then it is saved', a.writes().length === 2 && forced.length === 1 && forced[0].key === TITLE && forced[0].locale === 'de_DE' && forced[0].force === true && forced[0].was === undefined && forced[0].value === 'Mein Text'
		&& a.h.dirty.isDirty() === false && a.told.at( -1 ) === W('/_admin/common/msg/saved') && a.touches.join() === 'texts' );

	// Overwrite sends only the language that was turned down: the other one goes with its own text
	const b = make();
	b.type( b.d, 'en_US', 'Services B' );
	b.save( b.d );
	b.answerAsk('/_admin/common/conflict/overwrite');
	check( 'with two languages changed, the first turned down stops the save: nothing of the second was sent before the answer - and Overwrite forces the first and then sends the second as it always does', b.writes().length === 3 && b.writes()[0].payload.items[0].locale === 'de_DE' && b.writes()[1].payload.items[0].force === true
		&& b.writes()[2].payload.items[0].locale === 'en_US' && b.writes()[2].payload.items[0].force === undefined && b.writes()[2].payload.items[0].was === 'Services' );

	// P20: Reload
	const c = make();
	c.save( c.d );
	c.answerAsk('/_admin/common/conflict/reload');
	check( 'Reload reads the keys again - one more read - and shows what is stored, the row of the server: the typed text is dropped, the table has it', c.sent.filter( e => e.endpoint === 'keys' ).length === 2 && c.controlOf( c.dialog(), 'de_DE' ).value === 'Fremder Text'
		&& c.h.dirty.isDirty() === false && byClass( c.cells( TITLE )[2], 'nino-admin-table-clamp' )[0].textContent === 'Fremder Text' );

	// Cancel
	const e = make();
	e.save( e.d );
	e.answerAsk('/_admin/common/label/cancel');
	check( 'Cancel keeps the record as it is: what was typed stays, unsaved, nothing more was sent', e.dialog() === e.d && e.controlOf( e.d, 'de_DE' ).value === 'Mein Text' && e.h.dirty.isDirty() === true && e.writes().length === 1 );
}

{
	// P21: "Save and continue": the next text of the table, a text the account may not write is skipped
	const w = inRow( ROW );
	const d = w.openRow( BADGE );
	w.type( d, '*', 'Neu!' );
	fire( w.button( d, '/_admin/common/label/save-next' ), 'click' );
	check( '"Save and continue" saves and goes on to the next text of the table - at its address, with its head', w.writes().length === 1 && w.titleOf( w.dialog() ) === 'Einleitung › Titel' && w.hash() === addressOf( ROW, TITLE ) );
	w.close( w.dialog() );
	const prev = w.openRow( BODY );
	check( 'the steps skip a text the account may not write: after the text before the locked one comes the one after it', byClass( prev, 'nino-admin-record-count' )[0].textContent === F('/_admin/common/record/position',10,12) );
	w.type( prev, 'de_DE', 'Zeile eins\nZeile zwei\nund drei' );
	fire( w.button( prev, '/_admin/common/label/save-next' ), 'click' );
	check( '..."Save and continue" too: it is the long text, at its address', w.subtitleOf( w.dialog() ) === LONG && w.hash() === addressOf( ROW, LONG ) && w.writes().length === 2 );
	w.close( w.dialog() );
	// nothing changed: it goes on and sends nothing
	const last = w.openRow( LONG );
	fire( w.button( last, '/_admin/common/label/save-next' ), 'click' );
	check( '...and with nothing changed it goes on and sends nothing', w.subtitleOf( w.dialog() ) === '/template/page-services/legal/note' && w.writes().length === 2 );
}

{
	// "Save and continue" walks the rows the table shows now
	const w = inRow( ROW );
	const select = w.selects()[1];
	select.value = 'any';
	fire( select, 'change', { target : select } );
	const d = w.openRow( BODY );
	check( 'the record has the place of the text in the rows the table shows now', byClass( d, 'nino-admin-record-count' )[0].textContent === F('/_admin/common/record/position',2,3) );
	w.type( d, 'de_DE', 'Zeile eins\nZeile zwei\nDrei' );
	fire( w.button( d, '/_admin/common/label/save-next' ), 'click' );
	check( '"Save and continue" goes on to the next row of the table as it is filtered: the texts that are empty in a language', w.subtitleOf( w.dialog() ) === LONG && w.hash() === addressOf( ROW, LONG ) && w.writes().length === 1 );
	// a text that the save made no longer empty leaves that table: the record goes on to the text that followed it there - or stays, saved, where none did
	w.close( w.dialog() );
	const followed = w.ids()[ w.ids().indexOf( INTRO ) + 1 ] ?? null;
	const e = w.openRow( INTRO );
	w.type( e, 'en_US', 'Hello' );
	fire( w.button( e, '/_admin/common/label/save-next' ), 'click' );
	check( 'a text that the save made fill its last empty language leaves the table that shows the empty ones, and the record goes on to the text that followed it - it stays at the saved one where none did', w.subtitleOf( w.dialog() ) === ( followed ?? INTRO ) && w.ids().indexOf( INTRO ) === -1 && w.h.dirty.isDirty() === false && w.writes().length === 2 );
}

{
	// P24: the counts of the list follow a save when the list is shown again
	const w = inRow( ROW, undefined, { work : 'en_US' } );
	fire( byClass( w.form, 'nino-admin-back-link' )[0], 'click' );
	const before = w.lineOf( ROW );
	fire( w.linkOf( ROW ), 'click', { target : w.linkOf( ROW ) } );
	const d = w.openRow( INTRO );
	w.type( d, 'en_US', 'Introduction' );
	w.save( d );
	w.close( d );
	fire( byClass( w.form, 'nino-admin-back-link' )[0], 'click' );
	check( 'the counts of the list follow a save: the text that was empty in the working language is not counted as empty any more', before === F('/_admin/text/count/texts',13)+ ' \u00B7 '+ F('/_admin/text/count/empty',3,'English') && w.lineOf( ROW ) === F('/_admin/text/count/texts',13)+ ' \u00B7 '+ F('/_admin/text/count/empty',2,'English') );
}

{
	// what the record calls a change: a whitespace is one, a language that is empty is not sent unless it was typed into
	const w = inRow( ROW );
	const d = w.openRow( INTRO );
	w.type( d, 'de_DE', 'Das bieten wir an ' );
	check( 'a text that differs from the stored one by a whitespace only is a change: the record is unsaved', w.h.dirty.isDirty() === true );
	w.save( d );
	check( '...and the save sends it as typed, with the whitespace - and only that language: the empty one is not sent', w.writes().length === 1 && w.writes()[0].payload.items.length === 1 && w.writes()[0].payload.items[0].value === 'Das bieten wir an ' && w.writes()[0].payload.items[0].locale === 'de_DE' );
	check( 'a language that has no text of its own is an empty field, whatever the other says', w.controlOf( d, 'en_US' ).value === '' );
	w.type( d, 'en_US', 'x' );
	w.type( d, 'en_US', '' );
	w.save( d );
	check( '...typed into and emptied again it is what it was: nothing more is sent', w.writes().length === 1 );
}

{
	// a language the website does not serve yet is a field like the others, marked as the select of the working language marks it
	const response = fullResponse( { locales : [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : false } ] } );
	const w = inRow( ROW, response );
	const d = w.openRow( TITLE );
	check( 'a switched-off language is a field, editable, marked as inactive as the select of the working language marks it', nameOf( w.fieldOf( d, 'en_US' ) ) === 'English ('+ W('/_admin/common/word/inactive')+ ')' && w.controlOf( d, 'en_US' ).readOnly !== true );
	const v = inRow( ROW, response, { work : 'en_US' } );
	const e = v.openRow( TITLE );
	check( '...and as the working language it says both', nameOf( v.fieldOf( e, 'en_US' ) ) === 'English ('+ W('/_admin/text/label/working')+ ', '+ W('/_admin/common/word/inactive')+ ')' );
}

{
	// a text with markup: the editor holds it, a save writes what the editor reads, and what the server kept is written back into it
	const w = inRow( ROW );
	w.handle.sanitize = value => value.replace( /<script>.*<\/script>/, '' );
	const d = w.openRow( RICH );
	check( 'the editors are as the form was drawn, and nothing is unsaved', w.editors.length === 2 && w.h.dirty.isDirty() === false );
	w.save( d );
	check( 'a save of a record nothing was typed into sends nothing - the editor reads back what it was given', w.writes().length === 0 );
	w.editors[0].setValue( '<p>Wir beraten <strong>gern</strong></p><script>x</script>' );
	w.save( d );
	check( 'a rich text that was changed is sent as the editor reads it, for its language, with the text it was read as', w.writes().length === 1 && JSON.stringify( w.writes()[0].payload.items ) === JSON.stringify( [ { key : RICH, locale : 'de_DE', value : '<p>Wir beraten <strong>gern</strong></p><script>x</script>', was : '<p>Wir beraten Sie gern</p>' } ] ) );
	check( '...and what the server kept is written back into the editor, and is what the model holds', w.editors[0].getValue() === '<p>Wir beraten <strong>gern</strong></p>' && w.panel._groups[ROW].find( e => e.key === RICH ).values.de_DE === '<p>Wir beraten <strong>gern</strong></p>' && w.h.dirty.isDirty() === false );
	check( '...the table shows the words of it', byClass( w.cells( RICH )[2], 'nino-admin-table-clamp' )[0].textContent === 'Wir beraten gern' );
	// the editable box of a rich text takes no input while a save runs
	w.handle.hold = true;
	w.editors[1].setValue( 'We advise' );
	w.save( d );
	check( 'while a save runs the editable box of a rich text is not editable - and is again when it is over', w.editors[0].box.attributes['contenteditable'] === 'false' && w.editors[1].box.attributes['contenteditable'] === 'false' && ( w.held.shift()(), w.editors[0].box.attributes['contenteditable'] === 'true' && w.editors[1].box.attributes['contenteditable'] === 'true' ) );
	w.handle.hold = false;
}

// --- the language moves under an open record --------------------------------------------------------------------------------

{
	const w = inRow( ROW );
	const d = w.openRow( TITLE );

	check( 'the working language is first, marked as the working language', w.fields( d ).map( f => f.dataset.locale ).join() === 'de_DE,en_US' );
	// the shell asks about unsaved input before the language moves: there is none here
	w.go( 'en_US' );
	check( 'the record is drawn again with the new language first, marked, and what it held is what is stored', w.dialog() === d && w.fields( d ).map( f => f.dataset.locale ).join() === 'en_US,de_DE' && nameOf( w.fieldOf( d, 'en_US' ) ) === 'English ('+ W('/_admin/text/label/working')+ ')'
		&& nameOf( w.fieldOf( d, 'de_DE' ) ) === 'Deutsch' && w.controlOf( d, 'en_US' ).value === 'Services' && w.controlOf( d, 'de_DE' ).value === 'Leistungen' && w.h.dirty.isDirty() === false );
	check( '...and so is the table: its text column says the language, the title of the record is the same text', byClass( w.cells( TITLE )[2], 'nino-admin-table-clamp' )[0].textContent === 'Services' && w.dialog() === d && w.hash() === addressOf( ROW, TITLE ) );
	w.type( d, 'de_DE', 'Leistungen zwo' );
	w.sent.length = 0;
	w.save( d );
	check( 'a save writes the languages that were changed, whichever is first: the language is the field\'s, not the workbench\'s', w.writes().length === 1 && w.writes()[0].payload.items[0].locale === 'de_DE' && w.writes()[0].payload.items[0].value === 'Leistungen zwo' );
	w.go( 'de_DE' );
	check( '...and back: German first again', w.fields( d ).map( f => f.dataset.locale ).join() === 'de_DE,en_US' && w.controlOf( d, 'de_DE' ).value === 'Leistungen zwo' );
}

{
	// unsaved input that the shell was told to give up closes the record, as every record is closed by a Discard: the address is the row's then
	const w = inRow( ROW );
	const d = w.openRow( TITLE );
	w.type( d, 'de_DE', 'Unsaved' );
	w.h.dirty._entries.text.discard();
	w.go( 'en_US' );
	check( 'a discarded record is gone and the address says the row: the table is in the new language and nothing was sent', w.dialog() === null && w.hash() === addressOf( ROW ) && w.heads()[2].textContent === F('/_admin/text/col/text','English') && w.writes().length === 0 );
}

{
	// a record that a filter hides is drawn again in place: its fields are put in the order of the language
	const w = inRow( ROW );
	const select = w.selects()[1];
	select.value = 'any';
	fire( select, 'change', { target : select } );
	const d = w.openRow( INTRO );
	w.go( 'en_US' );
	check( 'a text the table hides is drawn again where it stands - the table follows, the working language is first', w.dialog() === d && w.fields( d ).map( f => f.dataset.locale ).join() === 'en_US,de_DE' );
}

// --- what the panel reads again ----------------------------------------------------------------------------------------------

{
	// A template or a text written since the keys were read - by another panel, the Routes panel writes the name and the title of a page - is a row or a value the list did not
	// have: the panel reads the keys anew where it is shown with no record open, and not before
	const response = textResponse();
	const w = textPage( response ).open();
	const asked = () => w.sent.filter( c => c.endpoint === 'keys' ).length;
	const fresh = '/template/frame-footer/navigation/label';

	check( 'the panel asks for the keys once when it opens, and keeps the stamps of the templates and of the texts it asked at', asked() === 1 && w.panel._stamp === '0:0' && w.panel._model.rowOf[fresh] === undefined && w.listRow('template/frame-footer') === undefined );
	w.panel.showCurrent();
	check( 'shown again with nothing written since, it asks for nothing', asked() === 1 );
	w.h.admin.touch( 'texts' );
	w.panel.showCurrent();
	check( 'a text written since by another panel: shown once more, it asks, and keeps the stamp it asked at', asked() === 2 && w.panel._stamp === '0:1' );
	w.panel._wrote();
	w.panel.showCurrent();
	check( '...nor does a write of texts make it ask that the panel made itself: what it writes it has in hand', asked() === 2 && w.panel._stamp === '0:2' && w.h.admin.stamp( 'texts' ) === 2 );

	response.keys = response.keys.concat( [ textEntry( fresh, { de_DE : 'Fußnavigation', en_US : 'Footer navigation' } ) ] );
	w.h.admin.touch( 'templates' );
	w.panel.showCurrent();
	check( 'a template written since: shown once more, it asks - and the rows have what the template made', asked() === 3 && w.panel._stamp === '1:2' && w.panel._model.rowOf[fresh] === 'template/frame-footer' && w.listRow('template/frame-footer') !== undefined );
	w.panel.showCurrent();
	check( '...once: what it holds is the stamp it asked at', asked() === 3 );

	// a record is open: the table is not drawn under it
	fire( w.linkOf( ROW ), 'click', { target : w.linkOf( ROW ) } );
	const d = w.openRow( TITLE );
	w.h.admin.touch( 'templates' );
	w.panel.showCurrent();
	check( 'a record that is open is not drawn under: shown once more, the panel asks for nothing and the record stays', asked() === 3 && w.dialog() === d && w.panel._group === ROW );
	w.close( d );
	w.panel.showCurrent();
	check( '...the showing that finds none open is the one that asks, and the stamp is the one of that time - the table of the row is drawn from what it holds', asked() === 4 && w.panel._stamp === '2:2' && has( w.form, 'admin-hidden' ) === false && w.ids().length === 9 );

	// a template written while the answer is on its way is not in it
	const v = textPage( response );
	v.panel.showCurrent();
	v.h.admin.touch( 'templates' );
	check( 'the stamp is the one from before the request went out: the answer that arrives later keeps it', v.panel._stamp === '0:0' && v.panel._ready === true );
	v.panel.showCurrent();
	check( '...so the next showing asks again', v.sent.filter( c => c.endpoint === 'keys' ).length === 2 );
}

{
	// A "Reload" of a conflict is a read the base does not make otherwise; the page reads once when it opens
	const w = textPage( fullResponse() ).open();
	check( 'a page that is opened reads the keys once', w.sent.length === 1 );
}

{
	// a row and a part named like a member of Object.prototype open as any other
	const free = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).concat( [ textEntry( '/project/shop/constructor/name', { de_DE : 'Konstrukteur', en_US : 'Constructor' } ), textEntry( '/constructor/x', { de_DE : 'Frei' } ) ] ) } );
	const w = shown( free );
	const box = w.listSearch();
	box.value = 'konstrukteur';
	fire( box, 'input', { target : box } );
	check( 'the search over keys with a part named constructor answers, in the Text panel', w.listRows().length === 1 );
	box.value = '';
	fire( box, 'input', { target : box } );
	fire( w.linkOf('project/shop'), 'click', { target : w.linkOf('project/shop') } );
	check( '...and the table of its row opens, and the record of its text', w.ids().join() === '/project/shop/constructor/name' && w.openRow('/project/shop/constructor/name') !== null && w.controlOf( w.dialog(), 'de_DE' ).value === 'Konstrukteur' );
}

// --- what a person of a restricted role sees ---------------------------------------------------------------------------------

{
	const response = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).map( e => e.key.indexOf( '/project/company/' ) === 0 ? Object.assign( {}, e, { writable : e.key.endsWith( 'email' ) } ) : e ) } );
	const w = inRow( 'project/company', response );
	check( 'a text the account may not write has no pencil; the ones it may write have', w.pencil('/project/company/general/name') === undefined && w.pencil('/project/company/contact/email') !== undefined );
	const d = w.openRow('/project/company/contact/email');
	w.type( d, '*', 'x@acme.test' );
	w.save( d );
	check( 'a save sends only the text it may write that was changed', w.writes().length === 1 && w.writes()[0].payload.items[0].key === '/project/company/contact/email' );
	const none = inRow( 'project/company', textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).map( e => Object.assign( {}, e, { writable : false } ) ) } ) );
	check( 'a row this account may not write anywhere is a table to read: no pencil, no record by a click', none.rows().every( row => none.pencil( row.dataset.row ) === undefined ) && ( fire( none.rows()[0], 'click', { target : none.rows()[0] } ), none.dialog() === null ) );
}

// --- the words ---------------------------------------------------------------------------------------------------------------

{
	// every fill the Text script asks for exists in both languages
	const used = new Set();
	for( const file of [ '_admin/Nino/Modules/Text/assets/admin.js' ] )
		for( const m of fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' ).matchAll( /(?:getText|text|word)\(\s*'(\/_admin\/[^']+)'/g ) ) used.add( m[1] );
	[ 'en_US', 'de_DE' ].forEach( locale => {
		const words = workbenchWords( locale );
		const missing = [ ...used ].filter( key => key.endsWith('/') === false && words[key] === undefined );
		check( locale+ ': every fill the Text script asks for is there'+ ( missing.length === 0 ? '' : ' - not '+ missing.join() ), used.size > 20 && missing.length === 0 );
		check( locale+ ': the counts, the heads and the words of the table and the record are all there, and the singular is another word than the plural', [ 'count/text', 'count/texts', 'count/empty', 'col/name', 'col/section', 'col/text', 'col/chars', 'col/long', 'col/languages', 'col/lang-ok', 'col/lang-missing', 'filter/show', 'filter/all', 'filter/empty-any', 'filter/sections', 'row/open', 'record/prev', 'record/next', 'record/missing', 'label/working' ].every( k => words['/_admin/text/'+ k] !== undefined && words['/_admin/text/'+ k] !== '' )
			&& words['/_admin/text/count/text'] !== words['/_admin/text/count/texts'] );
	} );
}

// --- the Text Keys tab on the real shell: the list, the table of a row, the record of one key, the dialog that renames, the scan ----------------------------------

const keysResponse = ( extra ) => Object.assign( { keys : homeKeys.map( e => Object.assign( {}, e, { values : Object.assign( {}, e.values ), formatSet : false, maxlengthSet : false } ) ),
	locales : [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true } ], selectedLocale : 'de_DE', order : homeOrder, pages : homePages, templates : homeData().templates, features : homeData().features,
	categories : { template : [ 'common', 'frame-header', 'page-services' ], feature : [ 'consent' ], module : [ 'form', 'localepicker' ], project : [ 'company', 'website', 'mail', 'catalog' ] } }, extra ?? {} );

const keyAddress = ( row, key ) => '#text/keys/'+ ( row === undefined ? '' : encodeURIComponent( row ) )+ ( key === undefined ? '' : '/'+ encodeURIComponent( key ) );
const renameAddress = key => '#text/keys/@rename/'+ encodeURIComponent( key );
const choose = ( select, value ) => { select.value = value; fire( select, 'change', { target : select } ) };
const tick = box => { box.checked = box.checked === false; fire( box, 'change', { target : box } ) };
const typeKey = ( control, value ) => {
	control.value = value;
	// What is typed is heard by everything round the control, as in a page
	for( let up = control; up !== null && up !== undefined; up = up.parentNode )
		fire( up, 'input', { target : control } );
};

/**
 *	A page of the real shell with the Text Keys tab open on a server that holds the keys and changes them the way the real one does: a shape that is saved is what
 *	the next list says, a text that is written is the text of the key. What it refuses is told to refuse
 */
function keysPage( response, options ) {

	options = options ?? {};

	const h = adminPage();
	const words = workbenchWords( options.words ?? 'de_DE' );
	const list = element('div'), form = element('div');
	const sent = [], told = [], touches = [], editors = [];
	const handle = { refuse : {}, down : false, hold : false, held : [], listed : 0, scan : { missing : [], alsoUsed : [] }, stored : {} };

	list.id = 'keys-list';
	form.id = 'keys-form';
	form.classList.add('admin-hidden');
	h.page.appendChild( list );
	h.page.appendChild( form );
	h.show('keys');
	h.router.start();
	h.Nino.content.getText = key => words[key] ?? '';
	h.ui.locales.names = { de_DE : 'Deutsch', en_US : 'English' };
	h.ui.locales.native = 'de_DE';
	h.admin.workLocale.current = options.work ?? 'de_DE';

	const entry = key => response.keys.find( e => e.key === key );
	const writes = [ 'save', 'savebatch', 'create', 'rename', 'delete', 'scanapply' ];

	// The server: what it holds changes as the real one's does
	const serve = ( action, payload ) => {

		const name = action.replace( 'keys/', '' );
		const refused = [].concat( handle.refuse[name] ?? [] ).find( r => r.key === undefined || r.key === payload.key );

		if( refused !== undefined )
			return [ refused.status, refused.body ];

		if( name === 'list' ) {
			handle.listed++;
			return handle.down === true && handle.listed > 1 ? [ 500, null ] : [ 200, JSON.parse( JSON.stringify( response ) ) ];
		}

		if( name === 'fills' )
			return [ 200, { fills : [] } ];

		if( name === 'scan' )
			return [ 200, handle.scan ];

		if( name === 'save' ) {
			const e = entry( payload.key );
			// A limit under the longest text the key holds is turned down, as the real server does
			const longest = Math.max( ...Object.values( e.values ).map( v => String( v ?? '' ).length ) );
			if( payload.maxlength !== null && payload.maxlength < longest )
				return [ 400, { error : 'limit below the current text', code : 'keys_limit_short', params : [ longest ], field : 'maxlength' } ];
			if( e.global !== payload.global )
				e.values = payload.global === true ? { '*' : Object.values( e.values ).find( v => v !== '' && v !== null ) ?? '' } : Object.fromEntries( [ 'de_DE', 'en_US' ].map( code => [ code, Object.values( e.values )[0] ] ) );
			e.global = payload.global;
			e.blacklisted = payload.blacklisted;
			e.formatSet = payload.format !== 'auto';
			e.format = payload.format === 'auto' ? 'plain' : payload.format;
			e.html = e.format !== 'plain';
			e.maxlengthSet = payload.maxlength !== null;
			e.maxlength = payload.maxlength ?? 150;
			return [ 200, {} ];
		}

		if( name === 'savebatch' ) {
			const results = {};
			payload.items.forEach( item => {
				// As the server does: a text that was read as something other than what is stored now is not written, unless it is forced
				const changed = item.was !== undefined && item.force !== true && handle.stored[item.locale+ ' '+ item.key] !== undefined && handle.stored[item.locale+ ' '+ item.key] !== item.was;
				const turned = changed === true ? { ok : false, error : 'changed' } : ( handle.refuse['item:'+ item.key+ ' '+ item.locale] ?? handle.refuse['item:'+ item.key] );
				const value = handle.sanitize === undefined ? item.value : handle.sanitize( item.value );
				if( turned === undefined ) {
					entry( item.key ).values[item.locale] = value;
					handle.stored[item.locale+ ' '+ item.key] = value;
				}
				results[item.key] = turned ?? { ok : true, value : value };
			} );
			return [ 200, { results : results } ];
		}

		if( name === 'delete' ) {
			response.keys = response.keys.filter( e => e.key !== payload.key );
			return [ 200, {} ];
		}

		if( name === 'create' ) {
			response.keys.push( textEntry( payload.key, payload.global === true ? { '*' : payload.value } : { de_DE : payload.value, en_US : '' }, { formatSet : false, maxlengthSet : false } ) );
			return [ 200, { ok : true, key : payload.key } ];
		}

		if( name === 'rename' ) {
			entry( payload.key ).key = payload.newKey;
			return [ 200, { ok : true, key : payload.newKey } ];
		}

		return [ 200, { created : 1, ignored : 1, skipped : 1 } ];
	};

	h.Nino.http = { sendRequest : function( uri, method, callback, data ) {
		const payload = JSON.parse( data.data );
		sent.push( { endpoint : data.action.replace( 'keys/', '' ), payload : payload } );
		const answer = () => { const [ status, body ] = serve( data.action, payload ); callback( { status : status, responseJSON : body } ) };
		if( handle.hold === true && data.action === 'keys/save' )
			handle.held.push( () => { handle.hold = false; answer() } );
		else
			answer();
	} };

	const say = h.ui.say;
	h.ui.say = function( words, opts ) { told.push( words ); return say.call( this, words, opts ) };
	const touch = h.admin.touch;
	h.admin.touch = function( topic, uri ) { touches.push( topic ); return touch.call( this, topic, uri ) };
	h.box.KeyboardEvent = function( type, init ) { Object.assign( this, { type : type }, init ) };

	// Every address the tab writes or links to is read back by the router as the same screen and the same parts
	[ 'set', 'go', 'href' ].forEach( name => {
		const real = h.router[name];
		h.router[name] = function( screen, parts ) { codec.check( screen, parts ); return real.call( this, screen, parts ) };
	} );

	// The rich-text editor, which keeps what it is given and says what a field marked it with
	h.admin.htmlEditor = { create : ( mount, value, maxlength, rows, format ) => {
		mount.dataset.editor = 'yes';
		const box = element('div');
		box.setAttribute( 'contenteditable', 'true' );
		mount.appendChild( box );
		let kept = String( value ?? '' );
		const marks = {};
		const editor = { maxlength : maxlength, format : format, given : String( value ?? '' ), marks : marks, destroyed : false, mount : mount, box : box,
			getValue : () => kept, setValue : v => { kept = v }, focus(){}, mark : state => Object.assign( marks, state ), destroy : () => { editor.destroyed = true } };
		editors.push( editor );
		return editor;
	} };

	if( options.hash !== undefined )
		h.box.location.hash = options.hash;
	else
		h.router.set( 'keys', [] );

	load( '_admin/Nino/Modules/Text/assets/textkeys.js', h.context );
	load( '_admin/Nino/Modules/Text/assets/keys.js', h.context );

	const panel = h.admin.keys;
	const dialog = () => h.dialogs.filter( d => d.open ).pop() ?? null;
	const record = () => h.dialogs.filter( d => d.open && has( d, 'nino-admin-record' ) ).pop() ?? null;
	const rows = () => findAll( form, el => el.tagName === 'TR' && el.dataset.row !== undefined );
	const w = { h, panel, list, form, sent, told, touches, editors, handle, response, entry, dialog, record, rows,
		open : () => { panel.showCurrent(); return w },
		listRows : () => findAll( list, el => el.tagName === 'LI' && el.dataset.row !== undefined ),
		listRow : id => w.listRows().find( li => li.dataset.row === id ),
		linkOf : id => byClass( w.listRow( id ), 'nino-admin-row-open' )[0],
		lineOf : id => tag( w.listRow( id ), 'small' )[0].textContent,
		ids : () => rows().map( el => el.dataset.row ),
		row : key => rows().find( el => el.dataset.row === key ),
		cells : key => w.row( key ).children.filter( el => el.tagName === 'TD' ),
		heads : () => findAll( form, el => el.tagName === 'TH' && el.dataset.key !== undefined ),
		selects : () => tag( form, 'select' ).filter( el => has( el, 'nino-admin-filterbar-select' ) && has( el, 'nino-admin-table-sortby' ) === false ),
		search : () => tag( form, 'input' ).find( el => el.type === 'search' ),
		listSearch : () => tag( list, 'input' ).find( el => el.type === 'search' ),
		pencil : key => byClass( w.row( key ), 'nino-admin-row-actions' )[0]?.children[0],
		writes : () => sent.filter( c => writes.indexOf( c.endpoint ) !== -1 ),
		titleOf : d => byClass( d, 'nino-admin-dialog-title' )[0]?.textContent ?? null,
		subtitleOf : d => byClass( d, 'nino-admin-dialog-subtitle' )[0]?.textContent ?? '',
		fieldOf : ( d, code ) => findAll( d, el => el.dataset.locale === code )[0],
		fields : d => findAll( d, el => el.dataset.locale !== undefined ),
		controlOf : ( d, code ) => findAll( w.fieldOf( d, code ), el => el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' )[0],
		counterOf : ( d, code ) => byClass( w.fieldOf( d, code ), 'nino-admin-char-counter' )[0],
		byId : ( d, stem ) => findAll( d, el => typeof el.id === 'string' && el.id.indexOf( stem ) === 0 )[0],
		format : d => w.byId( d, 'keys-format-' ),
		limit : d => w.byId( d, 'keys-limit-' ),
		switches : d => findAll( d, el => el.tagName === 'INPUT' && el.type === 'checkbox' && has( el.parentNode, 'nino-admin-switch' ) ),
		global : d => w.switches( d ).find( b => allText( b.parentNode ).indexOf( W('/_admin/keys/label/global') ) !== -1 ),
		hidden : d => w.switches( d ).find( b => allText( b.parentNode ).indexOf( W('/_admin/keys/label/hide') ) !== -1 ),
		errorsOf : d => byClass( d, 'nino-admin-field-error' ),
		button : ( d, text ) => tag( d, 'button' ).find( b => b.textContent === text ),
		lineText : d => byClass( d, 'nino-admin-status' )[0]?.textContent ?? '',
		type : ( d, code, value ) => { typeKey( w.controlOf( d, code ), value ); h.dirty.refresh() },
		save : d => fire( tag( d, 'form' )[0], 'submit' ),
		openRow : key => { const p = w.pencil( key ); fire( p, 'click', { currentTarget : p } ); return record() },
		cross : d => tag( d, 'button' ).find( b => has( b, 'nino-admin-dialog-close' ) ),
		close : d => { const asked = h.went.length; fire( w.cross( d ), 'click' ); if( h.went.length > asked && dialog() === null ) w.back() },
		back : () => { h.back(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		forward : () => { h.forward(); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		step : address => { h.box.location.hash = address; h.router.arrive(); panel.showCurrent(); h.hashchange() },
		// An address typed or followed: the browser makes an entry of it
		typed : address => { h.box.history.pushState( null, '', address ); h.router.arrive(); panel.showCurrent(); h.hashchange() },
		go : code => { h.admin.workLocale.current = code; h.admin.workLocale._followers.forEach( follower => follower.handler( code ) ) },
		answerAsk : text => { const top = dialog(); const asked = h.went.length; fire( w.button( top, text ), 'click' ); if( h.went.length > asked && dialog() === null ) w.back(); return top },
		hash : () => h.hash(),
		scanButton : () => byClass( list, 'nino-admin-list-actions' )[0].querySelectorAll('.nino-admin-btn-secondary')[0],
		newButton : () => byClass( list, 'nino-admin-list-actions' )[0].querySelectorAll('.nino-admin-btn-primary')[0],
	};

	return w;
}

// The page, shown and its keys read: the list is up, nothing is open
const keysShown = ( response, options ) => keysPage( response ?? keysResponse(), options ).open();
// ... and a row of it open
const keysRow = ( id, response, options ) => { const w = keysShown( response, options ); fire( w.linkOf( id ), 'click', { target : w.linkOf( id ) } ); return w };
// ... and a key of it open in the record
const keysRecord = ( id, key, response, options ) => { const w = keysRow( id, response, options ); w.openRow( key ); return w };
const KEYS = [ '/_nino/webpage/services/uri', '/_nino/webpage/services/name', '/_nino/webpage/services/title', '/_nino/webpage/services/description', '/template/page-services/intro/title', '/template/page-services/intro/text',
	'/template/page-services/item-1/title', '/template/page-services/item-1/text', '/template/page-services/item-2/title', '/template/page-services/legal/note' ];
const K1 = '/template/page-services/intro/title', K2 = '/template/page-services/intro/text', K3 = '/template/page-services/item-1/title', K4 = '/template/page-services/item-2/title', K5 = '/template/page-services/item-1/text';


// --- the first level: the list, its counts, the search -------------------------------------------------------------------------------------------------------

{
	const k = keysShown();
	const text = openedText( textResponse() );
	const rowOpens = list => opens( list );

	check( 'the first read asks for the keys, once, and for nothing else', k.sent.length === 1 && k.sent[0].endpoint === 'list' && JSON.stringify( k.sent[0].payload ) === '{}' );
	check( 'the Keys tab has the groups of the Text panel as the list of the workbench - with the hidden keys and the words of the workbench, which it exists to show - each a link with an address of its own', k.list.querySelectorAll('ul.nino-admin-list').length > 0
		&& rowOpens( k.list ).some( a => keyOf( a ) === '_admin' ) && rowOpens( k.list ).some( a => keyOf( a ) === 'project/mail' ) && rowOpens( k.list ).every( a => a.tagName === 'A' && a.href === keyAddress( keyOf( a ) ) )
		&& k.list.querySelectorAll('li').every( li => li.classList.contains('nino-admin-list-row') ) );
	const nameOfRow = ( t, group ) => rowOpens( t.list ).find( r => keyOf( r ) === group ).querySelector('strong').textContent;
	check( '...and a group has the one name in both tabs: the pages by their routes, the others by the words of the vocabulary - "Leistungen", not "Page services"',
		[ 'template/page-services', 'project/company', 'feature/consent', 'template/frame-header' ].every( g => nameOfRow( k, g ) === nameOfRow( text, g ) ) && nameOfRow( k, 'template/page-services' ) === 'Leistungen' && nameOfRow( k, 'template/frame-header' ) === W('/_admin/common/word/frame-header') );
	check( '...and the same height: the same row of the block, two lines', [ 'template/page-services', 'project/company' ].every( g => rowOpens( k.list ).find( r => keyOf( r ) === g ).parentNode.parentNode.className === rowOpens( text.list ).find( r => keyOf( r ) === g ).parentNode.parentNode.className ) );
	check( 'the bar of the list holds the scan and the new key, with the main button for the new key and nothing else', ( () => { const bar = byClass( k.list, 'nino-admin-list-actions' )[0]; return bar !== undefined && tag( bar, 'button' ).length === 2
		&& byClass( bar, 'nino-admin-btn-primary' ).length === 1 && byClass( bar, 'nino-admin-btn-primary' )[0].textContent === W('/_admin/keys/label/new') && byClass( bar, 'nino-admin-btn-secondary' )[0].textContent === W('/_admin/keys/label/scan') } )() );
	check( 'there is no switch of a language in the tab', tag( k.list, 'select' ).length === 0 );
	check( 'the page is at the address of the list, and the table of a row is not shown', k.hash() === '#text/keys' && has( k.form, 'admin-hidden' ) === true && has( k.list, 'admin-hidden' ) === false );
}

{
	// the second line of a row is counts: how many keys, and how many of them are hidden
	const k = keysShown();
	const hidden = id => k.panel._model.rows[id].entries.filter( e => e.blacklisted === true ).length;

	check( 'the second line of a row is how many keys it holds and - where there are any - how many are hidden from the Text panel, and no running text', k.lineOf('template/page-services') === F('/_admin/keys/count/keys',10)+ ' · '+ F('/_admin/keys/count/hidden',1)
		&& hidden('template/page-services') === 1 && k.lineOf('template/page-services').indexOf( 'Beratung' ) === -1 );
	check( '...a row with no key hidden says only how many', k.lineOf('project/company') === F('/_admin/keys/count/keys',4) && hidden('project/company') === 0 && k.lineOf('template/common') === F('/_admin/keys/count/keys',2) );
	check( '...one key is the singular, in the words of the tab', k.lineOf('template/frame-header') === F('/_admin/keys/count/key',1));
	check( '...a row of the system that holds only hidden keys says so', k.lineOf('_admin') === F('/_admin/keys/count/key',1)+ ' · '+ F('/_admin/keys/count/hidden',1) );
}

{
	// the counts follow what is saved: a key that is hidden is one more on the line of its row
	const k = keysRecord( 'template/page-services', K1 );
	tick( k.hidden( k.record() ) );
	k.save( k.record() );
	k.close( k.record() );
	fire( byClass( k.form, 'nino-admin-back-link' )[0], 'click' );
	check( 'the counts follow a save that hides a key: the list says it when it is shown again, and the row that held the key is the one that was open', k.dialog() === null && k.lineOf('template/page-services') === F('/_admin/keys/count/keys',10)+ ' · '+ F('/_admin/keys/count/hidden',2) );
}

{
	// the search with the hidden keys, and the chip for them alone
	const k = keysShown();
	const box = k.listSearch();
	const chip = () => byClass( k.list, 'nino-admin-chip' )[0];
	const hits = () => byClass( k.list, 'admin-text-hit-key' ).map( c => c.textContent );

	check( 'the search is the Text panel\'s, with a chip of the workbench for the hidden keys', box.attributes['aria-label'] === W('/_admin/text/label/search') && chip().id === 'keys-list-hidden' && chip().textContent === W('/_admin/keys/label/hidden-only') && chip().attributes['aria-pressed'] === 'false' );
	box.value = 'netzwerk';
	fire( box, 'input', { target : box } );
	check( 'it finds a hidden key as well, as a row of the list', k.listRows().length === 1 && hits().join() === '/_admin/elements/field/social/title' && opens( k.list )[0].href === keyAddress( '_admin', '/_admin/elements/field/social/title' ) );
	box.value = '';
	fire( box, 'input', { target : box } );
	fire( chip(), 'click', { target : chip() } );
	check( 'only the hidden ones: the chip is on, and the hits are the keys hidden from the Text panel', chip().attributes['aria-pressed'] === 'true' && hits().sort().join() === '/_admin/elements/field/social/title,/_nino/webpage/services/uri,/project/mail/color/backdrop' );
	fire( chip(), 'click', { target : chip() } );
	box.value = 'consulting';
	fire( box, 'input', { target : box } );
	check( 'a hit in another language has that language\'s code as a small button, and it asks the workbench for it', ( () => {
		const code = byClass( k.list, 'nino-admin-row-actions' )[0].children[0]; const asked = [];
		const choose = k.h.admin.workLocale.choose; k.h.admin.workLocale.choose = c => asked.push( c ); fire( code, 'click' ); k.h.admin.workLocale.choose = choose;
		return code.textContent === 'en_US' && asked.join() === 'en_US' } )() );
	box.value = 'beratung';
	fire( box, 'input', { target : box } );
	const before = k.h.entries.length;
	fire( opens( k.list )[0], 'click', { target : opens( k.list )[0] } );
	check( 'a hit opens the record of its key over the table of its row, at the address of the key, one entry more in the history', k.record() !== null && k.hash() === keyAddress( 'template/page-services', K3 ) && has( k.form, 'admin-hidden' ) === false && k.ids().length === KEYS.length && k.h.entries.length === before + 1
		&& k.titleOf( k.record() ).indexOf( 'Titel' ) !== -1 && k.subtitleOf( k.record() ) === K3 );
}

{
	// the singular of a count is another word than the plural in English, where the words are not the same
	const k = keysShown( keysResponse(), { words : 'en_US' } );
	const E = workbenchWords('en_US');

	check( 'a row with one key says "1 key", the others "n keys", in English', k.lineOf('template/frame-header') === E['/_admin/keys/count/key'].replace( '%d', '1' ) && k.lineOf('template/frame-header') === '1 key' && k.lineOf('project/company') === '4 keys' );
}

// --- the keys of a row: the standard directory -------------------------------------------------------------------------------------------------------------

{
	const k = keysRow('template/page-services');

	check( 'a row opens as its own view at its address, under the way back and the name of the row - one link, to the tab by its name', k.hash() === keyAddress('template/page-services') && has( k.form, 'admin-hidden' ) === false && has( k.list, 'admin-hidden' ) === true
		&& byClass( k.form, 'nino-admin-back-link' ).length === 1 && byClass( k.form, 'nino-admin-back-link' )[0].textContent === F('/_admin/common/label/back-to',W('/_admin/text/label/back-name')) && byClass( k.form, 'nino-admin-contextbar-title' )[0].textContent === 'Leistungen' );
	check( '...and under it the standard directory - no box to choose rows by - and under the table the bar of the list: the scan beside the main button that makes a key', byClass( k.form, 'nino-admin-directory' ).length === 1 && tag( k.form, 'input' ).filter( el => el.type === 'checkbox' ).length === 0
		&& byClass( k.form, 'nino-admin-list-actions' ).length === 1 && byClass( k.form, 'nino-admin-btn-primary' ).map( b => b.textContent ).join() === W('/_admin/keys/label/new') && byClass( byClass( k.form, 'nino-admin-list-actions' )[0], 'nino-admin-btn-secondary' )[0].textContent === W('/_admin/keys/label/scan') );
	check( 'the rows are the keys of the row in the order of its sections - the details of the page first - and of the templates', k.ids().join() === KEYS.join() );
	check( '...the columns are the name, the section, the text, the format, the limit and the state', k.heads().map( el => el.dataset.key ).join() === 'name,place,text,format,limit,states' );
	check( '...none of them sorts: the order is the content', k.heads().every( el => el.attributes['aria-sort'] === undefined || el.attributes['aria-sort'] === 'none' ) && tag( k.form, 'th' ).every( th => tag( th, 'button' ).length === 0 ) );
	check( '...the heads say what they hold, the one of the text in the language on screen', k.heads().map( el => el.textContent ).join() === [ W('/_admin/text/col/name'), W('/_admin/text/col/section'), F('/_admin/text/col/text','Deutsch'), W('/_admin/keys/label/format'), W('/_admin/keys/label/limit'), W('/_admin/keys/col/state') ].join() );

	const one = k.cells( K1 );
	check( 'a row says the name of the key strong, its key under it in the face of an identifier, as text', byClass( one[0], 'admin-text-name-label' )[0].tagName === 'STRONG' && byClass( one[0], 'admin-text-name-label' )[0].textContent === 'Titel' && byClass( one[0], 'admin-text-name-key' )[0].textContent === K1 );
	check( '...the section by its name - for the details of a page with the path - and the text of the working language, clamped, as plain text', one[1].textContent === 'Einleitung' && k.cells('/_nino/webpage/services/name')[1].textContent === W('/_admin/text/label/page-details')+ ' (/services)'
		&& byClass( one[2], 'nino-admin-table-clamp' )[0].textContent === 'Leistungen' && byClass( k.cells( K5 )[2], 'nino-admin-table-clamp' )[0].textContent === 'Wir beraten Sie gern' );
	check( '...the format and the limit "Automatic: ..." where they are not set, with what is at work', one[3].textContent === F('/_admin/keys/col/auto',W('/_admin/keys/format/plain')) && one[4].textContent === F('/_admin/keys/col/auto',150) && k.cells( K5 )[3].textContent === F('/_admin/keys/col/auto',W('/_admin/keys/format/blocks')) );
	check( '...and no badge where the key is neither global nor hidden', byClass( one[5], 'nino-admin-badge' ).length === 0 );

	const states = key => byClass( k.cells( key )[5], 'nino-admin-badge' ).map( b => b.textContent+ ':'+ ( b.dataset.state ?? '' ) );
	check( 'a key that is one text for all languages and hidden says both, a word each - the colour is not the only thing that says it', states('/_nino/webpage/services/uri').join() === W('/_admin/text/label/all-languages')+ ':,'+ W('/_admin/keys/label/blacklist')+ ':warn' );
	check( 'the pencil of a key is named for it, one for each key - every key of the tab opens', k.pencil( K1 ).attributes['aria-label'] === F('/_admin/keys/row/open','Einleitung › Titel') && KEYS.every( key => k.pencil( key ) !== undefined ) );
}

{
	// a format and a limit that are set are said as they are
	const response = keysResponse();
	const e1 = response.keys.find( e => e.key === K1 ), e2 = response.keys.find( e => e.key === K3 );
	Object.assign( e1, { formatSet : true, format : 'lines', maxlengthSet : true, maxlength : 90 } );
	Object.assign( e2, { formatSet : true, format : 'blocks', html : true, maxlengthSet : false } );
	const k = keysRow( 'template/page-services', response );

	check( 'a format and a limit that were set are said as they are, a format that was not as "Automatic: ..." with the format that is at work', k.cells( K1 )[3].textContent === W('/_admin/keys/format/lines') && k.cells( K1 )[4].textContent === '90'
		&& k.cells( K3 )[3].textContent === W('/_admin/keys/format/blocks') && k.cells( K3 )[4].textContent === F('/_admin/keys/col/auto',150) );
}

{
	// the category, the select "Show", the search
	const response = keysResponse();
	response.keys.find( e => e.key === K2 ).blacklisted = true;
	Object.assign( response.keys.find( e => e.key === '/template/page-services/legal/note' ), { global : true, values : { '*' : 'Ohne Gewähr' } } );
	const k = keysRow( 'template/page-services', response );
	const [ category, show ] = k.selects();

	check( 'the section is the category, left of the search: all sections, then the sections in their order, the details of a page with its path', category !== undefined && show !== undefined && k.selects().length === 2
		&& tag( category, 'option' ).map( o => o.textContent ).join() === [ W('/_admin/text/filter/sections'), W('/_admin/text/label/page-details')+ ' (/services)', 'Einleitung', 'Eintrag 1', 'Eintrag 2', 'Legal' ].join() );
	check( '...and one select "Show" after it: all keys, the hidden ones, the ones that are one text for all languages', show.attributes['aria-label'] === W('/_admin/text/filter/show')
		&& tag( show, 'option' ).map( o => o.textContent ).join() === [ W('/_admin/keys/filter/all'), W('/_admin/keys/filter/hidden'), W('/_admin/keys/filter/global') ].join() );

	choose( category, 'item-1' );
	check( 'a section narrows the rows to its keys, in their order', k.ids().join() === [ K3, K5 ].join() );
	choose( category, '' );
	choose( show, 'hidden' );
	check( 'Show "hidden" keeps the keys hidden from the Text panel', k.ids().join() === [ '/_nino/webpage/services/uri', K2 ].join() );
	choose( show, 'global' );
	check( '...Show "one text for all languages" the ones that are the same in every language', k.ids().join() === [ '/_nino/webpage/services/uri', '/template/page-services/legal/note' ].join() );
	choose( show, '' );
	check( '...and "all keys" is every one again', k.ids().join() === KEYS.join() );

	// the search: the name, the key, the section, the text of every language
	const found = query => { const box = k.search(); box.value = query; fire( box, 'input', { target : box } ); return k.ids().join() };
	check( 'the search finds a key by its text in the language on screen', found( 'Beratung' ) === K3 );
	check( '...by its text in another language', found( 'consulting' ) === K3 );
	check( '...by its name, its key and its section', found( 'eintrag 2' ) === K4 && found( 'legal/note' ) === '/template/page-services/legal/note' && found( 'Einleitung' ) === [ K1, K2 ].join() );
	check( '...and says it finds nothing in the words of the tab', found( 'zzz' ) === '' && byClass( k.form, 'nino-admin-empty' )[0].textContent === W('/_admin/text/msg/nothing') );
	found( '' );

	// the state is kept per row, while the tab lives
	choose( k.selects()[1], 'hidden' );
	fire( byClass( k.form, 'nino-admin-back-link' )[0], 'click' );
	check( 'Back is the list, the focus on the row that was open, the address the list\'s', has( k.list, 'admin-hidden' ) === false && k.hash() === '#text/keys' && k.h.doc.activeElement === k.linkOf('template/page-services') );
	fire( k.linkOf('template/page-services'), 'click', { target : k.linkOf('template/page-services') } );
	check( 'the row opened again is as the person left it: the select "Show" holds what it held', k.ids().join() === [ '/_nino/webpage/services/uri', K2 ].join() && k.selects()[1].value === 'hidden' );
	fire( byClass( k.form, 'nino-admin-back-link' )[0], 'click' );
	fire( k.linkOf('template/frame-header'), 'click', { target : k.linkOf('template/frame-header') } );
	check( '...and another row has its own state: none, and no category where it has one section', k.ids().length === 1 && k.selects().length === 1 && k.selects()[0].value === '' );
}

{
	// text from the server is text: in the list, the table, the head of the record, its subtitle and the fields; a rich text goes to the editor as it is
	const BAD = '<img src=x onerror=alert(1)> "quoted" [[/key]]';
	const SAID = 'x <img src=x onerror=alert(1)> "quoted" [[/key]]';
	const KEY = '/template/page-services/intro/bad', ENTITY = '/template/page-services/intro/entity', ODD = '/template/page-services/<b>sec/<img src=x onerror=alert(2)>';
	const response = keysResponse();
	response.keys.push( textEntry( KEY, { de_DE : BAD, en_US : BAD } ) );
	response.keys.push( textEntry( ENTITY, { de_DE : 'x &lt;img src=x onerror=alert(1)&gt; "quoted" [[/key]]', en_US : '' } ) );
	response.keys.push( textEntry( ODD, { de_DE : 'x', en_US : 'y' } ) );
	response.keys.push( textEntry( '/template/page-services/intro/rich', { de_DE : '<p>'+ BAD+ '</p>', en_US : '' }, { html : true, format : 'blocks' } ) );
	const k = keysRow( 'template/page-services', response );
	const noMarkup = root => tag( root, 'img' ).length === 0 && tag( root, 'b' ).length === 0;

	check( 'a text, a name and a key holding markup are text in the table - nothing in the page is made of them', noMarkup( k.form ) && byClass( k.cells( ENTITY )[2], 'nino-admin-table-clamp' )[0].textContent === SAID && byClass( k.cells( KEY )[0], 'admin-text-name-key' )[0].textContent === KEY
		&& byClass( k.cells( KEY )[2], 'nino-admin-table-clamp' )[0].textContent === k.panel._find( KEY ).text && noMarkup( k.list ) );
	const d = k.openRow( KEY );
	check( '...in the head of the record, its subtitle and its fields', noMarkup( d ) && k.subtitleOf( d ) === KEY && k.controlOf( d, 'de_DE' ).value === BAD && k.controlOf( d, 'en_US' ).value === BAD && k.hash() === keyAddress( 'template/page-services', KEY ) );
	k.close( d );
	const r = k.openRow( '/template/page-services/intro/rich' );
	check( '...a text with markup is given to the editor as it is, and nothing else makes elements of it', k.editors.some( e => e.given === '<p>'+ BAD+ '</p>' ) && noMarkup( r ) );
	k.close( r );
	fire( byClass( k.form, 'nino-admin-back-link' )[0], 'click' );
	const row = k.panel._model.rowOf[ODD];
	fire( k.linkOf( row ), 'click', { target : k.linkOf( row ) } );
	check( '...a section and a key that are no words of the grammar are text as well, in the row they fall into and in its record', noMarkup( k.form ) && k.ids().indexOf( ODD ) !== -1 && noMarkup( k.openRow( ODD ) ) && k.subtitleOf( k.record() ) === ODD );
	k.close( k.record() );
	fire( byClass( k.form, 'nino-admin-back-link' )[0], 'click' );
	const box = k.listSearch();
	box.value = 'onerror';
	fire( box, 'input', { target : box } );
	check( '...and in the hits of the search of the list: the words found are marks of text', noMarkup( k.list ) && byClass( k.list, 'admin-text-hit-key' ).length > 0 );
}

// --- the record of one key ---------------------------------------------------------------------------------------------------------------------------------

const PAGE = 'template/page-services';

{
	const k = keysRow( PAGE );
	const before = k.h.entries.length, index = k.h.index();
	const d = k.openRow( K1 );

	check( 'a pencil opens the record of that key, over the table: the place and the name in its head, the key under it, at its address as one step of the history', d !== null && k.titleOf( d ) === 'Einleitung › Titel' && k.subtitleOf( d ) === K1
		&& k.hash() === keyAddress( PAGE, K1 ) && k.h.entries.length === before + 1 && has( k.form, 'admin-hidden' ) === false && k.ids().length === KEYS.length );
	check( '...the dialog is the large one of a record, with the step to the keys of the table around it, its place and the cross', has( d, 'nino-admin-record' ) && has( d, 'nino-admin-dialog--large' ) && byClass( d, 'nino-admin-record-count' )[0].textContent === F('/_admin/common/record/position',5,10)
		&& tag( d, 'button' ).some( b => b.attributes['aria-label'] === W('/_admin/keys/record/prev') ) && tag( d, 'button' ).some( b => b.attributes['aria-label'] === W('/_admin/keys/record/next') ) && k.cross( d ) !== undefined );

	// the two groups
	const sets = tag( d, 'fieldset' );
	check( 'the body is two groups, each a fieldset with its legend: the text in every language, and the settings', sets.length === 2 && sets.map( s => s.children[0].textContent ).join() === [ W('/_admin/keys/label/text'), W('/_admin/keys/label/settings') ].join()
		&& d.querySelector('#keys-record') !== null && has( d.querySelector('#keys-record'), 'nino-admin-dialog-fields' ) && sets.every( s => s.parentNode.id === 'keys-record' ) );
	check( '...the text has a field for each language, the working one first and marked, and each says the count against the limit of the key', k.fields( d ).map( f => f.dataset.locale ).join() === 'de_DE,en_US'
		&& nameOf( k.fieldOf( d, 'de_DE' ) ) === 'Deutsch ('+ W('/_admin/text/label/working')+ ')' && nameOf( k.fieldOf( d, 'en_US' ) ) === 'English' && k.controlOf( d, 'de_DE' ).value === 'Leistungen' && k.controlOf( d, 'en_US' ).value === 'Services'
		&& k.counterOf( d, 'de_DE' ).textContent === '10 / 150' && k.counterOf( d, 'en_US' ).textContent === '8 / 150' );
	check( '...no field shows its key under it - the key is in the head - and none has the badge of a text for all languages', byClass( d, 'admin-text-meta' ).every( m => tag( m, 'code' ).length === 0 ) && byClass( d.querySelector('#keys-record'), 'nino-admin-badge' ).length === 0 );

	const group = sets[1];
	check( 'the settings are a format, a limit, one text for all languages and whether the key is hidden from the Text panel - each a field of the workbench with its own name and its own help behind the (?)', ( () => {
		const select = k.format( d ), limit = k.limit( d ), boxes = k.switches( d );
		return select.children.map( o => o.value ).join() === 'auto,plain,inline,lines,blocks' && select.value === 'auto' && limit.value === '' && limit.min === '1' && limit.type === 'number' && limit.placeholder === W('/_admin/keys/placeholder/limit')
			&& boxes.length === 2 && boxes.every( b => b.checked === false ) && allText( group ).indexOf( W('/_admin/keys/label/global') ) !== -1 && allText( group ).indexOf( W('/_admin/keys/label/hide') ) !== -1
			&& byClass( group, 'nino-admin-help' ).length === 4 && byClass( group, 'nino-admin-help-text' ).map( h => h.textContent ).sort().join() === [ 'format', 'limit', 'global', 'hidden' ].map( n => W('/_admin/keys/help/'+ n) ).sort().join() } )() );
	check( '...the limit says its unit in its name', allText( k.limit( d ).parentNode ).indexOf( W('/_admin/common/unit/characters') ) !== -1 );

	// the bar
	const bar = byClass( d, 'nino-admin-actionbar' )[0];
	const buttons = tag( bar, 'button' ).filter( b => b.hidden !== true );
	check( 'the bar has "Delete text key" at the left in red, and at the right "Save and continue", "Rename" and the one main button "Save" - and no Cancel, no Close', buttons.map( b => b.textContent ).join() === [ W('/_admin/keys/confirm/delete-title'), W('/_admin/common/label/save-next'), W('/_admin/common/label/rename'), W('/_admin/common/label/save') ].join()
		&& has( buttons[0], 'nino-admin-btn-danger' ) && byClass( bar, 'nino-admin-btn-primary' ).length === 1 && byClass( bar, 'nino-admin-btn-primary' )[0].textContent === W('/_admin/common/label/save') && byClass( bar, 'nino-admin-btn-danger' ).length === 1 );
	check( '...a button that reads the keys again is not there while nothing went wrong', tag( bar, 'button' ).filter( b => b.textContent === W('/_admin/keys/label/reload') ).every( b => b.hidden === true ) );

	// closing: the cross takes the entry off the history, the table is as it was, the focus is on the row
	k.close( d );
	check( 'the cross closes the record: the address is the row\'s, the table is as it was and the focus is on the row of the key', k.record() === null && k.hash() === keyAddress( PAGE ) && k.h.index() === index && k.h.doc.activeElement === k.row( K1 ) );
}

{
	// a key that is one text for all languages and hidden: the one field, the two switches on, no button to rename a key of the system
	const k = keysRow( PAGE );
	const d = k.openRow( '/_nino/webpage/services/uri' );

	check( 'a key that is one text for all languages has the one field, which says so by its name and is not marked as a language, and the switches say what the key is', k.fields( d ).map( f => f.dataset.locale ).join() === '*' && nameOf( k.fieldOf( d, '*' ) ) === W('/_admin/text/label/all-languages') && k.controlOf( d, '*' ).value === '/services'
		&& k.global( d ).checked === true && k.hidden( d ).checked === true );
	check( '...a key of the system has no button to rename it - its name is what the code that reads it asks for - and one somebody made up has', tag( byClass( d, 'nino-admin-actionbar' )[0], 'button' ).filter( b => b.textContent === W('/_admin/common/label/rename') ).every( b => b.hidden === true )
		&& ( () => { k.close( d ); const e = k.openRow( K1 ); return tag( byClass( e, 'nino-admin-actionbar' )[0], 'button' ).filter( b => b.textContent === W('/_admin/common/label/rename') ).every( b => b.hidden !== true ) } )() );
}

{
	// a key of the system is not deleted and its shape is not changed: no button to delete it, the format and the one text for all languages locked; the limit and the hiding are not
	const k = keysRow( PAGE );
	const system = k.openRow( '/_nino/webpage/services/uri' );
	const deleteLabel = W('/_admin/keys/confirm/delete-title');
	const deleteOf = d => tag( byClass( d, 'nino-admin-actionbar' )[0], 'button' ).filter( b => b.textContent === deleteLabel );

	check( 'the record of a key of the system has no button to delete it, and its format and its switch of one text for all languages are locked', deleteOf( system ).every( b => b.hidden === true ) && k.format( system ).disabled === true && k.global( system ).disabled === true );
	check( '...the limit and the hiding are not locked', k.limit( system ).disabled !== true && k.hidden( system ).disabled !== true );
	k.close( system );
	// The detail of a page that holds blocks can be given plain; the switch of one text for all languages stays locked
	const marked = keysResponse();
	Object.assign( marked.keys.find( e => e.key === '/_nino/webpage/services/description' ), { format : 'blocks', formatSet : true, html : true } );
	const repair = keysRow( PAGE, marked );
	const detail = repair.openRow( '/_nino/webpage/services/description' );
	check( '...the format of the detail of a page that is not plain stays free, so that it can be given plain, and its switch of one text for all languages is locked', repair.format( detail ).disabled !== true && repair.global( detail ).disabled === true );
	const ordinary = k.openRow( K1 );
	check( '...a key somebody made has the button, and both controls are free', deleteOf( ordinary ).length === 1 && deleteOf( ordinary ).every( b => b.hidden !== true ) && k.format( ordinary ).disabled !== true && k.global( ordinary ).disabled !== true );
}

{
	// a text with markup is the rich-text editor, in every language; a language the website does not serve yet is marked
	const response = keysResponse();
	response.locales = [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : false } ];
	const k = keysRow( PAGE, response );
	const d = k.openRow( K5 );

	check( 'a text with markup is an editor in each language, given the text as it is stored, with the limit of the key', k.editors.length === 2 && k.editors.every( e => e.mount.dataset.editor === 'yes' ) && k.editors[0].given === '<p>Wir beraten Sie gern</p>' && k.editors[0].maxlength === 150 && k.editors[0].format === 'blocks' );
	check( '...a language the website does not serve yet is marked as the select of the working language marks it', nameOf( k.fieldOf( d, 'en_US' ) ) === 'English ('+ W('/_admin/common/word/inactive')+ ')' );
}

{
	// a reload at the address of a key opens the record over the table of its row - no step of the history of its own; Back closes it
	const k = keysPage( keysResponse(), { hash : keyAddress( PAGE, K1 ) } ).open();

	check( 'a reload at the address of a key opens the record of it over the table of its row, as one entry of the history', k.record() !== null && k.subtitleOf( k.record() ) === K1 && k.ids().length === KEYS.length && k.hash() === keyAddress( PAGE, K1 ) && k.h.entries.length === 1 && has( k.list, 'admin-hidden' ) );
	k.close( k.record() );
	check( '...and closed by the cross it leaves the table at the row\'s address, written over the one that was there', k.record() === null && k.hash() === keyAddress( PAGE ) && k.ids().length === KEYS.length );

	const b = keysRecord( PAGE, K1 );
	b.back();
	check( 'Back closes the record: the table of the row, at its address', b.record() === null && b.hash() === keyAddress( PAGE ) && b.ids().length === KEYS.length && has( b.form, 'admin-hidden' ) === false );
	b.forward();
	check( '...and Forward opens it again', b.record() !== null && b.subtitleOf( b.record() ) === K1 && b.hash() === keyAddress( PAGE, K1 ) );
}

{
	// a key the row does not hold, and a row nobody has
	const k = keysPage( keysResponse(), { hash : keyAddress( PAGE, '/template/nope/a/b' ) } ).open();

	check( 'an address that names a key the row does not hold is the table of the row with the notice that it is not there - the address is the row\'s', k.record() === null && byClass( k.form, 'nino-admin-notice' ).some( n => n.textContent === W('/_admin/keys/record/missing') ) && k.hash() === keyAddress( PAGE ) );
	const l = keysPage( keysResponse(), { hash : keyAddress( 'nothing', K1 ) } ).open();
	check( '...and a row nobody has is the list, at its address', l.record() === null && has( l.list, 'admin-hidden' ) === false && l.hash() === '#text/keys' );
	const n = keysShown();
	n.step( keyAddress( PAGE, K1 ) );
	check( 'an address typed while the list is up opens the table of the row and the record of the key', n.record() !== null && n.ids().length === KEYS.length && n.hash() === keyAddress( PAGE, K1 ) );
	n.step( keyAddress( PAGE, K3 ) );
	check( '...and another key of the row is shown in the same record, with no entry of the history of its own', n.record() !== null && n.subtitleOf( n.record() ) === K3 && n.hash() === keyAddress( PAGE, K3 ) );
	n.step( '#text/keys' );
	check( '...the list closes it and shows the list', n.record() === null && has( n.list, 'admin-hidden' ) === false && n.hash() === '#text/keys' );
}

{
	// the key %40new is a row called @new, never the form of a new key: the word is told from the key by the router
	const response = keysResponse();
	response.keys.push( textEntry( '/@new/a/b', { de_DE : 'x', en_US : 'y' } ) );
	const k = keysPage( response, { hash : '#text/keys/%40new' } ).open();

	check( 'a row called @new (%40new) is a row, and the word @new is the form of a new key: the address says which', k.record() === null && k.dialog() === null && has( k.form, 'admin-hidden' ) === false && k.ids().join() === '/@new/a/b' && k.hash() === '#text/keys/%40new' );
	const w = keysPage( keysResponse(), { hash : '#text/keys/@new' } ).open();
	check( '...the word opens the form of a new key, and not a record of a key called @new', w.record() !== null && w.titleOf( w.record() ) === W('/_admin/keys/label/new') );
}

// --- saving: what is sent, in which order, and what the record shows after it ----------------------------------------------------------------------------------

const detailsOf = d => tag( byClass( d, 'nino-admin-dialog-details' )[0], 'li' ).map( li => li.textContent );
const CONTINUE = () => W('/_admin/common/label/save-next');
const dirtyNow = k => k.h.dirty.isDirty( [ 'keys' ] );
const lastList = k => k.sent.filter( c => c.endpoint === 'list' ).length;
// The sentence said at a control: the paragraph its aria-describedby names first (it stands after the label, not in it)
const saidAt = ( d, control ) => findAll( d, el => has( el, 'nino-admin-field-error' ) && el.id === ( control.attributes['aria-describedby'] ?? '' ).split(' ')[0] )[0];

{
	// nothing changed: nothing is sent, and what is on screen is what is stored
	const k = keysRecord( PAGE, K1 );
	const d = k.record();
	const listed = lastList( k );

	k.save( d );
	check( 'a save with nothing changed sends nothing - not a request for a text, not one for the shape, not even a read of the keys - and says that it is saved', k.writes().length === 0 && lastList( k ) === listed && k.told.at(-1) === W('/_admin/common/msg/saved') && dirtyNow( k ) === false );
	check( '...and it says it with the badge and a line that stays empty, no toast of a failure', k.lineText( d ) === '' && k.touches.length === 0 );
	k.save( k.record() );
	check( '...as often as it is asked', k.writes().length === 0 );
}

{
	// a plain text is stored with its quotes as entities: the record of the Keys tab shows what was typed and sends the stored text as the one it was read as
	const server = keysResponse();
	const held = server.keys.find( e => e.key === K1 );
	held.values.de_DE = 'Sag &quot;Hallo&quot;';
	held.typed = { de_DE : 'Sag "Hallo"' };
	const k = keysRecord( PAGE, K1, server );
	const d = k.record();

	check( 'the field of a plain text in the Keys tab shows what was typed, and the record is not unsaved', k.controlOf( d, 'de_DE' ).value === 'Sag "Hallo"' && k.controlOf( d, 'en_US' ).value === 'Services' && dirtyNow( k ) === false );
	k.save( d );
	check( '...saved as it was drawn it sends nothing', k.writes().length === 0 );
	k.type( d, 'de_DE', 'Sag "Hallo" mehr' );
	k.save( d );
	check( '...a changed text is sent as typed, with the stored text as the one it was read as', k.writes().length === 1 && JSON.stringify( k.writes()[0].payload.items[0] ) === JSON.stringify( { key : K1, locale : 'de_DE', value : 'Sag "Hallo" mehr', was : 'Sag &quot;Hallo&quot;' } ) );
}

{
	// a text: one request, for the language that was changed - and the keys are read again
	const k = keysRecord( PAGE, K1 );
	const d = k.record();
	const read = k.entry( K1 ).values.de_DE;

	k.type( d, 'de_DE', 'Unsere Leistungen' );
	check( 'a changed text is unsaved input for the shell', dirtyNow( k ) === true );
	k.save( d );
	const writes = k.writes();
	check( 'a save of a text sends the text and nothing of its shape: one request for one language, with one item, with the text it was read as', writes.map( c => c.endpoint ).join() === 'savebatch' && writes[0].payload.items.length === 1
		&& JSON.stringify( writes[0].payload.items[0] ) === JSON.stringify( { key : K1, locale : 'de_DE', value : 'Unsere Leistungen', was : read } ) );
	check( '...then it reads the keys again and draws the record from what the server holds', k.sent.at(-1).endpoint === 'list' && k.controlOf( k.record(), 'de_DE' ).value === 'Unsere Leistungen' && k.controlOf( k.record(), 'en_US' ).value === 'Services' );
	check( '...says it as a toast, the line empty, the address as it was - and the record holds no unsaved input', k.told.at(-1) === W('/_admin/common/msg/saved') && k.lineText( k.record() ) === '' && k.hash() === keyAddress( PAGE, K1 ) && dirtyNow( k ) === false );
	check( '...and tells the shell that texts were written, once for each request that wrote', k.touches.join() === 'texts' );
	check( '...the table behind the record has the text it holds now', byClass( k.cells( K1 )[2], 'nino-admin-table-clamp' )[0].textContent === 'Unsere Leistungen' );
}

{
	// two languages: one request each, in the order of the fields - the working language first
	const k = keysRecord( PAGE, K1 );
	const d = k.record();

	k.type( d, 'en_US', 'Our services' );
	k.type( d, 'de_DE', 'Unsere Leistungen' );
	k.save( d );
	check( 'a save of two languages is one request for each, one after the other in the order of the fields - the answer is keyed by the key, so two languages in one request would be one answer', k.writes().map( c => c.endpoint+ ':'+ c.payload.items.map( i => i.locale ).join() ).join() === 'savebatch:de_DE,savebatch:en_US'
		&& k.touches.join() === 'texts,texts' && dirtyNow( k ) === false );
}

{
	// what the server kept is what the field shows
	const k = keysRecord( PAGE, K1 );
	k.handle.sanitize = text => text.trim().toUpperCase();
	k.type( k.record(), 'de_DE', '  neu  ' );
	k.save( k.record() );
	check( 'a text the server changed on its way in - cleaned, converted - is the text the field shows after the save, which counts as saved', k.controlOf( k.record(), 'de_DE' ).value === 'NEU' && dirtyNow( k ) === false );
}

{
	// a key that another tab wrote since is not written over unseen, and the question is the kernel's
	const k = keysRecord( PAGE, K1 );
	const d = k.record();
	const read = k.entry( K1 ).values.de_DE;

	k.handle.stored['de_DE '+ K1] = 'Fremder Text';
	k.entry( K1 ).values.de_DE = 'Fremder Text';
	k.type( d, 'de_DE', 'Mein Text' );
	k.save( d );
	const ask = k.dialog();
	check( 'the text goes with the text it was read as, and a key that was written by another meanwhile asks the question of a refused save, naming the text by its place and its key', k.writes()[0].payload.items[0].was === read && k.writes()[0].payload.items[0].force === undefined
		&& ask !== null && k.titleOf( ask ) === W('/_admin/common/conflict/title') && allText( ask ).indexOf( K1 ) !== -1 && allText( ask ).indexOf( ' › ' ) !== -1 );
	check( '...the text stays as it was typed, at its field, and unsaved, and nothing was said as saved', k.controlOf( k.record(), 'de_DE' ).value === 'Mein Text' && dirtyNow( k ) === true && k.told.length === 0 );

	// Cancel leaves the text at its field: the next save is held to the text this tab read, and asks again
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( 'Cancel closes the question and puts the focus on the main button, the record is as it was typed', k.dialog() === k.record() && k.controlOf( k.record(), 'de_DE' ).value === 'Mein Text' && k.h.doc.activeElement === byClass( byClass( k.record(), 'nino-admin-actionbar' )[0], 'nino-admin-btn-primary' )[0] );
	k.save( k.record() );
	const second = k.writes().filter( c => c.endpoint === 'savebatch' ).at(-1).payload.items[0];
	check( 'a second save after Cancel is sent with the text of the first read, and asks the question again: the text of the other tab is not written over because the keys were read again', second.was === read && k.dialog() !== k.record() && k.handle.stored['de_DE '+ K1] === 'Fremder Text' );
	k.answerAsk( W('/_admin/common/conflict/overwrite') );
	const forced = k.writes().filter( c => c.endpoint === 'savebatch' ).at(-1).payload.items;
	check( 'Overwrite writes it once more for that language alone, forced and without the text it was read as - and the record is read again, saved', forced.length === 1 && forced[0].force === true && forced[0].was === undefined && k.handle.stored['de_DE '+ K1] === 'Mein Text'
		&& dirtyNow( k ) === false && k.told.at(-1) === W('/_admin/common/msg/saved') && k.controlOf( k.record(), 'de_DE' ).value === 'Mein Text' );
}

{
	// Reload shows what the other tab wrote, and drops what this one typed
	const k = keysRecord( PAGE, K1 );

	k.handle.stored['en_US '+ K1] = 'Fremd';
	k.type( k.record(), 'en_US', 'Mein' );
	k.save( k.record() );
	const listed = lastList( k );
	k.entry( K1 ).values.en_US = 'Fremd';
	k.answerAsk( W('/_admin/common/conflict/reload') );
	check( 'Reload reads the keys again and shows what is stored, the text of the other tab, and what was typed here is gone - nothing is unsaved, nothing was written', lastList( k ) === listed + 1 && k.controlOf( k.record(), 'en_US' ).value === 'Fremd' && dirtyNow( k ) === false && k.writes().filter( c => c.payload.items !== undefined && c.payload.items[0].force === true ).length === 0 );
}

{
	// a key whose shape changes in the save has no stamp of its text: the text moves with the shape
	const k = keysRecord( PAGE, K1 );
	const d = k.record();

	choose( k.format( d ), 'lines' );
	k.type( d, 'de_DE', 'Geändert' );
	k.save( d );
	check( 'a text written in the save that changes the shape of its key is written without the text it was read as', k.writes().filter( c => c.endpoint === 'savebatch' ).length === 1 && k.writes().filter( c => c.endpoint === 'savebatch' ).every( c => c.payload.items.every( i => i.was === undefined ) ) );
}

{
	// the shape of a key is saved with its text, by the one Save
	const k = keysRecord( PAGE, K2 );
	const d = k.record();

	choose( k.format( d ), 'lines' );
	check( 'a format that is chosen is unsaved input for the shell', dirtyNow( k ) === true );
	choose( k.format( d ), 'auto' );
	check( '...and chosen back it is not', dirtyNow( k ) === false );
	choose( k.format( d ), 'lines' );
	typeKey( k.limit( d ), '200' );
	tick( k.hidden( d ) );
	k.type( d, 'de_DE', 'Das bieten wir an: mehr' );
	k.save( d );
	const writes = k.writes();
	check( 'a save writes the shape of the key first - one request with all five fields - and then the text of each language that changed, one request each; the format a text was widened to asks nothing', writes.map( c => c.endpoint ).join() === 'save,savebatch' && k.dialog() === k.record() );
	check( '...the shape with the format, the limit as a number and both ticks', JSON.stringify( writes[0].payload ) === JSON.stringify( { key : K2, global : false, blacklisted : true, format : 'lines', maxlength : 200 } ) );
	check( '...and the text in the language of the field, without the text it was read as', writes[1].payload.items.length === 1 && writes[1].payload.items[0].key === K2 && writes[1].payload.items[0].locale === 'de_DE' && writes[1].payload.items[0].was === undefined );
	const e = k.record();
	check( 'the record is drawn again from what the server holds: the format, the limit and the ticks are the stored ones, and nothing is unsaved', k.format( e ).value === 'lines' && k.limit( e ).value === '200' && k.hidden( e ).checked === true && k.global( e ).checked === false
		&& dirtyNow( k ) === false && k.told.at(-1) === W('/_admin/common/msg/saved') && k.touches.join() === 'texts,texts' );
	check( '...the table behind it says the format, the limit and the badge of the key as they are stored', k.cells( K2 )[3].textContent === W('/_admin/keys/format/lines') && k.cells( K2 )[4].textContent === '200' && byClass( k.cells( K2 )[5], 'nino-admin-badge' ).map( b => b.textContent ).join() === W('/_admin/keys/label/blacklist') );
}

{
	// only the shape changed: one request for the shape, none for a text; only a text: none for the shape
	const k = keysRecord( PAGE, K1 );

	tick( k.hidden( k.record() ) );
	k.save( k.record() );
	check( 'a shape alone is one keys/save with all five fields - the three that did not change as they are stored - and no request for a text', k.writes().length === 1 && JSON.stringify( k.writes()[0].payload ) === JSON.stringify( { key : K1, global : false, blacklisted : true, format : 'auto', maxlength : null } ) );
	const j = keysRecord( PAGE, K1 );
	choose( j.format( j.record() ), 'blocks' );
	typeKey( j.limit( j.record() ), '120' );
	j.save( j.record() );
	check( '...a format and a limit that were chosen are written as chosen', JSON.stringify( j.writes()[0].payload ) === JSON.stringify( { key : K1, global : false, blacklisted : false, format : 'blocks', maxlength : 120 } ) && j.writes().length === 1 );
}

{
	// while the requests run the record is held, and drawn again from the server when they are done
	const k = keysRecord( PAGE, K2 );
	const d = k.record();

	typeKey( k.limit( d ), '200' );
	k.type( d, 'de_DE', 'Während es läuft' );
	k.handle.hold = true;
	k.save( d );
	const sentBefore = k.writes().length;
	k.save( d );
	check( 'while the requests of a save run the controls are held, and a second save sends nothing', k.writes().length === sentBefore && sentBefore === 1 && [ k.controlOf( d, 'de_DE' ), k.controlOf( d, 'en_US' ), k.format( d ), k.limit( d ) ].every( c => c.disabled === true ) && k.switches( d ).every( c => c.disabled === true ) );
	k.handle.held.shift()();
	const e = k.record();
	check( '...and when they are done the record is free again, drawn from what the server holds', [ k.controlOf( e, 'de_DE' ), k.format( e ), k.limit( e ) ].every( c => c.disabled === false ) && k.limit( e ).value === '200' && k.controlOf( e, 'de_DE' ).value === 'Während es läuft' && dirtyNow( k ) === false );
}

{
	// a key made global: its text is written to the one text
	const k = keysRecord( PAGE, K2 );
	const d = k.record();

	tick( k.global( d ) );
	k.type( d, 'de_DE', 'Global jetzt' );
	k.save( d );
	const batch = k.writes().at(-1);
	check( 'a key that is made global has its text written to the one text of every language, and not to a language - after the shape, which says where the text goes', k.writes().map( c => c.endpoint ).join() === 'save,savebatch' && batch.payload.items[0].locale === '*' && batch.payload.items[0].was === undefined && k.writes()[0].payload.global === true );
	const e = k.record();
	check( '...the record shows the one field of a key that is the same in every language, and it holds the text', k.fields( e ).map( f => f.dataset.locale ).join() === '*' && k.controlOf( e, '*' ).value === 'Global jetzt' && k.global( e ).checked === true && dirtyNow( k ) === false );
	tick( k.global( e ) );
	k.type( e, '*', 'Wieder je Sprache' );
	k.save( e );
	check( 'a key that is made one text per language again has the text of its one field written to the language of the workbench', k.writes().at(-1).endpoint === 'savebatch' && k.writes().at(-1).payload.items[0].locale === 'de_DE' && k.writes().at(-2).payload.global === false );
}

{
	// a format that holds less asks once, with what is stored being converted
	const k = keysRecord( PAGE, K5 );
	const d = k.record();

	choose( k.format( d ), 'plain' );
	k.save( d );
	const ask = k.dialog();
	check( 'a format that holds less than the one before is asked about once, with the heaviest weight - what is stored is converted - and the key and what happens to it as the details of the question', ask !== null && ask !== d && has( ask, 'nino-admin-dialog' ) && k.titleOf( ask ) === W('/_admin/keys/confirm/convert-title') && allText( ask ).indexOf( W('/_admin/keys/confirm/convert') ) !== -1
		&& detailsOf( ask ).length === 1 && detailsOf( ask )[0] === F('/_admin/keys/confirm/format-line',K5,W('/_admin/keys/format/blocks'),W('/_admin/keys/format/plain'),W('/_admin/keys/convert/plain')) );
	check( '...its main answer is worded like its title', k.button( ask, W('/_admin/keys/confirm/convert-title') ) !== undefined && has( k.button( ask, W('/_admin/keys/confirm/convert-title') ), 'nino-admin-btn-danger' ) );
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( 'answered no it sends nothing, and the record is as it was typed, with an empty line', k.writes().length === 0 && dirtyNow( k ) === true && k.format( k.record() ).value === 'plain' && k.lineText( k.record() ) === '' && k.dialog() === k.record() );
	k.save( k.record() );
	k.answerAsk( W('/_admin/keys/confirm/convert-title') );
	check( 'answered yes it writes the shape, and asks nothing more', k.writes().map( c => c.endpoint ).join() === 'save' && k.writes()[0].payload.format === 'plain' && k.dialog() === k.record() && dirtyNow( k ) === false );
	const j = keysRecord( PAGE, K1 );
	choose( j.format( j.record() ), 'blocks' );
	j.save( j.record() );
	check( 'a format that holds more asks nothing', j.dialog() === j.record() && j.writes().length === 1 );
	const i = keysRecord( PAGE, K1 );
	choose( i.format( i.record() ), 'inline' );
	i.save( i.record() );
	check( '...nor does one that holds more than the format "automatic", which is plain, and the format "automatic" itself is not asked about either', i.writes().length === 1 && i.dialog() === i.record() );
	choose( i.format( i.record() ), 'auto' );
	i.save( i.record() );
	check( '...going back to "automatic" asks nothing, whatever the format that was set', i.writes().length === 2 && i.dialog() === i.record() && i.writes()[1].payload.format === 'auto' );
}

{
	// a key that becomes global drops the texts of the other languages: asked once with the format, naming the key and the languages
	const response = keysResponse();
	response.keys.find( e => e.key === K1 ).values = { de_DE : 'Gleich', en_US : 'Gleich' };
	const k = keysRecord( PAGE, K3, response );

	tick( k.global( k.record() ) );
	k.save( k.record() );
	let ask = k.dialog();
	check( 'a key that becomes global asks, with the heaviest weight, naming the key and the languages that lose their text and the one whose text stays', ask !== k.record() && detailsOf( ask ).length === 1 && detailsOf( ask )[0] === F('/_admin/keys/confirm/global-line',K3,'English','Deutsch') );
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( '...answered no it changes nothing: nothing is sent and the record is as it was typed', k.writes().length === 0 && dirtyNow( k ) === true && k.global( k.record() ).checked === true );
	choose( k.format( k.record() ), 'plain' );
	const m = keysRecord( PAGE, K5, keysResponse() );
	tick( m.global( m.record() ) );
	choose( m.format( m.record() ), 'plain' );
	m.save( m.record() );
	ask = m.dialog();
	check( 'a narrower format and a global key are one question with both among its details', detailsOf( ask ).length === 2 && detailsOf( ask ).some( l => l.indexOf( K5 ) === 0 && l.indexOf( W('/_admin/keys/format/plain') ) !== -1 ) && detailsOf( ask ).some( l => l.indexOf( K5 ) === 0 && l.indexOf( 'English' ) !== -1 ) );
	m.answerAsk( W('/_admin/keys/confirm/convert-title') );
	check( '...answered yes the shape is written once, with both', m.writes().filter( c => c.endpoint === 'save' ).length === 1 && m.writes()[0].payload.global === true && m.writes()[0].payload.format === 'plain' );
	// the key of which the other languages say the same - or nothing - loses nothing: no question
	const c = keysRecord( PAGE, K1, response );
	tick( c.global( c.record() ) );
	c.save( c.record() );
	check( 'a key whose other languages say the same as the native one, or nothing, loses nothing when it becomes global: no question', c.dialog() === c.record() && c.writes().length === 1 && c.writes()[0].payload.global === true );
}

{
	// a key that becomes global keeps the text of the native language: a text typed in another language is dropped with the others, as the question said
	const k = keysRecord( PAGE, K1 );

	tick( k.global( k.record() ) );
	k.type( k.record(), 'en_US', 'Typed in English' );
	k.save( k.record() );
	k.answerAsk( W('/_admin/keys/confirm/convert-title') );
	check( 'a key that becomes global has only its native text written: a text typed in another language is not sent to the one text, which the question about what is dropped has named', k.writes().map( c => c.endpoint ).join() === 'save' && k.writes()[0].payload.global === true );
	const j = keysRecord( PAGE, K1 );
	tick( j.global( j.record() ) );
	j.type( j.record(), 'de_DE', 'Typed in German' );
	j.type( j.record(), 'en_US', 'Typed in English' );
	j.save( j.record() );
	j.answerAsk( W('/_admin/keys/confirm/convert-title') );
	check( '...and the native one is written to the one text', j.writes().map( c => c.endpoint ).join() === 'save,savebatch' && j.writes()[1].payload.items.length === 1 && j.writes()[1].payload.items[0].locale === '*' && j.writes()[1].payload.items[0].value === 'Typed in German' );
}

{
	// a server that turns a part down: said at its field, nothing counts as saved that is not
	const k = keysRecord( PAGE, K1 );
	const d = k.record();

	typeKey( k.limit( d ), '5' );
	k.type( d, 'de_DE', 'Ein neuer Titel' );
	choose( k.format( d ), 'lines' );
	k.handle.refuse.save = [ { key : K1, status : 400, body : { error : 'limit', code : 'keys_limit_short', params : [ 14 ], field : 'maxlength' } } ];
	k.save( d );
	const e = k.record();
	check( 'a shape the server turns down is said under its field - the limit under the limit - in the words of the server, and the field is marked', saidAt( e, k.limit( e ) ) !== undefined && k.limit( e ).attributes['aria-invalid'] === 'true' && saidAt( e, k.limit( e ) ).textContent === F('/_admin/error/keys_limit_short',14)
		&& byClass( e, 'nino-admin-field-error' ).length === 1 );
	check( 'a text made shorter in the save that lowers its limit is written before the shape, so that the server does not refuse a limit under the text', k.writes().map( c => c.endpoint ).join() === 'savebatch,save' && k.writes()[0].payload.items[0].value === 'Ein neuer Titel' );
	check( '...what the server took is saved and what it did not is still unsaved input, drawn as it was typed: the limit and the format', dirtyNow( k ) === true && k.limit( e ).value === '5' && k.format( e ).value === 'lines' && k.controlOf( e, 'de_DE' ).value === 'Ein neuer Titel' && k.entry( K1 ).values.de_DE === 'Ein neuer Titel' );
	check( '...the line of the bar says that a key was not saved - no toast of success - and the focus is on the first field that is wrong', k.lineText( e ) === W('/_admin/keys/error/one') && k.told.length === 0 && k.h.doc.activeElement === k.limit( e ) );
	k.handle.refuse = {};
	typeKey( k.limit( e ), '40' );
	k.sent.length = 0;
	k.save( e );
	check( 'the next save sends what is still unsaved: the shape - and not the text, which was written', k.writes().map( c => c.endpoint ).join() === 'save' && k.writes()[0].payload.maxlength === 40 && dirtyNow( k ) === false && k.told.at(-1) === W('/_admin/common/msg/saved') );
	check( '...and after a save that went well the focus is where it was: on the main button', k.h.doc.activeElement === byClass( byClass( k.record(), 'nino-admin-actionbar' )[0], 'nino-admin-btn-primary' )[0] || k.h.doc.activeElement === null || k.record().contains( k.h.doc.activeElement ) );
}

{
	// a format the server turns down is said under the format
	const k = keysRecord( PAGE, K1 );

	choose( k.format( k.record() ), 'lines' );
	k.handle.refuse.save = [ { key : K1, status : 400, body : { error : 'unknown format', code : 'keys_format', params : [], field : 'format' } } ];
	k.save( k.record() );
	check( 'a format the server turns down is said under the format, which is marked, and the focus goes there', saidAt( k.record(), k.format( k.record() ) ) !== undefined && k.format( k.record() ).attributes['aria-invalid'] === 'true' && saidAt( k.record(), k.format( k.record() ) ).textContent === W('/_admin/error/keys_format') && k.h.doc.activeElement === k.format( k.record() ) );
	// the text of a key whose shape was turned down is not sent, and stays as it was typed
	k.sent.length = 0;
	k.handle.refuse.save = [ { key : K1, status : 400, body : { error : 'unknown format', code : 'keys_format', params : [], field : 'format' } } ];
	k.type( k.record(), 'de_DE', 'Bleibt stehen' );
	k.save( k.record() );
	check( 'the text of a key whose shape the server turned down is not sent with it: one request for the shape, none for the text, and what was typed stays at its field, unsaved', k.writes().map( c => c.endpoint ).join() === 'save' && k.controlOf( k.record(), 'de_DE' ).value === 'Bleibt stehen' && k.entry( K1 ).values.de_DE !== 'Bleibt stehen' && dirtyNow( k ) === true );
	k.handle.refuse.save = [ { key : K1, status : 500, body : null } ];
	k.save( k.record() );
	check( '...a refusal that names no field is said at the controls of the shape and in the line, and what was typed stays', byClass( k.record(), 'nino-admin-field-error' ).length === 1 && k.format( k.record() ).value === 'lines' && dirtyNow( k ) === true );
}

{
	// a text the server turns down
	const k = keysRecord( PAGE, K1 );

	k.type( k.record(), 'en_US', 'Noch ein Text' );
	k.handle.refuse['item:'+ K1] = { ok : false, error : 'could not be written' };
	k.save( k.record() );
	check( 'a text the server turns down is said under its field, stays unsaved input and takes the focus', saidAt( k.record(), k.controlOf( k.record(), 'en_US' ) ) !== undefined && byClass( k.record(), 'nino-admin-field-error' ).length === 1
		&& dirtyNow( k ) === true && k.controlOf( k.record(), 'en_US' ).value === 'Noch ein Text' && k.h.doc.activeElement === k.controlOf( k.record(), 'en_US' ) && k.lineText( k.record() ) === W('/_admin/keys/error/one') );
	delete k.handle.refuse['item:'+ K1];
	k.save( k.record() );
	check( '...the next save writes it, and the record is saved', k.writes().length === 2 && dirtyNow( k ) === false && k.told.at(-1) === W('/_admin/common/msg/saved') );
}

{
	// a text that is too long: the sentence of its code, with the limit and the length, at its field
	const k = keysRecord( PAGE, K1 );

	k.type( k.record(), 'en_US', 'Ein viel zu langer Text' );
	k.handle.refuse['item:'+ K1] = { ok : false, error : 'too long', code : 'text_too_long', params : [ 150, 151 ], locale : 'en_US' };
	k.save( k.record() );
	check( 'a text that is too long is said under its field in the words of its code, with the limit and the length the server names, and takes the focus', saidAt( k.record(), k.controlOf( k.record(), 'en_US' ) ) !== undefined
		&& saidAt( k.record(), k.controlOf( k.record(), 'en_US' ) ).textContent === F( '/_admin/error/text_too_long', 150, 151 ) && dirtyNow( k ) === true && k.h.doc.activeElement === k.controlOf( k.record(), 'en_US' ) );
}

{
	// one language is turned down, the other is written: the one that was written is saved
	const k = keysRecord( PAGE, K1 );

	k.type( k.record(), 'de_DE', 'Deutsch neu' );
	k.type( k.record(), 'en_US', 'English new' );
	k.handle.refuse['item:'+ K1+ ' en_US'] = { ok : false, error : 'could not be written' };
	k.save( k.record() );
	check( 'where one language is turned down and another is written, the one that was written counts as saved and the other is unsaved at its field', k.entry( K1 ).values.de_DE === 'Deutsch neu' && k.controlOf( k.record(), 'de_DE' ).value === 'Deutsch neu' && saidAt( k.record(), k.controlOf( k.record(), 'en_US' ) ) !== undefined
		&& byClass( k.record(), 'nino-admin-field-error' ).length === 1 && dirtyNow( k ) === true );
}

{
	// a limit that is no number is said before anything is sent
	const k = keysRecord( PAGE, K1 );

	typeKey( k.limit( k.record() ), '0' );
	k.save( k.record() );
	check( 'a limit that is not a whole number from 1 is said at its field before anything is sent, with the focus on it and the line saying to check the marked fields', k.writes().length === 0 && saidAt( k.record(), k.limit( k.record() ) ) !== undefined && k.h.doc.activeElement === k.limit( k.record() )
		&& k.lineText( k.record() ) === W('/_admin/keys/error/fields') && saidAt( k.record(), k.limit( k.record() ) ).textContent === F('/_admin/keys/error/invalid',W('/_admin/keys/label/limit'),W('/_admin/keys/expect/limit')) );
	typeKey( k.limit( k.record() ), '1.5' );
	k.save( k.record() );
	check( '...so is one with a fraction', k.writes().length === 0 && byClass( k.record(), 'nino-admin-field-error' ).length === 1 );
}

{
	// the limit and the text in one save: each action is valid by itself, in either direction
	const response = keysResponse();
	response.keys.find( e => e.key === K2 ).values.de_DE = 'Ein Text von mehr als vierzig Zeichen Länge, der gekürzt wird.';
	const k = keysRecord( PAGE, K2, response );
	const d = k.record();
	const X = K2;

	typeKey( k.limit( d ), '20' );
	k.type( d, 'de_DE', 'Gekürzt' );
	k.save( d );
	check( 'a limit lowered under the text a key holds and the text made shorter in the same save: the text is written first and the limit is taken', k.writes().map( c => c.endpoint ).join() === 'savebatch,save' && dirtyNow( k ) === false && byClass( k.record(), 'nino-admin-field-error' ).length === 0 );

	// the limit raised and the text made longer: the field takes the longer text as the limit is typed, and the shape is written first
	const e = k.record();
	const before = k.controlOf( e, 'de_DE' ).maxLength;
	typeKey( k.limit( e ), '400' );
	check( 'a limit that is typed is the limit the field counts to and takes, before the save: every language\'s field', k.controlOf( e, 'de_DE' ).maxLength === 400 && k.controlOf( e, 'en_US' ).maxLength === 400 && before === 20 && k.counterOf( e, 'de_DE' ).textContent.split(' / ')[1] === '400' );
	k.type( e, 'de_DE', 'x'.repeat( 300 ) );
	k.sent.length = 0;
	k.save( e );
	check( '...a limit raised and the text made longer in the same save: the shape is written first, then the text', k.writes().map( c => c.endpoint ).join() === 'save,savebatch' && dirtyNow( k ) === false );
	void X;
}

{
	// The order of the two requests of a save that changes the limit and a text: the counter showed the limit that was typed, and what the server asks of a text is
	// the limit in force when it asks - so the texts go first where the limit is lowered or emptied and they fit what is in force, and the shape first otherwise
	const start = ( set, longest, others, alone ) => {
		const response = keysResponse( { limitCap : 2000 } );
		const entry = response.keys.find( e => e.key === K1 );
		const own = set === null ? 150 : set;
		entry.values.de_DE = 'd'.repeat( 50 );
		entry.values.en_US = alone === true ? '' : 'e'.repeat( 20 );
		entry.maxlengthSet = set !== null;
		entry.keyLimit = own;
		entry.maxlength = Math.max( own, longest ?? 50 );
		entry.limits = Object.assign( { de_DE : Math.max( own, 50 ), en_US : Math.max( own, 20 ) }, others ?? {} );
		return keysRecord( PAGE, K1, response );
	};
	const order = ( set, limit, language, length, longest, global ) => {
		const k = start( set, longest, null, global === true );
		const d = k.record();
		typeKey( k.limit( d ), limit );
		if( global === true )
			tick( k.global( d ) );
		if( length !== null )
			k.type( d, language, 'x'.repeat( length ) );
		k.sent.length = 0;
		k.save( d );
		return k.sent.filter( c => c.endpoint === 'save' || c.endpoint === 'savebatch' ).map( c => c.endpoint ).join();
	};

	check( 'a limit raised: the shape is written first, whatever the text - longer, shorter or left alone, which sends no text',
		order( 100, '200', 'de_DE', 150 ) === 'save,savebatch' && order( 100, '200', 'de_DE', 30 ) === 'save,savebatch' && order( 100, '200', 'de_DE', null ) === 'save' );
	check( 'a limit lowered: the text is written first when it fits what is in force - longer than it was or shorter - and a limit alone is the shape alone',
		order( 100, '60', 'de_DE', 55 ) === 'savebatch,save' && order( 100, '60', 'de_DE', 30 ) === 'savebatch,save' && order( 100, '60', 'de_DE', null ) === 'save' );
	check( 'a limit emptied: the text is written first when it fits what is in force, as the counter allowed it - 90 characters at a limit of 100 - and the limit is derived from the texts afterwards',
		order( 100, '', 'de_DE', 90 ) === 'savebatch,save' && order( 100, '', 'de_DE', 30 ) === 'savebatch,save' && order( 100, '', 'de_DE', null ) === 'save' );
	check( 'a limit set for the first time under what the key had: the text is written first; over it, the shape first',
		order( null, '80', 'de_DE', 70 ) === 'savebatch,save' && order( null, '80', 'de_DE', 30 ) === 'savebatch,save' && order( null, '300', 'de_DE', 250 ) === 'save,savebatch' && order( null, '300', 'de_DE', 30 ) === 'save,savebatch' );
	check( 'a limit emptied and a text of a language that has a lower limit than the key\'s longest text lets it have: longer than what is in force, the shape is written first, so that the limit that is derived from the longest text is what is asked',
		order( 10, '', 'en_US', 25, 30 ) === 'save,savebatch' && order( 10, '', 'en_US', 8, 30 ) === 'savebatch,save' );

	check( 'a text as long as the limit in force goes first, one character longer goes after the shape: it is "fits" that is "not more than"',
		order( 100, '60', 'de_DE', 100 ) === 'savebatch,save' && order( 100, '60', 'de_DE', 101 ) === 'save,savebatch' );
	check( 'the texts go first only where the key stays one text for each language or one for all: with the switch turned, the shape is written first, however the limit is changed',
		order( 100, '60', 'de_DE', 30, 50, true ) === 'save,savebatch' && order( 100, '', 'de_DE', 30, 50, true ) === 'save,savebatch' );

	// An emptied limit is derived from the texts and the server never derives it above its cap, which its answer names: the field counts up to that
	const capped = start( 100, 2500 );
	const g = capped.record();
	typeKey( capped.limit( g ), '' );
	check( 'a limit that is emptied is counted to the cap of the server: the key holds a text of 2500 characters, the field takes 2000 and counts to it',
		capped.controlOf( g, 'de_DE' ).maxLength === 2000 && capped.counterOf( g, 'de_DE' ).textContent.split(' / ')[1] === '2000' );
	typeKey( capped.limit( g ), '120' );
	check( '...and one that is typed is counted to what is typed', capped.controlOf( g, 'de_DE' ).maxLength === 120 );

	// Half-way: the request that is refused leaves what the next Save completes
	const half = start( 100, 50 );
	const e = half.record();
	typeKey( half.limit( e ), '60' );
	half.type( e, 'de_DE', 'y'.repeat( 40 ) );
	half.handle.refuse.save = [ { key : K1, status : 400, body : { error : 'limit below the current text', code : 'keys_limit_short', params : [ 99 ], field : 'maxlength' } } ];
	half.sent.length = 0;
	half.save( e );
	check( 'the text was written first and the shape was refused: the text stays written', half.sent.filter( c => c.endpoint === 'save' || c.endpoint === 'savebatch' ).map( c => c.endpoint ).join() === 'savebatch,save' && half.entry( K1 ).values.de_DE === 'y'.repeat( 40 ) );
	half.handle.refuse = {};
	half.sent.length = 0;
	half.save( e );
	check( '...and the next Save writes the shape and nothing else', half.sent.filter( c => c.endpoint === 'save' || c.endpoint === 'savebatch' ).map( c => c.endpoint ).join() === 'save' && dirtyNow( half ) === false );

	const second = start( 100, 50 );
	const f = second.record();
	typeKey( second.limit( f ), '200' );
	second.type( f, 'de_DE', 'z'.repeat( 150 ) );
	second.handle.refuse['item:'+ K1] = { ok : false, error : 'too long', code : 'text_too_long', params : [ 200, 150 ], locale : 'de_DE' };
	second.sent.length = 0;
	second.save( f );
	check( 'the shape was written first and the text was refused: the limit is taken and the text stays typed', second.sent.filter( c => c.endpoint === 'save' || c.endpoint === 'savebatch' ).map( c => c.endpoint ).join() === 'save,savebatch' && second.entry( K1 ).values.de_DE === 'd'.repeat( 50 ) && dirtyNow( second ) === true );
	second.handle.refuse = {};
	second.sent.length = 0;
	second.save( f );
	check( '...and the next Save writes the text and nothing else', second.sent.filter( c => c.endpoint === 'save' || c.endpoint === 'savebatch' ).map( c => c.endpoint ).join() === 'savebatch' && dirtyNow( second ) === false );
}

{
	// the keys cannot be read again after what was written: the record stays, says it, and keeps what was turned down
	const k = keysRecord( PAGE, K1 );
	const d = k.record();

	k.type( d, 'de_DE', 'Gespeichert aber nicht gelesen' );
	k.type( d, 'en_US', 'Abgelehnt' );
	k.handle.refuse['item:'+ K1+ ' en_US'] = { ok : false, error : 'could not be written' };
	k.handle.down = true;
	k.save( d );
	const bar = byClass( k.record(), 'nino-admin-actionbar' )[0];
	const reload = () => tag( bar, 'button' ).find( b => b.textContent === W('/_admin/keys/label/reload') );
	check( 'where the keys cannot be read again the record stays: the line says it was saved and not read again, and a button reads them - which was not there before', k.record() !== null && k.lineText( k.record() ) === W('/_admin/keys/error/reload') && reload().hidden !== true && [ k.controlOf( k.record(), 'de_DE' ), k.limit( k.record() ) ].every( c => c.disabled === false ) );
	check( '...what was written counts as saved and what was turned down is still unsaved input at its field, so the shell asks about exactly that', k.record() === d && saidAt( k.record(), k.controlOf( k.record(), 'en_US' ) ) !== undefined && k.controlOf( k.record(), 'en_US' ).value === 'Abgelehnt' && k.controlOf( k.record(), 'de_DE' ).value === 'Gespeichert aber nicht gelesen' );
	k.handle.down = false;
	k.handle.refuse = {};
	fire( reload(), 'click' );
	check( 'the button asks about what would be lost, reads the keys anew and draws the record from them: the button is gone, nothing is unsaved', k.dialog() !== k.record() );
	k.answerAsk( W('/_admin/common/label/discard') );
	const again = byClass( k.record(), 'nino-admin-actionbar' )[0];
	check( '...answered Discard it reads the keys and draws the record from what the server holds, the same key at the same address', k.record() !== null && k.hash() === keyAddress( PAGE, K1 ) && tag( again, 'button' ).filter( b => b.textContent === W('/_admin/keys/label/reload') ).every( b => b.hidden === true )
		&& k.controlOf( k.record(), 'de_DE' ).value === 'Gespeichert aber nicht gelesen' && k.controlOf( k.record(), 'en_US' ).value === 'Services' && dirtyNow( k ) === false && k.lineText( k.record() ) === '' );
}

{
	// "Save and continue": saves and goes on to the next key of the table - also from a key that a save hides or takes out of the rows that are shown
	const k = keysRecord( PAGE, K1 );
	const d = k.record();

	k.type( d, 'de_DE', 'Neuer Titel' );
	fire( k.button( d, CONTINUE() ), 'click' );
	check( '"Save and continue" saves the key and shows the next one of the table at its address, in the same record', k.writes().length === 1 && k.record() === d && k.subtitleOf( k.record() ) === K2 && k.hash() === keyAddress( PAGE, K2 ) && k.entry( K1 ).values.de_DE === 'Neuer Titel' );
	check( '...the key it came from is as saved in the table behind', byClass( k.cells( K1 )[2], 'nino-admin-table-clamp' )[0].textContent === 'Neuer Titel' );
	tick( k.hidden( k.record() ) );
	fire( k.button( k.record(), CONTINUE() ), 'click' );
	check( '...also from a key that the save hides while "Show: all" is chosen: it stays among the rows, and the next one is shown', k.subtitleOf( k.record() ) === K3 && k.entry( K2 ).blacklisted === true );

	// under "Show: hidden" un-hiding takes the key out of the rows: it goes on to the one that followed
	const response = keysResponse();
	[ K1, K2, K3 ].forEach( key => { response.keys.find( e => e.key === key ).blacklisted = true } );
	const h = keysRow( PAGE, response );
	choose( h.selects()[1], 'hidden' );
	const order = h.ids();
	const e = h.openRow( K1 );
	tick( h.hidden( e ) );
	fire( h.button( e, CONTINUE() ), 'click' );
	check( '"Save and continue" under "Show: hidden", where un-hiding takes the key out of the rows, goes on to the one that followed it - the next hidden key', order.join() === [ '/_nino/webpage/services/uri', K1, K2, K3 ].join() && h.subtitleOf( h.record() ) === K2 && h.entry( K1 ).blacklisted === false );
	check( '...and the table says the rows that are hidden now', h.ids().join() === [ '/_nino/webpage/services/uri', K2, K3 ].join() );
}

{
	// the language: the working language first, and the fields follow a change of it
	const k = keysRecord( PAGE, K1, keysResponse(), { work : 'en_US' } );
	const d = k.record();

	check( 'a record opens in the working language of the workbench: its field is the first and marked, the other follows', k.fields( d ).map( f => f.dataset.locale ).join() === 'en_US,de_DE' && nameOf( k.fieldOf( d, 'en_US' ) ) === 'English ('+ W('/_admin/text/label/working')+ ')' && k.controlOf( d, 'en_US' ).value === 'Services' );
	check( '...and the table behind it says its texts in that language, the head too', k.heads()[2].textContent === F('/_admin/text/col/text','English') && byClass( k.cells( K1 )[2], 'nino-admin-table-clamp' )[0].textContent === 'Services' );
	k.go( 'de_DE' );
	check( 'a change of the working language draws the record again with that language first, and the table in it', k.fields( k.record() ).map( f => f.dataset.locale ).join() === 'de_DE,en_US' && k.controlOf( k.record(), 'de_DE' ).value === 'Leistungen' && byClass( k.cells( K1 )[2], 'nino-admin-table-clamp' )[0].textContent === 'Leistungen'
		&& k.heads()[2].textContent === F('/_admin/text/col/text','Deutsch') );
	k.go( 'en_US' );
	check( '...and back', k.fields( k.record() ).map( f => f.dataset.locale ).join() === 'en_US,de_DE' );
}

{
	// a language without a value is an empty field, a whitespace is a change
	const k = keysRecord( PAGE, K2, keysResponse(), { work : 'en_US' } );
	const d = k.record();

	check( 'a language that has no text for a key draws its field empty - the text of another language is not put in as if it were its own - and nothing is unsaved', k.controlOf( d, 'en_US' ).value === '' && k.controlOf( d, 'de_DE' ).value === 'Das bieten wir an' && dirtyNow( k ) === false );
	k.type( d, 'en_US', ' ' );
	k.save( d );
	check( 'a whitespace is a change, and is saved as typed, in the language of the field, and in no other', k.writes().length === 1 && k.writes()[0].payload.items.length === 1 && k.writes()[0].payload.items[0].value === ' ' && k.writes()[0].payload.items[0].locale === 'en_US' );
	const j = keysRecord( PAGE, K1, keysResponse(), { work : 'en_US' } );
	j.save( j.record() );
	check( 'a save without a change sends no text, and none for a key that had none', j.writes().length === 0 );
}

{
	// the fills a field offers are the tab's own, asked of the server in the language of the field
	const k = keysShown();
	let listed = 'none';

	k.panel._loadFills( 'en_US', list => { listed = list } );
	check( 'the textfills are asked for with the tab\'s own action, in the language that is asked for, and the answer is the list', k.sent.at(-1).endpoint === 'fills' && k.sent.at(-1).payload.locale === 'en_US' && Array.isArray( listed ) === true );
	const asked = k.h.Nino.http.sendRequest;
	k.h.Nino.http.sendRequest = ( uri, method, callback ) => callback( { status : 200, responseJSON : { fills : [ { key : '/project/company/contact/phone', notation : '[[/project/company/contact/phone]]', value : '+49' } ] } } );
	k.panel._loadFills( 'de_DE', list => { listed = list } );
	check( '...each row named by its key read as words, with the notation the field reads and what it says today', JSON.stringify( listed ) === JSON.stringify( [ { name : [ 'company', 'contact', 'phone' ].map( w => k.h.ui.slugLabel( w ) ).join(' · '), notation : '[[/project/company/contact/phone]]', value : '+49' } ] ) );
	k.h.Nino.http.sendRequest = ( uri, method, callback ) => callback( { status : 403, responseJSON : null } );
	k.panel._loadFills( 'de_DE', list => { listed = list } );
	check( '...and where they could not be had, none is said and the field is left as it is', listed === null );
	k.h.Nino.http.sendRequest = asked;
	const r = keysRecord( PAGE, K5 );
	check( 'the fill icon is at a field of several lines and a text with markup, and not at a line', byClass( r.record(), 'nino-admin-fills-button' ).length > 0 && r.editors.length === 2 );
	const line = keysRecord( PAGE, K1 );
	check( '...a line has none', byClass( line.record(), 'nino-admin-fills-button' ).length === 0 );
}

// --- delete ----------------------------------------------------------------------------------------------------------------------------------------------

const DELETE = () => W('/_admin/keys/confirm/delete-title');

{
	const k = keysRecord( PAGE, K1 );
	const d = k.record();

	fire( k.button( d, DELETE() ), 'click' );
	const ask = k.dialog();
	check( 'deleting asks first, with the heaviest weight, over the record: the title and the answer say what is done, the message names the key', ask !== d && k.titleOf( ask ) === DELETE() && has( k.button( ask, DELETE() ), 'nino-admin-btn-danger' ) && allText( ask ).indexOf( F('/_admin/keys/confirm/delete',K1) ) !== -1 && k.writes().length === 0 );
	check( '...and it opens on Cancel, not on the button that destroys', k.h.doc.activeElement !== k.button( ask, DELETE() ) );
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( 'answered no it sends nothing and the record stays', k.writes().length === 0 && k.dialog() === d && k.entry( K1 ) !== undefined );

	k.handle.refuse.delete = [ { status : 500, body : null } ];
	fire( k.button( d, DELETE() ), 'click' );
	k.answerAsk( DELETE() );
	check( 'a refusal stands in the line of the bar - no window of the browser - and the key is still there, in the record and the table', k.lineText( d ).indexOf( '500' ) !== -1 && k.dialog() === d && k.row( K1 ) !== undefined && k.told.length === 0 );
	delete k.handle.refuse.delete;
	fire( k.button( d, DELETE() ), 'click' );
	k.answerAsk( DELETE() );
	check( 'a delete that went through closes the record, draws the table without the key, says it and tells the shell', k.writes().at(-1).endpoint === 'delete' && JSON.stringify( k.writes().at(-1).payload ) === JSON.stringify( { key : K1 } ) && k.record() === null && k.row( K1 ) === undefined && k.told.at(-1) === W('/_admin/common/msg/deleted') && k.touches.join() === 'texts' );
	check( '...the address is the row\'s, and the focus is on the row that stands where the deleted one did', k.hash() === keyAddress( PAGE ) && k.h.doc.activeElement === k.row( K2 ) && k.ids().length === KEYS.length - 1 );
}

{
	// the last key of a row: the list is what is behind the record
	const response = keysResponse();
	response.keys.push( textEntry( '/project/shop/solo/name', { de_DE : 'Einzig', en_US : 'Only' } ) );
	const k = keysRecord( 'project/shop', '/project/shop/solo/name', response );

	fire( k.button( k.record(), DELETE() ), 'click' );
	k.answerAsk( DELETE() );
	check( 'a delete that leaves the row without a key shows the list, which has no such row any more, at the address of the list', k.record() === null && has( k.list, 'admin-hidden' ) === false && has( k.form, 'admin-hidden' ) === true && k.listRow( 'project/shop' ) === undefined && k.hash() === '#text/keys' );
}

{
	// the last key of a row, the record opened by an address: the list is behind it and its address is written
	const response = keysResponse();
	response.keys.push( textEntry( '/project/shop/solo/name', { de_DE : 'Einzig', en_US : 'Only' } ) );
	const k = keysPage( response, { hash : keyAddress( 'project/shop', '/project/shop/solo/name' ) } ).open();

	fire( k.button( k.record(), DELETE() ), 'click' );
	k.answerAsk( DELETE() );
	check( 'a delete of the last key of a row, in a record that an address opened, shows the list and writes the address of the list - there is no entry of the history to step back to', k.record() === null && has( k.list, 'admin-hidden' ) === false && k.hash() === '#text/keys' && k.listRow( 'project/shop' ) === undefined );
}

{
	// the key that is deleted takes its place in the keys that were read again
	const k = keysRecord( PAGE, K3 );
	const listed = lastList( k );

	fire( k.button( k.record(), DELETE() ), 'click' );
	k.answerAsk( DELETE() );
	check( 'after a delete the keys are read again, so that the list says how many are left', lastList( k ) === listed + 1 );
	fire( byClass( k.form, 'nino-admin-back-link' )[0], 'click' );
	check( '...the list says one key less on the row that held it', k.lineOf( PAGE ) === F('/_admin/keys/count/keys',9)+ ' · '+ F('/_admin/keys/count/hidden',1) );
}

// --- rename: a dialog over the record, at its own address -------------------------------------------------------------------------------------------------------

const RENAME = () => W('/_admin/common/label/rename');
const RENAMED = '/template/page-services/item-1/heading-2';

{
	const k = keysRecord( PAGE, K3 );
	const d = k.record();
	const before = k.h.index();

	fire( k.button( d, RENAME() ), 'click' );
	const r = k.dialog();
	const control = id => findAll( r, el => el.id === id )[0];
	const options = id => tag( control( id ), 'option' ).map( o => o.value );

	check( 'Rename opens a small dialog over the record, at the address of the rename of the key, as one entry more of the history', r !== d && has( r, 'nino-admin-dialog--form' ) && has( r, 'nino-admin-record' ) === false && k.record() === d && k.hash() === renameAddress( K3 ) && k.h.index() === before + 1
		&& k.titleOf( r ) === W('/_admin/keys/confirm/rename-title') && k.panel._renaming === K3 );
	check( '...with the four parts of the key in its fields - the namespace, the category, the part, the name - and the key they make', control('keys-form-namespace').value === 'template' && control('keys-form-category').value === 'page-services' && control('keys-form-part').value === 'item-1' && control('keys-form-name').value === 'title'
		&& control('keys-form-preview').textContent === K3 && control('keys-form-new-value') === undefined && control('keys-form-new-global') === undefined );
	check( '...a namespace other than /project is unlocked, so the choice has them all; the category is a choice of what there is', options('keys-form-namespace').join() === 'project,template,feature,module' && options('keys-form-category').join() === 'common,frame-header,page-services' );
	check( '...its answers are Cancel and, at the far right, the rename - worded like the title', tag( r, 'button' ).filter( b => has( b, 'nino-admin-btn-primary' ) ).map( b => b.textContent ).join() === W('/_admin/keys/confirm/rename-title') && k.button( r, W('/_admin/common/label/cancel') ) !== undefined );

	// the checks
	fire( k.button( r, W('/_admin/keys/confirm/rename-title') ), 'click' );
	check( 'a key that is not changed is no rename: the name says so, nothing is sent, the focus is at the name', k.writes().length === 0 && saidAt( r, control('keys-form-name') ).textContent === W('/_admin/keys/error/unchanged') && k.h.doc.activeElement === control('keys-form-name') && k.dialog() === r );
	const name = control('keys-form-name');
	typeKey( name, 'Titel!' );
	fire( k.button( r, W('/_admin/keys/confirm/rename-title') ), 'click' );
	check( 'a part that is no word of a key is said under it when the dialog is sent, with what is expected - and nothing is sent', k.writes().length === 0 && byClass( r, 'nino-admin-field-error' ).length === 1 && name.attributes['aria-invalid'] === 'true'
		&& byClass( r, 'nino-admin-field-error' )[0].textContent === F('/_admin/keys/error/invalid',W('/_admin/keys/label/name'),W('/_admin/keys/expect/segment')) );
	typeKey( name, 'heading-2' );
	check( 'the key the parts make is said as it is typed, and how the panel will read it', control('keys-form-preview').textContent === RENAMED && control('keys-form-shown').textContent === W('/_admin/keys/label/shown-as')+ ' '+ k.h.ui.slugLabel('item-1')+ ' › '+ k.h.ui.slugLabel('heading-2') );
	typeKey( control('keys-form-part'), 'cta' );
	check( '...a part that is no word of the vocabulary but is one of a key is said under it - a warning, no refusal', byClass( r, 'admin-key-note' ).some( n => n.textContent.indexOf( stem('/_admin/keys/hint/not-a-word') ) === 0 ) );
	typeKey( control('keys-form-part'), 'item-1' );

	// the question, and what the server refuses
	k.handle.refuse.rename = [ { status : 409, body : { error : 'key already exists', code : 'keys_exists', params : [ RENAMED ], field : 'newKey' } } ];
	fire( k.button( r, W('/_admin/keys/confirm/rename-title') ), 'click' );
	const ask = k.dialog();
	check( 'a rename asks first, at the weight of an identifier that templates may still call by its old name, with both names in the question and the act in the title and the answer', ask !== r && k.titleOf( ask ) === W('/_admin/keys/confirm/rename-title') && allText( ask ).indexOf( F('/_admin/keys/confirm/rename',K3,RENAMED) ) !== -1 && k.button( ask, W('/_admin/keys/confirm/rename-title') ) !== undefined );
	k.answerAsk( W('/_admin/keys/confirm/rename-title') );
	check( 'a key the server refuses - one that is taken - is said at the name field, in the words of the server, with the focus there, and the dialog stays', k.dialog() === r && saidAt( r, name ) !== undefined && saidAt( r, name ).textContent === F('/_admin/error/keys_exists',RENAMED) && name.attributes['aria-invalid'] === 'true' && k.h.doc.activeElement === name );
	delete k.handle.refuse.rename;
	const renames = () => k.writes().filter( c => c.endpoint === 'rename' ).length;
	const done = renames();
	fire( k.button( r, W('/_admin/keys/confirm/rename-title') ), 'click' );
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( '...and No sends nothing: the dialog stays as it is, and can be sent again', renames() === done && k.dialog() === r );
	fire( k.button( r, W('/_admin/keys/confirm/rename-title') ), 'click' );
	const asked = k.h.went.length;
	k.answerAsk( W('/_admin/keys/confirm/rename-title') );
	check( 'submitting renames the key to the one the parts make - the old and the new name, nothing else', k.writes().at(-1).endpoint === 'rename' && JSON.stringify( k.writes().at(-1).payload ) === JSON.stringify( { key : K3, newKey : RENAMED } ) );
	check( '...a write of texts, and of templates too where the key is one a template reads', k.touches.join() === 'texts,templates' );
	check( '...the dialog is gone, and the history is back at the record, which the page asked for', k.dialog() === d && k.h.went.length > asked );
	k.back();
	check( 'the record follows the new key: its head, its address - written over the one the record had, one entry for it - and the table behind it names the new key', k.record() === d && k.subtitleOf( d ) === RENAMED && k.hash() === keyAddress( PAGE, RENAMED ) && k.h.index() === before && k.h.entries[before] === keyAddress( PAGE, RENAMED ) && k.row( RENAMED ) !== undefined && k.row( K3 ) === undefined );
	check( '...the field of the key says its text, and nothing is unsaved', k.controlOf( k.record(), 'de_DE' ).value === 'Beratung' && dirtyNow( k ) === false && k.panel._renaming === null && k.panel._dialog === null );
	const index = k.h.index();
	k.close( k.record() );
	check( 'the cross takes the one entry of the record off the history, as it does for a key that was not renamed - no address of the old key is left behind', k.record() === null && k.hash() === keyAddress( PAGE ) && k.h.index() === index - 1 );
}

{
	// Cancel gives the address back to the record: no stray entry
	const k = keysRecord( PAGE, K3 );
	const before = k.h.index();

	fire( k.button( k.record(), RENAME() ), 'click' );
	const r = k.dialog();
	const asked = k.h.went.length;
	fire( k.button( r, W('/_admin/common/label/cancel') ), 'click' );
	check( 'Cancel closes the dialog without a request and takes the entry it made off the history', k.dialog() === k.record() && k.writes().length === 0 && k.h.went.length === asked + 1 );
	k.back();
	check( '...the record is as it was, at its address, with no entry left over', k.record() !== null && k.hash() === keyAddress( PAGE, K3 ) && k.h.index() === before && k.panel._renaming === null );

	// Esc: nothing typed, nothing asked
	fire( k.button( k.record(), RENAME() ), 'click' );
	fire( k.dialog(), 'keydown', { key : 'Escape' } );
	k.back();
	check( 'Esc closes it as Cancel does', k.dialog() === k.record() && k.hash() === keyAddress( PAGE, K3 ) );
}

{
	// Back closes the dialog first, and the record stays
	const k = keysRecord( PAGE, K3 );

	fire( k.button( k.record(), RENAME() ), 'click' );
	k.back();
	check( 'Back from the dialog closes the dialog first: the record stays, at its address, with its text', k.dialog() === k.record() && k.record() !== null && k.hash() === keyAddress( PAGE, K3 ) && k.panel._renaming === null && k.writes().length === 0 );
	k.back();
	check( '...and the next Back closes the record', k.record() === null && k.hash() === keyAddress( PAGE ) );
	k.forward();
	k.forward();
	check( '...Forward opens the record and the dialog again', k.record() !== null && k.dialog() !== k.record() && k.hash() === renameAddress( K3 ) && k.panel._renaming === K3 );

	// something typed in the dialog: Back asks first, and where the answer is no the address is the dialog's
	const r = k.dialog();
	typeKey( findAll( r, el => el.id === 'keys-form-name' )[0], 'other' );
	k.back();
	check( 'Back with something typed in the dialog asks what to do with it: the dialog stays, with the question on it, and the address is the dialog\'s', k.dialog() !== r && k.panel._renaming === K3 && k.hash() === renameAddress( K3 ) );
	k.answerAsk( W('/_admin/common/dialog/discard-title') );
	check( '...Discard closes the dialog and leaves the record', k.dialog() === k.record() && k.panel._renaming === null );
}

{
	// a reload at the address of a rename opens the table of the row, the record of the key and the dialog over it - one entry of the history
	const k = keysPage( keysResponse(), { hash : renameAddress( K3 ) } ).open();

	check( 'a reload at the address of a rename opens the table of its row, the record of the key and the dialog over it, in one entry of the history', k.record() !== null && k.subtitleOf( k.record() ) === K3 && k.dialog() !== k.record() && k.ids().length === KEYS.length && k.hash() === renameAddress( K3 ) && k.h.entries.length === 1 );
	fire( k.button( k.dialog(), W('/_admin/common/label/cancel') ), 'click' );
	check( '...Cancel there gives the record its address: written over, as no entry was made', k.dialog() === k.record() && k.hash() === keyAddress( PAGE, K3 ) && k.h.entries.length === 1 );
	const j = keysPage( keysResponse(), { hash : renameAddress( K3 ) } ).open();
	const control = id => findAll( j.dialog(), el => el.id === id )[0];
	typeKey( control('keys-form-name'), 'heading-2' );
	fire( j.button( j.dialog(), W('/_admin/keys/confirm/rename-title') ), 'click' );
	j.answerAsk( W('/_admin/keys/confirm/rename-title') );
	check( '...and a rename there ends with the record of the new key at once, over the one entry of the history', j.dialog() === j.record() && j.subtitleOf( j.record() ) === RENAMED && j.hash() === keyAddress( PAGE, RENAMED ) && j.h.entries.length === 1 );
}

{
	// the address of a rename of a key that is not there, of a key of the system or of anything else is not a rename
	const t = hash => keysPage( keysResponse(), { hash : hash } ).open();
	const key = '/template/page-services/intro/title';

	check( 'the address of a rename of a key that is not there, one of the system, one with more behind it or without the key is the list', [ renameAddress( '/template/nope/a/b' ), renameAddress( '/_nino/webpage/services/title' ), renameAddress( key )+ '/more', '#text/keys/@rename' ].every( hash => { const w = t( hash ); return w.dialog() === null && has( w.list, 'admin-hidden' ) === false && w.hash() === '#text/keys' } ) );
	check( '...so is %40rename and a key: a row called @rename is a row, never the word', ( () => { const w = t( '#text/keys/%40rename/'+ encodeURIComponent( key ) ); return w.dialog() === null && w.hash() === '#text/keys' } )() );
}

{
	// a rename into another row: the table behind the record is the table of that row
	const k = keysRecord( PAGE, K3 );
	const moved = '/template/frame-header/item-1/title';

	fire( k.button( k.record(), RENAME() ), 'click' );
	const r = k.dialog();
	const control = id => findAll( r, el => el.id === id )[0];
	choose( control('keys-form-category'), 'frame-header' );
	fire( k.button( r, W('/_admin/keys/confirm/rename-title') ), 'click' );
	k.answerAsk( W('/_admin/keys/confirm/rename-title') );
	k.back();
	check( 'a key renamed into another row has its record at the address of that row, and the table behind it is the table of that row', k.subtitleOf( k.record() ) === moved && k.hash() === keyAddress( 'template/frame-header', moved ) && k.ids().indexOf( moved ) !== -1 && k.ids().indexOf( K3 ) === -1 && byClass( k.form, 'nino-admin-contextbar-title' )[0].textContent === W('/_admin/common/word/frame-header') );
}

{
	// unsaved input in the record is asked about before the rename is offered
	const k = keysRecord( PAGE, K3 );

	k.type( k.record(), 'de_DE', 'Beratung neu' );
	fire( k.button( k.record(), RENAME() ), 'click' );
	check( 'Rename of a record that holds unsaved input asks first, with the question of the workbench - and the dialog is not there yet', k.dialog() !== k.record() && k.panel._dialog === null && k.writes().length === 0 && k.titleOf( k.dialog() ) === W('/_admin/common/dirty/title') );
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( '...Cancel leaves everything as it is: the record, with what was typed', k.dialog() === k.record() && k.controlOf( k.record(), 'de_DE' ).value === 'Beratung neu' && k.panel._dialog === null );
	fire( k.button( k.record(), RENAME() ), 'click' );
	k.answerAsk( W('/_admin/common/label/discard') );
	check( '...Discard gives the typed text up and offers the rename: the record stays the record of the key, and the dialog is open over it, at the address of the rename, with no entry more in the history', k.record() !== null && k.dialog() !== k.record() && k.panel._renaming === K3 && k.controlOf( k.record(), 'de_DE' ).value === 'Beratung' && k.hash() === renameAddress( K3 ) );
	fire( k.button( k.dialog(), W('/_admin/common/label/cancel') ), 'click' );
	k.back();
	k.type( k.record(), 'de_DE', 'Beratung neu' );
	fire( k.button( k.record(), RENAME() ), 'click' );
	k.answerAsk( W('/_admin/common/label/save') );
	check( '...Save writes the text first, and then the dialog is offered', k.writes().length === 1 && k.writes()[0].endpoint === 'savebatch' && k.panel._renaming === K3 && k.dialog() !== k.record() );
}

// --- a key that is made: the form of a new key in the record, at #text/keys/@new ---------------------------------------------------------------------------------

const CREATE = () => W('/_admin/keys/label/create');
const NEW = '/project/company/tester/zzz-name';
const idIn = ( d, id ) => findAll( d, el => el.id === id )[0];
const barButtons = d => tag( byClass( d, 'nino-admin-actionbar' )[0], 'button' ).filter( b => b.hidden !== true ).map( b => b.textContent );

{
	const k = keysShown();
	const before = k.h.index();

	fire( k.newButton(), 'click' );
	const d = k.record();
	const options = id => tag( idIn( d, id ), 'option' ).map( o => o.value );

	check( 'New text key opens the form of a new key in the record at the address #text/keys/@new, as one entry more of the history, over the list - the at sign keeps it from a row called new', d !== null && k.hash() === '#text/keys/@new' && k.h.index() === before + 1 && has( k.list, 'admin-hidden' ) === false
		&& k.titleOf( d ) === W('/_admin/keys/label/new') && k.subtitleOf( d ) === '' && has( d, 'nino-admin-record' ) && k.panel._record.creating() === true && k.sent.length === 1 );
	check( '...its bar has the one main button, "Create text key" - no way to delete, no "Save and continue", no rename, no reload - and the cross', barButtons( d ).join() === CREATE() && byClass( byClass( d, 'nino-admin-actionbar' )[0], 'nino-admin-btn-primary' ).length === 1 && k.cross( d ) !== undefined );
	check( '...the four parts of the key are fields of the workbench, required, with the labels at the left, in one group with its legend', tag( d, 'fieldset' ).length === 1 && [ 'keys-form-category', 'keys-form-part', 'keys-form-name' ].every( id => idIn( d, id ).attributes['aria-required'] === 'true' ) && tag( d, 'fieldset' )[0].children.some( c => has( c, 'nino-admin-field' ) ) );
	check( '...a new key begins with /project alone to choose from, and a switch of the workbench that unlocks the others, with its reason behind the (?)', options('keys-form-namespace').join() === 'project' && byClass( d, 'nino-admin-switch' ).length >= 2 && byClass( d, 'nino-admin-help-text' ).some( h => h.textContent === W('/_admin/keys/hint/unlock') ) );
	check( '...its category is a field with suggestions for /project', idIn( d, 'keys-form-category' ).tagName === 'INPUT' && idIn( d, 'keys-form-category' ).attributes.list === 'keys-form-categories' && tag( idIn( d, 'keys-form-categories' ), 'option' ).map( o => o.value ).join() === 'company,website,mail,catalog' );
	check( '...and has one text for all languages and a start value, which is a field of several lines with the textfills', idIn( d, 'keys-form-new-value' ).tagName === 'TEXTAREA' && byClass( idIn( d, 'keys-form-new-value' ).parentNode, 'nino-admin-fills-button' ).length === 1 && idIn( d, 'keys-form-new-global' ) !== undefined
		&& allText( idIn( d, 'keys-form-new-global' ).parentNode ).indexOf( W('/_admin/keys/label/global') ) !== -1 );
	check( '...the key and how the panel reads it are said under the fields as they are typed', idIn( d, 'keys-form-preview' ).textContent === '/project///' && k.dialog() === d );

	const unlock = byClass( d, 'nino-admin-switch' )[0].children[0];
	tick( unlock );
	check( 'the unlock offers the other namespaces, and takes the category from a choice for a template, a feature or a module', options('keys-form-namespace').join() === 'project,template,feature,module' );
	choose( idIn( d, 'keys-form-namespace' ), 'template' );
	check( '...whose categories are the ones there are', idIn( d, 'keys-form-category' ).tagName === 'SELECT' && options('keys-form-category').join() === 'common,frame-header,page-services' );
	tick( unlock );
	check( 'locking it again takes them away', options('keys-form-namespace').join() === 'project' && idIn( d, 'keys-form-namespace' ).value === 'project' );

	k.save( d );
	check( 'sent empty, the form says under each part that it is missing - the first takes the focus - and sends nothing', byClass( d, 'nino-admin-field-error' ).map( e => e.textContent ).join() === [ 'category', 'part', 'name' ].map( f => F('/_admin/common/error/missing',W('/_admin/keys/label/'+ f)) ).join()
		&& k.writes().length === 0 && k.h.doc.activeElement === idIn( d, 'keys-form-category' ) && k.lineText( d ) === W('/_admin/keys/error/fields') );
	typeKey( idIn( d, 'keys-form-category' ), 'Bad Category' );
	typeKey( idIn( d, 'keys-form-part' ), 'tester' );
	typeKey( idIn( d, 'keys-form-name' ), 'zzz-name' );
	k.save( d );
	check( 'a part that is no word of a key is said under it when the form is sent, with what is expected', k.writes().length === 0 && byClass( d, 'nino-admin-field-error' ).length === 1 && byClass( d, 'nino-admin-field-error' )[0].textContent === F('/_admin/keys/error/invalid',W('/_admin/keys/label/category'),W('/_admin/keys/expect/segment')) );
	typeKey( idIn( d, 'keys-form-category' ), 'company' );
	typeKey( idIn( d, 'keys-form-new-value' ), 'Hallo Welt' );
	check( 'the form is unsaved input once something is typed', dirtyNow( k ) === true && idIn( d, 'keys-form-preview' ).textContent === NEW );
	k.save( d );
	check( 'sent, the form makes the key from its parts, with the start value in the language of the workbench', k.writes()[0].endpoint === 'create' && JSON.stringify( k.writes()[0].payload ) === JSON.stringify( { key : NEW, global : false, value : 'Hallo Welt', values : { de_DE : 'Hallo Welt' } } ) );
	check( '...the record is the record of the new key, in the same dialog, at the address of the key in its row - written over the form\'s, no entry more - with the table of the row behind it', k.record() === d && k.subtitleOf( d ) === NEW && k.hash() === keyAddress( 'project/company', NEW ) && k.h.index() === before + 1
		&& k.ids().indexOf( NEW ) !== -1 && k.controlOf( k.record(), 'de_DE' ).value === 'Hallo Welt' && k.panel._record.creating() === false && barButtons( d ).length === 4 );
	check( '...the toast says that the key was made, and tells the shell that texts were written', k.told.at(-1) === W('/_admin/keys/msg/created') && k.touches.join() === 'texts' && dirtyNow( k ) === false );
	k.close( k.record() );
	check( 'the cross takes the one entry of the history off: the list is back, with the key counted in its row', k.record() === null && k.hash() === '#text/keys' && has( k.list, 'admin-hidden' ) === false && k.lineOf('project/company') === F('/_admin/keys/count/keys',5) );
}

{
	// the button under the table of a row: the form starts in the namespace and the category of the row
	const k = keysRow( PAGE );
	const before = k.h.index();

	fire( byClass( byClass( k.form, 'nino-admin-list-actions' )[0], 'nino-admin-btn-primary' )[0], 'click' );
	const d = k.record();

	check( 'the button of the bar under the table opens the form at the same address - not under a row - and the form starts in the namespace and the category of the row', d !== null && k.hash() === '#text/keys/@new' && k.h.index() === before + 1 && idIn( d, 'keys-form-namespace' ).value === 'template' && idIn( d, 'keys-form-category' ).value === 'page-services'
		&& idIn( d, 'keys-form-part' ).value === '' && idIn( d, 'keys-form-name' ).value === '' && dirtyNow( k ) === false && has( k.form, 'admin-hidden' ) === false );
	typeKey( idIn( d, 'keys-form-part' ), 'hero' );
	typeKey( idIn( d, 'keys-form-name' ), 'title' );
	check( '...the key it makes is the key of the row, with what was typed', idIn( d, 'keys-form-preview' ).textContent === '/template/page-services/hero/title' );
	k.close( d );
	check( 'Cancel by the cross asks first, as input of a record is asked about - and nothing was sent', k.record() === d && k.writes().length === 0 && k.dialog() !== d );
	k.answerAsk( W('/_admin/common/label/discard') );
	check( '...and Discard closes the form: the table of the row, at its address, with the focus on the button that opened it', k.record() === null && k.hash() === keyAddress( PAGE ) && k.ids().length === KEYS.length );

	const c = keysRow( 'project/company' );
	fire( byClass( byClass( c.form, 'nino-admin-list-actions' )[0], 'nino-admin-btn-primary' )[0], 'click' );
	check( '...a row of /project starts the form in /project and the category of the row', idIn( c.record(), 'keys-form-namespace' ).value === 'project' && idIn( c.record(), 'keys-form-category' ).value === 'company' );
	const s = keysRow( '_admin' );
	fire( byClass( byClass( s.form, 'nino-admin-list-actions' )[0], 'nino-admin-btn-primary' )[0], 'click' );
	check( '...a row that stands for no namespace and category starts the form empty', idIn( s.record(), 'keys-form-namespace' ).value === 'project' && idIn( s.record(), 'keys-form-category' ).value === '' );
}

{
	// what the server refuses is said at the field it names
	const k = keysShown();

	fire( k.newButton(), 'click' );
	const d = k.record();
	typeKey( idIn( d, 'keys-form-category' ), 'company' );
	typeKey( idIn( d, 'keys-form-part' ), 'general' );
	typeKey( idIn( d, 'keys-form-name' ), 'name' );
	k.handle.refuse.create = [ { status : 409, body : { error : 'key already exists', code : 'keys_exists', params : [ '/project/company/general/name' ], field : 'key' } } ];
	k.save( d );
	check( 'a key the server refuses - one that already exists - is said at the last part and in the line of the bar, in the words of the server, with the focus there; the form stays as it was typed', k.record() === d && k.panel._record.creating() === true && saidAt( d, idIn( d, 'keys-form-name' ) ) !== undefined
		&& saidAt( d, idIn( d, 'keys-form-name' ) ).textContent === F('/_admin/error/keys_exists','/project/company/general/name') && k.lineText( d ) === F('/_admin/error/keys_exists','/project/company/general/name') && k.h.doc.activeElement === idIn( d, 'keys-form-name' ) && idIn( d, 'keys-form-category' ).value === 'company' );
	k.handle.refuse.create = [ { status : 500, body : null } ];
	k.save( d );
	check( '...a refusal that names no field is in the line alone', k.lineText( d ).indexOf( '500' ) !== -1 && byClass( d, 'nino-admin-field-error' ).length === 0 && k.record() === d );
	k.handle.refuse = {};
	typeKey( idIn( d, 'keys-form-name' ), 'new-name' );
	k.save( d );
	check( '...and the next send makes the key', k.writes().at(-1).endpoint === 'create' && k.subtitleOf( k.record() ) === '/project/company/general/new-name' );
}

{
	// a key of a template, one text for all languages, the language of the workbench
	const k = keysShown( keysResponse(), { work : 'en_US' } );

	fire( k.newButton(), 'click' );
	const d = k.record();

	check( 'the form says the language the start value is written in, where it is not the native one, by the pill of the workbench', byClass( d, 'nino-admin-locale-badge' ).length === 1 );
	tick( byClass( d, 'nino-admin-switch' )[0].children[0] );
	choose( idIn( d, 'keys-form-namespace' ), 'template' );
	choose( idIn( d, 'keys-form-category' ), 'page-services' );
	typeKey( idIn( d, 'keys-form-part' ), 'hero' );
	typeKey( idIn( d, 'keys-form-name' ), 'title' );
	tick( idIn( d, 'keys-form-new-global' ) );
	typeKey( idIn( d, 'keys-form-new-value' ), 'Welcome' );
	k.save( d );
	check( 'a key of a template is made with its one text for all languages, written in the language of the workbench in the request, and tells the shell of the templates as well', JSON.stringify( k.writes()[0].payload ) === JSON.stringify( { key : '/template/page-services/hero/title', global : true, value : 'Welcome', values : { en_US : 'Welcome' } } ) && k.touches.join() === 'texts,templates' );
	check( '...and the record of the new key has the one field of a text for all languages, in the row of the key', k.fields( k.record() ).map( f => f.dataset.locale ).join() === '*' && k.controlOf( k.record(), '*' ).value === 'Welcome' && k.hash() === keyAddress( PAGE, '/template/page-services/hero/title' ) );
	k.go( 'de_DE' );
	check( 'the working language of the workbench is said by the pill of the form, and goes with it', true );
}

{
	// the word of the address that makes a key, typed and reloaded
	const k = keysPage( keysResponse(), { hash : '#text/keys/@new' } ).open();

	check( 'a reload at #text/keys/@new opens the form of a new key over the list, as the one entry of the history', k.record() !== null && k.panel._record.creating() === true && has( k.list, 'admin-hidden' ) === false && k.hash() === '#text/keys/@new' && k.h.entries.length === 1 && k.titleOf( k.record() ) === W('/_admin/keys/label/new') );
	k.close( k.record() );
	check( '...and the cross there writes the address of the list over it, as no entry was made', k.record() === null && k.hash() === '#text/keys' );

	const t = keysRow( PAGE );
	t.typed( '#text/keys/@new' );
	check( 'the address of a new key typed while the table of a row is up opens the form over the table', t.record() !== null && t.panel._record.creating() === true && t.ids().length === KEYS.length && t.hash() === '#text/keys/@new' );
	t.back();
	check( 'Back closes the form and shows the table of the row it was opened from', t.record() === null && t.hash() === keyAddress( PAGE ) && t.ids().length === KEYS.length );
	t.forward();
	check( '...and Forward opens it again', t.record() !== null && t.panel._record.creating() === true && t.hash() === '#text/keys/@new' );

	const u = keysShown();
	fire( u.newButton(), 'click' );
	typeKey( idIn( u.record(), 'keys-form-part' ), 'typed' );
	u.step( '#text/keys' );
	check( 'an address that leads away from a form that holds input asks first, with the question of the workbench; no keeps the form, with what was typed, at its address', u.dialog() !== u.record() && u.record() !== null );
	u.answerAsk( W('/_admin/common/label/cancel') );
	check( '...Cancel puts the address back to the form\'s', u.record() !== null && u.hash() === '#text/keys/@new' && idIn( u.record(), 'keys-form-part' ).value === 'typed' );
}

{
	// the form of a new key and a record of a key: the one that is open is the one the address names
	const k = keysRecord( PAGE, K1 );

	fire( k.button( k.record(), W('/_admin/common/label/save-next') ), 'click' );
	k.close( k.record() );
	const b = keysRecord( PAGE, K1 );
	b.step( '#text/keys/@new' );
	check( 'the form of a new key typed while a record is open takes the place of the record, in the same dialog', b.record() !== null && b.panel._record.creating() === true && b.hash() === '#text/keys/@new' && b.titleOf( b.record() ) === W('/_admin/keys/label/new') );
	b.step( keyAddress( PAGE, K3 ) );
	check( '...and the address of a key takes the form\'s place, which was not typed into: the record of that key', b.record() !== null && b.panel._record.creating() === false && b.subtitleOf( b.record() ) === K3 && b.hash() === keyAddress( PAGE, K3 ) );
}

// --- the scan: a view of its own, beside the records ------------------------------------------------------------------------------------------------------

const SCAN = { missing : [
	{ key : '/project/shop/hero/title', files : [ 'page-shop.tpl' ], kind : 'create', hint : null },
	{ key : '/project/shop/hero/text', files : [ 'page-shop.tpl', 'page-other.tpl' ], kind : 'create', hint : 'feature', owner : 'consent' },
	{ key : '/foo/bar', files : [ 'page-shop.tpl' ], kind : 'grammar' },
	{ key : '/_nino/webpage/shop/name', files : [ 'page-shop.tpl' ], kind : 'system', writer : 'routes' },
], alsoUsed : [ { key : '/template/page-shop/x/y', files : [ 'page-other.tpl' ] } ] };

{
	const k = keysShown();
	k.handle.scan = SCAN;
	const before = k.h.index();

	fire( k.scanButton(), 'click' );
	check( 'the scan is a view with an address of its own, #text/keys/@scan, as one entry more of the history: the level of the form shows it, with its way back', k.hash() === '#text/keys/@scan' && k.h.index() === before + 1 && has( k.form, 'admin-hidden' ) === false && has( k.list, 'admin-hidden' ) === true && k.panel._scanning === true
		&& byClass( k.form, 'nino-admin-back-link' ).length === 1 && byClass( k.form, 'nino-admin-contextbar-title' )[0].textContent === W('/_admin/keys/label/scan') && k.sent.filter( c => c.endpoint === 'scan' ).length === 1 );
	const rows = byClass( k.form, 'admin-keys-scan-row' );
	check( 'a finding is a card with the key as its name, where it is read, and a starting value - where a key can be made here - and the three outcomes of it: a value, nothing, or ignored for good', rows.length === 4 && rows[0].children[0].textContent === '/project/shop/hero/title'
		&& tag( rows[0], 'input' ).length === 2 && tag( rows[2], 'input' ).filter( i => i.type === 'text' ).length === 0 && tag( rows[3], 'input' ).length === 0 );
	check( '...what the scan says besides - why a key cannot be made, who writes it - stands at the finding as a line of its own, and every note has its words', allText( rows[1] ).indexOf( F('/_admin/keys/scan/hint-feature','consent') ) !== -1 && allText( rows[2] ).indexOf( W('/_admin/keys/scan/grammar') ) !== -1 && allText( rows[3] ).indexOf( W('/_admin/keys/scan/system-routes') ) !== -1 );
	check( '...a key of the system has nothing to tick: it is the system\'s to write', tag( rows[3], 'input' ).length === 0 && tag( rows[2], 'input' ).filter( i => i.type === 'checkbox' ).length === 1 );
	check( 'the keys another template reads as well are listed as a note, and counted nowhere', allText( k.form ).indexOf( '/template/page-shop/x/y' ) !== -1 && k.panel._scanRows.length === 3 );
	check( 'the bar has the one main button, and no explanation stands open over the rows: the scan is explained behind the ? of the panel', byClass( k.form, 'nino-admin-btn-primary' ).length === 1 && byClass( k.form, 'nino-admin-hint' ).length === 0 );
	check( 'a form nobody touched holds nothing unsaved', dirtyNow( k ) === false );
	typeKey( tag( rows[0], 'input' ).find( i => i.type === 'text' ), 'Eine Überschrift' );
	const ignore = tag( rows[2], 'input' ).find( i => i.type === 'checkbox' );
	ignore.checked = true;
	fire( ignore, 'change', { target : ignore } );
	check( 'a value typed or a key ticked is unsaved input, which the shell knows by the one name of the tab', dirtyNow( k ) === true );
	fire( tag( k.form, 'form' )[0], 'submit' );
	const apply = k.writes().at(-1);
	check( 'the whole form goes to the server in one request, with the language the tab shows: every finding, the ones left empty included - which the server passes over - and the ignored ones', apply.endpoint === 'scanapply' && apply.payload.rows.length === 3 && apply.payload.locale === 'de_DE'
		&& apply.payload.rows[0].value === 'Eine Überschrift' && apply.payload.rows[0].ignore === false && apply.payload.rows[1].value === '' && apply.payload.rows[2].ignore === true );
	check( 'what came of it is one sentence as a toast, and the list is back - with the keys that were made', k.told.at(-1) === W('/_admin/keys/scan/result').replace( '%c', '1' ).replace( '%i', '1' ).replace( '%s', '1' ) && has( k.form, 'admin-hidden' ) === true && has( k.list, 'admin-hidden' ) === false && k.panel._scanning === false && k.hash() === '#text/keys' );
	check( '...a write of texts, and of templates where a key of a template was made', k.touches.join() === 'texts' && dirtyNow( k ) === false );
	k.handle.scan = { missing : [], alsoUsed : [] };
	fire( k.scanButton(), 'click' );
	check( 'a scan that finds nothing says so in the box of the workbench, with no bar to save', byClass( k.form, 'nino-admin-empty' ).length === 1 && byClass( k.form, 'nino-admin-actionbar' ).length === 0 && dirtyNow( k ) === false );
}

{
	// a scan with a template key: the templates are told
	const k = keysShown();
	k.handle.scan = { missing : [ { key : '/template/page-shop/hero/title', files : [ 'page-shop.tpl' ], kind : 'create', hint : null } ], alsoUsed : [] };
	fire( k.scanButton(), 'click' );
	typeKey( tag( byClass( k.form, 'admin-keys-scan-row' )[0], 'input' ).find( i => i.type === 'text' ), 'Shop' );
	fire( tag( k.form, 'form' )[0], 'submit' );
	check( 'a scan that makes a key of a template tells the shell of the templates as well', k.touches.join() === 'texts,templates' );
}

{
	// the scan beside the records: its unsaved input is asked about on leaving the tab, and Ctrl+S saves it through the registry's entry
	const k = keysShown();
	k.handle.scan = SCAN;
	fire( k.scanButton(), 'click' );
	typeKey( tag( byClass( k.form, 'admin-keys-scan-row' )[0], 'input' ).find( i => i.type === 'text' ), 'Wert' );
	let left = 0, stayed = 0;

	k.h.dirty.guard( [ 'keys' ], () => { left++ }, () => { stayed++ } );
	check( 'a way out of the tab - the rail, the language of the interface - asks about the unsaved input of the scan first, as it asks about the input of a record', k.dialog() !== null && k.titleOf( k.dialog() ) === W('/_admin/common/dirty/title') && left === 0 );
	k.answerAsk( W('/_admin/common/label/cancel') );
	check( '...Cancel stays', left === 0 && stayed === 1 && k.panel._scanning === true );
	k.h.dirty.shortcut( { ctrlKey : true, key : 's', preventDefault : () => {} } );
	check( 'Ctrl+S saves the scan, through the entry of the tab', k.writes().at(-1).endpoint === 'scanapply' && k.panel._scanning === false && k.hash() === '#text/keys' );

	// the same entry answers for a record
	const r = keysRecord( PAGE, K1 );
	r.type( r.record(), 'de_DE', 'Strg S' );
	r.h.dirty.shortcut( { ctrlKey : true, key : 's', preventDefault : () => {} } );
	check( '...and for a record that holds input: Ctrl+S saves it', r.writes().length === 1 && r.writes()[0].endpoint === 'savebatch' && dirtyNow( r ) === false );
}

{
	// leaving the scan by the history with input in it asks first, as the shell asks for a record
	const k = keysShown();
	k.handle.scan = SCAN;
	fire( k.scanButton(), 'click' );
	typeKey( tag( byClass( k.form, 'admin-keys-scan-row' )[0], 'input' ).find( i => i.type === 'text' ), 'Wert' );
	k.back();
	check( 'Back from a scan that holds input asks what to do with it, with the question of the workbench: the scan stays on screen under it', k.dialog() !== null && k.titleOf( k.dialog() ) === W('/_admin/common/dirty/title') && k.panel._scanning === true );
	fire( k.button( k.dialog(), W('/_admin/common/label/cancel') ), 'click' );
	check( '...Cancel keeps the scan, with what was typed, and takes the step back through the history: the history is asked to go forward again', k.panel._scanning === true && tag( byClass( k.form, 'admin-keys-scan-row' )[0], 'input' ).find( i => i.type === 'text' ).value === 'Wert' && k.dialog() === null && k.h.went.at(-1) === 1 );
	k.forward();
	check( '...which brings the address of the scan back', k.hash() === '#text/keys/@scan' && k.panel._scanning === true && k.dialog() === null );
	k.back();
	fire( k.button( k.dialog(), W('/_admin/common/label/discard') ), 'click' );
	check( '...Discard leaves the scan for the list', k.panel._scanning === false && has( k.list, 'admin-hidden' ) === false && k.writes().length === 0 );
}

{
	// every transition between the scan, the table, the record, the form of a new key and the rename by address, and through the history
	const k = keysShown();
	k.handle.scan = SCAN;

	k.typed( keyAddress( PAGE ) );
	k.typed( '#text/keys/@scan' );
	check( 'the address of the scan, typed over the table of a row, shows the scan in the place of the table', k.panel._scanning === true && byClass( k.form, 'admin-keys-scan-row' ).length === 4 && k.hash() === '#text/keys/@scan' );
	k.back();
	check( 'Back leaves the scan for the table of the row it came from', k.panel._scanning === false && k.ids().length === KEYS.length && k.hash() === keyAddress( PAGE ) );
	k.forward();
	check( '...Forward is the scan again', k.panel._scanning === true && k.hash() === '#text/keys/@scan' );
	k.typed( keyAddress( PAGE, K1 ) );
	check( 'the address of a key typed while the scan is up draws the table of its row and opens the record', k.panel._scanning === false && k.record() !== null && k.subtitleOf( k.record() ) === K1 && k.ids().length === KEYS.length );
	k.typed( '#text/keys/@scan' );
	check( 'the address of the scan typed while a record is open closes the record and shows the scan', k.record() === null && k.panel._scanning === true && k.hash() === '#text/keys/@scan' );
	k.typed( '#text/keys/@new' );
	check( 'the address of a new key typed while the scan is up leaves the scan and opens the form', k.panel._scanning === false && k.record() !== null && k.panel._record.creating() === true && k.hash() === '#text/keys/@new' );
	k.typed( renameAddress( K3 ) );
	check( 'the address of a rename typed while the form of a new key is open opens the record of that key with the dialog over it', k.record() !== null && k.panel._record.creating() === false && k.subtitleOf( k.record() ) === K3 && k.panel._renaming === K3 && k.dialog() !== k.record() && k.hash() === renameAddress( K3 ) );
	k.typed( '#text/keys/@scan' );
	check( 'the address of the scan typed while the rename dialog is open closes the dialog and the record and shows the scan', k.dialog() === null && k.record() === null && k.panel._renaming === null && k.panel._scanning === true && k.hash() === '#text/keys/@scan' );
	k.typed( '#text/keys' );
	check( 'the address of the list shows the list', k.panel._scanning === false && has( k.list, 'admin-hidden' ) === false && k.hash() === '#text/keys' );
}

{
	// the way through the history: list, row, key, rename, and back
	const k = keysShown();

	fire( k.linkOf( PAGE ), 'click', { target : k.linkOf( PAGE ) } );
	k.openRow( K3 );
	fire( k.button( k.record(), RENAME() ), 'click' );
	const states = () => [ k.hash(), k.record() !== null, k.dialog() !== null && k.dialog() !== k.record(), has( k.list, 'admin-hidden' ) === false ].join();

	check( 'row, key and rename are three steps of the history, each its own address', states() === [ renameAddress( K3 ), true, true, false ].join() );
	k.back();
	check( 'Back: the dialog goes, the record stays', states() === [ keyAddress( PAGE, K3 ), true, false, false ].join() );
	k.back();
	check( 'Back: the record goes, the table of the row stays', states() === [ keyAddress( PAGE ), false, false, false ].join() && k.ids().length === KEYS.length );
	k.back();
	check( 'Back: the list', states() === [ '#text/keys', false, false, true ].join() );
	k.forward();
	k.forward();
	k.forward();
	check( 'Forward walks the same way again, to the dialog over the record over the table', states() === [ renameAddress( K3 ), true, true, false ].join() );
}

// --- the words --------------------------------------------------------------------------------------------------------------------------------------------

{
	// every fill the two scripts ask for is there in both languages: every quoted fill of the tab, the ones put together at runtime among them
	const files = [ '_admin/Nino/Modules/Text/assets/keys.js', '_admin/Nino/Modules/Text/assets/textkeys.js' ];
	const used = new Set();
	files.forEach( file => { for( const m of fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' ).matchAll( /'(\/_admin\/[^']+)'/g ) ) used.add( m[1] ) } );
	[ 'en_US', 'de_DE' ].forEach( locale => {
		const words = workbenchWords( locale );
		const missing = [ ...used ].filter( key => key.endsWith('/') === false && key.endsWith('-') === false && words[key] === undefined );
		check( locale+ ': every fill the scripts of the Text Keys tab name is there'+ ( missing.length === 0 ? '' : ' - not '+ missing.join() ), used.size > 40 && missing.length === 0 );
		const sorts = [ 'grammar', 'system-routes', 'system-language', 'system-none', 'hint-feature', 'hint-module' ].filter( s => words['/_admin/keys/scan/'+ s] === undefined );
		check( locale+ ': every note of the scan, which is put together at runtime, has its words', sorts.length === 0 );
		check( locale+ ': every word of a format and of its conversion is there', [ 'auto', 'plain', 'inline', 'lines', 'blocks' ].every( f => words['/_admin/keys/format/'+ f] !== undefined ) && [ 'plain', 'inline', 'lines' ].every( f => words['/_admin/keys/convert/'+ f] !== undefined ) );
		check( locale+ ': the counts, the heads, the filter, the words of the table and the record are all there', [ 'count/key', 'count/keys', 'count/hidden', 'col/state', 'col/auto', 'filter/all', 'filter/hidden', 'filter/global', 'row/open', 'record/prev', 'record/next', 'record/missing', 'msg/created', 'label/text', 'label/global', 'label/hide',
			'help/format', 'help/limit', 'help/global', 'help/hidden' ].every( k => words['/_admin/keys/'+ k] !== undefined && words['/_admin/keys/'+ k] !== '' ) );
	} );
	check( 'the singular of a count is another word than the plural, in English', workbenchWords('en_US')['/_admin/keys/count/key'] !== workbenchWords('en_US')['/_admin/keys/count/keys'] );
}

check( 'every address the Text and Keys panels wrote or drew - a row, a key of it, a new key, a rename, the scan - is read back by the router as the same screen and the same parts', codec.written.length > 10 && codec.mismatches.length === 0
	&& codec.written.some( written => written.screen === 'text' && written.parts.length === 2 ) && codec.written.some( written => written.screen === 'keys' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@rename' ) && written.parts.length === 2 )
	&& codec.written.some( written => written.screen === 'keys' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@new' ) ) && codec.written.some( written => written.screen === 'keys' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@scan' ) )
	&& codec.written.some( written => written.screen === 'keys' && written.parts.length === 2 && typeof written.parts[0] === 'string' ) );

done();
