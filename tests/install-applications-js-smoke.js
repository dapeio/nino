/**
 *	Nino									A compact filesystembased php framework
 *	install-applications-js-smoke.js	DOM-light checks for the wizard's Applications
 *									step: the offer drawn as a radio group for what provides
 *									the start page and checkboxes for the rest, "none" as the
 *									way that is always open, the features applied one request
 *									each in the order the plan gave, a failure that stays in
 *									the step with both ways on, and what the last step is told.
 *
 *	Usage: node tests/install-applications-js-smoke.js
 */

'use strict';

const vm = require('vm');
const { check, done, load, element, findAll, fire, documentOf } = require( './harness.js' );

const mount = element('div');
[ 'applications-msg', 'applications-notice', 'applications-notice-text', 'applications-missing', 'applications-reload', 'applications-start', 'applications-more',
	'applications-start-card', 'applications-start-locked', 'applications-more-card', 'applications-progress-card', 'applications-progress', 'applications-failure', 'applications-failure-text',
	'applications-retry', 'applications-skip', 'finish-msg' ].forEach( function( id ) {
	const el = element( id.endsWith( '-text' ) || id.endsWith( '-msg' ) || id.endsWith( '-locked' ) ? 'p' : ( id.endsWith( 'reload' ) || id.endsWith( 'retry' ) || id.endsWith( 'skip' ) ? 'button' : 'div' ) );
	el.id = id;
	mount.appendChild( el );
} );
const byId = function( id ) { return mount.children.find( function( el ) { return el.id === id } ) };
byId('applications-notice').classList.add( 'install-hidden' );
byId('applications-progress-card').classList.add( 'install-hidden' );
byId('applications-failure').classList.add( 'install-hidden' );

const sandbox = { console : console, document : documentOf( mount ) };
sandbox.document.createElement = element;
sandbox.window = sandbox;
const shown = [];
sandbox.Nino = {
	dir : '',
	install : { _index : 4, _busy : false,
		_setBusy : function( busy ) { sandbox.Nino.install._busy = busy },
		showStep : function( index ) { shown.push( index ); sandbox.Nino.install._index = index },
	},
	events : { bindCallback : function( name, fn ) { fn() } },
};

// What the server answers, by action - and every request in order
let requests = [];
let answers = {};
sandbox.Nino.install.apiCall = function( action, payload, callback ) {
	requests.push( { action : action, payload : payload } );
	const answer = typeof answers[action] === 'function' ? answers[action]( payload ) : answers[action];
	callback( answer[0], answer[1] );
};

load( '_admin/install/assets/applications.js', vm.createContext( sandbox ) );
const apps = sandbox.Nino.install.applications;

const offer = [
	{ key : 'site', name : 'Site', description : 'A site', features : [ { key : 'alpha', name : 'Alpha' }, { key : 'beta', name : 'Beta' } ], start : 'starter' },
	{ key : 'card', name : 'Card', description : 'A card', features : [ { key : 'delta', name : 'Delta' } ], start : 'own' },
	{ key : 'shop', name : 'Shop', description : '', features : [ { key : 'beta', name : 'Beta' }, { key : 'gamma', name : 'Gamma' } ], start : null },
];
const inputs = function( id ) { return findAll( byId( id ), function( el ) { return el.tagName === 'INPUT' } ) };
const pick = function( id, key ) { inputs( id ).forEach( function( el ) { if( el.value === key ) el.checked = true; else if( el.type === 'radio' ) el.checked = false } ) };

// --- the offer

answers['applications/list'] = [ 200, { applications : offer, missing : [], message : '', retry : false } ];
apps.showCurrent();

check( 'the step asks the server for the applications when it is shown', requests.length === 1 && requests[0].action === 'applications/list' );
check( 'the applications that provide the start page are a radio group, beginning with "none", which is the default',
	inputs('applications-start').map( function( el ) { return el.type+ ':'+ el.value } ).join() === 'radio:,radio:site,radio:card' && inputs('applications-start')[0].checked === true && inputs('applications-start').every( function( el ) { return el.name === 'install-application-start' } ) );
check( '...the others are checkboxes, none of them checked', inputs('applications-more').map( function( el ) { return el.type+ ':'+ el.value+ ':'+ el.checked } ).join() === 'checkbox:shop:false' );
const featureNames = findAll( byId('applications-start'), function( el ) { return el.dataset.feature !== undefined } );
check( '...a row carries the name and the description it was given and the features it switches on, each marked by its key with the name it was given',
	findAll( byId('applications-start'), function( el ) { return el.tagName === 'STRONG' } ).map( function( el ) { return el.textContent } ).includes( offer[0].name )
	&& featureNames.slice( 0, 2 ).map( function( el ) { return el.dataset.feature+ '='+ el.textContent } ).join() === 'alpha=Alpha,beta=Beta' );
check( '...both cards are on screen, and no notice', byId('applications-start-card').classList.contains('install-hidden') === false && byId('applications-more-card').classList.contains('install-hidden') === false
	&& byId('applications-notice').classList.contains('install-hidden') === true );
apps.showCurrent();
check( 'a step that was drawn is not asked for again', requests.length === 1 );

// --- nothing picked: on at once, whatever the server says

requests = [];
let result = null;
apps.apply( function( ok, message ) { result = [ ok, message ] } );
check( 'with nothing picked Next goes on without a request', requests.length === 0 && result[0] === true && apps.applied().length === 0 );

// --- applications picked: the plan, then one request per feature, in the order the plan gave

answers['applications/plan'] = [ 200, { features : [ { key : 'alpha', name : 'Alpha' }, { key : 'beta', name : 'Beta' }, { key : 'gamma', name : 'Gamma' } ] } ];
answers['applications/apply'] = function( payload ) { return [ 200, { feature : payload.feature, placed : true, activated : true } ] };
pick( 'applications-start', 'site' );
pick( 'applications-more', 'shop' );
inputs('applications-more')[0].checked = true;
requests = [];
apps.apply( function( ok, message ) { result = [ ok, message ] } );
check( 'the picked keys are posted for the plan, then each feature of it once, in its order',
	requests.map( function( r ) { return r.action+ ( r.payload.feature ? ':'+ r.payload.feature : '' ) } ).join() === 'applications/plan,applications/apply:alpha,applications/apply:beta,applications/apply:gamma'
	&& requests.every( function( r ) { return r.payload.applications.join() === 'site,shop' } ) );
check( '...every feature is drawn, with its state as a word', byId('applications-progress').children.length === 3
	&& byId('applications-progress').children.every( function( li ) { return li.dataset.state === 'on' } ) );
check( '...Next goes on, and the applications that went through are what the last step reports', result[0] === true && apps.applied().join() === 'site,shop' );
check( '...and the list given out is a copy', ( function() { const copy = apps.applied(); copy.push( 'x' ); return apps.applied().length === 2 } )() );

// --- a failure stays in the step

let failOn = 'beta';
answers['applications/apply'] = function( payload ) {
	return payload.feature === failOn ? [ 400, { error: 'refused', code : 'install_feature' } ] : [ 200, { feature : payload.feature, placed : true, activated : true } ];
};
requests = [];
result = null;
apps.apply( function( ok, message ) { result = [ ok, message ] } );
check( 'a feature that fails ends the round there: the ones after it are not asked for', requests.filter( function( r ) { return r.action === 'applications/apply' } ).map( function( r ) { return r.payload.feature } ).join() === 'alpha,beta'
	&& requests[requests.length - 1].action === 'applications/list' );
check( '...Next stays, with a sentence for the bar', result[0] === false && typeof result[1] === 'string' && result[1] !== '' && apps.applied().length === 0 );
check( '...the step names the feature and why, and offers both ways on', byId('applications-failure').classList.contains('install-hidden') === false
	&& byId('applications-failure').dataset.feature === 'beta' && byId('applications-failure').dataset.code === 'install_feature' && byId('applications-failure-text').textContent.includes( 'refused' ) );
check( '...the row of the feature is in the state failed, the one before it in the state on, the one after it waits', byId('applications-progress').children.map( function( li ) { return li.dataset.state } ).slice( 0, 3 ).join() === 'on,failed,waiting' );
check( '...and the failure takes the focus, for a reader who cannot see it appear', byId('applications-failure-text').focused === true );

// "Try again" asks again, and the answer goes the way of the step
failOn = '';
requests = [];
shown.length = 0;
fire( byId('applications-retry'), 'click' );
check( 'Try again runs the round again with the picked applications', requests.map( function( r ) { return r.payload.feature || r.action } ).join() === 'applications/plan,alpha,beta,gamma' );
check( '...and when it goes through, the wizard moves on and is not busy', shown.join() === '5' && sandbox.Nino.install._busy === false && apps.applied().join() === 'site,shop' );

// "Continue without an application" leaves what is on, on - and says so
failOn = 'beta';
apps.apply( function( ok ) { result = [ ok ] } );
shown.length = 0;
sandbox.Nino.install._index = 4;
fire( byId('applications-skip'), 'click' );
check( 'Continue without an application moves on with nothing applied and nothing picked', shown.join() === '5' && apps.applied().length === 0 && apps._selected().length === 0 );
check( '...and leaves a message for the last step, because features were switched on', byId('finish-msg').textContent !== '' );
check( '...the radio group is back on "none"', inputs('applications-start')[0].checked === true );

// A plan that cannot be made is a failure of the round too
answers['applications/plan'] = [ 409, { error: 'refused', code : 'install_missing' } ];
pick( 'applications-start', 'site' );
apps.apply( function( ok ) { result = [ ok ] } );
check( 'a plan that is refused stays in the step with the code of the refusal and no feature', result[0] === false && byId('applications-failure').dataset.code === 'install_missing' && byId('applications-failure').dataset.feature === ''
	&& byId('applications-failure').classList.contains('install-hidden') === false );

// --- the catalogue is not there: a sentence, "Try again", and the way without an application

answers['applications/list'] = [ 200, { applications : [], missing : [ { key : 'openssl', text : 'the openssl extension, which checks the signature of the catalogue' } ], message : 'Loading an application needs what this server lacks.', retry : true } ];
apps._ready = false;
apps.showCurrent();
check( 'what the server lacks is said in a sentence and a list, with "Try again"', byId('applications-notice').classList.contains('install-hidden') === false
	&& byId('applications-notice-text').textContent === 'Loading an application needs what this server lacks.' && byId('applications-missing').children.map( function( li ) { return li.dataset.missing } ).join() === 'openssl'
	&& byId('applications-reload').classList.contains('install-hidden') === false );
check( '...no card with applications is on screen', byId('applications-start-card').classList.contains('install-hidden') === true && byId('applications-more-card').classList.contains('install-hidden') === true );
check( '...and a step that could be asked again is asked again the next time it is shown', apps._ready === false );
requests = [];
pick( 'applications-start', '' );
apps.apply( function( ok ) { result = [ ok ] } );
check( '...while Next with nothing picked goes on', result[0] === true && requests.length === 0 );

answers['applications/list'] = [ 200, { applications : [], missing : [], message : 'This project names another catalogue or switches the catalogue off, so the wizard offers no applications.', retry : false } ];
apps._ready = false;
apps.showCurrent();
check( 'a project that does not use the official catalogue gets a sentence and no "Try again"', byId('applications-notice').classList.contains('install-hidden') === false && byId('applications-reload').classList.contains('install-hidden') === true && apps._ready === true );

answers['applications/list'] = [ 500, { error : 'Internal' } ];
apps._ready = false;
apps.showCurrent();
check( 'a request that fails is a sentence with the status and "Try again", not a step that cannot be left', byId('applications-notice-text').textContent.includes( '500' ) && byId('applications-notice-text').textContent.includes( 'Internal' ) && byId('applications-reload').classList.contains('install-hidden') === false );

// What was picked stays picked when the offer is drawn again
answers['applications/list'] = [ 200, { applications : offer, missing : [], message : '', retry : false } ];
apps._ready = false;
apps.showCurrent();
pick( 'applications-start', 'card' );
apps._ready = false;
apps.showCurrent();
check( 'an offer drawn again keeps what was picked', apps._selected().join() === 'card' );

// --- None empties what the last step is told

answers['applications/list'] = [ 200, { applications : offer, missing : [], message : '', retry : false, startApplied : null } ];
answers['applications/plan'] = [ 200, { features : [ { key : 'alpha', name : 'Alpha' } ] } ];
answers['applications/apply'] = function( payload ) { return [ 200, { feature : payload.feature, placed : true, activated : true } ] };
apps._ready = false;
apps.showCurrent();
pick( 'applications-start', 'site' );
apps.apply( function( ok ) { result = [ ok ] } );
check( 'what went through is what is reported', apps.applied().join() === 'site' );
pick( 'applications-start', '' );
apps.apply( function( ok ) { result = [ ok ] } );
check( '...and None, picked afterwards, empties it: nothing is reported for an application nobody chose', result[0] === true && apps.applied().length === 0 );

// --- A start application that is set up stays the one

const setUp = offer.map( function( app ) { return Object.assign( {}, app, { applied : app.key === 'site' || app.key === 'shop', entry : app.key !== 'card' } ) } );
answers['applications/list'] = [ 200, { applications : setUp, missing : [], message : '', retry : false, startApplied : 'site' } ];
apps._ready = false;
apps.showCurrent();
const radios = inputs('applications-start');
check( 'with a start application set up the radio group is fixed on it and cannot be changed', radios.every( function( el ) { return el.disabled === true } ) && radios.filter( function( el ) { return el.checked } ).map( function( el ) { return el.value } ).join() === 'site'
	&& byId('applications-start-card').dataset.state === 'locked' && byId('applications-start-locked').classList.contains('install-hidden') === false && byId('applications-start-locked').textContent !== '' );
check( '...what is set up of the other applications is checked and fixed', inputs('applications-more').map( function( el ) { return el.value+ ':'+ el.checked+ ':'+ el.disabled } ).join() === 'shop:true:true' );
check( '...and what is chosen is what is set up: the keys are reported to the server and none else', apps._selected().join() === 'site,shop' );
apps.apply( function( ok ) { result = [ ok ] } );
check( 'Next applies nothing new and goes on, reporting both', result[0] === true && apps.applied().join() === 'site,shop' );
check( 'the entry points are those of what went through that has one', apps.appliedEntries().map( function( e ) { return e.key } ).join() === 'site,shop' );

shown.length = 0;
sandbox.Nino.install._index = 4;
fire( byId('applications-skip'), 'click' );
check( 'Continue without an application lets go of nothing that is set up', shown.join() === '5' && apps.applied().join() === 'site,shop' && apps._selected().join() === 'site,shop' );

// Without the catalogue what is set up is shown, fixed, and goes on
answers['applications/list'] = [ 200, { applications : setUp.concat( [ { key : 'crm', name : 'CRM', description : '', features : [], start : null, applied : false, entry : true } ] ), missing : [], message : 'unreachable', retry : true, reason : 'install_catalogue', offline : true, startApplied : 'site' } ];
apps._ready = false;
apps.showCurrent();
check( 'where the catalogue cannot be reached the choice that is set up is shown, fixed, and nothing else can be chosen',
	inputs('applications-start').every( function( el ) { return el.disabled === true } ) && inputs('applications-more').every( function( el ) { return el.disabled === true } )
	&& inputs('applications-more').length === 2 && apps._selected().join() === 'site,shop' && byId('applications-start-card').dataset.state === 'locked' && byId('applications-notice').classList.contains('install-hidden') === false
	&& byId('applications-reload').classList.contains('install-hidden') === false && apps._ready === false );
requests = [];
apps._applied = [];
apps.apply( function( ok ) { result = [ ok ] } );
check( '...Next asks for no plan, and reports what is set up', requests.length === 0 && result[0] === true && apps.applied().join() === 'site,shop' );
answers['applications/list'] = [ 200, { applications : [ { key : 'shop', name : 'Shop', description : '', features : [], start : null, applied : true, entry : true } ], missing : [], message : 'unreachable', retry : true, offline : true, startApplied : null } ];
apps._ready = false;
apps.showCurrent();
check( '...with no start application set up the group is fixed all the same, and the sentence says why', inputs('applications-start').length === 0 && byId('applications-start-locked').textContent !== '' && inputs('applications-more').every( function( el ) { return el.disabled === true } ) );

// A larger one whose application stops half way: the smaller is on, and the choice stays the larger's and open
const larger = offer.concat( [ { key : 'big', name : 'Big', description : 'Big', features : [ { key : 'alpha', name : 'Alpha' }, { key : 'beta', name : 'Beta' }, { key : 'gamma', name : 'Gamma' } ], start : 'starter', applied : false, entry : false } ] );
answers['applications/list'] = [ 200, { applications : larger.map( function( app ) { return Object.assign( {}, app, { applied : app.key === 'site' } ) } ), missing : [], message : '', retry : false, startApplied : null } ];
apps._ready = false;
apps.showCurrent();
pick( 'applications-start', 'big' );
answers['applications/list'] = [ 200, { applications : larger.map( function( app ) { return Object.assign( {}, app, { applied : app.key === 'site' } ) } ), missing : [], message : '', retry : false, startApplied : 'site' } ];
apps._ready = false;
apps.showCurrent();
check( 'while a larger start application is being applied the smaller that is on does not take the choice: it stays on the larger one, open', inputs('applications-start').every( function( el ) { return el.disabled === false } )
	&& apps._selected().filter( function( key ) { return key === 'big' || key === 'site' } ).join() === 'big' && byId('applications-start-card').dataset.state === 'open' );

// An application that is not set up is drawn open again where none of the start group is
answers['applications/list'] = [ 200, { applications : offer, missing : [], message : '', retry : false, startApplied : null } ];
apps._ready = false;
apps.showCurrent();
check( 'where no start application is set up the group is open', inputs('applications-start').every( function( el ) { return el.disabled === false } ) && byId('applications-start-card').dataset.state === 'open' && byId('applications-start-locked').classList.contains('install-hidden') === true );


done();
