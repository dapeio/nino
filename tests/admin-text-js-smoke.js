/**
 *	Nino									A compact filesystembased php framework
 *	admin-text-js-smoke.js	DOM-light checks for the Text panel and its Keys tab
 *										(_admin/Nino/Modules/Text/assets): how keys are named and
 *										fall into rows, sections and fields, how they are searched,
 *										and what the two views do on a fake page.
 *
 *										The groups of both tabs are the list of the workbench, with
 *										the same names and an address each; the language the
 *										texts are shown in is the workbench's own, there is no
 *										switch of it here, and a form shows one language and
 *										writes that one and no other. The Text panel saves what
 *										was changed with one Save and sends nothing where nothing
 *										was; the Keys tab saves a key's shape and its text with
 *										the one Save as well, asks once for the formats that
 *										would change what is stored, and says at the field which
 *										part a server turned down - what is not saved is not
 *										counted as saved. A key is made and renamed in a view of
 *										its own with an address, checked when it is sent.
 *
 *	Usage: node tests/admin-text-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');
const { check, done, fills, load, shellRouter } = require( './harness.js' );

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
	[ '_admin/assets/Nino.admin.js', '_admin/Nino/Modules/Text/assets/textkeys.js', '_admin/Nino/Modules/Text/assets/admin.js', '_admin/Nino/Modules/Text/assets/keys.js' ]
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

// Whether the focus is in a level, as a person who looks at the page would say
const focusIn = ( w, level ) => w.dom.doc.activeElement !== null && w.dom.doc.activeElement.closest( '#'+ level.id ) === level;

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
// --- the Text panel: the list, the search, the form ------------------------------------

const copies = list => list.map( e => Object.assign( {}, e, { values : Object.assign( {}, e.values ) } ) );
// The words of the workbench in German, by their fills: what a check asks for is the fill, never the wording
const D = workbenchWords('de_DE');
const W = key => D[key];
const F = ( key, ...params ) => { let at = 0; return W( key ).replace( /%[sdn]/g, token => at < params.length ? String( params[at++] ) : token ) };
const stem = key => W( key ).split('%')[0];
const group = id => W('/_admin/text/group/'+ id);

const textResponse = ( extra ) => Object.assign( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ), locales : [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true } ], selectedLocale : 'de_DE', order : homeOrder, pages : homePages,
	templates : homeData().templates, features : homeData().features }, extra ?? {} );

/** A page of the workbench with the Text panel open on a stubbed server: what it asked, what it said as a toast, and the answers it can be made to give */
function openedText( response, hash, locale ) {
	const w = world( locale ?? 'de_DE', hash );
	const { title, levels : [ list, form ] } = w.pane( 'text', 'text-list', 'text-form' );
	title.textContent = 'Texte';
	form.classList.add('admin-hidden');
	// The name the head of the pane says: the fake page cannot answer the selector the block asks it with
	w.Nino.adminUi.panelName = () => 'Texte';
	w.Nino.adminUi.fillsDialog = options => { w.dialogs.push( options ); return true };
	const sent = [], told = [], held = [];
	w.dialogs = [];
	const handle = { hold : false, refuse : {}, fail : null, stored : {} };
	w.Nino.adminUi.say = sentence => told.push( sentence );
	const text = w.Nino.admin.text;
	text._apiCall = ( endpoint, payload, callback ) => {
		sent.push( { endpoint : endpoint, payload : payload } );
		const answer = () => {
			if( endpoint === 'keys' )
				return callback( 200, response );
			if( endpoint === 'fills' )
				return callback( 200, { fills : [ { key : '/project/company/general/name', notation : '[[/project/company/general/name]]', value : 'Acme' } ] } );
			if( handle.fail !== null )
				return callback( handle.fail.status, handle.fail.body );
			const results = {};
			payload.items.forEach( item => {
				// As the server does: a text that was read as something other than what is stored now is not written, unless it is forced
				const changed = item.was !== undefined && item.force !== true && handle.stored[item.locale+ ' '+ item.key] !== undefined && handle.stored[item.locale+ ' '+ item.key] !== item.was;
				results[item.key] = changed === true ? { ok : false, error : 'changed' } : ( handle.refuse[item.key] ?? { ok : true, value : item.value } );
				if( results[item.key].ok === true )
					handle.stored[item.locale+ ' '+ item.key] = item.value;
			} );
			callback( 200, { results : results } );
		};
		if( handle.hold === true && endpoint === 'savebatch' )
			held.push( answer );
		else
			answer();
	};
	text.init();
	return Object.assign( w, { list, form, title, sent, text, told, handle, held, dialogs : w.dialogs } );
}

/** The names of the fields of a section, as the labels at the left say them */
const namesOf = section => section.querySelectorAll('.nino-admin-field').map( f => f.children[0].firstChild.textContent );
const writes = t => t.sent.filter( c => c.endpoint === 'savebatch' );
const fieldOf = ( t, key ) => t.form.querySelector('[data-key="'+ key+ '"]');
const controlOf = ( t, key ) => fieldOf( t, key ).querySelector('input, textarea');
const typeInto = ( control, value ) => { control.value = value; control.fire('input') };
const submitText = t => t.form.querySelector('form').fire('submit');
const openRow = ( t, group ) => opens( t.list ).find( r => keyOf( r ) === group ).click();

{
	const t = openedText( textResponse() );
	const rows = opens( t.list );

	check( 'the list is two blocks: the pages, and the rest in groups - each with its heading', t.list.querySelectorAll('h2').map( h => h.textContent ).join() === [ 'pages', 'general' ].map( group ).join()
		&& t.list.querySelectorAll('h3').map( h => h.textContent ).join() === [ 'project', 'blocks', 'modules', 'features', 'system', 'other' ].map( group ).join() );
	check( '...a row for each page, named after the route, and one for each group of the others - the rows of the workbench\'s list', rows.length === Object.keys( t.text._model.rows ).length && keyOf( rows[0] ) === 'template/page-services'
		&& rows.map( r => r.querySelector('strong').textContent ).slice( 0, 2 ).join() === 'Leistungen,Rechtliches' && t.list.querySelectorAll('li').every( li => li.classList.contains('nino-admin-list-row') ) );
	check( '...with what it holds as its line: how many keys and the beginning of their texts', rows[0].querySelector('small').textContent.startsWith( '(9) ' ) );
	check( 'every row is a link to an address of its own, which writes it when it is pressed', rows.every( r => r.tagName === 'a' && r.href === '#text/text/'+ encodeURIComponent( keyOf( r ) ) ) );
	check( 'the bar of the list has the search and the filter and no switch of a language: the language is the workbench\'s', t.list.querySelector('#text-search').getAttribute('aria-label') === W('/_admin/text/label/search')
		&& t.list.querySelectorAll('select').length === 0 && t.list.querySelector('.nino-admin-filterbar') !== null && t.list.querySelector('#text-list-empty').classList.contains('nino-admin-chip') );
	check( '...and the filter names the language the way the workbench does, and is off', t.list.querySelector('#text-list-empty').textContent === F('/_admin/text/label/empty-in','de_DE') && t.list.querySelector('#text-list-empty').getAttribute('aria-pressed') === 'false' );
	check( 'the words of the workbench are not among the rows', opens( t.list ).every( r => keyOf( r ) !== '_admin' ) );

	t.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	t.Nino.admin.workLocale.set( 'en_US' );
	check( 'the language the rail moves is the one the list is named in, and the filter says it', t.list.querySelector('#text-list-empty').textContent === F('/_admin/text/label/empty-in','English')
		&& opens( t.list ).slice( 0, 2 ).map( r => r.querySelector('strong').textContent ).join() === 'Services,Legal' );
	t.Nino.admin.workLocale.set( 'de_DE' );

	// the search
	const box = t.list.querySelector('#text-search');
	box.value = 'beraten';
	box.fire('input');
	check( 'a search replaces the rows by hits, the rows of the list again: the path, the piece of text with the word marked, the key behind it', opens( t.list ).length === 1 && t.list.querySelectorAll('li').length === 1
		&& t.list.querySelector('strong').textContent === 'Leistungen › Eintrag 1 › Text' && t.list.querySelector('mark')?.textContent === 'beraten' && t.list.querySelector('.admin-text-hit-key').textContent === '/template/page-services/item-1/text'
		&& t.list.querySelector('small').textContent.startsWith( 'Wir ' ) );
	check( '...each a link to the row of the key, at its own address', opens( t.list )[0].href === '#text/text/template%2Fpage-services/%2Ftemplate%2Fpage-services%2Fitem-1%2Ftext' );
	check( '...and says how many there are, where a screen reader hears it', t.list.querySelector('#text-list-status').textContent === F('/_admin/text/msg/result',1) && t.list.querySelector('#text-list-status').getAttribute('aria-live') === 'polite' );
	check( 'a hit in the language on screen has no code of a language', t.list.querySelectorAll('.nino-admin-row-actions').length === 0 );
	box.value = 'xyzzy';
	box.fire('input');
	check( 'nothing found says so, with the empty state of the workbench', t.list.querySelectorAll('li').length === 0 && t.list.querySelector('.nino-admin-empty').textContent === W('/_admin/text/msg/nothing') && t.list.querySelector('#text-list-status').textContent === F('/_admin/text/msg/results',0) );
	box.value = '';
	box.fire('input');
	check( 'an empty box is the rows again', opens( t.list ).length === rows.length && t.list.querySelector('#text-list-status').textContent === '' );

	// a hit in another language: its code asks for the language, and nothing else
	box.value = 'consulting';
	box.fire('input');
	const lang = t.list.querySelector('.nino-admin-row-actions button');
	check( 'a hit in another language shows that language\'s code as a small button of the workbench, named for what it does', lang.textContent === 'en_US' && lang.classList.contains('nino-admin-btn--small') && lang.getAttribute('aria-label') === F('/_admin/text/label/switch-language','English') );
	const chosen = [];
	t.Nino.admin.workLocale.choose = code => chosen.push( code );
	t.list.querySelector('.nino-admin-row-actions button').click();
	check( '...which asks the workbench for it - as every change of the language is, so that a view with unsaved input is asked about first - and opens nothing', chosen.join() === 'en_US' && t.text._currentGroup === null );
	t.Nino.admin.workLocale.choose = undefined;
	box.value = '';
	box.fire('input');

	t.Nino.admin.workLocale.set( 'en_US' );
	t.list.querySelector('#text-list-empty').click();
	check( 'the filter for the keys with no text in the language shows them as hits, and says it is on', t.list.querySelector('#text-list-empty').getAttribute('aria-pressed') === 'true'
		&& t.list.querySelectorAll('.admin-text-hit-key').map( h => h.textContent ).join() === '/template/page-services/intro/text' );
	t.list.querySelector('#text-list-empty').click();
	t.Nino.admin.workLocale.set( 'de_DE' );

	// a hit opens its row, at the field
	box.value = 'beraten';
	box.fire('input');
	t.calls.length = 0;
	opens( t.list )[0].click();
	const field = fieldOf( t, '/template/page-services/item-1/text' );
	check( 'a hit opens its row at the field - the address names the key, the field is marked and scrolled to', t.text._currentGroup === 'template/page-services' && t.form.classList.contains('admin-hidden') === false && t.list.classList.contains('admin-hidden') === true
		&& t.calls.includes( 'go text/template/page-services//template/page-services/item-1/text' ) && t.calls.includes( 'set text/template/page-services//template/page-services/item-1/text' ) && field.classList.contains('is-found') && field.scrolled === true );
}

{
	// the form of a row
	const t = openedText( textResponse() );
	openRow( t, 'template/page-services' );

	const back = t.form.querySelectorAll('.nino-admin-back-link');
	check( 'the way back is one link in the context bar, to the panel by its name, and the bar says what the view is', back.length === 1 && back[0].textContent === F('/_admin/common/label/back-to',W('/_admin/text/label/back-name')) && back[0].closest('.nino-admin-contextbar') !== null
		&& t.form.querySelector('.nino-admin-contextbar-title').textContent === 'Leistungen' && t.form.querySelectorAll('.main-title').length === 0 );
	check( 'the form has no switch of a language and no select at all', t.form.querySelectorAll('select').length === 0 );
	const sections = t.form.querySelectorAll('fieldset.admin-text-section');
	check( 'the form is a section for each part, the details of the page first, then in the order of the template', sections.map( f => f.dataset.section ).join() === 'webpage:/services,intro,item-1,item-2,legal' );
	check( '...a legend with the name of the section and, for a page, its route small', sections[0].querySelector('legend .admin-text-section-name').textContent === W('/_admin/text/label/page-details') && sections[0].querySelector('legend code')?.textContent === '/services'
		&& sections[1].querySelector('legend .admin-text-section-name').textContent === W('/_admin/common/word/intro') && sections[1].querySelector('legend code') === null );
	check( 'the fields stand in a grid whose labels are at the left: a fieldgrid in the fieldset, a field of the workbench for each key', sections.every( s => s.querySelector('.nino-admin-fieldgrid') !== null && s.querySelector('.nino-admin-fieldgrid').children.every( f => f.classList.contains('nino-admin-field') ) ) );
	check( '...named in German - the key is not the name', namesOf( sections[0] ).join() === [ 'name', 'title', 'description' ].map( f => W('/_admin/text/label/page-'+ f) ).join() );

	// what a field is
	const form = t.form.querySelector('form');
	check( 'the form checks nothing itself, and is the one form of the view', form.noValidate === true && t.form.querySelectorAll('form').length === 1 );
	check( 'a plain text of a line is a line, with its key and its count under it', ( () => { const f = fieldOf( t, '/template/page-services/intro/title' ); return f.querySelector('input') !== null && f.querySelector('textarea') === null
		&& f.querySelector('.admin-text-meta code').textContent === '/template/page-services/intro/title' && f.querySelector('.nino-admin-char-counter').textContent === '10 / 150' } )() );
	check( 'a text with markup is the rich-text editor of the workbench, named by a span its group points at', ( () => { const f = fieldOf( t, '/template/page-services/item-1/text' ); return f.querySelector('[data-editor]') !== null
		&& f.getAttribute('role') === 'group' && f.getAttribute('aria-labelledby') === f.querySelector('span').id } )() );
	check( 'no field is a label that holds the editor, and none carries the key as its name', t.form.querySelectorAll('.admin-text-field').every( f => f.querySelector('span').textContent.indexOf('/') === -1 ) );
	check( 'the textfills are asked for at a field the account may change that is a text with markup', fieldOf( t, '/template/page-services/item-1/text' ).querySelector('.nino-admin-fills-button') !== null );
	check( '...and at no field of one line', t.form.querySelectorAll('.admin-text-field').filter( f => f.querySelector('input') !== null ).every( f => f.querySelector('.nino-admin-fills-button') === null ) );

	// the bar: one, with one main button
	const bars = t.form.querySelectorAll('.nino-admin-actionbar');
	check( 'the view has one bar, with one main button that submits - no button of Save in the content', bars.length === 1 && bars[0].querySelectorAll('.nino-admin-btn-primary').length === 1 && bars[0].querySelector('.nino-admin-btn-primary').type === 'submit'
		&& t.form.querySelectorAll('button').filter( b => b.type === 'submit' ).length === 1 && bars[0].querySelectorAll('.nino-admin-btn-danger').length === 0 );
	check( '...with the state of the form and the line that says what a save did', bars[0].querySelector('.nino-admin-actionbar-state') !== null && t.form.querySelector('#text-form-msg') !== null );
	check( 'the panel is in the registry of unsaved input under its name, with the bar that carries its mark', typeof t.registry.text.isDirty === 'function' && typeof t.registry.text.save === 'function' && typeof t.registry.text.discard === 'function' && t.registry.text.bar() === bars[0] );
	check( 'a form nobody touched holds nothing unsaved', t.registry.text.isDirty() === false );

	// a save that changes nothing sends nothing
	submitText( t );
	check( 'a save with nothing changed sends nothing and says it is saved', writes( t ).length === 0 && t.told.join() === W('/_admin/common/msg/saved') );

	// a save sends what was changed, in the language the form shows
	typeInto( controlOf( t, '/template/page-services/intro/title' ), 'Leistungen neu' );
	check( 'a field that differs from what it held is unsaved input for the shell', t.registry.text.isDirty() === true );
	typeInto( controlOf( t, '/template/page-services/intro/title' ), 'Leistungen' );
	check( '...and typing it back takes that away', t.registry.text.isDirty() === false );
	typeInto( controlOf( t, '/template/page-services/intro/title' ), 'Leistungen neu' );
	typeInto( controlOf( t, '/_nino/webpage/services/title' ), 'Neuer Seitentitel' );
	t.told.length = 0;
	submitText( t );
	const batch = writes( t )[0];
	check( 'a save sends the keys that were changed - the text of the template and the details of the page together - and no other', writes( t ).length === 1 && batch.payload.items.map( i => i.key ).sort().join() === '/_nino/webpage/services/title,/template/page-services/intro/title'
		&& batch.payload.items.every( i => i.locale === 'de_DE' ) );
	check( '...says it with a toast, the line left empty and the form as it was - no jump to the list', t.told.join() === W('/_admin/common/msg/saved') && t.form.querySelector('#text-form-msg').textContent === '' && t.form.classList.contains('admin-hidden') === false && t.registry.text.isDirty() === false );
	check( '...and tells the shell that texts were written, once, and no template, which the panel does not write', t.touched.join() === 'texts' );
	check( '...what the server answered is what the model holds', t.text._groups['template/page-services'].find( e => e.key === '/template/page-services/intro/title' ).values.de_DE === 'Leistungen neu' );
}

{
	// another tab wrote the key since: the save is turned down, the question is the kernel's, and each answer does what it says
	const t = openedText( textResponse() );
	const KEY = '/template/page-services/intro/title';
	const read = t.text._groups['template/page-services'].find( e => e.key === KEY ).values.de_DE;
	const questions = [];
	t.Nino.adminUi.conflict = options => { questions.push( options ); return true };
	openRow( t, 'template/page-services' );
	t.handle.stored['de_DE '+ KEY] = 'Fremder Text';
	typeInto( controlOf( t, KEY ), 'Mein Text' );
	submitText( t );
	check( 'a save sends the text each key was read as, beside what is written', writes( t )[0].payload.items.every( i => i.was !== undefined ) && writes( t )[0].payload.items.find( i => i.key === KEY ).was === read && writes( t )[0].payload.items.every( i => i.force === undefined ) );
	check( '...and a key that was written by another meanwhile asks the question of a refused save, with the key in it, and says nothing was saved: the text stays typed and unsaved', questions.length === 1 && questions[0].message.indexOf( KEY ) !== -1
		&& t.told.length === 0 && t.registry.text.isDirty() === true && controlOf( t, KEY ).value === 'Mein Text' && t.form.querySelector('#text-form-msg').textContent !== '' );
	questions[0].onOverwrite();
	const forced = writes( t ).at(-1).payload.items;
	check( 'Overwrite writes it again, once, for that key alone and without the text it was read as - and then it is saved', forced.length === 1 && forced[0].key === KEY && forced[0].force === true && forced[0].was === undefined && forced[0].value === 'Mein Text'
		&& t.handle.stored['de_DE '+ KEY] === 'Mein Text' && t.registry.text.isDirty() === false && t.told.at(-1) === W('/_admin/common/msg/saved') );
	t.handle.stored['de_DE '+ KEY] = 'Wieder fremd';
	typeInto( controlOf( t, KEY ), 'Noch einer' );
	submitText( t );
	questions.at(-1).onReload();
	check( 'Reload puts the focus on the field of the text that was turned down, drawn again', t.dom.doc.activeElement === controlOf( t, KEY ) );
	check( 'the question names the text by its place and by its key', questions.at(-1).message.indexOf( KEY ) !== -1 && questions.at(-1).message.indexOf(' › ') !== -1 );
	check( 'Reload drops what was typed and shows what is stored', t.sent.filter( c => c.endpoint === 'keys' ).length === 2 && controlOf( t, KEY ).value !== 'Noch einer' && t.registry.text.isDirty() === false );
}

{
	// a key that is the same in every language says so, and is written to the one text
	const t = openedText( textResponse() );
	openRow( t, 'project/company' );
	const name = fieldOf( t, '/project/company/general/name' );
	check( 'a field that is the same in every language carries a badge beside its name, and the field is described by it', name.querySelector('.nino-admin-badge').textContent === W('/_admin/text/label/all-languages')
		&& name.querySelector('input').getAttribute('aria-describedby') === name.querySelector('.nino-admin-badge').id && fieldOf( t, '/project/company/general/description' ).querySelector('.nino-admin-badge') === null );
	check( 'the row is called by its name in the vocabulary, and the fields that are the same in every language come first in their section', t.form.querySelector('.nino-admin-contextbar-title').textContent === W('/_admin/common/word/company')
		&& namesOf( t.form.querySelectorAll('fieldset.admin-text-section')[0] ).join() === [ 'name', 'description' ].map( w => W('/_admin/common/word/'+ w) ).join() );
	typeInto( controlOf( t, '/project/company/general/name' ), 'Acme AG' );
	typeInto( controlOf( t, '/project/company/general/description' ), 'Wir bauen mehr' );
	submitText( t );
	const items = writes( t )[0].payload.items;
	check( 'a save writes it to the one text, and a text of a language to the language the form shows', items.find( i => i.key === '/project/company/general/name' ).locale === '*' && items.find( i => i.key === '/project/company/general/description' ).locale === 'de_DE' );
}

{
	// textareas: a text with a line break, or a long one, is a field of several lines
	const longText = 'Ein langer Satz, der über die Länge einer Zeile hinausgeht und deshalb in einem Feld von mehreren Zeilen steht.';
	const response = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).concat( [ textEntry( '/project/shop/about/two', { de_DE : 'Zeile eins\nZeile zwei', en_US : '' } ), textEntry( '/project/shop/about/long', { de_DE : longText, en_US : '' } ), textEntry( '/project/shop/about/high', { de_DE : 'Kurz', en_US : '' }, { maxlength : 600 } ) ] ) } );
	const t = openedText( response );
	openRow( t, 'project/shop' );
	const kind = key => fieldOf( t, key ).querySelector('textarea') !== null ? 'textarea' : 'input';
	check( 'a text with a line break, a text longer than a line and one with a high limit are fields of several lines - a short one is a line', [ '/project/shop/about/two', '/project/shop/about/long', '/project/shop/about/high' ].every( k => kind( k ) === 'textarea' ) );
	check( '...which is where the textfills are asked for, at the name, for the account that may change it', [ '/project/shop/about/two', '/project/shop/about/long' ].every( k => fieldOf( t, k ).querySelector('.nino-admin-fills-button') !== null ) );
	fieldOf( t, '/project/shop/about/two' ).querySelector('.nino-admin-fills-button').click();
	check( '...and the list they offer is the server\'s, asked when the button is pressed', t.sent.some( c => c.endpoint === 'fills' ) );
	check( 'a plain text of a line keeps its line breaks where it has them: a value with one is never put into a field that would cut it', kind( '/project/shop/about/two' ) === 'textarea' && controlOf( t, '/project/shop/about/two' ).value === 'Zeile eins\nZeile zwei' );
}

{
	// the language: a form shows one and writes that one
	const t = openedText( textResponse() );
	t.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	openRow( t, 'template/page-services' );
	const title = '/template/page-services/intro/title';
	check( 'a form in the native language has no badge of a language', t.form.querySelector('.nino-admin-locale-badge') === null && controlOf( t, title ).value === 'Leistungen' );

	// the language moved under the form without the form being told: what it writes is what it shows
	t.box.Nino.admin.workLocale.current = 'en_US';
	typeInto( controlOf( t, title ), 'Leistungen B' );
	submitText( t );
	check( 'a form writes the language it shows, whatever the workbench says by now', writes( t )[0].payload.items.length === 1 && writes( t )[0].payload.items[0].locale === 'de_DE' && writes( t )[0].payload.items[0].value === 'Leistungen B' );
	t.box.Nino.admin.workLocale.current = 'de_DE';

	// the language moved, as the shell does it after it has asked
	typeInto( controlOf( t, title ), 'In German' );
	t.Nino.admin.workLocale.set( 'en_US' );
	check( 'the form follows a language the workbench moved: it is drawn again in it, the pill in its bar says so, and what was typed is not there', controlOf( t, title ).value === 'Services' && t.form.querySelector('.nino-admin-locale-badge') !== null
		&& t.text._shownLocale === 'en_US' && t.registry.text.isDirty() === false );
	typeInto( controlOf( t, title ), 'Services two' );
	t.sent.length = 0;
	submitText( t );
	check( '...and writes that language, and no other', writes( t ).length === 1 && writes( t )[0].payload.items.length === 1 && writes( t )[0].payload.items[0].locale === 'en_US' && writes( t )[0].payload.items[0].value === 'Services two' );
	t.Nino.admin.workLocale.set( 'de_DE' );
	check( '...the native language needs no pill', t.form.querySelector('.nino-admin-locale-badge') === null && controlOf( t, title ).value === 'Leistungen B' );
}

{
	// a server that turns a key down: said at its field, the key stays changed, the others are saved
	const t = openedText( textResponse() );
	openRow( t, 'template/page-services' );
	const title = '/template/page-services/intro/title', text = '/template/page-services/intro/text';
	typeInto( controlOf( t, title ), 'Eins' );
	typeInto( controlOf( t, text ), 'Zwei' );
	t.handle.refuse[text] = { ok : false, error : 'could not be written' };
	submitText( t );
	check( 'a key the server turns down says so at its field, in a sentence under it, and the field is marked', fieldOf( t, text ).parentNode.querySelectorAll('.nino-admin-field-error').length === 1 && controlOf( t, text ).getAttribute('aria-invalid') === 'true'
		&& fieldOf( t, title ).parentNode.querySelectorAll('.nino-admin-field-error').length === 1 );
	check( '...and the form says how many were not saved, in the line of the bar - no toast of success', t.form.querySelector('#text-form-msg').textContent === W('/_admin/text/error/one') && t.told.length === 0 );
	check( '...the key stays unsaved input, the one that was saved is not', t.registry.text.isDirty() === true && t.text._session.changed().join() === text );
	typeInto( controlOf( t, text ), 'Zwei!' );
	check( '...and typing into the field takes its sentence away', fieldOf( t, text ).parentNode.querySelectorAll('.nino-admin-field-error').length === 0 && controlOf( t, text ).getAttribute('aria-invalid') === null );
	t.handle.refuse = {};
	t.sent.length = 0;
	submitText( t );
	check( 'the next save sends the key that is still changed, and nothing else', writes( t )[0].payload.items.map( i => i.key ).join() === text && t.registry.text.isDirty() === false );

	// a request that fails
	typeInto( controlOf( t, title ), 'Drei' );
	t.handle.fail = { status : 500, body : null };
	submitText( t );
	check( 'a request that fails leaves the form as it is, unsaved, and says so in the line', t.registry.text.isDirty() === true && t.form.querySelector('#text-form-msg').textContent.indexOf( '500' ) !== -1 );
	t.handle.fail = null;

	// what is typed while the request runs is not in what was sent
	t.handle.hold = true;
	typeInto( controlOf( t, title ), 'Vier' );
	submitText( t );
	typeInto( controlOf( t, title ), 'Vier und mehr' );
	let ended = null;
	t.registry.text.save( ok => { ended = ok } );
	check( 'a second save while one runs is told no, and sends nothing', ended === false && writes( t ).length === 3 );
	check( 'the shell sees a save that runs', t.registry.text.saving() === true );
	t.held.shift()();
	check( 'what was typed while the request was on its way stays unsaved input', t.registry.text.isDirty() === true && controlOf( t, title ).value === 'Vier und mehr' && t.registry.text.saving() === false );
	t.handle.hold = false;

	// discard
	t.registry.text.discard();
	check( 'discarding puts the fields back to what they held', controlOf( t, title ).value === 'Vier' && t.registry.text.isDirty() === false );
}

{
	// keys this account may not write are read, in place
	const response = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).map( e => e.key.indexOf( '/project/company/' ) === 0 ? Object.assign( {}, e, { writable : e.key.endsWith( 'email' ) } ) : e ) } );
	const t = openedText( response );
	openRow( t, 'project/company' );
	const view = fieldOf( t, '/project/company/general/name' ).querySelector('.admin-text-readonly');
	check( 'a key the account may not write is shown as text - named like the others - and has no field and no textfills', view !== null && view.textContent === 'Acme' && t.text._session.get( '/project/company/general/name' ).readonly === true
		&& view.getAttribute('aria-labelledby') === view.parentNode.querySelector('span').id && fieldOf( t, '/project/company/general/name' ).querySelector('input, textarea, .nino-admin-fills-button') === null );
	check( '...the keys it may write are fields, and a save sends only those', controlOf( t, '/project/company/contact/email' ) !== null && t.form.querySelectorAll('button').filter( b => b.type === 'submit' ).length === 1 );
	typeInto( controlOf( t, '/project/company/contact/email' ), 'x@acme.test' );
	submitText( t );
	check( '...and a save sends only the keys it may write that were changed', writes( t )[0].payload.items.map( i => i.key ).join() === '/project/company/contact/email' );

	// a row with nothing writable has no Save
	const none = openedText( textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).map( e => Object.assign( {}, e, { writable : false } ) ) } ) );
	openRow( none, 'project/company' );
	check( 'a row this account may not write anywhere is a row to read: no bar, no Save', none.form.querySelectorAll('.nino-admin-actionbar').length === 0 && none.registry.text.isDirty() === false );
}

{
	// A template written since the keys were read is a row the list did not have - the Builder makes the keys of its sections, and so does a key of a template that the
	// Keys tab adds: the panel reads the keys again where it is shown, and not before
	const response = textResponse();
	const t = openedText( response );
	const asked = () => t.sent.filter( c => c.endpoint === 'keys' ).length;
	const fresh = '/template/frame-footer/navigation/label';
	const rowOf = key => t.text._model.rowOf[key];
	const listed = group => opens( t.list ).some( r => keyOf( r ) === group );

	check( 'the panel asks for the keys once when it opens, and keeps the stamp of the templates it asked at', asked() === 1 && t.text._stamp === 0 && rowOf( fresh ) === undefined && listed( 'template/frame-footer' ) === false );
	t.text.showCurrent();
	check( 'shown again with nothing written since, it asks for nothing', asked() === 1 );
	t.Nino.admin.touch( 'texts' );
	t.text.showCurrent();
	check( '...nor does a write of texts make it ask: the texts it writes itself it has in hand', asked() === 1 );

	response.keys = response.keys.concat( [ textEntry( fresh, { de_DE : 'Fußnavigation', en_US : 'Footer navigation' } ) ] );
	t.Nino.admin.touch( 'templates' );
	t.text.showCurrent();
	check( 'a template written since: shown again, it asks again - and the rows have what the template made', asked() === 2 && t.text._stamp === 1 && rowOf( fresh ) === 'template/frame-footer' && listed( 'template/frame-footer' ) );
	t.text.showCurrent();
	check( '...once: what it holds now is the stamp it asked at', asked() === 2 );

	// a row is open: the list is not drawn over it
	opens( t.list ).find( r => keyOf( r ) === 'template/page-services' ).click();
	t.Nino.admin.touch( 'templates' );
	t.text.showCurrent();
	check( 'a row that is open is not drawn over: shown again, the panel asks for nothing and the form stays', asked() === 2 && t.text._currentGroup === 'template/page-services' && t.form.classList.contains('admin-hidden') === false );
	t.text._showList();
	t.text.showCurrent();
	check( '...the showing that finds the list is the one that asks, and the stamp is the one of that time', asked() === 3 && t.text._stamp === 2 );

	// a template written while the answer is on its way is not in it
	let held = null;
	t.text._apiCall = ( endpoint, payload, callback ) => { t.sent.push( { endpoint : endpoint, payload : payload } ); held = () => callback( 200, response ) };
	t.Nino.admin.touch( 'templates' );
	t.text.showCurrent();
	t.Nino.admin.touch( 'templates' );
	held();
	check( 'a template written while the answer is on its way is not in it: the panel keeps the stamp from before the request', asked() === 4 && t.text._stamp === 3 && t.stamps.templates === 4 );
	t.text.showCurrent();
	check( '...so the next showing asks again', asked() === 5 );
}

{
	// a row and a part named like a member of Object.prototype open as any other
	const free = textResponse( { keys : homeKeys.filter( e => e.blacklisted === false ).concat( [ textEntry( '/project/shop/constructor/name', { de_DE : 'Konstrukteur', en_US : 'Constructor' } ), textEntry( '/constructor/x', { de_DE : 'Frei' } ) ] ) } );
	const t = openedText( free );
	const box = t.list.querySelector('#text-search');
	box.value = 'konstrukteur';
	box.fire('input');
	check( 'the search over keys with a part named constructor answers, in the Text panel', t.list.querySelectorAll('li').length === 1 );
	box.value = '';
	box.fire('input');
	opens( t.list ).find( r => keyOf( r ) === 'project/shop' ).click();
	check( '...and the form of its row opens, with the section named by the part', t.form.querySelectorAll('fieldset.admin-text-section').map( f => f.dataset.section ).join() === 'constructor' && controlOf( t, '/project/shop/constructor/name' ).value === 'Konstrukteur' );
}

{
	// deep links: a key, the details of a page
	const t = openedText( textResponse(), { panel : 'text', parts : [ 'template/page-services', '/template/page-services/item-2/title' ] } );
	check( 'a hash that names a key opens the row that holds it, at that key, when the panel loads', t.text._currentGroup === 'template/page-services' && t.text._focusKey === '/template/page-services/item-2/title'
		&& t.form.querySelector('[data-key="/template/page-services/item-2/title"]').classList.contains('is-found') && t.list.classList.contains('admin-hidden') );
	const details = openedText( textResponse(), { panel : 'text', parts : [ 'template/page-services', '/_nino/webpage/services/title' ] } );
	check( '...also the details of a page, which are in the form of the page', details.text._currentGroup === 'template/page-services' && details.text._focusKey === '/_nino/webpage/services/title' );
	// the hash moves to another key of the row that is open: that field is shown, and the address keeps it
	const moving = { panel : 'text', parts : [ 'template/page-services', '/template/page-services/item-2/title' ] };
	const open = openedText( textResponse(), moving );
	moving.parts = [ 'template/page-services', '/template/page-services/intro/text' ];
	open.calls.length = 0;
	open.text.showCurrent();
	check( 'a hash that names another key of the row that is open focuses that key and the address keeps it', open.text._focusKey === '/template/page-services/intro/text'
		&& open.form.querySelector('[data-key="/template/page-services/intro/text"]').classList.contains('is-found') && open.calls.at(-1) === 'set text/template/page-services//template/page-services/intro/text' );
	const other = openedText( textResponse(), { panel : 'images', parts : [ 'x' ] } );
	check( 'the hash of another panel leaves the list', other.text._currentGroup === null && other.list.classList.contains('admin-hidden') === false );
	const unknown = openedText( textResponse(), { panel : 'text', parts : [ 'template/page-nope' ] } );
	check( 'a row there is not is the list', unknown.text._currentGroup === null && unknown.list.classList.contains('admin-hidden') === false );
}

{
	// the focus of a drill-down: into the form that a row or a hit opens, back to the row, and left where it stands when a level is only shown again
	const t = openedText( textResponse() );
	const row = group => opens( t.list ).find( r => keyOf( r ) === group );
	const pressed = row('template/page-services');

	pressed.click();
	check( 'a row that is opened takes the focus into its form', t.form.classList.contains('admin-hidden') === false && focusIn( t, t.form ) );
	t.form.querySelector('.nino-admin-back-link').click();
	check( 'Back shows the list and gives the focus to the row that was open - the one the list has now, drawn again, not the one that was pressed', t.list.classList.contains('admin-hidden') === false
		&& t.dom.doc.activeElement !== pressed && t.dom.doc.activeElement === row('template/page-services') );

	// a hit opens the row at its key: the focus goes in with it
	const box = t.list.querySelector('#text-search');
	box.value = 'beraten';
	box.fire('input');
	opens( t.list )[0].click();
	check( 'a hit takes the focus into the form as well', focusIn( t, t.form ) );

	// the level shown again, by the address or by a step through the history: the focus is the person's
	t.text._showList();
	check( 'the list shown again with the focus in the form that goes gives it to the name of the panel - it would fall to the body', focusIn( t, t.list ) === false && t.dom.doc.activeElement === t.title );
	box.focus();
	t.text._showList();
	check( '...and leaves it where it stands, in the search box, when it stood in the level that stays', t.dom.doc.activeElement === box );
	t.text._openGroup( 'template/page-services' );
	check( 'a form shown by the address takes the focus only from the level that goes: it was in the search box, and is on the name of the panel now', t.form.classList.contains('admin-hidden') === false && t.dom.doc.activeElement === t.title );
}

{
	// more hits than a page: fifty, then "Weitere anzeigen", fifty at a time
	const many = Array.from( { length : 120 }, ( v, i ) => textEntry( '/project/many/item/n'+ String( i ).padStart( 3, '0' ), { de_DE : 'Treffer '+ i, en_US : '' } ) );
	const t = openedText( textResponse( { keys : many, pages : [], order : {} } ) );
	const box = t.list.querySelector('#text-search');
	box.value = 'treffer';
	box.fire('input');
	const more = () => t.list.querySelectorAll('button').find( b => b.textContent === W('/_admin/text/label/more') );
	check( 'a search with many hits draws fifty and offers more', t.list.querySelectorAll('li').length === 50 && more() !== undefined && t.list.querySelector('#text-list-status').textContent === F('/_admin/text/msg/results',120) );
	more().click();
	check( '...fifty more', t.list.querySelectorAll('li').length === 100 && more() !== undefined );
	more().click();
	check( '...until there are no more, and no button', t.list.querySelectorAll('li').length === 120 && more() === undefined );
	box.value = 'treffer 119';
	box.fire('input');
	check( 'typing again begins at the first fifty', t.list.querySelectorAll('li').length === 1 && t.list.querySelector('#text-list-status').textContent === F('/_admin/text/msg/result',1) );
	t.Nino.admin.workLocale.set( 'en_US' );
	t.list.querySelector('#text-list-empty').click();
	box.value = '';
	box.fire('input');
	check( 'the filter alone is a list of hits as well: 50 of the 120 keys that are empty in English', t.list.querySelectorAll('li').length === 50 && t.list.querySelector('#text-list-status').textContent === F('/_admin/text/msg/results',120) );
}

{
	// without the Routes module there are no pages, and the list has no block of them
	const t = openedText( textResponse( { pages : null } ) );
	check( 'without pages the list has the one block, and the details of a page are among the system\'s rows', t.list.querySelectorAll('h2').map( h => h.textContent ).join() === group('general')
		&& opens( t.list ).some( r => keyOf( r ) === '_nino/webpage' ) );
}

{
	// what the form calls a change: a whitespace is one, a text that is only the fallback of a language is none, a hit is text
	const t = openedText( textResponse() );
	t.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	openRow( t, 'template/page-services' );
	const title = '/template/page-services/intro/title', intro = '/template/page-services/intro/text';
	typeInto( controlOf( t, title ), 'Leistungen ' );
	check( 'a text that differs from the stored one by a whitespace only is a change: the form is unsaved and the save sends it', t.registry.text.isDirty() === true );
	submitText( t );
	check( '...as typed, with the whitespace', writes( t ).length === 1 && writes( t )[0].payload.items.length === 1 && writes( t )[0].payload.items[0].value === 'Leistungen ' );
	t.Nino.admin.workLocale.set( 'en_US' );
	check( 'a language that has no text of its own is drawn with an empty field: the text of another language that stands in for it is never put into the field', controlOf( t, intro ).value === '' && t.registry.text.isDirty() === false );
	t.sent.length = 0;
	submitText( t );
	check( '...and saved as it is, it sends nothing for that key', writes( t ).every( c => c.payload.items.every( i => i.key !== intro ) ) );
	// the fills are asked for in the language of the form
	fieldOf( t, '/template/page-services/item-1/text' ).querySelector('.nino-admin-fills-button').click();
	check( 'the textfills are asked for in the language the form is drawn in', t.sent.filter( c => c.endpoint === 'fills' ).at(-1).payload.locale === 'en_US' );
}

{
	// a hit arrives as text, whatever the text holds
	const bad = 'Treffer &lt;img src=x&gt; und &lt;b&gt;fett&lt;/b&gt;';
	const t = openedText( textResponse( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ).concat( [ textEntry( '/project/shop/xss/name', { de_DE : bad, en_US : bad } ) ] ) } ) );
	const box = t.list.querySelector('#text-search');
	box.value = 'treffer';
	box.fire('input');
	check( 'a hit that holds markup shows it as words: the text is in the row and no element is made from it', t.list.querySelectorAll('li').length === 1 && t.list.textContent.indexOf( '<b>fett</b>' ) !== -1 && t.list.querySelectorAll('img, b').length === 0 );
}

{
	// a key that cannot be written is read as text, markup included
	const readonly = textEntry( '/project/company/note/html', { de_DE : '<p>Hallo <b>Welt</b></p>', en_US : '' }, { html : true, format : 'blocks', writable : false } );
	const t = openedText( textResponse( { keys : copies( homeKeys.filter( e => e.blacklisted === false ) ).concat( [ readonly ] ) } ) );
	openRow( t, 'project/company' );
	const view = fieldOf( t, '/project/company/note/html' ).querySelector('.admin-text-readonly');
	check( 'a text with markup that the account may not write is read as text: the words, and no element made from the markup', view !== null && view.textContent.indexOf( 'Hallo' ) !== -1 && view.textContent.indexOf( '<' ) === -1 && view.querySelectorAll('b, p').length === 0 );
}

// --- the Keys tab: the list, the search, the form of a row, the form that makes and renames a key ---

const keysResponse = ( extra ) => Object.assign( { keys : homeKeys.map( e => Object.assign( {}, e, { values : Object.assign( {}, e.values ), formatSet : false, maxlengthSet : false } ) ),
	locales : [ { locale : 'de_DE', active : true }, { locale : 'en_US', active : true } ], selectedLocale : 'de_DE', order : homeOrder, pages : homePages, templates : homeData().templates, features : homeData().features,
	categories : { template : [ 'common', 'frame-header', 'page-services' ], feature : [ 'consent' ], module : [ 'form', 'localepicker' ], project : [ 'company', 'website', 'mail', 'catalog' ] } }, extra ?? {} );

/**
 *	A page of the workbench with the Keys tab open on a server that holds the keys and changes them the way the real one does: a shape that is
 *	saved is what the next list says, a text that is written is the text of the key. What it refuses is told to refuse
 */
function openedKeys( response, hash, language ) {
	const w = world( 'de_DE', hash, language ?? 'de_DE' );
	const { title, levels : [ list, form ] } = w.pane( 'keys', 'keys-list', 'keys-form' );
	title.textContent = 'Texte';
	form.classList.add('admin-hidden');
	w.Nino.adminUi.panelName = () => 'Texte';
	w.Nino.adminUi.fillsDialog = () => true;
	const sent = [], told = [], asked = [];
	const handle = { answer : true, refuse : {}, down : false, hold : false, held : [], listed : 0, scan : { missing : [], alsoUsed : [] }, stored : {} };
	w.Nino.adminUi.say = sentence => told.push( sentence );
	w.Nino.adminUi.confirm = ( level, options ) => { asked.push( Object.assign( { level : level }, options ) ); options.onChoose( handle.answer ); return true };
	const keys = w.Nino.admin.keys;
	const entry = key => response.keys.find( e => e.key === key );

	keys._apiCall = ( endpoint, payload, callback ) => {
		sent.push( { endpoint : endpoint, payload : payload } );
		if( handle.hold === true && endpoint === 'save' ) {
			handle.held.push( () => { handle.hold = false; keys._apiCall( endpoint, payload, callback ); sent.pop() } );
			return;
		}
		const refused = [].concat( handle.refuse[endpoint] ?? [] ).find( r => r.key === undefined || r.key === payload.key );
		if( refused !== undefined )
			return callback( refused.status, refused.body );
		if( endpoint === 'list' ) {
			handle.listed++;
			return handle.down === true && handle.listed > 1 ? callback( 500, null ) : callback( 200, response );
		}
		if( endpoint === 'fills' )
			return callback( 200, { fills : [] } );
		if( endpoint === 'scan' )
			return callback( 200, handle.scan );
		if( endpoint === 'save' ) {
			const e = entry( payload.key );
			// A limit under the longest text the key holds is turned down, as the real server does
			const longest = Math.max( ...Object.values( e.values ).map( v => String( v ?? '' ).length ) );
			if( payload.maxlength !== null && payload.maxlength < longest )
				return callback( 400, { error : 'limit below the current text', code : 'keys_limit_short', params : [ longest ], field : 'maxlength' } );
			if( e.global !== payload.global )
				e.values = payload.global === true ? { '*' : Object.values( e.values ).find( v => v !== '' && v !== null ) ?? '' } : Object.fromEntries( [ 'de_DE', 'en_US' ].map( code => [ code, Object.values( e.values )[0] ] ) );
			e.global = payload.global;
			e.blacklisted = payload.blacklisted;
			e.formatSet = payload.format !== 'auto';
			e.format = payload.format === 'auto' ? 'plain' : payload.format;
			e.html = e.format !== 'plain';
			e.maxlengthSet = payload.maxlength !== null;
			e.maxlength = payload.maxlength ?? 150;
			return callback( 200, {} );
		}
		if( endpoint === 'savebatch' ) {
			const results = {};
			payload.items.forEach( item => {
				// As the server does: a text that was read as something other than what is stored now is not written, unless it is forced
				const changed = item.was !== undefined && item.force !== true && handle.stored[item.locale+ ' '+ item.key] !== undefined && handle.stored[item.locale+ ' '+ item.key] !== item.was;
				const refused = changed === true ? { ok : false, error : 'changed' } : handle.refuse['item:'+ item.key];
				if( refused === undefined ) {
					entry( item.key ).values[item.locale] = item.value;
					handle.stored[item.locale+ ' '+ item.key] = item.value;
				}
				results[item.key] = refused ?? { ok : true, value : item.value };
			} );
			return callback( 200, { results : results } );
		}
		if( endpoint === 'delete' ) {
			response.keys = response.keys.filter( e => e.key !== payload.key );
			return callback( 200, {} );
		}
		if( endpoint === 'create' ) {
			response.keys.push( textEntry( payload.key, payload.global === true ? { '*' : payload.value } : { de_DE : payload.value, en_US : '' } ) );
			return callback( 200, { ok : true, key : payload.key } );
		}
		if( endpoint === 'rename' ) {
			entry( payload.key ).key = payload.newKey;
			return callback( 200, { ok : true, key : payload.newKey } );
		}
		callback( 200, { created : 1, ignored : 1, skipped : 1 } );
	};
	keys.init();
	return Object.assign( w, { list, form, title, sent, keys, told, asked, handle, response, entry } );
}

const keyWrites = k => k.sent.filter( c => c.endpoint !== 'list' && c.endpoint !== 'fills' );
const settingsOf = ( k, key ) => k.form.querySelector('[data-settings="'+ key+ '"]');
const part = ( row, selector ) => row.querySelector( selector );
const rowButtons = row => row.querySelectorAll('.nino-admin-row-actions button');
const keyControl = ( k, key ) => k.form.querySelector('[data-key="'+ key+ '"]').querySelector('input, textarea');
const submitKeys = k => k.form.querySelector('form').fire('submit');
const choose = ( select, value ) => { select.value = value; select.fire('change') };
const tick = box => { box.checked = box.checked === false; box.fire('change') };

{
	const k = openedKeys( keysResponse() );
	const text = openedText( textResponse() );

	check( 'the Keys tab has the groups of the Text panel as the list of the workbench - with the hidden keys and the words of the workbench, which it exists to show - each a link with an address of its own', k.list.querySelectorAll('ul.nino-admin-list').length > 0
		&& opens( k.list ).some( a => keyOf( a ) === '_admin' ) && opens( k.list ).some( a => keyOf( a ) === 'project/mail' ) && opens( k.list ).every( a => a.tagName === 'a' && a.href === '#text/keys/'+ encodeURIComponent( keyOf( a ) ) )
		&& k.list.querySelectorAll('li').every( li => li.classList.contains('nino-admin-list-row') ) );
	const nameOf = ( t, group ) => opens( t.list ).find( r => keyOf( r ) === group ).querySelector('strong').textContent;
	check( '...and a group has the one name in both tabs: the pages by their routes, the others by the words of the vocabulary - "Leistungen", not "Page services"',
		[ 'template/page-services', 'project/company', 'feature/consent', 'template/frame-header' ].every( g => nameOf( k, g ) === nameOf( text, g ) ) && nameOf( k, 'template/page-services' ) === 'Leistungen' && nameOf( k, 'template/frame-header' ) === W('/_admin/common/word/frame-header') );
	check( '...and the same height: the same row of the block, two lines', [ 'template/page-services', 'project/company' ].every( g => opens( k.list ).find( r => keyOf( r ) === g ).parentNode.parentNode.className === opens( text.list ).find( r => keyOf( r ) === g ).parentNode.parentNode.className ) );
	check( 'the bar of the list holds the scan and the new key, with the main button for the new key and nothing else', ( () => { const bar = k.list.querySelector('.nino-admin-list-actions'); return bar !== null && bar.querySelectorAll('button').length === 2
		&& bar.querySelectorAll('.nino-admin-btn-primary').length === 1 && bar.querySelector('.nino-admin-btn-primary').textContent === W('/_admin/keys/label/new') && bar.querySelector('.nino-admin-btn-secondary').textContent === W('/_admin/keys/label/scan') } )() );
	check( 'there is no switch of a language in the tab', k.list.querySelectorAll('select').length === 0 );

	// the search with the hidden keys, and the chip for them alone
	const box = k.list.querySelector('#keys-search');
	check( 'the search is the Text panel\'s, with a chip of the workbench for the hidden keys', box.getAttribute('aria-label') === W('/_admin/text/label/search') && k.list.querySelector('#keys-list-hidden').classList.contains('nino-admin-chip') && k.list.querySelector('#keys-list-hidden').textContent === W('/_admin/keys/label/hidden-only') );
	box.value = 'netzwerk';
	box.fire('input');
	check( 'it finds a hidden key as well, as a row of the list', k.list.querySelectorAll('li').length === 1 && k.list.querySelector('.admin-text-hit-key').textContent === '/_admin/elements/field/social/title' && opens( k.list )[0].href === '#text/keys/_admin/%2F_admin%2Felements%2Ffield%2Fsocial%2Ftitle' );
	box.value = '';
	box.fire('input');
	k.list.querySelector('#keys-list-hidden').click();
	check( 'only the hidden ones: the chip is on, and the hits are the keys hidden from the Text panel', k.list.querySelector('#keys-list-hidden').getAttribute('aria-pressed') === 'true'
		&& k.list.querySelectorAll('.admin-text-hit-key').map( c => c.textContent ).sort().join() === '/_admin/elements/field/social/title,/_nino/webpage/services/uri,/project/mail/color/backdrop' );
	k.list.querySelector('#keys-list-hidden').click();
	box.value = 'consulting';
	box.fire('input');
	check( 'a hit in another language has that language\'s code as a small button, and it asks the workbench for it', ( () => { const code = k.list.querySelector('.nino-admin-row-actions button'); const asked = [];
		k.Nino.admin.workLocale.choose = c => asked.push( c ); code.click(); k.Nino.admin.workLocale.choose = c => k.Nino.admin.workLocale.set( c ); return code.textContent === 'en_US' && asked.join() === 'en_US' } )() );
	box.value = 'beratung';
	box.fire('input');
	k.calls.length = 0;
	opens( k.list )[0].click();
	check( 'a hit opens the row at the key: the address names it, the field is marked', k.keys._currentGroup === 'template/page-services' && k.calls.includes('go keys/template/page-services//template/page-services/item-1/title') && k.calls.includes('set keys/template/page-services//template/page-services/item-1/title')
		&& k.form.querySelector('[data-key="/template/page-services/item-1/title"]').classList.contains('is-found') && k.keys._view === 'group' );
}

{
	// the form of a row: a text and what it is, for every key, and one Save
	const k = openedKeys( keysResponse() );
	opens( k.list ).find( a => keyOf( a ) === 'template/page-services' ).click();
	const KEY = '/template/page-services/intro/title';

	check( 'the form has the way back in the context bar, the name of the row, and no select of a language', k.form.querySelectorAll('.nino-admin-back-link').length === 1 && k.form.querySelector('.nino-admin-back-link').textContent === F('/_admin/common/label/back-to',W('/_admin/text/label/back-name'))
		&& k.form.querySelector('.nino-admin-contextbar-title').textContent === 'Leistungen' && k.form.querySelectorAll('select').filter( s => s.dataset.settings === undefined && s.closest('.admin-key-settings') === null ).length === 0 );
	check( 'a key is a field named like the Text panel\'s, with the key and the count under it, and under it the row of what the key is', ( () => { const f = k.form.querySelector('[data-key="'+ KEY+ '"]'); return f.querySelector('.admin-text-meta code').textContent === KEY
		&& f.querySelector('input').value === 'Leistungen' && settingsOf( k, KEY ) !== null && f.parentNode === settingsOf( k, KEY ).parentNode && f.parentNode.children.indexOf( settingsOf( k, KEY ) ) === f.parentNode.children.indexOf( f ) + 1 } )() );
	const row = settingsOf( k, KEY );
	check( 'the row of a key is a format, a limit, whether it is global and whether it is hidden - fields of the workbench, each with its own name - and the buttons of the key at its end', ( () => {
		const select = part( row, 'select' ), limit = part( row, 'input[type="number"]' ), boxes = row.querySelectorAll('input').filter( i => i.type === 'checkbox' );
		return select.children.map( o => o.value ).join() === 'auto,plain,inline,lines,blocks' && select.value === 'auto' && limit.value === '' && limit.min === '1' && boxes.length === 2 && boxes.every( b => b.checked === false )
			&& row.querySelectorAll('.nino-admin-switch').length === 2 && row.children[0].tagName === 'span' && row.querySelector('.nino-admin-help') !== null } )() );
	check( '...Rename and Delete are the small buttons at the end of the row of the key, the one that takes something away last and red', ( () => { const buttons = rowButtons( row );
		return buttons.map( b => b.textContent ).join() === [ 'rename', 'delete' ].map( w => W('/_admin/common/label/'+ w) ).join() && buttons.every( b => b.classList.contains('nino-admin-btn--small') ) && buttons[1].classList.contains('nino-admin-btn-danger') && buttons.every( b => b.getAttribute('aria-label').indexOf( KEY ) !== -1 ) } )() );
	check( '...a key of the system has no button to rename it - its name is what the code that reads it asks for - and one somebody made up has', ( () => {
		k.keys._openGroup( '_nino/webpage' ); const a = rowButtons( k.form.querySelector('.admin-key-settings') ).map( b => b.textContent ).join();
		k.keys._openGroup( 'website' ); const b = rowButtons( k.form.querySelector('.admin-key-settings') ).map( b => b.textContent ).join();
		k.keys._openGroup( 'template/page-services' ); return a === W('/_admin/common/label/delete') && b === [ 'rename', 'delete' ].map( w => W('/_admin/common/label/'+ w) ).join() } )() );
	check( 'the view has one bar with one main button that submits, and no button of Apply, in the rows or anywhere', k.form.querySelectorAll('.nino-admin-actionbar').length === 1 && k.form.querySelectorAll('.nino-admin-btn-primary').length === 1
		&& k.form.querySelector('.nino-admin-btn-primary').type === 'submit' && k.form.querySelectorAll('button').filter( b => b.type === 'submit' ).length === 1 );
	check( 'a key the form holds that is the same in every language has the badge, and the row says that it is global', ( () => { k.keys._openGroup( 'project/company' ); const f = k.form.querySelector('[data-key="/project/company/general/name"]');
		const g = settingsOf( k, '/project/company/general/name' ).querySelectorAll('input').filter( i => i.type === 'checkbox' )[0]; k.keys._openGroup( 'template/page-services' ); return f.querySelector('.nino-admin-badge') !== null && g.checked === true } )() );
	check( 'the fill icon is at a field of several lines and a text with markup, and not at a line', k.form.querySelectorAll('.admin-text-field').every( f => ( f.querySelector('input') !== null ) === ( f.querySelector('.nino-admin-fills-button') === null ) ) );

	// nothing changed: nothing is sent
	submitKeys( k );
	check( 'a save with nothing changed sends nothing and says it is saved', keyWrites( k ).length === 0 && k.told.join() === W('/_admin/common/msg/saved') );

	// a text
	typeInto( keyControl( k, KEY ), 'Unsere Leistungen' );
	check( 'a changed text is unsaved input', k.registry.keys.isDirty() === true );
	submitKeys( k );
	check( 'a save of a text sends the text and nothing of its shape, then reads the keys again and draws the row from what the server holds', keyWrites( k ).map( c => c.endpoint ).join() === 'savebatch'
		&& keyWrites( k )[0].payload.items.length === 1 && keyWrites( k )[0].payload.items[0].key === KEY && keyWrites( k )[0].payload.items[0].locale === 'de_DE' && k.sent.at(-1).endpoint === 'list' );
	check( '...says it as a toast, the line empty, the view and the address as they were - and the form holds no unsaved input', k.told.at(-1) === W('/_admin/common/msg/saved') && k.form.querySelector('#keys-form-msg').textContent === '' && k.keys._currentGroup === 'template/page-services' && k.registry.keys.isDirty() === false
		&& keyControl( k, KEY ).value === 'Unsere Leistungen' );
	check( '...and tells the shell that texts were written, once for each request that wrote', k.touched.join() === 'texts' );
}

{
	// a key that another tab wrote since is not written over unseen, and the question is the kernel's
	const k = openedKeys( keysResponse() );
	opens( k.list ).find( a => keyOf( a ) === 'template/page-services' ).click();
	const KEY = '/template/page-services/intro/title';
	const read = k.entry( KEY ).values.de_DE;
	const questions = [];
	k.Nino.adminUi.conflict = options => { questions.push( options ); return true };
	k.handle.stored['de_DE '+ KEY] = 'Fremder Text';
	typeInto( keyControl( k, KEY ), 'Mein Text' );
	submitKeys( k );
	check( 'the text goes with the text it was read as, and a key that was written by another meanwhile asks the question of a refused save', keyWrites( k )[0].payload.items[0].was === read && keyWrites( k )[0].payload.items[0].force === undefined
		&& questions.length === 1 && questions[0].message.indexOf( KEY ) !== -1 );
	check( '...the text stays as it was typed, at its field, and unsaved', keyControl( k, KEY ).value === 'Mein Text' && k.registry.keys.isDirty() === true && k.told.length === 0 );
	// Cancel leaves the typed text at its field, with the focus on it; the keys were read again and show the text of the other tab, which this tab was never
	// shown as the text it holds: a second save is held to the text this tab read, and asks again
	questions[0].onCancel();
	check( 'Cancel puts the focus on the field of the text that was turned down', k.dom.doc.activeElement === keyControl( k, KEY ) );
	k.entry( KEY ).values.de_DE = 'Fremder Text';
	submitKeys( k );
	const second = keyWrites( k ).filter( c => c.endpoint === 'savebatch' ).at(-1).payload.items[0];
	check( 'a second save after Cancel is sent with the text of the first read, and asks the question again: the text of the other tab is not written over because the keys were read again', second.was === read && questions.length === 2 && k.handle.stored['de_DE '+ KEY] === 'Fremder Text' );
	check( '...and the question names the text by its place and by its key', questions[1].message.indexOf( KEY ) !== -1 && questions[1].message.indexOf(' › ') !== -1 );
	questions[1].onOverwrite();
	const forced = keyWrites( k ).filter( c => c.endpoint === 'savebatch' ).at(-1).payload.items;
	check( 'Overwrite writes it once more for that key alone, forced and without the text it was read as - and the row is read again, saved', forced.length === 1 && forced[0].force === true && forced[0].was === undefined && k.handle.stored['de_DE '+ KEY] === 'Mein Text'
		&& k.registry.keys.isDirty() === false && k.told.at(-1) === W('/_admin/common/msg/saved') );
}

{
	// a key whose shape changes in the save has no stamp of its text: the text moves with the shape
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title';
	choose( part( settingsOf( k, T ), 'select' ), 'lines' );
	typeInto( keyControl( k, T ), 'Geändert' );
	submitKeys( k );
	check( 'a text written in the save that changes the shape of its key is written without a stamp', keyWrites( k ).filter( c => c.endpoint === 'savebatch' ).every( c => c.payload.items.every( i => i.was === undefined ) ) );
}

{
	// the shape of a key is saved with its text, by the one Save
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title', X = '/template/page-services/intro/text';
	const row = key => settingsOf( k, key );

	choose( part( row( T ), 'select' ), 'lines' );
	check( 'a format that is chosen is unsaved input for the shell', k.registry.keys.isDirty() === true );
	choose( part( row( T ), 'select' ), 'auto' );
	check( '...and chosen back it is not', k.registry.keys.isDirty() === false );
	choose( part( row( T ), 'select' ), 'lines' );
	typeInto( part( row( T ), 'input[type="number"]' ), '90' );
	tick( row( X ).querySelectorAll('input').filter( i => i.type === 'checkbox' )[1] );
	typeInto( keyControl( k, X ), 'Das bieten wir an: mehr' );
	submitKeys( k );
	const writes = keyWrites( k );
	check( 'a save writes the shape of each key that changed, one request each, and then the texts that changed in one - and the formats a text was widened to ask nothing', writes.map( c => c.endpoint ).join() === 'save,save,savebatch' && k.asked.length === 0 );
	check( '...a shape with the format, the limit as a number and both ticks', JSON.stringify( writes[0].payload ) === JSON.stringify( { key : T, global : false, blacklisted : false, format : 'lines', maxlength : 90 } )
		&& JSON.stringify( writes[1].payload ) === JSON.stringify( { key : X, global : false, blacklisted : true, format : 'auto', maxlength : null } ) );
	check( '...and the text in the language the form shows', writes[2].payload.items.length === 1 && writes[2].payload.items[0].key === X && writes[2].payload.items[0].locale === 'de_DE' );
	check( 'the row is drawn again from what the server holds: the format, the limit and the ticks are the stored ones, and nothing is unsaved', part( row( T ), 'select' ).value === 'lines' && part( row( T ), 'input[type="number"]' ).value === '90'
		&& row( X ).querySelectorAll('input').filter( i => i.type === 'checkbox' )[1].checked === true && k.registry.keys.isDirty() === false && k.told.at(-1) === W('/_admin/common/msg/saved') );

	// while the requests run the form is held, and drawn again from the server when they are done
	choose( part( row( T ), 'select' ), 'inline' );
	typeInto( keyControl( k, X ), 'Während es läuft' );
	k.handle.hold = true;
	submitKeys( k );
	let second = null;
	k.registry.keys.save( ok => { second = ok } );
	check( 'while the requests of a save run the form is busy: its controls are held, the shell sees a save that runs, and a second save is told no and sends nothing', k.form.querySelector('form').getAttribute('aria-busy') === 'true'
		&& k.form.querySelectorAll('input, textarea, select, button').every( c => c.disabled === true ) && k.registry.keys.saving() === true && second === false && keyWrites( k ).filter( c => c.endpoint === 'save' ).length === 3 );
	k.handle.held.shift()();
	check( '...and when they are done the form is free again, drawn from what the server holds', k.registry.keys.saving() === false && k.form.querySelector('form').getAttribute('aria-busy') !== 'true' && k.form.querySelectorAll('button').some( c => c.disabled === true ) === false
		&& part( row( T ), 'select' ).value === 'inline' && k.registry.keys.isDirty() === false );

	// a key made global: its text is written to the one text
	tick( row( X ).querySelectorAll('input').filter( i => i.type === 'checkbox' )[0] );
	typeInto( keyControl( k, X ), 'Global jetzt' );
	submitKeys( k );
	const batch = keyWrites( k ).at(-1);
	check( 'a key that is made global has its text written to the one text of every language, and not to the language on screen', batch.endpoint === 'savebatch' && batch.payload.items[0].locale === '*' && k.keys._groups['template/page-services'].find( e => e.key === X ).global === true );
}

{
	// a format that holds less asks once, for all the keys it changes
	const response = keysResponse();
	[ '/template/page-services/item-1/text', '/template/page-services/intro/title' ].forEach( key => { const e = response.keys.find( x => x.key === key ); e.format = 'blocks'; e.formatSet = true; e.html = true } );
	const k = openedKeys( response );
	k.keys._openGroup( 'template/page-services', null, true );
	const A = '/template/page-services/item-1/text', B = '/template/page-services/intro/title', C = '/template/page-services/item-2/title';
	choose( part( settingsOf( k, A ), 'select' ), 'plain' );
	choose( part( settingsOf( k, B ), 'select' ), 'inline' );
	choose( part( settingsOf( k, C ), 'select' ), 'blocks' );
	k.handle.answer = false;
	submitKeys( k );
	check( 'two formats that hold less are asked about once, with the heaviest weight - what is stored is converted - and the keys they change as the details of the question', k.asked.length === 1 && k.asked[0].level === 'destructive'
		&& k.asked[0].details.length === 2 && k.asked[0].details.some( d => d.indexOf( A ) === 0 ) && k.asked[0].details.some( d => d.indexOf( B ) === 0 ) );
	check( '...a format that holds as much as the one before is not among them, and a question that is answered no sends nothing', k.asked[0].details.every( d => d.indexOf( C ) === -1 ) && keyWrites( k ).length === 0 && k.registry.keys.isDirty() === true );
	k.handle.answer = true;
	submitKeys( k );
	check( 'answered yes it writes the shape of all three, in one go, and asks nothing more', k.asked.length === 2 && keyWrites( k ).filter( c => c.endpoint === 'save' ).length === 3 );
	choose( part( settingsOf( k, B ), 'select' ), 'blocks' );
	submitKeys( k );
	check( 'a format that holds more asks nothing', k.asked.length === 2 && keyWrites( k ).filter( c => c.endpoint === 'save' ).length === 4 );
}

{
	// a server that turns a part down: said at its field, nothing counts as saved that is not
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title', X = '/template/page-services/intro/text', Y = '/template/page-services/item-2/title';
	const row = key => settingsOf( k, key );
	const limitOf = key => part( row( key ), 'input[type="number"]' );
	typeInto( limitOf( T ), '5' );
	typeInto( keyControl( k, T ), 'Ein neuer Titel' );
	typeInto( keyControl( k, X ), 'Ein neuer Text' );
	typeInto( keyControl( k, Y ), 'Ein neuer Titel B' );
	choose( part( row( Y ), 'select' ), 'lines' );
	k.handle.refuse.save = [
		{ key : T, status : 400, body : { error : 'limit', code : 'keys_limit_short', params : [ 14 ], field : 'maxlength' } },
		{ key : Y, status : 400, body : { error : 'unknown format', code : 'keys_format', params : [], field : 'format' } },
	];
	submitKeys( k );
	const writes = keyWrites( k );
	check( 'a shape the server turns down is said under its field - the limit under the limit, the format under the format - in the words of the server', row( T ).querySelectorAll('.nino-admin-field-error').length === 1 && limitOf( T ).getAttribute('aria-invalid') === 'true'
		&& row( Y ).querySelectorAll('.nino-admin-field-error').length === 1 && part( row( Y ), 'select' ).getAttribute('aria-invalid') === 'true' && row( T ).querySelector('.nino-admin-field-error').textContent === F('/_admin/error/keys_limit_short',14) );
	check( 'a text made shorter in the save that lowers its limit is written before the shape, so that the server does not refuse a limit under the text; the text of a key whose shape was turned down for another reason is not sent', writes.map( c => c.endpoint ).join() === 'savebatch,save,save,savebatch'
		&& writes[0].payload.items.map( i => i.key ).join() === T && writes[3].payload.items.map( i => i.key ).join() === X );
	check( '...what the server took is saved and what it did not is still unsaved input, drawn as it was typed', k.registry.keys.isDirty() === true && limitOf( T ).value === '5' && part( row( Y ), 'select' ).value === 'lines' && keyControl( k, Y ).value === 'Ein neuer Titel B'
		&& k.keys._session.changed().join() === Y && k.keys._shapeChanged().sort().join() === [ T, Y ].sort().join() );
	check( '...the line of the bar says how many keys were not saved - no toast of success - and the focus is on the first field that is wrong', k.form.querySelector('#keys-form-msg').textContent === F('/_admin/keys/error/some',2) && k.told.length === 0 && k.dom.doc.activeElement === limitOf( T ) );
	k.handle.refuse = {};
	typeInto( limitOf( T ), '40' );
	k.sent.length = 0;
	submitKeys( k );
	check( 'the next save sends what is still unsaved, the shapes of both keys and the text of the one', keyWrites( k ).map( c => c.endpoint ).join() === 'save,save,savebatch' && keyWrites( k ).at(-1).payload.items.map( i => i.key ).join() === Y
		&& k.registry.keys.isDirty() === false && k.told.at(-1) === W('/_admin/common/msg/saved') );
	check( '...and after a save that went well the focus is on the main button', k.dom.doc.activeElement === k.form.querySelector('.nino-admin-btn-primary') );

	// a text the server turns down
	typeInto( keyControl( k, X ), 'Noch ein Text' );
	k.handle.refuse['item:'+ X] = { ok : false, error : 'could not be written' };
	submitKeys( k );
	check( 'a text the server turns down is said under its field, stays unsaved input and takes the focus', fieldOf( k, X ).parentNode.querySelectorAll('.nino-admin-field-error').length === 1 && k.registry.keys.isDirty() === true && keyControl( k, X ).value === 'Noch ein Text' && k.dom.doc.activeElement === keyControl( k, X ) );
	delete k.handle.refuse['item:'+ X];

	// a limit that is no number is said before anything is sent
	k.sent.length = 0;
	typeInto( limitOf( T ), '0' );
	submitKeys( k );
	check( 'a limit that is not a whole number from 1 is said at its field before anything is sent, with the focus on it', keyWrites( k ).length === 0 && row( T ).querySelectorAll('.nino-admin-field-error').length === 1 && k.dom.doc.activeElement === limitOf( T )
		&& k.form.querySelector('#keys-form-msg').textContent === W('/_admin/keys/error/fields') );
	typeInto( limitOf( T ), '40' );
}

{
	// one key that was not saved is said in the singular, several in the plural
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title';
	typeInto( keyControl( k, T ), 'Neu' );
	k.handle.refuse['item:'+ T] = { ok : false, error : 'could not be written' };
	submitKeys( k );
	check( 'one key that was not saved is said in the singular, which is not the sentence of several', k.form.querySelector('#keys-form-msg').textContent === F('/_admin/keys/error/one',1) && F('/_admin/keys/error/one',1) !== F('/_admin/keys/error/some',1) );
}

{
	// the limit and the text in one save: each action is valid by itself, in either direction
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const X = '/template/page-services/intro/text', T = '/template/page-services/intro/title';
	typeInto( part( settingsOf( k, X ), 'input[type="number"]' ), '40' );
	typeInto( keyControl( k, T ), 'Kürzer' );
	typeInto( part( settingsOf( k, T ), 'input[type="number"]' ), '10' );
	typeInto( keyControl( k, X ), 'Ein Text' );
	k.entry( X ).values.de_DE = 'Ein Text von mehr als vierzig Zeichen Länge, der gekürzt wird.';
	k.keys._openGroup( 'template/page-services', null, true );
	typeInto( part( settingsOf( k, X ), 'input[type="number"]' ), '20' );
	typeInto( keyControl( k, X ), 'Gekürzt' );
	k.sent.length = 0;
	submitKeys( k );
	check( 'a limit lowered under the text a key holds and the text made shorter in the same save: the text is written first and the limit is taken', keyWrites( k ).map( c => c.endpoint ).join() === 'savebatch,save' && k.registry.keys.isDirty() === false
		&& k.form.querySelectorAll('.nino-admin-field-error').length === 0 );

	// the limit raised and the text made longer: the field takes the longer text as the limit is typed, and the shape is written first
	const control = keyControl( k, X );
	const before = control.maxLength;
	typeInto( part( settingsOf( k, X ), 'input[type="number"]' ), '400' );
	check( 'a limit that is typed is the limit the field counts to and takes, before the save', control.maxLength === 400 && before === 20 && fieldOf( k, X ).querySelector('.nino-admin-char-counter').textContent.split(' / ')[1] === '400' );
	typeInto( control, 'x'.repeat( 300 ) );
	k.sent.length = 0;
	submitKeys( k );
	check( '...a limit raised and the text made longer in the same save: the shape is written first, then the text', keyWrites( k ).map( c => c.endpoint ).join() === 'save,savebatch' && k.registry.keys.isDirty() === false );
}

{
	// a key that becomes global drops the texts of the other languages: asked once with the formats, naming each key and the languages
	const response = keysResponse();
	const A = '/template/page-services/item-1/title', C = '/template/page-services/intro/title', D = '/template/page-services/item-1/text';
	response.keys.find( e => e.key === C ).values = { de_DE : 'Gleich', en_US : 'Gleich' };
	{ const e = response.keys.find( x => x.key === D ); e.format = 'blocks'; e.formatSet = true; e.html = true }
	const k = openedKeys( response );
	k.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	k.keys._openGroup( 'template/page-services', null, true );
	const globalBox = key => settingsOf( k, key ).querySelectorAll('input').filter( i => i.type === 'checkbox' )[0];
	k.handle.answer = false;
	tick( globalBox( A ) );
	tick( globalBox( C ) );
	tick( globalBox( '/template/page-services/intro/text' ) );
	submitKeys( k );
	check( 'a key that becomes global asks, with the heaviest weight, naming the key and the languages that lose their text and the one whose text stays - and a key whose other texts are the same does not', k.asked.length === 1 && k.asked[0].level === 'destructive'
		&& k.asked[0].details.length === 1 && k.asked[0].details[0] === F('/_admin/keys/confirm/global-line',A,'English','Deutsch') ); // the key whose other language has no text is not among them
	check( '...answered no it changes nothing: nothing is sent and the form is as it was typed', keyWrites( k ).length === 0 && k.registry.keys.isDirty() === true && globalBox( A ).checked === true );
	choose( part( settingsOf( k, D ), 'select' ), 'plain' );
	submitKeys( k );
	check( 'a narrower format and a global key are one question with both among its details', k.asked.length === 2 && k.asked[1].details.length === 2 && k.asked[1].details.some( d => d.indexOf( D ) === 0 ) && k.asked[1].details.some( d => d.indexOf( A ) === 0 ) );
	k.handle.answer = true;
	submitKeys( k );
	check( '...answered yes both are written', keyWrites( k ).filter( c => c.endpoint === 'save' ).map( c => c.payload.key ).sort().join() === [ A, C, D, '/template/page-services/intro/text' ].sort().join() && k.asked.length === 3 );
}

{
	// the keys cannot be read again after what was written: the form stays, says it, and keeps what was turned down
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title', X = '/template/page-services/intro/text';
	typeInto( keyControl( k, T ), 'Gespeichert aber nicht gelesen' );
	typeInto( keyControl( k, X ), 'Abgelehnt' );
	k.handle.refuse['item:'+ X] = { ok : false, error : 'could not be written' };
	k.handle.down = true;
	submitKeys( k );
	check( 'where the keys cannot be read again the form stays: the line says it was saved and not read again, with a button that reads them, and the controls are free', k.form.querySelector('form') !== null && k.form.querySelector('#keys-form-msg').textContent === W('/_admin/keys/error/reload')
		&& k.form.querySelector('#keys-reload') !== null && k.form.querySelector('form').getAttribute('aria-busy') !== 'true' && k.form.querySelectorAll('input, textarea, select, button').every( c => c.disabled === false ) );
	check( '...what was written counts as saved and what was turned down is still unsaved input at its field, so the shell asks about exactly that', k.registry.keys.isDirty() === true && k.keys._session.changed().join() === X
		&& fieldOf( k, X ).parentNode.querySelectorAll('.nino-admin-field-error').length === 1 && keyControl( k, X ).value === 'Abgelehnt' );
	k.handle.down = false;
	k.handle.refuse = {};
	k.form.querySelector('#keys-reload').click();
	check( 'the button reads the keys anew and draws the row from them', k.form.querySelector('#keys-reload') === null && keyControl( k, T ).value === 'Gespeichert aber nicht gelesen' && k.registry.keys.isDirty() === false );
}

{
	// delete: asked, and said at the bar
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title';
	k.handle.answer = false;
	rowButtons( settingsOf( k, T ) )[1].click();
	check( 'deleting asks first, with the heaviest weight, and the title and the answer say what is done', k.asked.length === 1 && k.asked[0].level === 'destructive' && k.asked[0].title === W('/_admin/keys/confirm/delete-title') && k.asked[0].yes === k.asked[0].title && keyWrites( k ).length === 0 );
	k.handle.answer = true;
	k.handle.refuse.delete = { status : 500, body : null };
	rowButtons( settingsOf( k, T ) )[1].click();
	check( 'a refusal stands in the line of the bar - no window of the browser - and the key is still there', k.form.querySelector('#keys-form-msg').textContent.indexOf( '500' ) !== -1 && settingsOf( k, T ) !== null && k.told.length === 0 );
	delete k.handle.refuse.delete;
	rowButtons( settingsOf( k, T ) )[1].click();
	check( 'a delete that went through draws the row again without the key, says it and keeps the address', keyWrites( k ).at(-1).endpoint === 'delete' && settingsOf( k, T ) === null && k.told.at(-1) === W('/_admin/common/msg/deleted') && k.form.classList.contains('admin-hidden') === false && k.touched.join() === 'texts' );

	// it asks about unsaved input first, as the row is drawn again
	typeInto( keyControl( k, '/template/page-services/intro/text' ), 'x' );
	const before = k.counts.guard;
	rowButtons( settingsOf( k, '/template/page-services/item-2/title' ) )[1].click();
	check( 'a delete of a key in a row that holds unsaved input asks the shell first', k.counts.guard === before + 1 );
}

{
	// the form that renames: an address, the four segments, checked when it is sent
	const k = openedKeys( keysResponse() );
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/item-1/title';
	k.calls.length = 0;
	rowButtons( settingsOf( k, T ) )[0].click();
	const control = id => k.form.querySelector( '#'+ id );
	const options = id => control( id ).children.filter( c => c.tagName === 'option' ).map( o => o.value );
	check( 'Rename opens the form of a rename at its address, with the four segments of the key in its fields', k.keys._view === 'new' && k.keys._renameFrom === T && k.calls.includes('go keys/@rename//template/page-services/item-1/title') && k.calls.includes('set keys/@rename//template/page-services/item-1/title')
		&& control('keys-form-namespace').value === 'template' && control('keys-form-category').value === 'page-services' && control('keys-form-part').value === 'item-1' && control('keys-form-name').value === 'title' );
	check( '...the title says it and the main button says it - one main button, not locked', k.form.querySelector('.nino-admin-contextbar-title').textContent === W('/_admin/keys/label/rename-title') && k.form.querySelectorAll('.nino-admin-btn-primary').length === 1
		&& k.form.querySelector('.nino-admin-btn-primary').textContent === W('/_admin/keys/label/rename-title') && k.form.querySelector('.nino-admin-btn-primary').disabled === false && k.form.querySelector('form').noValidate === true );
	check( '...a namespace other than /project is unlocked, so the choice has them all; the category is a choice of what there is', options('keys-form-namespace').join() === 'project,template,feature,module' && options('keys-form-category').join() === 'common,frame-header,page-services'
		&& control('keys-form-preview').textContent === T && control('keys-form-new-value') === null );
	check( 'the form is in the address of the tab, and Back shows the list', JSON.stringify( k.keys._parts() ) === '[{"word":"@rename"},"/template/page-services/item-1/title"]' );

	submitKeys( k );
	check( 'a key that is not changed is no rename: the line says so, nothing is sent, the focus is at the name', keyWrites( k ).length === 0 && k.form.querySelector('#keys-form-msg').textContent === W('/_admin/keys/error/unchanged') && k.dom.doc.activeElement === control('keys-form-name') );
	const name = control('keys-form-name');
	typeInto( name, 'Titel!' );
	submitKeys( k );
	check( 'a segment that is no word of a key is said under it when the form is sent, with what is expected - and nothing is sent', keyWrites( k ).length === 0 && k.form.querySelectorAll('.nino-admin-field-error').length === 1 && name.getAttribute('aria-invalid') === 'true'
		&& k.form.querySelector('.nino-admin-field-error').textContent === F('/_admin/keys/error/invalid',W('/_admin/keys/label/name'),W('/_admin/keys/expect/segment')) );
	typeInto( name, 'heading-2' );
	check( 'the key the segments make is said as it is typed, and how the panel will read it', control('keys-form-preview').textContent === '/template/page-services/item-1/heading-2' && k.form.querySelector('#keys-form-shown').textContent === W('/_admin/keys/label/shown-as')+ ' '+ k.U.slugLabel('item-1')+ ' › '+ k.U.slugLabel('heading-2') );
	typeInto( control('keys-form-part'), 'cta' );
	check( '...a segment that is no word of the vocabulary but is one of a key is said under it - a warning, no refusal', k.form.querySelectorAll('.admin-key-note').some( n => n.textContent.indexOf( stem('/_admin/keys/hint/not-a-word') ) === 0 ) );
	typeInto( control('keys-form-part'), 'item-1' );
	k.handle.refuse.rename = { status : 409, body : { error : 'key already exists', code : 'keys_exists', params : [ '/template/page-services/item-1/heading-2' ], field : 'newKey' } };
	submitKeys( k );
	check( 'a key the server refuses - one that is taken - is said at the name field and in the line, with the focus there', k.form.querySelectorAll('.nino-admin-field-error').length === 1 && name.getAttribute('aria-invalid') === 'true' && k.form.querySelector('#keys-form-msg').textContent === F('/_admin/error/keys_exists','/template/page-services/item-1/heading-2') && k.dom.doc.activeElement === name );
	delete k.handle.refuse.rename;
	const renames = () => keyWrites( k ).filter( c => c.endpoint === 'rename' ).length;
	const before = renames();
	k.handle.answer = false;
	submitKeys( k );
	check( 'a rename asks first, at the weight of an identifier that templates may still call by its old name, with both names in the question and the act in the title and the answer', k.asked.at(-1).level === 'big' && k.asked.at(-1).title === W('/_admin/keys/confirm/rename-title') && k.asked.at(-1).yes === W('/_admin/keys/confirm/rename-title')
		&& k.asked.at(-1).message === F('/_admin/keys/confirm/rename',T,'/template/page-services/item-1/heading-2') );
	check( '...and No sends nothing: the form stays as it is, the line does not say it is saving', renames() === before && k.keys._view === 'new' && k.form.querySelector('#keys-form-msg').textContent !== W('/_admin/common/status/saving') );
	k.handle.answer = true;
	submitKeys( k );
	check( 'submitting renames the key to the one the segments make', keyWrites( k ).at(-1).endpoint === 'rename' && JSON.stringify( keyWrites( k ).at(-1).payload ) === JSON.stringify( { key : T, newKey : '/template/page-services/item-1/heading-2' } ) );
	check( '...a write of texts, and of templates too where the key is one a template reads', k.touched.join() === 'texts,templates' );
	check( '...and the key has its place: its row is open at it, at its address, as a toast says it', k.keys._view === 'group' && k.keys._currentGroup === 'template/page-services' && k.calls.at(-1).indexOf( 'set keys/template/page-services//template/page-services/item-1/heading-2' ) === 0 && k.told.at(-1) === W('/_admin/common/msg/saved') );
}

{
	// the form that creates
	const k = openedKeys( keysResponse() );
	k.calls.length = 0;
	k.list.querySelector('.nino-admin-list-actions .nino-admin-btn-primary').click();
	const control = id => k.form.querySelector( '#'+ id );
	const options = id => control( id ).children.filter( c => c.tagName === 'option' ).map( o => o.value );
	check( 'a new key has an address of its own, the at sign keeping it from a row called new', k.keys._view === 'new' && k.keys._renameFrom === null && k.calls.includes('go keys/@new') && k.calls.includes('set keys/@new') );
	check( '...the labels are at the left of the fields (the form is a fieldset of fields of the workbench), and every field of the segments is required', k.form.querySelector('fieldset').children.some( c => c.classList.contains('nino-admin-field') )
		&& [ 'keys-form-category', 'keys-form-part', 'keys-form-name' ].every( id => control( id ).getAttribute('aria-required') === 'true' ) );
	check( 'the main button says what it does, and is not locked while the form is empty', k.form.querySelector('.nino-admin-btn-primary').textContent === W('/_admin/keys/label/create') && k.form.querySelector('.nino-admin-btn-primary').disabled === false && k.form.querySelector('form').noValidate === true );
	check( '...a new key begins with /project alone to choose from, and a switch of the workbench that unlocks the others, with its reason behind the (?)', options('keys-form-namespace').join() === 'project'
		&& k.form.querySelector('.nino-admin-switch') !== null && k.form.querySelectorAll('.nino-admin-help-text').some( h => h.textContent === W('/_admin/keys/hint/unlock') ) );
	check( '...its category is a field with suggestions for /project', control('keys-form-category').tagName === 'input' && control('keys-form-category').getAttribute('list') === 'keys-form-categories' && k.form.querySelector('#keys-form-categories').children.map( o => o.value ).join() === 'company,website,mail,catalog' );
	check( '...and has a starting value, which is a field of several lines with the textfills', control('keys-form-new-value').tagName === 'textarea' && control('keys-form-new-value').closest('.nino-admin-field').querySelector('.nino-admin-fills-button') !== null && control('keys-form-new-global') !== null );

	const unlock = k.form.querySelector('.nino-admin-switch input');
	unlock.checked = true;
	unlock.fire('change');
	check( 'the unlock offers the other namespaces, and takes the category from a choice for a template, a feature or a module', options('keys-form-namespace').join() === 'project,template,feature,module' );
	choose( control('keys-form-namespace'), 'template' );
	check( '...whose categories are the ones there are', control('keys-form-category').tagName === 'select' && options('keys-form-category').join() === 'common,frame-header,page-services' );
	unlock.checked = false;
	unlock.fire('change');
	check( 'locking it again takes them away', options('keys-form-namespace').join() === 'project' && control('keys-form-namespace').value === 'project' );

	submitKeys( k );
	const errors = k.form.querySelectorAll('.nino-admin-field-error').map( e => e.textContent );
	check( 'sent empty, the form says under each segment that it is missing - the first takes the focus - and sends nothing', errors.join() === [ 'category', 'part', 'name' ].map( f => F('/_admin/common/error/missing',W('/_admin/keys/label/'+ f)) ).join() && keyWrites( k ).length === 0 && k.dom.doc.activeElement === control('keys-form-category') );
	check( '...and the line of the bar says so once', k.form.querySelector('#keys-form-msg').textContent === W('/_admin/keys/error/fields') );
	typeInto( control('keys-form-category'), 'company' );
	typeInto( control('keys-form-part'), 'tester' );
	typeInto( control('keys-form-name'), 'zzz-name' );
	typeInto( control('keys-form-new-value'), 'Hallo Welt' );
	check( 'the form is unsaved input once something is typed', k.registry.keys.isDirty() === true );
	submitKeys( k );
	check( 'sent, the form makes the key from its segments, with the starting value in the language of the workbench', keyWrites( k )[0].endpoint === 'create' && JSON.stringify( keyWrites( k )[0].payload ) === JSON.stringify( { key : '/project/company/tester/zzz-name', global : false, value : 'Hallo Welt', values : { de_DE : 'Hallo Welt' } } ) );
	check( '...the new key is open in its row, at its address, with the cursor in it, and a toast says it', k.keys._view === 'group' && k.keys._currentGroup === 'project/company' && k.calls.at(-1).indexOf( 'set keys/project/company//project/company/tester/zzz-name' ) === 0 && k.told.at(-1) === W('/_admin/common/msg/saved')
		&& k.form.querySelector('[data-key="/project/company/tester/zzz-name"]') !== null );
}

{
	// the language: a row shows one and writes that one
	const k = openedKeys( keysResponse(), undefined, 'en_US' );
	k.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	k.keys._openGroup( 'template/page-services', null, true );
	const T = '/template/page-services/intro/title';
	check( 'a row opens in the language of the workbench, which is the pill of its bar - and the list names its rows in it', keyControl( k, T ).value === 'Services' && k.form.querySelector('.nino-admin-locale-badge') !== null && k.keys._shownLocale === 'en_US' );
	k.box.Nino.admin.workLocale.current = 'de_DE';
	typeInto( keyControl( k, T ), 'Services B' );
	submitKeys( k );
	check( 'it writes the language it shows, whatever the workbench says by now', keyWrites( k )[0].payload.items[0].locale === 'en_US' );
	k.Nino.admin.workLocale.set( 'de_DE' );
	check( 'a language the workbench moved draws the row again in it, and the pill goes', keyControl( k, T ).value === 'Leistungen' && k.form.querySelector('.nino-admin-locale-badge') === null && k.keys._shownLocale === 'de_DE' );
}

{
	// the scan: a view with an address, the three answers of a finding, said as a toast
	const k = openedKeys( keysResponse() );
	k.handle.scan = { missing : [
		{ key : '/project/shop/hero/title', files : [ 'page-shop.tpl' ], kind : 'create', hint : null },
		{ key : '/project/shop/hero/text', files : [ 'page-shop.tpl', 'page-other.tpl' ], kind : 'create', hint : 'feature', owner : 'consent' },
		{ key : '/foo/bar', files : [ 'page-shop.tpl' ], kind : 'grammar' },
		{ key : '/_nino/webpage/shop/name', files : [ 'page-shop.tpl' ], kind : 'system', writer : 'routes' },
	], alsoUsed : [ { key : '/template/page-shop/x/y', files : [ 'page-other.tpl' ] } ] };
	k.calls.length = 0;
	k.list.querySelector('.nino-admin-list-actions .nino-admin-btn-secondary').click();
	check( 'the scan is a view with an address of its own, and says that it is scanning in place of the form while it runs', k.keys._view === 'scan' && k.calls.includes('go keys/@scan') && k.form.querySelector('form') !== null );
	const rows = k.form.querySelectorAll('fieldset.admin-keys-scan-row');
	check( 'a finding is a card with the key as its name, where it is read, and a starting value - where a key can be made here - and the three outcomes of it: a value, nothing, or ignored for good', rows.length === 4 && rows[0].querySelector('legend').textContent === '/project/shop/hero/title'
		&& rows[0].querySelector('input') !== null && rows[2].querySelectorAll('input').filter( i => i.type === 'text' ).length === 0 && rows[3].querySelectorAll('input').length === 0 );
	check( '...what the scan says besides - why a key cannot be made, who writes it - stands at the finding as a line of its own, and every note has its words', rows[1].textContent.indexOf( F('/_admin/keys/scan/hint-feature','consent') ) !== -1 && rows[2].textContent.indexOf( W('/_admin/keys/scan/grammar') ) !== -1 && rows[3].textContent.indexOf( W('/_admin/keys/scan/system-routes') ) !== -1 );
	check( '...a key of the system has nothing to tick: it is the system\'s to write', rows[3].querySelectorAll('input').length === 0 && rows[2].querySelectorAll('input').filter( i => i.type === 'checkbox' ).length === 1 );
	check( 'the keys another template reads as well are listed as a note, and counted nowhere', k.form.textContent.indexOf( '/template/page-shop/x/y' ) !== -1 && k.keys._scanRows.length === 3 );
	check( 'the bar has the one main button, and no explanation stands open over the rows: the scan is explained behind the ? of the panel', k.form.querySelectorAll('.nino-admin-btn-primary').length === 1 && k.form.querySelectorAll('.nino-admin-hint').length === 0 );
	check( 'a form nobody touched holds nothing unsaved', k.registry.keys.isDirty() === false );
	typeInto( rows[0].querySelector('input'), 'Eine Überschrift' );
	rows[2].querySelectorAll('input').find( i => i.type === 'checkbox' ).checked = true;
	check( 'a value typed or a key ticked is unsaved input', k.registry.keys.isDirty() === true );
	submitKeys( k );
	const apply = keyWrites( k ).at(-1);
	check( 'the whole form goes to the server in one request, with the language the tab shows: every finding, the ones left empty included - which the server passes over - and the ignored ones', apply.endpoint === 'scanapply' && apply.payload.rows.length === 3 && apply.payload.locale === 'de_DE'
		&& apply.payload.rows[0].value === 'Eine Überschrift' && apply.payload.rows[0].ignore === false && apply.payload.rows[1].value === '' && apply.payload.rows[2].ignore === true );
	check( 'what came of it is one sentence as a toast, and the list is back - with the keys that were made', k.told.at(-1) === W('/_admin/keys/scan/result').replace( '%c', '1' ).replace( '%i', '1' ).replace( '%s', '1' ) && k.form.classList.contains('admin-hidden') === true && k.list.classList.contains('admin-hidden') === false );
	check( '...a write of texts, and of templates where a key of a template was made', k.touched.join() === 'texts' );
	k.handle.scan = { missing : [], alsoUsed : [] };
	k.list.querySelector('.nino-admin-list-actions .nino-admin-btn-secondary').click();
	check( 'a scan that finds nothing says so in the box of the workbench, with no bar to save', k.form.querySelector('.nino-admin-empty') !== null && k.form.querySelectorAll('.nino-admin-actionbar').length === 0 && k.registry.keys.isDirty() === false );
}

{
	// the address of a view of the tab
	const k = openedKeys( keysResponse() );
	const target = parts => JSON.stringify( k.keys._target( parts ) );
	check( 'an address names a row, a key in it, the form of a new key, the scan, or the rename of a key that can be renamed', target( [ 'template/page-services' ] ) === '{"view":"group","row":"template/page-services","key":null}'
		&& target( [ 'template/page-services', '/template/page-services/intro/title' ] ) === '{"view":"group","row":"template/page-services","key":"/template/page-services/intro/title"}' && target( [ '@new' ] ) === '{"view":"new","key":null}' && target( [ '@scan' ] ) === '{"view":"scan"}'
		&& target( [ '@rename', '/template/page-services/intro/title' ] ) === '{"view":"new","key":"/template/page-services/intro/title"}' );
	check( 'the address read by the router: @rename and a key is the rename of that key, %40rename and a key is no rename - a row called @rename is none', JSON.stringify( k.keys._target( codec.read( '#text/keys/@rename/%2Ftemplate%2Fpage-services%2Fintro%2Ftitle' ).parts ) ) === '{"view":"new","key":"/template/page-services/intro/title"}' );
	check( '...and what is not there is the list: a rename of a key that is not there or of one of the system, an at sign with more behind it, a row nobody has', k.keys._target( [ '@rename', '/template/nope/a/b' ] ) === null && k.keys._target( [ '@rename', '/_nino/webpage/services/title' ] ) === null && k.keys._target( [ '@rename', 'template', 'page-services', 'intro', 'title' ] ) === null && k.keys._target( [ '@rename', '/template/page-services/intro/title', 'more' ] ) === null && k.keys._target( codec.read( '#text/keys/%40rename/%2Ftemplate%2Fpage-services%2Fintro%2Ftitle' ).parts ) === null
		&& k.keys._target( [ '@new', 'x' ] ) === null && k.keys._target( [ '@scan', 'x' ] ) === null && k.keys._target( [ 'nothing' ] ) === null && k.keys._target( [ '@rename' ] ) === null );
}

{
	// the fills a field offers are the tab's own, asked of the server in the language on screen
	const k = openedKeys( keysResponse() );
	let listed = 'none';
	k.keys._loadFills( list => { listed = list } );
	check( 'the textfills are asked for with the tab\'s own action, and the answer is the list', k.sent.at(-1).endpoint === 'fills' && Array.isArray( listed ) === true );
	k.keys._apiCall = ( endpoint, payload, callback ) => callback( 200, { fills : [ { key : '/project/company/contact/phone', notation : '[[/project/company/contact/phone]]', value : '+49' } ] } );
	k.keys._loadFills( list => { listed = list } );
	check( '...each row named by its key read as words, with the notation the field reads and what it says today', JSON.stringify( listed ) === JSON.stringify( [ { name : [ 'company', 'contact', 'phone' ].map( w => k.U.slugLabel( w ) ).join(' \u00B7 '), notation : '[[/project/company/contact/phone]]', value : '+49' } ] ) );
	k.keys._apiCall = ( endpoint, payload, callback ) => callback( 403, null );
	k.keys._loadFills( list => { listed = list } );
	check( '...and where they could not be had, none is said and the field is left as it is', listed === null );
}

{
	// the focus after a delete: the key that stands where the deleted one did, else the list; and the language is told in the other views
	const response = keysResponse();
	response.keys.push( textEntry( '/project/shop/solo/name', { de_DE : 'Einzig', en_US : 'Only' } ) );
	const k = openedKeys( response );
	k.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	k.keys._openGroup( 'template/page-services', null, true );
	const order = k.keys._session.keys();
	const T = '/template/page-services/intro/title', next = order[order.indexOf( T ) + 1];
	rowButtons( settingsOf( k, T ) )[1].click();
	check( 'after a delete the focus is on the field of the key that stands where the deleted one was', settingsOf( k, T ) === null && k.dom.doc.activeElement === keyControl( k, next ) );
	const last = k.keys._session.keys().at(-1);
	rowButtons( settingsOf( k, last ) )[1].click();
	check( '...and where it was the last one, on the one before', k.dom.doc.activeElement === keyControl( k, k.keys._session.keys().at(-1) ) );

	k.keys._openGroup( 'project/shop', null, true );
	rowButtons( settingsOf( k, '/project/shop/solo/name' ) )[1].click();
	check( 'a delete that leaves the group without a key draws the list, which is the empty state of the group, and the focus is not lost to the page', k.keys._view === null && k.form.classList.contains('admin-hidden') && k.dom.doc.activeElement !== null && k.dom.doc.activeElement !== k.dom.doc.body );

	// the language the workbench is in is said by a pill in the views that make or scan, and it follows
	k.Nino.admin.workLocale.current = 'en_US';
	k.keys._openNewKeyForm( null, null, true );
	check( 'the form that makes a key says the language its starting value is written in, by the pill of the bar', k.form.querySelector('.nino-admin-locale-badge') !== null );
	k.Nino.admin.workLocale.set( 'de_DE' );
	check( '...and the native language needs none', k.form.querySelector('.nino-admin-locale-badge') === null );
	k.Nino.admin.workLocale.set( 'en_US' );
	check( '...the language the workbench moves to is the pill, without the form being drawn again', k.form.querySelector('.nino-admin-locale-badge') !== null );
	k.list.querySelector('.nino-admin-list-actions .nino-admin-btn-secondary').click();
	check( 'the scan says the language its new keys get their value in, in the same way', k.keys._view === 'scan' && k.form.querySelector('.nino-admin-locale-badge') !== null );
}

{
	// a language without a value is an empty field, a whitespace is a change, and the fills ask in the language of the form
	const response = keysResponse();
	const k = openedKeys( response, undefined, 'en_US' );
	k.Nino.adminUi.locales = { native : 'de_DE', names : { de_DE : 'Deutsch', en_US : 'English' } };
	k.keys._openGroup( 'template/page-services', null, true );
	const X = '/template/page-services/intro/text', T = '/template/page-services/intro/title';
	check( 'a language that has no text for a key draws its field empty - the text of another language is not put in as if it were its own', keyControl( k, X ).value === '' && keyControl( k, T ).value === 'Services' && k.registry.keys.isDirty() === false );
	typeInto( keyControl( k, T ), 'Services ' );
	submitKeys( k );
	check( 'a whitespace is a change, and is saved as typed, in the language of the form', keyWrites( k ).length === 1 && keyWrites( k )[0].payload.items.length === 1 && keyWrites( k )[0].payload.items[0].value === 'Services ' && keyWrites( k )[0].payload.items[0].locale === 'en_US' );
	k.sent.length = 0;
	submitKeys( k );
	check( 'a save without a change sends no text, and none for the key that had none', keyWrites( k ).every( c => c.endpoint !== 'savebatch' ) );
	k.keys._loadFills( () => {} );
	check( 'the textfills are asked for in the language of the form', k.sent.at(-1).endpoint === 'fills' && k.sent.at(-1).payload.locale === 'en_US' );
}

{
	// words: every fill the two scripts ask for is there in both languages
	const files = [ '_admin/Nino/Modules/Text/assets/admin.js', '_admin/Nino/Modules/Text/assets/keys.js', '_admin/Nino/Modules/Text/assets/textkeys.js' ];
	const used = new Set();
	files.forEach( file => { for( const m of fs.readFileSync( path.join( __dirname, '..', file ), 'utf8' ).matchAll( /(?:getText|text)\(\s*'(\/_admin\/[^']+)'/g ) ) used.add( m[1] ) } );
	[ 'en_US', 'de_DE' ].forEach( locale => {
		const words = workbenchWords( locale );
		const missing = [ ...used ].filter( key => key.endsWith('/') === false && key.endsWith('-') === false && words[key] === undefined );
		check( locale+ ': every fill the scripts of the Text module name is there'+ ( missing.length === 0 ? '' : ' - not '+ missing.join() ), missing.length === 0 );
		const sorts = [ 'grammar', 'system-routes', 'system-language', 'system-none', 'hint-feature', 'hint-module' ].filter( s => words['/_admin/keys/scan/'+ s] === undefined );
		check( locale+ ': every note of the scan, which is put together at runtime, has its words', sorts.length === 0 );
		check( locale+ ': every word of a format and of its conversion is there', [ 'auto', 'plain', 'inline', 'lines', 'blocks' ].every( f => words['/_admin/keys/format/'+ f] !== undefined ) && [ 'plain', 'inline', 'lines' ].every( f => words['/_admin/keys/convert/'+ f] !== undefined ) );
	} );
}
check( 'every address the Text and Keys panels wrote or drew - a row, a key of it, a new key, a rename, the scan - is read back by the router as the same screen and the same parts', codec.written.length > 10 && codec.mismatches.length === 0
	&& codec.written.some( written => written.screen === 'text' && written.parts.length === 2 ) && codec.written.some( written => written.screen === 'keys' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@rename' ) && written.parts.length === 2 )
	&& codec.written.some( written => written.screen === 'keys' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@new' ) ) && codec.written.some( written => written.screen === 'keys' && ( typeof written.parts[0] === 'object' && String( written.parts[0] ) === '@scan' ) ) );

done();
