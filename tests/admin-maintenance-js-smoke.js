/**
 *	Nino											A compact filesystembased php framework
 *	admin-maintenance-js-smoke.js	DOM-light checks for the Maintenance panel's script
 *														(_nino/Nino/Modules/Maintenance/assets/admin.js) and the
 *														notice it puts at the top of the workbench while the
 *														site shows its maintenance page.
 *
 *														The notice is for whoever works anywhere in the
 *														workbench, so it is not in the panel's own pane - it is
 *														in the content column above every panel - and it has to
 *														follow the state the server reports: there after the
 *														page loads or a save that switched maintenance on, gone
 *														after one that switched it off, and never twice.
 *
 *	Usage: node tests/admin-maintenance-js-smoke.js
 */

'use strict';

const vm 		= require('vm');
const { check, done, fills, load, element, findAll, shellRouter } = require( './harness.js' );

// The link of the notice is written by the router's own codec
const codec = shellRouter();

function banners( root ) {
	return findAll( root, function( el ) { return el.id === 'maintenance-banner' } );
}

function byId( root, id ) {
	return root.id === id ? root : ( findAll( root, function( el ) { return el.id === id } )[0] ?? null );
}


// --- the words -------------------------------------------------------------

const moduleEn = fills('_nino/Nino/Modules/Maintenance/text/en_US.php');
const moduleDe = fills('_nino/Nino/Modules/Maintenance/text/de_DE.php');

function text( key ) {
	return moduleEn[key] || '';
}


// --- the sandbox -----------------------------------------------------------

/** A workbench column as the shell draws it: the heading only a screen reader sees, then the panes */
function column() {
	const col = element('main');
	col.id = 'admin-content-wrap';
	const heading = element('h1');
	col.appendChild( heading );
	col.appendChild( element('div') );
	return col;
}

let content = column();
let form 		= null;
const requests = [];

function present( withForm ) {
	content = column();
	form = null;
	if( withForm === true ) {
		form = element('div');
		form.id = 'maintenance-form';
		content.appendChild( form );
	}
}

const Nino = {
	admin : { router : codec.stub() },
	events : { bindCallback : function() {} },
	http : { sendRequest : function( uri, method, callback, data ) {
		requests.push( { action : data.action, payload : JSON.parse( data.data ), callback : callback } );
	} },
	content : { getText : text },
};
const sandbox = {
	console : console,
	document : {
		createElement : element,
		createTextNode : function( value ) { return { nodeType : 3, textContent : String( value ), children : [] } },
		getElementById : function( id ) {
			if( id === 'admin-content-wrap' )
				return content;
			return byId( content, id );
		},
		documentElement : null,
		body : null,
	},
	Nino : Nino,
};
sandbox.window = { Nino : Nino, setTimeout : function() { return 1 }, clearTimeout : function() {} };

const context = vm.createContext( sandbox );
load( '_admin/assets/Nino.admin.js', context );

// The form's own fields are the design system's business, and have their own
// tests - here they are only there. The save reads them back by their key
let switchOptions = null;
Nino.adminUi.switchField = function( options ) {
	switchOptions = options;
	const field = element('div');
	const input = element('input');
	input.attributes['data-key'] = options.key;
	input.checked = options.checked;
	field.appendChild( input );
	return field;
};
Nino.adminUi.numberField = function( options ) {
	const field = element('div');
	const input = element('input');
	input.attributes['data-key'] = options.key;
	input.value = String( options.value );
	input.min = options.min;
	input.max = options.max;
	field.appendChild( input );
	return field;
};

load( '_nino/Nino/Modules/Maintenance/assets/admin.js', context );

const panel = Nino.admin.maintenance;

/** Answer the last request the panel made, the way Nino.http.sendRequest() calls back */
function answer( status, body ) {
	requests[requests.length - 1].callback( { status : status, responseJSON : body } );
}

console.log('Admin Maintenance');

// --- the panel is not there -------------------------------------------------

present( false );
panel.init();
check( 'an account without the panel has no form on the page: nothing is requested and nothing drawn', requests.length === 0 && banners( content ).length === 0 );

// --- the page loads ----------------------------------------------------------

present( true );
panel.init();
check( 'the panel asks for the state', requests.length === 1 && requests[0].action === 'maintenance/status' );
answer( 200, { status : false, retry : 3600 } );
check( 'no paragraph that explains stands under the title of a section or under a field: it is behind a (?)', findAll( content, function( el ) { return el.tagName === 'FIELDSET' } ).every( function( set ) { return set.children.every( function( child ) { return String( child.className ).indexOf('nino-admin-hint') === -1 || String( child.className ).indexOf('nino-admin-status') !== -1 } ) } ) );
check( 'a site that is online has no notice', banners( content ).length === 0 );
check( 'the pane opens on its form: no sentence stands over it, and what the switch does and who still sees the site is its help, with no note that stays under it', form.children.length === 1 && form.children[0].tagName === 'FORM'
	&& switchOptions.help === text('/_admin/maintenance/hint/status')+ ' '+ text('/_admin/maintenance/hint/signedin') && switchOptions.note === undefined );
const bar = findAll( form, function( el ) { return el.classList.contains('nino-admin-actionbar') } );
const main = findAll( form, function( el ) { return el.classList.contains('nino-admin-btn-primary') } );
check( 'the form ends in the one bar, whose last child is the one main button that submits it', bar.length === 1 && main.length === 1 && bar[0].children[bar[0].children.length - 1] === main[0] && main[0].type === 'submit' );

present( true );
requests.length = 0;
panel.init();
answer( 200, { status : true, retry : 3600 } );
let drawn = banners( content );
check( 'a site that shows its maintenance page gets one notice', drawn.length === 1 );
check( '...in the workbench\'s content column, right after the heading - above every panel, not in the form\'s pane', content.children[1] === drawn[0] && content.children[0].tagName === 'H1' && drawn[0].parentNode === content );
check( '...a status notice of the design system, saying so in the interface language', drawn[0].className === 'nino-admin-notice' && drawn[0].attributes.role === 'status'
	&& drawn[0].textContent === text('/_admin/maintenance/banner/text') && text('/_admin/maintenance/banner/text') !== '' );

const anchors = findAll( drawn[0], function( el ) { return el.tagName === 'A' } );
check( '...with a link to the panel, labelled in the interface language', anchors.length === 1 && anchors[0].href === '#maintenance'
	&& anchors[0].textContent === text('/_admin/maintenance/banner/link') && text('/_admin/maintenance/banner/link') !== '' );

// --- a save --------------------------------------------------------------------

requests.length = 0;
panel._save();
check( 'a save posts the switch and the seconds', requests.length === 1 && requests[0].action === 'maintenance/set' && requests[0].payload.retry === '3600' && requests[0].payload.status === true );
answer( 200, { status : true, retry : 3600 } );
check( 'a save that leaves maintenance on keeps one notice, not two', banners( content ).length === 1 );

requests.length = 0;
panel._save();
answer( 200, { status : false, retry : 3600 } );
check( 'a save that answers false takes the notice away, and nothing else of the column', banners( content ).length === 0 && content.children.length === 3 && content.children[0].tagName === 'H1' );

requests.length = 0;
panel._save();
answer( 200, { status : true, retry : 7200 } );
check( 'and one that answers true puts it back', banners( content ).length === 1 );

requests.length = 0;
panel._save();
answer( 500, { error : 'could not write' } );
check( 'a save that failed changes nothing about it, and says why in the line of the bar', banners( content ).length === 1
	&& findAll( content, function( el ) { return el.id === 'maintenance-form-msg' } )[0].dataset.state === 'error' );

// --- asking again --------------------------------------------------------------

requests.length = 0;
panel.init();
answer( 200, { status : true, retry : 3600 } );
check( 'asking again does not draw a second notice', banners( content ).length === 1 );
requests.length = 0;
panel.init();
answer( 200, { status : false, retry : 3600 } );
check( 'and an answer that says online removes it', banners( content ).length === 0 );

// --- a refusal -----------------------------------------------------------------

present( true );
requests.length = 0;
panel.init();
answer( 403, { error : 'no' } );
check( 'a refused status request draws no notice - the state is not known', banners( content ).length === 0 );

// --- the bounds ---------------------------------------------------------------

// The seconds field is bounded by what the server answers, not by a copy of it
present( true );
requests.length = 0;
panel.init();
answer( 200, { status : false, retry : 3600, min : 120, max : 86400 } );
const retryInput = form.querySelector('[data-key="retry"]');
check( 'the seconds field takes its bounds from the answer', retryInput !== null && retryInput.min === 120 && retryInput.max === 86400 );

// --- checking: when saving, at the field, with the focus on it ------------------

const realForm = findAll( form, el => el.tagName === 'FORM' )[0];
const sentences = () => findAll( form, el => el.classList.contains('nino-admin-field-error') );
check( 'the form is checked by the panel and never by the browser: it carries novalidate', realForm.noValidate === true );
retryInput.value = '5';
requests.length = 0;
let ended = null;
panel._save( function( ok ) { ended = ok } );
check( 'seconds outside the bounds the server gave are refused before anything is sent: a sentence at the field, the field marked, the focus on it, and the line says so', requests.length === 0 && ended === false
	&& retryInput.attributes['aria-invalid'] === 'true' && sentences().length === 1 && retryInput.focused === true && byId( form, 'maintenance-form-msg' ).dataset.state === 'error' );
retryInput.value = '300';
panel._save();
check( '...and the sentence is gone when they are right, and the save is sent', sentences().length === 0 && requests.length === 1 );
answer( 400, { error : 'x', code : 'invalid_value', field : 'retry' } );
check( 'a refusal of the server that names the seconds says it at the field too, with the focus there', sentences().length === 1 && retryInput.focused === true );

// --- the shell is told ---------------------------------------------------------

// What the shell asks how much to ask by (Nino.admin.mode.policy()) follows the
// maintenance page: told by every answer that says what state it is in, and not
// by a request that failed
const told = [];
Nino.admin.mode = { maintenance : function( on ) { told.push( on ) } };
present( true );
requests.length = 0;
panel.init();
answer( 200, { status : true, retry : 3600 } );
check( 'a status answer tells the shell whether the maintenance page is up', told.join() === 'true' );
requests.length = 0;
panel.init();
answer( 200, { status : false, retry : 3600 } );
check( '...and when it is not', told.join() === 'true,false' );
requests.length = 0;
panel.init();
answer( 403, { error : 'no' } );
check( '...but a refusal tells it nothing: the state is not known', told.length === 2 );
panel.init();
answer( 200, { status : false, retry : 3600 } );
form.querySelector('[data-key="status"]').checked = true;
panel._save();
answer( 200, { status : true, retry : 3600 } );
check( 'a save that switched it on tells the shell, with what the server now holds', told[told.length - 1] === true );
delete Nino.admin.mode;
present( true );
requests.length = 0;
panel.init();
answer( 200, { status : true, retry : 3600 } );
check( 'a shell that has no mode is told nothing, and the notice is drawn all the same', banners( content ).length === 1 );

// --- the words ---------------------------------------------------------------

check( 'both interface languages have the two words', [ moduleEn, moduleDe ].every( function( map ) {
	return map['/_admin/maintenance/banner/text'] !== undefined && map['/_admin/maintenance/banner/text'] !== ''
		&& map['/_admin/maintenance/banner/link'] !== undefined && map['/_admin/maintenance/banner/link'] !== '';
} ) );

done();
