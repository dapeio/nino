/**
 *	Nino									A compact filesystembased php framework
 *	admin-text-js-smoke.js	DOM-light checks for the Text panel and its Keys tab
 *										(_admin/Nino/Modules/Text/assets): how keys are named and
 *										fall into rows, sections and fields, how they are searched,
 *										and what the Text panel does on the real shell
 *										(adminPage() of the harness) and the Keys tab on a fake page.
 *
 *										The groups of both tabs are the list of the workbench, with
 *										the same names and an address each; the language the
 *										texts are shown in is the workbench's own, there is no
 *										switch of it here. In the Text panel a group is the standard
 *										directory of its texts and ONE text is edited in the record
 *										over it: the working language first, the other languages
 *										below, saved one request for each language that was
 *										changed, none where nothing was. The Keys tab shows one
 *										language in a form and writes that one and no other, saves a key's shape and its text with
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

// What the Keys part reads its forms of the fake page with
const fieldOf = ( t, key ) => t.form.querySelector('[data-key="'+ key+ '"]');
const typeInto = ( control, value ) => { control.value = value; control.fire('input') };

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
	const handle = { hold : false, refuse : {}, fail : null, sanitize : null, listed : 0 };

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
				results[item.key] = { ok : true, value : value };
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
	check( 'the second line of a row is how many texts it holds, how many have none in the working language and how many are too long - and no running text', w.lineOf( ROW ) === F('/_admin/text/count/texts',13)+ ' · '+ F('/_admin/text/count/long',1) && w.lineOf( ROW ).indexOf( 'Beratung' ) === -1 );
	check( '...a row that has nothing empty and nothing too long says only how many', w.lineOf('project/company') === F('/_admin/text/count/texts',4) && w.lineOf('template/common') === F('/_admin/text/count/texts',2) );
	check( '...one text is the singular, in the words of the panel', w.lineOf('template/frame-header') === F('/_admin/text/count/text',1) && W('/_admin/text/count/text') !== W('/_admin/text/count/texts') );
	check( '...a text that is the same in every language is never empty in one', w.panel._model.rows['project/company'].entries.some( e => e.global === true ) && w.lineOf('project/company').indexOf(' leer in ') === -1 );

	// the counts follow the working language
	w.go( 'en_US' );
	check( 'the counts follow the working language: the texts that are empty in it, named in it, and the ones that are too long there', w.lineOf( ROW ) === F('/_admin/text/count/texts',13)+ ' · '+ F('/_admin/text/count/empty',3,'English')
		&& tag( w.listRow( ROW ), 'strong' )[0].textContent === 'Services' && findAll( w.list, el => el.id === 'text-list-empty' )[0].textContent === F('/_admin/text/label/empty-in','English') );
	w.go( 'de_DE' );
	check( '...and back', w.lineOf( ROW ) === F('/_admin/text/count/texts',13)+ ' · '+ F('/_admin/text/count/long',1) );
}

{
	// "too long" is counted the way the field counts: the characters of a plain text, the visible characters of a text with markup
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
	// P2
	check( 'a text with markup is too long by its visible characters and a plain one by its length - on the limit it is not', w.lineOf('template/page-hello') === F('/_admin/text/count/texts',5)+ ' · '+ F('/_admin/text/count/long',3) );
	check( '...counted for the language on screen: the other has none of them', ( () => { w.go( 'en_US' ); const line = w.lineOf('template/page-hello'); w.go( 'de_DE' ); return line === F('/_admin/text/count/texts',5)+ ' · '+ F('/_admin/text/count/empty',5,'English') } )() );
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
	check( '...and one select "Show" after it: all texts, empty in the working language, empty in a language, too long', show.attributes['aria-label'] === W('/_admin/text/filter/show') || findAll( w.form, el => el.textContent === W('/_admin/text/filter/show') ).length > 0
		&& tag( show, 'option' ).map( o => o.textContent ).join() === [ W('/_admin/text/filter/all'), F('/_admin/text/label/empty-in','Deutsch'), W('/_admin/text/filter/empty-any'), W('/_admin/text/filter/long') ].join() );
	const choose = ( select, value ) => { select.value = value; fire( select, 'change', { target : select } ) };

	choose( category, 'item-1' );
	check( 'a section narrows the rows to its texts, in their order', w.ids().join() === [ '/template/page-services/item-1/title', RICH ].join() );
	choose( category, '' );
	choose( show, 'long' );
	check( 'Show "too long" keeps the texts over their limit - counted as the field counts them', w.ids().join() === LONG );
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
	choose( w.selects()[1], 'long' );
	fire( byClass( w.form, 'nino-admin-back-link' )[0], 'click' );
	check( 'Back is the list, the focus on the row that was open, the address the list\'s', has( w.list, 'admin-hidden' ) === false && w.hash() === '#text' && w.h.doc.activeElement === w.linkOf( ROW ) );
	fire( w.linkOf( ROW ), 'click', { target : w.linkOf( ROW ) } );
	check( 'the row opened again is as the person left it: the select "Show" holds what it held', w.ids().join() === LONG && w.selects()[1].value === 'long' );
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
	// a text that the save made no longer empty leaves that table: there is no place to step on from, and the record stays at it, saved
	w.close( w.dialog() );
	const e = w.openRow( INTRO );
	w.type( e, 'en_US', 'Hello' );
	fire( w.button( e, '/_admin/common/label/save-next' ), 'click' );
	check( 'a text that the save made fill its last empty language leaves the table that shows the empty ones: the record stays at it, saved', w.subtitleOf( w.dialog() ) === INTRO && w.ids().indexOf( INTRO ) === -1 && w.h.dirty.isDirty() === false && w.writes().length === 2 );
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
	// A template written since the keys were read is a row the list did not have: the panel reads the keys again where it is shown with no record open, and not before
	const response = textResponse();
	const w = textPage( response ).open();
	const asked = () => w.sent.filter( c => c.endpoint === 'keys' ).length;
	const fresh = '/template/frame-footer/navigation/label';

	check( 'the panel asks for the keys once when it opens, and keeps the stamp of the templates it asked at', asked() === 1 && w.panel._stamp === 0 && w.panel._model.rowOf[fresh] === undefined && w.listRow('template/frame-footer') === undefined );
	w.panel.showCurrent();
	check( 'shown again with nothing written since, it asks for nothing', asked() === 1 );
	w.h.admin.touch( 'texts' );
	w.panel.showCurrent();
	check( '...nor does a write of texts make it ask: the texts it writes itself it has in hand', asked() === 1 );

	response.keys = response.keys.concat( [ textEntry( fresh, { de_DE : 'Fußnavigation', en_US : 'Footer navigation' } ) ] );
	w.h.admin.touch( 'templates' );
	w.panel.showCurrent();
	check( 'a template written since: shown again, it asks again - and the rows have what the template made', asked() === 2 && w.panel._stamp === 1 && w.panel._model.rowOf[fresh] === 'template/frame-footer' && w.listRow('template/frame-footer') !== undefined );
	w.panel.showCurrent();
	check( '...once: what it holds now is the stamp it asked at', asked() === 2 );

	// a record is open: the table is not drawn under it
	fire( w.linkOf( ROW ), 'click', { target : w.linkOf( ROW ) } );
	const d = w.openRow( TITLE );
	w.h.admin.touch( 'templates' );
	w.panel.showCurrent();
	check( 'a record that is open is not drawn under: shown again, the panel asks for nothing and the record stays', asked() === 2 && w.dialog() === d && w.panel._group === ROW );
	w.close( d );
	w.panel.showCurrent();
	check( '...the showing that finds none open is the one that asks, and the stamp is the one of that time - the table of the row is drawn from what it holds', asked() === 3 && w.panel._stamp === 2 && has( w.form, 'admin-hidden' ) === false && w.ids().length === 9 );

	// a template written while the answer is on its way is not in it
	const v = textPage( response );
	v.panel.showCurrent();
	v.h.admin.touch( 'templates' );
	check( 'the stamp is the one from before the request went out: the answer that arrives later keeps it', v.panel._stamp === 0 && v.panel._ready === true );
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
		check( locale+ ': the counts, the heads and the words of the table and the record are all there, and the singular is another word than the plural', [ 'count/text', 'count/texts', 'count/empty', 'count/long', 'col/name', 'col/section', 'col/text', 'col/chars', 'col/long', 'col/languages', 'col/lang-ok', 'col/lang-missing', 'filter/show', 'filter/all', 'filter/empty-any', 'filter/long', 'filter/sections', 'row/open', 'record/prev', 'record/next', 'record/missing', 'label/working' ].every( k => words['/_admin/text/'+ k] !== undefined && words['/_admin/text/'+ k] !== '' )
			&& words['/_admin/text/count/text'] !== words['/_admin/text/count/texts'] );
	} );
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
