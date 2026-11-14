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
const { check, done, load } = require( './harness.js' );

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
load( '_admin/assets/Nino.admin.js', context );

vm.runInContext(
	source,
	context,
	{ filename : 'elements.js' }
);

const elements = sandbox.Nino.admin.elements;
// The form a person opens, as the script has it: later checks stand it in for what they drive
const originalOpenForm = elements._openForm;

// The shell's counters (see Nino.admin.touch()): what the panel says it wrote
const touched = [];
sandbox.Nino.admin.touch = function( topic ) { touched.push( topic ) };
sandbox.Nino.admin.stamp = function() { return 0 };

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
// tests/elements-smoke.php and tests/admin-elements-smoke.php)
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

let answered = false;
elements._loadReferenceOptions( function() { answered = true } );

check( 'the continuation runs once every referenced type has answered', answered === true );
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
check( 'a blank number input has no value: null for an integer and for a double, spaces included',
	elements._readField( { dataset : { type : 'integer' }, value : '' } ) === null && elements._readField( { dataset : { type : 'double' }, value : '  ' } ) === null );
check( 'a typed number is that number, and 0 is 0',
	elements._readField( { dataset : { type : 'integer' }, value : '20' } ) === 20 && elements._readField( { dataset : { type : 'integer' }, value : '0' } ) === 0
	&& elements._readField( { dataset : { type : 'double' }, value : '2.5' } ) === 2.5 && elements._readField( { dataset : { type : 'double' }, value : '0' } ) === 0 );
check( 'text that is no number stays text, for the server to refuse',
	elements._readField( { dataset : { type : 'integer' }, value : 'abc' } ) === 'abc' && elements._readField( { dataset : { type : 'double' }, value : '-' } ) === '-' );
check( 'an empty number field is not the same as 0 when a stored value is compared with the control',
	elements._fieldValuesEqual( { type : 'integer' }, null, null ) === true && elements._fieldValuesEqual( { type : 'integer' }, undefined, null ) === true
	&& elements._fieldValuesEqual( { type : 'integer' }, 0, null ) === false && elements._fieldValuesEqual( { type : 'double' }, null, 0 ) === false );
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
	Object.keys( nodes ).forEach( id => { nodes[id].id = id } );
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
	load( '_admin/assets/Nino.admin.js', box_context );
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
	load( '_admin/assets/Nino.admin.js', context );
	vm.runInContext( source, context, { filename : 'elements.js' } );

	const module = box.Nino.admin.elements;
	// The languages the panel works in, as elements/types names them
	module._locales = [ 'de_DE', 'en_US' ];
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
	sandbox.Nino.admin.router = { set(){}, go : ( panel, parts ) => stepped.push( panel+ '/'+ parts.join('/') ), href : ( panel, parts ) => '#'+ [ panel ].concat( parts ).map( encodeURIComponent ).join('/') };
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
	elements._locales = [ 'de_DE', 'en_US' ];
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

	// The Text panel works in languages the website does not serve yet; an element has no version of one to show or to edit here
	sandbox.Nino.admin.sessionLocale = { current : 'it_IT', set(){}, init(){} };
	elements._renderList( [ { uri : 'ada', label : 'Ada', values : {} } ], [] );
	check( 'a working language this panel does not offer - one the website does not serve yet - is none for it: the list reads the server\'s own', elements._workingLocale( '' ) === '' && elements._listLocale === '' );
	check( '...and a form opens on the first language it has, or the one it is told', elements._workingLocale( 'de_DE' ) === 'de_DE' );
	sandbox.Nino.admin.sessionLocale = { current : 'en_US', set(){}, init(){} };
	check( '...a language it offers is the working language as it was', elements._workingLocale( '' ) === 'en_US' );
	sandbox.Nino.admin.sessionLocale = { current : 'de_DE', set(){}, init(){} };

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
	check( 'nothing was written yet, so nothing is told to the other panels', touched.length === 0 );
	sent[0].callback( 200, { filename : 'a.jpg', url : '/images/a.jpg' } );
	check( '...and the answer lands in the field and the preview', hidden.value === 'a.jpg' && preview.src === '/images/a.jpg' && msg.textContent === 'Saved.' );
	check( '...and the Remove button is there from then on', removeBtn.hidden === false );
	check( 'an image that was written tells the other panels that elements were', touched.join() === 'elements' );

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
	const toldBefore = touched.length;
	check( '...and told nobody that anything was written', touched.length === toldBefore && toldBefore === 2 );
	elements._removeImage( 'photo', 'Photo', hidden, preview, msg, removeBtn );
	sent[sent.length - 1].callback( 200, { filename : null } );
	check( 'a removal empties the field, hides the preview and the button and says so', hidden.value === '' && preview.hidden === true && removeBtn.hidden === true && msg.textContent === 'Removed.' );
	check( '...and tells the other panels that elements were written', touched.length === toldBefore + 1 && touched[touched.length - 1] === 'elements' );

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

	// The element that has the focus: a page has one, and loses it with the element
	let held = null;

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
				// A flag the node holds as a property - hidden, disabled - is an attribute while it is true
				return m[2] === undefined ? value !== null && value !== undefined && value !== false : String( value ) === m[2];
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
			id : '', _value : '', checked : false, disabled : false, hidden : false, type : '', textContent : '', focused : false,
			classList : {
				add : ( ...k ) => k.forEach( c => classes.add( c ) ), remove : ( ...k ) => k.forEach( c => classes.delete( c ) ),
				contains : k => classes.has( k ), toggle : ( k, on ) => { if( on === undefined ? ! classes.has( k ) : on ) classes.add( k ); else classes.delete( k ); return classes.has( k ) },
			},
			get className() { return [ ...classes ].join(' ') }, set className( v ) { classes = new Set( String( v ).split( /\s+/ ).filter( Boolean ) ) },
			get contentEditable() { return this.attrs.contenteditable ?? 'inherit' }, set contentEditable( v ) { this.attrs.contenteditable = String( v ) },
			// A control's value is a string in a browser, whatever was assigned
			get value() { return this._value }, set value( v ) { this._value = String( v ) },
			get firstChild() { return this.children[0] ?? null },
			get innerHTML() { return '' }, set innerHTML( v ) { this.children.forEach( c => c.parentNode = null ); this.children = [] },
			attr( name ) { return name in this.attrs ? this.attrs[name] : ( name === 'type' ? ( this.type || null ) : ( this[name] === undefined || this[name] === '' ? ( name.startsWith('data-') ? this.dataset[name.slice(5)] ?? null : null ) : this[name] ) ) },
			setAttribute( k, v ) { this.attrs[k] = String( v ) }, getAttribute( k ) { return this.attrs[k] ?? null }, hasAttribute( k ) { return k in this.attrs }, removeAttribute( k ) { delete this.attrs[k] },
			get isConnected() { for( let n = this; n; n = n.parentNode ) if( n === root ) return true; return false },
			appendChild( c ) { if( c.parentNode ) c.remove(); c.parentNode = this; this.children.push( c ); return c },
			insertBefore( c, ref ) { c.parentNode = this; this.children.splice( ref ? this.children.indexOf( ref ) : this.children.length, 0, c ); return c },
			remove() { if( this.parentNode ) this.parentNode.children.splice( this.parentNode.children.indexOf( this ), 1 ); this.parentNode = null },
			after( c ) { if( c.parentNode ) c.remove(); c.parentNode = this.parentNode; this.parentNode.children.splice( this.parentNode.children.indexOf( this ) + 1, 0, c ) },
			replaceWith( c ) { const at = this.parentNode.children.indexOf( this ); c.parentNode = this.parentNode; this.parentNode.children.splice( at, 1, c ); this.parentNode = null },
			addEventListener( type, fn ) { ( this.listeners[type] = this.listeners[type] ?? [] ).push( fn ) },
			fire( type, ev = {} ) { ( this.listeners[type] ?? [] ).forEach( fn => fn( Object.assign( { target : this, preventDefault(){} }, ev ) ) ) },
			click() { this.fire('click') },
			focus() { if( held !== null && held !== this ) held.focused = false; held = this; this.focused = true },
			querySelectorAll( selector ) { return walk( this ).filter( n => matches( n, selector ) ) },
			querySelector( selector ) { return this.querySelectorAll( selector )[0] ?? null },
			closest( selector ) { for( let n = this; n; n = n.parentNode ) if( matches( n, selector ) ) return n; return null },
		};
		return node;
	};

	const root = make('body');
	const doc = {
		documentElement : make('html'), body : root,
		get activeElement() { return held !== null && held.isConnected === true ? held : null },
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
	Object.defineProperties( sandbox.document, Object.getOwnPropertyDescriptors( d.doc ) );
	sandbox.CSS = { escape : value => String( value ) };

	const texts = {
		'/_admin/elements/error/required' : 'REQ', '/_admin/common/error/required' : 'FIELD', '/_admin/elements/error/json' : 'JSON', '/_admin/elements/error/number' : 'NUMBER',
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
	texts['/_admin/common/label/image-target'] = 'TARGET';
	texts['/_admin/elements/label/image-largest'] = 'LARGEST';
	texts['/_admin/elements/hint/alt'] = 'ALT OF %s';
	texts['/_admin/elements/label/image-remove'] = 'Remove image';
	const hintsOf = el => el.querySelectorAll('.nino-admin-field-hint').map( h => h.textContent );
	check( 'the image field of a model with an alt link names the field it is written in', hintsOf( elements._renderField( 'photo', elements._currentModel.photo, 'a.jpg' ) ).join() === 'ALT IN Caption' );
	check( '...and the linked field names the image it is the alt text of', hintsOf( elements._renderField( 'caption', elements._currentModel.caption, 'x' ) ).join() === 'ALT OF Photo' );
	check( 'an image without the link, and a field nobody links to, carry no such hint', hintsOf( elements._renderField( 'plain', elements._currentModel.plain, '' ) ).length === 0
		&& hintsOf( elements._renderField( 'other', { type : 'string' }, 'x' ) ).length === 0 );
	const dimensionsOf = el => el.querySelector('.nino-admin-field-image-dimensions').textContent;
	check( 'an image field that crops names its box as the target size, one that fits as the largest size it stores',
		dimensionsOf( elements._renderField( 'photo', elements._currentModel.photo, 'a.jpg' ) ) === 'TARGET 40 × 30 px'
		&& dimensionsOf( elements._renderField( 'logo', { type : 'image', width : 40, height : 30, fit : true }, 'a.jpg' ) ) === 'LARGEST 40 × 30 px' );
	const withImage = elements._renderField( 'photo', elements._currentModel.photo, 'a.jpg' );
	const withoutImage = elements._renderField( 'photo', elements._currentModel.photo, '' );
	const removeOf = el => el.querySelectorAll('button').filter( b => b.className === 'nino-admin-btn-danger' )[0];
	check( 'the remove button is drawn with the image field, shown while there is an image and hidden while there is none', removeOf( withImage ).hidden === false && removeOf( withoutImage ).hidden === true
		&& removeOf( withImage ).textContent === 'Remove image' );
	check( 'the preview of an image field has an alt of its own that is empty: the label of the field names it, and a file name would be read as well', withImage.querySelector('.nino-admin-field-image-preview').alt === '' );
	elements._isNew = true;
	check( 'a new element, which has no image to remove, has no button', removeOf( elements._renderField( 'photo', elements._currentModel.photo, '' ) ) === undefined );
	elements._isNew = false;
	elements._currentModel = {};
	check( 'an image and a yes/no choice are never marked: one is uploaded on its own, the other cannot be empty',
		stars( draw( { type : 'image', required : true } ) ).length === 0 && stars( draw( { type : 'boolean', required : true } ) ).length === 0 );

	const choice = draw( { type : 'boolean' }, true );
	check( 'a yes/no choice is a radio group named by its visible name - not a label around two controls, which would name the first of them "Name Yes"',
		choice.tagName === 'div' && choice.getAttribute('role') === 'radiogroup' && choice.firstChild.tagName === 'span' && choice.getAttribute('aria-labelledby') === choice.firstChild.id && choice.firstChild.id !== ''
		&& choice.querySelectorAll('input').length === 2 && choice.querySelectorAll('label').length === 2 && choice.querySelectorAll('label').every( option => option.parentNode.className === 'nino-admin-field-radios' ) );

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
	check( '...an empty required field is described by the sentence of the shell, the one a form of another panel says as well', d.doc.getElementById('elements-error-field-slug').textContent === 'FIELD' );
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

	touched.length = 0;
	elements._save( report );
	sent[1].callback( 200, { element : { '.uri' : '/post/one', slug : 'new slug', tags : [ 'x' ], pic : 'a.jpg', name : 'Name', body : 'Body', ltags : [], rich : '' } } );
	check( 'a save that goes through reports true', reports.join() === 'false,false,false,true' );
	check( '...and tells the other panels that elements were written', touched.join() === 'elements' );
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

	// ---- a number field left empty is sent as no value, and 0 as the number it is
	elements._currentModel.order = { type : 'integer' };
	elements._currentModel.weight = { type : 'double', locale : true };
	elements._globalKeys.push( 'order' );
	elements._localeKeys.push( 'weight' );
	elements._globalValues.order = null;
	elements._localeValues.en_US.weight = null;
	elements._localeValues.de_DE.weight = null;
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	elements._switchLocale( 'de_DE' );
	elements._switchLocale( 'en_US' );
	type( 'slug', 'number save' );
	const answered = ( payload ) => ( { element : Object.assign( { '.uri' : '/post/one', slug : 'number save', tags : [ 'x' ], pic : 'a.jpg', name : 'Name', body : 'Body', ltags : [], rich : '' }, payload.fields ) } );
	check( 'an untouched empty number field is not an edit', field('order').value === '' && field('weight').value === '' && elements._dirtyLocales.length === 0 );
	sent.length = 0;
	elements._save( report );
	check( 'a number field left empty is sent as null, the integer and the double alike - not 0, not an empty text',
		sent.length === 1 && sent[0].payload.fields.order === null && sent[0].payload.fields.weight === null && JSON.stringify( sent[0].payload.fields ).indexOf('"order":null') !== -1 );
	sent[0].callback( 200, answered( sent[0].payload ) );
	type( 'order', '20' );
	type( 'weight', '1.5' );
	sent.length = 0;
	elements._save( report );
	check( 'what is typed goes as the number', sent[0].payload.fields.order === 20 && sent[0].payload.fields.weight === 1.5 );
	sent[0].callback( 200, answered( sent[0].payload ) );
	type( 'order', '0' );
	type( 'weight', '0' );
	sent.length = 0;
	elements._save( report );
	check( '0 typed is 0', sent[0].payload.fields.order === 0 && sent[0].payload.fields.weight === 0 );
	sent[0].callback( 200, answered( sent[0].payload ) );
	type( 'order', 'abc' );
	sent.length = 0;
	elements._save( report );
	check( 'text that is no number is sent as it is, for the server to refuse, and is not made a 0', sent[0].payload.fields.order === 'abc' );
	sent[0].callback( 400, null );
	type( 'order', '' );
	elements._globalValues.order = '0';
	elements._localeValues.en_US.weight = null;
	elements._localeValues.de_DE.weight = null;
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	check( 'a stored 0 is shown as 0 and stays an untouched field', field('order').value === '0' && elements.isDirty() === false );
	type( 'order', '' );
	check( '...and emptying it is an edit, because empty is not 0', elements.isDirty() === true );
	elements._dirtyLocales = [];
	delete elements._currentModel.order;
	delete elements._currentModel.weight;
	elements._globalKeys.pop();
	elements._localeKeys.pop();
	delete elements._globalValues.order;
	delete elements._localeValues.en_US.weight;
	delete elements._localeValues.de_DE.weight;
	elements._resetEdits();
	elements._remember();
	elements._renderForm();

	// ---- a number field with a model default: emptied, it shows what the server answers
	elements._currentModel.prio = { type : 'integer', default : 50 };
	elements._currentModel.order = { type : 'integer' };
	elements._globalKeys.push( 'prio', 'order' );
	elements._globalValues.prio = '9';
	elements._globalValues.order = '3';
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	check( 'a stored number is shown and is no edit', field('prio').value === '9' && elements.isDirty() === false );
	type( 'prio', '' );
	sent.length = 0;
	elements._save( report );
	check( 'the emptied field is sent as null', sent.length === 1 && sent[0].payload.fields.prio === null && Number( sent[0].payload.fields.order ) === 3 );
	sent[0].callback( 200, { element : Object.assign( answered( sent[0].payload ).element, { prio : 50, order : 3 } ) } );
	check( '...and once the save is answered its control shows the default the server holds - not empty, which the next load would contradict',
		field('prio').value === '50' && elements._globalValues.prio === 50 && elements._status.state === 'saved' );
	check( '...as what is saved: the form is clean, and a field that is not number-emptied is left alone', elements.isDirty() === false && field('order').value === '3' );
	type( 'prio', '' );
	check( 'emptying it again is an edit', elements.isDirty() === true );

	// a number control that holds what is no number reads as empty - the save is held back for it
	field('prio').value = '50';
	field('order').value = '';
	field('order').validity = { badInput : true };
	sent.length = 0;
	let held = null;
	elements._save( ok => { held = ok } );
	check( 'a number control with badInput holds the save back, marked at its field, and sends nothing', sent.length === 0 && held === false
		&& elements._validate().some( p => p.key === 'order' && p.kind === 'number' ) === true && field('order').getAttribute('aria-invalid') === 'true' );
	check( '...in the words of the number refusal', d.doc.getElementById('elements-error-field-order').textContent === 'NUMBER' && elements._status.state === 'error' && d.doc.getElementById('elements-form-msg').textContent === 'NUMBER' );
	field('order').validity = { badInput : false };
	check( '...and an empty control without it is just empty', elements._validate().every( p => p.key !== 'order' ) === true );
	// ...also in a translation that was left: its number stays, and the save is held back for it
	elements._currentModel.rank = { type : 'integer', locale : true };
	elements._localeKeys.push( 'rank' );
	elements._localeValues.de_DE.rank = '10';
	elements._localeValues.en_US.rank = '20';
	elements._globalValues.prio = '50';
	elements._globalValues.order = '3';
	elements._resetEdits();
	elements._remember();
	elements._renderForm();
	elements._switchLocale( 'de_DE' );
	check( 'a stored number of the translation on screen is shown', field('rank').value === '10' );
	field('rank').value = '';
	field('rank').validity = { badInput : true };
	elements._switchLocale( 'en_US' );
	check( 'leaving a translation whose number control holds no number keeps the stored number and remembers the slot',
		elements._localeValues.de_DE.rank === '10' && elements._invalidNumbers['de_DE|rank'] === true && elements._dirtyLocales.indexOf('de_DE') !== -1 && elements.isDirty() === true );
	check( 'the problem is a number in the translation that was left, though its control is not on screen',
		elements._validate().some( p => p.key === 'rank' && p.locale === 'de_DE' && p.kind === 'number' ) === true && elements._selectedLocale === 'en_US' );
	sent.length = 0;
	held = null;
	elements._save( ok => { held = ok } );
	check( 'the save sends nothing, reports false and comes back to that translation', sent.length === 0 && held === false && elements._selectedLocale === 'de_DE' );
	check( '...marked at its field in the words of the number refusal, and the status line says it',
		field('rank').getAttribute('aria-invalid') === 'true' && d.doc.getElementById('elements-error-field-rank').textContent === 'NUMBER' && d.doc.getElementById('elements-form-msg').textContent === 'NUMBER' );
	field('rank').validity = { badInput : false };
	type( 'rank', '12' );
	check( 'a number typed in its place lets the save go: the problem and the slot are gone',
		elements._validate().length === 0 && Object.keys( elements._invalidNumbers ).length === 0 );
	sent.length = 0;
	elements._save( report );
	check( '...and what is sent is the number typed', sent.length >= 1 && sent.some( r => r.payload.locale === 'de_DE' && r.payload.fields.rank === 12 ) );
	while( sent.length > 0 )
		sent.shift().callback( 400, null );
	delete elements._currentModel.rank;
	elements._localeKeys.pop();
	delete elements._localeValues.de_DE.rank;
	delete elements._localeValues.en_US.rank;
	elements._dirtyLocales = [];
	delete elements._currentModel.prio;
	delete elements._currentModel.order;
	elements._globalKeys.pop();
	elements._globalKeys.pop();
	delete elements._globalValues.prio;
	delete elements._globalValues.order;
	elements._resetEdits();
	elements._remember();
	elements._renderForm();

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

	// ---- save and add another: the same save, and a blank form of the type after it
	{
		texts['/_admin/elements/label/save-again'] = 'AGAIN';
		for( const id of [ 'elements-types', 'elements-list' ] )
			if( d.doc.getElementById( id ) === null ) {
				const level = d.make('div');
				level.id = id;
				d.root.appendChild( level );
			}
		elements._openForm = originalOpenForm;
		elements._currentType = 'post';
		elements._currentTypeTitle = 'Posts';
		elements._currentModel = { slug : { type : 'string', required : true }, name : { type : 'string', locale : true } };
		elements._globalKeys = [ 'slug' ];
		elements._localeKeys = [ 'name' ];
		elements._locales = [ 'de_DE', 'en_US' ];
		elements._selectedLocale = 'en_US';
		elements._rights = {};
		elements._referenceOptions = {};
		elements._numbered = {};
		elements._raw = {};
		const blank = () => {
			elements._isNew = true;
			elements._currentUri = null;
			elements._globalValues = {};
			elements._localeValues = {};
			elements._dirtyLocales = [];
			elements._resetEdits();
			elements._renderForm();
		};
		const bar = () => d.doc.getElementById('elements-form-actions').querySelectorAll('button');
		const again = () => d.doc.getElementById('elements-form-save-again');
		const fillIn = () => {
			d.doc.getElementById('elements-form-uri').value = 'newone';
			field('slug').value = 'fresh';
			d.doc.getElementById('elements-edit-form').fire('input');
		};

		blank();
		check( 'the form of a new element has a second button after Save: the words of "save and add another", secondary, and no submit of its own',
			again() !== null && bar().length === 2 && bar()[0].type === 'submit' && bar()[1] === again() && again().type === 'button' && again().className === 'nino-admin-btn-secondary' && again().textContent === 'AGAIN' );

		elements._isNew = false;
		elements._currentUri = 'one';
		elements._globalValues = { slug : 'hello' };
		elements._localeValues = { en_US : { name : 'Name' } };
		elements._renderForm();
		check( '...a form of an element that is saved has none: there is nothing to add another to', again() === null );

		// The bar lays Delete on its far side and the status line beside it (order in the stylesheet): the markup has them there
		const kinds = () => d.doc.getElementById('elements-form-actions').children.map( c => c.classList.contains('nino-admin-btn-danger') ? 'delete' : ( c.id === 'elements-form-msg' ? 'status' : ( c.type === 'submit' ? 'save' : 'other' ) ) ).join();
		check( 'the bar of a saved element holds its actions in the order it lays them out - Delete, the status line, Save, Duplicate - so that the Tab key follows the eye', kinds() === 'delete,status,save,other' );
		blank();
		check( '...that of a new element has nothing to delete: the status line, Save, Save and add another', kinds() === 'status,save,other' );

		// a refused save stays where it is
		blank();
		sent.length = 0;
		touched.length = 0;
		again().click();
		check( 'the second button saves as the first does: a form that is refused sends nothing and stays, with its problems marked', sent.length === 0 && elements._isNew === true && field('slug').getAttribute('aria-invalid') === 'true' && touched.length === 0 );

		// a request that fails stays where it is
		fillIn();
		again().click();
		check( 'a complete form is sent as the first button sends it - a new element, this uri, these fields', sent.length === 1 && sent[0].endpoint === 'save' && sent[0].payload.isNew === true && sent[0].payload.uri === 'newone' && sent[0].payload.fields.slug === 'fresh' );
		check( '...with both buttons disabled while it is on its way, like everything else in the form', again().disabled === true && bar()[0].disabled === true );
		sent[0].callback( 500, null );
		check( 'a request that fails leaves the form as it was, saying so, the second button given back', elements._isNew === true && d.doc.getElementById('elements-form-uri').value === 'newone' && again().disabled === false && elements._status.state === 'error' && touched.length === 0 );

		// the answer: the blank form of the same type
		again().click();
		check( 'the second try sends the same request', sent.length === 2 && sent[1].payload.uri === 'newone' );
		sent[1].callback( 200, { element : { '.uri' : '/post/newone', slug : 'fresh', name : 'Name' } } );
		check( 'saved, a blank form of the same type opens: a new element with no uri and nothing in it', elements._isNew === true && elements._currentUri === null && elements._currentType === 'post'
			&& d.doc.getElementById('elements-form-uri').value === '' && d.doc.getElementById('elements-form-uri').disabled !== true && field('slug').value === '' && again() !== null );
		check( '...the status line of that form says what was saved, and holds nothing unsaved', elements._status.state === 'saved' && d.doc.getElementById('elements-form-msg').textContent.indexOf( 'Saved at' ) === 0 && elements.isDirty() === false );
		check( '...and the other panels were told that elements were written, once', touched.join() === 'elements' );
		check( '...it is shown the way a new element is, as the level of the pane', d.doc.getElementById('elements-form').classList.contains('admin-hidden') === false );
		check( '...and the focus is in its first field, where the next element is typed, and not on the way back', d.doc.activeElement === d.doc.getElementById('elements-form-uri') && d.doc.activeElement.closest('.nino-admin-back-link') === null );

		// a save the server refuses for fields: the sentence stands at each field it names
		{
			const refused = { error : 'FIRST SENTENCE', field : 'slug', fields : [ { field : 'slug', error : 'FIRST SENTENCE', code : '' }, { field : 'name', error : 'SECOND SENTENCE', code : '' }, { field : '.uri', error : 'URI SENTENCE', code : '' } ] };
			const mark = key => ( { sentence : d.doc.getElementById('elements-error-field-'+ key), control : field( key ) } );

			blank();
			fillIn();
			sent.length = 0;
			elements._save( () => {} );
			sent[0].callback( 400, refused );
			check( 'a save the server refused for fields says the first sentence in the status line, as every failed request does', elements._status.state === 'error' && d.doc.getElementById('elements-form-msg').textContent.indexOf( 'FIRST SENTENCE' ) !== -1 );
			check( '...and each field it names carries its sentence next to it, aria-invalid, and aria-describedby naming that sentence',
				[ 'slug', 'name' ].every( key => { const m = mark( key ); return m.sentence !== null && m.sentence.className === 'nino-admin-field-error' && m.control.getAttribute('aria-invalid') === 'true' && m.control.getAttribute('aria-describedby') === m.sentence.id } )
				&& mark('slug').sentence.textContent === 'FIRST SENTENCE' && mark('name').sentence.textContent === 'SECOND SENTENCE' );
			check( '...the element\'s name is a field like the others', d.doc.getElementById('elements-error-uri').textContent === 'URI SENTENCE' && d.doc.getElementById('elements-form-uri').getAttribute('aria-describedby') === 'elements-error-uri' );
			check( '...the focus is on the first, and the form is given back', d.doc.activeElement === field('slug') && elements._saving === false && again().disabled === false );

			field('slug').value = 'fixed';
			d.doc.getElementById('elements-edit-form').fire( 'input', { target : field('slug') } );
			check( 'typing into a field lets its refusal go, the others stay',
				d.doc.getElementById('elements-error-field-slug') === null && field('slug').getAttribute('aria-invalid') === null && field('slug').getAttribute('aria-describedby') === null
				&& mark('name').sentence !== null && mark('name').control.getAttribute('aria-invalid') === 'true' );

			sent.length = 0;
			elements._save( () => {} );
			check( 'the next save starts without the refusals of the one before', sent.length === 1 && d.doc.getElementById('elements-error-field-name') === null && field('name').getAttribute('aria-invalid') === null );
			sent[0].callback( 400, { error : 'ONLY A SENTENCE' } );
			check( 'a refusal without fields is the status line alone', d.doc.getElementById('elements-form-msg').textContent.indexOf( 'ONLY A SENTENCE' ) !== -1 && d.doc.getElementById('elements-form').querySelectorAll('.nino-admin-field-error').length === 0 );
			sent.length = 0;
			elements._save( () => {} );
			sent[0].callback( 400, { error : 'UNKNOWN', field : 'nothere', fields : [ { field : 'nothere', error : 'UNKNOWN' } ] } );
			check( 'a field the form does not have draws nothing and breaks nothing', d.doc.getElementById('elements-form').querySelectorAll('.nino-admin-field-error').length === 0 && elements._status.state === 'error' );
			elements._resetEdits();
			check( 'a form that is reset forgets what was refused', elements._refused.length === 0 );

			// the focus is the form's own where the answer names the fields only
			blank();
			fillIn();
			sent.length = 0;
			elements._save( () => {} );
			sent[0].callback( 400, { error : 'ONLY IN THE LIST', fields : [ { field : 'name', error : 'ONLY IN THE LIST' } ] } );
			check( 'the focus goes to the first field of the list, whether or not the answer names it as its field', d.doc.activeElement === field('name') && field('name').getAttribute('aria-invalid') === 'true' );

			// drawn again, a sentence stands once
			elements._drawRefusals( false );
			elements._drawRefusals( false );
			check( 'a refusal that is drawn twice has one sentence', d.doc.getElementById('elements-form').querySelectorAll('.nino-admin-field-error').filter( e => e.id === 'elements-error-field-name' ).length === 1 );

			// the marks of a rich text are its editor's: they go when the sentence goes
			{
				const marks = [];
				elements._clearRefusals();
				elements._htmlEditors.name = { mark : o => marks.push( o ), focus(){}, destroy(){} };
				elements._refused = [ { key : 'name', locale : 'en_US', text : 'EDITOR SENTENCE' } ];
				elements._drawRefusals( false );
				check( 'a refused rich text is marked invalid through its editor, pointed at its sentence', marks.length === 1 && marks[0].invalid === true && marks[0].describedBy === 'elements-error-field-name' );
				d.doc.getElementById('elements-edit-form').fire( 'input', { target : field('name') } );
				check( '...and typing into it marks it valid again through the editor', marks.length === 2 && marks[1].invalid === false && marks[1].describedBy === '' && d.doc.getElementById('elements-error-field-name') === null );
				delete elements._htmlEditors.name;
			}

			// a refusal survives the revalidation a refused save starts: only the field typed into lets go
			elements._refused = [ { key : 'slug', locale : null, text : 'SLUG' }, { key : 'name', locale : 'en_US', text : 'NAME' } ];
			elements._drawRefusals( false );
			elements._validated = true;
			field('slug').value = 'typed';
			d.doc.getElementById('elements-edit-form').fire( 'input', { target : field('slug') } );
			check( 'the revalidation after a refused save keeps the refusal of the field that was not typed into drawn', d.doc.getElementById('elements-error-field-slug') === null && d.doc.getElementById('elements-error-field-name') !== null && d.doc.getElementById('elements-error-field-name').textContent === 'NAME' );
			elements._validated = false;
			elements._clearRefusals();

			// a code the workbench has words for is said in them
			texts['/_admin/error/probe_code'] = 'WORDS OF THE WORKBENCH';
			blank();
			fillIn();
			sent.length = 0;
			elements._save( () => {} );
			sent[0].callback( 400, { error : 'ENGLISH', field : 'slug', fields : [ { field : 'slug', error : 'ENGLISH', code : 'probe_code' } ] } );
			check( 'a refusal with a code the workbench knows is said in the workbench\'s words, one without in the callback\'s own', d.doc.getElementById('elements-error-field-slug').textContent === 'WORDS OF THE WORKBENCH' );
			delete texts['/_admin/error/probe_code'];

			// the name of a saved element: its line carries the sentence and can take the focus
			elements._isNew = false;
			elements._currentUri = 'one';
			elements._globalValues = { slug : 'hello' };
			elements._localeValues = { en_US : { name : 'Name' } };
			elements._renderForm();
			elements._refused = [ { key : '.uri', locale : null, text : 'URI SENTENCE' } ];
			elements._drawRefusals( true );
			check( 'a refusal of the name of a saved element stands at the line that shows it, and the line takes the focus', d.doc.getElementById('elements-error-uri').textContent === 'URI SENTENCE' && d.doc.activeElement.className === 'main-uri' && d.doc.activeElement.getAttribute('aria-describedby') === 'elements-error-uri' );
			check( '...as a paragraph after the line, not inside the text of the address', d.doc.getElementById('elements-error-uri').parentNode === d.doc.querySelector('.main-uri').parentNode && d.doc.getElementById('elements-error-uri').parentNode !== d.doc.querySelector('.main-uri') );
			d.doc.getElementById('elements-edit-form').fire( 'input', { target : field('slug') } );
			check( '...typing into another field leaves it', d.doc.getElementById('elements-error-uri') !== null );
			elements._clearRefusals();
			check( '...and it goes with the others', d.doc.getElementById('elements-error-uri') === null );
			blank();

			// a refusal of a translation that is not on screen does not go when the same field of the one on screen is typed into
			elements._localeValues = { de_DE : { name : 'Name de' }, en_US : { name : 'Name en' } };
			elements._selectedLocale = 'en_US';
			elements._refused = [ { key : 'name', locale : 'de_DE', text : 'GERMAN ONLY' } ];
			elements._drawRefusals( false );
			d.doc.getElementById('elements-edit-form').fire( 'input', { target : field('name') } );
			check( 'typing into a field of the translation on screen leaves the refusal of that field in another translation', elements._refused.length === 1 );
			elements._clearRefusals();

			// a translation that is not on screen: the form goes to it, and a translation left and come back to shows it again
			blank();
			fillIn();
			elements._localeValues = { de_DE : { name : 'Name de' }, en_US : { name : 'Name en' } };
			elements._dirtyLocales = [ 'de_DE', 'en_US' ];
			elements._selectedLocale = 'en_US';
			sent.length = 0;
			elements._save( () => {} );
			check( 'a save of two translations asks for the first', sent.length === 1 && sent[0].payload.locale === 'de_DE' );
			sent[0].callback( 400, { error : 'GERMAN', field : 'name', fields : [ { field : 'name', error : 'GERMAN' } ] } );
			check( 'a refusal of a translation that is not on screen switches the form to that translation and marks the field there',
				elements._selectedLocale === 'de_DE' && d.doc.getElementById('elements-error-field-name') !== null && d.doc.getElementById('elements-error-field-name').textContent === 'GERMAN' && d.doc.activeElement === field('name') );
			elements._switchLocale( 'en_US' );
			check( '...the other translation is not marked', d.doc.getElementById('elements-error-field-name') === null && field('name').getAttribute('aria-invalid') === null );
			elements._switchLocale( 'de_DE' );
			check( '...and coming back to the refused one shows the sentence at its field again', d.doc.getElementById('elements-error-field-name') !== null && field('name').getAttribute('aria-describedby') === 'elements-error-field-name' );
			elements._selectedLocale = 'en_US';
		}

		// a locked field takes no focus: the first one that can be typed into does
		blank();
		d.doc.getElementById('elements-form-uri').disabled = true;
		elements._focusFirstField();
		check( 'a blank form whose first field is locked puts the focus on the first one that is not', d.doc.activeElement === field('slug') && d.doc.activeElement.disabled !== true );

		// a type with nothing to type into but its way back
		elements._globalKeys = [];
		elements._localeKeys = [];
		elements._numbered = { post : true };
		blank();
		elements._focusFirstField();
		check( 'a blank form with no field to type into puts the focus on its way back instead of leaving it nowhere', d.doc.activeElement === d.doc.getElementById('elements-form').querySelector('.nino-admin-back-link') && d.doc.activeElement !== null );

		// A rich text is a field to type into, and its text box is the control: the editor keeps an
		// input of its own in a bar that is hidden until a link is made, and a call on that moves nothing
		{
			const plainEditor = sandbox.Nino.admin.htmlEditor;
			const kept = [ elements._currentModel, elements._globalKeys, elements._localeKeys, elements._numbered ];
			let editor = null;
			// In the order of the editor's own: the text box, then the bar of its link input, hidden
			sandbox.Nino.admin.htmlEditor = { create : mount => {
				const content = d.make('div');
				content.contentEditable = 'true';
				const bar = d.make('div');
				bar.hidden = true;
				const link = d.make('input');
				link.type = 'text';
				bar.appendChild( link );
				mount.appendChild( content );
				mount.appendChild( bar );
				editor = { getValue : () => '', setValue(){}, destroy(){}, focus(){}, mark(){}, content : content, link : link };
				return editor;
			} };
			elements._currentModel = { body : { type : 'string', locale : true, html : true }, name : { type : 'string', locale : true } };
			elements._globalKeys = [];
			elements._localeKeys = [ 'body', 'name' ];
			elements._numbered = { post : '00002' };

			blank();
			sent.length = 0;
			again().click();
			sent[0].callback( 200, { element : { '.uri' : '/post/00002' }, nextUri : '00003' } );
			check( 'a numbered type whose first field is a rich text opens its next blank form with the focus in the text box of the editor, where the next element is typed',
				elements._isNew === true && editor !== null && d.doc.activeElement === editor.content );
			check( '...not nowhere, not on the input of the hidden link bar and not past the editor on the field after it',
				d.doc.activeElement !== null && d.doc.activeElement.closest('[hidden]') === null && editor.link.focused !== true && field('name').focused !== true );

			// a control in a part that is hidden takes no focus: the first one that can does
			editor.content.parentNode.hidden = true;
			elements._focusFirstField();
			check( 'with the editor in a part that is hidden the focus goes to the field after it', d.doc.activeElement === field('name') );

			// a rich text this account may not write is locked, and a locked text box takes no focus either
			elements._rights = { post : { update : { body : false } } };
			blank();
			elements._focusFirstField();
			check( 'a rich text that is locked is passed over: the field after it has the focus', d.doc.activeElement === field('name') );
			elements._rights = {};

			sandbox.Nino.admin.htmlEditor = plainEditor;
			[ elements._currentModel, elements._globalKeys, elements._localeKeys, elements._numbered ] = kept;
		}
		elements._numbered = {};
	}

	// ---- a delete: the other panels hear of it once the server says the element is gone
	{
		let selected = null;
		const originalSelect = elements._selectType;
		elements._selectType = ( type, model, title ) => { selected = [ type, model, title ] };
		elements._isNew = false;
		elements._currentUri = 'one';
		elements._currentType = 'post';
		sent.length = 0;
		touched.length = 0;

		sandbox.window.confirm = () => false;
		elements._delete();
		check( 'a delete that is not confirmed sends nothing and writes nothing', sent.length === 0 && touched.length === 0 );

		sandbox.window.confirm = () => true;
		elements._delete();
		check( 'a confirmed one asks the server to delete the element, and has written nothing yet', sent.length === 1 && sent[0].endpoint === 'delete' && sent[0].payload.uri === 'one' && touched.length === 0 );
		sent[0].callback( 500, null );
		check( '...one the server refused wrote nothing, gives the form back and stays where it is', touched.length === 0 && elements._saving === false && selected === null );

		elements._delete();
		sent[1].callback( 200, { ok : true } );
		check( '...one that went through tells the other panels that elements were written, and goes back to the list of the type', touched.join() === 'elements' && selected !== null && selected[0] === 'post' );

		elements._selectType = originalSelect;
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

// --- the focus of the drill-down: types, list, form ---------------------------------
//
// A level a person opens takes the focus in, and Back gives it to the control that
// opened the level that was left - the button of the type, the row of the element,
// the button that adds one - found again where the picker or the list was drawn anew
// in between, which a request does after every save. A row of a plain list is a link
// with the address it writes, a row of the table a stop of the Tab key of its own

{
	const f = fakeDom();
	Object.defineProperties( sandbox.document, Object.getOwnPropertyDescriptors( f.doc ) );
	sandbox.CSS = { escape : value => String( value ) };
	sandbox.Nino.content = { getText : key => key };
	sandbox.Nino.admin.formToolbar = backLink => { const bar = f.make('div'); bar.className = 'nino-admin-contextbar'; bar.appendChild( backLink ); return bar };
	sandbox.Nino.admin.sessionLocale = { current : 'en_US', set(){}, init(){} };
	sandbox.Nino.admin.htmlEditor = { create : () => ( { getValue : () => '', setValue(){}, destroy(){} } ) };
	// The bar under a list, as the design system makes it: the buttons in a bar of the list's kind
	sandbox.Nino.adminUi.listActions = buttons => { const bar = f.make('div'); bar.className = 'nino-admin-actionbar nino-admin-list-actions'; buttons.forEach( button => bar.appendChild( button ) ); return bar };
	const routed = [];
	sandbox.Nino.admin.router = { set(){}, go : ( panel, parts ) => routed.push( panel+ '/'+ parts.join('/') ), current : () => ( { panel : '', parts : [] } ), leave : ( names, leaving, proceed ) => proceed(),
		href : ( panel, parts ) => '#'+ [ panel ].concat( parts ).map( encodeURIComponent ).join('/') };

	// The pane the shell draws: the name of the panel in its head, and the three levels beside it
	const pane = f.make('div');
	pane.dataset.panel = 'elements';
	const head = f.make('div');
	head.className = 'admin-panel-head';
	const paneTitle = f.make('h2');
	paneTitle.className = 'admin-panel-title';
	head.appendChild( paneTitle );
	pane.appendChild( head );
	f.root.appendChild( pane );
	const level = {};
	[ 'types', 'list', 'form' ].forEach( name => {
		level[name] = f.make('div');
		level[name].id = 'elements-'+ name;
		if( name !== 'types' )
			level[name].classList.add('admin-hidden');
		pane.appendChild( level[name] );
	} );

	const asked = [];
	elements._apiCall = ( endpoint, payload, callback ) => asked.push( { endpoint : endpoint, payload : payload, callback : callback } );
	const answer = ( endpoint, status, body ) => asked.splice( asked.findIndex( request => request.endpoint === endpoint ), 1 )[0].callback( status, body );

	const model = { slug : { type : 'string' }, title : { type : 'string' } };
	const types = [ { type : 'post', title : 'Posts', descr : '(2)', model : model }, { type : 'page', title : 'Pages', descr : '(0)', model : model } ];
	const plain = { elements : [ { uri : 'one', label : 'One' }, { uri : 'two', label : 'Two' } ], columns : [] };
	const tabled = { elements : [ { uri : 'one', label : 'One', values : { title : 'One' } }, { uri : 'two', label : 'Two', values : { title : 'Two' } } ], columns : [ 'title' ] };
	const element = { global : { slug : 'x', title : 'X' }, locales : {}, raw : {} };

	const focus = () => f.doc.activeElement;
	const inside = node => focus() !== null && focus().closest( '#'+ node.id ) === node;
	const typeButton = type => level.types.querySelectorAll('button').find( b => b.dataset.type === type );
	const rowOf = uri => level.list.querySelectorAll('tr, a').find( row => row.dataset.row === uri );
	const backOf = node => node.querySelector('.nino-admin-back-link');

	elements._ready = true;
	elements._loading = false;
	elements._saving = false;
	elements._rights = {};
	elements._locales = [];
	elements._currentType = null;
	elements._renderTypes( types );
	elements._showTypes();
	check( 'the picker is shown as it stands, and the focus is nowhere in particular: it was nowhere', level.types.classList.contains('admin-hidden') === false && focus() === null );

	// a type is picked: the list arrives, and the focus goes in
	const picked = typeButton('post');
	picked.click();
	check( 'a type that is picked goes by its address', routed.at(-1) === 'elements/post' );
	answer( 'list', 200, plain );
	check( 'its list takes the focus when it arrives', level.list.classList.contains('admin-hidden') === false && level.types.classList.contains('admin-hidden') === true && inside( level.list ) );

	// an element of a plain list: a link with its address, and the focus goes into the form
	const link = rowOf('one');
	check( 'an entry of the plain list is a link with the address it writes - not a link to nowhere', link.tagName === 'a' && link.href === '#elements/post/one' );
	link.click();
	answer( 'get', 200, element );
	check( 'the form of the element takes the focus when it arrives', level.form.classList.contains('admin-hidden') === false && inside( level.form ) );

	// the list is drawn anew while the form is open: a save does it
	elements._renderList( plain.elements, [] );
	check( '...a list drawn anew behind the form leaves the focus where it is', inside( level.form ) );
	backOf( level.form ).click();
	check( 'Back shows the list and gives the focus to the row of the element - the one the list has now, not the one that was pressed', level.list.classList.contains('admin-hidden') === false
		&& focus() === rowOf('one') && focus() !== link );

	// the list drawn anew while the person is on it, as the language does it: the row keeps the focus
	elements._renderList( plain.elements, [] );
	check( 'a list drawn anew with the focus on a row gives it to the row of the same element', focus() === rowOf('one') && focus() !== link );
	rowOf('two').focus();
	elements._renderList( plain.elements, [] );
	check( '...whichever row it was on', focus() === rowOf('two') );

	// the table: a row is a stop of the Tab key of its own, and Enter on it opens
	elements._renderList( tabled.elements, tabled.columns );
	const tr = rowOf('two');
	check( 'a row of the table has its element for an identity, and is a stop of the Tab key', tr.tagName === 'tr' && tr.tabIndex === 0 && level.list.querySelectorAll('tr').filter( row => row.dataset.row !== undefined ).length === 2 );
	tr.focus();
	tr.fire( 'keydown', { key : 'Enter' } );
	answer( 'get', 200, element );
	check( 'Enter on a row opens the form, with the focus in it', inside( level.form ) );
	backOf( level.form ).click();
	check( '...and Back puts the focus on that row', focus() === rowOf('two') && focus().tagName === 'tr' );

	// a blank form: the button that adds one is where Back returns to
	const add = level.list.querySelector('.nino-admin-btn-primary');
	add.click();
	check( 'a new element opens its form with the focus in it', inside( level.form ) && elements._isNew === true );
	backOf( level.form ).click();
	check( '...and Back returns it to the button that adds one', focus() === add );

	// back to the picker: the button of the type, which is drawn anew behind it
	backOf( level.list ).click();
	check( 'Back to the types shows the picker and gives the focus to the button of the type that was open', level.types.classList.contains('admin-hidden') === false && focus() === typeButton('post') );
	const before = typeButton('post');
	check( '...the picker is read again then', asked.some( request => request.endpoint === 'types' ) );
	answer( 'types', 200, { types : types } );
	check( '...and drawn anew, the button of the same type has the focus, not the body', typeButton('post') !== before && focus() === typeButton('post') );
	typeButton('page').focus();
	elements._renderTypes( types );
	check( 'a picker drawn anew gives the focus to the button of the type it was on, whichever', focus() === typeButton('page') );

	// shown again as it stands - by the address, a step through the history: the focus stays, unless it stood in the level that goes
	typeButton('page').focus();
	elements._showTypes();
	check( 'the picker shown again leaves the focus where it is', focus() === typeButton('page') );
	elements._currentType = 'post';
	elements._renderList( plain.elements, [] );
	elements._showList();
	rowOf('one').focus();
	elements._showTypes();
	check( 'the list gone with the focus on its row: the focus goes to the name of the panel, where the person started', focus() === paneTitle );
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

pinOptionWords();

done();

// --- the words of an option --------------------------------------------------
//
// A field with a fixed set of values is a select. Each option is worded by a fill
// of the type's own (/_admin/elements/option/<type>/<key>/<value>), which a
// module or a project may bring; without one the option is its value

function pinOptionWords() {
	const words = { '/_admin/elements/option/tickets/phase/any' : 'Any', '/_admin/elements/option/tickets/phase/early' : 'Early (5-7)' };
	sandbox.Nino.content = { getText : key => words[key] ?? '' };
	elements._currentType = 'tickets';
	elements._currentModel = { phase : { type : 'string', options : [ 'any', 'early', 'noon' ] } };
	const options = elements._renderField( 'phase', { type : 'string', options : [ 'any', 'early', 'noon' ] }, 'early' ).querySelector('select').querySelectorAll('option');

	check( 'an option is worded by the fill of the type, the value stays what is sent', options.map( o => o.textContent ).slice( 0, 2 ).join() === 'Any,Early (5-7)' && options.map( o => o.value ).join() === 'any,early,noon' );
	check( '...an option with no fill is its value', options[2].textContent === 'noon' );
	check( '...and the stored one is the one selected', options[1].selected === true && options[0].selected === false );

	elements._currentType = 'other';
	const plain = elements._renderField( 'phase', { type : 'string', options : [ 'any', 'early' ] }, 'any' ).querySelector('select').querySelectorAll('option');
	check( 'a type that has none of the fills shows the values', plain.map( o => o.textContent ).join() === 'any,early' );
}
