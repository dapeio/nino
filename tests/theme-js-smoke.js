/**
 *	Nino				A compact filesystembased php framework
 *	theme-js-smoke.js	DOM contracts for /_theme's four independent editors.
 *
 *	Usage: node tests/theme-js-smoke.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

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

function element( id, tag ) {

	const el = {
		id : id,
		tagName : ( tag || 'div' ).toUpperCase(),
		className : '',
		value : '',
		textContent : '',
		title : '',
		disabled : false,
		checked : false,
		dataset : {},
		style : {},
		children : [],
		_listeners : {},
		classList : {
			_set : new Set(),
			add : function( name ) { el.classList._set.add( name ); },
			remove : function( name ) { el.classList._set.delete( name ); },
			contains : function( name ) { return el.classList._set.has( name ); },
			toggle : function( name, on ) {
				if( on === true ) el.classList.add( name );
				else el.classList.remove( name );
			},
		},
		appendChild : function( child ) { el.children.push( child ); return child; },
		removeAttribute : function( name ) { if( name === 'srcdoc' ) el.srcdoc = null; },
		addEventListener : function( type, handler ) { ( el._listeners[type] = el._listeners[type] || [] ).push( handler ); },
		fire : function( type ) {
			( el._listeners[type] || [] ).forEach( function( handler ) {
				handler( { preventDefault : function() {} } );
			} );
		},
	};

	Object.defineProperty( el, 'innerHTML', {
		get : function() { return ''; },
		set : function() { el.children.length = 0; },
	} );

	return el;
}

const nodes = {};
[
	'theme-page-wrap', 'theme-pane', 'theme-grid', 'theme-empty',
	'theme-action-status', 'theme-action-save',
	'theme-nav-theme', 'theme-nav-design', 'theme-nav-header', 'theme-nav-footer',
	'theme-design-controls', 'theme-design-preview-light', 'theme-design-preview-dark', 'theme-design-sizes',
	'theme-design-primary', 'theme-design-primary-hex', 'theme-design-secondary', 'theme-design-secondary-hex',
	'theme-design-contrast', 'theme-design-colors', 'theme-design-volume', 'theme-design-spacing', 'theme-design-shaping',
	'theme-frame-header-panel', 'theme-frame-footer-panel', 'theme-frame-header-empty', 'theme-frame-footer-empty',
	'theme-frame-header', 'theme-frame-footer', 'theme-frame-header-preview', 'theme-frame-footer-preview',
].forEach( function( id ) { nodes[id] = element( id ); } );

nodes['theme-page-wrap'].classList.add('show-theme');

let appearance = {
	themes : {
		basis : { label : 'Basis', description : 'Neutral starting point', preview : '/basis.svg', header : 'v1', footer : 'v1' },
		nocturne : { label : 'Nocturne', description : '<b>server text stays text</b>', preview : '/nocturne.svg', header : 'v5', footer : 'v2' },
	},
	activeTheme : 'basis',
	frames : { header : [ 'v1', 'v2', 'v5' ], footer : [ 'v1', 'v2' ] },
	activeFrames : { header : 'v1', footer : 'v1' },
};

const designRead = {
	settings : { primary : '#b6a6ff', secondary : '', contrast : 'high', colors : 'vibrant', volume : 'default', spacing : 'default', shaping : 'round' },
	choices : {
		contrast : [ 'soft', 'default', 'high' ],
		colors : [ 'clean', 'default', 'vibrant' ],
		volume : [ 'compact', 'default', 'generous' ],
		spacing : [ 'tight', 'default', 'airy' ],
		shaping : [ 'sharp', 'default', 'round' ],
	},
	palette : {
		light : { default : { bg : '#ffffff', on : '#111111', border : '#999999' }, origin : { bg : '#147bb7', on : '#ffffff', border : '#c2d3df' } },
		dark : { default : { bg : '#111111', on : '#ffffff', border : '#777777' }, origin : { bg : '#65b9e9', on : '#111111', border : '#c2d3df' } },
	},
	raster : {
		text : { 1 : '1rem', 4 : '1.5rem', 6 : '2.5rem' },
		space : { 1 : '0.5rem', 2 : '1rem', 3 : '2rem' },
		radius : { 1 : '0.1rem', 2 : '0.3rem', 3 : '0.7rem' },
		lineHeight : '1.5',
	},
};

const posts = [];

const sandbox = {
	console : console,
	location : { search : '' },
	URLSearchParams : URLSearchParams,
	setTimeout : function( callback ) { callback(); return 1; },
	clearTimeout : function() {},
	document : {
		getElementById : function( id ) { return nodes[id] || null; },
		createElement : function( tag ) { return element( '', tag ); },
	},
};
sandbox.window = sandbox;
sandbox.Nino = {
	http : {
		sendRequest : function( uri, method, callback, data ) {
			const payload = JSON.parse( data.data || '{}' );
			posts.push( { uri : uri, method : method, action : data.action, payload : JSON.parse( JSON.stringify( payload ) ) } );

			let response = {};
			if( data.action === 'appearance/read' ) response = appearance;
			if( data.action === 'theme/apply' ) {
				appearance = Object.assign( {}, appearance, { activeTheme : payload.theme, activeFrames : { header : 'v5', footer : 'v2' } } );
				response = { theme : payload.theme, header : 'v5', footer : 'v2' };
			}
			if( data.action === 'design/read' ) response = designRead;
			if( data.action === 'design/preview' ) response = designRead;
			if( data.action === 'design/save' ) response = { settings : payload };
			if( data.action === 'frame/preview' ) response = { frame : payload.frame, html : '<!doctype html><body data-frame="'+ payload.kind+ '/'+ payload.frame+ '">' };
			if( data.action === 'frame/apply' ) response = { kind : payload.kind, frame : payload.frame };

			callback( { status : 200, responseJSON : response } );
		},
	},
	events : { bindCallback : function() {} },
};

const source = fs.readFileSync( path.join( __dirname, '../_theme/assets/theme.js' ), 'utf8' );
vm.runInContext( source, vm.createContext( sandbox ), { filename : 'theme.js' } );

const theme = sandbox.Nino.theme;
theme.init();

check( 'the tool exposes exactly the four requested dialogs', JSON.stringify( theme.TABS ) === JSON.stringify( [ 'theme', 'design', 'header', 'footer' ] )
	&& nodes['theme-nav-theme']._listeners.click.length === 1
	&& nodes['theme-nav-footer']._listeners.click.length === 1 );
check( 'Theme opens first and loads the shared appearance catalogue', nodes['theme-page-wrap'].classList.contains('show-theme') === true
	&& posts[0].action === 'appearance/read'
	&& nodes['theme-grid'].children.length === 2 );
check( 'the applied Theme is also the selected Theme', theme._activeTheme === 'basis'
	&& theme._selectedTheme === 'basis'
	&& nodes['theme-grid'].children[0].classList.contains('theme-tile--active') === true
	&& nodes['theme-grid'].children[0].classList.contains('theme-tile--selected') === true );
check( 'API descriptions are inserted as text, never interpreted as markup', nodes['theme-grid'].children[1].children[2].children[1].textContent === '<b>server text stays text</b>' );

// Pick the second card through its radio and apply only that Theme choice.
const nocturneRadio = nodes['theme-grid'].children[1].children[0];
nocturneRadio.checked = true;
nocturneRadio.fire('change');
nodes['theme-action-save'].fire('click');

const themeApply = posts.filter( function( post ) { return post.action === 'theme/apply'; } )[0];
check( 'Theme apply posts only the picked Theme', themeApply !== undefined
	&& JSON.stringify( themeApply.payload ) === JSON.stringify( { theme : 'nocturne' } ) );
check( 'a successful Theme apply refreshes the complete baseline', theme._activeTheme === 'nocturne'
	&& theme._activeFrames.header === 'v5'
	&& theme._activeFrames.footer === 'v2'
	&& nodes['theme-action-status'].textContent.indexOf('recommended baseline') !== -1 );

// Design owns the generated settings and nothing else.
nodes['theme-nav-design'].fire('click');
check( 'Design has its own pane and action', nodes['theme-page-wrap'].classList.contains('show-design') === true
	&& nodes['theme-page-wrap'].classList.contains('show-theme') === false
	&& nodes['theme-action-save'].textContent === 'Save Design'
	&& posts.filter( function( post ) { return post.action === 'design/read'; } ).length === 1 );
check( 'Design renders every server-owned choice and both colour modes', nodes['theme-design-volume'].children.length === 3
	&& nodes['theme-design-shaping'].value === 'round'
	&& nodes['theme-design-preview-light'].children.length === 2
	&& nodes['theme-design-preview-dark'].children.length === 2 );
check( 'the size specimen uses the generated sizes', nodes['theme-design-sizes'].children[0].children[0].style.fontSize === '2.5rem'
	&& nodes['theme-design-sizes'].children[1].children[2].children[0].style.width === '2rem'
	&& nodes['theme-design-sizes'].children[2].children[2].style.borderRadius === '0.7rem' );

posts.length = 0;
nodes['theme-design-spacing'].value = 'tight';
nodes['theme-design-spacing'].fire('change');
check( 'a Design change asks the server for a preview without changing Theme or frames', posts.length === 1
	&& posts[0].action === 'design/preview'
	&& posts[0].payload.spacing === 'tight'
	&& typeof posts[0].payload.design === 'undefined' );

nodes['theme-action-save'].fire('click');
const designSave = posts.filter( function( post ) { return post.action === 'design/save'; } )[0];
check( 'Design save posts the settings directly and only to design/save', designSave !== undefined
	&& designSave.payload.spacing === 'tight'
	&& typeof designSave.payload.theme === 'undefined'
	&& typeof designSave.payload.header === 'undefined' );

// Header and Footer each preview and apply one frame only.
posts.length = 0;
nodes['theme-nav-header'].fire('click');
check( 'Header opens on the active frame and renders it server-side', nodes['theme-page-wrap'].classList.contains('show-header') === true
	&& nodes['theme-frame-header'].value === 'v5'
	&& nodes['theme-frame-header-preview'].srcdoc === '<!doctype html><body data-frame="header/v5">'
	&& posts[0].action === 'frame/preview'
	&& posts[0].payload.theme === 'nocturne'
	&& posts[0].payload.design.spacing === 'tight' );

posts.length = 0;
nodes['theme-frame-header'].value = 'v2';
nodes['theme-frame-header'].fire('change');
nodes['theme-action-save'].fire('click');
const headerApply = posts.filter( function( post ) { return post.action === 'frame/apply'; } )[0];
check( 'Header applies only kind and frame', JSON.stringify( headerApply.payload ) === JSON.stringify( { kind : 'header', frame : 'v2' } )
	&& theme._activeFrames.header === 'v2'
	&& theme._activeFrames.footer === 'v2' );

posts.length = 0;
nodes['theme-nav-footer'].fire('click');
nodes['theme-frame-footer'].value = 'v1';
nodes['theme-frame-footer'].fire('change');
nodes['theme-action-save'].fire('click');
const footerApply = posts.filter( function( post ) { return post.action === 'frame/apply'; } )[0];
check( 'Footer is an independent pane and applies only its own frame', nodes['theme-page-wrap'].classList.contains('show-footer') === true
	&& nodes['theme-action-save'].textContent === 'Apply Footer'
	&& JSON.stringify( footerApply.payload ) === JSON.stringify( { kind : 'footer', frame : 'v1' } )
	&& theme._activeFrames.header === 'v2'
	&& theme._activeFrames.footer === 'v1' );

const template = fs.readFileSync( path.join( __dirname, '../_theme/templates/page-index.tpl' ), 'utf8' );
const css = fs.readFileSync( path.join( __dirname, '../_theme/assets/style.css' ), 'utf8' );
const adminTemplate = fs.readFileSync( path.join( __dirname, '../_admin/templates/page-index.tpl' ), 'utf8' );
const templatesTemplate = fs.readFileSync( path.join( __dirname, '../_templates/templates/page-index.tpl' ), 'utf8' );

check( 'template navigation and content contain one matching pair per dialog', [ 'theme', 'design', 'header', 'footer' ].every( function( tab ) {
	return template.indexOf('id="theme-nav-'+ tab+ '"') !== -1 && template.indexOf('id="theme-content-'+ tab+ '"') !== -1;
} ) );
check( 'one shared action bar changes responsibility with the active dialog', ( template.match(/id="theme-action-save"/g) || [] ).length === 1
	&& source.indexOf("theme \t: 'Apply Theme'") !== -1
	&& source.indexOf("design \t: 'Save Design'") !== -1
	&& template.indexOf('class="nino-admin-btn-primary" id="theme-action-save"') !== -1 );
check( 'all authenticated tools mount the same availability-aware bridge',
	adminTemplate.includes('[admin-tools admin]')
	&& templatesTemplate.includes('[admin-tools templates]')
	&& template.includes('[admin-tools theme]')
	&& adminTemplate.includes('id="admin-theme"') === false
	&& templatesTemplate.includes('pd-back-admin') === false );
check( 'the local stylesheet has an explicit visibility contract for all four panes', [ 'theme', 'design', 'header', 'footer' ].every( function( tab ) {
	return css.indexOf('#theme-page-wrap.show-'+ tab+ ' #theme-content-'+ tab) !== -1;
} ) );

console.log( '\n'+ checks+ ' checks, '+ failures+ ' failed' );
process.exit( failures === 0 ? 0 : 1 );
