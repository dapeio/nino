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
// it: the panel's admin.js asks Nino.adminUi whether a model field is a multi element
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
// appeared once the form was re-rendered from the stored value.
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
// would make the element impossible to create at all - the same rule the
// kernel's insertElement() and the Element Types tab apply (see
// tests/kernel-smoke.php and tests/admin-system-smoke.php)
sandbox.document.getElementById = function() { return null };
sandbox.Nino.content = { getText : function() { return '' } };

elements._currentType 	= 'service';
elements._globalKeys 		= [ 'photo' ];
elements._localeKeys 		= [ 'title' ];
elements._currentModel	= {
	photo : { type : 'image', required : true },
	title : { type : 'string', required : true },
};

/** What _validate() holds the save back for, as key@locale (locale empty for the uri and the global fields) and its kind */
function problems() {
	return elements._validate().map( function( problem ) { return problem.key+ '@'+ ( problem.locale ?? '' )+ ':'+ problem.kind } );
}

check( 'a required image field is never reported as missing', problems().some( function( problem ) { return problem.indexOf('photo') === 0 } ) === false );
check( '...while an empty required field of any other type still is', JSON.stringify( problems() ) === JSON.stringify( [ 'title@en_US:required' ] ) );

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
	JSON.stringify( problems() ) === JSON.stringify( [ 'title@de_DE:required' ] ) );

elements._localeValues.de_DE.tags = [];
check( '...and a required list is empty the same way a required text is',
	JSON.stringify( problems() ) === JSON.stringify( [ 'title@de_DE:required', 'tags@de_DE:required' ] ) );

// The kernel counts what is in a list or an object (count() === 0), so an
// object with entries satisfies a required field, and an empty one does not
elements._localeValues.de_DE.tags = { a : 1 };
check( '...a required object with an entry in it is not empty', JSON.stringify( problems() ) === JSON.stringify( [ 'title@de_DE:required' ] ) );
elements._localeValues.de_DE.tags = {};
check( '...and an empty object is', JSON.stringify( problems() ) === JSON.stringify( [ 'title@de_DE:required', 'tags@de_DE:required' ] ) );

elements._localeValues.de_DE = { title : 'Reinigung', tags : [ 'y' ] };
check( 'a translation that has everything it needs does not hold anything back',
	JSON.stringify( problems() ) === JSON.stringify( [] ) );

// A translation nobody edited is not submitted, so it is not this save's
// business either - an element may perfectly well have a language it has no
// text for yet
elements._dirtyLocales = [ 'en_US' ];
elements._localeValues.fr_FR = { title : '', tags : [] };
check( 'an untouched translation is not checked, because it is not written',
	JSON.stringify( problems() ) === JSON.stringify( [] ) );

// The locale on screen is still read from its controls, and still named
// without a language nobody needs to be told about
elements._dirtyLocales = [ 'en_US' ];
visibleFields.title.value = '   ';
check( 'the visible locale is still read from the form itself',
	JSON.stringify( problems() ) === JSON.stringify( [ 'title@en_US:required' ] ) );

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
	/_isNew === true && Nino\.admin\.elements\._isNumbered\(\) === false && uriInput !== null && uriInput\.value\.trim\(\) === ''/.test( source ) === true );
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
		admin : { router : { set(){}, go(){}, current : () => ( { panel : '', parts : [] } ), leave : ( names, leaving, proceed ) => proceed() }, sessionLocale : { init(){} } },
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

/*	The browser's Back and Forward. A step through the history changes the
	address and nothing else, so showCurrent() shows the level the hash names:
	the picker, a type's list or one element's form. The levels themselves are
	stubbed out here - what is checked is which one is asked for, whether the
	shell is asked to put a question in front of leaving a form, and what a
	person's own move writes into the history	*/
{
	const classes = hidden => { const held = { 'admin-hidden' : hidden }; return { add : k => held[k] = true, remove : k => held[k] = false, contains : k => held[k] === true } };
	const nodes = { 'elements-types' : { classList : classes( false ) }, 'elements-list' : { classList : classes( true ) }, 'elements-form' : { classList : classes( true ) } };
	const calls = [];
	const hash = { panel : 'elements', parts : [] };
	const asked = [];

	const box = { console : console, document : { getElementById : id => nodes[id] || null, querySelectorAll : () => [], documentElement : {}, body : {} } };
	box.window = box;
	box.Nino = {
		editor : {},
		events : { bindCallback(){} },
		content : { getText : key => key },
		admin : {
			router : {
				set : ( panel, parts ) => calls.push( 'set '+ [ panel ].concat( parts ).join('/') ),
				go : ( panel, parts ) => calls.push( 'go '+ [ panel ].concat( parts ).join('/') ),
				current : () => hash,
				leave : ( names, leaving, proceed, resync ) => { asked.push( { names : names, leaving : leaving, resync : resync } ); proceed() },
			},
			sessionLocale : { current : 'de_DE', init(){} },
		},
		adminUi : {},
	};

	const context = vm.createContext( box );
	vm.runInContext( fs.readFileSync( path.join( __dirname, '../_admin/assets/Nino.admin.js' ), 'utf8' ), context, { filename : 'Nino.admin.js' } );
	vm.runInContext( source, context, { filename : 'elements.js' } );

	const module = box.Nino.admin.elements;
	const teamModel = { title : { type : 'string' } };
	const levels = { form : 'elements-form', list : 'elements-list', types : 'elements-types' };

	// Levels are stubbed, the picker's and the form's markup is not what is checked
	const realOpenForm = module._openForm;
	module._showTypes = () => calls.push( 'types' );
	module._showList = () => calls.push( 'list' );
	module._showFormView = () => calls.push( 'form' );
	module._openForm = uri => calls.push( 'open '+ uri );
	module._refreshList = () => calls.push( 'refresh' );
	module._destroyHtmlEditors = () => calls.push( 'destroy' );
	module._selectType = ( type, model, title ) => calls.push( 'select '+ type+ ' '+ title+ ' '+ module._pendingUri );

	// Which level is on screen, as the panes say it
	const on = ( level, type, uri ) => {
		Object.keys( levels ).forEach( name => nodes[levels[name]].classList[ name === level ? 'remove' : 'add' ]( 'admin-hidden' ) );
		module._currentType = type ?? null;
		module._isNew = uri === 'new';
		module._currentUri = uri === 'new' ? null : ( uri ?? null );
	};
	const at = ( ...parts ) => { hash.panel = 'elements'; hash.parts = parts; calls.length = 0; asked.length = 0; module._pendingUri = undefined; module.showCurrent() };

	module._ready = true;
	module._types = [ { type : 'team', title : 'Team', model : teamModel }, { type : 'post', title : 'Post', model : {} } ];
	module._listLocale = 'de_DE';

	on( 'types' );
	at( 'team' );
	check( 'a hash that names a type opens it from the picker: the list is loaded the way a reload loads it', calls.join() === 'select team Team undefined' && asked.length === 1 && asked[0].leaving === false );

	on( 'types' );
	at( 'team', 'ada' );
	check( '...one that names an element opens that element once the list is there', calls.join() === 'select team Team ada' );
	on( 'types' );
	at( 'team', 'new' );
	check( '...and a blank form for \'new\'', calls.join() === 'select team Team new' );

	on( 'list', 'team' );
	at( 'team', 'ada' );
	check( 'in the type that is open, an element is opened at once', calls.join() === 'open ada' && asked[0].leaving === false );
	at( 'team', 'new' );
	check( '...\'new\' is a blank form', calls.join() === 'open null' );
	at( 'post' );
	check( 'another type is selected', calls.join() === 'select post Post undefined' );
	at();
	check( 'the bare panel is the picker, torn down like the list\'s back link', calls.join() === 'destroy,types' );

	on( 'form', 'team', 'ada' );
	at( 'team' );
	check( 'Back from a form to its list asks first - leaving a form is what the shell guards - and then shows the list', asked.length === 1 && asked[0].leaving === true && JSON.stringify( asked[0].names ) === '["elements"]' && typeof asked[0].resync === 'function' && calls.join() === 'destroy,list' );
	module._listLocale = 'en_US';
	at( 'team' );
	check( '...and reads the list again when the form changed the content locale, as the back link does', calls.join() === 'destroy,list,refresh' );
	module._listLocale = 'de_DE';
	at();
	check( 'Back out of the form to the picker leaves it too', asked[0].leaving === true && calls.join() === 'destroy,types' );
	at( 'team', 'bob' );
	check( 'another element of the same type is opened in place of this one, asked first', asked[0].leaving === true && calls.join() === 'open bob' );
	at( 'post', 'x' );
	check( 'an element of another type goes through that type\'s list', asked[0].leaving === true && calls.join() === 'select post Post x' );

	on( 'form', 'team', 'new' );
	at( 'team', 'new' );
	check( 'the level on screen needs no move: it falls to the panel\'s own re-show, which writes the address', calls.length === 1 && calls[0] === 'form' && asked.length === 0 );
	on( 'list', 'team' );
	at( 'team' );
	check( '...the list too', calls.join() === 'list' );
	on( 'types' );
	at();
	check( '...and the picker', calls.join() === 'types' );

	on( 'form', 'team', 'ada' );
	at( 'nope' );
	check( 'a type the picker does not know is the picker', calls.join() === 'destroy,types' );
	on( 'types' );
	at( 'nope', 'x' );
	check( '...and when that is on screen already it is only written back', calls.join() === 'types' );

	on( 'form', 'team', 'ada' );
	module._saving = true;
	at();
	check( 'while a save runs the screen stays where it is', asked.length === 0 && calls.join() === 'form' );
	let refused = 0;
	box.Nino.admin.router.refuse = () => refused++;
	at();
	check( '...and the step through the history that came here is refused to the shell, which takes it back', refused === 1 && asked.length === 0 );
	delete box.Nino.admin.router.refuse;
	module._saving = false;

	on( 'form', 'team', 'ada' );
	hash.panel = 'images';
	calls.length = 0;
	module.showCurrent();
	check( 'a hash that names another panel (a click on the rail) keeps the level in memory and writes it', calls.join() === 'form' && asked.length === 0 );

	// The moves a person makes
	module._openForm = uri => calls.push( 'open '+ uri );
	on( 'list', 'team' );
	calls.length = 0;
	module._visit( 'ada' );
	check( 'opening an element from the list is a step Back returns to, pushed before the form writes the address', calls.join() === 'go elements/team/ada,open ada' );
	calls.length = 0;
	module._visit( null );
	check( '...a new one too', calls.join() === 'go elements/team/new,open null' );
	module._saving = true;
	calls.length = 0;
	module._visit( 'ada' );
	check( '...but not while a save runs', calls.length === 0 );
	module._saving = false;

	/*	An answer that comes late. An element is opened from the list and the
		person steps Back before elements/get has answered: the address names
		the list, so the form must not open when the answer arrives	*/
	module._openForm = realOpenForm;
	const pending = [];
	module._apiCall = ( endpoint, payload, callback ) => pending.push( { endpoint : endpoint, payload : payload, callback : callback } );
	module._loadReferenceOptions = proceed => proceed();
	module._renderForm = () => calls.push( 'render' );
	module._remember = () => {};
	module._resetEdits = () => {};
	const answered = { global : { title : 'Ada' }, locales : { de_DE : { title : 'Ada' } }, raw : {} };

	on( 'list', 'team' );
	calls.length = 0;
	module._visit( 'bob' );
	pending[pending.length - 1].callback( 200, answered );
	check( 'an element opened from the list is drawn when its answer arrives', pending[pending.length - 1].payload.uri === 'bob' && calls.join() === 'go elements/team/bob,render,form' );

	on( 'list', 'team' );
	calls.length = 0;
	module._visit( 'ada' );
	at( 'team' );
	pending[pending.length - 1].callback( 200, answered );
	check( 'Back before it answered keeps the list: the late answer opens no form', calls.join() === 'list' );

	// ...the same for the picker, where the list of a type may still be loading
	on( 'types' );
	module._listRequest = 5;
	module._formRequest = 5;
	at();
	check( 'Back to the picker retires a list and a form still on their way', module._listRequest === 6 && module._formRequest === 6 && calls.join() === 'types' );

	// ...and a move that opens something takes its own id, so its answer is the only one that counts
	on( 'list', 'team' );
	module._formRequest = 10;
	at( 'team', 'ada' );
	check( 'a move to a form takes one id of its own, and the request it sends is the one that counts', module._formRequest === 11 && pending[pending.length - 1].payload.uri === 'ada' );
	pending[pending.length - 1].callback( 200, answered );
	check( '...and its answer draws the form', calls.join() === 'render,form' );
	on( 'form', 'team', 'ada' );
	module._formRequest = 20;
	at( 'team' );
	check( 'leaving a form for its list retires a form that was loading behind it', module._formRequest === 21 && calls.join() === 'destroy,list' );
}

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
		get firstChild() { return this.children[0] ?? null },
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
let editorFormat = null;
sandbox.Nino.admin.htmlEditor = { create : function( mount, value, maxlength, rows, format ) { editorRows = rows; editorFormat = format; return { getValue : () => value, setValue(){}, destroy(){} } } };
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

// --- blocks: a rich-text field may allow paragraphs and lists ----------------

elements._renderFieldControl( 'body', { type : 'string', html : true, blocks : true }, '' );
check( 'a rich-text field that allows paragraphs and lists hands the editor the format blocks', editorFormat === 'blocks' );
elements._renderFieldControl( 'body', { type : 'string', html : true }, '' );
check( '...one that does not, inline', editorFormat === 'inline' );
elements._renderFieldControl( 'body', { type : 'string', html : false, blocks : true }, '' );
check( 'a flag on a field that is not rich text builds no editor at all', editorFormat === 'inline' && dom.find( elements._renderFieldControl( 'body', { type : 'string', blocks : true }, 'x' ), n => n.tagName === 'textarea' ).length === 1 );


// --- an array of texts is a list of rows -------------------------------------
//
// The value stays what the form reads, compares and saves: a hidden input that
// carries the JSON, exactly as stored until a row is changed

const listControl = ( value, rawText ) => elements._renderFieldControl( 'tags', { type : 'array' }, value, rawText );
const hiddenOf = control => dom.find( control, n => n.type === 'hidden' )[0];
const isList = control => dom.find( control, n => n.className === 'nino-admin-stringlist-rows' ).length === 1;

const tagList = listControl( [ 'php', 'css' ] );
check( 'an array field holding texts is edited as rows, and no textarea is drawn', isList( tagList ) === true && dom.find( tagList, n => n.tagName === 'textarea' ).length === 0 );
check( '...its hidden input is the field: data-field, the array type and the stored json untouched', hiddenOf( tagList ).dataset.field === 'tags' && hiddenOf( tagList ).dataset.type === 'array' && hiddenOf( tagList ).value === '["php","css"]' );
check( '...and the form reads it back as the array it was, without any row being edited', JSON.stringify( elements._readField( hiddenOf( tagList ) ) ) === '["php","css"]' && elements._fieldValuesEqual( { type : 'array' }, [ 'php', 'css' ], elements._readField( hiddenOf( tagList ) ) ) === true );
check( 'a value that is not there yet is an empty list of rows, and an empty array in the form', isList( listControl( undefined ) ) === true && hiddenOf( listControl( undefined ) ).value === '[]' && isList( listControl( [] ) ) === true );
check( '...so an untouched empty field is not an edited one', elements._fieldValuesEqual( { type : 'array' }, undefined, elements._readField( hiddenOf( listControl( undefined ) ) ) ) === true );

check( 'a list that holds anything but texts keeps the JSON field', [ [ 'a', 1 ], [ 'a', [ 'b' ] ], [ { name : 'x' } ], { a : 'b' }, 'text', 3 ].every( function( value ) { return isList( listControl( value ) ) === false && dom.find( listControl( value ), n => n.tagName === 'textarea' ).length === 1 } ) );
check( '...and so does a text that was typed and is not JSON, which is what it has to be shown as', isList( listControl( [ 'a' ], '{broken' ) ) === false && dom.find( listControl( [ 'a' ], '{broken' ), n => n.tagName === 'textarea' )[0].value === '{broken' );
check( 'a required list carries its asterisk on the name, as the reference list does', ( function() {
	const field = { type : 'array', required : true };
	elements._currentModel = { tags : field };
	return dom.find( elements._renderFieldControl( 'tags', field, [ 'a' ] ), n => n.className === 'nino-admin-required' ).length === 1;
} )() );


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
	const stepped = [];
	sandbox.Nino.admin.router = { set(){}, go : ( panel, parts ) => stepped.push( panel+ '/'+ parts.join('/') ) };
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
	check( '...as a step Back returns to', stepped.length === 1 && stepped[0] === 'elements/'+ elements._currentType+ '/cy' );

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

	// The note of the type stands in the form too, between the context bar and the fields
	const contentWas = sandbox.Nino.content;
	check( 'a form of a type without a note has no hint paragraph', dom.find( formNode, n => n.className === 'nino-admin-hint' ).length === 0 );
	sandbox.Nino.content = { getText : key => key === '/_admin/elements/type/'+ elements._currentType+ '/hint' ? 'Have it checked.' : '' };
	elements._renderForm();
	const formHints = dom.find( formNode, n => n.className === 'nino-admin-hint' );
	check( 'a form of a type with one shows it, as text, right after the context bar', formHints.length === 1 && formHints[0].textContent === 'Have it checked.'
		&& formNode.children.indexOf( formHints[0] ) === formNode.children.findIndex( n => n.className === 'nino-admin-contextbar' ) + 1 );
	sandbox.Nino.content = contentWas;

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

	// A type that has a note of its own - the legal texts say they are no
	// legal advice - shows it above its elements, as text; one that has none is as it was
	const contentBefore = sandbox.Nino.content;
	const hintsOf = node => dom.find( node, n => n.className === 'nino-admin-hint' );
	check( 'a type without a hint fill has no note', hintsOf( listNode ).length === 0 && elements._typeHint() === null );
	sandbox.Nino.content = { getText : key => key === '/_admin/elements/type/services/hint' ? 'A starting point, not legal advice. <b>Check it.</b>' : '' };
	elements._renderList( [ { uri : 'ada', label : 'Ada', values : { title : 'Ada' } } ], [ 'title' ] );
	const listHints = hintsOf( listNode );
	check( 'a type with the fill shows it above the list, as a hint paragraph whose words are text', listHints.length === 1 && listHints[0].tagName === 'p' && listHints[0].textContent === 'A starting point, not legal advice. <b>Check it.</b>'
		&& dom.find( listNode, n => n.tagName === 'table' ).length === 1 );
	elements._currentType = 'other';
	elements._renderList( [ { uri : 'ada', label : 'Ada', values : { title : 'Ada' } } ], [ 'title' ] );
	check( '...and the note of another type is not shown for this one', hintsOf( listNode ).length === 0 );
	elements._currentType = 'services';
	sandbox.Nino.content = contentBefore;
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
		'/_admin/elements/msg/image-below-target' : 'Saved - only %s, scaled up.',
		'/_admin/elements/confirm/image-remove' : 'Remove from "%s"?',
		'/_admin/elements/msg/image-removed' : 'Removed.',
		'/_admin/elements/error/image-remove' : 'Removing failed.',
	};
	sandbox.document.getElementById = id => id === 'admin-page-wrap' ? wrap : null;
	sandbox.document.createElement = dom.make;
	sandbox.Nino.content = { getText : key => texts[key] || '' };

	const sent = [];
	elements._apiCall = function( endpoint, payload, callback, extra ) { sent.push( { endpoint : endpoint, payload : payload, extra : extra, callback : callback } ) };
	elements._currentType = 'services';
	elements._currentUri = 'one';
	elements._selectedLocale = 'de_DE';

	const msg = { className : '', textContent : '' };
	const fileInput = { disabled : false, value : 'C:\\fakepath\\big.jpg' };
	const hidden = { value : '' };
	const preview = { src : '', hidden : true };
	const removeBtn = { hidden : true, disabled : false };

	elements._uploadImage( 'photo', { size : 3 * 1048576 }, hidden, preview, msg, fileInput, removeBtn );
	check( 'a file above the limit is refused before it is sent, in the server\'s own words', sent.length === 0 && msg.textContent === 'The image is larger than 2 MB.' );
	check( '...the message is styled as an error and the control is free to choose another file', msg.className === 'nino-admin-field-image-msg is-error' && fileInput.disabled === false && fileInput.value === '' );

	elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput, removeBtn );
	check( 'a file within it goes to the server as before, with the file as the extra field', sent.length === 1 && sent[0].endpoint === 'uploadimage' && sent[0].extra.file.size === 1000 && msg.textContent === 'Saving' );
	sent[0].callback( 200, { filename : 'a.jpg', url : '/images/a.jpg' } );
	check( '...and the answer lands in the field and the preview', hidden.value === 'a.jpg' && preview.src === '/images/a.jpg' && msg.textContent === 'Saved.' );
	check( '...and the Remove button is there from then on', removeBtn.hidden === false );

	// A picture below the field's target size is saved and said to be scaled up: in words, with its size, and in the warning's colour
	elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput, removeBtn );
	sent[sent.length - 1].callback( 200, { filename : 'b.jpg', url : '/images/b.jpg', belowTarget : true, source : { width : 120, height : 80 } } );
	check( 'an upload below the target size says so with the size it has, as a warning', msg.className === 'nino-admin-field-image-msg is-warning' && msg.textContent === 'Saved - only 120 \u00d7 80 px, scaled up.' && hidden.value === 'b.jpg' );

	// Remove: asked first, sent for the field, and the field is empty afterwards
	sandbox.window.confirm = () => false;
	const sentBeforeRemove = sent.length;
	elements._removeImage( 'photo', 'Photo', hidden, preview, msg, removeBtn );
	check( 'a cancelled question removes nothing and sends nothing', sent.length === sentBeforeRemove && hidden.value === 'b.jpg' );
	let question = '';
	sandbox.window.confirm = text => { question = text; return true };
	elements._removeImage( 'photo', 'Photo', hidden, preview, msg, removeBtn );
	check( 'the question names the field, and the request names the element, language and field', question === 'Remove from "Photo"?' && sent.length === sentBeforeRemove + 1
		&& sent[sent.length - 1].endpoint === 'removeimage'
		&& JSON.stringify( sent[sent.length - 1].payload ) === JSON.stringify( { type : 'services', uri : 'one', locale : 'de_DE', key : 'photo' } ) );
	sent[sent.length - 1].callback( 500, null );
	check( 'a failed removal is an error and leaves the field as it was', msg.className === 'nino-admin-field-image-msg is-error' && hidden.value === 'b.jpg' && removeBtn.hidden === false && removeBtn.disabled === false );
	elements._removeImage( 'photo', 'Photo', hidden, preview, msg, removeBtn );
	sent[sent.length - 1].callback( 200, { filename : null } );
	check( 'a removal empties the field, hides the preview and the button and says so', hidden.value === '' && preview.hidden === true && removeBtn.hidden === true && msg.textContent === 'Removed.' );

	elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput, removeBtn );
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

		elements._uploadImage( 'photo', { size : 1000 }, hidden, preview, msg, fileInput, removeBtn );
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

check( 'the element form says whether it is saved through the shared status line, bound to the form and to its own word on what is unsaved', /_status = Nino\.adminUi\.status\( msg \);\s*Nino\.admin\.elements\._status\.bind\( form, Nino\.admin\.elements\.isDirty \)/.test( source ) === true );
check( '...a save marks it saving, saved and - with the server\'s words and field - failed', source.indexOf( '_status.saving()' ) !== -1 && source.indexOf( '_status.saved()' ) !== -1 && /_status\.error\( status, response, '\/_admin\/elements\/error\/save' \)/.test( source ) === true );
check( '...and the old plain message element no longer carries its own text', source.indexOf( "elements-form-msg')" ) === -1 );


// --- required fields, JSON fields and unsaved input -----------------------
//
// A dom that can be asked: elements with attributes, a class list, a selector
// matcher and listeners, so the marks, the sentences under the fields and the
// focus can be looked at rather than inferred from the source

/**
 *	A dom of plain objects, just enough for the panel scripts: elements with
 *	children, attributes, a class list, listeners, a selector matcher that
 *	knows tags, #ids, .classes, [attributes], :not([attribute="value"]) and
 *	comma lists, and a document with getElementById over everything attached.
 */
function fakeDom() {

	const matchOne = ( node, selector ) => {
		selector = selector.trim();
		if( selector.endsWith(':checked') === true ) {
			if( node.checked !== true )
				return false;
			selector = selector.slice( 0, -8 );
		}
		const not = /:not\(\[([a-z-]+)="([^"]*)"\]\)/.exec( selector );
		if( not !== null ) {
			if( ( node.attr( not[1] ) ?? '' ) === not[2] )
				return false;
			selector = selector.replace( not[0], '' );
		}
		const parts = selector.match( /(#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]|^[a-z]+)/g ) ?? [];
		return parts.length > 0 && parts.every( part => {
			if( part[0] === '#' ) return node.id === part.slice( 1 );
			if( part[0] === '.' ) return node.classList.contains( part.slice( 1 ) );
			if( part[0] === '[' ) {
				const m = /\[([\w-]+)(?:="([^"]*)")?\]/.exec( part );
				const value = node.attr( m[1] );
				return m[2] === undefined ? value !== null && value !== undefined : String( value ) === m[2];
			}
			return node.tagName === part;
		} );
	};
	const matches = ( node, selector ) => selector.split(',').some( one => matchOne( node, one ) );
	const walk = ( node, out = [] ) => { node.children.forEach( child => { out.push( child ); walk( child, out ) } ); return out };

	const make = tag => {
		let classes = new Set();
		const node = {
			tagName : tag, children : [], parentNode : null, attrs : {}, dataset : {}, listeners : {}, style : {},
			id : '', value : '', checked : false, disabled : false, hidden : false, type : '', textContent : '', focused : false,
			classList : {
				add : ( ...k ) => k.forEach( c => classes.add( c ) ), remove : ( ...k ) => k.forEach( c => classes.delete( c ) ),
				contains : k => classes.has( k ), toggle : ( k, on ) => { if( on === undefined ? ! classes.has( k ) : on ) classes.add( k ); else classes.delete( k ); return classes.has( k ) },
			},
			get className() { return [ ...classes ].join(' ') }, set className( v ) { classes = new Set( String( v ).split( /\s+/ ).filter( Boolean ) ) },
			get firstChild() { return this.children[0] ?? null },
			get innerHTML() { return '' }, set innerHTML( v ) { this.children.forEach( c => c.parentNode = null ); this.children = [] },
			attr( name ) { return name in this.attrs ? this.attrs[name] : ( name === 'type' ? ( this.type || null ) : ( this[name] === undefined || this[name] === '' ? ( name.startsWith('data-') ? this.dataset[name.slice(5)] ?? null : null ) : this[name] ) ) },
			setAttribute( k, v ) { this.attrs[k] = String( v ) }, getAttribute( k ) { return this.attrs[k] ?? null }, removeAttribute( k ) { delete this.attrs[k] },
			appendChild( c ) { if( c.parentNode ) c.remove(); c.parentNode = this; this.children.push( c ); return c },
			insertBefore( c, ref ) { c.parentNode = this; this.children.splice( ref ? this.children.indexOf( ref ) : this.children.length, 0, c ); return c },
			remove() { if( this.parentNode ) this.parentNode.children.splice( this.parentNode.children.indexOf( this ), 1 ); this.parentNode = null },
			after( c ) { if( c.parentNode ) c.remove(); c.parentNode = this.parentNode; this.parentNode.children.splice( this.parentNode.children.indexOf( this ) + 1, 0, c ) },
			replaceWith( c ) { const at = this.parentNode.children.indexOf( this ); c.parentNode = this.parentNode; this.parentNode.children.splice( at, 1, c ); this.parentNode = null },
			addEventListener( type, fn ) { ( this.listeners[type] = this.listeners[type] ?? [] ).push( fn ) },
			fire( type, ev = {} ) { ( this.listeners[type] ?? [] ).forEach( fn => fn( Object.assign( { target : this, preventDefault(){} }, ev ) ) ) },
			click() { this.fire('click') },
			focus() { this.focused = true; doc.activeElement = this },
			querySelectorAll( selector ) { return walk( this ).filter( n => matches( n, selector ) ) },
			querySelector( selector ) { return this.querySelectorAll( selector )[0] ?? null },
			closest( selector ) { for( let n = this; n; n = n.parentNode ) if( matches( n, selector ) ) return n; return null },
		};
		return node;
	};

	const root = make('body');
	const doc = {
		documentElement : make('html'), body : root, activeElement : null,
		createElement : make,
		createTextNode : text => Object.assign( make('#text'), { textContent : String( text ) } ),
		getElementById : id => walk( root ).find( n => n.id === id ) ?? null,
		querySelector : selector => root.querySelector( selector ),
		querySelectorAll : selector => root.querySelectorAll( selector ),
		addEventListener(){}, removeEventListener(){},
	};

	return { make, doc, root, walk, matches };
}


{
	const d = fakeDom();
	Object.assign( sandbox.document, d.doc );
	sandbox.CSS = { escape : value => String( value ) };

	const texts = {
		'/_admin/elements/error/required' : 'REQ', '/_admin/elements/error/field-required' : 'FIELD', '/_admin/elements/error/json' : 'JSON',
		'/_admin/elements/error/uri' : 'URI', '/_admin/elements/error/save' : 'SAVEFAIL', '/_admin/elements/label/locale-open' : '%s - %d open',
		'/_admin/elements/label/uri' : 'Uri', '/_admin/elements/msg/duplicated' : 'COPY',
		'/_admin/common/msg/dirty' : 'Unsaved changes', '/_admin/common/msg/savedat' : 'Saved at %s.', '/_admin/common/msg/saving' : 'Saving',
	};
	sandbox.Nino.content = { getText : key => texts[key] ?? ( key.indexOf('/field/') !== -1 || key.indexOf('/common/word/') !== -1 ? '' : key ) };
	sandbox.Nino.admin.formToolbar = backLink => { const bar = d.make('div'); bar.className = 'nino-admin-contextbar'; bar.appendChild( backLink ); return bar };
	const localeSwitches = [];
	sandbox.Nino.admin.sessionLocale = { current : 'en_US', set : locale => localeSwitches.push( locale ), init(){} };
	sandbox.Nino.admin.publicUrl = path => '/public'+ path;
	sandbox.Nino.admin.router = { set(){}, go(){}, current : () => ( { panel : '', parts : [] } ), leave : ( names, leaving, proceed ) => proceed() };
	const editorMarks = [];
	sandbox.Nino.admin.htmlEditor = { create : ( mount, value ) => {
		const content = d.make('div');
		content.contentEditable = 'true';
		mount.appendChild( content );
		// Reads back as the markup it built, not as the string it was given
		return { getValue : () => content.textContent !== '' ? content.textContent : String( value ?? '' ).replace( '&nbsp;', '\u00a0' ), setValue(){}, destroy(){}, focus(){}, mark : state => editorMarks.push( state ), content : content };
	} };

	const stars = el => el.querySelectorAll('.nino-admin-required');

	// ---- the marks, drawn with the field
	elements._currentType = 'post';
	elements._rights = {};
	elements._isNew = false;
	elements._htmlEditors = {};
	elements._referenceOptions = { tag : [] };
	const draw = ( definition, value ) => elements._renderField( 'f', definition, value ?? '' );

	const text = draw( { type : 'string', required : true } );
	check( 'a required text field carries an asterisk after its name, hidden from a screen reader',
		stars( text ).length === 1 && stars( text )[0].textContent === '*' && stars( text )[0].getAttribute('aria-hidden') === 'true' && stars( text )[0].parentNode.className === 'nino-admin-field-name' );
	check( '...and aria-required on the control itself', text.querySelector('textarea').getAttribute('aria-required') === 'true' );
	check( 'an optional field carries neither', stars( draw( { type : 'string' } ) ).length === 0 && draw( { type : 'string' } ).querySelector('textarea').getAttribute('aria-required') === null );
	check( 'a required number, list and fixed-value field are marked on their control as well',
		draw( { type : 'integer', required : true } ).querySelector('input').getAttribute('aria-required') === 'true'
		&& draw( { type : 'array', required : true } ).querySelector('textarea').getAttribute('aria-required') === 'true'
		&& draw( { type : 'string', required : true, options : [ 'a', 'b' ] } ).querySelector('select').getAttribute('aria-required') === 'true' );
	check( 'a single reference is marked on its select', draw( { type : 'element', elementType : 'tag', required : true } ).querySelector('select').getAttribute('aria-required') === 'true' );
	// An image field that names the field holding its alt text says so, and that field says whose it is
	elements._currentModel = { photo : { type : 'image', width : 40, height : 30, alt : 'caption' }, caption : { type : 'string', locale : true }, plain : { type : 'image', width : 40, height : 30 } };
	texts['/_admin/elements/hint/image-alt'] = 'ALT IN %s';
	texts['/_admin/elements/hint/alt'] = 'ALT OF %s';
	texts['/_admin/elements/label/image-remove'] = 'Remove image';
	const hintsOf = el => el.querySelectorAll('.nino-admin-field-hint').map( h => h.textContent );
	check( 'the image field of a model with an alt link names the field it is written in', hintsOf( elements._renderField( 'photo', elements._currentModel.photo, 'a.jpg' ) ).join() === 'ALT IN Caption' );
	check( '...and the linked field names the image it is the alt text of', hintsOf( elements._renderField( 'caption', elements._currentModel.caption, 'x' ) ).join() === 'ALT OF Photo' );
	check( 'an image without the link, and a field nobody links to, carry no such hint', hintsOf( elements._renderField( 'plain', elements._currentModel.plain, '' ) ).length === 0
		&& hintsOf( elements._renderField( 'other', { type : 'string' }, 'x' ) ).length === 0 );
	const withImage = elements._renderField( 'photo', elements._currentModel.photo, 'a.jpg' );
	const withoutImage = elements._renderField( 'photo', elements._currentModel.photo, '' );
	const removeOf = el => el.querySelectorAll('button').filter( b => b.className === 'nino-admin-btn-danger' )[0];
	check( 'the remove button is drawn with the image field, shown while there is an image and hidden while there is none', removeOf( withImage ).hidden === false && removeOf( withoutImage ).hidden === true
		&& removeOf( withImage ).textContent === 'Remove image' );
	elements._isNew = true;
	check( 'a new element, which has no image to remove, has no button', removeOf( elements._renderField( 'photo', elements._currentModel.photo, '' ) ) === undefined );
	elements._isNew = false;
	elements._currentModel = {};
	check( 'an image and a yes/no choice are never marked: one is uploaded on its own, the other cannot be empty',
		stars( draw( { type : 'image', required : true } ) ).length === 0 && stars( draw( { type : 'boolean', required : true } ) ).length === 0 );

	const list = draw( { type : 'element', elementType : 'tag', multiple : 0, required : true }, [] );
	check( 'a list of references gets the asterisk on its name only - nothing in it could carry aria-required',
		stars( list ).length === 1 && stars( list )[0].parentNode === list.firstChild && list.querySelectorAll('[aria-required]').length === 0 );

	editorMarks.length = 0;
	const rich = draw( { type : 'string', html : true, required : true } );
	check( 'a rich-text field is marked through the editor, which puts it on its text box',
		stars( rich ).length === 1 && JSON.stringify( editorMarks ) === '[{"required":true}]' );
	editorMarks.length = 0;
	draw( { type : 'string', html : true } );
	check( '...and an optional one is not', editorMarks.length === 0 );

	elements._rights = { post : { update : { f : false } } };
	check( 'a field this account may not write is not asked for', stars( draw( { type : 'string', required : true } ) ).length === 0 );
	elements._rights = {};

	// ---- the form of a new element
	const formNode = d.make('div');
	formNode.id = 'elements-form';
	d.root.appendChild( formNode );

	elements._currentModel = {
		slug 	: { type : 'string', required : true },
		tags 	: { type : 'array', required : true },
		ro 		: { type : 'string', required : true },
		flag 	: { type : 'boolean', required : true },
		pic 	: { type : 'image', required : true },
		name 	: { type : 'string', required : true, locale : true },
		body 	: { type : 'string', required : true, locale : true },
		ltags : { type : 'array', locale : true },
	};
	elements._globalKeys = [ 'slug', 'tags', 'ro', 'flag', 'pic' ];
	elements._localeKeys = [ 'name', 'body', 'ltags' ];
	elements._locales = [ 'de_DE', 'en_US' ];
	elements._selectedLocale = 'en_US';
	elements._currentTypeTitle = 'Posts';
	elements._numbered = {};
	elements._raw = {};
	elements._rights = { post : { update : { ro : false } } };
	elements._isNew = true;
	elements._currentUri = null;
	elements._globalValues = {};
	elements._localeValues = {};
	elements._dirtyLocales = [];
	elements._resetEdits();
	elements._renderForm();

	const uriInput = d.doc.getElementById('elements-form-uri');
	check( 'the uri of a new element is marked like any other required field, with no native required attribute to show the browser\'s own bubble',
		uriInput.getAttribute('aria-required') === 'true' && uriInput.required !== true && stars( uriInput.parentNode ).length === 1 );

	const field = key => d.doc.getElementById('elements-form').querySelector('[data-field="'+ key+ '"]');
	const type = ( key, value ) => { field( key ).value = value; d.doc.getElementById('elements-edit-form').fire('input') };
	const keys = () => elements._validate().map( p => p.key+ '@'+ ( p.locale ?? '' )+ ':'+ p.kind );

	elements._dirtyLocales = [ 'de_DE', 'en_US' ];
	elements._localeValues = { de_DE : { name : '', body : 'x' } };
	check( 'the problems come in the order of the form: the uri, the global fields, the translation on screen, then the other translations',
		JSON.stringify( keys() ) === JSON.stringify( [ '.uri@:required', 'slug@:required', 'tags@:required', 'name@en_US:required', 'body@en_US:required', 'name@de_DE:required' ] ) );
	check( '...and a read-only field, a yes/no choice and an image are never among them', keys().every( k => /^(\.uri|slug|tags|name|body)@/.test( k ) ) );

	field('tags').value = '{broken';
	check( 'a list that is not JSON is reported as that - not as an empty required field', keys().indexOf('tags@:json') !== -1 && keys().indexOf('tags@:required') === -1 );
	field('tags').value = '[1,';
	check( '...a scalar and null are no list either', ( field('tags').value = '5', keys().indexOf('tags@:json') !== -1 ) && ( field('tags').value = 'null', keys().indexOf('tags@:json') !== -1 ) );
	field('tags').value = '{"a":1}';
	check( 'an object with an entry is valid, and satisfies a required field', keys().some( k => k.indexOf('tags@') === 0 ) === false );
	field('tags').value = '{}';
	check( '...an empty object does not', keys().indexOf('tags@:required') !== -1 );
	field('tags').value = '';
	check( 'a blank list text is an empty list - required says so, and the rest of the time it is fine', keys().indexOf('tags@:required') !== -1 );
	field('tags').value = '[]';

	elements._invalidArrays = { 'de_DE|ltags' : '[1,' };
	check( 'text that is not JSON in another translation holds the save back too', keys().indexOf('ltags@de_DE:json') !== -1 );
	elements._invalidArrays = {};

	// ---- a save that is refused
	const sent = [];
	elements._apiCall = ( endpoint, payload, callback ) => sent.push( { endpoint : endpoint, payload : payload, callback : callback } );
	let outcome = null;
	elements._save( ok => { outcome = ok } );
	check( 'a refused save sends nothing and reports false', sent.length === 0 && outcome === false && elements._validated === true );
	check( 'the uri is the first problem and takes the focus', uriInput.focused === true );
	check( '...it is marked invalid, points at its sentence, and the sentence is under it',
		uriInput.getAttribute('aria-invalid') === 'true' && uriInput.getAttribute('aria-describedby') === 'elements-error-uri'
		&& d.doc.getElementById('elements-error-uri').textContent === 'URI' && d.doc.getElementById('elements-error-uri').className === 'nino-admin-field-error' && d.doc.getElementById('elements-error-uri').parentNode === uriInput.parentNode.parentNode );
	check( '...next to the label, not inside it: the sentence describes the control and is not part of its name',
		uriInput.parentNode.querySelector('#elements-error-uri') === null && field('slug').closest('label').querySelector('.nino-admin-field-error') === null );
	check( 'every other field on screen is marked the same way', [ 'slug', 'name', 'body' ].every( key => field( key ).getAttribute('aria-invalid') === 'true' && field( key ).getAttribute('aria-describedby') === 'elements-error-field-'+ key ) );
	check( 'a required list of texts with no row has no input to mark: the sentence is under the list and its add button is the control',
	( function() {
		const add = d.doc.getElementById('elements-form').querySelector('.nino-admin-stringlist-add');
		const sentence = d.doc.getElementById('elements-error-field-tags');
		return add !== null && sentence !== null && add.getAttribute('aria-invalid') === 'true' && add.getAttribute('aria-describedby') === 'elements-error-field-tags' && sentence.parentNode === add.parentNode;
	} )() );
check( 'the sentences are not alerts - the status line says it once, in its own words',
		d.doc.getElementById('elements-error-field-slug').getAttribute('role') === null && d.doc.getElementById('elements-form-msg').textContent === 'REQ' );
	const optionsText = () => d.doc.getElementById('elements-form-locale-select').querySelectorAll('option').map( o => o.textContent );
	check( 'the language switch counts the open fields of each translation it would write', JSON.stringify( optionsText() ) === JSON.stringify( [ 'de_DE - 1 open', 'en_US - 2 open' ] ) );

	// fixed by typing: the marks follow
	type( 'slug', 'hello' );
	check( 'a field that was fixed loses its mark while the others keep theirs', field('slug').getAttribute('aria-invalid') === null && d.doc.getElementById('elements-error-field-slug') === null && field('name').getAttribute('aria-invalid') === 'true' );

	// the first problem is in another translation: the form goes there
	uriInput.value = 'first';
	type( 'tags', '["x"]' );
	type( 'name', 'Name' );
	type( 'body', 'Body' );
	localeSwitches.length = 0;
	outcome = null;
	elements._save( ok => { outcome = ok } );
	check( 'a first problem in a translation that is not on screen switches to it - the select, the content locale and the fields',
		outcome === false && elements._selectedLocale === 'de_DE' && d.doc.getElementById('elements-form-locale-select').value === 'de_DE' && localeSwitches.join() === 'de_DE' );
	check( '...and the field there is marked and has the focus', field('name').getAttribute('aria-invalid') === 'true' && field('name').focused === true );
	check( 'the other translation shows no open fields now', JSON.stringify( optionsText() ) === JSON.stringify( [ 'de_DE - 1 open', 'en_US' ] ) );

	// until the old translation is taken down the selection is still its own
	const realSessionLocale = sandbox.Nino.admin.sessionLocale;
	const seenDuringSwitch = [];
	sandbox.Nino.admin.sessionLocale = { current : 'de_DE', set : locale => seenDuringSwitch.push( { to : locale, selected : elements._selectedLocale, name : field('name').value } ), init(){} };
	elements._switchLocale( 'en_US' );
	check( 'the content locale is told while the old translation is still on screen and still selected', seenDuringSwitch.length === 1 && seenDuringSwitch[0].to === 'en_US' && seenDuringSwitch[0].selected === 'de_DE' && elements._selectedLocale === 'en_US' );
	elements._switchLocale( 'de_DE' );
	sandbox.Nino.admin.sessionLocale = realSessionLocale;

	// the marks come back with a translation drawn afresh
	elements._switchLocale( 'en_US' );
	elements._switchLocale( 'de_DE' );
	check( 'a translation drawn again carries the marks of the refused save', field('name').getAttribute('aria-invalid') === 'true' && d.doc.getElementById('elements-error-field-name') !== null );

	type( 'name', 'Name de' );
	check( '...and the option falls back to its plain code once nothing is open', JSON.stringify( optionsText() ) === JSON.stringify( [ 'de_DE', 'en_US' ] ) );

	// invalid text in a translation is kept, shown again, and blocks the save
	field('ltags').value = '[oops';
	elements._switchLocale( 'en_US' );
	check( 'text that is not JSON is kept when its translation is left: the old value stays, the text is remembered, the translation counts as edited',
		elements._invalidArrays['de_DE|ltags'] === '[oops' && JSON.stringify( elements._localeValues.de_DE.ltags ) === '[]' && elements._dirtyLocales.indexOf('de_DE') !== -1 );
	elements._switchLocale( 'de_DE' );
	check( '...and it is what the field shows when it comes back', field('ltags').value === '[oops' );
	sent.length = 0;
	elements._save();
	check( 'such a translation holds the save back even from another one', sent.length === 0 && elements._validate().some( p => p.key === 'ltags' && p.kind === 'json' ) === true );
	field('ltags').value = '[1]';
	elements._switchLocale( 'en_US' );
	check( 'valid again, the text is let go of', Object.keys( elements._invalidArrays ).length === 0 && JSON.stringify( elements._localeValues.de_DE.ltags ) === '[1]' );

	// ---- unsaved input: what the shell asks about
	elements._currentModel.rich = { type : 'string', html : true, locale : true };
	elements._localeKeys = [ 'name', 'body', 'ltags', 'rich' ];
	elements._isNew = false;
	elements._currentUri = 'one';
	elements._globalValues = { slug : 'hello', tags : [ 'x' ], pic : 'a.jpg' };
	elements._localeValues = {
		de_DE : { name : 'Name de', body : 'Body de', ltags : [], rich : 'a&nbsp;b' },
		en_US : { name : 'Name', body : 'Body', ltags : [], rich : 'c&nbsp;d' },
	};
	elements._dirtyLocales = [];
	elements._selectedLocale = 'en_US';
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	formNode.classList.remove('admin-hidden');

	check( 'a form nobody touched holds nothing unsaved', elements.isDirty() === false );
	type( 'slug', 'changed' );
	check( 'an edited global field does', elements.isDirty() === true );
	check( '...the status line says so', elements._status.state === 'dirty' );
	type( 'slug', 'hello' );
	check( '...and typing it back takes it away again, line and all', elements.isDirty() === false && elements._status.state === 'idle' );
	type( 'name', 'Name edited' );
	check( 'an edited field of the translation on screen does', elements.isDirty() === true );
	type( 'name', 'Name' );

	elements._switchLocale( 'de_DE' );
	elements._switchLocale( 'en_US' );
	check( 'merely visiting a translation does not mark it - not even one with a rich-text field that reads back as other markup than the server holds',
		elements._dirtyLocales.length === 0 && elements.isDirty() === false );

	// a refused save stores the translation on screen; typing the stored value back takes the mark away again
	type( 'name', '' );
	sent.length = 0;
	elements._save();
	check( 'a refused save of an emptied required field counts the translation as edited', sent.length === 0 && elements._dirtyLocales.join() === 'en_US' && elements.isDirty() === true );
	type( 'name', 'Name' );
	check( '...and typing the stored value back leaves nothing unsaved', elements.isDirty() === false );
	check( '...and the status line no longer names fields that are not marked', elements._status.state === 'idle' );
	type( 'name', 'Name edited' );
	elements._switchLocale( 'de_DE' );
	elements._switchLocale( 'en_US' );
	type( 'name', 'Name' );
	check( 'a translation that was edited and left stays unsaved, whatever it is typed back to', elements._dirtyLocales.join() === 'en_US' && elements.isDirty() === true );
	elements.discard();
	elements._renderForm();

	// a translation this account may not write: the save never sends it, and a
	// rich text reads back as other markup than the string the server holds
	elements._currentModel.note = { type : 'string', html : true, locale : true };
	elements._localeKeys = [ 'name', 'body', 'ltags', 'rich', 'note' ];
	elements._localeValues.de_DE.note = 'p&nbsp;q';
	elements._localeValues.en_US.note = 'r&nbsp;s';
	elements._rights = { post : { update : { ro : false, note : false } } };
	elements._resetEdits();
	elements._renderForm();
	elements._switchLocale( 'de_DE' );
	elements._switchLocale( 'en_US' );
	check( 'visiting a translation does not mark it for a rich-text field the account may not write, whatever markup it reads back as',
		elements._dirtyLocales.length === 0 && elements.isDirty() === false );
	delete elements._currentModel.note;
	elements._localeKeys = [ 'name', 'body', 'ltags', 'rich' ];
	delete elements._localeValues.de_DE.note;
	delete elements._localeValues.en_US.note;
	elements._rights = { post : { update : { ro : false } } };
	elements._resetEdits();
	elements._renderForm();

	elements._switchLocale( 'de_DE' );
	type( 'body', 'Body de edited' );
	elements._switchLocale( 'en_US' );
	check( 'a translation that was edited and left stays unsaved', elements._dirtyLocales.join() === 'de_DE' && elements.isDirty() === true );

	field('pic').value = 'b.jpg';
	elements.discard();
	check( 'discarding takes the model back to what the server sent and counts the form as saved',
		elements.isDirty() === false && elements._dirtyLocales.length === 0 && elements._localeValues.de_DE.body === 'Body de' && elements._globalValues.slug === 'hello' );

	field('pic').value = 'c.jpg';
	check( 'an uploaded image commits by itself and is not an edit of the form', elements.isDirty() === false );

	formNode.classList.add('admin-hidden');
	elements._dirtyLocales = [ 'de_DE' ];
	check( 'only a form on screen can be dirty - a list or the type picker has nothing typed into it', elements.isDirty() === false );
	formNode.classList.remove('admin-hidden');
	elements._dirtyLocales = [];

	elements._isNew = true;
	elements._currentUri = null;
	elements._numbered = {};
	elements._globalValues = {};
	elements._localeValues = {};
	elements._resetEdits();
	elements._renderForm();
	check( 'a new element is clean until something is typed', elements.isDirty() === false );
	d.doc.getElementById('elements-form-uri').value = 'x';
	check( '...its uri counts', elements.isDirty() === true );
	d.doc.getElementById('elements-form-uri').value = '';

	// a copy
	elements._isNew = false;
	elements._currentUri = 'one';
	elements._globalValues = { slug : 'hello', tags : [ 'x' ], pic : 'a.jpg' };
	elements._localeValues = { de_DE : { name : 'Name de', body : 'Body de', ltags : [], rich : '' }, en_US : { name : 'Name', body : 'Body', ltags : [], rich : '' } };
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	elements._duplicate();
	check( 'a copy nobody has saved is unsaved input, even one with nothing edited in it', elements._isNew === true && elements._copy === true && elements.isDirty() === true );
	check( '...and discarding it goes back to nothing', ( elements.discard(), elements.isDirty() === false ) );

	// ---- every way a save ends reports
	elements._isNew = false;
	elements._currentUri = 'one';
	elements._globalValues = { slug : 'hello', tags : [ 'x' ], pic : 'a.jpg' };
	elements._localeValues = { de_DE : { name : 'Name de', body : 'Body de', ltags : [], rich : '' }, en_US : { name : 'Name', body : 'Body', ltags : [], rich : '' } };
	elements._dirtyLocales = [];
	elements._selectedLocale = 'en_US';
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	sent.length = 0;
	const reports = [];
	const report = ok => reports.push( ok );

	elements._saving = true;
	elements._save( report );
	elements._saving = false;
	check( 'a save asked for while one runs reports false', reports.join() === 'false' && sent.length === 0 );

	type( 'slug', '' );
	elements._save( report );
	check( 'a refusal reports false', reports.join() === 'false,false' && sent.length === 0 );

	type( 'slug', 'new slug' );
	elements._save( report );
	check( 'a request that fails reports false and gives the form back', sent.length === 1 && ( sent[0].callback( 500, null ), reports.join() === 'false,false,false' ) && elements._saving === false );
	check( '...what was typed is still unsaved', elements.isDirty() === true );

	elements._save( report );
	sent[1].callback( 200, { element : { '.uri' : '/post/one', slug : 'new slug', tags : [ 'x' ], pic : 'a.jpg', name : 'Name', body : 'Body', ltags : [], rich : '' } } );
	check( 'a save that goes through reports true', reports.join() === 'false,false,false,true' );
	check( '...the form counts as saved, the line says when, and the model is what the server answered',
		elements.isDirty() === false && elements._status.state === 'saved' && elements._globalValues.slug === 'new slug' );
	elements.discard();
	check( '...and that answer is what a discard goes back to now', elements._globalValues.slug === 'new slug' );

	// ---- a save that runs is not interrupted by the question
	elements._switchLocale( 'de_DE' );
	type( 'body', 'DE-RACE' );
	elements._switchLocale( 'en_US' );
	type( 'body', 'EN-RACE' );
	sent.length = 0;
	elements._save( report );
	const backLink = d.doc.getElementById('elements-form').querySelector('.nino-admin-back-link');
	check( 'while a save runs the back link is inert - a click on it cannot open the question',
		sent.length === 1 && sent[0].payload.locale === 'de_DE' && backLink.getAttribute('aria-disabled') === 'true' && backLink.style.pointerEvents === 'none' );
	elements.discard();
	check( 'a discard that comes in during the save leaves the edits of the languages not yet sent alone',
		elements._localeValues.en_US.body === 'EN-RACE' && elements._dirtyLocales.join() === 'de_DE,en_US' && elements.isDirty() === true );
	sent[0].callback( 200, { element : { '.uri' : '/post/one', slug : 'new slug', tags : [ 'x' ], pic : 'a.jpg', name : 'Name de', body : 'DE-RACE', ltags : [], rich : '' } } );
	check( '...so the next request still carries them', sent.length === 2 && sent[1].payload.locale === 'en_US' && sent[1].payload.fields.body === 'EN-RACE' );
	sent[1].callback( 200, { element : { '.uri' : '/post/one', slug : 'new slug', tags : [ 'x' ], pic : 'a.jpg', name : 'Name', body : 'EN-RACE', ltags : [], rich : '' } } );
	check( '...and when it is done the link works again, and discarding goes back to what was written',
		backLink.getAttribute('aria-disabled') === 'false' && backLink.style.pointerEvents === '' && ( elements.discard(), elements._localeValues.en_US.body === 'EN-RACE' && elements._localeValues.de_DE.body === 'DE-RACE' ) );

	// ---- the exits the form guards
	const guards = [];
	sandbox.Nino.admin.dirty = { guard : ( names, proceed ) => guards.push( { names : names, proceed : proceed } ), register(){}, refresh(){}, snapshot(){} };
	const opened = [];
	elements._openForm = uri => opened.push( uri );
	elements._elements = [ { uri : 'ada', label : 'Ada' }, { uri : 'one', label : 'One' }, { uri : 'cy', label : 'Cy' } ];
	const navButtons = elements._renderNav().querySelectorAll('button');
	navButtons[1].click();
	check( 'stepping to the next element asks about this form first', guards.length === 1 && guards[0].names.join() === 'elements' && opened.length === 0 );
	guards[0].proceed();
	check( '...and goes on once the question is answered', opened.join() === 'cy' );

	const copies = [];
	elements._makeCopy = () => copies.push( true );
	elements._duplicate();
	check( 'duplicating asks first too', guards.length === 2 && copies.length === 0 );
	guards[1].proceed();
	check( '...and copies once it may', copies.length === 1 );

	delete sandbox.Nino.admin.dirty;
	elements._duplicate();
	navButtons[0].click();
	check( 'a shell without the registry asks nothing', copies.length === 2 && opened.join() === 'cy,ada' );

	// ---- a link inside a rich text is its content, not a link of the form
	{
		const plainEditor = sandbox.Nino.admin.htmlEditor;
		// An editor whose content really holds the link: it reads back as the markup
		// of that node, attributes and all, so whatever the form writes onto it shows
		sandbox.Nino.admin.htmlEditor = { create : ( mount, value ) => {
			const content = d.make('div');
			content.setAttribute( 'contenteditable', 'true' );
			const link = d.make('a');
			link.attrs.href = /href="([^"]*)"/.exec( String( value ?? '' ) )?.[1] ?? '';
			content.appendChild( link );
			mount.appendChild( content );
			const markup = () => '<a href="'+ link.attrs.href+ '"'
				+ ( 'aria-disabled' in link.attrs ? ' aria-disabled="'+ link.attrs['aria-disabled']+ '"' : '' )
				+ ( 'pointerEvents' in link.style ? ' style="'+ ( link.style.pointerEvents === '' ? '' : 'pointer-events: '+ link.style.pointerEvents+ ';' )+ '"' : '' )+ '>l</a>';
			return { getValue : markup, setValue(){}, destroy(){}, focus(){}, mark(){}, content : content, link : link };
		} };

		const richLink = '<a href="/x?a=\'b\'">l</a>';
		const savedElement = ( uri, global ) => ( { '.uri' : '/post/'+ uri, slug : global ?? 'hello', tags : [ 'x' ], pic : 'a.jpg', name : 'Name', body : 'Body', ltags : [], rich : richLink } );
		const saveRequest = () => sent.filter( r => r.endpoint === 'save' ).pop();
		const richValue = () => elements._readFieldByKey( 'rich', elements._currentModel.rich );

		elements._isNew = false;
		elements._currentUri = 'one';
		elements._globalValues = { slug : 'hello', tags : [ 'x' ], pic : 'a.jpg' };
		elements._localeValues = {
			de_DE : { name : 'Name de', body : 'Body de', ltags : [], rich : richLink },
			en_US : { name : 'Name', body : 'Body', ltags : [], rich : richLink },
		};
		elements._dirtyLocales = [];
		elements._selectedLocale = 'en_US';
		elements._resetEdits();
		elements._remember();
		elements._renderForm();

		const before = richValue();
		elements._setFormPending( true );
		const during = richValue();
		elements._setFormPending( false );
		check( 'a rich text that holds a link keeps its value while the form is locked for a save and when it is given back',
			before === richLink && during === richLink && richValue() === richLink && elements.isDirty() === false );

		sent.length = 0;
		elements._save( report );
		check( '...the link is not touched while the save runs', saveRequest() !== undefined && richValue() === richLink );
		saveRequest().callback( 200, { element : savedElement( 'one' ) } );
		check( '...and an existing element saved with nothing edited is clean afterwards, with the markup it had',
			elements._saving === false && elements.isDirty() === false && richValue() === richLink && elements._status.state === 'saved' );

		sent.length = 0;
		elements._save( report );
		saveRequest().callback( 500, null );
		check( '...a save that fails leaves an unedited form clean', elements._saving === false && elements.isDirty() === false && richValue() === richLink );

		// a new element, saved once
		elements._isNew = true;
		elements._currentUri = null;
		elements._globalValues = { slug : 'fresh', tags : [ 'x' ], pic : 'a.jpg' };
		elements._localeValues = { de_DE : { name : 'Name de', body : 'Body de', ltags : [], rich : richLink }, en_US : { name : 'Name', body : 'Body', ltags : [], rich : richLink } };
		elements._dirtyLocales = [];
		elements._resetEdits();
		elements._renderForm();
		d.doc.getElementById('elements-form-uri').value = 'newone';
		d.doc.getElementById('elements-edit-form').fire('input');
		sent.length = 0;
		elements._save( report );
		saveRequest().callback( 200, { element : savedElement( 'newone', 'fresh' ) } );
		check( 'a new element saved with a link in its rich text is clean afterwards, and the link is as it was',
			elements._isNew === false && elements._saving === false && elements.isDirty() === false && richValue() === richLink && elements._status.state === 'saved' );

		sandbox.Nino.admin.htmlEditor = plainEditor;
	}
}

// The registration is the shell's to offer: a shell with it is told about this
// form, one without it is not asked
{
	const registered = [];
	const box = { console : console, document : { documentElement : null, body : null } };
	box.window = box;
	box.Nino = { editor : {}, events : { bindCallback(){} }, admin : { dirty : { register : ( name, entry ) => registered.push( [ name, entry ] ) } } };
	vm.runInContext( source, vm.createContext( box ), { filename : 'elements.js' } );
	check( 'the Elements form registers with the shell under its name, with the three words the shell asks it',
		registered.length === 1 && registered[0][0] === 'elements' && [ 'isDirty', 'save', 'discard' ].every( k => typeof registered[0][1][k] === 'function' ) );
}

// --- the name of a field: a fill, a word, the key -----------------------------------
//
// A field is named by the fill of its type (/_admin/elements/field/<type>/<key>,
// which Social brings), else by the word of the vocabulary its key is, else by
// the key with a capital first letter - the key as written, so "price_default"
// stays "Price_default" where the Text panel would say "Price default". The
// labels the shipped demo types used to carry are gone: they were a table of
// names, and covered the labels a project gave a type of the same name

{
	const words = { '/_admin/common/word/title' : 'Titel', '/_admin/common/word/price' : 'Preis', '/_admin/elements/field/social/title' : 'Netzwerk' };
	sandbox.Nino.content = { getText : key => words[key] ?? '' };
	elements._currentType = 'social';
	check( 'the fill of the type comes first', elements._fieldLabel( 'title' ) === 'Netzwerk' );
	elements._currentType = 'services';
	check( '...then the word of the vocabulary', elements._fieldLabel( 'title' ) === 'Titel' && elements._fieldLabel( 'price' ) === 'Preis' );
	check( '...then the key with a capital first letter, as written', elements._fieldLabel( 'price_default' ) === 'Price_default' && elements._fieldLabel( 'tasks' ) === 'Tasks' );
	check( 'the demo labels of the shipped types are gone from both languages', [ 'en_US', 'de_DE' ].every( locale => {
		const fills = fs.readFileSync( path.join( __dirname, '../_admin/Nino/Modules/Elements/text/'+ locale+ '.php' ), 'utf8' );
		return /\[\[\/_admin\/elements\/field\//.test( fills ) === false && fills.includes( '[[/_admin/types/label/ownkey]]' );
	} ) );
}

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exitCode = failures === 0 ? 0 : 1;
