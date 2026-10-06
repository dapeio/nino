/**
 *	Nino									A compact filesystembased php framework
 *	admin-elements-js-smoke.js	DOM-free checks for the Elements editor's
 *											multi-locale save planning.
 *
 *	Usage: node tests/admin-elements-js-smoke.js
 */

'use strict';

const fs 	= require('fs');
const path 	= require('path');
const vm 		= require('vm');

let checks = 0;
let failures = 0;

function check( label, condition ) {
	checks++;
	if( condition === true ) {
		console.log( '  ok  - '+ label );
		return;
	}
	failures++;
	console.log( 'FAIL  - '+ label );
}

const sandbox = {
	console : console,
	document : { documentElement : null, body : null },
};
sandbox.window = sandbox;
sandbox.Nino = {
	editor : {},
	events : { bindCallback : function() {} },
};

const source = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/admin.js' ), 'utf8' );

const context = vm.createContext( sandbox );

// The shared admin layer, loaded first exactly as the tool's own shell loads
// it: elements.js asks Nino.adminUi whether a model field is a multi element
// reference, so a sandbox without it tests a module the page never runs
vm.runInContext(
	fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ),
	context,
	{ filename : 'Nino.admin.js' }
);

vm.runInContext(
	source,
	context,
	{ filename : 'elements.js' }
);

const elements = sandbox.Nino.admin.elements;

// Regression: an image field's preview used to be built as "/uploads/<stored
// filename>", a directory no deployment has - every upload is stored under
// /images (Nino\Images::UPLOAD_DIR). The preview shown right after an upload
// renders the server's own url and always looked right, so the 404 only
// appeared once the form was re-rendered from the stored value. Same bug and
// same guard as _admin's own copy of this module (see
// tests/admin-elements-js-smoke.js)
check( 'no image url is built under a /uploads directory', /(?:asset|public)Url\(\s*'\/uploads\//.test( source ) === false );
check( 'the image preview is built under /images, via the public content prefix', /publicUrl\(\s*'\/images\/'\+ value\s*\)/.test( source ) === true );

elements._localeKeys = [ 'title', 'description' ];
elements._dirtyLocales = [ 'de_DE', 'en_US' ];
elements._selectedLocale = 'en_US';
check( 'Save includes an edited locale visited before the visible one', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

elements._dirtyLocales = [ 'de_DE', 'en_US' ];
check( 'the visible locale is not queued twice', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

elements._dirtyLocales = [];
check( 'the visible locale is the fallback when only global fields changed', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

elements._localeKeys = [];
check( 'a type without translated fields still needs only one save', JSON.stringify( elements._saveLocales() ) === JSON.stringify( [ 'en_US' ] ) );

check( 'an absent string equals its untouched empty control', elements._fieldValuesEqual( { type : 'string' }, null, '' ) === true );
check( 'an absent array equals its untouched empty control', elements._fieldValuesEqual( { type : 'array' }, undefined, [] ) === true );
check( 'an absent boolean equals its untouched false control', elements._fieldValuesEqual( { type : 'boolean' }, null, false ) === true );
check( 'actual text edits are detected', elements._fieldValuesEqual( { type : 'string' }, 'Before', 'After' ) === false );

// An image field never blocks a save, even when its model says required: its
// file is uploaded separately, only once the element exists and has a uri to
// attach it to, so on a new element it is empty by construction. Enforcing it
// would make the element impossible to create at all - the same rule _admin's
// own copy of this module and its Element Types editor apply (see
// tests/admin-elements-js-smoke.js and tests/admin-smoke.php)
sandbox.document.getElementById = function() { return null };
sandbox.Nino.content = { getText : function() { return '' } };

elements._currentType 	= 'service';
elements._globalKeys 		= [ 'photo' ];
elements._localeKeys 		= [ 'title' ];
elements._currentModel	= {
	photo : { type : 'image', required : true },
	title : { type : 'string', required : true },
};

check( 'a required image field is never reported as missing', elements._missingRequiredFields().indexOf( 'photo' ) === -1 );
check( '...while an empty required field of any other type still is', JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [ 'title' ] ) );

/*	One click on Save writes every translation that was edited (see
	_saveLocales() above), and the required check read the dom - which only
	ever holds the locale on screen. So a required field left empty in one
	language, filled in in another and saved from there, went through: the
	form said "saved" and the element carried a required field with nothing
	in it, in a language nobody was looking at.

	The dom below is what _isFieldEmpty() reads: the controls of the visible
	locale, and nothing else. Every other translation lives in _localeValues,
	which is exactly what is submitted for it.	*/
const visibleFields = {};
sandbox.CSS = { escape : function( value ) { return String( value ) } };
sandbox.document.getElementById = function( id ) {
	return id !== 'elements-form' ? null : {
		querySelector : function( selector ) {
			// ':checked' resolves a radio group, which none of these are
			if( selector.endsWith(':checked') === true )
				return null;
			const match = selector.match( /\[data-field="([^"]+)"\]/ );
			const key = match === null ? '' : match[1];
			return visibleFields[key] ?? null;
		},
	};
};

elements._globalKeys 			= [];
elements._localeKeys 			= [ 'title', 'tags' ];
elements._currentModel		= {
	title : { type : 'string', required : true },
	tags 	: { type : 'array', required : true },
};
elements._selectedLocale 	= 'en_US';
elements._dirtyLocales 		= [ 'de_DE', 'en_US' ];
elements._localeValues 		= {
	de_DE : { title : '', tags : [ 'x' ] },
	en_US : { title : 'Cleaning', tags : [ 'x' ] },
};
visibleFields.title = { value : 'Cleaning', dataset : { type : 'string' } };
visibleFields.tags 	= { value : '["x"]', dataset : { type : 'array' } };

check( 'a required field left empty in another edited translation holds the save back',
	JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [ 'title (de_DE)' ] ) );

elements._localeValues.de_DE.tags = [];
check( '...and a required list is empty the same way a required text is',
	JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [ 'title (de_DE)', 'tags (de_DE)' ] ) );

elements._localeValues.de_DE = { title : 'Reinigung', tags : [ 'y' ] };
check( 'a translation that has everything it needs does not hold anything back',
	JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [] ) );

// A translation nobody edited is not submitted, so it is not this save's
// business either - an element may perfectly well have a language it has no
// text for yet
elements._dirtyLocales = [ 'en_US' ];
elements._localeValues.fr_FR = { title : '', tags : [] };
check( 'an untouched translation is not checked, because it is not written',
	JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [] ) );

// The locale on screen is still read from its controls, and still named
// without a language nobody needs to be told about
elements._dirtyLocales = [ 'en_US' ];
visibleFields.title.value = '   ';
check( 'the visible locale is still read from the form itself',
	JSON.stringify( elements._missingRequiredFields() ) === JSON.stringify( [ 'title' ] ) );

sandbox.document.getElementById = function() { return null };
elements._localeValues = {};
elements._dirtyLocales = [];

// --- _loadReferenceOptions(): what an element field's select is built from --
//
// The choices come from the referenced type's own element list, fetched
// before the form renders (a locale switch re-renders the fields, so options
// arriving afterwards would have to be threaded through that too). One
// request per distinct referenced type, never one per field.

const calls = [];
elements._apiCall = function( endpoint, payload, callback ) {
	calls.push( endpoint+ ':'+ JSON.stringify( payload ) );
	callback( 200, { elements : [ { uri : 'ada', label : 'Ada' }, { uri : 'grace', label : 'Grace' } ] } );
};

elements._currentModel = {
	headline 	: { type : 'string' },
	author 		: { type : 'element', elementType : 'people' },
	reviewer 	: { type : 'element', elementType : 'people' },
	source 		: { type : 'element', elementType : 'journals' },
	broken 		: { type : 'element' },
};

let done = false;
elements._loadReferenceOptions( function() { done = true } );

check( 'the continuation runs once every referenced type has answered', done === true );
check( 'one list request per distinct referenced type, not per field', calls.length === 2 );
check( '...and each asks for the type the field declares', calls.join() === 'list:{"type":"people"},list:{"type":"journals"}' );
check( 'a field with no referenced type is skipped rather than requested as ""', calls.some( function( c ) { return c.indexOf( '""' ) !== -1 } ) === false );
check( 'the options land keyed by referenced type', Object.keys( elements._referenceOptions ).sort().join() === 'journals,people' );
check( '...carrying uri and label for the select to render', elements._referenceOptions.people[0].uri === 'ada' && elements._referenceOptions.people[0].label === 'Ada' );

// A model with nothing to resolve must not wait on a request that never fires
calls.length = 0;
elements._currentModel = { headline : { type : 'string' } };
let plainDone = false;
elements._loadReferenceOptions( function() { plainDone = true } );
check( 'a model without references continues immediately, with no request at all', plainDone === true && calls.length === 0 );

// A referenced type deleted since the model was written answers with an error.
// Resolving it to an empty list is what lets _renderField() show the stored
// value as missing - holding the form back would strand the whole element
elements._apiCall = function( endpoint, payload, callback ) { callback( 404, null ) };
elements._currentModel = { author : { type : 'element', elementType : 'gone' } };
let errorDone = false;
elements._loadReferenceOptions( function() { errorDone = true } );
check( 'a referenced type that errors resolves to an empty list instead of hanging', errorDone === true && JSON.stringify( elements._referenceOptions.gone ) === '[]' );

// Stale options are worse than none: an element added to the referenced type
// has to be selectable in the very next form that points at it
elements._apiCall = function( endpoint, payload, callback ) { callback( 200, { elements : [ { uri : 'new', label : 'New' } ] } ) };
elements._loadReferenceOptions( function() {} );
check( 'each form open refills the options rather than reusing the last ones', elements._referenceOptions.gone[0].uri === 'new' );



// --- a type that numbers its own elements ---------------------------------
//
// The element form asks for a uri, unless the type assigns one itself (see
// Elements::AUTOINCREMENT_PAD). Which types those are arrives with the same
// type list every form is built from, and _renderTypes() is the one place that
// records it - every path into a form goes through that list.

elements._numbered = {};
elements._currentType = 'articles';
check( 'a type not in the numbered map asks for a uri', elements._isNumbered() === false );

elements._numbered = { gallery : '00007' };
elements._currentType = 'gallery';
check( 'a numbered type is recognised', elements._isNumbered() === true );
check( 'the next uri is the one the backend reported', elements._nextUri() === '00007' );

// A type whose entry is an empty string is still numbered - the backend simply
// had no number to report. hasOwnProperty, not a truthiness test, is what keeps
// those two apart.
elements._numbered = { gallery : '' };
check( 'a numbered type with no reported number is still numbered', elements._isNumbered() === true );
check( '...and falls back to the first number rather than showing nothing', elements._nextUri() === '00001' );

// Source-level, because both live in _renderForm()/_save()'s dom branches
// which this dom-free sandbox cannot reach
check( 'a numbered insert renders the uri field hidden rather than dropping it',
	/_isNumbered\(\) === true \) \{[\s\S]{0,600}uriInput\.type = 'hidden'/.test( source ) === true );
check( '...and keeps its value empty, which is what asks for a number',
	/_isNumbered\(\) === true \) \{[\s\S]{0,600}uriInput\.value = ''/.test( source ) === true );
check( 'the "a uri is required" guard is skipped for a numbered insert',
	/uri === '' && numberedInsert === false/.test( source ) === true );
check( 'the saved element\'s own .uri is what the form adopts afterwards',
	/response\.element\['\.uri'\]/.test( source ) === true );

// --- an element reference holding a list ----------------------------------
//
// The control stores its ordered list as json in a hidden input, so the form
// reads it back through the same [data-field] path as every other field. Only
// data-multiple separates it from a single reference's select, which carries
// one uri as a plain string.

check( 'a list reference is read back as the array it holds',
	JSON.stringify( elements._readField( { dataset : { type : 'element', multiple : '0' }, value : '["/tag/php","/tag/css"]' } ) ) === '["/tag/php","/tag/css"]' );
check( 'a single reference is still read back as its plain uri',
	elements._readField( { dataset : { type : 'element' }, value : '/tag/php' } ) === '/tag/php' );
// A half-written value must not take the whole save down with it
check( 'unreadable json falls back to an empty list rather than throwing',
	JSON.stringify( elements._readField( { dataset : { type : 'element', multiple : '2' }, value : '[' } ) ) === '[]' );

// Merely visiting a translation must not mark it as edited
check( 'an absent list equals its untouched empty control',
	elements._fieldValuesEqual( { type : 'element', multiple : 0 }, null, [] ) === true );
check( 'a reordered list is an actual edit',
	elements._fieldValuesEqual( { type : 'element', multiple : 0 }, [ '/tag/php', '/tag/css' ], [ '/tag/css', '/tag/php' ] ) === false );
// Without the multiple key this is the old single reference, whose empty
// value is the empty string - normalizing it to [] would call every untouched
// single reference edited
check( 'a single reference still normalizes to an empty string, not a list',
	elements._fieldValuesEqual( { type : 'element' }, null, '' ) === true );

check( 'the form renders the shared control rather than a second copy of it',
	source.includes('Nino.adminUi.elementList(') && source.includes('Nino.adminUi.isMultiElement( field )') );


// --- scoped permissions, as the form reads them ---------------------------
//
// apiTypes() sends what the account may do per type; the form draws itself
// from it so a control the save would refuse is never offered. A type the
// server said nothing about has to stay fully usable - the enforcement is
// server-side, and a missing answer must not lock a working panel down.

elements._currentType = 'services';
elements._rights = {};
check( 'a type with no answer is unrestricted', elements._mayInsert() === true && elements._mayDelete() === true && elements._mayUpdate('title') === true );

elements._rights = { services : { insert : false, delete : false, update : { title : true, price : false } } };
check( 'adding is refused where the server said so', elements._mayInsert() === false );
check( 'deleting is refused where the server said so', elements._mayDelete() === false );
check( 'a field it may write is writable', elements._mayUpdate('title') === true );
check( '...and one it may not is not', elements._mayUpdate('price') === false );
// A field the answer does not mention at all - a model changed since the list
// was loaded - is not a field to lock: the save decides
check( 'a field the answer does not mention stays writable', elements._mayUpdate('subtitle') === true );

elements._currentType = 'other';
check( 'another type is judged by its own answer, not this one', elements._mayInsert() === true );

check( 'the save sends only the fields it may write',
	source.includes( '.filter( function( key ) { return Nino.admin.elements._mayUpdate( key ) } )' ) );
// Locking, not hiding: the value is the context the writable fields around it
// are edited in
check( 'a field it may not write is rendered and then locked',
	source.includes( "label.classList.add( 'admin-field-readonly' )" ) && source.includes( "el.disabled = true" ) );
// _setFormPending( false ) used to re-enable every control in the form, which
// would hand back exactly the fields this account may not write
check( 'a save cycle does not unlock them again',
	source.includes( "el.disabled = pending || el.closest('.admin-field-readonly') !== null" ) );
check( 'Duplicate is offered where adding is, and only there',
	source.includes( "Nino.admin.elements._isNew === false && Nino.admin.elements._mayInsert() === true" ) );


// --- invalidate(): the contract with the Element Types tab ----------------
//
// The schema this module renders every form from belongs to the tab next
// door, and is read once per page load. When a type is saved, created or
// deleted over there, this cache is stale - a deleted type leaves this pane
// on a list of elements that no longer exist. types.js calls
// Nino.admin.elements.invalidate() for exactly that, so the method has to be
// here: the call is silent about a namespace member that does not exist until
// it throws in the browser.
check( 'the module answers the invalidate() its sibling calls',
	typeof elements.invalidate === 'function' );

const typesSource = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/assets/types.js' ), 'utf8' );
check( '...and that sibling is the one calling it',
	typesSource.includes( 'Nino.admin.elements.invalidate()' ) );

sandbox.document.getElementById = function() { return null };

elements._ready 			= true;
elements._loading 		= true;
elements._currentType = 'services';
elements._currentModel = { title : { type : 'string' } };
elements._currentUri 	= 'first';
elements._globalKeys 	= [ 'title' ];
elements._localeKeys 	= [ 'body' ];
elements._globalValues = { title : 'x' };
elements._localeValues = { de_DE : { body : 'y' } };
elements._dirtyLocales = [ 'de_DE' ];
elements._raw 				= { '*' : {} };
elements._referenceOptions = { tag : [] };
elements._pendingUri 	= 'first';
const typesRequestBefore = elements._typesRequest;

elements.invalidate();

// _ready false is what makes the next showCurrent() fetch again rather than
// re-showing a drill-down level built from the deleted type
check( 'invalidate drops the cached schema and the open element', elements._ready === false && elements._currentType === null && elements._currentModel === null && elements._currentUri === null );
check( '...every value the form was holding', JSON.stringify( [ elements._globalKeys, elements._localeKeys, elements._globalValues, elements._localeValues, elements._dirtyLocales, elements._raw ] ) === '[[],[],{},{},[],{}]' );
check( '...and the leftovers that would outlive it', elements._referenceOptions !== undefined && Object.keys( elements._referenceOptions ).length === 0 && elements._pendingUri === undefined );
// A types request in flight would otherwise land after this and mark the
// module ready again, with the list it fetched before the type was deleted
check( 'a request still in flight is invalidated with it', elements._typesRequest === typesRequestBefore + 1 && elements._loading === false );

// Every drill-down level that writes an error into its pane has to show that
// pane too - the list one runs while the type picker is still the visible
// level, so without it a failed elements/list is a click that does nothing
const errorPaths = source.split( '_showError(' ).length - 1;
check( 'every error path shows the pane it writes into', errorPaths === 3
	&& /_showError\( dc\.getElementById\('elements-list'\)[\s\S]{0,120}_showList\(\)/.test( source )
	&& /_showError\( dc\.getElementById\('elements-form'\)[\s\S]{0,120}_showFormView\(\)/.test( source ) );

/*	Opening the panel asks the server for the type list once. It asked twice:
	_refreshTypes() stands down while a types request is in flight ("refetching
	there would just repeat the request it is already inside of", says its own
	comment), and init()'s callback cleared both halves of that guard - _loading
	and _ready - before calling _showTypes(), which is what reaches
	_refreshTypes(). So the list the callback had just rendered was fetched
	again, on every single load of the panel.

	Its own context, and a document with the three drill-down elements in it:
	the sandbox above is deliberately DOM-free, and init() returns at its first
	getElementById()	*/
function countTypesRequests() {

	const classes = () => { const held = {}; return { add : k => held[k] = true, remove : k => delete held[k], contains : k => held[k] === true } };
	const el = () => ( { classList : classes(), dataset : {}, style : {}, innerHTML : '', textContent : '',
		appendChild(){}, addEventListener(){}, setAttribute(){}, removeAttribute(){}, querySelectorAll : () => [] } );
	const nodes = { 'elements-types' : el(), 'elements-list' : el(), 'elements-form' : el() };
	const asked = [];

	const box = {
		console : console,
		document : { getElementById : id => nodes[id] || null, createElement : el, querySelectorAll : () => [], documentElement : el(), body : el() },
	};
	box.window = box;
	box.Nino = {
		editor : {},
		events : { bindCallback(){} },
		// Answered straight away: what matters here is the order init()'s own
		// callback does things in, not that a real request takes a moment
		http : { sendRequest : ( uri, method, callback, data ) => {
			asked.push( data.action );
			callback( { status : 200, responseJSON : { types : [], locales : [ 'de_DE' ], selectedLocale : 'de_DE' } } );
		} },
		content : { getText : key => key },
		admin : { router : { set(){} }, sessionLocale : { init(){} } },
		adminUi : { text : value => value, emptyState : el, listActions : el },
	};

	const box_context = vm.createContext( box );
	vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), box_context, { filename : 'Nino.admin.js' } );
	vm.runInContext( source, box_context, { filename : 'elements.js' } );

	const module = box.Nino.admin.elements;
	// Rendering is not what is being counted, and _restoreFromHash() would
	// drill into a level this context has no markup for
	module._renderTypes = function(){};
	module._restoreFromHash = function(){ return false };

	module.showCurrent();
	const opening = asked.slice();
	asked.length = 0;
	module.showCurrent();

	return { opening : opening, returning : asked.slice() };
}

const typesRequests = countTypesRequests();
check( 'opening the panel asks for the type list once, not twice', JSON.stringify( typesRequests.opening ) === '["elements/types"]' );
// _refreshTypes()'s actual job: the count beside a type is content, so coming
// back to the overview after adding an element has to re-read it
check( '...and coming back to the overview re-reads it, which is what that second request was for', JSON.stringify( typesRequests.returning ) === '["elements/types"]' );

// --- inputsize: a model field's rows reach its input ----------------------
//
// A string field may say how many rows its input opens with. The textarea
// takes it as its rows; a rich-text field hands it to the html editor, which
// sizes its area from it. Nothing else changes: a size is a hint for the
// form, not a limit on the value.
//
// A dom of plain objects that records what was appended, so a rendered
// control can be found again - the sandbox above has none

function domTree() {
	const classes = () => { const held = {}; return { add : k => held[k] = true, remove : k => delete held[k], toggle : ( k, on ) => { if( on ) held[k] = true; else delete held[k] }, contains : k => held[k] === true } };
	const make = tag => ( { tagName : tag, children : [], parentNode : null, listeners : {}, classList : classes(), dataset : {}, style : {}, attrs : {},
		appendChild( c ) { c.parentNode = this; this.children.push( c ); return c },
		replaceWith( c ) { const at = this.parentNode.children.indexOf( this ); c.parentNode = this.parentNode; this.parentNode.children.splice( at, 1, c ) },
		addEventListener( type, fn ) { this.listeners[type] = fn }, setAttribute( k, v ) { this.attrs[k] = v }, removeAttribute(){}, closest : () => null,
		querySelectorAll( selector ) { return find( this, byTag( selector ) ) }, querySelector( selector ) { return find( this, byTag( selector ) )[0] ?? null },
		get innerHTML() { return '' }, set innerHTML( v ) { this.children = [] } } );
	const byTag = selector => {
		if( selector === '.admin-element-nav button' )
			return n => n.tagName === 'button' && n.parentNode !== null && n.parentNode.className === 'admin-element-nav';
		if( selector === '.admin-element-nav' )
			return n => n.className === 'admin-element-nav';
		const tags = selector.split(',').map( s => s.trim() ).filter( s => /^[a-z]+$/.test( s ) );
		return n => tags.indexOf( n.tagName ) !== -1;
	};
	const find = ( node, test, out = [] ) => { ( node.children || [] ).forEach( c => { if( test( c ) ) out.push( c ); find( c, test, out ) } ); return out };
	return { make, find };
}

const dom = domTree();
sandbox.document.createElement = dom.make;
let editorRows = null;
sandbox.Nino.admin.htmlEditor = { create : function( mount, value, maxlength, rows ) { editorRows = rows; return { getValue : () => value, setValue(){}, destroy(){} } } };
elements._currentType = 'services';
elements._rights = {};
elements._htmlEditors = {};

const sized = dom.find( elements._renderFieldControl( 'body', { type : 'string', inputsize : 8 }, 'text' ), n => n.tagName === 'textarea' );
check( 'a string field opens with the rows its model asks for', sized.length === 1 && sized[0].rows === 8 );
const unsized = dom.find( elements._renderFieldControl( 'body', { type : 'string' }, '' ), n => n.tagName === 'textarea' );
check( '...and without one the textarea keeps the stylesheet\'s height', unsized.length === 1 && unsized[0].rows === undefined );
elements._renderFieldControl( 'body', { type : 'string', html : true, inputsize : 6 }, '' );
check( 'a rich-text field hands its rows to the html editor', editorRows === 6 );
elements._renderFieldControl( 'body', { type : 'string', html : true }, '' );
check( '...and 0 without one', editorRows === 0 );


// --- previous/next: the form steps through the list's order ---------------
//
// The list the server sends is the order; the open element's neighbours are
// the entries before and after it. The form renders one button each into its
// context bar, disabled at either end, and a save in flight disables both
// without handing the unreachable one back afterwards.

check( 'the form knows its neighbours', typeof elements._neighbours === 'function' && typeof elements._renderNav === 'function' );

if( typeof elements._renderNav === 'function' ) {

	elements._elements = [ { uri : 'ada', label : 'Ada' }, { uri : 'bob', label : 'Bob' }, { uri : 'cy', label : 'Cy' } ];
	elements._isNew = false;
	elements._currentUri = 'bob';
	check( 'the middle element has both neighbours, in the list\'s order', JSON.stringify( elements._neighbours() ) === '{"prev":"ada","next":"cy"}' );
	elements._currentUri = 'ada';
	check( 'the first has no previous', JSON.stringify( elements._neighbours() ) === '{"prev":null,"next":"bob"}' );
	elements._currentUri = 'cy';
	check( 'the last has no next', JSON.stringify( elements._neighbours() ) === '{"prev":"bob","next":null}' );
	elements._currentUri = 'zed';
	check( 'an element the list does not hold has none', JSON.stringify( elements._neighbours() ) === '{"prev":null,"next":null}' );
	elements._currentUri = 'bob';
	elements._isNew = true;
	check( '...nor has a new element', JSON.stringify( elements._neighbours() ) === '{"prev":null,"next":null}' );
	elements._isNew = false;

	const opened = [];
	elements._openForm = function( uri ) { opened.push( uri ) };
	const nav = elements._renderNav();
	const buttons = dom.find( nav, n => n.tagName === 'button' );
	check( 'the form gets a previous and a next button', nav.className === 'admin-element-nav' && buttons.map( b => b.dataset.nav ).join() === 'prev,next' );
	check( '...each pointing at its neighbour', buttons[0].dataset.uri === 'ada' && buttons[1].dataset.uri === 'cy' && buttons.every( b => b.disabled === false ) );
	elements._currentUri = 'cy';
	const atEnd = dom.find( elements._renderNav(), n => n.tagName === 'button' );
	check( 'at the end, next is disabled and points nowhere', atEnd[1].disabled === true && atEnd[1].dataset.uri === '' && atEnd[0].disabled === false );
	buttons[1].listeners.click();
	atEnd[1].listeners.click();
	check( 'clicking next opens that element, and a button at the end opens nothing', JSON.stringify( opened ) === '["cy"]' );

	// Into the form's context bar, after the back link (and the locale
	// switch, when the type has one) - the shell's toolbar stands in
	const formNode = dom.make('div');
	sandbox.document.getElementById = id => id === 'elements-form' ? formNode : null;
	sandbox.Nino.admin.formToolbar = backLink => { const bar = dom.make('div'); bar.className = 'nino-admin-contextbar'; bar.appendChild( backLink ); return bar };
	elements._numbered = {};
	elements._raw = {};
	elements._globalKeys = [];
	elements._localeKeys = [];
	elements._currentTypeTitle = 'Services';
	elements._currentUri = 'bob';
	elements._renderForm();
	const bar = formNode.children.find( n => n.className === 'nino-admin-contextbar' );
	check( 'the form renders them into its context bar, at its end', bar !== undefined && bar.children.length === 2 && bar.children[1].className === 'admin-element-nav' );
	elements._isNew = true;
	elements._renderForm();
	const barNew = formNode.children.find( n => n.className === 'nino-admin-contextbar' );
	check( 'a new element, which the list does not hold yet, gets none', barNew !== undefined && barNew.children.length === 1 );
	elements._isNew = false;

	// The list comes back after every save; a form open on one of its
	// elements takes the fresh order without being rendered again
	elements._currentUri = 'cy';
	elements._renderForm();
	const listNode = dom.make('div');
	sandbox.document.getElementById = id => ( { 'elements-form' : formNode, 'elements-list' : listNode } )[id] ?? null;
	sandbox.Nino.adminUi.listActions = () => dom.make('div');
	// The list notes the workbench's content locale, which the shell owns
	sandbox.Nino.admin.sessionLocale = { current : 'de_DE', set(){}, init(){} };
	elements._renderList( [ { uri : 'cy', label : 'Cy' }, { uri : 'dee', label : 'Dee' } ] );
	const refreshed = formNode.querySelectorAll('.admin-element-nav button');
	check( 'a fresh list re-points the open form\'s buttons', refreshed.length === 2 && refreshed[0].disabled === true && refreshed[1].dataset.uri === 'dee' );

	// A save in flight disables both; afterwards only the reachable one
	// comes back
	elements._setFormPending( true );
	check( 'a save in flight disables both', refreshed.every( b => b.disabled === true ) );
	elements._setFormPending( false );
	check( '...and afterwards the one at the end stays disabled, the other comes back', refreshed[0].disabled === true && refreshed[1].disabled === false );
}



// --- the list is a table ---------------------------------------------------
//
// elements/list answers the fields a cell can show (columns) and every
// element's values for one translation; the panel draws the shared table
// from it: the uri first, under a key no model field can carry, then one
// column per field, and a row opens the form. A type none of whose fields
// fits a cell keeps the plain list of labels.

{
	const listNode = dom.make('div');
	sandbox.document.getElementById = id => id === 'elements-list' ? listNode : null;
	sandbox.Nino.adminUi.listActions = () => dom.make('div');
	sandbox.Nino.admin.formToolbar = backLink => dom.make('div');
	const opened = [];
	elements._openForm = function( uri ) { opened.push( uri ) };
	elements._currentType = 'services';
	elements._currentTypeTitle = 'Services';
	elements._currentModel = { title : { type : 'string', locale : true }, body : { type : 'string', html : true }, price : { type : 'double' }, uri : { type : 'string' } };
	elements._rights = {};

	elements._renderList( [
		{ uri : 'ada', label : 'Ada', values : { title : 'Ada', price : 9.5, uri : 'a field called uri' } },
		{ uri : 'bob', label : 'Bob', values : { title : null, price : 3, uri : '' } },
	], [ 'title', 'price', 'uri' ] );

	const table = dom.find( listNode, n => n.tagName === 'table' )[0];
	const heads = table ? dom.find( table, n => n.tagName === 'th' ).map( th => th.dataset.key ) : [];
	check( 'the list is the shared table, the uri first and then the columns the server named', table !== undefined && table.className === 'nino-admin-table' && heads.join() === '.uri,title,price,uri' );
	const rows = table ? dom.find( table, n => n.tagName === 'tr' && n.parentNode.tagName === 'tbody' ) : [];
	const cells = rows.map( tr => dom.find( tr, n => n.tagName === 'td' ).map( td => td.textContent ) );
	check( 'one row per element, the cells its values - a field called uri stays a field', JSON.stringify( cells ) === '[["ada","Ada","9.5","a field called uri"],["bob","","3",""]]' );
	check( 'the plain list is not drawn beside it', dom.find( listNode, n => n.tagName === 'ul' ).length === 0 );
	if( rows[1] !== undefined )
		rows[1].listeners.click();
	check( 'a row opens its element', JSON.stringify( opened ) === '["bob"]' );
	check( 'the list remembers the translation it shows', elements._listLocale === 'de_DE' );

	// No column: the plain list
	elements._renderList( [ { uri : 'ada', label : 'Ada', values : {} } ], [] );
	check( 'a type with no field a cell can show keeps the plain list', dom.find( listNode, n => n.tagName === 'table' ).length === 0 && dom.find( listNode, n => n.tagName === 'ul' ).length === 1 );
}

// --- an upload is checked against the server's limits before it is sent --------
//
// The shell writes the limits onto its wrapper (see Admin::handleGet()); the
// control names them before a file is chosen, and a file that cannot work is
// refused in the server's own words without ever being sent

{
	const wrap = { dataset : { uploadBytes : '2097152', uploadPixels : '20000000' } };
	const texts = {
		'/_admin/error/image_too_large' : 'The image is larger than %s MB.',
		'/_admin/error/upload_too_large' : 'The file is larger than this server accepts (PHP upload limit: %s MB).',
		'/_admin/common/hint/upload' : 'Up to %s MB and %s megapixels.',
		'/_admin/elements/msg/pending' : 'Saving',
		'/_admin/elements/msg/saved' : 'Saved.',
	};
	sandbox.document.getElementById = id => id === 'admin-page-wrap' ? wrap : null;
	sandbox.document.createElement = dom.make;
	sandbox.Nino.content = { getText : key => texts[key] || '' };

	const sent = [];
	elements._apiCall = function( endpoint, payload, callback, extra ) { sent.push( { endpoint : endpoint, extra : extra, callback : callback } ) };
	elements._currentType = 'services';
	elements._currentUri = 'one';
	elements._selectedLocale = 'de_DE';

	const msg = { className : '', textContent : '' };
	const fileInput = { disabled : false, value : 'C:\\fakepath\\big.jpg' };
	const hidden = { value : '' };
	const preview = { src : '', hidden : true };

	elements._uploadImage( 'photo', { size : 3 * 1048576 }, hidden, preview, msg, fileInput );
	check( 'a file above the limit is refused before it is sent, in the server\'s own words', sent.length === 0 && msg.textContent === 'The image is larger than 2 MB.' );
	check( '...the message is styled as an error and the control is free to choose another file', msg.className === 'nino-admin-field-image-msg is-error' && fileInput.disabled === false && fileInput.value === '' );

	elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput );
	check( 'a file within it goes to the server as before, with the file as the extra field', sent.length === 1 && sent[0].endpoint === 'uploadimage' && sent[0].extra.file.size === 1000 && msg.textContent === 'Saving' );
	sent[0].callback( 200, { filename : 'a.jpg', url : '/images/a.jpg' } );
	check( '...and the answer lands in the field and the preview', hidden.value === 'a.jpg' && preview.src === '/images/a.jpg' && msg.textContent === 'Saved.' );

	elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput );
	sent[1].callback( 413, { error : 'the file is larger than the server accepts', code : 'upload_too_large', params : [ 2 ] } );
	check( 'a failure the server worded is shown as an error, in those words and without the status number', msg.className === 'nino-admin-field-image-msg is-error' && msg.textContent === 'The file is larger than this server accepts (PHP upload limit: 2 MB).' );

	// The check waits for the picture to decode; the person may step to
	// another element or language meanwhile, and the file still belongs to
	// the one it was chosen on
	{
		let decoded = null;
		// A deferred decode the test settles by hand, so it needs no event loop
		sandbox.createImageBitmap = function() { return { then : function( resolve ) { decoded = resolve } } };
		const before = sent.length;
		elements._currentType = 'services';
		elements._currentUri = 'one';
		elements._selectedLocale = 'de_DE';
		elements._apiCall = function( endpoint, payload, callback, extra ) { sent.push( { endpoint : endpoint, payload : payload, extra : extra, callback : callback } ) };

		elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput );
		check( 'while the picture is decoded nothing is sent yet', sent.length === before && decoded !== null );

		elements._currentUri = 'two';
		elements._selectedLocale = 'en_US';
		decoded( { width : 100, height : 100 } );

		check( 'a file chosen on one element and language is sent for that one, not for where the person is by then',
			sent.length === before + 1 && sent[before].payload.type === 'services' && sent[before].payload.uri === 'one' && sent[before].payload.locale === 'de_DE' && sent[before].payload.key === 'photo' );

		delete sandbox.createImageBitmap;
	}

	const hint = sandbox.Nino.adminUi.uploadHint();
	check( 'the control carries the limits as permanent text', hint !== null && hint.textContent === 'Up to 2 MB and 20 megapixels.' );
	check( 'both upload controls draw that hint under the file input', source.indexOf( 'Nino.adminUi.uploadHint()' ) !== -1 && fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Images/assets/admin.js' ), 'utf8' ).indexOf( 'Nino.adminUi.uploadHint()' ) !== -1 );
}

// --- the form's status line: saved at, unsaved ------------------------------

{
	const line = dom.make('p');
	const form = dom.make('form');
	const status = sandbox.Nino.adminUi.status( line );
	status.bind( form );
	sandbox.Nino.content = { getText : key => ( { '/_admin/common/msg/savedat' : 'Saved at %s.', '/_admin/common/msg/dirty' : 'Unsaved changes' } )[key] || '' };
	status.saved( new Date( 2026, 8, 3, 7, 5 ) );
	check( 'a saved form says when', line.dataset.state === 'saved' && line.textContent === 'Saved at 07:05.' );
	form.listeners.input();
	check( 'and turns to "unsaved changes" with the first thing typed', line.dataset.state === 'dirty' && line.textContent === 'Unsaved changes' );
}

check( 'the element form says whether it is saved through the shared status line, bound to the form', /_status = Nino\.adminUi\.status\( msg \);\s*Nino\.admin\.elements\._status\.bind\( form \)/.test( source ) === true );
check( '...a save marks it saving, saved and - with the server\'s words and field - failed', source.indexOf( '_status.saving()' ) !== -1 && source.indexOf( '_status.saved()' ) !== -1 && /_status\.error\( status, response, '\/_admin\/elements\/error\/save' \)/.test( source ) === true );
check( '...and the old plain message element no longer carries its own text', source.indexOf( "elements-form-msg')" ) === -1 );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
