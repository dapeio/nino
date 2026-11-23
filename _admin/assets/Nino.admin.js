/**
 *	Nino										A compact filesystembased php framework
 *	Nino.admin.js						The shared behaviour half of the management design system:
 *													small DOM primitives the tool frontends build their
 *													chrome from, the pure table model behind the list
 *													views, the lifecycle of a panel, and the one way a
 *													panel talks to the server: Nino.adminUi.api, with the
 *													status line and the messages that say what failed.
 *													style.css is the vocabulary, this is what assembles it.
 *
 *													Separate from Nino.js for the reason of who needs it. Nino.js is in the public site's script
 *													bundle, so every visitor was being served the complete
 *													table renderer, the field builders and the switch - none
 *													of which a page has any use for. Two files, two
 *													audiences: Nino.js everywhere, this one in the tool
 *													frontends.
 *
 *													Loaded after Nino.js, which owns the namespace this
 *													extends - on its own it has nothing to attach to.
 *													Names no Nino.admin member at load, which is what lets
 *													the setup wizard load it without the workbench shell
 *													in assets/script.js: panel() and levels() look the
 *													shell's dirty registry and router up when a panel asks
 *													for them, feature-detected, as api looks Nino.http up
 *													when a request is made, and work without them.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	if( typeof wn.Nino === 'undefined' )
		return;

	/**
	 *	The workbench shell's namespace, looked up when a panel asks for it and
	 *	never at load: this file runs on the setup wizard's page as well, where
	 *	script.js - and with it Nino.admin - does not exist, and a panel of a
	 *	project or a feature may be older than the shell that runs it. Whoever
	 *	asks feature-detects what it wants of it (the dirty registry, the router)
	 *
	 *	@return		{Object}									Nino.admin, or an empty object where there is none
	 */
	const shell = function() {
		return typeof Nino.admin === 'object' && Nino.admin !== null ? Nino.admin : {};
	};

	/**
	 *	Small DOM primitives shared by Nino's administration surfaces.
	 *	They only assign the common chrome classes from style.css;
	 *	application modules keep ownership of labels, state and events.
	 */
	// The counter behind the ids the building blocks make when they are given none
	let serial = 0;

	// Whether the side column of a split view (split() with an aside) is folded, for each view that has an id: held for the session, so that a view that is
	// drawn again - or the page, in a browser that has a sessionStorage - finds the column as it was left, as the rail does for the sidebar
	const ASIDE_FOLDED = {};
	const ASIDE_KEY = 'nino-admin-aside-';

	// How long the line of a saveBar() holds the sentence "unsaved changes" before it goes: the badge says the state from then on
	const SAID_FOR = 4000;

	// How long a save of a saveBar() is on its way before the line says "Saving", in milliseconds
	const SAVING_AFTER = 400;

	// How long the toast of say() stays once nothing holds it, in milliseconds
	const SAY_FOR = 4000;

	// The kinds a toast may have: it says what a person did and went well - an error stays where it happened
	const SAY_KINDS = [ 'success', 'info' ];

	// Whether a value is a DOM element - an options object has no tag
	const isElement = function( value ) {
		return value !== null && typeof value === 'object' && typeof value.tagName === 'string';
	};

	// What the badge of a bar says: "● Changed" while there is something to save, "✓ Saved" (or the caller's calm word) where what is
	// on the screen is what is stored - and nothing for a thing that was never saved, until the first change. fresh sets or takes
	// that state; it stays on the badge until a call takes it off
	const paintBadge = function( badge, now, word ) {

		const calm = typeof now.calm === 'string' ? now.calm : '';

		if( typeof now.fresh === 'boolean' )
			badge.dataset.fresh = now.fresh === true ? 'true' : '';

		badge.hidden = now.dirty !== true && calm === '' && badge.dataset.fresh === 'true';
		badge.dataset.state = now.dirty === true ? 'warn' : ( calm !== '' ? 'muted' : 'ok' );
		badge.textContent = badge.hidden === true ? '' : ( now.dirty === true ? '\u25CF '+ word( 'changed', '/_admin/common/savebar/changed' ) : '\u2713 '+ ( calm !== '' ? calm : word( 'saved', '/_admin/common/savebar/saved' ) ) );
	};

	// The icons of icon(): Lucide 0.577.0 (ISC, notice in THIRD-PARTY-NOTICES.md), the geometry of each on its
	// 24 by 24 grid - a string is the "d" of a path, anything else is [ tag, attributes ]. This is the one place
	// the scripts of the workbench take an icon from; the rail's and the panels' own icons are markup of the
	// pages, and a table in a script is the cheapest way to have the same drawings without a request or a build
	const ICONS = {
		'align-center' : [ 'M21 5H3', 'M17 12H7', 'M19 19H5' ],
		'align-center-horizontal' : [ 'M2 12h20', 'M10 16v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4', 'M10 8V4a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v4', 'M20 16v1a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-1', 'M14 8V7c0-1.1.9-2 2-2h2a2 2 0 0 1 2 2v1' ],
		'align-center-vertical' : [ 'M12 2v20', 'M8 10H4a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h4', 'M16 10h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-4', 'M8 20H7a2 2 0 0 1-2-2v-2c0-1.1.9-2 2-2h1', 'M16 14h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-1' ],
		'align-end-horizontal' : [ [ 'rect', { width : '6', height : '16', x : '4', y : '2', rx : '2' } ], [ 'rect', { width : '6', height : '9', x : '14', y : '9', rx : '2' } ], 'M22 22H2' ],
		'align-end-vertical' : [ [ 'rect', { width : '16', height : '6', x : '2', y : '4', rx : '2' } ], [ 'rect', { width : '9', height : '6', x : '9', y : '14', rx : '2' } ], 'M22 22V2' ],
		'align-horizontal-space-between' : [ [ 'rect', { width : '6', height : '14', x : '3', y : '5', rx : '2' } ], [ 'rect', { width : '6', height : '10', x : '15', y : '7', rx : '2' } ], 'M3 2v20', 'M21 2v20' ],
		'align-justify' : [ 'M3 5h18', 'M3 12h18', 'M3 19h18' ],
		'align-left' : [ 'M21 5H3', 'M15 12H3', 'M17 19H3' ],
		'align-right' : [ 'M21 5H3', 'M21 12H9', 'M21 19H7' ],
		'align-start-horizontal' : [ [ 'rect', { width : '6', height : '16', x : '4', y : '6', rx : '2' } ], [ 'rect', { width : '6', height : '9', x : '14', y : '6', rx : '2' } ], 'M22 2H2' ],
		'align-start-vertical' : [ [ 'rect', { width : '9', height : '6', x : '6', y : '14', rx : '2' } ], [ 'rect', { width : '16', height : '6', x : '6', y : '4', rx : '2' } ], 'M2 2v20' ],
		'arrow-down' : [ 'M12 5v14', 'm19 12-7 7-7-7' ],
		'arrow-left' : [ 'm12 19-7-7 7-7', 'M19 12H5' ],
		'arrow-left-right' : [ 'M8 3 4 7l4 4', 'M4 7h16', 'm16 21 4-4-4-4', 'M20 17H4' ],
		'arrow-right' : [ 'M5 12h14', 'm12 5 7 7-7 7' ],
		'arrow-up' : [ 'm5 12 7-7 7 7', 'M12 19V5' ],
		brackets : [ 'M16 3h3a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-3', 'M8 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h3' ],
		check : [ 'M20 6 9 17l-5-5' ],
		'chevron-down' : [ 'm6 9 6 6 6-6' ],
		'chevron-left' : [ 'm15 18-6-6 6-6' ],
		'chevron-right' : [ 'm9 18 6-6-6-6' ],
		'chevron-up' : [ 'm18 15-6-6-6 6' ],
		'chevrons-left' : [ 'm11 17-5-5 5-5', 'm18 17-5-5 5-5' ],
		'chevrons-right' : [ 'm6 17 5-5-5-5', 'm13 17 5-5-5-5' ],
		'circle-help' : [ [ 'circle', { cx : '12', cy : '12', r : '10' } ], 'M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3', 'M12 17h.01' ],
		clock : [ [ 'circle', { cx : '12', cy : '12', r : '10' } ], 'M12 6v6l4 2' ],
		code : [ 'm16 18 6-6-6-6', 'm8 6-6 6 6 6' ],
		'columns-3' : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ], 'M9 3v18', 'M15 3v18' ],
		contact : [ 'M16 2v2', 'M7 22v-2a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2', 'M8 2v2', [ 'circle', { cx : '12', cy : '11', r : '3' } ], [ 'rect', { x : '3', y : '4', width : '18', height : '18', rx : '2' } ] ],
		cookie : [ 'M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5', 'M8.5 8.5v.01', 'M16 15.5v.01', 'M12 12v.01', 'M11 17v.01', 'M7 14v.01' ],
		copy : [ [ 'rect', { width : '14', height : '14', x : '8', y : '8', rx : '2', ry : '2' } ], 'M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2' ],
		database : [ [ 'ellipse', { cx : '12', cy : '5', rx : '9', ry : '3' } ], 'M3 5V19A9 3 0 0 0 21 19V5', 'M3 12A9 3 0 0 0 21 12' ],
		ellipsis : [ [ 'circle', { cx : '12', cy : '12', r : '1' } ], [ 'circle', { cx : '19', cy : '12', r : '1' } ], [ 'circle', { cx : '5', cy : '12', r : '1' } ] ],
		'external-link' : [ 'M15 3h6v6', 'M10 14 21 3', 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' ],
		eye : [ 'M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0', [ 'circle', { cx : '12', cy : '12', r : '3' } ] ],
		'eye-off' : [ 'M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49', 'M14.084 14.158a3 3 0 0 1-4.242-4.242', 'M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143', 'm2 2 20 20' ],
		gauge : [ 'm12 14 4-4', 'M3.34 19a10 10 0 1 1 17.32 0' ],
		hand : [ 'M18 11V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2', 'M14 10V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2', 'M10 10.5V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2v8', 'M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15' ],
		heading : [ 'M6 12h12', 'M6 20V4', 'M18 20V4' ],
		image : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2', ry : '2' } ], [ 'circle', { cx : '9', cy : '9', r : '2' } ], 'm21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21' ],
		images : [ 'm22 11-1.296-1.296a2.4 2.4 0 0 0-3.408 0L11 16', 'M4 8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2', [ 'circle', { cx : '13', cy : '7', r : '1', fill : 'currentColor' } ], [ 'rect', { x : '8', y : '2', width : '14', height : '14', rx : '2' } ] ],
		list : [ 'M3 5h.01', 'M3 12h.01', 'M3 19h.01', 'M8 5h13', 'M8 12h13', 'M8 19h13' ],
		lock : [ [ 'rect', { width : '18', height : '11', x : '3', y : '11', rx : '2', ry : '2' } ], 'M7 11V7a5 5 0 0 1 10 0v4' ],
		mail : [ 'm22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7', [ 'rect', { x : '2', y : '4', width : '20', height : '16', rx : '2' } ] ],
		menu : [ 'M4 5h16', 'M4 12h16', 'M4 19h16' ],
		monitor : [ [ 'rect', { width : '20', height : '14', x : '2', y : '3', rx : '2' } ], 'M8 21h8', 'M12 17v4' ],
		'mouse-pointer-click' : [ 'M14 4.1 12 6', 'm5.1 8-2.9-.8', 'm6 12-1.9 2', 'M7.2 2.2 8 5.1', 'M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z' ],
		'move-horizontal' : [ 'm18 8 4 4-4 4', 'M2 12h20', 'm6 8-4 4 4 4' ],
		newspaper : [ 'M15 18h-5', 'M18 14h-8', 'M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-4 0v-9a2 2 0 0 1 2-2h2', [ 'rect', { width : '8', height : '4', x : '10', y : '6', rx : '1' } ] ],
		'panel-bottom' : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ], 'M3 15h18' ],
		'panel-top' : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ], 'M3 9h18' ],
		'panels-top-left' : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ], 'M3 9h18', 'M9 21V9' ],
		pencil : [ 'M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z', 'm15 5 4 4' ],
		play : [ 'M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z' ],
		plus : [ 'M5 12h14', 'M12 5v14' ],
		'rectangle-horizontal' : [ [ 'rect', { width : '20', height : '12', x : '2', y : '6', rx : '2' } ] ],
		'redo-2' : [ 'm15 14 5-5-5-5', 'M20 9H9.5A5.5 5.5 0 0 0 4 14.5A5.5 5.5 0 0 0 9.5 20H13' ],
		'refresh-cw' : [ 'M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8', 'M21 3v5h-5', 'M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16', 'M8 16H3v5' ],
		repeat : [ 'm17 2 4 4-4 4', 'M3 11v-1a4 4 0 0 1 4-4h14', 'm7 22-4-4 4-4', 'M21 13v1a4 4 0 0 1-4 4H3' ],
		'rows-2' : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ], 'M3 12h18' ],
		search : [ 'm21 21-4.34-4.34', [ 'circle', { cx : '11', cy : '11', r : '8' } ] ],
		'settings-2' : [ 'M14 17H5', 'M19 7h-9', [ 'circle', { cx : '17', cy : '17', r : '3' } ], [ 'circle', { cx : '7', cy : '7', r : '3' } ] ],
		'share-2' : [ [ 'circle', { cx : '18', cy : '5', r : '3' } ], [ 'circle', { cx : '6', cy : '12', r : '3' } ], [ 'circle', { cx : '18', cy : '19', r : '3' } ], [ 'line', { x1 : '8.59', x2 : '15.42', y1 : '13.51', y2 : '17.49' } ], [ 'line', { x1 : '15.41', x2 : '8.59', y1 : '6.51', y2 : '10.49' } ] ],
		'sliders-horizontal' : [ 'M10 5H3', 'M12 19H3', 'M14 3v4', 'M16 17v4', 'M21 12h-9', 'M21 19h-5', 'M21 5h-7', 'M8 10v4', 'M8 12H3' ],
		smartphone : [ [ 'rect', { width : '14', height : '20', x : '5', y : '2', rx : '2', ry : '2' } ], 'M12 18h.01' ],
		square : [ [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ] ],
		'square-split-horizontal' : [ 'M8 19H5c-1 0-2-1-2-2V7c0-1 1-2 2-2h3', 'M16 5h3c1 0 2 1 2 2v10c0 1-1 2-2 2h-3', [ 'line', { x1 : '12', x2 : '12', y1 : '4', y2 : '20' } ] ],
		'stretch-horizontal' : [ [ 'rect', { width : '20', height : '6', x : '2', y : '4', rx : '2' } ], [ 'rect', { width : '20', height : '6', x : '2', y : '14', rx : '2' } ] ],
		'stretch-vertical' : [ [ 'rect', { width : '6', height : '20', x : '4', y : '2', rx : '2' } ], [ 'rect', { width : '6', height : '20', x : '14', y : '2', rx : '2' } ] ],
		'sun-moon' : [ 'M12 2v2', 'M14.837 16.385a6 6 0 1 1-7.223-7.222c.624-.147.97.66.715 1.248a4 4 0 0 0 5.26 5.259c.589-.255 1.396.09 1.248.715', 'M16 12a4 4 0 0 0-4-4', 'm19 5-1.256 1.256', 'M20 12h2' ],
		table : [ 'M12 3v18', [ 'rect', { width : '18', height : '18', x : '3', y : '3', rx : '2' } ], 'M3 9h18', 'M3 15h18' ],
		tablet : [ [ 'rect', { width : '16', height : '20', x : '4', y : '2', rx : '2', ry : '2' } ], 'M12 18h.01' ],
		text : [ 'M21 5H3', 'M15 12H3', 'M17 19H3' ],
		'text-cursor-input' : [ 'M12 20h-1a2 2 0 0 1-2-2 2 2 0 0 1-2 2H6', 'M13 8h7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-7', 'M5 16H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h1', 'M6 4h1a2 2 0 0 1 2 2 2 2 0 0 1 2-2h1', 'M9 6v12' ],
		timer : [ [ 'line', { x1 : '10', x2 : '14', y1 : '2', y2 : '2' } ], [ 'line', { x1 : '12', x2 : '15', y1 : '14', y2 : '11' } ], [ 'circle', { cx : '12', cy : '14', r : '8' } ] ],
		'trash-2' : [ 'M10 11v6', 'M14 11v6', 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6', 'M3 6h18', 'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' ],
		'triangle-alert' : [ 'm21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3', 'M12 9v4', 'M12 17h.01' ],
		'undo-2' : [ 'M9 14 4 9l5-5', 'M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11' ],
		x : [ 'M18 6 6 18', 'm6 6 12 12' ],
	};

	// The names icon() has warned about, so that a name that is wrong warns once and not at every row it is drawn in
	const UNKNOWN_ICONS = new Set();
	// How many strips tabs() has drawn: the stem of the ids of one that was given none
	let TAB_STRIPS = 0;

	// A moment as the files and the server write it: "2026-11-17", "2026-11-17 06:02", "2026-11-17T06:02:15" - the seconds, where there are
	// some, are read and left out (see dateText())
	const MOMENT = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/;

	// The classes of a button's kind: what button() puts on it for `kind`. A button without a kind has none and
	// looks as the base rule of style.css draws a button
	const BUTTON_KINDS = {
		primary		: 'nino-admin-btn-primary',
		secondary	: 'nino-admin-btn-secondary',
		danger		: 'nino-admin-btn-danger',
		link			: 'nino-admin-linkbutton',
	};

	wn.Nino.adminUi = {

		// How many search hits elementList() draws at once. The search itself
		// always runs over the whole catalogue - this caps the rows, so a type
		// with thousands of elements stays a panel rather than a page, and the
		// control says how many more it found
		ELEMENTLIST_RESULTS : 50,

		// How long color() waits for the typing to rest before it tells a screen reader the
		// contrast line, in milliseconds: a code typed digit by digit passes through valid ones
		// that a live region would otherwise read out one by one
		COLOR_QUIET : 400,


		/**
		 *	The pinned row at the top of a view: the way back, the name of the view and its tools. Its class is
		 *	.nino-admin-contextbar
		 *
		 *	Called with an element it is the back link and the controls: contextBar( backLink, controls ).
		 *	Called with options it builds the link itself, and says where it leads
		 *
		 *	  - back    { to, onClick, href } - the link: "Back to <to>", one wording (/_admin/common/label/back-to). to is the
		 *	            name of the level it leads to, or an element of the pane (a mount, a level) - then it is the name the pane's
		 *	            head says (panelHead()); onClick( event ) is called with the default of the click already prevented
		 *	  - title   the name of the view, shown after the link, a heading the focus goes to when the view opens
		 *	  - code    a quiet identifier beside the title (a page's address, a theme's key); text, no heading
		 *	  - icons   icon buttons beside the title, for what is rarely needed (the frame of a page): a list of
		 *	            { icon, name, onClick, danger, className, id } made by iconButton(), or elements
		 *	  - tools   one element or a list of them, at the right: the tools of the view (previewTools()). Where the bar is
		 *	            narrow - less than 56 rem, the bar's own width - they stand in a menu behind ONE icon button, "Tools"
		 *	            (aria-expanded; Esc, a click beside it, the focus leaving it and a click into the preview close the menu)
		 *	  - panel   the name of the panel, small above the link, for a work surface that has no head of its own: the head of
		 *	            the pane (.admin-panel-head) gives way while the level with this bar is on the screen - its heading stays for
		 *	            a screen reader - and the "?" of its manual is an icon button at the right end of the bar, the same
		 *	            trigger (data-manual) with the same name
		 *	  - panelLabel  false leaves the name of the panel out of the bar - it is not drawn, not even hidden - where the view says it
		 *	            by other means (a title that is the name); the head of the pane still gives way and the "?" still stands in the
		 *	            bar, for panel is what says both. Default true
		 *	  - within  an element of the pane, where the head is looked for when back.to is a name
		 *
		 *	@param		{Element|Object}	[backLink]			The link out of the level, or the options
		 *	@param		{Element|Array}		[controls]			One element or a list of them
		 *
		 *	@return		{Element}														The row
		 */
		contextBar : function( backLink, controls ) {

			const bar = dc.createElement('div');
			bar.className = 'nino-admin-contextbar';

			const list = function( value ) {
				return Array.isArray( value ) ? value : ( value ? [ value ] : [] );
			};

			if( backLink !== null && typeof backLink === 'object' && isElement( backLink ) === false ) {

				const options = backLink;
				const lead = Nino.adminUi.el( 'div', 'nino-admin-contextbar-lead' );

				const within = isElement( options.within ) ? options.within : ( options.back && isElement( options.back.to ) ? options.back.to : null );

				if( typeof options.panel === 'string' && options.panel !== '' ) {
					bar.dataset.panel = options.panel;

					if( options.panelLabel !== false )
						lead.appendChild( Nino.adminUi.el( 'span', 'nino-admin-contextbar-panel', options.panel ) );
				}

				if( options.back !== undefined && options.back !== null ) {

					const to = isElement( options.back.to ) ? Nino.adminUi.panelName( options.back.to ) : String( options.back.to ?? '' );
					const link = Nino.adminUi.el( 'a', 'nino-admin-back-link', Nino.adminUi.format( Nino.adminUi.text('/_admin/common/label/back-to'), to ) );

					link.href = typeof options.back.href === 'string' ? options.back.href : '#';
					link.addEventListener( 'click', function( ev ) {
						ev.preventDefault();
						if( typeof options.back.onClick === 'function' )
							options.back.onClick( ev );
					} );
					lead.appendChild( link );
				}

				bar.appendChild( lead );

				const icons = list( options.icons ).map( function( icon ) {
					return isElement( icon ) ? icon : Nino.adminUi.iconButton( icon.icon, icon.name, icon.onClick, { danger : icon.danger, className : icon.className, id : icon.id } );
				} );
				const hasTitle = typeof options.title === 'string' && options.title !== '';
				const hasCode = typeof options.code === 'string' && options.code !== '';

				// The name of the view, what is quietly beside it and the icons for what is rarely needed: one group, which takes the room the tools leave
				if( hasTitle === true || hasCode === true || icons.length > 0 ) {

					const name = Nino.adminUi.el( 'div', 'nino-admin-contextbar-name' );

					if( hasTitle === true )
						name.appendChild( Object.assign( Nino.adminUi.el( 'h3', 'nino-admin-contextbar-title', options.title ), { title : options.title } ) );

					if( hasCode === true )
						name.appendChild( Object.assign( Nino.adminUi.el( 'span', 'nino-admin-contextbar-code', options.code ), { title : options.code } ) );

					icons.forEach( function( icon ) { name.appendChild( icon ) } );
					bar.appendChild( name );
				}

				const tools = list( options.tools );

				if( tools.length > 0 ) {

					const box = Nino.adminUi.el( 'div', 'nino-admin-contextbar-toolbox' );
					const menu = Nino.adminUi.el( 'div', 'nino-admin-contextbar-tools' );
					const toggle = Nino.adminUi.iconButton( 'sliders-horizontal', Nino.adminUi.text('/_admin/common/preview/tools'), null, { className : 'nino-admin-contextbar-tools-toggle' } );

					menu.id = 'nino-admin-contextbar-tools-'+ ( ++serial );
					toggle.setAttribute( 'aria-expanded', 'false' );
					toggle.setAttribute( 'aria-controls', menu.id );
					tools.forEach( function( tool ) { menu.appendChild( tool ) } );

					const close = function( focus ) {

						bar.dataset.tools = 'closed';
						toggle.setAttribute( 'aria-expanded', 'false' );
						dc.removeEventListener( 'click', beside );
						wn.removeEventListener( 'blur', away );

						if( focus === true )
							toggle.focus();
					};

					// A click beside the menu closes it; the menu itself and its button are not beside it
					const beside = function( ev ) {
						if( box.contains( ev.target ) === false )
							close( false );
					};

					// A click into the preview does not reach this page, but the page loses the focus to the frame
					const away = function() { close( false ) };

					box.addEventListener( 'focusout', function( ev ) {

						// The focus went to something outside the menu and its button: the menu is done. Where it went to nothing (a click on a surface) the click says it
						if( bar.dataset.tools === 'open' && ev.relatedTarget !== null && ev.relatedTarget !== undefined && box.contains( ev.relatedTarget ) === false )
							close( false );
					} );

					toggle.addEventListener( 'click', function() {

						if( bar.dataset.tools === 'open' ) {
							close( false );
							return;
						}

						bar.dataset.tools = 'open';
						toggle.setAttribute( 'aria-expanded', 'true' );
						dc.addEventListener( 'click', beside );
						wn.addEventListener( 'blur', away );
					} );

					bar.addEventListener( 'keydown', function( ev ) {
						if( ev.key === 'Escape' && bar.dataset.tools === 'open' ) {
							ev.stopPropagation();
							close( true );
						}
					} );

					bar.dataset.tools = 'closed';
					box.appendChild( toggle );
					box.appendChild( menu );
					bar.appendChild( box );
				}

				// The head of the pane gives way to this bar, and its "?" goes with it: the same trigger, the same name
				const head = within !== null && typeof options.panel === 'string' && options.panel !== '' ? Nino.adminUi.panelHead( within ) : null;
				const manual = head === null ? null : head.element.querySelector('.admin-panel-manual');

				if( manual !== null ) {
					const help = Nino.adminUi.iconButton( 'circle-help', manual.getAttribute('aria-label') || manual.title || manual.textContent, null, { className : 'nino-admin-contextbar-help' } );
					help.dataset.manual = manual.dataset.manual;
					bar.appendChild( help );
				}

				return bar;
			}

			if( backLink )
				bar.appendChild( backLink );
			list( controls ).forEach( function( control ) {
				bar.appendChild( control );
			} );
			return bar;
		},

		/**
		 *	The name of the panel an element stands in, as the head of its pane says it: what the back link
		 *	"Back to <name>" leads to where the list of the panel is the level above. Empty where the element has no pane
		 *
		 *	@param		{Element}		el							Any element inside the pane
		 *
		 *	@return		{string}
		 */
		panelName : function( el ) {
			const head = Nino.adminUi.panelHead( el );
			return head === null || head.title === null || head.title === undefined ? '' : String( head.title.textContent ).trim();
		},

		/**
		 *	Show one level of a drill-down and move the focus into it. The levels
		 *	of a pane are elements whose ids start alike (<prefix>-list,
		 *	<prefix>-form - see levels()), and the pane shows one at a time: the
		 *	level asked for loses the shell's admin-hidden class and its siblings,
		 *	the other children of its parent with an id that begins the same, gain
		 *	it. What every panel left out was the focus. The control that opened a
		 *	level is hidden with the level it stood in, the focus falls to the
		 *	body, and the next Tab starts again at the top of the page
		 *
		 *	The focus goes to options.focus where that is an element; else to the
		 *	first heading of the level, and where the level has none to its back
		 *	link, and where it has none either to the name of the panel. A target
		 *	that is no control gets tabindex="-1" - it can take the focus and is no
		 *	stop of the Tab key.
		 *
		 *	false is for a level that is shown again, or drawn when its answer
		 *	arrives - not one the person opened: the focus stays where it is.
		 *	Unless it stood in a level that is hidden now. Nothing keeps it
		 *	there, and it goes to the name of the panel, where the person started.
		 *	Owns no strings
		 *
		 *	@param		{Element|string}	level				The level, or its id
		 *	@param		{Object}					[options]		{ focus } - an element to focus instead of the heading, or false to keep the focus where it is
		 *
		 *	@return		{Element|null}									The element that took the focus, null where it stayed
		 */
		showLevel : function( level, options ) {

			options = options || {};

			if( typeof level === 'string' )
				level = dc.getElementById( level );

			if( level === null || level === undefined )
				return null;

			const others	= [];
			const stem		= level.id.lastIndexOf('-') > 0 ? level.id.slice( 0, level.id.lastIndexOf('-')+ 1 ) : '';

			if( stem !== '' && level.parentNode !== null && level.parentNode !== undefined )
				Array.from( level.parentNode.children ).forEach( function( other ) {
					if( other !== level && other.id.indexOf( stem ) === 0 )
						others.push( other );
				} );

			// Asked before the siblings are hidden: a browser takes the focus
			// from a hidden element a moment later, and by then nothing says where it was
			const held	= dc.activeElement ?? null;
			const lost	= held !== null && others.some( function( other ) {
				for( let node = held; node !== null && node !== undefined; node = node.parentNode )
					if( node === other )
						return true;
				return false;
			} );

			others.forEach( function( other ) { other.classList.add('admin-hidden') } );
			level.classList.remove('admin-hidden');

			if( options.focus === false && lost === false )
				return null;

			const pane		= level.closest('[data-panel]');
			const title		= pane === null ? null : pane.querySelector('.admin-panel-title');
			let target		= typeof options.focus === 'object' ? options.focus : null;

			if( target === null && options.focus === false )
				target = title;
			else if( target === null )
				target = level.querySelector('h1, h2, h3, h4, h5, h6, [role="heading"]') ?? level.querySelector('.nino-admin-back-link') ?? title;

			if( target === null || typeof target.focus !== 'function' )
				return null;

			if( target.hasAttribute('tabindex') === false && /^(A|BUTTON|INPUT|SELECT|TEXTAREA|SUMMARY)$/.test( target.tagName ) === false )
				target.setAttribute( 'tabindex', '-1' );

			target.focus();

			return target;
		},

		/**
		 *	Back to the level a drill-down came from, and the focus to the control
		 *	that opened the one that is left - the row or the button - where that
		 *	is still in the page; else to what showLevel() picks. A list that is
		 *	drawn again while the form is open has a new row for it, which is the
		 *	one to pass
		 *
		 *	@param		{Element|string}	level				The level to go back to, or its id
		 *	@param		{Element}					[opener]		The control that opened the level that is left
		 *
		 *	@return		{Element|null}									The element that took the focus
		 */
		backTo : function( level, opener ) {
			return Nino.adminUi.showLevel( level, { focus : opener !== null && opener !== undefined && opener.isConnected === true ? opener : true } );
		},

		/**
		 *	The fixed bar at the bottom of a screen, where its main button, its state and its line stand. Two forms
		 *
		 *	Called with an element it turns it into the bar - it gets the class .nino-admin-actionbar and nothing else is
		 *	touched; the caller puts the buttons and the status line in - and returns the element
		 *
		 *	Called with options it builds the bar in the one order of the guide: the destructive action at the left, then the
		 *	state with its line, the side actions, and ONE main button at the right edge. Nothing else is in it, so every bar
		 *	reads the same
		 *
		 *	  - danger      the button that destroys (Delete <thing>): one element; it gets the class of its kind
		 *	  - status      true, or { line, fresh } - line the element (a p, say) that is to be the status line, fresh true for a thing
		 *	                that was never saved: the state as a badge ("● Changed" / "✓ Saved", the one place the shell's mark of
		 *	                unsaved input is drawn too, see barState()) - a fresh one has none until something is typed - and the
		 *	                line (status()) that says what the last action did
		 *	  - secondary   the side actions, a list of buttons, in the order they stand
		 *	  - primary     the main button: ONE element, which gets the class of its kind; a second one (in secondary, in danger, or a
		 *	                list) is a TypeError, and so is a bar without one
		 *	  - list        true for the bar of a list: it carries .nino-admin-list-actions, which the shell's mark leaves alone, and
		 *	                is drawn as every bar of a list (listActions()) is
		 *	  - id, className, text   the id and the extra class of the bar, and { changed, saved } to word the state differently
		 *
		 *	@param		{Element|Object}		bar					The element that becomes the bar, or the options
		 *
		 *	@return		{Element|Object}									The same element; with options { el, state, line, danger, primary, secondary, paint( { dirty, calm } ) } - state and line only where there is a status
		 */
		actionBar : function( bar ) {

			if( isElement( bar ) === true ) {
				bar.classList.add('nino-admin-actionbar');
				return bar;
			}

			const options = bar ?? {};
			const secondary = options.secondary === undefined ? [] : options.secondary;
			const text = options.text || {};
			const word = function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };
			const isPrimary = function( el ) { return isElement( el ) === true && el.classList.contains('nino-admin-btn-primary') };

			if( isElement( options.primary ) === false )
				throw new TypeError( 'Nino.adminUi.actionBar(): a bar has exactly one main button, given as primary' );

			if( Array.isArray( secondary ) === false || secondary.every( isElement ) === false )
				throw new TypeError( 'Nino.adminUi.actionBar(): secondary is a list of buttons' );

			if( options.danger !== undefined && options.danger !== null && isElement( options.danger ) === false )
				throw new TypeError( 'Nino.adminUi.actionBar(): danger is one button' );

			if( secondary.some( isPrimary ) === true || isPrimary( options.danger ) === true )
				throw new TypeError( 'Nino.adminUi.actionBar(): a bar has exactly one main button - a second one is no main button' );

			if( secondary.some( function( el ) { return el.classList.contains('nino-admin-btn-danger') } ) === true )
				throw new TypeError( 'Nino.adminUi.actionBar(): the destructive button is given as danger, at the left' );

			const stated = options.status !== undefined && options.status !== false && options.status !== null;
			const el = Nino.adminUi.el( 'div', 'nino-admin-actionbar' + ( stated === true ? ' nino-admin-bar' : '' ) + ( options.list === true ? ' nino-admin-list-actions' : '' ) + ( options.className ? ' '+ options.className : '' ) );
			const made = { el : el, secondary : secondary, primary : options.primary, danger : options.danger ?? null };

			if( options.id )
				el.id = options.id;

			if( made.danger !== null ) {
				made.danger.classList.add('nino-admin-btn-danger');
				el.appendChild( made.danger );
			}

			if( stated === true ) {

				const given = options.status === true ? null : options.status.line;

				made.state = Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-actionbar-state' );
				made.line = Nino.adminUi.status( isElement( given ) ? given : Nino.adminUi.el('p') );

				made.paint = function( now ) { paintBadge( made.state, now || {}, word ) };

				made.state.dataset.fresh = options.status !== true && options.status.fresh === true ? 'true' : '';
				made.paint( { dirty : false } );
				el.appendChild( made.state );
				el.appendChild( made.line.element );
			}

			secondary.forEach( function( button ) { el.appendChild( button ) } );

			made.primary.classList.add('nino-admin-btn-primary');
			el.appendChild( made.primary );

			return made;
		},

		/**
		 *	Paint the state badge of a bar that has one - the one display of "unsaved changes", which the shell's registry of
		 *	unsaved input uses instead of a mark of its own. A bar of saveBar() paints itself and is left alone
		 *
		 *	@param		{Element}		bar						The bar element
		 *	@param		{Object}		now						{ dirty, calm, fresh } - fresh true or false sets whether the thing was never saved, and is kept until said again
		 *
		 *	@return		{boolean}										Whether the bar has a state badge, a bar of saveBar() included
		 */
		barState : function( bar, now ) {

			const badge = bar.querySelector('.nino-admin-actionbar-state');

			if( badge === null || badge === undefined )
				return false;

			if( bar.classList.contains('nino-admin-savebar') === false )
				paintBadge( badge, now, function( key, fill ) { return Nino.adminUi.text( fill ) } );

			return true;
		},

		/**
		 *	The answer to a thing a person did that went well and needs nothing more from them - saved, deleted, copied, moved:
		 *	one sentence with a check mark, as a toast at the bottom right ABOVE the bar, never over it. At most one is on the
		 *	screen, a new one replaces the one before it; it goes after four seconds, and stays while the pointer rests on it
		 *	or the focus is in it. It is a status, which a screen reader reads out, and it moves only where the person
		 *	allows motion. It is for success alone: an error stays where it happened - at the field, in the line of the bar, in
		 *	showError() - and a kind that is none of the two, 'error' among them, is the neutral one, 'info': it is never thrown at the panel. Owns no strings: the sentence is the caller's
		 *	(/_admin/common/msg/saved is the shared one for a save, and what status().saved() says)
		 *
		 *	@param		{string}		text						The sentence
		 *	@param		{Object}		[options]				{ kind } - 'success' (the default) or 'info'
		 *
		 *	@return		{Element|null}								The toast; null on a page that has nothing to draw it in
		 */
		say : function( text, options ) {

			const asked = ( options || {} ).kind ?? 'success';

			// A kind this does not know - 'error' among them: an error is said where it happened - is the neutral one, and nothing is thrown at a panel that asked for it
			const kind = SAY_KINDS.indexOf( asked ) === -1 ? 'info' : asked;

			// Inside the workbench's own element, where the rules of the design system apply; a page without one has its body
			const body = ( typeof dc.querySelector === 'function' ? dc.querySelector('.nino-admin') : null ) ?? dc.body ?? bd;

			if( body === null || body === undefined )
				return null;

			// The live region stays in the page, empty, before the first sentence arrives, so that the sentence is read out; the card
			// with the mark and the button is what is seen, and no part of what is said
			let host = Array.from( body.children ).find( function( child ) { return child.classList.contains('nino-admin-toasts') } );

			if( host === undefined ) {
				host = Nino.adminUi.el( 'div', 'nino-admin-toasts' );
				host.appendChild( Nino.adminUi.el( 'p', 'nino-admin-toast-said' ) );
				host.firstChild.setAttribute( 'role', 'status' );
				host.firstChild.setAttribute( 'aria-live', 'polite' );
				body.appendChild( host );
			}

			const said = host.firstChild;

			Array.from( host.children ).forEach( function( old ) {
				if( old === said )
					return;
				wn.clearTimeout( old.timer );
				host.removeChild( old );
			} );

			// A new node every time: the same sentence twice is said twice
			Array.from( said.children ).forEach( function( old ) { said.removeChild( old ) } );
			said.appendChild( Nino.adminUi.el( 'span', '', String( text ) ) );

			const toast = Nino.adminUi.el( 'div', 'nino-admin-toast' );
			const mark = Nino.adminUi.el( 'span', 'nino-admin-toast-mark', '\u2713' );
			const words = Nino.adminUi.el( 'span', 'nino-admin-toast-text', String( text ) );

			toast.dataset.kind = kind;
			mark.setAttribute( 'aria-hidden', 'true' );
			words.setAttribute( 'aria-hidden', 'true' );
			toast.appendChild( mark );
			toast.appendChild( words );

			const close = function() {
				wn.clearTimeout( toast.timer );
				if( toast.parentNode !== null )
					toast.parentNode.removeChild( toast );
			};
			const start = function() {
				wn.clearTimeout( toast.timer );
				toast.timer = wn.setTimeout( close, SAY_FOR );
			};
			const hold = function() { wn.clearTimeout( toast.timer ) };

			// The bar may be two rows high on a phone: the toast stands over the one that is on the screen, and the stylesheet falls
			// back to the height of the token where the page has none to measure
			const rise = typeof dc.querySelectorAll === 'function' ? Array.from( dc.querySelectorAll('.nino-admin-actionbar') ).reduce( function( most, bar ) { return Math.max( most, Number.isFinite( bar.offsetHeight ) === true ? bar.offsetHeight : 0 ) }, 0 ) : 0;

			if( rise > 0 && typeof toast.style.setProperty === 'function' )
				toast.style.setProperty( '--nino-admin-toast-rise', rise+ 'px' );

			toast.appendChild( Nino.adminUi.iconButton( 'x', Nino.adminUi.text('/_admin/common/label/close'), close, { small : true } ) );
			toast.addEventListener( 'mouseenter', hold );
			toast.addEventListener( 'mouseleave', start );
			toast.addEventListener( 'focusin', hold );
			toast.addEventListener( 'focusout', start );

			host.appendChild( toast );
			start();

			return toast;
		},

		/**
		 *	The fixed bar above a list that holds the buttons for the list as
		 *	a whole - "New", an import (.nino-admin-actionbar
		 *	.nino-admin-list-actions)
		 *
		 *	@param		{Array}			buttons				The buttons, in the order they stand
		 *
		 *	@return		{Element}										The bar, the buttons in it
		 */
		listActions : function( buttons ) {
			const bar = dc.createElement('div');
			bar.className = 'nino-admin-actionbar nino-admin-list-actions';
			buttons.forEach( function( button ) { bar.appendChild( button ) } );
			return bar;
		},

		/**
		 *	The head of the pane an element stands in: the row the shell
		 *	renders over every panel but the Dashboard (see
		 *	\Nino\Admin\Panels::$html) - the panel's name, the registry's tab
		 *	strip beside it, a slot for actions at its end. A panel with tabs
		 *	of its own puts its strip there through tabs(), which takes the
		 *	place after the name and replaces whatever strip stood there, so
		 *	a screen drawn again does not stack two; a panel with buttons
		 *	over its screen appends them to actions. One row across the
		 *	workbench, then, rather than a title and a strip per panel.
		 *	Where the head already holds the registry's strip and the screen
		 *	is one of its tabs, tabs() leaves that strip alone and puts the
		 *	panel's own at the top of the tab's pane.
		 *
		 *	null where there is no head - the Dashboard, or a script rendered
		 *	somewhere other than a pane - so a panel keeps its strip where it
		 *	drew it
		 *
		 *	@param		{Element}		el						Any element inside the pane - the mount, usually
		 *
		 *	@return		{Object|null}						{ element, title, actions, tabs( strip ) }
		 */
		panelHead : function( el ) {
			const pane = el && typeof el.closest === 'function' ? el.closest('[data-panel]') : null;
			const head = pane ? pane.querySelector(':scope > .admin-panel-head') : null;
			if( !head )
				return null;
			return {
				element	: head,
				title		: head.querySelector(':scope > .admin-panel-title'),
				actions	: head.querySelector(':scope > .admin-panel-actions'),
				tabs		: function( strip ) {
					/*	The head already holds the registry's strip - buttons the
						shell wires through data-tab - and the screen asking is one
						of its tabs: a strip of its own goes to the top of that
						tab's pane instead, and replaces the one it drew there
						before. Replacing the head's would take the tabs away
						(the Settings tab a feature's panel gets is one of them)	*/
					let tabPane = el;
					while( tabPane && tabPane.parentNode !== pane )
						tabPane = tabPane.parentNode;
					if( tabPane && tabPane.dataset && tabPane.dataset.tab !== undefined && head.querySelector(':scope > .admin-panel-tabs > button[data-tab]') !== null ) {
						const own = tabPane.querySelector(':scope > .admin-panel-tabs');
						if( own !== null && own !== strip )
							own.remove();
						strip.classList.add('admin-panel-tabs');
						tabPane.insertBefore( strip, tabPane.firstChild );
						return strip;
					}
					const before = head.querySelector(':scope > .admin-panel-tabs');
					if( before !== null && before !== strip )
						before.remove();
					// The class the head lays a strip out by, whatever the
					// panel called its own
					strip.classList.add('admin-panel-tabs');
					const title = head.querySelector(':scope > .admin-panel-title');
					if( title !== null )
						title.insertAdjacentElement( 'afterend', strip );
					else
						head.insertBefore( strip, head.firstChild );
					return strip;
				},
			};
		},

		/**
		 *	The one way a panel says "nothing here yet": a list without entries,
		 *	a table without rows, a scan that found nothing. One sentence and,
		 *	where there is a next step, the button that takes it - the one that
		 *	makes the first entry. Owns no strings - the caller says what is
		 *	missing, in the interface language
		 *
		 *	@param		{string}		message
		 *	@param		{Element}		[action]				The button that creates the first entry, made by button()
		 *
		 *	@return		{Element}										A <p> for the sentence alone, a <div> that holds the sentence and the button
		 */
		emptyState : function( message, action ) {

			if( action === undefined || action === null ) {
				const empty = dc.createElement('p');
				empty.className = 'nino-admin-empty';
				empty.textContent = message;
				return empty;
			}

			const box = dc.createElement('div');
			box.className = 'nino-admin-empty nino-admin-empty--action';
			box.appendChild( Nino.adminUi.el( 'p', '', message ) );
			box.appendChild( action );
			return box;
		},

		/**
		 *	The buttons at the end of a row - of a list() row and of a table() row:
		 *	at most three, small ones, and whatever takes something away goes last
		 *	(kept in the order it was given otherwise). A fourth is refused with a
		 *	RangeError: a row with that many actions is a screen of its own. Owns no
		 *	strings
		 *
		 *	@param		{Array}		buttons					Elements made by button( ..., { small : true } ) or iconButton( ..., { small : true } )
		 *
		 *	@return		{Element}									<span class="nino-admin-row-actions">
		 */
		rowActions : function( buttons ) {

			const given = ( buttons || [] ).filter( function( button ) { return button !== null && button !== undefined } );

			if( given.length > 3 )
				throw new RangeError( 'Nino.adminUi.rowActions(): a row has at most three actions, '+ given.length+ ' were given' );

			const dangerous = function( button ) { return button.classList.contains('nino-admin-btn-danger') };
			const actions = Nino.adminUi.el( 'span', 'nino-admin-row-actions' );

			given.filter( function( button ) { return dangerous( button ) === false } )
				.concat( given.filter( dangerous ) )
				.forEach( function( button ) { actions.appendChild( button ) } );

			return actions;
		},

		// The close of the menu that is open: one at a time, a second one opening closes the first
		_menuClose : null,

		/**
		 *	A button that opens a menu - WAI-ARIA "Menu Button": the trigger says aria-haspopup="menu" and aria-expanded, the list is
		 *	role="menu" of role="menuitem" buttons, one stop of the Tab key (the items take the focus by the arrows, not by Tab).
		 *	Enter, Space and the Down arrow open it on the first item, the Up arrow on the last; Down and Up walk the items and wrap,
		 *	Home and End go to the ends, a letter goes to the next item that begins with it, Escape closes it and gives the focus back to the trigger, so does a choice; Tab, a press beside
		 *	the menu, a scroll and a resize close it. What takes something away is red and last, whatever the order it was given in.
		 *	An item made of a button of a row (the actions of a table) presses that button, which is not in the page: a handler that asks where the
		 *	button stands (closest('tr')) or reads a state it was given later finds none of it - the action says what it needs through the row it was made for
		 *	The list stands fixed at the trigger's right edge and flips above it where there is no room below, so that the frame of a
		 *	table does not cut it. Owns no strings: the name of the trigger is the caller's
		 *
		 *	@param		{Object}		options					{ label, items, icon, small, className, id }
		 *														- label       the name of the trigger (and of the list), not optional
		 *														- items       [ { label, onClick, danger, disabled } ] - a disabled item is read and not pressed
		 *														- icon        a name of ICONS, 'ellipsis' by default
		 *														- small       the small icon button (24 px)
		 *
		 *	@return		{Object}										{ el, button, list, open(), close( [focus] ), isOpen() }
		 */
		menuButton : function( options ) {

			const label = typeof options.label === 'string' ? options.label.trim() : '';

			if( label === '' )
				throw new TypeError( 'Nino.adminUi.menuButton(): a menu needs the name of its button' );

			const given = ( options.items || [] ).filter( function( item ) { return item !== null && typeof item === 'object' } );
			const items = given.filter( function( item ) { return item.danger !== true } ).concat( given.filter( function( item ) { return item.danger === true } ) );

			const wrap = Nino.adminUi.el( 'span', 'nino-admin-menu' );
			const list = Nino.adminUi.el( 'div', 'nino-admin-menu-list' );
			const button = Nino.adminUi.iconButton( options.icon ?? 'ellipsis', label, null, { small : options.small === true, className : options.className, id : options.id } );

			list.id = 'nino-admin-menu-'+ ( ++serial );
			list.setAttribute( 'role', 'menu' );
			list.setAttribute( 'aria-label', label );
			list.hidden = true;
			button.setAttribute( 'aria-haspopup', 'menu' );
			button.setAttribute( 'aria-expanded', 'false' );
			button.setAttribute( 'aria-controls', list.id );

			const nodes = items.map( function( item ) {

				const node = Nino.adminUi.el( 'button', 'nino-admin-menu-item'+ ( item.danger === true ? ' nino-admin-menu-item--danger' : '' ), String( item.label ?? '' ) );

				node.type = 'button';
				node.setAttribute( 'role', 'menuitem' );
				node.tabIndex = -1;

				if( item.disabled === true )
					node.setAttribute( 'aria-disabled', 'true' );

				node.addEventListener( 'click', function() {

					if( item.disabled === true )
						return;

					close( true );

					if( typeof item.onClick === 'function' )
						item.onClick();
				} );

				list.appendChild( node );

				return node;
			} );

			const isOpen = function() { return list.hidden === false };

			const beside = function( ev ) {
				if( wrap.contains( ev.target ) === false )
					close( false );
			};

			const away = function( ev ) {
				if( list.contains( ev.target ) === false )
					close( false );
			};

			function close( focus ) {

				if( isOpen() === false )
					return;

				list.hidden = true;
				button.setAttribute( 'aria-expanded', 'false' );
				dc.removeEventListener( 'click', beside, true );
				dc.removeEventListener( 'scroll', away, true );
				wn.removeEventListener( 'resize', away );

				if( Nino.adminUi._menuClose === close )
					Nino.adminUi._menuClose = null;

				if( focus === true )
					button.focus();
			}

			const open = function( at ) {

				if( isOpen() === true || nodes.length === 0 )
					return;

				if( typeof Nino.adminUi._menuClose === 'function' )
					Nino.adminUi._menuClose( false );

				list.hidden = false;
				button.setAttribute( 'aria-expanded', 'true' );

				// Fixed at the trigger, so that no frame of overflow cuts it: right edge to right edge, above where the room below is short
				const at_ = button.getBoundingClientRect();
				const room = list.getBoundingClientRect();
				const below = at_.bottom + 4;
				const high = typeof wn.innerHeight === 'number' ? wn.innerHeight : 0;

				list.style.top = ( high > 0 && below + room.height > high && at_.top - 4 - room.height >= 0 ? at_.top - 4 - room.height : below )+ 'px';
				list.style.left = Math.max( 4, at_.right - room.width )+ 'px';

				Nino.adminUi._menuClose = close;
				dc.addEventListener( 'click', beside, true );
				dc.addEventListener( 'scroll', away, true );
				wn.addEventListener( 'resize', away );

				nodes[at === 'last' ? nodes.length - 1 : 0].focus();
			};

			button.addEventListener( 'click', function() { isOpen() === true ? close( true ) : open( 'first' ) } );
			button.addEventListener( 'keydown', function( ev ) {

				if( ev.key !== 'ArrowDown' && ev.key !== 'ArrowUp' )
					return;

				ev.preventDefault();
				open( ev.key === 'ArrowUp' ? 'last' : 'first' );
			} );

			list.addEventListener( 'keydown', function( ev ) {

				const here = nodes.indexOf( ev.target );
				let to = -1;

				if( ev.key === 'ArrowDown' )		to = ( here + 1 ) % nodes.length;
				else if( ev.key === 'ArrowUp' )	to = ( here - 1 + nodes.length ) % nodes.length;
				else if( ev.key === 'Home' )		to = 0;
				else if( ev.key === 'End' )			to = nodes.length - 1;
				else if( ev.key === 'Escape' ) {
					ev.preventDefault();
					ev.stopPropagation();
					close( true );
					return;
				}
				else if( ev.key === 'Tab' ) {
					close( false );
					return;
				}
				// A letter goes to the next item that begins with it, round the list
				else if( ev.key.length === 1 && ev.key !== ' ' && ev.ctrlKey !== true && ev.metaKey !== true && ev.altKey !== true ) {

					const letter = ev.key.toLowerCase();
					const found = nodes.map( function( node, at ) { return ( here + 1 + at ) % nodes.length } ).find( function( at ) { return nodes[at].textContent.trim().toLowerCase().indexOf( letter ) === 0 } );

					if( found === undefined )
						return;

					to = found;
				}
				else
					return;

				ev.preventDefault();
				nodes[to].focus();
			} );

			wrap.appendChild( button );
			wrap.appendChild( list );

			return { el : wrap, button : button, list : list, open : function() { open( 'first' ) }, close : close, isOpen : isOpen };
		},

		/**
		 *	The buttons at the end of a row of a table: up to `inline` of them stand as they are (see rowActions()); with more, the first two
		 *	stand and the rest are in a menu behind one icon button, "More actions" - the one that takes something away last. An item of the menu
		 *	is named by the name of its button (aria-label, title or text) and presses it
		 *
		 *	@param		{Array}			buttons				Elements made by button() or iconButton()
		 *	@param		{number}		[inline]			How many may stand before the menu takes the rest, 2 or 3 (3 by default)
		 *
		 *	@return		{Element}									<span class="nino-admin-row-actions">
		 */
		_rowActionsMenu : function( buttons, inline ) {

			const given = ( buttons || [] ).filter( function( button ) { return button !== null && button !== undefined } );
			const limit = Math.min( 3, Math.max( 2, Math.floor( Number( inline ) ) || 3 ) );

			if( given.length <= limit )
				return Nino.adminUi.rowActions( given );

			const dangerous = function( button ) { return button.classList.contains('nino-admin-btn-danger') };
			const ordered = given.filter( function( button ) { return dangerous( button ) === false } ).concat( given.filter( dangerous ) );
			const actions = Nino.adminUi.el( 'span', 'nino-admin-row-actions' );
			const named = function( button ) { return String( button.getAttribute('aria-label') || button.title || button.textContent || '' ).trim() };

			ordered.slice( 0, 2 ).forEach( function( button ) { actions.appendChild( button ) } );

			actions.appendChild( Nino.adminUi.menuButton( {
				label : Nino.adminUi.text('/_admin/common/table/more'),
				small : true,
				items : ordered.slice( 2 ).map( function( button ) {
					return { label : named( button ), danger : dangerous( button ), disabled : button.disabled === true, onClick : function() { button.click() } };
				} ),
			} ).el );

			return actions;
		},

		/**
		 *	A heading over a group of rows: the small caps line of a list that has
		 *	groups. Owns no strings
		 *
		 *	@param		{string}		text
		 *	@param		{number}		[level]					2 for a group, 3 for a group within one (the default is 2)
		 *
		 *	@return		{Element}										<h2> or <h3 class="nino-admin-section-title">
		 */
		sectionTitle : function( text, level ) {
			return Nino.adminUi.el( level === 3 ? 'h3' : 'h2', 'nino-admin-section-title'+ ( level === 3 ? ' nino-admin-section-title--sub' : '' ), text );
		},

		/**
		 *	The one row of a list of things that are opened: a short directory, the
		 *	groups of a panel. A row is as high as --nino-admin-row-height where it has
		 *	a second line and --nino-admin-row-dense where it has none; it shows a
		 *	title in the strong weight, a quiet second line and a badge after the
		 *	title, at the right at most three small actions (see rowActions()) and a
		 *	"›" where the row opens. A row that opens is opened by its whole width: the
		 *	target is a real link (with an `href`) or button, so the keyboard reaches it
		 *	and Enter or Space press it, and the actions stand above it, each with
		 *	a press of its own. Owns no strings
		 *
		 *	@param		{Array}			rows								[ { key, title, detail, badge, sub, href, onOpen, actions } ]
		 *																					key the row's identity, written on the row as data-row; detail the
		 *																					second line, badge a short word as a pill after the title (a string
		 *																					or { text, state }), sub a row that is below another one,
		 *																					href the address a row that opens is a link to, onOpen( row, event )
		 *																					called by a press on it - with an href the event's default is
		 *																					prevented, so the address is written by the caller, actions an
		 *																					array of small buttons
		 *	@param		{Object}		[options]						{ title, level, label } - title the heading of the group (level 2, or 3
		 *																					for a group within a group), label the name of the list for a screen reader
		 *
		 *	@return		{Element}											<section class="nino-admin-list-group"> with the heading, where there is one, and the <ul class="nino-admin-list">
		 */
		list : function( rows, options ) {

			options = options || {};

			const group = Nino.adminUi.el( 'section', 'nino-admin-list-group' );

			if( typeof options.title === 'string' && options.title !== '' )
				group.appendChild( Nino.adminUi.sectionTitle( options.title, options.level ) );

			const ul = Nino.adminUi.el( 'ul', 'nino-admin-list' );

			if( typeof options.label === 'string' && options.label !== '' )
				ul.setAttribute( 'aria-label', options.label );

			( rows || [] ).forEach( function( row ) {

				const opens = typeof row.onOpen === 'function' || typeof row.href === 'string';
				const detail = typeof row.detail === 'string' && row.detail !== '';

				const li = Nino.adminUi.el( 'li', 'nino-admin-list-row'+ ( detail === false ? ' is-dense' : '' )+ ( row.sub === true ? ' is-sub' : '' ) );

				if( row.key !== undefined && row.key !== null )
					li.dataset.row = String( row.key );

				const line = Nino.adminUi.el( 'div', 'nino-admin-row' );

				const copy = Nino.adminUi.el( 'span', 'nino-admin-list-copy' );
				const title = Nino.adminUi.el( 'strong', '', row.title );

				if( row.badge !== undefined && row.badge !== null && row.badge !== '' ) {
					const badge = typeof row.badge === 'object' ? row.badge : { text : row.badge };
					const pill = Nino.adminUi.el( 'span', 'nino-admin-badge', badge.text );

					if( badge.state !== undefined )
						pill.setAttribute( 'data-state', badge.state );

					title.appendChild( pill );
				}

				copy.appendChild( title );

				if( detail === true )
					copy.appendChild( Nino.adminUi.el( 'small', '', row.detail ) );

				let target = null;

				if( opens === true ) {

					if( typeof row.href === 'string' ) {
						target = dc.createElement('a');
						target.href = row.href;
					}
					else {
						target = dc.createElement('button');
						target.type = 'button';
					}

					target.className = 'nino-admin-row-open';
					target.appendChild( copy );

					target.addEventListener( 'click', function( ev ) {

						if( typeof row.onOpen !== 'function' )
							return;

						if( typeof row.href === 'string' && ev && typeof ev.preventDefault === 'function' )
							ev.preventDefault();

						row.onOpen( row, ev );
					} );

					line.appendChild( target );
				}
				else
					line.appendChild( copy );

				if( Array.isArray( row.actions ) === true && row.actions.length > 0 )
					line.appendChild( Nino.adminUi.rowActions( row.actions ) );

				if( opens === true ) {
					const chev = Nino.adminUi.el( 'span', 'nino-admin-row-chev', '\u203a' );
					chev.setAttribute( 'aria-hidden', 'true' );
					line.appendChild( chev );
				}

				li.appendChild( line );
				ul.appendChild( li );
			} );

			group.appendChild( ul );

			return group;
		},

		/**
		 *	A filter that is on or off: a pill button that says so by aria-pressed,
		 *	and by its look as well. A press turns it over and tells the caller, who
		 *	draws what it filters. Owns no strings
		 *
		 *	@param		{string}		text
		 *	@param		{Object}		[options]				{ pressed, onToggle, id } - onToggle( pressed ) is called after the press with the new state
		 *
		 *	@return		{Element}										<button class="nino-admin-chip" aria-pressed>
		 */
		chip : function( text, options ) {

			options = options || {};

			const chip = Nino.adminUi.el( 'button', 'nino-admin-chip', text );
			chip.type = 'button';
			chip.setAttribute( 'aria-pressed', options.pressed === true ? 'true' : 'false' );

			if( options.id )
				chip.id = options.id;

			chip.addEventListener( 'click', function() {

				const pressed = chip.getAttribute('aria-pressed') !== 'true';
				chip.setAttribute( 'aria-pressed', pressed === true ? 'true' : 'false' );

				if( typeof options.onToggle === 'function' )
					options.onToggle( pressed );
			} );

			return chip;
		},

		/**
		 *	What narrows a list: one bar over it with a select that picks a kind, the search field beside it - to its right, in the same line - and
		 *	whatever else narrows it - chips. It wraps where there is no room: under 30 rem the select stands over the search, both of the full
		 *	width. The one place of the bar: table() makes its toolbar of it, and a list that is no table - cards - uses it on its own. The bar
		 *	filters nothing: it draws, and tells the caller what was chosen and typed, who loads or filters. The search and the select say their
		 *	names in the labels the caller gives. Owns no strings
		 *
		 *	@param		{Object}		options					{ filter, search, items, className }
		 *																			filter { value, options, label, onChange( value ), id } - a select left of the search; options [ { value, label } ],
		 *																			value the one that is chosen (the first by default), label its name for a screen reader (it has no other), left out, the bar has no select
		 *																			search { value, label, placeholder, onInput( value ), id, className } - left out, the bar has no field; className replaces the
		 *																			classes of the field
		 *																			items an array of elements that stand after the field: chip()
		 *																			className what the bar has besides its own class
		 *
		 *	@return		{Object}										{ el, input, select } - el <div class="nino-admin-filterbar" role="search">, input the search field or null, select the select or null
		 */
		filterBar : function( options ) {

			options = options || {};

			const bar = Nino.adminUi.el( 'div', 'nino-admin-filterbar'+ ( options.className ? ' '+ options.className : '' ) );
			bar.setAttribute( 'role', 'search' );

			let input = null;
			let select = null;

			if( options.filter !== undefined && options.filter !== null ) {
				const filter = options.filter;

				select = dc.createElement('select');
				select.className = 'nino-admin-input nino-admin-filterbar-select';
				select.setAttribute( 'aria-label', filter.label ?? '' );

				if( filter.id )
					select.id = filter.id;

				( filter.options || [] ).forEach( function( option ) {

					const item = dc.createElement('option');
					item.value = String( option.value ?? '' );
					item.textContent = String( option.label ?? option.value ?? '' );
					select.appendChild( item );
				} );

				if( filter.value !== undefined && filter.value !== null )
					select.value = String( filter.value );

				select.addEventListener( 'change', function() {
					if( typeof filter.onChange === 'function' )
						filter.onChange( select.value );
				} );

				bar.appendChild( select );
			}

			if( options.search !== undefined && options.search !== null ) {
				const search = options.search;

				input = dc.createElement('input');
				input.type = 'search';
				input.className = search.className ?? 'nino-admin-input nino-admin-filterbar-input';
				input.autocomplete = 'off';
				input.placeholder = search.placeholder ?? search.label ?? '';
				input.setAttribute( 'aria-label', search.label ?? search.placeholder ?? '' );
				input.value = search.value ?? '';

				if( search.id )
					input.id = search.id;

				input.addEventListener( 'input', function() {
					if( typeof search.onInput === 'function' )
						search.onInput( input.value );
				} );

				bar.appendChild( input );
			}

			( options.items || [] ).forEach( function( item ) { bar.appendChild( item ) } );

			return { el : bar, input : input, select : select };
		},

		/**
		 *	Tabs of the second level: the strip of a view that has parts, under the head
		 *	whose own tabs it does not replace. A tablist with one tab stop and the arrow
		 *	keys (Left and Right walk it and wrap, Home and End go to its ends), which
		 *	wraps into a second row where it does not fit - it never scrolls sideways.
		 *	A tab may bring its pane: that one is shown with the tab and the others are
		 *	hidden, each named by its tab. Owns no strings
		 *
		 *	@param		{Array}			items						[ { key, label, panel, badge } ] - panel an Element, optional; badge a short word or a number - how many of something wait in that tab -
		 *																			drawn as the workbench's pill after the label (not a part of it, so the label stays the name of the tab) and said again with badge( key, text )
		 *	@param		{Object}		[options]				{ active, onSelect( key ), label, id } - active the key to start on (the first tab), label the name of the strip for a screen reader, id the stem of the ids of the tabs
		 *
		 *	@return		{Object}										{ el, tabs, select( key ), badge( key, [ text ] ) } - el <div role="tablist">, tabs key => button; badge( key, text ) changes the word of the pill of a tab in place, '' or null takes it away, and badge( key ) answers the word it has
		 */
		tabs : function( items, options ) {

			options = options || {};

			const stem = options.id || 'nino-admin-tabs-'+ ( ++TAB_STRIPS );
			const bar = Nino.adminUi.el( 'div', 'nino-admin-tabs nino-admin-tabs--bar nino-admin-tabs--wrap' );
			bar.setAttribute( 'role', 'tablist' );

			if( options.label )
				bar.setAttribute( 'aria-label', options.label );

			const buttons = {};

			( items || [] ).forEach( function( item ) {

				const button = Nino.adminUi.el( 'button', 'nino-admin-tab', item.label );
				button.type = 'button';
				button.id = stem+ '-'+ item.key;
				button.setAttribute( 'role', 'tab' );

				if( item.panel ) {
					item.panel.id = item.panel.id || stem+ '-pane-'+ item.key;
					item.panel.setAttribute( 'role', 'tabpanel' );
					item.panel.setAttribute( 'aria-labelledby', button.id );
					button.setAttribute( 'aria-controls', item.panel.id );
				}

				bar.appendChild( button );
				buttons[item.key] = button;
			} );

			// The pill of a tab: one at most, after the label, as the pane's own tabs have it (see tabBadge())
			const badge = function( key, text ) {

				if( Object.hasOwn( buttons, key ) === false )
					return '';

				const button = buttons[key];
				const before = button.querySelector(':scope > .nino-admin-tab-badge');

				if( arguments.length < 2 )
					return before === null ? '' : before.textContent;

				const word = text === null || text === undefined ? '' : String( text ).trim();

				if( before !== null && word === '' )
					before.remove();
				else if( before !== null )
					before.textContent = word;
				else if( word !== '' )
					button.appendChild( Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-tab-badge', word ) );

				return word;
			};

			( items || [] ).forEach( function( item ) {
				if( item.badge !== undefined && item.badge !== null )
					badge( item.key, item.badge );
			} );

			const show = function( key ) {
				( items || [] ).forEach( function( item ) {
					if( item.panel )
						item.panel.hidden = item.key !== key;
				} );
			};

			const first = items && items.length > 0 ? items[0].key : '';
			const start = options.active !== undefined && Object.hasOwn( buttons, options.active ) === true ? options.active : first;

			const paint = Nino.adminUi.buttonRow( buttons, start, function( key ) {
				show( key );
				if( typeof options.onSelect === 'function' )
					options.onSelect( key );
			}, 'aria-selected' );

			show( start );

			return {
				el : bar,
				tabs : buttons,
				badge : badge,
				select : function( key ) {
					if( Object.hasOwn( buttons, key ) === false )
						return;
					paint( key );
					show( key );
				},
			};
		},

		/**
		 *	The one way a screen says "this needs your attention": a failing
		 *	mail, texts without a translation, a site that shows its
		 *	maintenance page. A line of text that stays until what it names is
		 *	fixed, with a link to where that is done where there is one. Owns
		 *	no strings - the caller says what and where, in the interface
		 *	language; the text is set as text, never as markup
		 *
		 *	@param		{string}		message
		 *	@param		{Object}		[link]					{ href, label } - drawn only for an href that is an address of the workbench, #panel/tab/part (see Nino.admin.router)
		 *
		 *	@return		{Element}
		 */
		notice : function( message, link ) {

			const notice = dc.createElement('p');
			notice.className = 'nino-admin-notice';
			notice.setAttribute( 'role', 'status' );
			notice.textContent = message;

			if( typeof link === 'object' && link !== null && /^#[a-z][a-z0-9-]*(?:\/(?:@[a-z][a-z0-9-]*|(?:[A-Za-z0-9._~!*'()-]|%[0-9A-Fa-f]{2})+))*$/.test( String( link.href ) ) === true ) {
				const a = dc.createElement('a');
				a.href = link.href;
				a.textContent = String( link.label ?? '' );
				notice.appendChild( dc.createTextNode(' ') );
				notice.appendChild( a );
			}

			return notice;
		},

		/**
		 *	One line of explanation: a paragraph in the hint's quiet type, and in
		 *	the error colour as well for a hint that is a refusal. The words are
		 *	used as they are - a caller that has a fill key runs it through text()
		 *	itself. A line that is announced when it changes is status() or
		 *	notice(), which exist. Owns no strings
		 *
		 *	@param		{string}		text
		 *	@param		{string}		[kind]					'error' for the error colour too
		 *
		 *	@return		{Element}										The paragraph
		 */
		hint : function( text, kind ) {

			const hint = dc.createElement('p');
			hint.className = kind === 'error' ? 'nino-admin-hint nino-admin-error' : 'nino-admin-hint';
			hint.textContent = text;
			return hint;
		},

		/**
		 *	Say at a field what is wrong with it: a paragraph in the field error
		 *	type after the field, the control marked aria-invalid and pointed at
		 *	the paragraph with aria-describedby - so the sentence is read where
		 *	the person stands and not only seen. It goes after a label, not into
		 *	it: inside, it would be part of the control's name as well. The
		 *	sentences are not alerts - a form with six empty fields would announce
		 *	six at once - the form's status line says the one summary
		 *	(status().fail()) and the focus goes to the first field, which reads
		 *	its own. Owns no strings
		 *
		 *	@param		{Element}				wrapper			What the field stands in - its label, or the block around the control
		 *	@param		{Element|null}	control			The control that is invalid; null where the caller marks it itself (an editor)
		 *	@param		{string}				id					The paragraph's id, which is what the control's aria-describedby names
		 *	@param		{string}				text				The sentence
		 *
		 *	@return		{Element}											The paragraph
		 */
		fieldError : function( wrapper, control, id, text ) {

			const error = dc.createElement('p');
			error.id = id;
			error.className = 'nino-admin-field-error';
			error.textContent = text;

			if( wrapper.tagName.toLowerCase() === 'label' )
				wrapper.after( error );
			else
				wrapper.appendChild( error );

			// The error replaces what the control was described by - except the sentence of its help, which stays behind it
			if( control !== null ) {
				control.setAttribute( 'aria-invalid', 'true' );
				control.setAttribute( 'aria-describedby', [ id ].concat( Nino.adminUi._helpTokens( control ) ).join(' ') );
			}

			return error;
		},

		/**
		 *	Take what fieldError() put on a form off again: the sentences go, and
		 *	so do aria-invalid and the aria-describedby that goes with it
		 *
		 *	@param		{Element}		root				The form, or whatever holds its fields
		 *
		 *	@return		void
		 */
		clearFieldErrors : function( root ) {

			root.querySelectorAll('.nino-admin-field-error').forEach( function( el ) { el.remove() } );
			// A group that was marked itself (the ticks of a list, the fields of a date) is one of them
			const marked = Array.from( root.querySelectorAll('[aria-invalid]') );

			if( root.hasAttribute('aria-invalid') === true )
				marked.unshift( root );

			marked.forEach( function( el ) {

				el.removeAttribute('aria-invalid');

				// What the field is described by besides its errors - the sentence of its help - stays
				const kept = Nino.adminUi._helpTokens( el );

				if( kept.length === 0 )
					el.removeAttribute('aria-describedby');
				else
					el.setAttribute( 'aria-describedby', kept.join(' ') );
			} );
		},

		/**
		 *	The ids in a control's aria-describedby that name a sentence of its own - the help (see fieldHelp()) or a note the caller wrote with an id of nino-admin-note-* - which an error does not replace
		 *
		 *	@param		{Element}		control
		 *
		 *	@return		{Array}
		 */
		_helpTokens : function( control ) {
			return String( control.getAttribute('aria-describedby') ?? '' ).split(/\s+/).filter( function( token ) { return token.indexOf('nino-admin-help-') === 0 || token.indexOf('nino-admin-note-') === 0 } );
		},

		/**
		 *	The (?) at the title of a section - a fieldset's legend, a card's heading - for the sentence that explains
		 *	the whole section. The section is a group named by the words of its title alone, and described by the
		 *	sentence: what a screen reader announces on entering it. Name of the button: "Help for <title>"
		 *
		 *	@param		{Element}		title				The legend or heading that holds the words of the title
		 *	@param		{Element}		group				The element the section is: the fieldset, or a div that gets role="group"
		 *	@param		{string}		text				The sentence
		 *
		 *	@return		{Element}									The (?) as fieldHelp() makes it, already in the title
		 */
		sectionHelp : function( title, group, text ) {

			const words = dc.createElement('span');
			words.className = 'nino-admin-field-text';
			words.id = 'nino-admin-name-'+ ( ++serial );

			Array.from( title.childNodes ?? title.children ).forEach( function( node ) {
				title.removeChild( node );
				words.appendChild( node );
			} );

			title.appendChild( words );

			if( String( group.tagName ).toLowerCase() !== 'fieldset' )
				group.setAttribute( 'role', 'group' );

			group.setAttribute( 'aria-labelledby', words.id );

			const help = Nino.adminUi.fieldHelp( text, { field : group, name : words.textContent || ( words.children[0] ? words.children[0].textContent : '' ) } );
			title.appendChild( help );

			return help;
		},

		// The help bubble that is open: at most one. { close() } while there is one
		_helpOpen : null,
		_helpListening : false,

		/**
		 *	The (?) at the name of a field: a button of 24 px, named "Help for <field>", whose press opens the
		 *	sentence that explains what the value does as a small bubble at the field. Escape and a press anywhere
		 *	else close it, a second one opens in place of the first, and the bubble stays inside the window (inside
		 *	the dialog, where the field stands in one). The sentence is always in the document - hidden from the eye,
		 *	not from a screen reader - and the field is described by it (aria-describedby), so it is heard without a
		 *	press. It explains; what has to stay in view - an error, a counter, a limit, a conversion, the result of a
		 *	check - is no help and stands at the field as a note. Owns no strings, the name of the button apart
		 *
		 *	@param		{string}		text						The sentence
		 *	@param		{Object}		[options]				{ field, name }
		 *															- field   the control the sentence describes: aria-describedby names it, the existing ones stay
		 *															- name    the name of the field, which the button is named after
		 *
		 *	@return		{Element}									The wrapper: the button and the sentence (.sentence), the sentence's id in .sentenceId
		 */
		fieldHelp : function( text, options ) {

			options = options || {};

			const root = dc.createElement('span');
			root.className = 'nino-admin-help';

			const sentence = dc.createElement('span');
			sentence.className = 'nino-admin-help-text';
			sentence.id = 'nino-admin-help-'+ ( ++serial );
			sentence.textContent = String( text ?? '' );

			const button = Nino.adminUi.iconButton( 'circle-help', Nino.adminUi.format( Nino.adminUi.text('/_admin/common/label/help-for'), String( options.name ?? '' ) ), null, { small : true, className : 'nino-admin-help-button' } );
			button.setAttribute( 'aria-expanded', 'false' );
			button.setAttribute( 'aria-controls', sentence.id );

			const handle = {
				root : root,
				close : function() {
					delete root.dataset.open;
					button.setAttribute( 'aria-expanded', 'false' );
					sentence.style.left = '';
					sentence.style.top = '';
					if( Nino.adminUi._helpOpen === handle )
						Nino.adminUi._helpOpen = null;
				},
				button : button,
			};

			button.addEventListener( 'click', function() {

				if( Nino.adminUi._helpOpen === handle ) {
					handle.close();
					return;
				}

				if( Nino.adminUi._helpOpen !== null )
					Nino.adminUi._helpOpen.close();

				root.dataset.open = 'true';
				button.setAttribute( 'aria-expanded', 'true' );
				Nino.adminUi._helpOpen = handle;
				Nino.adminUi._helpPlace( button, sentence, options.field ?? null );
				Nino.adminUi._helpListen();
			} );

			// Tabbing on to another control takes the bubble away; a press on something that cannot take the focus is the document's to judge
			root.addEventListener( 'focusout', function( event ) {

				const next = event.relatedTarget;

				if( next === null || next === undefined )
					return;

				for( let node = next; node !== null && node !== undefined; node = node.parentNode )
					if( node === root )
						return;

				handle.close();
			} );

			root.appendChild( button );
			root.appendChild( sentence );
			root.sentence = sentence;
			root.sentenceId = sentence.id;

			if( options.field !== undefined && options.field !== null )
				Nino.adminUi._describe( options.field, sentence.id );

			return root;
		},

		/**
		 *	Name one more sentence as what a control is described by, behind what describes it already
		 *
		 *	@param		{Element}		control
		 *	@param		{string}		id
		 *
		 *	@return		void
		 */
		_describe : function( control, id ) {

			const tokens = String( control.getAttribute('aria-describedby') ?? '' ).split(/\s+/).filter( function( token ) { return token !== '' } );

			if( tokens.indexOf( id ) === -1 )
				tokens.push( id );

			control.setAttribute( 'aria-describedby', tokens.join(' ') );
		},

		/**
		 *	Put an open bubble where it reads and covers nothing it belongs to: under its button where the field is not
		 *	there, else under the field, else over the button - a field that stands under its name (a narrow column, a
		 *	dialog, the sheet of properties) is never hidden by the sentence about it. As far left as the button, moved
		 *	left where it would leave the window or the dialog the button stands in, and never lower than what shows
		 *	above the bar at the foot of the page. Fixed, so that no scroll area the field stands in cuts it off - which
		 *	is why any scrolling closes it
		 *
		 *	@param		{Element}		button
		 *	@param		{Element}		bubble
		 *	@param		{Element|null}	field		The control the sentence is about
		 *
		 *	@return		void
		 */
		_helpPlace : function( button, bubble, field ) {

			if( typeof button.getBoundingClientRect !== 'function' || typeof bubble.getBoundingClientRect !== 'function' )
				return;

			const margin = 8;
			const at = button.getBoundingClientRect();
			const dialog = typeof button.closest === 'function' ? button.closest('.nino-admin-dialog') : null;
			const area = dialog !== null && typeof dialog.getBoundingClientRect === 'function'
				? dialog.getBoundingClientRect()
				: { left : 0, right : Number( dc.documentElement?.clientWidth ?? wn.innerWidth ?? 0 ), top : 0, bottom : Number( wn.innerHeight ?? 0 ) };

			// The bar at the foot of the page covers the window: what shows is what is above it
			if( dialog === null && typeof dc.querySelectorAll === 'function' )
				Array.from( dc.querySelectorAll('.nino-admin-actionbar') ).forEach( function( bar ) {

					const box = typeof bar.getBoundingClientRect === 'function' ? bar.getBoundingClientRect() : null;

					// A bar of a pane that is not shown has no size
					if( box !== null && box.width > 0 && box.height > 0 && box.top > 0 )
						area.bottom = Math.min( area.bottom, box.top );
				} );

			bubble.style.left = '0px';
			bubble.style.top = '0px';

			const size = bubble.getBoundingClientRect();
			const wide = Math.min( size.width, Math.max( 0, area.right - area.left - 2 * margin ) );

			let left = Math.min( at.left, area.right - margin - wide );
			left = Math.max( left, area.left + margin );

			const control = field !== null && field !== undefined && typeof field.getBoundingClientRect === 'function' ? field.getBoundingClientRect() : null;
			const covers = function( top ) {
				return control !== null && left < control.right && left + wide > control.left && top < control.bottom && top + size.height > control.top;
			};
			const fits = function( top ) { return top >= area.top + margin && top + size.height <= area.bottom - margin };

			const under = at.bottom + 4;
			const afterField = control === null ? under : control.bottom + 4;
			const over = at.top - 4 - size.height;
			const choices = [ under, afterField, over ];
			let top = choices.find( function( candidate ) { return covers( candidate ) === false && fits( candidate ) === true } );

			if( top === undefined )
				top = choices.find( function( candidate ) { return fits( candidate ) === true } ) ?? under;

			bubble.style.left = Math.round( left )+ 'px';
			bubble.style.top = Math.round( top )+ 'px';
		},

		/**
		 *	Escape, a press beside the bubble and any scrolling close the open bubble - listened for once, on the
		 *	document, from the first bubble that opens
		 *
		 *	@return		void
		 */
		_helpListen : function() {

			if( Nino.adminUi._helpListening === true || typeof dc.addEventListener !== 'function' )
				return;

			Nino.adminUi._helpListening = true;

			// In the capture phase and ahead of the dialog: Escape closes the bubble, and the dialog it stands in stays
			dc.addEventListener( 'keydown', function( event ) {

				const open = Nino.adminUi._helpOpen;

				if( open === null || event.key !== 'Escape' )
					return;

				event.preventDefault();
				event.stopPropagation();
				open.close();
				open.button.focus();
			}, true );

			dc.addEventListener( 'click', function( event ) {

				const open = Nino.adminUi._helpOpen;

				if( open === null )
					return;

				for( let node = event.target; node !== null && node !== undefined; node = node.parentNode )
					if( node === open.root )
						return;

				open.close();
			}, true );

			const away = function() {
				if( Nino.adminUi._helpOpen !== null )
					Nino.adminUi._helpOpen.close();
			};

			dc.addEventListener( 'scroll', away, true );

			if( typeof wn.addEventListener === 'function' )
				wn.addEventListener( 'resize', away );
		},

		/**
		 *	What a field builder does with the name of a field and its control once they exist: the help behind the (?)
		 *	and the textfill icon at the name, and the names the control is read by. The name is read from the words of
		 *	the name alone - the sentence of the help stands in the label too, and would be part of what the control is
		 *	called otherwise (aria-labelledby). A field with neither has nothing changed
		 *
		 *	@param		{Element}		name				The element that holds the name of the field
		 *	@param		{Element}		control			The control the field is for
		 *	@param		{Object}		options			The builder's options: help (or hint), fills, and the label
		 *	@param		{string}		label				The name of the field, as the button is named after it
		 *
		 *	@return		void
		 */
		_tools : function( name, control, options, label ) {

			const sentence = typeof ( options.help ?? options.hint ) === 'string' ? String( options.help ?? options.hint ) : '';
			const fills = options.fills !== undefined && options.fills !== null && options.fills.editable === true ? options.fills : null;

			if( sentence === '' && fills === null )
				return;

			const words = dc.createElement('span');
			words.className = 'nino-admin-field-text';
			words.id = 'nino-admin-name-'+ ( ++serial );

			Array.from( name.childNodes ?? name.children ).forEach( function( node ) {
				name.removeChild( node );
				words.appendChild( node );
			} );

			name.appendChild( words );
			control.setAttribute( 'aria-labelledby', words.id );

			// The label points at the field: a press on the name or on the label's surface focuses the field, and the
			// first control in the label - which would be the (?) - is no longer what the label stands for
			const holder = name.parentNode;

			if( holder !== null && holder !== undefined && String( holder.tagName ).toLowerCase() === 'label' ) {

				if( !control.id )
					control.id = 'nino-admin-control-'+ ( ++serial );

				holder.setAttribute( 'for', control.id );
			}

			if( sentence !== '' )
				name.appendChild( Nino.adminUi.fieldHelp( sentence, { field : control, name : label } ) );

			if( fills !== null )
				name.appendChild( Nino.adminUi.fillsButton( fills, control, label ) );
		},

		/**
		 *	The "[ ]" at the name of a field whose work textfills decide, in the size and the place of the (?). A press
		 *	asks the caller for the textfills this field takes and opens fillsDialog(); where they could not be had it
		 *	says so at the field. The caller draws the button only for an account that may edit the field - the field
		 *	builders do it for the option fills, a field they do not build (an editor) takes the button from here.
		 *	Owns no strings
		 *
		 *	@param		{Object}		fills				{ title, items | load( done ), opener } - see textareaField(); opener a function that answers what gets the focus back when the dialog closes, for a field whose control is made later
		 *	@param		{Element|null}	control	The field's control, which gets the focus back when the dialog closes; null where the caller says it by opener
		 *	@param		{string}		label				The name of the field
		 *
		 *	@return		{Element}									The button; it goes into the element that holds the name of the field
		 */
		fillsButton : function( fills, control, label ) {

			const button = Nino.adminUi.iconButton( 'brackets', Nino.adminUi.text('/_admin/common/label/fills'), null, { small : true, className : 'nino-admin-fills-button' } );
			let said = null;

			button.addEventListener( 'click', function() {

				if( said !== null )
					said.remove();

				said = null;

				const show = function( list ) {

					if( Array.isArray( list ) === false ) {
						// The field is not wrong, the list is missing: said at the field, which is left as it is
						said = Nino.adminUi.fieldError( button.closest('.nino-admin-field') ?? button.parentNode, null, 'nino-admin-fills-error-'+ ( ++serial ), Nino.adminUi.text('/_admin/common/error/request') );
						return;
					}

					// Back to the field when the dialog closes: what is copied is pasted there next
					const back = typeof fills.opener === 'function' ? fills.opener() : ( control ?? button );

					Nino.adminUi.fillsDialog( { title : fills.title ?? Nino.adminUi.format( Nino.adminUi.text('/_admin/common/fills/title'), label ), fills : list, opener : back ?? button } );
				};

				if( typeof fills.load === 'function' )
					fills.load( show );
				else
					show( fills.items ?? [] );
			} );

			return button;
		},

		/**
		 *	The textfills a field takes, in a dialog: a search, and a row for each - its name, how it is written, and
		 *	what it says today. A press on a row copies the NOTATION to the clipboard, says so as a toast and closes
		 *	the dialog; nothing is pasted at the caret. A browser that has no clipboard for the page (no https, no
		 *	localhost) cannot copy: the notation is selected in the row instead, a sentence in the dialog says to copy
		 *	it with the keyboard - no toast, no mark of success -, and the dialog stays. Owns no strings
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		options.title			What the dialog is: "Textfills for Text"
		 *	@param		{Array}			options.fills			[ { name, notation, value } ] - the notation as the field reads it (the key in brackets or between hashes), the value in the working language
		 *	@param		{Element}		[options.opener]	See choiceDialog()
		 *
		 *	@return		{boolean}										false when a dialog is already open
		 */
		fillsDialog : function( options ) {

			const fills = Array.isArray( options.fills ) === true ? options.fills : [];
			const text = function( key ) { return Nino.adminUi.text( '/_admin/common/fills/'+ key ) };

			// Whether this page has a clipboard it may write to. A page without one - http that is no localhost - is
			// not asked: the older way of copying cannot reach from the open dialog to the page, and an answer of
			// "copied" that copied nothing is worse than none
			const clipboard = wn.navigator !== undefined && wn.navigator !== null ? wn.navigator.clipboard : undefined;
			const copies = clipboard !== undefined && clipboard !== null && typeof clipboard.writeText === 'function' && wn.isSecureContext !== false && typeof Nino.clipboard === 'function';
			let said = null;

			const mark = function( row ) {

				const code = row.querySelector('.nino-admin-pick-code');

				if( code !== null && typeof dc.createRange === 'function' && typeof wn.getSelection === 'function' ) {
					const range = dc.createRange();
					range.selectNodeContents( code );
					const selection = wn.getSelection();
					selection.removeAllRanges();
					selection.addRange( range );
				}

				// The sentence stands in the dialog, where it is read: no toast and no mark of success
				if( said === null ) {
					const root = row.closest('.nino-admin-pick');
					said = Nino.adminUi.el( 'p', 'nino-admin-hint nino-admin-pick-marked', text('select') );
					said.setAttribute( 'role', 'status' );
					root.insertBefore( said, root.children[0] );
				}
			};

			return Nino.adminUi.pickDialog( {
				title			: options.title,
				items			: fills.map( function( fill, at ) {
					return {
						value				: at,
						label				: String( fill.name ?? fill.notation ),
						code				: String( fill.notation ),
						description	: fill.value === undefined || fill.value === null ? '' : String( fill.value ),
					};
				} ),
				search		: text('search'),
				searchFrom: 1,
				empty			: text('empty'),
				cancel		: Nino.adminUi.text('/_admin/common/label/close'),
				opener		: options.opener,
				onRow			: function( at, row, pick ) {

					const notation = String( fills[at].notation );

					if( copies === false ) {
						mark( row );
						return;
					}

					Promise.resolve( Nino.clipboard( notation ) ).then( function( copied ) {

						if( copied !== true ) {
							mark( row );
							return;
						}

						Nino.adminUi.say( Nino.adminUi.format( text('copied'), notation ) );
						pick();
					} );
				},
				onChoose	: function() {},
			} );
		},

		// The modal choiceDialog()s that are open, the one below first: one at a time, a second call while
		// one stands is the same click arriving twice - except the question a dialog asks of itself
		// (nested), which stands on top of it. _choiceOpen says whether there is one; a dialog that
		// closes takes itself off the stack, whatever closes before or after it, so none is left
		// standing for the next call to be refused by
		_choiceStack : [],
		_choiceOpen : false,

		// The handle of the sheet that is open (propertiesDialog()), which is no modal dialog and is
		// not one of the stack
		_sheet : null,

		/**
		 *	The one dialog of the workbench: a native <dialog> in the workbench
		 *	root, opened with showModal(), so the page behind keeps everything that
		 *	was typed into it and the keyboard stays inside. Its head (the title)
		 *	and its foot (the answers) stand where they are and what is between
		 *	them scrolls, so that neither leaves the screen however much there is
		 *	to read or fill in. confirm(), formDialog(), pickDialog(),
		 *	propertiesDialog() and manualDialog() are this dialog with something
		 *	in it; none of them builds a <dialog> of its own.
		 *
		 *	The answers stand in one row at the right, in the order they are given:
		 *	the caller puts the one that goes on last, so that it is at the far
		 *	right and Cancel at its left. Escape is the secondary answer, the one
		 *	that does nothing; focus goes back to what had it when the question
		 *	was asked. The focus opens on the first control of
		 *	`focus`, else on the 'primary' answer, unless the question has a
		 *	'danger' answer and no 'primary' one: then it opens on the secondary
		 *	answer, so that Enter on a dialog that has just appeared is never the
		 *	destroying one.
		 *
		 *	Owns no strings and names no Nino.admin member: the caller says
		 *	what to ask and what the answers are called, in the interface
		 *	language. Where the browser has no showModal() the question is
		 *	asked with confirm() - OK picks the 'primary' choice (the 'danger'
		 *	one where there is none), Cancel the 'secondary' one: nothing is
		 *	thrown away by agreeing to the question as it is worded
		 *
		 *	A `note` is a second paragraph under the message - what the caller
		 *	has to add to the question without making it another one (see
		 *	confirm()); a browser without <dialog> is asked the two with a blank
		 *	line between them
		 *
		 *	A `content` is drawn in place of the message: a text that is read
		 *	rather than answered (manualDialog()), a form (formDialog()), a list
		 *	to pick from (pickDialog())
		 *
		 *	A `guard` is asked before an answer closes the dialog, by a press or by
		 *	Escape: it answers false to keep the dialog open - a form that is not
		 *	valid, a question that is on its way to the server. What it keeps open
		 *	it closes itself, with the handle's close()
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		[options.title]
		 *	@param		{string}		options.message				The question - what a browser without <dialog> is asked, with a `content`
		 *	@param		{Element}		[options.content]			Drawn in place of the message
		 *	@param		{Element}		[options.tools]				Drawn at the right of the title
		 *	@param		{string}		[options.className]		Added to the dialog's own class
		 *	@param		{string}		[options.size]				'narrow' (a question, the default), 'form', 'wide' or 'sheet' - the class nino-admin-dialog--<size>; a sheet stands at the right over the whole height
		 *	@param		{string}		[options.note]				A second paragraph under the message
		 *	@param		{Array}			[options.details]			What the question is about, as a list under the message: lines of text, and groups { title, lines } where the lines have a heading each (what a deletion takes and what it keeps). Drawn as <ul>, every line as text and never as markup; the caller has cut a long list short with a line of its own ("and 3 more")
		 *	@param		{Element}		[options.opener]			What gets the focus back when the dialog closes, where it is not what had it when the dialog opened: a button that was clicked has the focus in Chromium and Firefox and not in Safari
		 *	@param		{Element}		[options.focus]				What gets the focus when the dialog opens
		 *	@param		{boolean}		[options.nested]			The dialog asks something of an open dialog and stands on it
		 *	@param		{string}		[options.escape]			The value of the choice that Escape is, where it is not the first 'secondary' one
		 *	@param		{string}		[options.describedBy]	The ids that describe a dialog that holds `content`
		 *	@param		{boolean}		[options.dismiss]			A press that starts and ends on the darkened surface behind a modal dialog closes it as its secondary answer does - the guard asked, the focus back where it was. A press that starts inside the dialog and is let go outside it (a selection dragged out of a field) does not, nor does one on a dialog that is not the top one. Default false: a question is closed by an answer
		 *	@param		{boolean}		[options.modeless]		Not modal: no backdrop, no trap for the focus, the page behind stays in use; Escape closes it where the focus is in it or nobody else is asking. The workbench root carries nino-admin-has-sheet while it is open
		 *	@param		{boolean}		[options.refuseWithoutDialog]	Where the browser has no <dialog>, the answer is the secondary one without asking (a word that has to be typed cannot be answered by OK)
		 *	@param		{Array}			options.choices				[ { value, label, kind } ], kind 'primary', 'danger' or 'secondary'
		 *	@param		{Function}	[options.guard]				guard( value, handle ) - false keeps the dialog open
		 *	@param		{Function}	[options.onOpen]			onOpen( handle ) once the dialog is open
		 *	@param		{Function}	options.onChoose			Called once with the chosen value
		 *
		 *	@return		{boolean}										false when a question is already open
		 */
		choiceDialog : function( options ) {

			const modeless = options.modeless === true;

			if( modeless === true && Nino.adminUi._sheet !== null )
				return false;

			if( Nino.adminUi._choiceOpen === true && options.nested !== true )
				return false;

			const choices = options.choices;
			const cancel 	= choices.find( function( choice ) { return choice.value === options.escape } ) ?? choices.find( function( choice ) { return choice.kind === 'secondary' } ) ?? choices[choices.length - 1];
			const dialog 	= dc.createElement('dialog');
			const hasNote = typeof options.note === 'string' && options.note !== '' && ( options.content === undefined || options.content === null );

			if( typeof dialog.showModal !== 'function' ) {

				if( options.refuseWithoutDialog === true ) {
					options.onChoose( cancel.value );
					return true;
				}

				const yes = choices.find( function( choice ) { return choice.kind === 'primary' } ) ?? choices.find( function( choice ) { return choice.kind === 'danger' } ) ?? cancel;
				const listed = Nino.adminUi._detailLines( options.details );
				options.onChoose( wn.confirm( options.message+ ( hasNote === true ? '\n\n'+ options.note : '' )+ ( listed.length > 0 ? '\n\n'+ listed.join('\n') : '' ) ) === true ? yes.value : cancel.value );
				return true;
			}

			// What a second dialog on top of the first says its title and its text by must not be the ids of the first
			const sfx = modeless === false ? ( Nino.adminUi._choiceStack.length > 0 ? '-'+ Nino.adminUi._choiceStack.length : '' ) : '-sheet';

			if( modeless === false ) {
				Nino.adminUi._choiceStack.push( dialog );
				Nino.adminUi._choiceOpen = true;
			}

			const opener = options.opener ?? dc.activeElement;
			const hasContent = options.content !== undefined && options.content !== null;
			let chosen = null;

			// A dialog that brings a size of its own with a class of its own is not made narrow beside it
			const hasClass = typeof options.className === 'string' && options.className !== '';

			dialog.className = 'nino-admin-dialog'+ ( typeof options.size === 'string' && options.size !== '' ? ' nino-admin-dialog--'+ options.size : ( hasClass === false ? ' nino-admin-dialog--narrow' : '' ) )+ ( hasClass === true ? ' '+ options.className : '' );

			const detailList = hasContent === false ? Nino.adminUi._details( options.details ) : null;

			// A message is what the dialog is about and is read out with it; a
			// manual is as long as it is and is read when it is looked at
			if( hasContent === false )
				dialog.setAttribute( 'aria-describedby', 'nino-admin-choice-text'+ sfx+ ( hasNote === true ? ' nino-admin-choice-note'+ sfx : '' )+ ( detailList !== null ? ' nino-admin-choice-details'+ sfx : '' ) );
			else if( typeof options.describedBy === 'string' )
				dialog.setAttribute( 'aria-describedby', options.describedBy );

			const body = dc.createElement('div');
			body.className = 'nino-admin-dialog-body';

			if( typeof options.title === 'string' && options.title !== '' ) {
				const head = dc.createElement('div');
				head.className = 'nino-admin-dialog-head';

				const title = dc.createElement('h2');
				title.id = 'nino-admin-choice-title'+ sfx;
				title.className = 'nino-admin-dialog-title';
				title.textContent = options.title;
				head.appendChild( title );

				if( options.tools !== undefined && options.tools !== null )
					head.appendChild( options.tools );

				body.appendChild( head );
				dialog.setAttribute( 'aria-labelledby', 'nino-admin-choice-title'+ sfx );
			}

			const scroll = dc.createElement('div');
			scroll.className = 'nino-admin-dialog-content';

			if( hasContent === true )
				scroll.appendChild( options.content );
			else {
				const text = dc.createElement('p');
				text.id = 'nino-admin-choice-text'+ sfx;
				text.textContent = options.message;
				scroll.appendChild( text );

				if( hasNote === true ) {
					const note = dc.createElement('p');
					note.id = 'nino-admin-choice-note'+ sfx;
					note.textContent = options.note;
					scroll.appendChild( note );
				}

				if( detailList !== null ) {
					detailList.id = 'nino-admin-choice-details'+ sfx;
					scroll.appendChild( detailList );
				}
			}

			body.appendChild( scroll );

			const actions = dc.createElement('div');
			actions.className = 'nino-admin-dialog-actions';

			// A question whose way on is a 'danger' one and has no 'primary' one - the
			// one that destroys what is there, with nothing safe to press at its
			// side - opens with the focus on the answer that does nothing
			const cautious = choices.some( function( choice ) { return choice.kind === 'danger' } ) === true && choices.some( function( choice ) { return choice.kind === 'primary' } ) === false;
			const initial 	= cautious === true ? cancel : ( choices.find( function( choice ) { return choice.kind === 'primary' } ) ?? choices[0] );

			const buttons = {};

			choices.forEach( function( choice ) {

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-btn-'+ ( choice.kind === 'primary' || choice.kind === 'danger' ? choice.kind : 'secondary' );
				btn.textContent = choice.label;
				btn.autofocus = options.focus === undefined && choice === initial;
				btn.addEventListener( 'click', function() { handle.close( choice.value, true ) } );
				buttons[choice.value] = btn;
				actions.appendChild( btn );
			} );

			body.appendChild( actions );
			dialog.appendChild( body );

			// What the caller gets to open and close the dialog by
			const handle = {
				el : dialog,
				buttons : buttons,
				close : function( value, ask ) {

					const choice = choices.find( function( one ) { return one.value === value } ) ?? { value : value };

					if( ask === true && typeof options.guard === 'function' && options.guard( choice.value, handle ) === false )
						return;

					chosen = choice;
					dialog.close();
				},
			};

			// The Escape key closes it too, and says no - unless the guard has a reason to keep it. A dialog
			// that has a guard takes the key itself: a browser lets a cancelled close pass the second time
			// the key is pressed with nothing done in between, so cancel alone does not keep a form open
			if( typeof options.guard === 'function' )
				dialog.addEventListener( 'keydown', function( event ) {
					if( event.key !== 'Escape' )
						return;
					event.preventDefault();
					handle.close( cancel.value, true );
				} );

			// The surface behind a modal dialog is the dialog's own box as far as an event can tell: a press on it has the dialog itself as its
			// target (the dialog is all body, no padding), a press inside has an element of it. Both ends of the press are asked for, because
			// a drag that starts in a field and is let go beside the dialog ends as a click on the dialog
			if( options.dismiss === true && modeless === false ) {

				let pressed = false;

				dialog.addEventListener( 'pointerdown', function( event ) {
					pressed = event.target === dialog;
				} );

				dialog.addEventListener( 'pointerup', function( event ) {

					const surface = pressed === true && event.target === dialog;
					pressed = false;

					if( surface === true && Nino.adminUi._choiceStack[Nino.adminUi._choiceStack.length - 1] === dialog )
						handle.close( cancel.value, true );
				} );
			}

			dialog.addEventListener( 'cancel', function( event ) {
				if( typeof options.guard === 'function' && options.guard( cancel.value, handle ) === false )
					event.preventDefault();
			} );

			// A dialog that is not modal has no Escape of the browser's: the key closes it where the focus is in it, or
			// where no modal dialog is open to take the key
			const onKey = function( event ) {
				if( event.key === 'Escape' && ( dialog.contains( dc.activeElement ) === true || Nino.adminUi._choiceOpen === false ) ) {
					event.preventDefault();
					handle.close( cancel.value, true );
				}
			};

			if( modeless === true )
				dc.addEventListener( 'keydown', onKey );

			dialog.addEventListener( 'close', function() {
				dialog.remove();

				if( modeless === true ) {
					dc.removeEventListener( 'keydown', onKey );
					Nino.adminUi._sheet = null;
					( dc.getElementById('admin-page-wrap') ?? dc.body ).classList.remove('nino-admin-has-sheet');
				} else {
					const at = Nino.adminUi._choiceStack.indexOf( dialog );
					if( at !== -1 )
						Nino.adminUi._choiceStack.splice( at, 1 );
					Nino.adminUi._choiceOpen = Nino.adminUi._choiceStack.length > 0;
					release();
				}

				if( opener !== null && typeof opener.focus === 'function' )
					opener.focus();
				options.onChoose( ( chosen ?? cancel ).value );
			} );

			if( options.focus !== undefined && options.focus !== null )
				options.focus.autofocus = true;

			// Inside the workbench root, not the body: the design system's rules
			// are scoped to .nino-admin and would not reach it there
			const root = dc.getElementById('admin-page-wrap') ?? dc.body;
			root.appendChild( dialog );

			// A modal dialog keeps the Tab key inside it: the rest of the page is inert and Tab and Shift+Tab walk round the dialog
			const release = modeless === true ? null : Nino.adminUi._trap( dialog );

			if( modeless === true ) {
				Nino.adminUi._sheet = handle;
				root.classList.add('nino-admin-has-sheet');
				dialog.show();
			} else
				dialog.showModal();

			// The browser picks the first control of the dialog where nothing says otherwise; here something does
			const first = options.focus ?? buttons[initial.value];

			if( first !== undefined && first !== null && typeof first.focus === 'function' )
				first.focus();

			if( typeof options.onOpen === 'function' )
				options.onOpen( handle );

			return true;
		},

		/**
		 *	What a question is about, read into groups: a list of lines of text, and of groups { title, lines } - the
		 *	lines that stand one after the other without a group are one group with no title. Anything else is left
		 *	out, and so is a line that is empty
		 *
		 *	@param		{*}				details
		 *
		 *	@return		{Array}									[ { title, lines } ]
		 */
		_detailGroups : function( details ) {

			const groups = [];
			let plain = null;

			( Array.isArray( details ) === true ? details : [] ).forEach( function( item ) {

				if( typeof item === 'string' ) {

					if( item === '' )
						return;

					if( plain === null ) {
						plain = { title : '', lines : [] };
						groups.push( plain );
					}

					plain.lines.push( item );
					return;
				}

				plain = null;

				if( item === null || typeof item !== 'object' || Array.isArray( item.lines ) === false )
					return;

				const lines = item.lines.map( String ).filter( function( line ) { return line !== '' } );

				if( lines.length > 0 )
					groups.push( { title : typeof item.title === 'string' ? item.title : '', lines : lines } );
			} );

			return groups;
		},

		/**
		 *	The details of a question as lines of plain text, for a browser that has no <dialog> and asks with its own
		 *
		 *	@param		{*}				details
		 *
		 *	@return		{Array<string>}
		 */
		_detailLines : function( details ) {

			const lines = [];

			Nino.adminUi._detailGroups( details ).forEach( function( group ) {

				if( group.title !== '' )
					lines.push( group.title );

				group.lines.forEach( function( line ) { lines.push( '- '+ line ) } );
			} );

			return lines;
		},

		/**
		 *	The details of a question as a list: for each group its title, if it has one, and its lines as a <ul>. Every
		 *	line is set as text - a line that spells markup is shown as the letters it is
		 *
		 *	@param		{*}				details			See choiceDialog()
		 *
		 *	@return		{Element|null}							null where there is nothing to list
		 */
		_details : function( details ) {

			const groups = Nino.adminUi._detailGroups( details );

			if( groups.length === 0 )
				return null;

			const root = dc.createElement('div');
			root.className = 'nino-admin-dialog-details';

			groups.forEach( function( group ) {

				if( group.title !== '' )
					root.appendChild( Nino.adminUi.el( 'p', 'nino-admin-dialog-details-title', group.title ) );

				const list = dc.createElement('ul');
				list.className = 'nino-admin-dialog-details-list';

				group.lines.forEach( function( line ) { list.appendChild( Nino.adminUi.el( 'li', '', line ) ) } );
				root.appendChild( list );
			} );

			return root;
		},

		/**
		 *	Keep the Tab key inside a modal dialog: everything of the page that is not the dialog is made inert - what was
		 *	not inert already - and Tab at the last control goes to the first, Shift+Tab at the first to the last. The
		 *	browser's own modal does the first; the second is what a browser leaves to the person's browser chrome, and
		 *	what a dialog on top of a dialog needs, whose lower one stands beside it in the page
		 *
		 *	@param		{Element}		dialog
		 *
		 *	@return		{Function}								Lets the page go again: call it when the dialog has closed
		 */
		_trap : function( dialog ) {

			const held = [];
			const skip = [ 'SCRIPT', 'STYLE', 'LINK', 'META', 'TITLE', 'HEAD' ];

			for( let node = dialog; node !== null && node !== undefined && node !== dc.body && node.parentNode !== null && node.parentNode !== undefined; node = node.parentNode )
				Array.from( node.parentNode.children ).forEach( function( sibling ) {

					if( sibling === node || sibling.inert === true || skip.indexOf( String( sibling.tagName ).toUpperCase() ) !== -1 )
						return;

					// The toast is a live region the person has to hear while a dialog is open
					if( sibling.classList !== undefined && sibling.classList.contains('nino-admin-toasts') === true )
						return;

					sibling.inert = true;
					held.push( sibling );
				} );

			const stops = function() {

				return Array.from( dialog.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex]') ).filter( function( el ) {

					if( el.disabled === true || el.hidden === true || el.type === 'hidden' || el.inert === true || el.tabIndex < 0 )
						return false;

					if( typeof el.closest === 'function' && el.closest('[hidden]') !== null )
						return false;

					return typeof el.getClientRects !== 'function' || el.getClientRects().length > 0;
				} );
			};

			const onKey = function( event ) {

				if( event.key !== 'Tab' )
					return;

				const all = stops();

				if( all.length === 0 ) {
					event.preventDefault();
					return;
				}

				const at = all.indexOf( dc.activeElement );

				if( event.shiftKey === true && at <= 0 ) {
					event.preventDefault();
					all[all.length - 1].focus();
				} else if( event.shiftKey !== true && ( at === -1 || at === all.length - 1 ) ) {
					event.preventDefault();
					all[0].focus();
				}
			};

			dialog.addEventListener( 'keydown', onKey );

			return function() {
				dialog.removeEventListener( 'keydown', onKey );
				held.forEach( function( el ) { el.inert = false } );
			};
		},

		/**
		 *	A question with two answers, asked as heavily as the workbench's mode
		 *	asks it. `level` says what the action is worth - 'routine' is done all
		 *	day, 'big' reaches visitors or many records at once, 'destructive'
		 *	cannot be taken back - and the shell, which is the policy
		 *	(Nino.admin.mode.policy()), answers whether the question is asked at
		 *	all and what has to be added to it. A site that is being built asks
		 *	only before a destructive action, and onChoose( true ) comes at once,
		 *	before confirm() returns, where the answer is not asked for. Where
		 *	there is no shell with a policy - the setup wizard, a test, a shell
		 *	older than the policy - every question is asked, as confirm() always
		 *	asked.
		 *
		 *	The question is choiceDialog()'s and owns no words: `title`, `yes` and
		 *	`no` are what the dialog and the answers are called, in the interface
		 *	language. The title is the act and the thing - "Delete element" - and
		 *	the answer that goes on carries the same words, never "Yes" or "OK". A
		 *	question without a title throws a TypeError, also where the policy
		 *	would not ask it: the mistake is in the call and shows in development.
		 *	The note the policy adds is drawn under the message as a paragraph of
		 *	its own. The answers stand with Cancel at the left of the one that
		 *	goes on.
		 *
		 *	`typed` is the highest weight of a question: the way on stays locked
		 *	until the person has typed that word, which the dialog names under the
		 *	message. It is for the thing that takes many others with it (an element
		 *	type with all of its elements) and is asked even where the policy would
		 *	not ask - a word that was asked for is not dropped by a mode. What was
		 *	typed is handed to onChoose, for the caller to send as the proof of it;
		 *	a browser with no <dialog> answers No, since OK is no word
		 *
		 *	@param		{string}		level									'routine', 'big' or 'destructive'; anything else is taken for the last
		 *	@param		{Object}		options
		 *	@param		{string}		options.title					What is done to what, as a statement: "Delete element"
		 *	@param		{string}		options.message					The question
		 *	@param		{Array}			[options.details]				What it is about, as a list under the message - lines, and groups { title, lines }; see choiceDialog(). A deletion says here what it takes and what it keeps; the caller cuts a long list short with a line of its own ("and 3 more")
		 *	@param		{string}		options.yes							The label of the answer that goes on
		 *	@param		{string}		options.no							The label of the answer that does not
		 *	@param		{string}		[options.kind]					'danger' or 'primary' for the answer that goes on: 'danger' for a destructive level, else 'primary'. Where something goes away or is switched off, the caller says 'danger' - what the level costs a visitor and what the action costs the person are two questions
		 *	@param		{string}		[options.typed]					The word to type before the answer that goes on is open
		 *	@param		{string}		[options.typedLabel]		What the field says, with %s for the word; /_admin/common/confirm/typed by default
		 *	@param		{boolean}		[options.visitors]			false where the action does not touch what visitors see (an account, a role): the policy's note that visitors see the change at once is left out
		 *	@param		{Element}		[options.opener]				See choiceDialog()
		 *	@param		{boolean}		[options.nested]				The question is asked of an open dialog - a form that asks before it throws something away - and stands on it: the focus goes back into the dialog below when it closes, and Escape closes this one alone. See choiceDialog()
		 *	@param		{Function}	options.onChoose				Called once with true or false, and with `typed` the word that was typed as the second argument - not at all when false is returned
		 *
		 *	@return		{boolean}												false when another question is already open and this one was not asked, which is the same click arriving twice
		 */
		confirm : function( level, options ) {

			if( typeof options.title !== 'string' || options.title.trim() === '' )
				throw new TypeError( 'Nino.adminUi.confirm(): a question needs a title' );

			const typed 	= typeof options.typed === 'string' && options.typed !== '' ? options.typed : null;
			const mode 		= shell().mode;
			const policy 	= typeof mode === 'object' && mode !== null && typeof mode.policy === 'function' ? mode.policy( level ) : { ask : true, note : '' };

			if( policy.ask === false && typed === null ) {
				options.onChoose( true );
				return true;
			}

			let field = null;

			if( typed !== null ) {
				field = dc.createElement('input');
				field.type = 'text';
				field.className = 'nino-admin-input';
				field.autocomplete = 'off';
				field.setAttribute( 'autocapitalize', 'off' );
				field.setAttribute( 'spellcheck', 'false' );
				field.dataset.dirty = 'ignore';
			}

			let body = null;
			let ids = 'nino-admin-choice-text';

			if( field !== null ) {
				body = dc.createElement('div');
				body.className = 'nino-admin-dialog-typed';

				const message = dc.createElement('p');
				message.id = 'nino-admin-choice-text';
				message.textContent = options.message;
				body.appendChild( message );

				if( policy.note !== '' && options.visitors !== false ) {
					ids += ' nino-admin-choice-note';
					const note = dc.createElement('p');
					note.id = 'nino-admin-choice-note';
					note.textContent = policy.note;
					body.appendChild( note );
				}

				const listed = Nino.adminUi._details( options.details );

				if( listed !== null ) {
					ids += ' nino-admin-choice-details';
					listed.id = 'nino-admin-choice-details';
					body.appendChild( listed );
				}

				const label = dc.createElement('label');
				label.className = 'nino-admin-field';
				label.appendChild( Nino.adminUi.el( 'span', '', Nino.adminUi.format( options.typedLabel ?? Nino.adminUi.text('/_admin/common/confirm/typed'), typed ) ) );
				label.appendChild( field );
				body.appendChild( label );
			}

			return Nino.adminUi.choiceDialog( {
				title			: options.title,
				message		: options.message,
				content		: body ?? undefined,
				details		: field === null ? options.details : undefined,
				note			: options.visitors === false ? '' : policy.note,
				opener		: options.opener,
				nested		: options.nested === true,
				focus			: field ?? undefined,
				describedBy : field === null ? undefined : ids,
				refuseWithoutDialog : field !== null,
				choices		: [
					{ value : 'no', label : options.no, kind : 'secondary' },
					{ value : 'yes', label : options.yes, kind : options.kind ?? ( level === 'routine' || level === 'big' ? 'primary' : 'danger' ) },
				],
				guard			: field === null ? undefined : function( value ) { return value !== 'yes' || field.value.trim() === typed },
				onOpen		: field === null ? undefined : function( handle ) {

					const unlock = function() { handle.buttons.yes.disabled = field.value.trim() !== typed };

					unlock();
					field.addEventListener( 'input', unlock );
					field.addEventListener( 'keydown', function( event ) {
						if( event.key !== 'Enter' )
							return;
						event.preventDefault();
						handle.close( 'yes', true );
					} );
				},
				onChoose	: function( value ) { options.onChoose( value === 'yes', field === null ? undefined : field.value.trim() ) },
			} );
		},

		/**
		 *	The fields of a form in a dialog, drawn by the builders of the
		 *	workbench - the same family a screen's form is made of: a text, a
		 *	select, whatever input type textField() takes, and the fields of the
		 *	second part - a tick, a list of ticks, the languages one under the
		 *	other, a file, code, a date with a time. The label stands at the left
		 *	of the control where the dialog is wide enough and above it where it
		 *	is not (style.css decides). Owns no strings
		 *
		 *	@param		{Array}			fields					[ { key, label, help, note, type, value, placeholder, required, maxLength, autocomplete, options, validate } ] - help stands behind the (?) at the name, note stays in view; placeholder is an example of what is typed ("#ffffff") in a field that takes text - it is never the name of the field.
		 *																	type 'select' takes options [ { value, label } ]; 'check' a tick (value true or false); 'checklist' ticks (options [ { value, label } ], value the list of the ticked); 'tiles' one tile chosen in each of several groups (groups as tileChoice() takes them, value an object group => value); 'locales' a row for each language (locales, values a map, multiline); 'file' (accept, maxBytes); 'code' (rows); 'datetime' (min, max, zone, value an ISO string);
		 *																	any other type is the type of an <input>. validate( value ) answers the sentence that is wrong with it, or ''; a required field is wrong when it is empty - a tick not ticked, a list with none, a language with no text, no file
		 *	@param		{Function}	[onInput]				onInput( key, value ) on every change of a field
		 *
		 *	@return		{Object}									{ el, controls, labels, handles, values(), initial, serial( value ), empty( field, value ) } - controls is key => the control that gets the focus, labels key => the element of the field, values() key => the value as the field gives it (a string, true or false, a list, a map of the languages, a File or null), serial() what two values are compared by
		 */
		_dialogFields : function( fields, onInput ) {

			const el = dc.createElement('div');
			el.className = 'nino-admin-dialog-fields';

			const controls = {};
			const labels = {};
			const handles = {};
			const readers = {};
			const initial = {};

			// What two values are compared by, to know whether a form changed: a text as it is, anything else as its json - a file by what names it
			const serial = function( value ) {

				if( typeof value === 'string' )
					return value;

				if( value !== null && typeof value === 'object' && typeof value.name === 'string' && typeof value.size === 'number' )
					return 'file:'+ value.name+ ':'+ value.size+ ':'+ String( value.lastModified ?? '' );

				return JSON.stringify( value ?? null );
			};

			fields.forEach( function( field ) {

				const say = function( value ) { if( typeof onInput === 'function' ) onInput( field.key, value ) };
				const shared = { key : field.key, label : field.label, help : field.help, note : field.note, required : field.required };
				let label;

				if( field.type === 'select' ) {
					label = Nino.adminUi.selectField( { key : field.key, label : field.label, help : field.help, note : field.note, options : field.options, value : field.value ?? '', onChange : say } );
					controls[field.key] = label.querySelector('select');
					readers[field.key] = function() { return controls[field.key].value };
				} else if( field.type === 'check' ) {
					label = Nino.adminUi.checkField( Object.assign( shared, { checked : field.value === true, onChange : say } ) );
					controls[field.key] = label.control;
					readers[field.key] = function() { return label.control.checked === true };
				} else if( field.type === 'checklist' ) {
					handles[field.key] = Nino.adminUi.checklist( Object.assign( shared, { options : field.options, value : field.value, onChange : say } ) );
					label = handles[field.key].el;
					controls[field.key] = handles[field.key].controls[0] ?? label;
					readers[field.key] = function() { return handles[field.key].value() };
				} else if( field.type === 'tiles' ) {
					handles[field.key] = Nino.adminUi.tileChoice( Object.assign( shared, { groups : field.groups, draw : field.draw, onChange : function( group, value, values ) { say( values ) } } ) );
					label = handles[field.key].el;
					controls[field.key] = handles[field.key].controls[0] ?? label;
					readers[field.key] = function() { return handles[field.key].value() };
				} else if( field.type === 'locales' ) {
					handles[field.key] = Nino.adminUi.localeRows( Object.assign( shared, { locales : field.locales, work : field.work, values : field.values ?? field.value, multiline : field.multiline, rows : field.rows, maxLength : field.maxLength, placeholder : field.placeholder, onChange : function() { say( handles[field.key].value() ) } } ) );
					label = handles[field.key].el;
					controls[field.key] = handles[field.key].controls[handles[field.key].locales[0]] ?? label;
					readers[field.key] = function() { return handles[field.key].value() };
				} else if( field.type === 'file' ) {
					handles[field.key] = Nino.adminUi.fileField( Object.assign( shared, { accept : field.accept, maxBytes : field.maxBytes, onChange : say } ) );
					label = handles[field.key].el;
					controls[field.key] = handles[field.key].button;
					readers[field.key] = function() { return handles[field.key].value() };
				} else if( field.type === 'code' ) {
					label = Nino.adminUi.codeField( Object.assign( shared, { value : field.value, placeholder : field.placeholder, rows : field.rows, maxLength : field.maxLength, onInput : say } ) );
					controls[field.key] = label.control;
					readers[field.key] = function() { return label.control.value };
				} else if( field.type === 'datetime' ) {
					handles[field.key] = Nino.adminUi.dateTime( Object.assign( shared, { value : field.value, min : field.min, max : field.max, zone : field.zone, onChange : say } ) );
					label = handles[field.key].el;
					controls[field.key] = handles[field.key].date;
					readers[field.key] = function() { return handles[field.key].value() };
				} else {
					label = Nino.adminUi.textField( {
						key				: field.key,
						label			: field.label,
						help			: field.help,
						note			: field.note,
						type			: field.type,
						value			: field.value,
						placeholder	: field.placeholder,
						required	: field.required,
						maxLength	: field.maxLength,
						autocomplete : field.autocomplete ?? 'off',
						onInput		: say,
					} );
					controls[field.key] = label.querySelector('input');
					readers[field.key] = function() { return controls[field.key].value };
				}

				labels[field.key] = label;
				initial[field.key] = serial( readers[field.key]() );
				el.appendChild( label );
			} );

			return {
				el 				: el,
				controls	: controls,
				labels		: labels,
				handles		: handles,
				initial		: initial,
				serial		: serial,
				values		: function() {
					const values = {};
					fields.forEach( function( field ) { values[field.key] = readers[field.key]() } );
					return values;
				},
				// Whether a required field has nothing in it: a text of blanks, a tick that is not ticked, a list with none, a language with no text, no file
				empty			: function( field, value ) {

					if( typeof value === 'string' )
						return value.trim() === '';

					if( typeof value === 'boolean' )
						return value === false;

					if( Array.isArray( value ) === true )
						return value.length === 0;

					if( value !== null && typeof value === 'object' && typeof value.size !== 'number' )
						return Object.keys( value ).length === 0 || Object.keys( value ).some( function( code ) { return String( value[code] ?? '' ).trim() === '' } );

					return value === null || value === undefined;
				},
			};
		},

		/**
		 *	A form in a dialog: for something that is made or copied from a name
		 *	and at most four more fields (a duplicate, a "save as"); a form of its
		 *	own record, with more fields or an address, is a screen. The dialog is
		 *	choiceDialog() with the fields as its content: the title and the
		 *	answers stand, the fields scroll, the focus opens on the first field,
		 *	Enter in a field sends, and Cancel goes back to what had the focus.
		 *
		 *	What is wrong with a field is said at the field (fieldError()) and the
		 *	dialog stays open: a required field that is empty, a field's own
		 *	validate(), then what the server refused (ctl.fail()). The dialog
		 *	stays open until the caller says ctl.close() - it is the server that
		 *	says yes. While the request is out the dialog does not close and does
		 *	not send twice. Cancel, Escape and the dialog's own close ask once
		 *	whether to throw away what was typed - and only when something was
		 *	changed; an unchanged form closes without a word. It takes no
		 *	`dismiss` (see choiceDialog()): a press on the surface beside a form
		 *	never closes it, whatever was typed
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		options.title					What is made of what, as a statement: "Duplicate theme"
		 *	@param		{Array}			[options.fields]			See _dialogFields(): every field of the family, not only a text and a select
		 *	@param		{Element}		[options.body]				The content in place of the fields; the caller reads its controls itself, and a form of this kind counts as changed from its first input
		 *	@param		{string}		[options.message]			A sentence above the fields
		 *	@param		{string}		options.submit				The label of the answer that goes on, the title's words: "Duplicate theme"
		 *	@param		{string}		options.cancel				The label of the answer that does not
		 *	@param		{string}		[options.size]				'form' (the default), 'narrow' or 'wide'
		 *	@param		{Object}		[options.text]				{ missing, discardTitle, discardMessage } - /_admin/common/error/missing (with %s for the field's label), /_admin/common/dialog/discard-title, /_admin/common/dialog/discard-message by default
		 *	@param		{Element}		[options.opener]			See choiceDialog()
		 *	@param		{Function}	options.onSubmit			onSubmit( values, ctl ) with ctl { close(), fail( key, text ), idle() }: close() closes the dialog, fail() says a sentence at a field (key null: at the foot of the form) and lets the form be sent again, idle() only the latter
		 *	@param		{Function}	[options.onCancel]		Called when the dialog closed without a send
		 *
		 *	@return		{boolean}										false when a dialog is already open
		 */
		formDialog : function( options ) {

			const word = function( key, fill ) { return ( options.text ?? {} )[key] ?? Nino.adminUi.text( fill ) };
			const fields = Array.isArray( options.fields ) === true ? options.fields : [];
			let changed = false;
			let busy = false;
			let errors = 0;

			const made = Nino.adminUi._dialogFields( fields, function() { changed = true } );
			const form = dc.createElement('form');
			form.className = 'nino-admin-dialog-form';
			form.noValidate = true;

			if( typeof options.message === 'string' && options.message !== '' )
				form.appendChild( Nino.adminUi.el( 'p', '', options.message ) );

			form.appendChild( options.body ?? made.el );

			// What is wrong is said to a screen reader once, as the first sentence of a send: the focus is on that
			// field already where Enter was pressed in it, and a focus that does not move says nothing
			const live = dc.createElement('p');
			live.className = 'nino-admin-sr-only';
			live.setAttribute( 'role', 'alert' );
			form.appendChild( live );

			if( options.body !== undefined && options.body !== null ) {
				form.addEventListener( 'input', function() { changed = true } );
				form.addEventListener( 'change', function() { changed = true } );
			}

			// A field says what is wrong with it, once: the sentences of the last send go first
			const say = function( key, text ) {

				const label = made.labels[key];

				if( live.textContent === '' )
					live.textContent = text;

				if( label === undefined ) {
					const p = dc.createElement('p');
					p.className = 'nino-admin-field-error';
					p.setAttribute( 'role', 'alert' );
					p.textContent = text;
					form.appendChild( p );
					return null;
				}

				Nino.adminUi.fieldError( label, made.controls[key], 'nino-admin-dialog-error-'+ ( ++errors ), text );
				return made.controls[key];
			};

			const differs = function() {
				if( options.fields === undefined )
					return changed;
				const now = made.values();
				return fields.some( function( field ) { return made.serial( now[field.key] ) !== made.initial[field.key] } );
			};

			const send = function( handle ) {

				if( busy === true )
					return;

				Nino.adminUi.clearFieldErrors( form );
				live.textContent = '';

				const values = made.values();
				let firstBad = null;

				fields.forEach( function( field ) {

					const value = values[field.key];
					let problem = '';

					if( field.required === true && made.empty( field, value ) === true )
						problem = Nino.adminUi.format( word( 'missing', '/_admin/common/error/missing' ), field.label );
					else if( typeof field.validate === 'function' )
						problem = field.validate( value ) ?? '';

					if( problem !== '' ) {
						const control = say( field.key, problem );
						firstBad = firstBad ?? control;
					}
				} );

				if( firstBad !== null ) {
					firstBad.focus();
					return;
				}

				busy = true;
				handle.buttons.submit.disabled = true;
				form.setAttribute( 'aria-busy', 'true' );

				const idle = function() {
					busy = false;
					handle.buttons.submit.disabled = false;
					form.removeAttribute('aria-busy');
				};

				options.onSubmit( values, {
					close : function() { busy = false; handle.close( 'submit' ) },
					idle : idle,
					fail : function( key, text ) {
						idle();
						live.textContent = '';
						const control = say( key, text );
						if( control !== null )
							control.focus();
					},
				} );
			};

			const first = made.controls[ fields.length > 0 ? fields[0].key : '' ] ?? form.querySelector('input, select, textarea');

			return Nino.adminUi.choiceDialog( {
				title			: options.title,
				message		: options.title,
				content		: form,
				size			: options.size ?? 'form',
				opener		: options.opener,
				focus			: first ?? undefined,
				choices		: [
					{ value : 'cancel', label : options.cancel, kind : 'secondary' },
					{ value : 'submit', label : options.submit, kind : 'primary' },
				],
				guard			: function( value, handle ) {

					if( value === 'submit' ) {
						send( handle );
						return false;
					}

					// A send that is on its way is not taken back by closing the form
					if( busy === true )
						return false;

					if( differs() === false )
						return true;

					Nino.adminUi.choiceDialog( {
						title			: word( 'discardTitle', '/_admin/common/dialog/discard-title' ),
						message		: word( 'discardMessage', '/_admin/common/dialog/discard-message' ),
						nested		: true,
						choices		: [
							{ value : 'no', label : options.cancel, kind : 'secondary' },
							{ value : 'yes', label : word( 'discardTitle', '/_admin/common/dialog/discard-title' ), kind : 'danger' },
						],
						onChoose	: function( answer ) {
							if( answer === 'yes' ) {
								changed = false;
								handle.close( 'cancel' );
							}
						},
					} );

					return false;
				},
				onOpen		: function( handle ) {
					form.addEventListener( 'submit', function( event ) {
						event.preventDefault();
						handle.close( 'submit', true );
					} );
					form.addEventListener( 'keydown', function( event ) {
						if( event.key !== 'Enter' || event.target.tagName === 'TEXTAREA' || event.target.tagName === 'BUTTON' )
							return;
						event.preventDefault();
						handle.close( 'submit', true );
					} );
				},
				onChoose	: function( value ) {
					if( value !== 'submit' && typeof options.onCancel === 'function' )
						options.onCancel();
				},
			} );
		},

		// From how many items pickDialog() offers a search: fewer are found by looking
		PICK_SEARCH_FROM : 8,

		/**
		 *	One thing picked from a list, in a dialog: a block, a source, a
		 *	component. Each item is a button of a row - its name and, under it, a
		 *	line of what it is - and a press on it picks it and closes the dialog.
		 *	The only answer is Cancel. From PICK_SEARCH_FROM items on there is a
		 *	search over the names and the lines, which has the focus; Enter in it
		 *	picks the first that is left, and Down walks into the list; a list
		 *	that is shorter is looked at, not searched, and the focus opens on its
		 *	first item. With groups or pictures the arrow keys walk the tiles in every direction (Left and Right along a row, Up and Down to the tile
		 *	over or under, Home and End to the ends), over the groups, which have no stop of their own - a group is role="group", named by its heading - and the search
		 *	hides a group that has nothing left. The size is 'wide' unless the caller says otherwise
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		options.title					What is picked, as a statement: "Insert block"
		 *	@param		{Array}			options.items					[ { value, label, description, code, group, image } ] - code is a notation that stands in front of the description, in the face of code;
		 *																			group a text: items of the same group stand under one heading, the groups in the order they first appear (an item with none stands where it
		 *																			comes, under no heading); image the address of a picture of the item: then every item is a tile - the picture of a fixed shape over the name, as
		 *																			many in a row as fit - and an item without one has a quiet surface of the same size. A picture is decoration (alt=""), loaded when it comes
		 *																			near, and the name says the item. With neither on any item the dialog is the list it was
		 *	@param		{string}		[options.search]			What the search field is called ("Search blocks"); none, no search however many items there are
		 *	@param		{number}		[options.searchFrom]	From how many items there is a search, PICK_SEARCH_FROM by default
		 *	@param		{Function}	[options.onRow]				onRow( value, row, pick ) in place of picking at once: the caller decides when the pick is made, by calling pick()
		 *	@param		{string}		[options.empty]				What a search that finds nothing says
		 *	@param		{string}		options.cancel				The label of Cancel
		 *	@param		{string}		[options.size]				'wide' (the default), 'form' or 'narrow'
		 *	@param		{Element}		[options.opener]			See choiceDialog()
		 *	@param		{Function}	options.onChoose			Called once with the value of the picked item
		 *	@param		{Function}	[options.onCancel]		Called when nothing was picked
		 *
		 *	@return		{boolean}										false when a dialog is already open
		 */
		pickDialog : function( options ) {

			const root = dc.createElement('div');
			root.className = 'nino-admin-pick';

			const searchable = typeof options.search === 'string' && options.search !== '' && options.items.length >= ( options.searchFrom ?? Nino.adminUi.PICK_SEARCH_FROM );
			let search = null;
			let handle = null;
			let picked = null;

			// Groups and pictures are asked for by the items; without either the markup is the list it always was
			const named = function( item ) { return typeof item.group === 'string' ? item.group : '' };
			const grouped = options.items.some( function( item ) { return named( item ) !== '' } );
			const tiled = options.items.some( function( item ) { return typeof item.image === 'string' && item.image !== '' } );
			const sets = new Map();

			const list = dc.createElement('div');
			list.className = 'nino-admin-pick-list'+ ( tiled === true ? ' nino-admin-pick-list--tiles' : '' );

			// Where an item goes: the list, or the set of its group - made at the first item of the group, under a heading that names it
			const setOf = function( item ) {

				if( grouped === false && tiled === false )
					return list;

				const name = named( item );

				if( sets.has( name ) === false ) {

					const box = Nino.adminUi.el( 'div', 'nino-admin-pick-group' );
					const inner = Nino.adminUi.el( 'div', 'nino-admin-pick-set' );

					if( name !== '' ) {
						const title = Nino.adminUi.el( 'h3', 'nino-admin-pick-group-title', name );
						title.id = 'nino-admin-pick-group-'+ ( ++serial );
						box.setAttribute( 'role', 'group' );
						box.setAttribute( 'aria-labelledby', title.id );
						box.appendChild( title );
					}

					box.appendChild( inner );
					list.appendChild( box );
					sets.set( name, { box : box, inner : inner } );
				}

				return sets.get( name ).inner;
			};

			const rows = options.items.map( function( item ) {

				const row = dc.createElement('button');
				row.type = 'button';
				row.className = 'nino-admin-pick-item'+ ( tiled === true ? ' nino-admin-pick-item--tile' : '' );

				if( tiled === true ) {

					const face = Nino.adminUi.el( 'span', 'nino-admin-pick-image' );

					if( typeof item.image === 'string' && item.image !== '' ) {
						const picture = dc.createElement('img');
						picture.src = item.image;
						picture.alt = '';
						picture.loading = 'lazy';
						picture.decoding = 'async';
						face.appendChild( picture );
					}

					face.setAttribute( 'aria-hidden', 'true' );
					row.appendChild( face );
				}

				row.appendChild( Nino.adminUi.el( 'span', 'nino-admin-pick-name', item.label ) );

				const hasCode = typeof item.code === 'string' && item.code !== '';
				const hasLine = typeof item.description === 'string' && item.description !== '';

				if( hasCode === true || hasLine === true ) {
					const line = Nino.adminUi.el( 'span', 'nino-admin-pick-line' );

					if( hasCode === true )
						line.appendChild( Nino.adminUi.el( 'code', 'nino-admin-pick-code', item.code ) );

					if( hasLine === true )
						line.appendChild( dc.createTextNode( ( hasCode === true ? ' \u00B7 ' : '' )+ item.description ) );

					row.appendChild( line );
				}

				row.addEventListener( 'click', function() {

					const pick = function() { picked = item.value; handle.close( 'pick' ) };

					if( typeof options.onRow === 'function' )
						options.onRow( item.value, row, pick );
					else
						pick();
				} );
				setOf( item ).appendChild( row );

				return { item : item, row : row };
			} );

			const visible = function() { return rows.filter( function( entry ) { return entry.row.hidden === false } ) };

			// A group with nothing left in it is not shown, nor is its heading
			const settle = function() {
				sets.forEach( function( one ) { one.box.hidden = Array.from( one.inner.children ).every( function( row ) { return row.hidden === true } ) } );
			};

			const empty = dc.createElement('p');
			empty.className = 'nino-admin-pick-empty';
			empty.setAttribute( 'role', 'status' );
			empty.hidden = true;
			empty.textContent = options.empty ?? '';

			if( searchable === true ) {
				search = dc.createElement('input');
				search.type = 'search';
				search.className = 'nino-admin-input nino-admin-pick-search';
				search.setAttribute( 'aria-label', options.search );
				search.placeholder = options.search;
				search.autocomplete = 'off';

				search.addEventListener( 'input', function() {
					const words = search.value.trim().toLowerCase();
					rows.forEach( function( entry ) {
						entry.row.hidden = words !== '' && ( entry.item.label+ ' '+ ( entry.item.code ?? '' )+ ' '+ ( entry.item.description ?? '' ) ).toLowerCase().indexOf( words ) === -1;
					} );
					settle();
					empty.hidden = visible().length > 0 || empty.textContent === '';
				} );

				root.appendChild( search );
			}

			root.appendChild( list );
			root.appendChild( empty );

			// Up and Down walk the rows that are shown; Down from the search steps into them
			root.addEventListener( 'keydown', function( event ) {

				const shown = visible().map( function( entry ) { return entry.row } );

				if( event.key === 'Enter' && event.target === search ) {
					event.preventDefault();
					if( shown.length > 0 )
						shown[0].click();
					return;
				}

				const at = shown.indexOf( event.target );

				// Tiles: every direction. Left and Right go along the tiles in the order they stand, Up and Down to the tile over or under the one that has the focus -
				// the nearest line, and in it the tile whose middle is nearest - Home and End to the ends; the groups are no stops, the walk goes over them
				if( ( grouped === true || tiled === true ) && at !== -1 && [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].indexOf( event.key ) !== -1 ) {
					event.preventDefault();
					shown[event.key === 'Home' ? 0 : ( event.key === 'End' ? shown.length - 1 : Math.max( 0, Math.min( shown.length - 1, at + ( event.key === 'ArrowRight' ? 1 : -1 ) ) ) )].focus();
					return;
				}

				if( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' )
					return;

				let next = event.key === 'ArrowDown' ? ( at === -1 ? 0 : at + 1 ) : at - 1;

				if( tiled === true && at !== -1 ) {

					const here = shown[at].getBoundingClientRect();
					const middle = function( box ) { return box.left + box.width / 2 };
					const down = event.key === 'ArrowDown';
					const lines = shown.map( function( row, index ) { return { index : index, box : row.getBoundingClientRect() } } ).filter( function( other ) { return down === true ? other.box.top > here.top + 1 : other.box.top < here.top - 1 } );

					if( lines.length === 0 )
						next = down === true ? shown.length : -1;
					else {
						const line = lines.reduce( function( best, other ) { return best === null || ( down === true ? other.box.top < best : other.box.top > best ) ? other.box.top : best }, null );

						next = lines.filter( function( other ) { return other.box.top === line } ).reduce( function( best, other ) { return best === null || Math.abs( middle( other.box ) - middle( here ) ) < Math.abs( middle( best.box ) - middle( here ) ) ? other : best }, null ).index;
					}
				}

				if( next >= 0 && next < shown.length ) {
					event.preventDefault();
					shown[next].focus();
				} else if( next < 0 && search !== null ) {
					event.preventDefault();
					search.focus();
				}
			} );

			return Nino.adminUi.choiceDialog( {
				title			: options.title,
				message		: options.title,
				content		: root,
				size			: options.size ?? 'wide',
				opener		: options.opener,
				focus			: search ?? ( rows.length > 0 ? rows[0].row : undefined ),
				choices		: [ { value : 'cancel', label : options.cancel, kind : 'secondary' } ],
				onOpen		: function( opened ) { handle = opened },
				onChoose	: function( value ) {
					if( value === 'pick' )
						options.onChoose( picked );
					else if( typeof options.onCancel === 'function' )
						options.onCancel();
				},
			} );
		},

		/**
		 *	One tile chosen in each of several groups, then taken over: "the header and the footer of this page", "a look for the day and one for the night".
		 *	A group is a radiogroup - role="radiogroup", named by its heading - of tiles, the tile of pickDialog() ( a picture on a surface of a fixed shape over
		 *	the name, a quiet surface where there is no picture ) with the chosen one marked; a tile is a radio. Where the focus stands is one stop of the group - the
		 *	chosen tile, the first where none is chosen - and Tab goes on to the next group. The arrow keys walk the tiles of the group in every direction and
		 *	choose the one they come to: Left and Right along the tiles in the order they stand, Up and Down to the tile over or under (the nearest line, and in it the
		 *	tile whose middle is nearest), Home and End to the ends; they stay in the group and do not wrap. A press chooses a tile. It chooses nothing else and
		 *	sends nothing: the caller asks value() where it takes the choice over (a dialog's main answer), and onChange tells every change at once.
		 *
		 *	It stands as a field of formDialog() and of the fields of a dialog ( type 'tiles', the value an object group => value ), as the content of choiceDialog() ( el ),
		 *	or free on a page. Each group has a heading with the (?) of its help behind the words and a quiet line under it ( note ); the whole has the same, over the groups, where it
		 *	has a label. The picture of a tile is an address ( image: alt="", loaded when it comes near ), or what the caller draws into its surface with a hook ( draw ) -
		 *	called once for each tile, with the surface - a thing that cannot be drawn in one step ( the page in its frame ) is drawn in later into the very same surface, which
		 *	the handle gives ( faces ). Owns no strings
		 *
		 *	@param		{Object}		options					{ key, label, help, note, groups, draw, onChange, className }
		 *														- key         the name of the field in a form
		 *														- label       the name of the whole: a heading over the groups, with the (?) of its help; without it the whole has no heading, and its help is not shown
		 *														- help        the sentence behind the (?) of the whole, note a quiet line under the label
		 *														- groups      [ { key, label, help, note, value, items } ] - label is the heading of the group, value the value of the tile that is chosen ( none: no tile is ),
		 *																			items [ { value, label, hint, image, draw, disabled } ] - hint is a quiet line of its own under the name, draw( face, item, group ) the hook of this tile
		 *														- draw        the hook of every tile that has none of its own
		 *														- onChange    onChange( groupKey, value, values ) when a tile is chosen by a press or a key
		 *
		 *	@return		{Object}										{ el, controls, faces, value(), get( key ), set( key, value ), focus( [key] ) } - value() is an object group key => value ( null for a group in which none is chosen ),
		 *																			controls the tiles in the order of the groups and the items, faces group key => value => the surface of the tile; set() chooses a tile without calling onChange
		 */
		tileChoice : function( options ) {

			const groups = Array.isArray( options.groups ) === true ? options.groups : [];
			const root = Nino.adminUi.el( 'div', ( options.className || 'nino-admin-field' )+ ' nino-admin-tilechoice' );
			const controls = [];
			const faces = {};
			const sets = {};
			const setters = {};
			const state = {};
			const sentence = function( value ) { return typeof value === 'string' && value !== '' ? value : '' };

			if( options.key )
				root.dataset.key = options.key;

			// The name of the whole, and what stands under it
			if( typeof options.label === 'string' && options.label !== '' ) {

				const title = Nino.adminUi.el( 'div', 'nino-admin-tilechoice-title', options.label );

				root.appendChild( title );

				if( sentence( options.help ) !== '' )
					Nino.adminUi.sectionHelp( title, root, options.help );
			}

			Nino.adminUi._note( root, options.note );

			const list = Nino.adminUi.el( 'div', 'nino-admin-pick-list nino-admin-pick-list--tiles' );
			root.appendChild( list );

			// The tile to go to from one with a key: along the order for Left and Right, to the nearest line for Up and Down where the tiles have a place - none, no move
			const roveTo = function( tiles, at, key ) {

				if( key === 'Home' )
					return 0;

				if( key === 'End' )
					return tiles.length - 1;

				if( key === 'ArrowLeft' )
					return Math.max( 0, at - 1 );

				if( key === 'ArrowRight' )
					return Math.min( tiles.length - 1, at + 1 );

				const boxes = tiles.map( function( tile ) { return typeof tile.getBoundingClientRect === 'function' ? tile.getBoundingClientRect() : null } );

				if( boxes.some( function( box ) { return box === null || typeof box.top !== 'number' } ) === true )
					return key === 'ArrowDown' ? Math.min( tiles.length - 1, at + 1 ) : Math.max( 0, at - 1 );

				const down = key === 'ArrowDown';
				const over = boxes.map( function( box, index ) { return index } ).filter( function( index ) { return down === true ? boxes[index].top > boxes[at].top + 1 : boxes[index].top < boxes[at].top - 1 } );

				if( over.length === 0 )
					return at;

				const line = over.reduce( function( best, index ) { return down === true ? Math.min( best, boxes[index].top ) : Math.max( best, boxes[index].top ) }, down === true ? Infinity : -Infinity );
				const middle = function( index ) { return boxes[index].left + boxes[index].width / 2 };

				return over.filter( function( index ) { return Math.abs( boxes[index].top - line ) < 1 } ).reduce( function( best, index ) { return Math.abs( middle( index ) - middle( at ) ) < Math.abs( middle( best ) - middle( at ) ) ? index : best } );
			};

			groups.forEach( function( group ) {

				const key = String( group.key );
				const box = Nino.adminUi.el( 'div', 'nino-admin-pick-group' );
				const title = Nino.adminUi.el( 'h3', 'nino-admin-pick-group-title', group.label ?? '' );
				const set = Nino.adminUi.el( 'div', 'nino-admin-pick-set' );
				const tiles = [];
				const items = Array.isArray( group.items ) === true ? group.items : [];

				box.dataset.group = key;
				title.id = 'nino-admin-tilechoice-'+ ( ++serial );
				box.appendChild( title );

				// The help of the group behind the words of its heading: the heading keeps its words, the radiogroup is named by them
				if( sentence( group.help ) !== '' ) {
					const help = Nino.adminUi.sectionHelp( title, box, group.help );
					set.setAttribute( 'aria-labelledby', box.getAttribute( 'aria-labelledby' ) );
					box.removeAttribute( 'role' );
					box.removeAttribute( 'aria-labelledby' );
					help.dataset.group = key;
				}
				else
					set.setAttribute( 'aria-labelledby', title.id );

				set.setAttribute( 'role', 'radiogroup' );

				if( sentence( group.note ) !== '' )
					box.appendChild( Nino.adminUi.el( 'small', 'nino-admin-hint nino-admin-tilechoice-note', group.note ) );

				box.appendChild( set );
				list.appendChild( box );

				state[key] = null;
				faces[key] = {};
				sets[key] = tiles;

				items.forEach( function( item ) {

					const tile = Nino.adminUi.el( 'button', 'nino-admin-pick-item nino-admin-pick-item--tile nino-admin-tilechoice-item' );
					const face = Nino.adminUi.el( 'span', 'nino-admin-pick-image' );
					const text = Nino.adminUi.el( 'span', 'nino-admin-pick-text' );
					const value = String( item.value );

					tile.type = 'button';
					tile.setAttribute( 'role', 'radio' );
					tile.setAttribute( 'aria-checked', 'false' );
					tile.tabIndex = -1;
					tile.dataset.value = value;
					tile.dataset.group = key;
					tile.disabled = item.disabled === true;

					if( typeof item.image === 'string' && item.image !== '' ) {
						const picture = dc.createElement('img');
						picture.src = item.image;
						picture.alt = '';
						picture.loading = 'lazy';
						picture.decoding = 'async';
						face.appendChild( picture );
					}

					face.setAttribute( 'aria-hidden', 'true' );
					text.appendChild( Nino.adminUi.el( 'span', 'nino-admin-pick-name', String( item.label ?? value ) ) );

					if( sentence( item.hint ) !== '' )
						text.appendChild( Nino.adminUi.el( 'span', 'nino-admin-pick-line nino-admin-tilechoice-hint', item.hint ) );

					tile.appendChild( face );
					tile.appendChild( text );
					faces[key][value] = face;
					tiles.push( tile );
					controls.push( tile );
					set.appendChild( tile );

					const hook = typeof item.draw === 'function' ? item.draw : options.draw;

					if( typeof hook === 'function' )
						hook( face, item, group );
				} );

				// The tab stop of a group: the chosen tile, or the first one a press can reach where none is chosen
				const stop = function() {

					const chosen = tiles.find( function( tile ) { return tile.getAttribute('aria-checked') === 'true' && tile.disabled !== true } );
					const first = chosen ?? tiles.find( function( tile ) { return tile.disabled !== true } );

					tiles.forEach( function( tile ) { tile.tabIndex = tile === first ? 0 : -1 } );
				};

				const choose = function( tile, told, focus ) {

					tiles.forEach( function( other ) { other.setAttribute( 'aria-checked', other === tile ? 'true' : 'false' ) } );
					state[key] = tile === null ? null : tile.dataset.value;
					stop();

					if( focus === true && tile !== null )
						tile.focus();

					if( told === true && typeof options.onChange === 'function' )
						options.onChange( key, state[key], handle.value() );
				};

				setters[key] = function( value ) {
					choose( tiles.find( function( tile ) { return value !== null && value !== undefined && tile.dataset.value === String( value ) } ) ?? null, false, false );
				};

				tiles.forEach( function( tile, at ) {

					tile.addEventListener( 'click', function() {
						if( state[key] !== tile.dataset.value )
							choose( tile, true, false );
						else
							stop();
					} );

					tile.addEventListener( 'keydown', function( event ) {

						if( [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End' ].indexOf( event.key ) === -1 )
							return;

						event.preventDefault();

						// A tile that cannot be chosen is passed over, in the direction of the key
						const open = tiles.filter( function( other ) { return other.disabled !== true } );
						const to = open.indexOf( tile ) === -1 ? -1 : roveTo( open, open.indexOf( tile ), event.key );

						if( to !== -1 && open[to] !== tile )
							choose( open[to], true, true );
					} );
				} );

				choose( tiles.find( function( tile ) { return group.value !== null && group.value !== undefined && tile.dataset.value === String( group.value ) } ) ?? null, false, false );
			} );

			const handle = {
				el : root,
				controls : controls,
				faces : faces,
				value : function() {
					const values = {};
					groups.forEach( function( group ) { values[String( group.key )] = state[String( group.key )] } );
					return values;
				},
				get : function( key ) { return state[String( key )] ?? null },
				set : function( key, value ) {
					if( setters[String( key )] !== undefined )
						setters[String( key )]( value );
				},
				focus : function( key ) {
					const tiles = key === undefined ? controls : ( sets[String( key )] ?? [] );
					const stop = tiles.find( function( tile ) { return tile.tabIndex === 0 } ) ?? tiles[0];

					if( stop !== undefined )
						stop.focus();
				},
			};

			return handle;
		},

		/**
		 *	The properties of ONE part of a work surface, in a sheet at the right of
		 *	the screen over its whole height, while the surface stays in view
		 *	(a component, a logo, the menu of a frame). Where the screen is
		 *	narrow the sheet is the whole screen. The sections are an accordion()
		 *	with one open; a section is its fields, or a content of its own.
		 *
		 *	Nothing is sent and nothing is asked: a value is the caller's the
		 *	moment it is changed - onChange( key, value ) is called at once, on the
		 *	input of a field - and what was changed is saved with the bar of the
		 *	surface, not here. So the sheet has one answer, Close, which is also
		 *	Escape, and no Cancel. The focus opens on the first field of the open
		 *	section, or on Close where it has none, and goes back to what asked.
		 *
		 *	From 38 rem on the sheet is not modal: no backdrop, no trap for the
		 *	focus, the surface behind stays in use and in view, and the root of the
		 *	workbench carries nino-admin-has-sheet while it is open, which gives the
		 *	surface the room of the sheet on its right (padding-inline-end of the
		 *	sheet's width), so that a preview wraps beside it and not under it. A
		 *	question the surface asks goes over it. Below 38 rem it is modal and
		 *	the whole screen
		 *
		 *	@param		{Object}		options
		 *	@param		{string}		options.title					What the part is and what it is called: "Button Contact"
		 *	@param		{Array}			options.sections			[ { key, label, summary, badge, fields | content } ] - fields as _dialogFields(), content an Element or a function that answers one
		 *	@param		{string}		[options.open]				The key of the section that is open, the first by default
		 *	@param		{Element}		[options.tools]				Drawn at the right of the title (a steps() of widths)
		 *	@param		{string}		options.close					The label of the one answer
		 *	@param		{Function}	[options.onChange]		onChange( key, value, section ) for every change of a field of a section
		 *	@param		{Function}	[options.onClose]			Called when the sheet closed
		 *	@param		{Element}		[options.opener]			See choiceDialog()
		 *
		 *	@return		{Object|boolean}						{ close(), set( key, value, [section] ) } - set() puts a value into the fields of the sheet without calling onChange; false when a dialog is already open
		 */
		propertiesDialog : function( options ) {

			const fields = {};
			const pending = {};
			let handle = null;

			const accordion = Nino.adminUi.accordion( {
				id			: 'nino-admin-properties',
				open		: options.open ?? ( options.sections.length > 0 ? options.sections[0].key : undefined ),
				sections	: options.sections.map( function( section ) {
					return {
						key			: section.key,
						label		: section.label,
						summary	: section.summary,
						badge		: section.badge,
						content	: Array.isArray( section.fields ) === false ? section.content : function() {
							const made = Nino.adminUi._dialogFields( section.fields, function( key, value ) {
								if( typeof options.onChange === 'function' )
									options.onChange( key, value, section.key );
							} );
							fields[section.key] = made;

							// A value the caller set while the section was closed
							Object.keys( pending ).forEach( function( key ) {
								if( made.controls[key] !== undefined )
									made.controls[key].value = pending[key];
							} );

							return made.el;
						},
					};
				} ),
			} );

			// Beside the surface where there is room for both, over it where there is not
			const beside = typeof wn.matchMedia === 'function' && wn.matchMedia( '(min-width: 38.01rem)' ).matches === true;

			const opened = Nino.adminUi.choiceDialog( {
				title			: options.title,
				message		: options.title,
				content		: accordion.el,
				tools			: options.tools,
				size			: 'sheet',
				modeless	: beside,
				opener		: options.opener,
				choices		: [ { value : 'close', label : options.close, kind : 'secondary' } ],
				onOpen		: function( opening ) {
					handle = opening;
					const open = Object.keys( fields )[0];
					const control = open === undefined ? null : Object.values( fields[open].controls )[0];
					if( control !== undefined && control !== null )
						control.focus();
					else
						handle.buttons.close.focus();
				},
				onChoose	: function() {
					if( typeof options.onClose === 'function' )
						options.onClose();
				},
			} );

			if( opened === false || handle === null )
				return opened;

			return {
				close : function() { handle.close( 'close' ) },
				// Puts a value into a field of the sheet - the surface changed it (an undo, a click on another part) - without calling onChange; in every open section that has the key, or the one named
				set : function( key, value, section ) {
					pending[key] = String( value );
					Object.keys( fields ).forEach( function( name ) {
						if( ( section === undefined || section === name ) && fields[name].controls[key] !== undefined )
							fields[name].controls[key].value = String( value );
					} );
				},
			};
		},

		/**
		 *	A manual, drawn: what a screen is for and how it is used, as sections
		 *	of "a handle and what it does" or as running text. The one drawing
		 *	the "?" of every panel opens in a dialog (see manualDialog()), and the
		 *	one the Features panel draws a feature's manual with on its own
		 *	screen.
		 *
		 *	A section is { heading, entries }, an entry { handle, text }. A
		 *	handle is what a developer types and is set as code; a manual written
		 *	for the people who edit with a panel names it in words instead
		 *	(options.named). An entry with no handle says something about the
		 *	whole section and keeps the column the others stand in. A section
		 *	with no entries says so rather than being left out - "no callbacks"
		 *	is an answer, and a reader who does not find the question has to go
		 *	and read the source to learn that the answer was nothing - unless the
		 *	caller has no sentence for it.
		 *
		 *	Everything is set as text: a manual comes out of a manifest or a text
		 *	file, so there is no markup in it to get wrong. The running text,
		 *	which is the form a manifest written before the sections existed has,
		 *	has one mark: a `backticked` span is code, and an unclosed backtick
		 *	is text - shown as it was written instead of turning the rest of the
		 *	paragraph into code.
		 *
		 *	No box of its own and no cap on its height: what holds it decides how
		 *	it gets out of the way. Owns no strings
		 *
		 *	@param		{Array|string}	content					[ { heading, entries } ], or the running text - a blank line starts a paragraph, a single one is where the text wrapped its own line
		 *	@param		{Object}				[options]
		 *	@param		{string}				[options.none]			What a section with no entries says; such a section is left out where there is none
		 *	@param		{Function}			[options.named]			named( handle ) answers the words a handle is shown as, instead of code
		 *	@param		{number}				[options.level]			The heading level of the sections, 3 where it is not given - one under the dialog's own
		 *
		 *	@return		{Element|null}									<div class="nino-admin-manual">; null where there is nothing to show
		 */
		manual : function( content, options ) {

			options = options ?? {};

			const body = dc.createElement('div');
			body.className = 'nino-admin-manual';

			if( typeof content === 'string' )
				Nino.adminUi._manualProse( body, content.trim() );
			else if( Array.isArray( content ) === true )
				content.forEach( function( section ) { Nino.adminUi._manualSection( body, section, options ) } );

			return body.children.length === 0 ? null : body;
		},

		/**
		 *	One section of manual(): its heading, then a line per entry - the
		 *	handle beside what it does. One description list for the whole
		 *	section is what puts every handle of it in one column and every line
		 *	in another: one grid per entry - which is what a row of <p> is -
		 *	aligns each entry with itself and with nothing else, which is not a
		 *	column and is no faster to read than prose
		 *
		 *	@param		{Element}	body
		 *	@param		{Object}	section				{ heading, entries }
		 *	@param		{Object}	options				See manual()
		 *
		 *	@return		void
		 */
		_manualSection : function( body, section, options ) {

			const entries = Array.isArray( section.entries ) === true ? section.entries : [];
			const none		= typeof options.none === 'string' ? options.none : '';

			if( entries.length === 0 && none === '' )
				return;

			if( typeof section.heading === 'string' && section.heading !== '' ) {
				const level = Math.min( 6, Math.max( 2, Math.floor( Number( options.level ) ) || 3 ) );
				const heading = dc.createElement( 'h'+ level );
				heading.className = 'nino-admin-manual-heading';
				heading.textContent = section.heading;
				body.appendChild( heading );
			}

			if( entries.length === 0 ) {
				const empty = dc.createElement('p');
				empty.className = 'nino-admin-manual-none';
				empty.textContent = none;
				body.appendChild( empty );
				return;
			}

			const named = typeof options.named === 'function' ? options.named : null;
			const list	= dc.createElement('dl');
			list.className = 'nino-admin-manual-section'+ ( named === null ? '' : ' nino-admin-manual-section--terms' );

			entries.forEach( function( entry ) {

				const term = dc.createElement('dt');

				if( entry.handle ) {
					if( named !== null )
						term.textContent = named( String( entry.handle ) );
					else {
						const code = dc.createElement('code');
						code.textContent = entry.handle;
						term.appendChild( code );
					}
				}

				// A line with no handle of its own - one that says something about
				// the whole section - keeps the column the other lines are in
				const said = dc.createElement('dd');
				said.textContent = entry.text || '';

				list.appendChild( term );
				list.appendChild( said );
			} );

			body.appendChild( list );
		},

		/**
		 *	The running text of manual(): paragraphs, with `backticked` spans as
		 *	code
		 *
		 *	@param		{Element}	body
		 *	@param		{string}	text
		 *
		 *	@return		void
		 */
		_manualProse : function( body, text ) {

			// A blank line starts a paragraph; a single one is where the text
			// wrapped its own line and means nothing here
			text.split( /\n[ \t]*\n/ ).forEach( function( block ) {

				const flowed = block.trim().replace( /\s*\n\s*/g, ' ' );

				if( flowed === '' )
					return;

				const paragraph = dc.createElement('p');
				const pieces = flowed.split( '`' );

				// The backtick is the only markup there is, and it makes an
				// element rather than a string of html. An unclosed one is prose,
				// not markup
				if( pieces.length % 2 === 0 )
					paragraph.textContent = flowed;
				else
					pieces.forEach( function( piece, index ) {

						if( piece === '' )
							return;

						if( index % 2 === 0 ) {
							paragraph.appendChild( dc.createTextNode( piece ) );
							return;
						}

						const code = dc.createElement('code');
						code.textContent = piece;
						paragraph.appendChild( code );
					} );

				body.appendChild( paragraph );
			} );
		},

		/**
		 *	A manual in a dialog: the panel's name over it, a button to close it
		 *	under it, and what is between them scrolls. The dialog is
		 *	choiceDialog()'s - it opens, closes on Escape, and gives the focus
		 *	back the way every question of the workbench does, with one answer
		 *	and no question. The focus goes into the dialog on opening
		 *
		 *	@param		{string}		title					What the manual is of - the panel's name
		 *	@param		{Element}		content				What manual() answered
		 *	@param		{Element}		[opener]			The button that asked, which gets the focus back
		 *
		 *	@return		{boolean}									false when a question is already open
		 */
		manualDialog : function( title, content, opener ) {

			return Nino.adminUi.choiceDialog( {
				title			: title,
				message		: content.textContent,
				content		: content,
				size			: 'form',
				className	: 'nino-admin-dialog--manual',
				opener		: opener,
				choices		: [ { value : 'close', label : Nino.content.getText('/_admin/common/label/close'), kind : 'secondary' } ],
				onChoose	: function() {},
			} );
		},

		/**
		 *	A label the server sends either as a fill key or as literal text:
		 *	a key ('/…') is looked up in the interface language, anything else
		 *	is shown as it is - the rule every panel applies to a nav label, a
		 *	tile caption, a permission or a field schema
		 *
		 *	@param		{*}				value
		 *
		 *	@return		{string}
		 */
		text : function( value ) {
			value = String( value ?? '' );
			return value.charAt(0) === '/' ? ( Nino.content.getText( value ) || value ) : value;
		},

		/**
		 *	What a text key is made of, read from its form alone - the one
		 *	function the Text panel, its Keys tab and the Images panel share.
		 *	A key is one of
		 *
		 *	  - a key of the grammar, /<namespace>/<category>/<part>/<name>:
		 *	    { kind : 'grammar', namespace, category, part, name }, with
		 *	    { list, id } added when the part has a hyphen - 'category-necessary'
		 *	    is the list 'category' and the entry 'necessary'. Whether the part
		 *	    really is a list is the vocabulary's call (see slugLabel())
		 *	  - the details of a page the system writes after its Element-URI,
		 *	    /_nino/webpage<uri>/<field>: { kind : 'webpage', uri, field }, the
		 *	    uri read from the right, since it may hold slashes and dots
		 *	  - the name of a language, /_nino/locale/<code>/name: { kind : 'locale', code }
		 *	  - anything else - a key a project made up, one of the workbench:
		 *	    { kind : 'free' }
		 *
		 *	@param		{string}	key
		 *
		 *	@return		{Object}
		 */
		describeKey : function( key ) {

			key = String( key ?? '' );

			let match = new RegExp( '^/_nino/webpage(/.+)/(name|title|description|uri)$' ).exec( key );
			if( match !== null )
				return { kind : 'webpage', uri : match[1], field : match[2] };

			match = /^\/_nino\/locale\/([^\/]+)\/name$/.exec( key );
			if( match !== null )
				return { kind : 'locale', code : match[1] };

			match = /^\/(template|project|feature|module)\/([a-z0-9]+(?:-[a-z0-9]+)*)\/([a-z0-9]+(?:-[a-z0-9]+)*)\/([a-z0-9]+(?:-[a-z0-9]+)*)$/.exec( key );
			if( match === null )
				return { kind : 'free' };

			const described = { kind : 'grammar', namespace : match[1], category : match[2], part : match[3], name : match[4] };
			const dash = match[3].indexOf('-');

			if( dash > 0 ) {
				described.list = match[3].slice( 0, dash );
				described.id = match[3].slice( dash + 1 );
			}

			return described;
		},

		/**
		 *	A slug the way a person reads it when no word of the vocabulary
		 *	names it: the first letter in capitals, hyphens and underscores as
		 *	spaces
		 *
		 *	@param		{string}	slug
		 *
		 *	@return		{string}
		 */
		humanize : function( slug ) {
			slug = String( slug ?? '' ).replace( /[-_]+/g, ' ' ).trim();
			return slug.charAt(0).toUpperCase() + slug.slice(1);
		},

		/**
		 *	The name of one segment of a text key, in the language of the
		 *	interface: a word of the closed vocabulary (/_admin/common/word/<slug>,
		 *	one per slug, no aliases), put together from words where the slug
		 *	is a compound, humanized where it is not. In this order:
		 *
		 *	  1. the slug is a word: 'intro' is "Einleitung"
		 *	  2. it is <word>-<number>: 'item-3' is "Eintrag 3"
		 *	  3. it is split at every hyphen from the left, and where what is
		 *	     right of the hyphen is a word, the two read "<left> · <word>" -
		 *	     the left part looked up as well, humanized if it is no word:
		 *	     'unit-day' is "Einheit · Tag", 'cta-label' "Cta · Beschriftung",
		 *	     'hero-image-alt' "Hero image · Alternativtext"
		 *	  4. otherwise it is humanized: 'tagline' is "Tagline"
		 *
		 *	@param		{string}	slug
		 *
		 *	@return		{string}
		 */
		slugLabel : function( slug ) {

			slug = String( slug ?? '' );

			const word = function( candidate ) { return Nino.content.getText( '/_admin/common/word/'+ candidate ) };

			if( word( slug ) !== '' )
				return word( slug );

			const numbered = /^(.+)-(\d+)$/.exec( slug );
			if( numbered !== null )
				return Nino.adminUi.slugLabel( numbered[1] )+ ' '+ numbered[2];

			for( let dash = slug.indexOf('-'); dash !== -1; dash = slug.indexOf( '-', dash + 1 ) ) {
				const right = word( slug.slice( dash + 1 ) );
				if( dash > 0 && right !== '' ) {
					const left = slug.slice( 0, dash );
					return ( word( left ) || Nino.adminUi.humanize( left ) )+ ' · '+ right;
				}
			}

			return Nino.adminUi.humanize( slug );
		},

		/**
		 *	Whether a slug is covered by the vocabulary: the words the interface
		 *	names a part of a key with, /_admin/common/word/<slug>. It is one,
		 *	or <word>-<number> (a list's entry: 'item-3'), or <word>-<word> -
		 *	the slug falls apart at a hyphen into two words, as 'unit-day' does.
		 *	That is the rule of every key the kernel ships (admin-text-smoke
		 *	holds them to it), and what slugLabel() gives a slug that is none of
		 *	it is only a guess at how to read it: 'cta' is shown as "Cta"
		 *
		 *	@param		{string}	slug
		 *
		 *	@return		{boolean}
		 */
		isWord : function( slug ) {

			slug = String( slug ?? '' );

			const word = function( candidate ) { return Nino.content.getText( '/_admin/common/word/'+ candidate ) !== '' };

			if( word( slug ) === true )
				return true;

			const numbered = /^(.+)-\d+$/.exec( slug );
			if( numbered !== null )
				return Nino.adminUi.isWord( numbered[1] );

			for( let dash = slug.indexOf('-'); dash !== -1; dash = slug.indexOf( '-', dash + 1 ) )
				if( dash > 0 && word( slug.slice( 0, dash ) ) === true && word( slug.slice( dash + 1 ) ) === true )
					return true;

			return false;
		},

		/**
		 *	Show a frame at a layout width it does not have room for, scaled to
		 *	the room it does have.
		 *
		 *	A preview panel is half a tool pane wide - around 900px - and a page
		 *	designed for a desktop browser never meets its own layout limits
		 *	there. Nino's widest content ceiling is 160rem, so every setting for
		 *	it rendered identically: the frame was narrower than the narrowest of
		 *	them, and the difference the operator was choosing between could not
		 *	reach the glass.
		 *
		 *	So the frame is given the width a desktop browser has and then scaled
		 *	down as a whole. Everything inside keeps its proportions exactly -
		 *	which is what separates this from shrinking the root font, where the
		 *	type would move against everything measured in px and the preview
		 *	would start lying about the one thing it was just taught to tell the
		 *	truth about.
		 *
		 *	The height is solved rather than set: at scale f, filling a box h tall
		 *	needs h/f of document, so the page gets exactly as much vertical room
		 *	as the panel can show and no more.
		 *
		 *	@param	{Element}		frame			The iframe, absolutely placed in its port
		 *	@param	{Element}		port			The box it is shown inside
		 *	@param	{number}		width			The layout width to render at
		 *	@param	{number}		[height]	The layout height to render at. Given, the
		 *														whole page is fitted into the port; omitted, the
		 *														page is given as much height as the port shows
		 *
		 *	@return	{Function}						fit(), for calling after a layout change
		 */
		scaleFrame : function( frame, port, width, height ) {

			const fit = function() {

				const room = port.getBoundingClientRect();

				if( room.width < 1 || room.height < 1 )
					return;

				/*	Without a height the page is given the panel's, solved back
					through the scale: as much document as the port can show and
					no more. That fits by width alone, so a page taller than the
					port scrolls - and a page whose own lengths are viewport
					relative gets a different viewport on every window size.

					With one, both dimensions are fixed and the scale is
					whichever of the two fits: the whole page is on screen at a
					stable layout, and what changes with the panel is how small
					it is drawn rather than how much of it there is.	*/
				const solved = height > 0 ? height : Math.round( room.height / Math.min( 1, room.width / width ) );

				// Never magnified: on a panel wider than a desktop browser the page
				// should be shown at its own size, not blown up past it
				const factor = Math.min( 1, room.width / width, room.height / solved );

				frame.style.width = width+ 'px';
				frame.style.height = solved+ 'px';
				frame.style.transform = 'scale('+ factor+ ')';

				/*	A page is taller than it is wide and a preview panel is the
					other way round, so fitting the whole page leaves room at the
					sides. Centred rather than left in the corner: the empty half
					of the port then reads as a margin instead of as a page that
					failed to fill it.	*/
				frame.style.left = height > 0 ? Math.round( ( room.width - width * factor ) / 2 )+ 'px' : '0';
				frame.style.top = height > 0 ? Math.round( ( room.height - solved * factor ) / 2 )+ 'px' : '0';
			};

			fit();

			/*	The panel resizes with the window, with the rail, and with the tab
				the operator is on - watching the box is the only one of those the
				caller does not have to remember to report.

				One observer per port, not one per call. A caller re-fits the same
				box whenever the thing inside it changes - Design rebuilds the
				scaler on every width change and every preview it loads - and each
				of those used to leave the previous observer attached and firing.
				After n calls one drag of the window edge ran fit() n times, and
				nothing ever took one away. The port carries its observer, so a
				second call replaces the first rather than joining it.	*/
			if( typeof wn.ResizeObserver === 'function' ) {
				if( port._ninoScaleObserver )
					port._ninoScaleObserver.disconnect();
				port._ninoScaleObserver = new wn.ResizeObserver( fit );
				port._ninoScaleObserver.observe( port );
			}

			return fit;
		},

		/**
		 *	An element with a class and a text, in one call: the tag, the class
		 *	set only when there is one, the text set as text - never as markup -
		 *	only when there is one. Owns no strings
		 *
		 *	@param		{string}		tag
		 *	@param		{string}		[className]
		 *	@param		{string}		[text]
		 *
		 *	@return		{Element}
		 */
		el : function( tag, className, text ) {

			const el = dc.createElement( tag );

			if( typeof className === 'string' && className !== '' )
				el.className = className;

			if( text !== undefined )
				el.textContent = text;

			return el;
		},

		/**
		 *	An icon of the Lucide set (see ICONS): an inline <svg> that draws in the
		 *	colour of the text it stands in (currentColor) and is as high as it is
		 *	wide. Without a label it is decoration - aria-hidden, for the word beside
		 *	it - and with one it is an image with that name. A name the set does not
		 *	have is no error: the answer is the same <svg> with nothing drawn in it,
		 *	and the console is told once per name. Owns no strings
		 *
		 *	@param		{string}		name							A name of ICONS, as Lucide spells it ('trash-2')
		 *	@param		{Object}		[options]					{ size, label } - size in pixels (16), label the name of an icon that stands alone
		 *
		 *	@return		{Element}
		 */
		icon : function( name, options ) {

			options = options || {};

			const svg = dc.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
			const size = Number.isFinite( options.size ) === true && options.size > 0 ? options.size : 16;

			svg.setAttribute( 'class', 'nino-admin-icon' );
			svg.setAttribute( 'width', String( size ) );
			svg.setAttribute( 'height', String( size ) );
			svg.setAttribute( 'viewBox', '0 0 24 24' );
			svg.setAttribute( 'fill', 'none' );
			svg.setAttribute( 'stroke', 'currentColor' );
			svg.setAttribute( 'stroke-width', '2' );
			svg.setAttribute( 'stroke-linecap', 'round' );
			svg.setAttribute( 'stroke-linejoin', 'round' );
			svg.setAttribute( 'focusable', 'false' );

			const parts = typeof name === 'string' && Object.hasOwn( ICONS, name ) === true ? ICONS[name] : null;

			if( parts === null ) {

				if( UNKNOWN_ICONS.has( name ) === false ) {
					UNKNOWN_ICONS.add( name );
					console.warn( 'Nino.adminUi.icon(): no icon named "'+ String( name )+ '"' );
				}

				svg.setAttribute( 'aria-hidden', 'true' );
				return svg;
			}

			for( const part of parts ) {

				const shape = dc.createElementNS( 'http://www.w3.org/2000/svg', typeof part === 'string' ? 'path' : part[0] );

				if( typeof part === 'string' )
					shape.setAttribute( 'd', part );
				else
					for( const attribute of Object.keys( part[1] ) )
						shape.setAttribute( attribute, part[1][attribute] );

				svg.appendChild( shape );
			}

			if( typeof options.label === 'string' && options.label !== '' ) {
				svg.setAttribute( 'role', 'img' );
				svg.setAttribute( 'aria-label', options.label );
			} else
				svg.setAttribute( 'aria-hidden', 'true' );

			return svg;
		},

		/**
		 *	A button: a real button, which does not submit unless it is asked to,
		 *	showing a word, a glyph or an icon before the word. A glyph is no name -
		 *	a screen reader says "button ↑" - so a button whose face is one is given
		 *	its name, which becomes its title and its aria-label both. The look is
		 *	its kind (primary, secondary, danger, link; none is how a plain button
		 *	looks), its size, and then the caller's class (nino-admin-btn--small...).
		 *	An unknown kind throws a TypeError. Owns no strings: the face and the
		 *	name are the caller's
		 *
		 *	@param		{string}		text						What the button shows
		 *	@param		{Function}	[onClick]				Called on a click
		 *	@param		{Object}		[options]				{ kind, small, icon, className, name, disabled, id, type } - type 'submit' for a button that submits, icon the name of an icon() drawn before the text
		 *
		 *	@return		{Element}
		 */
		button : function( text, onClick, options ) {

			options = options || {};

			if( options.kind !== undefined && Object.hasOwn( BUTTON_KINDS, options.kind ) === false )
				throw new TypeError( 'Nino.adminUi.button(): "'+ String( options.kind )+ '" is no kind of button' );

			const button = dc.createElement('button');
			button.type = options.type === 'submit' ? 'submit' : 'button';

			const classes = [];

			if( options.kind !== undefined )
				classes.push( BUTTON_KINDS[options.kind] );

			if( options.small === true )
				classes.push( 'nino-admin-btn--small' );

			if( options.icon !== undefined )
				classes.push( 'nino-admin-btn--lead-icon' );

			if( options.className )
				classes.push( options.className );

			if( classes.length > 0 )
				button.className = classes.join(' ');

			if( options.icon !== undefined ) {
				button.appendChild( Nino.adminUi.icon( options.icon ) );

				if( text !== '' )
					button.appendChild( Nino.adminUi.el( 'span', '', text ) );
			} else
				button.textContent = text;

			if( options.name ) {
				button.title = options.name;
				button.setAttribute( 'aria-label', options.name );
			}

			button.disabled = options.disabled === true;

			if( options.id )
				button.id = options.id;

			if( typeof onClick === 'function' )
				button.addEventListener( 'click', onClick );

			return button;
		},

		/**
		 *	A button that shows an icon and no word: the row's up, down and remove,
		 *	the toolbar's reload. An icon is no name, so the name is not optional -
		 *	it becomes the title and the aria-label - and a call without one throws
		 *	a TypeError. 28 pixels square, 24 when it is small
		 *
		 *	@param		{string}		icon						The name of an icon() to show
		 *	@param		{string}		name						What the button does, as a person reads it
		 *	@param		{Function}	[onClick]				Called on a click
		 *	@param		{Object}		[options]				{ danger, small, className, disabled, id }
		 *
		 *	@return		{Element}
		 */
		iconButton : function( icon, name, onClick, options ) {

			options = options || {};

			if( typeof name !== 'string' || name.trim() === '' )
				throw new TypeError( 'Nino.adminUi.iconButton(): a button with an icon alone needs a name' );

			const classes = [ 'nino-admin-btn-icon' ];

			if( options.danger === true )
				classes.push( 'nino-admin-btn-danger' );

			if( options.small === true )
				classes.push( 'nino-admin-btn-icon--small' );

			if( options.className )
				classes.push( options.className );

			const button = Nino.adminUi.button( '', onClick, { className : classes.join(' '), name : name, disabled : options.disabled, id : options.id } );
			button.appendChild( Nino.adminUi.icon( icon ) );

			return button;
		},

		/**
		 *	A row of buttons where exactly one is active - tabs over a pair of
		 *	panels, a light/dark switch over a preview, anything that is one
		 *	choice made by pressing rather than by opening a list.
		 *
		 *	Owns the state and the aria, not the look: the caller passes elements
		 *	it has already styled, and gets back the one function it needs. Which
		 *	is the whole reason this is here - four copies of "toggle is-active on
		 *	one of these and set aria on all of them" is how one of them ends up
		 *	without the aria.
		 *
		 *	@param	{Object}		buttons		key -> element
		 *	@param	{string}		active		The key to start on
		 *	@param	{Function}	onSelect	Called with the newly picked key
		 *	@param	{string}		flag			The aria attribute the row uses:
		 *																'aria-selected' for tabs, 'aria-pressed'
		 *																for a switch. Defaults to pressed.
		 *
		 *	@return	{Function}						select( key ), for setting it from outside
		 */
		buttonRow : function( buttons, active, onSelect, flag ) {

			const attribute = flag || 'aria-pressed';
			const keys 			= Object.keys( buttons ).filter( function( key ) {
				return buttons[key] !== null && typeof buttons[key] !== 'undefined';
			} );

			const paint = function( key ) {
				keys.forEach( function( candidate ) {

					const button = buttons[candidate];
					const on 		= candidate === key;

					button.classList.toggle( 'is-active', on );
					button.setAttribute( attribute, on === true ? 'true' : 'false' );

					/*	A tab strip is one tab stop, not one per tab: the pattern
						reaches a strip with Tab and walks it with the arrows, and a
						row that leaves every tab tabbable makes the operator press
						Tab once per tab to get past a strip they did not want. The
						switch does not do this - aria-pressed buttons are ordinary
						buttons and each is its own stop	*/
					if( attribute === 'aria-selected' )
						button.tabIndex = on === true ? 0 : -1;
				} );
			};

			keys.forEach( function( key ) {
				buttons[key].addEventListener( 'click', function() {
					paint( key );
					if( typeof onSelect === 'function' )
						onSelect( key );
				} );
			} );

			// Only a tablist gets the arrows; a row of aria-pressed switches is
			// a row of ordinary buttons and the browser already walks it
			if( attribute === 'aria-selected' )
				Nino.adminUi.tabKeys( keys.map( function( key ) { return buttons[key] } ), function( at ) {
					paint( keys[at] );
					if( typeof onSelect === 'function' )
						onSelect( keys[at] );
				} );

			paint( active );

			return paint;
		},

		/**
		 *	The keyboard half of a tab strip: Left/Right walk it, Home and End
		 *	jump to its ends, and exactly one tab is in the page's tab order at
		 *	a time.
		 *
		 *	Here rather than in each strip because the workbench has three of
		 *	them - the pane strip the shell renders (see \Nino\Admin\Panels::$html),
		 *	and the Features panel's two - and every one of them was a row of
		 *	buttons a pointer could operate and an arrow key could not. A tab
		 *	is announced as a tab, so it is offered the keys a tab answers to.
		 *
		 *	@param	{Array}			tabs			The strip's buttons, in the order they are drawn
		 *	@param	{Function}	onMove		Called with the index the operator moved to
		 *
		 *	@return	{Function}						roving( index ), for pointing the one tab stop
		 *													from outside
		 */
		tabKeys : function( tabs, onMove ) {

			const strip = ( tabs || [] ).filter( function( tab ) {
				return tab !== null && typeof tab !== 'undefined';
			} );

			const roving = function( index ) {
				strip.forEach( function( tab, at ) { tab.tabIndex = ( at === index ) ? 0 : -1 } );
			};

			strip.forEach( function( tab, at ) {

				tab.addEventListener( 'keydown', function( ev ) {

					if( [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].indexOf( ev.key ) === -1 )
						return;

					// The arrows scroll the page otherwise, which is the one thing
					// the operator did not ask for by pressing them on a tab
					ev.preventDefault();

					let next = at;
					if( ev.key === 'ArrowLeft' )	next = ( at - 1 + strip.length ) % strip.length;
					if( ev.key === 'ArrowRight' )	next = ( at + 1 ) % strip.length;
					if( ev.key === 'Home' )				next = 0;
					if( ev.key === 'End' )				next = strip.length - 1;

					roving( next );

					if( typeof strip[next].focus === 'function' )
						strip[next].focus();

					if( typeof onMove === 'function' )
						onMove( next );
				} );
			} );

			return roving;
		},

		// What tabBadge() was told, by tab: { text, state }. The record of the chips,
		// whether the button that carries one is in the page or not
		_tabBadges : new Map(),

		/**
		 *	A short word as a chip on the button of a tab in the pane's strip - how
		 *	many of something wait in that tab, what state it is in. The button is
		 *	the registry's (id admin-tabbutton-<tab>, see \Nino\Admin\Panels::$html),
		 *	and the kernel has no place in its label for a word of a panel's, so the
		 *	panel's script puts one there: the workbench's own pill, .nino-admin-badge,
		 *	after the label. One chip at most: a second call replaces the first, and an
		 *	empty text - or null - takes it away. Asked with the tab alone, it answers
		 *	the word it was given, '' where there is none.
		 *
		 *	The shell does not draw its strip again - a move between panels and tabs
		 *	changes the classes and attributes of the buttons, never the buttons - so a
		 *	chip stays where it was put. The word is recorded all the same, by tab, and
		 *	is answered from the record: also for a tab that has no button in the page
		 *	(the account lacks its permission), where nothing is drawn. A chip of a
		 *	script's own on the same button - a class of its own beside nino-admin-badge -
		 *	is left as it is
		 *
		 *	@param		{string}				tab							The uri of the tab, a slug
		 *	@param		{string|number}	[text]					The word; empty or null removes the chip; left out, the call answers
		 *	@param		{string}				[state]					ok, warn, error or muted, the colours of .nino-admin-badge; none, or any other, draws it in the colour of the label
		 *
		 *	@return		{string|undefined}									The word, for a call with the tab alone
		 */
		tabBadge : function( tab, text, state ) {

			const badges = Nino.adminUi._tabBadges;

			if( arguments.length < 2 )
				return badges.has( tab ) === true ? badges.get( tab ).text : '';

			if( typeof tab !== 'string' || /^[a-z][a-z0-9-]*$/.test( tab ) === false )
				return;

			const word = text === null || text === undefined ? '' : String( text ).trim();

			if( word === '' )
				badges.delete( tab );
			else
				badges.set( tab, { text : word, state : state } );

			const button = dc.getElementById( 'admin-tabbutton-'+ tab );

			if( button === null )
				return;

			const before = button.querySelector(':scope > .nino-admin-tab-badge');

			if( before !== null )
				before.remove();

			if( word === '' )
				return;

			const chip = Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-tab-badge', word );

			if( [ 'ok', 'warn', 'error', 'muted' ].indexOf( state ) !== -1 )
				chip.setAttribute( 'data-state', state );

			button.appendChild( chip );
		},

		/**
		 *	One setting picked from a fixed list, as a labelled select.
		 *
		 *	Owns the control and its options, not the wrapper: the caller passes
		 *	the field class its own panel already styles, so a generated field
		 *	sits beside a hand-written one without either having to know about
		 *	the other. Owns no strings either, same rule as table() and
		 *	switchField() - the label, the note and every option's text come
		 *	from the caller.
		 *
		 *	What explains the setting is its help (help; hint is the same thing),
		 *	behind the (?) at its name - see fieldHelp(). What has to stay in view
		 *	is its note, a quiet line under the select.
		 *
		 *	@param	{Object}	options		{ key, id, className, label, help, hint, note, options, value, onChange }
		 *
		 *	@return	{Element}						The label; its select carries data-key, and is
		 *													reachable as the label's own .control - so a caller
		 *													writing a value back into it needs no id of its own
		 */
		selectField : function( options ) {

			const field = dc.createElement('label');
			field.className = options.className || 'nino-admin-field';

			const name = dc.createElement('span');
			name.textContent = options.label || options.key;

			field.appendChild( name );

			const select = dc.createElement('select');
			select.className = 'nino-admin-input';
			select.setAttribute( 'data-key', options.key );

			if( options.id )
				select.id = options.id;

			( options.options || [] ).forEach( function( option ) {
				const el = dc.createElement('option');
				el.value = String( option.value );
				el.textContent = option.label;
				select.appendChild( el );
			} );

			select.value = String( options.value );

			select.addEventListener( 'change', function() {
				if( typeof options.onChange === 'function' )
					options.onChange( select.value );
			} );

			field.appendChild( select );

			Nino.adminUi._tools( name, select, options, options.label || options.key );
			Nino.adminUi._note( field, options.note );

			return field;
		},

		/**
		 *	The note of a field: what has to stay in view at the control - a counter, a limit, a conversion,
		 *	the result of a check - as a quiet line after it. It is no help and is never put behind the (?)
		 *
		 *	@param		{Element}				field
		 *	@param		{string|undefined}	note
		 *
		 *	@return		{Element|null}
		 */
		_note : function( field, note ) {

			if( typeof note !== 'string' || note === '' )
				return null;

			const line = dc.createElement('small');
			line.className = 'nino-admin-hint nino-admin-field-note';
			line.textContent = note;
			field.appendChild( line );

			return line;
		},

		/**
		 *	One boolean setting as the design system's switch. The written
		 *	on/off state next to the knob is the point of it: knob position
		 *	alone is the one signal a low-vision reader loses, so the words
		 *	are part of the component rather than a caller's decision.
		 *
		 *	Owns no strings, same rule as table(): every word the workbench
		 *	shows is a fill, so both the label and the two state words come
		 *	from the caller.
		 *
		 *	@param	{Object}	options		{ key, checked, label, help, hint, note, on, off } - help (hint is the same) stands behind the (?) at the name, note stays in view under it
		 *
		 *	@return	{Element}						The label; its input carries data-key
		 */
		switchField : function( options ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-switch';

			const input = dc.createElement('input');
			input.type = 'checkbox';
			input.checked = options.checked === true;
			if( options.key )
				input.dataset.key = options.key;
			label.appendChild( input );

			const track = dc.createElement('span');
			track.className = 'nino-admin-switch-track';
			label.appendChild( track );

			const copy = dc.createElement('span');
			copy.className = 'nino-admin-switch-copy';
			// The name and its (?) in a line of their own, the note under it
			const name = dc.createElement('span');
			name.appendChild( dc.createTextNode( options.label ) );
			copy.appendChild( name );

			if( options.note ) {
				const note = dc.createElement('small');
				note.textContent = options.note;
				copy.appendChild( note );
			}
			label.appendChild( copy );

			Nino.adminUi._tools( name, input, options, options.label );

			const on 		= options.on ?? ( Nino.content.getText('/_admin/common/label/on') || 'On' );
			const off 	= options.off ?? ( Nino.content.getText('/_admin/common/label/off') || 'Off' );
			const state = dc.createElement('span');
			state.className = 'nino-admin-switch-state';
			state.textContent = input.checked ? on : off;
			input.addEventListener( 'change', function() { state.textContent = input.checked ? on : off } );
			label.appendChild( state );

			return label;
		},

		/**
		 *	A whole number in bounds, as a number input the browser limits the
		 *	same way the backend rejects - so an out-of-range value is caught
		 *	before the request, and still caught by the backend if it is not.
		 *	Owns no strings, same rule as switchField(): the label, the hint
		 *	and the unit come from the caller. A duration in seconds is
		 *	unreadable as a number - 3600 says nothing, "1 h" does - so a
		 *	field whose unit is seconds gets a reading aid, recomputed on
		 *	input so the two never disagree. The Config panel, the Users
		 *	panel's login-protection tab and a feature's settings in the
		 *	Features panel render their numbers with it.
		 *
		 *	@param	{Object}	options		{ key, value, min, max, unit, label, help, hint, note } - help (hint is the same) stands behind the (?) at the name, note stays in view under the input
		 *
		 *	@return	{Element}						The label; its input carries data-key
		 */
		numberField : function( options ) {

			const label = dc.createElement('label');
			label.className = 'nino-admin-field';

			// Label, hint and unit may arrive as fill keys - a schema the
			// backend declares once and every language reads (see text())
			const name = dc.createElement('span');
			name.textContent = options.unit
				? Nino.adminUi.text( options.label )+ ' ('+ Nino.adminUi.text( '/_admin/common/unit/'+ options.unit )+ ')'
				: Nino.adminUi.text( options.label );
			label.appendChild( name );

			const input = dc.createElement('input');
			input.type = 'number';
			input.step = '1';
			input.value = options.value;
			if( options.key )
				input.dataset.key = options.key;
			if( options.min !== undefined ) input.min = options.min;
			if( options.max !== undefined ) input.max = options.max;
			label.appendChild( input );

			Nino.adminUi._tools( name, input, { help : options.help !== undefined || options.hint !== undefined ? Nino.adminUi.text( options.help ?? options.hint ) : undefined }, name.textContent );
			Nino.adminUi._note( label, options.note === undefined ? undefined : Nino.adminUi.text( options.note ) );

			if( options.unit === 'seconds' ) {
				const human = dc.createElement('small');
				human.className = 'nino-admin-hint';
				const setHuman = function() { human.textContent = Nino.adminUi.humanDuration( input.value ) };
				input.addEventListener( 'input', setHuman );
				setHuman();
				label.appendChild( human );
			}

			return label;
		},

		/**
		 *	"3600" -> "= 1 h". Rounded, not exact: this is a reading aid next
		 *	to the real value, not a second source of truth
		 *
		 *	@param	{string|number}	seconds
		 *
		 *	@return	{string}
		 */
		humanDuration : function( seconds ) {

			const total = parseInt( seconds, 10 );
			if( isNaN( total ) === true || total <= 0 )
				return '';

			if( total < 60 )
				return '= '+ total+ ' s';

			if( total < 3600 )
				return '= '+ Math.round( total / 60 )+ ' min';

			if( total < 86400 )
				return '= '+ ( Math.round( total / 360 ) / 10 )+ ' h';

			return '= '+ ( Math.round( total / 8640 ) / 10 )+ ' d';
		},

		/**
		 *	One line of text as a labelled input, beside selectField(),
		 *	switchField() and numberField() and by the select's rule: the words
		 *	are used as they are, so a caller that has a fill key runs it through
		 *	text() itself. A required field is marked the way the workbench marks
		 *	every one: the asterisk behind its name, drawn aria-hidden, and
		 *	aria-required on the control. Owns no strings, the asterisk apart
		 *
		 *	@param	{Object}	options		{ key, type, value, placeholder, label, help, hint, note, fills, required, maxLength, minLength, autocomplete, id, onInput, className }
		 *													- help       what the value does, behind the (?) at the name (hint is the same thing)
		 *													- note       what has to stay in view under the input
		 *													- fills      the textfills the field takes, see textareaField(); drawn only where the caller asks for it
		 *													- type       the input's type, text by default
		 *													- key        written onto the input as data-key
		 *													- onInput    called with the input's value on every input
		 *													- className  the label's class instead of nino-admin-field
		 *
		 *	@return	{Element}						The label; the control is its input
		 */
		textField : function( options ) {

			const field = dc.createElement('label');
			field.className = options.className || 'nino-admin-field';

			const name = dc.createElement('span');
			name.appendChild( dc.createTextNode( options.label ?? '' ) );

			if( options.required === true ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				name.appendChild( star );
			}

			field.appendChild( name );

			const input = dc.createElement('input');
			input.className = 'nino-admin-input';
			input.type = options.type || 'text';

			if( options.key )
				input.dataset.key = options.key;

			if( options.value !== undefined && options.value !== null )
				input.value = options.value;

			if( options.placeholder )
				input.placeholder = options.placeholder;

			if( typeof options.maxLength === 'number' )
				input.maxLength = options.maxLength;

			if( typeof options.minLength === 'number' )
				input.minLength = options.minLength;

			if( options.autocomplete )
				input.autocomplete = options.autocomplete;

			if( options.id )
				input.id = options.id;

			if( options.required === true ) {
				input.required = true;
				input.setAttribute( 'aria-required', 'true' );
			}

			input.addEventListener( 'input', function() {
				if( typeof options.onInput === 'function' )
					options.onInput( input.value );
			} );

			field.appendChild( input );

			Nino.adminUi._tools( name, input, options, options.label ?? '' );
			Nino.adminUi._note( field, options.note );

			return field;
		},

		/**
		 *	Several lines of text as a labelled textarea, beside textField() and by its rule: the words are used as they
		 *	are, a required field has its star and aria-required. The field a person writes a text in and the one where
		 *	the textfills decide the work - so it is the one that takes the option fills: a "[ ]" at the name, in the
		 *	size and the place of the (?), which opens the textfills the field takes (fillsDialog()). It is drawn only
		 *	where the caller says that the account may edit the field, never for a field that is locked. Owns no
		 *	strings, the asterisk apart
		 *
		 *	@param	{Object}	options		{ key, value, rows, placeholder, label, help, hint, note, fills, required, maxLength, spellcheck, id, onInput, className }
		 *													- help       what the value does, behind the (?) at the name (hint is the same thing)
		 *													- note       what has to stay in view under the textarea
		 *													- fills      { editable, title, items | load } - editable true where the account may edit the field, else no icon; items [ { name, notation, value } ], or load( done ) answering them to done( list ) - null where they could not be had; title the dialog's, "Textfills for <label>" by default
		 *													- rows       4 by default
		 *													- spellcheck false by default: this is the field of lists and of technical values
		 *
		 *	@return	{Element}						The label; the control is its textarea
		 */
		textareaField : function( options ) {

			const field = dc.createElement('label');
			field.className = options.className || 'nino-admin-field';

			const name = dc.createElement('span');
			name.appendChild( dc.createTextNode( options.label ?? '' ) );

			if( options.required === true ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				name.appendChild( star );
			}

			field.appendChild( name );

			const area = dc.createElement('textarea');
			area.className = 'nino-admin-input';
			area.rows = Number.isInteger( options.rows ) === true && options.rows > 0 ? options.rows : 4;
			area.spellcheck = options.spellcheck === true;

			if( options.key )
				area.dataset.key = options.key;

			if( options.value !== undefined && options.value !== null )
				area.value = options.value;

			if( options.placeholder )
				area.placeholder = options.placeholder;

			if( typeof options.maxLength === 'number' )
				area.maxLength = options.maxLength;

			if( options.id )
				area.id = options.id;

			if( options.required === true ) {
				area.required = true;
				area.setAttribute( 'aria-required', 'true' );
			}

			area.addEventListener( 'input', function() {
				if( typeof options.onInput === 'function' )
					options.onInput( area.value );
			} );

			field.appendChild( area );

			Nino.adminUi._tools( name, area, options, options.label ?? '' );
			Nino.adminUi._note( field, options.note );

			return field;
		},

		/**
		 *	The name of a field that is no <label>: a group (the rows of the languages, a list of checkboxes) or a
		 *	field with a button in it (a file). The words - with the star of a required field - are the first child of the
		 *	field and name the group (aria-labelledby), the (?) and the "[ ]" stand behind them as at any field
		 *
		 *	@param		{Element}		field			The field's own element
		 *	@param		{Element}		group			What the words name: the element a screen reader enters
		 *	@param		{Object}		options		The builder's options: label, required, help, fills
		 *
		 *	@return		{Element}									The element that holds the name
		 */
		_groupName : function( field, group, options ) {

			const label = String( options.label ?? options.key ?? '' );
			const name = dc.createElement('span');
			name.appendChild( dc.createTextNode( label ) );

			if( options.required === true ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				name.appendChild( star );
				group.setAttribute( 'aria-required', 'true' );
			}

			field.appendChild( name );

			Nino.adminUi._tools( name, group, options, label );

			if( group.hasAttribute('aria-labelledby') === false ) {
				name.id = 'nino-admin-name-'+ ( ++serial );
				group.setAttribute( 'aria-labelledby', name.id );
			}

			return name;
		},

		/**
		 *	One tick with its name, the label at the left of the box as at every field: a consent, a "this is
		 *	the main one". For a yes or no that is a setting there is the switch (switchField()); for several of
		 *	a set there is checklist(). A required tick has its star and aria-required. Owns no strings
		 *
		 *	@param		{Object}		options		{ key, id, label, checked, disabled, required, help, hint, note, onChange, className }
		 *														- help       what the tick does, behind the (?) at the name (hint is the same thing)
		 *														- note       what has to stay in view under the box
		 *														- onChange   called with true or false on every change
		 *														- className  the label's class instead of nino-admin-field
		 *
		 *	@return		{Element}									The label; its input is the label's own .control
		 */
		checkField : function( options ) {

			const field = dc.createElement('label');
			field.className = ( options.className || 'nino-admin-field' )+ ' nino-admin-field-check';

			const name = dc.createElement('span');
			name.appendChild( dc.createTextNode( options.label ?? '' ) );

			if( options.required === true ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				name.appendChild( star );
			}

			field.appendChild( name );

			const input = dc.createElement('input');
			input.type = 'checkbox';
			input.checked = options.checked === true;
			input.disabled = options.disabled === true;

			if( options.key )
				input.dataset.key = options.key;

			if( options.id )
				input.id = options.id;

			if( options.required === true )
				input.setAttribute( 'aria-required', 'true' );

			input.addEventListener( 'change', function() {
				if( typeof options.onChange === 'function' )
					options.onChange( input.checked );
			} );

			field.appendChild( input );
			field.control = input;

			Nino.adminUi._tools( name, input, options, options.label ?? '' );
			Nino.adminUi._note( field, options.note );

			return field;
		},

		/**
		 *	Several of a set, as one group of ticks: the name of the group is its legend, with the (?) of its help
		 *	behind the words, the values are a list - the values of the ticked boxes, in the order of the options.
		 *	A row may carry a state of its own at the far end ("12 text keys"). It is the field of "which of these",
		 *	for a short list; a long one is searched (elementList()). Owns no strings
		 *
		 *	@param		{Object}		options		{ key, label, help, hint, note, required, options, value, disabled, onChange, className }
		 *														- options    [ { value, label, state, disabled } ] - state is a quiet word at the end of the row
		 *														- value      the values that are ticked
		 *														- onChange   called with the list of the ticked values on every change
		 *
		 *	@return		{Object}									{ el, controls, value(), set( list ), error( text ), clear(), focus() } - el is the group (a fieldset with a legend), controls the boxes in the order of the options
		 */
		checklist : function( options ) {

			const set = dc.createElement('fieldset');
			set.className = ( options.className || 'nino-admin-field' )+ ' nino-admin-checklist-field';

			const legend = dc.createElement('legend');
			legend.appendChild( dc.createTextNode( options.label ?? '' ) );

			if( options.required === true ) {
				const star = dc.createElement('span');
				star.className = 'nino-admin-required';
				star.setAttribute( 'aria-hidden', 'true' );
				star.textContent = '*';
				legend.appendChild( star );
				set.setAttribute( 'aria-required', 'true' );
			}

			set.appendChild( legend );

			const sentence = typeof ( options.help ?? options.hint ) === 'string' ? String( options.help ?? options.hint ) : '';

			if( sentence !== '' )
				Nino.adminUi.sectionHelp( legend, set, sentence );

			const list = dc.createElement('div');
			list.className = 'nino-admin-checklist';

			const controls = [];
			const chosen = new Set( Array.isArray( options.value ) === true ? options.value.map( String ) : [] );

			( Array.isArray( options.options ) === true ? options.options : [] ).forEach( function( option ) {

				const row = dc.createElement('label');
				const input = dc.createElement('input');

				input.type = 'checkbox';
				input.value = String( option.value );
				input.checked = chosen.has( String( option.value ) );
				input.disabled = options.disabled === true || option.disabled === true;

				if( options.key )
					input.dataset.key = options.key;

				row.appendChild( input );
				row.appendChild( Nino.adminUi.el( 'span', '', String( option.label ?? option.value ) ) );

				if( typeof option.state === 'string' && option.state !== '' )
					row.appendChild( Nino.adminUi.el( 'span', 'nino-admin-checklist-state', option.state ) );

				input.addEventListener( 'change', function() {
					if( typeof options.onChange === 'function' )
						options.onChange( handle.value() );
				} );

				controls.push( input );
				list.appendChild( row );
			} );

			set.appendChild( list );
			Nino.adminUi._note( set, options.note );

			const handle = {
				el : set,
				controls : controls,
				value : function() {
					return controls.filter( function( input ) { return input.checked === true } ).map( function( input ) { return input.value } );
				},
				set : function( values ) {
					const wanted = new Set( ( Array.isArray( values ) === true ? values : [] ).map( String ) );
					controls.forEach( function( input ) { input.checked = wanted.has( input.value ) } );
				},
				error : function( text ) {
					handle.clear();
					return Nino.adminUi.fieldError( set, set, 'nino-admin-error-'+ ( ++serial ), text );
				},
				clear : function() { Nino.adminUi.clearFieldErrors( set ) },
				focus : function() { if( controls.length > 0 ) controls[0].focus() },
			};

			return handle;
		},

		/**
		 *	The value of each language, one row for each under the other: the badge of the language at the left, the
		 *	field at the right, the working language of the workbench first and marked. For the short values that are
		 *	required in EVERY language - the name and the address of a page, an alt text -; anything else is edited in
		 *	the working language alone. A row is one line, or several with options.multiline. What is wrong is said
		 *	at the row it is wrong in. Owns no strings
		 *
		 *	@param		{Object}		options		{ key, label, help, hint, note, required, locales, work, values, multiline, rows, maxLength, placeholder, autocomplete, onChange, className }
		 *														- locales    the codes of the languages, in the order of the site
		 *														- work       the working language, which goes first; Nino.admin.workLocale.current by default
		 *														- values     { code: text }
		 *														- multiline  a textarea in each row, options.rows (3) lines high
		 *														- onChange   onChange( code, text ) on every change
		 *
		 *	@return		{Object}									{ el, locales, controls, value( [code] ), set( code, text | map ), error( code, text ), clear(), focus( [code] ) } - value() is the map code => text, value( code ) one text; set( map ) sets several; controls is code => control
		 */
		localeRows : function( options ) {

			const codes = ( Array.isArray( options.locales ) === true ? options.locales : [] ).map( String );
			const work = String( options.work ?? ( shell().workLocale ?? {} ).current ?? Nino.adminUi.locales.native ?? '' );
			const ordered = codes.indexOf( work ) === -1 ? codes.slice() : [ work ].concat( codes.filter( function( code ) { return code !== work } ) );
			const given = options.values !== null && typeof options.values === 'object' ? options.values : {};

			const field = dc.createElement('div');
			field.className = ( options.className || 'nino-admin-field' )+ ' nino-admin-localerows';

			const list = dc.createElement('div');
			list.className = 'nino-admin-localerows-list';

			Nino.adminUi._groupName( field, list, options );
			list.setAttribute( 'role', 'group' );

			const controls = {};
			const names = {};

			ordered.forEach( function( code ) {

				const row = dc.createElement('div');
				row.className = 'nino-admin-localerow'+ ( code === work ? ' is-work' : '' );
				row.dataset.locale = code;

				const control = dc.createElement( options.multiline === true ? 'textarea' : 'input' );
				control.className = 'nino-admin-input';
				control.id = 'nino-admin-control-'+ ( ++serial );
				control.lang = code.replace( '_', '-' );

				if( options.multiline === true )
					control.rows = Number.isInteger( options.rows ) === true && options.rows > 0 ? options.rows : 3;
				else
					control.type = 'text';

				if( typeof options.maxLength === 'number' )
					control.maxLength = options.maxLength;

				if( options.placeholder )
					control.placeholder = options.placeholder;

				if( options.autocomplete )
					control.autocomplete = options.autocomplete;

				if( options.key )
					control.dataset.key = options.key;

				control.dataset.locale = code;
				control.value = typeof given[code] === 'string' ? given[code] : '';

				// The badge is the label of the row: a press on it focuses the field, and it names the field for a screen reader with the name of the group
				const badge = dc.createElement('label');
				badge.className = 'nino-admin-badge nino-admin-locale-badge';
				badge.id = 'nino-admin-badge-'+ ( ++serial );
				badge.setAttribute( 'for', control.id );
				badge.textContent = Nino.adminUi.localeName( code, ordered );

				control.setAttribute( 'aria-labelledby', ( list.getAttribute('aria-labelledby') ?? '' )+ ' '+ badge.id );

				control.addEventListener( 'input', function() {
					if( typeof options.onChange === 'function' )
						options.onChange( code, control.value );
				} );

				const body = dc.createElement('div');
				body.className = 'nino-admin-localerow-body';
				body.appendChild( control );

				row.appendChild( badge );
				row.appendChild( body );
				list.appendChild( row );

				controls[code] = control;
				names[code] = body;
			} );

			field.appendChild( list );
			Nino.adminUi._note( field, options.note );

			const handle = {
				el : field,
				locales : ordered,
				controls : controls,
				value : function( code ) {

					if( code !== undefined )
						return Object.hasOwn( controls, code ) === true ? controls[code].value : '';

					const values = {};
					ordered.forEach( function( each ) { values[each] = controls[each].value } );
					return values;
				},
				set : function( code, text ) {

					const map = code !== null && typeof code === 'object' ? code : { [ code ] : text };

					Object.keys( map ).forEach( function( each ) {
						if( Object.hasOwn( controls, each ) === true )
							controls[each].value = String( map[each] ?? '' );
					} );
				},
				error : function( code, text ) {

					if( Object.hasOwn( controls, code ) === false )
						return null;

					Nino.adminUi.clearFieldErrors( names[code] );
					return Nino.adminUi.fieldError( names[code], controls[code], 'nino-admin-error-'+ ( ++serial ), text );
				},
				clear : function() { Nino.adminUi.clearFieldErrors( field ) },
				focus : function( code ) {

					const control = controls[code ?? work] ?? controls[ordered[0]];

					if( control !== undefined )
						control.focus();
				},
			};

			return handle;
		},

		/**
		 *	A choice of one file, as the guide has it: a button of the second kind, the name of the chosen file beside
		 *	it with its size, "Remove" once there is one, the limits as a note under it, and what is wrong with the
		 *	file at the field. It does not upload: the caller gets the File (onChange) and sends it when the guide's
		 *	model of the view says so. A file that is too large or of a type that `accept` does not name is refused
		 *	at once, said at the field - the types it takes in words, not as the picker's notation - and not taken. Owns no strings
		 *
		 *	@param		{Object}		options		{ key, id, label, help, hint, note, required, accept, maxBytes, button, onChange, className }
		 *														- accept     the input's accept: a list of .ext and type/* or type/sub, which is also what the chosen file is held to
		 *														- maxBytes   the largest file taken; 0 or left out, no limit
		 *														- button     the words of the button instead of /_admin/common/label/file-choose
		 *														- note       the limits of the upload, which stay in view
		 *														- onChange   onChange( file | null ) when a file was chosen or taken away
		 *
		 *	@return		{Object}									{ el, input, button, value(), clear(), error( text ) } - value() is the File, or null
		 */
		fileField : function( options ) {

			const field = dc.createElement('div');
			field.className = ( options.className || 'nino-admin-field' )+ ' nino-admin-filefield';

			const body = dc.createElement('div');
			body.className = 'nino-admin-filefield-body';
			body.setAttribute( 'role', 'group' );

			Nino.adminUi._groupName( field, body, options );

			const input = dc.createElement('input');
			input.type = 'file';
			input.hidden = true;
			input.tabIndex = -1;

			if( typeof options.accept === 'string' && options.accept !== '' )
				input.accept = options.accept;

			if( options.key )
				input.dataset.key = options.key;


			const button = Nino.adminUi.button( options.button ?? Nino.adminUi.text('/_admin/common/label/file-choose'), function() { input.click() }, { kind : 'secondary', id : options.id } );

			// Enter on the button asks for the file picker the way Space does, when the key is let go: a picker asked for while the key goes down is refused by
			// the browser in a dialog (Chromium takes the press for no gesture it will open a window for), so the button was dead for Enter and alive for Space. The
			// key must have gone down on this button: the release of the Enter that opened the dialog arrives on it, and asks for nothing
			let armed = false;

			button.addEventListener( 'keydown', function( ev ) {

				if( ev.key !== 'Enter' )
					return;

				ev.preventDefault();
				armed = ev.repeat !== true;
			} );
			button.addEventListener( 'keyup', function( ev ) {

				if( ev.key === 'Enter' && armed === true ) {
					armed = false;
					input.click();
				}
			} );
			button.addEventListener( 'blur', function() { armed = false } );

			const chosen = Nino.adminUi.el( 'span', 'nino-admin-filefield-name', Nino.adminUi.text('/_admin/common/label/file-none') );
			const size = Nino.adminUi.el( 'span', 'nino-admin-filefield-size' );
			const remove = Nino.adminUi.button( Nino.adminUi.text('/_admin/common/label/remove'), function() { handle.clear( true ) }, { kind : 'secondary', small : true } );

			chosen.id = 'nino-admin-filename-'+ ( ++serial );
			button.setAttribute( 'aria-describedby', chosen.id );
			remove.hidden = true;
			size.hidden = true;

			body.appendChild( input );
			body.appendChild( button );
			body.appendChild( chosen );
			body.appendChild( size );
			body.appendChild( remove );
			field.appendChild( body );
			Nino.adminUi._note( field, options.note );

			let file = null;

			const paint = function() {
				chosen.textContent = file === null ? Nino.adminUi.text('/_admin/common/label/file-none') : file.name;
				chosen.dataset.state = file === null ? 'none' : 'chosen';
				size.textContent = file === null ? '' : Nino.adminUi._bytes( file.size );
				size.hidden = file === null;
				remove.hidden = file === null;
			};

			paint();

			const handle = {
				el : field,
				input : input,
				button : button,
				value : function() { return file },
				clear : function( tell ) {

					file = null;
					input.value = '';
					handle.clearError();
					paint();

					if( tell === true && typeof options.onChange === 'function' )
						options.onChange( null );
				},
				clearError : function() { Nino.adminUi.clearFieldErrors( field ) },
				error : function( text ) {
					handle.clearError();
					return Nino.adminUi.fieldError( field, button, 'nino-admin-error-'+ ( ++serial ), text );
				},
			};

			input.addEventListener( 'change', function() {

				const picked = input.files !== null && input.files !== undefined && input.files.length > 0 ? input.files[0] : null;

				// Nothing chosen - the picker was cancelled: what was chosen before stays
				if( picked === null )
					return;

				handle.clearError();

				if( Number.isFinite( options.maxBytes ) === true && options.maxBytes > 0 && picked.size > options.maxBytes ) {
					input.value = '';
					handle.error( Nino.adminUi.format( Nino.adminUi.text('/_admin/common/error/file-too-large'), Nino.adminUi.megabytes( options.maxBytes ) ) );
					return;
				}

				if( typeof options.accept === 'string' && options.accept !== '' && Nino.adminUi._accepts( picked, options.accept ) === false ) {
					input.value = '';
					handle.error( Nino.adminUi.format( Nino.adminUi.text('/_admin/common/error/file-type'), Nino.adminUi._acceptNames( options.accept ) ) );
					return;
				}

				file = picked;
				paint();

				if( typeof options.onChange === 'function' )
					options.onChange( file );
			} );

			return handle;
		},

		/**
		 *	What an `accept` allows, as a person says it: ".pdf,image/*" is "PDF or picture", "image/svg+xml" is "SVG". An
		 *	extension and a subtype are written in capitals, a whole kind of file ("image/*") by its word, and the last one is joined
		 *	with the word for "or" - so that the sentence about a wrong file names what is taken, not the notation of the picker
		 *
		 *	@param		{string}		accept
		 *
		 *	@return		{string}
		 */
		_acceptNames : function( accept ) {

			const names = [];

			String( accept ).split(',').map( function( part ) { return part.trim() } ).filter( function( part ) { return part !== '' } ).forEach( function( part ) {

				let name = '';

				if( part.charAt(0) === '.' )
					name = part.slice( 1 ).toUpperCase();
				else if( part.slice( -2 ) === '/*' ) {
					const kind = Nino.adminUi.text('/_admin/common/filetype/'+ part.slice( 0, -2 ).toLowerCase() );
					name = kind !== '' && kind.indexOf('/_admin/') !== 0 ? kind : part.slice( 0, -2 );
				}
				else
					name = ( part.split('/')[1] ?? part ).split('+')[0].toUpperCase();

				if( name !== '' && names.indexOf( name ) === -1 )
					names.push( name );
			} );

			if( names.length < 2 )
				return names.join('');

			return names.slice( 0, -1 ).join(', ')+ ' '+ Nino.adminUi.text('/_admin/common/filetype/or')+ ' '+ names[names.length - 1];
		},

		/**
		 *	Whether a file is of what an `accept` names, as the browser's own file picker reads it: a list of
		 *	.extension, type/subtype and type/* , any case. The picker only offers; a file dropped on a page or
		 *	named by hand is held to it here
		 *
		 *	@param		{File}			file
		 *	@param		{string}		accept
		 *
		 *	@return		{boolean}
		 */
		_accepts : function( file, accept ) {

			const name = String( file.name ?? '' ).toLowerCase();
			const type = String( file.type ?? '' ).toLowerCase();

			return String( accept ).toLowerCase().split(',').map( function( part ) { return part.trim() } ).filter( function( part ) { return part !== '' } ).some( function( part ) {

				if( part.charAt(0) === '.' )
					return name.length > part.length && name.slice( -part.length ) === part;

				if( part.slice( -2 ) === '/*' )
					return type.indexOf( part.slice( 0, -1 ) ) === 0;

				return type === part;
			} );
		},

		/**
		 *	A size in bytes as a person reads it: B, KB or MB with a decimal. The units are the same in every language
		 *
		 *	@param		{number}		bytes
		 *
		 *	@return		{string}
		 */
		_bytes : function( bytes ) {

			const number = Number( bytes );

			if( Number.isFinite( number ) === false || number < 0 )
				return '';

			if( number < 1024 )
				return Math.round( number )+ ' B';

			if( number < 1048576 )
				return Nino.adminUi.format( '%s KB', Math.round( number / 102.4 ) / 10 );

			return Nino.adminUi.format( '%s MB', Math.round( number / 104857.6 ) / 10 );
		},

		/**
		 *	A field for a technical value - markup, css, json, a template - of several lines in the face of code and
		 *	nothing else: no editor, no highlighting, no help from the keys. The Tab key is the browser's, it moves
		 *	the focus on (a field that took the Tab key for itself would be a trap for the keyboard); the browser does
		 *	not check the spelling or capitalise or complete what is typed. Where the account may edit the textfills
		 *	of the field it has the "[ ]" like any text field (fills). Owns no strings
		 *
		 *	@param		{Object}		options		As textareaField(): { key, value, rows, placeholder, label, help, hint, note, fills, required, maxLength, id, onInput, className }, rows 8 by default
		 *
		 *	@return		{Element}									The label; the control is its textarea
		 */
		codeField : function( options ) {

			const field = Nino.adminUi.textareaField( Object.assign( { rows : 8 }, options, { spellcheck : false } ) );
			const area = field.querySelector('textarea');

			field.classList.add('nino-admin-field-code');
			area.classList.add('nino-admin-code');
			area.setAttribute( 'wrap', 'off' );
			area.setAttribute( 'autocomplete', 'off' );
			area.setAttribute( 'autocapitalize', 'off' );
			area.setAttribute( 'autocorrect', 'off' );
			field.control = area;

			return field;
		},

		/**
		 *	A moment as two native fields, a date and a time of day, with ONE value: an ISO string without a zone,
		 *	"2026-11-17T06:02", which is the time of the clock of the site - the zone of the site, not the one of the
		 *	browser, so nothing is converted. Empty is allowed (both empty, the value ''); a date without a time
		 *	is midnight, a time without a date is no value. min and max are ISO strings too. The zone the site's clock
		 *	runs in is named under the field where the caller says it. Owns no strings
		 *
		 *	@param		{Object}		options		{ key, id, label, help, hint, note, required, value, min, max, zone, disabled, onChange, className }
		 *														- value      '' or an ISO date with or without a time: 2026-11-17, 2026-11-17T06:02, 2026-11-17 06:02:30
		 *														- min, max   the earliest and the latest moment, ISO as the value; the date field has them as its limits, and valid() says which was broken
		 *														- zone       the name of the site's zone, "Europe/Berlin": said as /_admin/common/label/timezone under the field
		 *														- onChange   onChange( value ) on every change
		 *
		 *	@return		{Object}									{ el, date, time, controls, value(), set( iso ), valid(), error( text ), clear(), focus() } - valid() answers '' or what is wrong: 'incomplete' (a time without a date), 'min' or 'max'
		 */
		dateTime : function( options ) {

			const parse = Nino.adminUi._moment;
			const field = dc.createElement('div');
			field.className = ( options.className || 'nino-admin-field' )+ ' nino-admin-datetime-field';

			const body = dc.createElement('div');
			body.className = 'nino-admin-datetime';
			body.setAttribute( 'role', 'group' );

			Nino.adminUi._groupName( field, body, options );

			const part = function( type, fill ) {

				const control = dc.createElement('input');
				control.type = type;
				control.className = 'nino-admin-input';
				control.disabled = options.disabled === true;
				control.dataset.part = type;

				// Each field is named by the name of the group and by its own word: "Start - Date"
				const word = dc.createElement('span');
				word.className = 'nino-admin-sr-only';
				word.id = 'nino-admin-part-'+ ( ++serial );
				word.textContent = Nino.adminUi.text( fill );
				control.setAttribute( 'aria-labelledby', ( body.getAttribute('aria-labelledby') ?? '' )+ ' '+ word.id );
				body.appendChild( word );
				body.appendChild( control );

				return control;
			};

			const date = part( 'date', '/_admin/common/label/date' );
			const time = part( 'time', '/_admin/common/label/time' );

			if( options.key )
				date.dataset.key = options.key;

			if( options.id )
				date.id = options.id;

			if( options.required === true )
				date.setAttribute( 'aria-required', 'true' );

			const min = parse( options.min );
			const max = parse( options.max );

			if( min !== null )
				date.min = min.date;

			if( max !== null )
				date.max = max.date;

			field.appendChild( body );

			if( typeof options.zone === 'string' && options.zone !== '' )
				Nino.adminUi._note( field, Nino.adminUi.format( Nino.adminUi.text('/_admin/common/label/timezone'), options.zone ) );

			Nino.adminUi._note( field, options.note );

			const handle = {
				el : field,
				date : date,
				time : time,
				controls : [ date, time ],
				value : function() {

					if( date.value === '' )
						return '';

					return date.value+ 'T'+ ( time.value === '' ? '00:00' : time.value.slice( 0, 5 ) );
				},
				set : function( iso ) {

					const moment = parse( iso );

					date.value = moment === null ? '' : moment.date;
					time.value = moment === null || moment.time === '' ? '' : moment.time;
				},
				valid : function() {

					if( date.value === '' )
						return time.value === '' ? '' : 'incomplete';

					const now = handle.value();

					if( min !== null && now < min.date+ 'T'+ ( min.time === '' ? '00:00' : min.time ) )
						return 'min';

					if( max !== null && now > max.date+ 'T'+ ( max.time === '' ? '23:59' : max.time ) )
						return 'max';

					return '';
				},
				error : function( text ) {
					handle.clear();
					return Nino.adminUi.fieldError( field, date, 'nino-admin-error-'+ ( ++serial ), text );
				},
				clear : function() { Nino.adminUi.clearFieldErrors( field ) },
				focus : function() { date.focus() },
			};

			const tell = function() {
				if( typeof options.onChange === 'function' )
					options.onChange( handle.value() );
			};

			date.addEventListener( 'input', tell );
			time.addEventListener( 'input', tell );
			date.addEventListener( 'change', tell );
			time.addEventListener( 'change', tell );

			handle.set( options.value );

			return handle;
		},

		/**
		 *	An ISO moment cut into its date and its time of day: { date : 'YYYY-MM-DD', time : 'HH:MM' | '' } - the seconds,
		 *	a zone and the fraction are dropped, T or a space may part the two. What is no date of the calendar - the 30th
		 *	of February - is none
		 *
		 *	@param		{*}				value
		 *
		 *	@return		{Object|null}
		 */
		_moment : function( value ) {

			const found = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/.exec( String( value ?? '' ).trim() );

			if( found === null )
				return null;

			const day = new Date( Date.UTC( Number( found[1] ), Number( found[2] ) - 1, Number( found[3] ) ) );

			// A day the month does not have rolls over into the next month (or year), which is how it is told
			if( day.getUTCFullYear() !== Number( found[1] ) || day.getUTCMonth() !== Number( found[2] ) - 1 )
				return null;

			if( found[4] !== undefined && ( Number( found[4] ) > 23 || Number( found[5] ) > 59 ) )
				return null;

			return { date : found[1]+ '-'+ found[2]+ '-'+ found[3], time : found[4] === undefined ? '' : found[4]+ ':'+ found[5] };
		},

		/**
		 *	The "which translation am I looking at" switch of a form: a select of
		 *	the locale codes, the current one picked. It says what the form below
		 *	it shows rather than collecting a value, so it is the compact pill of
		 *	the design system (.nino-admin-locale-select) and sits in the pinned
		 *	context bar. What a change does is the caller's - a panel that works in
		 *	the language of the workbench tells the one source of it
		 *	(Nino.admin.workLocale.set()) and draws what it shows when that source
		 *	says it moved. The select is named: the caller's label, or the shared
		 *	word for a translation where it has none - a combobox of bare codes
		 *	is announced as nothing else. An option says the language by its name
		 *	(localeName()), the code behind it only where two of the list are
		 *	named alike
		 *
		 *	@param		{Object}		options					{ locales, inactive, current, label, id, onChange }
		 *														- locales    the codes, which are the values of the options
		 *														- inactive   the codes of the languages the website does not serve yet,
		 *														             which an option says with the word Inactive in parentheses behind its name
		 *														- current    the code that is picked
		 *														- label      the select's aria-label, /_admin/common/label/locale by default
		 *														- onChange   called with the picked code on every change
		 *
		 *	@return		{Element}										The select
		 */
		localeSelect : function( options ) {

			const select = dc.createElement('select');

			if( options.id )
				select.id = options.id;

			select.className = 'nino-admin-locale-select nino-admin-contextbar-select';

			select.setAttribute( 'aria-label', options.label || Nino.adminUi.text('/_admin/common/label/locale') );

			( options.locales || [] ).forEach( function( locale ) {
				select.appendChild( Nino.adminUi.localeOption( locale, options.locales, options.inactive, options.current ) );
			} );

			select.addEventListener( 'change', function() {
				if( typeof options.onChange === 'function' )
					options.onChange( select.value );
			} );

			return select;
		},

		// The languages the workbench names, which the shell hands over once
		// (Nino.admin.workLocale.init()): the native language of the project and
		// the name of each language, code -> name, as the site's own picker says it
		locales : { native : '', names : {} },

		/**
		 *	The name of a language, the one form the whole workbench writes it in
		 *	("Deutsch", "English (US)"): what the project calls it, the code where it
		 *	does not. Where it is told the languages that stand together - the
		 *	options of a select, the rows of a form - and another of them has the
		 *	same name, the code follows in parentheses, so that two of one name can be
		 *	told apart; otherwise the code stays out of sight
		 *
		 *	@param		{string}				code					The language, eg. de_DE
		 *	@param		{Array<string>}	[among]				The codes that are shown together with it
		 *
		 *	@return		{string}
		 */
		localeName : function( code, among ) {

			const names = Nino.adminUi.locales.names;
			const nameOf = function( locale ) {
				return Object.prototype.hasOwnProperty.call( names, locale ) === true && typeof names[locale] === 'string' && names[locale] !== '' ? names[locale] : locale;
			};
			const name = nameOf( code );

			if( Array.isArray( among ) === true && among.some( function( other ) { return other !== code && nameOf( other ).toLowerCase() === name.toLowerCase() } ) === true )
				return name+ ' ('+ code+ ')';

			return name;
		},

		/**
		 *	The one way the workbench writes a date and a time it draws itself: German as "17.11.2026, 06:02", every other interface language as
		 *	"2026-11-17, 06:02" - never with seconds, never as the raw ISO string of a file, and not as the formatter of the site's pages writes it
		 *	(\Nino\Format::datetime() is for visitors, see docs/admin-ui.md, "Words"). A date, and where it has one a time, in the form of the
		 *	interface language. The clock is the one the value carries: a string is not converted to any zone, a Date is read on the clock of the
		 *	browser
		 *
		 *	@param		{string|Date}		value					"Y-m-d", "Y-m-d H:i", "Y-m-d H:i:s" (a "T" for the space), or a Date
		 *	@param		{Object}				[options]			{ time, lang } - time false leaves the time of day out of a value that has one, lang the interface language (the page's own by default)
		 *
		 *	@return		{string}											'' for a value that is no moment
		 */
		dateText : function( value, options ) {

			options = options || {};

			let parts = null;

			if( Object.prototype.toString.call( value ) === '[object Date]' ) {
				if( Number.isNaN( value.getTime() ) === true )
					return '';

				parts = [ value.getFullYear(), value.getMonth() + 1, value.getDate(), value.getHours(), value.getMinutes() ].map( function( part ) { return String( part ).padStart( 2, '0' ) } );
			}
			else if( typeof value === 'string' ) {

				const found = MOMENT.exec( value.trim() );

				if( found === null )
					return '';

				// Digits are no date: a month, a day, an hour or a minute that cannot be is no moment
				if( +found[2] < 1 || +found[2] > 12 || +found[3] < 1 || +found[3] > 31 || ( found[4] !== undefined && ( +found[4] > 23 || +found[5] > 59 ) ) )
					return '';

				parts = [ found[1], found[2], found[3], found[4], found[5] ];
			}
			else
				return '';

			const lang = typeof options.lang === 'string' ? options.lang : ( dE && dE.lang ? dE.lang : '' );
			const date = /^de(?:[-_]|$)/i.test( lang ) === true ? parts[2]+ '.'+ parts[1]+ '.'+ parts[0] : parts[0]+ '-'+ parts[1]+ '-'+ parts[2];

			if( parts[3] === undefined || options.time === false )
				return date;

			return date+ ', '+ parts[3]+ ':'+ parts[4];
		},

		/**
		 *	The option of a language in a select: its code the value, its name the text
		 *	(localeName()), and for a language the website does not serve yet the
		 *	shared word Inactive in parentheses behind the name
		 *
		 *	@param		{string}				locale			The language
		 *	@param		{Array<string>}	[among]			The codes that stand in the same select
		 *	@param		{Array<string>}	[inactive]	The codes of the languages the website does not serve yet
		 *	@param		{string}				[current]		The code that is picked
		 *
		 *	@return		{Element}
		 */
		localeOption : function( locale, among, inactive, current ) {

			const option = dc.createElement('option');
			const name = Nino.adminUi.localeName( locale, among );

			option.value = locale;
			option.textContent = ( inactive || [] ).indexOf( locale ) === -1 ? name : name+ ' ('+ Nino.adminUi.text('/_admin/common/word/inactive')+ ')';
			option.selected = ( locale === current );

			return option;
		},

		/**
		 *	The quiet pill for the context bar of a view that shows content in a
		 *	language: it names the language of the content - only when that is not
		 *	the native language of the project, so that nobody writes in a foreign
		 *	language without noticing. With a bar, the bar's own badge is replaced
		 *	(or taken away), so a view that follows the language draws it again by
		 *	calling this
		 *
		 *	@param		{string}			locale			The language of the content on screen
		 *	@param		{Element}			[bar]				The context bar to put it in, at the end
		 *	@param		{boolean}			[always]		Also for the native language: the view shows it where the workbench's content language is another one the view has no translation of
		 *
		 *	@return		{Element|null}							The pill, null for the native language
		 */
		localeBadge : function( locale, bar, always ) {

			const hasBar = bar !== undefined && bar !== null;
			const old = hasBar === true ? bar.querySelector('.nino-admin-locale-badge') : null;
			let badge = null;

			if( typeof locale === 'string' && locale !== '' && ( locale !== Nino.adminUi.locales.native || always === true ) ) {
				badge = Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-locale-badge', Nino.adminUi.format( Nino.adminUi.text('/_admin/common/label/worklocale-badge'), Nino.adminUi.localeName( locale ) ) );
				badge.dataset.state = 'warn';
			}

			if( old !== null && old !== undefined )
				badge === null ? old.remove() : old.replaceWith( badge );
			else if( hasBar === true && badge !== null )
				( bar.querySelector('.nino-admin-contextbar-name') ?? bar ).appendChild( badge );

			return badge;
		},

		/**
		 *	Whether one model field holds a list of element references rather
		 *	than a single uri - the client-side half of
		 *	\Nino\Elements::isMultiElement(), and deliberately the same rule:
		 *	presence of a number under 'multiple' is the switch, so a model
		 *	written before the setting existed stays a single reference in the
		 *	element form exactly as it does in the kernel.
		 *
		 *	@param	{Object}	field		Model field definition
		 *
		 *	@return	{boolean}
		 */
		isMultiElement : function( field ) {
			return ( field || {} ).type === 'element' && typeof ( field || {} ).multiple === 'number';
		},

		/**
		 *	The control an element field uses once its model lets it hold more
		 *	than one reference: the chosen elements as an ordered, scrollable
		 *	list with move/remove per row, and a search field below it that
		 *	offers what may still be added.
		 *
		 *	A multi-select would have been the smaller control and the wrong
		 *	one. Order is part of this value - it is the order a template
		 *	renders the referenced elements in - and a `<select multiple>`
		 *	has no notion of one, nor anywhere to put the up/down that gives
		 *	the operator a way to say it. It also shows the chosen entries
		 *	scattered through the whole catalogue rather than together, which
		 *	is exactly backwards for a field whose question is "which of these,
		 *	in what order".
		 *
		 *	Searching is done here, over the options the caller already
		 *	loaded, rather than against an endpoint of its own. The element
		 *	form fetches the referenced type's elements up front to build the
		 *	single-reference select (see _loadReferenceOptions() in
		 *	_admin/Nino/Modules/Elements/assets/admin.js), and the Roles tab
		 *	holds every permission already, so the data is in the page before
		 *	this control is built - a request per keystroke would ask a second
		 *	time for what is already here, and would have to grow a debounce
		 *	and a race guard to do it.
		 *
		 *	Owns no strings, same rule as table() and switchField(): every word
		 *	comes from the caller through options.text.
		 *
		 *	@param	{Object}	options		{ key, label, value, limit, options, ordered, text, onChange }
		 *													- key      the model key, written onto the hidden input
		 *													- value    array of currently chosen uris, in order
		 *													- limit    max entries, 0 for unlimited
		 *													- options  [ { value, label } ] everything selectable
		 *													- ordered  false for a set rather than a list: no move
		 *													           buttons, because the order carries no meaning
		 *													           and offering to change it would say it does
		 *													- text     { search, empty, noMatches, more, missing,
		 *													             up, down, remove, add, full }
		 *
		 *	@return	{Element}						The field wrapper; its hidden input carries
		 *													data-field/data-type and holds the value as json
		 */
		elementList : function( options ) {

			const text 		= options.text || {};
			const limit 	= Math.max( 0, parseInt( options.limit, 10 ) || 0 );
			const all 		= Array.isArray( options.options ) ? options.options : [];
			const ordered = options.ordered !== false;

			// A stored value survives a target that was deleted since, exactly
			// as the single-reference select does - dropping it here would turn
			// "this points at something gone" into a silent data loss the next
			// save makes permanent
			let chosen = Array.isArray( options.value ) ? options.value.slice() : [];

			const field = dc.createElement('div');
			field.className = 'nino-admin-field nino-admin-elementlist';

			const name = dc.createElement('span');
			name.textContent = options.label || options.key;
			field.appendChild( name );

			// The value itself. A hidden input rather than component state the
			// form would have to know about: every other field in the element form is
			// read back out of the dom by [data-field], and a control that
			// needed its own read path would be the one field that breaks
			// locale switching, dirty tracking and save alike
			const store = dc.createElement('input');
			store.type = 'hidden';
			store.dataset.field = options.key;
			store.dataset.type = 'element';
			store.dataset.multiple = String( limit );
			field.appendChild( store );

			const list = dc.createElement('ul');
			list.className = 'nino-admin-elementlist-chosen';
			field.appendChild( list );

			const counter = dc.createElement('span');
			counter.className = 'nino-admin-elementlist-count';
			field.appendChild( counter );

			const search = dc.createElement('input');
			search.type = 'search';
			search.className = 'nino-admin-input nino-admin-elementlist-search';
			search.placeholder = text.search || '';
			// The field's own name belongs to the chosen list, not to the box
			// below it - a <div> field, so the <span> above labels nothing at
			// all - and a placeholder is not a name: it is gone the moment
			// somebody types, and never reaches the accessibility tree as one
			search.setAttribute( 'aria-label', text.search || '' );
			field.appendChild( search );

			const results = dc.createElement('ul');
			results.className = 'nino-admin-elementlist-results';
			field.appendChild( results );

			/**
			 *	The label an option carries, or the uri itself marked as
			 *	missing when nothing on offer matches it any more
			 */
			function labelFor( uri ) {
				const match = all.find( function( option ) { return option.value === uri } );
				return ( match === undefined ) ? uri + ( text.missing ? ' (' + text.missing + ')' : '' ) : match.label;
			}

			function commit() {
				store.value = JSON.stringify( chosen );
				// Writing the hidden input fires nothing; tell a surrounding form (status line) the value changed
				if( typeof store.dispatchEvent === 'function' && typeof wn.Event === 'function' )
					store.dispatchEvent( new wn.Event( 'change', { bubbles : true } ) );
				if( typeof options.onChange === 'function' )
					options.onChange( chosen.slice() );
			}

			function move( index, delta ) {
				const target = index + delta;
				if( target < 0 || target >= chosen.length )
					return;
				const moved = chosen[index];
				chosen[index] = chosen[target];
				chosen[target] = moved;
				commit();
				draw();
			}

			function remove( index ) {
				chosen.splice( index, 1 );
				commit();
				draw();
			}

			function add( uri ) {
				// The cap is the model's, so the control refuses past it rather
				// than letting the save be the thing that says no. The kernel
				// checks it again either way - this is the courtesy, not the
				// boundary
				if( chosen.indexOf( uri ) !== -1 || ( limit > 0 && chosen.length >= limit ) )
					return;
				chosen.push( uri );
				commit();
				draw();
			}

			function drawChosen() {

				list.innerHTML = '';

				if( chosen.length === 0 ) {
					const empty = dc.createElement('li');
					empty.className = 'nino-admin-elementlist-empty';
					empty.textContent = text.empty || '';
					list.appendChild( empty );
					return;
				}

				chosen.forEach( function( uri, index ) {

					const row = dc.createElement('li');

					const rowLabel = dc.createElement('span');
					rowLabel.className = 'nino-admin-elementlist-label';
					rowLabel.textContent = labelFor( uri );
					rowLabel.title = uri;
					row.appendChild( rowLabel );

					const actions = dc.createElement('span');
					actions.className = 'nino-admin-elementlist-actions';
					if( ordered === true ) {
						actions.appendChild( Nino.adminUi.button( '↑', function() { move( index, -1 ) }, { name : text.up, disabled : index === 0 } ) );
						actions.appendChild( Nino.adminUi.button( '↓', function() { move( index, 1 ) }, { name : text.down, disabled : index === chosen.length - 1 } ) );
					}
					actions.appendChild( Nino.adminUi.button( '✕', function() { remove( index ) }, { className : 'nino-admin-elementlist-remove', name : text.remove } ) );
					row.appendChild( actions );

					list.appendChild( row );
				} );
			}

			function drawResults() {

				results.innerHTML = '';

				const full = ( limit > 0 && chosen.length >= limit );

				counter.textContent = ( limit > 0 ) ? chosen.length + ' / ' + limit : String( chosen.length );
				counter.classList.toggle( 'is-limit', full );
				search.disabled = full;

				if( full === true ) {
					const note = dc.createElement('li');
					note.className = 'nino-admin-elementlist-note';
					note.textContent = text.full || '';
					results.appendChild( note );
					return;
				}

				const query = search.value.trim().toLowerCase();

				const matches = all.filter( function( option ) {
					if( chosen.indexOf( option.value ) !== -1 )
						return false;
					if( query === '' )
						return true;
					return ( option.label || '' ).toLowerCase().indexOf( query ) !== -1
						|| ( option.value || '' ).toLowerCase().indexOf( query ) !== -1;
				} );

				if( matches.length === 0 ) {
					const none = dc.createElement('li');
					none.className = 'nino-admin-elementlist-note';
					none.textContent = text.noMatches || '';
					results.appendChild( none );
					return;
				}

				// A catalogue of any size still has to render into a panel. The
				// cap is on the rows drawn, never on what the search looked
				// through, and the note says so - a silently truncated result
				// list would read as "there is nothing else", which is the one
				// thing it must not mean
				matches.slice( 0, Nino.adminUi.ELEMENTLIST_RESULTS ).forEach( function( option ) {
					const row = dc.createElement('li');
					const addButton = Nino.adminUi.button( '+', function() { add( option.value ) }, { className : 'nino-admin-elementlist-add', name : text.add } );
					const rowLabel = dc.createElement('span');
					rowLabel.className = 'nino-admin-elementlist-label';
					rowLabel.textContent = option.label;
					rowLabel.title = option.value;
					row.appendChild( addButton );
					row.appendChild( rowLabel );
					results.appendChild( row );
				} );

				if( matches.length > Nino.adminUi.ELEMENTLIST_RESULTS && text.more ) {
					const more = dc.createElement('li');
					more.className = 'nino-admin-elementlist-note';
					more.textContent = Nino.adminUi.format( text.more, matches.length );
					results.appendChild( more );
				}
			}

			function draw() {
				drawChosen();
				drawResults();
			}

			search.addEventListener( 'input', drawResults );

			store.value = JSON.stringify( chosen );
			draw();

			return field;
		},

		/**
		 *	The control an element field of type array uses while its value is a
		 *	plain list of texts: one row per text, with move and remove per row and
		 *	an add button, instead of a JSON field a person has to write brackets
		 *	and quotes into. A value that is anything else (objects, numbers, a
		 *	list inside a list) keeps the JSON textarea - this edits strings and
		 *	cannot tell the rest what it is.
		 *
		 *	Like elementList(), the value is a hidden input carrying the JSON, so
		 *	the form reads, compares and saves it the way it does every other field.
		 *	It starts as exactly the JSON the value is stored as, and only a
		 *	change rewrites it: an untouched list is not an edited one. The rows'
		 *	own inputs carry no data-field - the form takes the first element it
		 *	finds under a key as the field. A row that is empty is not an entry,
		 *	and is left out of the value.
		 *
		 *	Owns no strings, same rule as elementList(): every word comes from the
		 *	caller through options.text.
		 *
		 *	@param	{Object}	options		{ key, label, value, text, onChange }
		 *													- key      the model key, written onto the hidden input
		 *													- value    array of strings, absent for none
		 *													- text     { add, remove, up, down, empty, item } - item
		 *													           names a row for a screen reader, with %d for its
		 *													           number; the field's label is the fallback
		 *
		 *	@return	{Element}						The field wrapper; its hidden input carries
		 *													data-field/data-type and holds the value as json
		 */
		stringList : function( options ) {

			const text = options.text || {};
			const rows = Array.isArray( options.value ) ? options.value.map( String ) : [];

			const field = dc.createElement('div');
			field.className = 'nino-admin-field nino-admin-stringlist';

			const name = dc.createElement('span');
			name.textContent = options.label || options.key;
			field.appendChild( name );

			const store = dc.createElement('input');
			store.type = 'hidden';
			store.dataset.field = options.key;
			store.dataset.type = 'array';
			store.value = JSON.stringify( Array.isArray( options.value ) ? options.value : [] );
			field.appendChild( store );

			const list = dc.createElement('ul');
			list.className = 'nino-admin-stringlist-rows';
			field.appendChild( list );

			function commit() {
				store.value = JSON.stringify( rows.filter( function( row ) { return row.trim() !== '' } ) );
				// Writing the hidden input fires nothing; tell a surrounding form (status line) the value changed
				if( typeof store.dispatchEvent === 'function' && typeof wn.Event === 'function' )
					store.dispatchEvent( new wn.Event( 'change', { bubbles : true } ) );
				if( typeof options.onChange === 'function' )
					options.onChange( JSON.parse( store.value ) );
			}

			function move( index, delta ) {
				const target = index + delta;
				if( target < 0 || target >= rows.length )
					return;
				const moved = rows[index];
				rows[index] = rows[target];
				rows[target] = moved;
				commit();
				// The button that was pressed is redrawn: the one in its place is
				// where the focus stays, so the row can be moved again
				draw( target, delta < 0 ? 'up' : 'down' );
			}

			function remove( index ) {
				rows.splice( index, 1 );
				commit();
				// The row that took its place offers its own remove button next
				draw( Math.min( index, rows.length - 1 ), 'remove' );
				if( rows.length === 0 && typeof add.focus === 'function' )
					add.focus();
			}

			// focusIndex is the row to put the focus in and focusPart the control
			// of it: 'up', 'down', 'remove', or the text input where it is left out
			function draw( focusIndex, focusPart ) {

				list.innerHTML = '';

				if( rows.length === 0 ) {
					const empty = dc.createElement('li');
					empty.className = 'nino-admin-stringlist-empty';
					empty.textContent = text.empty || '';
					list.appendChild( empty );
					return;
				}

				rows.forEach( function( row, index ) {

					const item = dc.createElement('li');

					const input = dc.createElement('input');
					input.type = 'text';
					input.className = 'nino-admin-stringlist-input';
					input.value = row;
					input.setAttribute( 'aria-label', text.item ? Nino.adminUi.format( text.item, index + 1 ) : ( options.label || options.key ) );
					input.addEventListener( 'input', function() {
						rows[index] = input.value;
						commit();
					} );
					item.appendChild( input );

					const actions = dc.createElement('span');
					actions.className = 'nino-admin-stringlist-actions';
					const up 			= Nino.adminUi.button( '↑', function() { move( index, -1 ) }, { name : text.up, disabled : index === 0 } );
					const down 		= Nino.adminUi.button( '↓', function() { move( index, 1 ) }, { name : text.down, disabled : index === rows.length - 1 } );
					const removal = Nino.adminUi.button( '✕', function() { remove( index ) }, { className : 'nino-admin-stringlist-remove', name : text.remove } );
					actions.appendChild( up );
					actions.appendChild( down );
					actions.appendChild( removal );
					item.appendChild( actions );

					list.appendChild( item );

					if( focusIndex !== index )
						return;

					// A move button that has nowhere left to go is disabled and cannot
					// hold the focus: the other one is next, then the text
					const wanted = { up : [ up, down ], down : [ down, up ], remove : [ removal ] }[focusPart] || [];
					const target = wanted.concat( [ input ] ).find( function( control ) { return control.disabled !== true } );

					if( typeof target.focus === 'function' )
						target.focus();
				} );
			}

			const add = Nino.adminUi.button( text.add || '', function() {
				rows.push( '' );
				draw( rows.length - 1 );
			}, { className : 'nino-admin-stringlist-add' } );
			field.appendChild( add );

			draw();

			return field;
		},

		/**
		 *	The data table's pure half: filtering, sorting, paging and cell
		 *	formatting as plain functions over arrays, with no DOM and no
		 *	state. table() below is the rendering half and owns neither.
		 *	Kept separate so the behaviour that is easy to get wrong - type
		 *	aware comparison, page clamping - is directly testable.
		 */
		tableModel : {

			/**
			 *	One cell's text. Deliberately language-neutral: a boolean is
			 *	a glyph rather than "yes"/"no", so the same component reads
			 *	correctly in both interface languages without a caller
			 *	passing a translation for it.
			 *
			 *	@param		{*}				value
			 *	@param		{string}	type			Model field type
			 *
			 *	@return		{string}
			 */
			format : function( value, type ) {

				if( value === null || value === undefined || value === '' )
					return '';

				if( type === 'boolean' )
					return ( value === true || value === 1 || value === '1' ) ? '\u2713' : '\u2013';

				// A multi element field holds its references as a list. Joined
				// with a separator rather than left to String(), whose comma
				// carries no space and reads as one run-on uri
				if( Array.isArray( value ) === true )
					return value.join(', ');

				return String( value );
			},

			/**
			 *	Whether a cell has no value: null, undefined, '' or - for a field
			 *	that holds a list of elements - an empty list. sort() places such
			 *	a cell with the other absent ones, last whichever way the column
			 *	runs
			 *
			 *	@param		{*}				value
			 *
			 *	@return		{boolean}
			 */
			isEmpty : function( value ) {
				// An element field holding an empty list is as absent as a blank
				// string, and sort() has to place it with the other absent
				// values rather than among the present ones
				if( Array.isArray( value ) === true )
					return value.length === 0;
				return value === null || value === undefined || value === '';
			},

			/**
			 *	Order two present values of one type. A plain string compare puts
			 *	10 before 9 and sorts booleans by the words "false"/"true", so each
			 *	type that is not text gets its own ordering. Absent values are not
			 *	handled here on purpose - see sort(), which has to place them
			 *	outside the direction flip.
			 *
			 *	@param		{*}				a
			 *	@param		{*}				b
			 *	@param		{string}	type
			 *
			 *	@return		{number}
			 */
			compare : function( a, b, type ) {

				if( type === 'integer' || type === 'double' )
					return Number( a ) - Number( b );

				if( type === 'boolean' )
					return ( a === true ? 1 : 0 ) - ( b === true ? 1 : 0 );

				// Dates are stored ISO-first, so their lexical order is
				// already chronological - compared as plain strings on
				// purpose rather than parsed into Date objects
				return String( a ).localeCompare( String( b ), undefined, { numeric : true, sensitivity : 'base' } );
			},

			/**
			 *	Order rows by one column: the present values by compare(), the
			 *	absent ones (see isEmpty()) after them whichever way the column
			 *	runs
			 *
			 *	@param		{Array}		rows
			 *	@param		{string}	key				Column key, '' to leave the order alone
			 *	@param		{string}	type			That column's model type
			 *	@param		{number}	dir				1 ascending, -1 descending
			 *	@param		{Function}	[absent]		absent( row ) is true for a row whose cell is to count as absent although it holds a value - a text a column only shows in place of another's
			 *
			 *	@return		{Array}								A new array; the input is not reordered
			 */
			sort : function( rows, key, type, dir, absent ) {

				if( !key )
					return rows.slice();

				const model = Nino.adminUi.tableModel;

				return rows.slice().sort( function( rowA, rowB ) {

					const a = rowA[key], b = rowB[key];
					const emptyA = model.isEmpty( a ) || ( typeof absent === 'function' && absent( rowA ) === true );
					const emptyB = model.isEmpty( b ) || ( typeof absent === 'function' && absent( rowB ) === true );

					// Absent sorts last whichever way the column runs - an
					// unset value is not "smaller", it is missing, and
					// flipping it with the direction opens every descending
					// sort on a screenful of blanks. Deliberately decided
					// before the direction is applied, not inside compare()
					if( emptyA === true || emptyB === true )
						return emptyA === emptyB ? 0 : ( emptyA === true ? 1 : -1 );

					return model.compare( a, b, type ) * ( dir < 0 ? -1 : 1 );
				} );
			},

			/**
			 *	Substring match across the given columns, case-insensitive.
			 *	One box over every visible column rather than a filter per
			 *	column: this is the "where is that entry" case, not a query
			 *	builder. A column reads as what the cell shows: shown( row, column )
			 *	answers that text where the caller has it (a column with a drawing
			 *	function shows something else than its value), and without it
			 *	the value is read as format() writes it
			 *
			 *	@param		{Array}		rows
			 *	@param		{Array}		columns		[ { key, type }, ... ]
			 *	@param		{string}	query
			 *	@param		{Function}	[extra]		extra( row ) answers more text of a row the search reads, beyond its columns
			 *	@param		{Function}	[shown]		shown( row, column ) answers the text the cell of that column shows
			 *
			 *	@return		{Array}
			 */
			filter : function( rows, columns, query, extra, shown ) {

				const needle = String( query || '' ).trim().toLowerCase();
				if( needle === '' )
					return rows.slice();

				return rows.filter( function( row ) {
					return columns.some( function( column ) {
						const text = typeof shown === 'function' ? shown( row, column ) : Nino.adminUi.tableModel.format( row[column.key], column.type );
						return String( text ).toLowerCase().indexOf( needle ) !== -1;
					} ) || ( typeof extra === 'function' && String( extra( row ) ?? '' ).toLowerCase().indexOf( needle ) !== -1 );
				} );
			},

			/**
			 *	How many pages a number of rows make at a page size
			 *
			 *	@param		{number}	total
			 *	@param		{number}	pageSize
			 *
			 *	@return		{number}						At least 1, so an empty table still has a page 1
			 */
			pageCount : function( total, pageSize ) {
				return Math.max( 1, Math.ceil( total / Math.max( 1, pageSize ) ) );
			},

			/**
			 *	One page of rows, with the page number clamped into range -
			 *	filtering down to fewer results than the current page starts
			 *	at must not show an empty table
			 *
			 *	@param		{Array}		rows
			 *	@param		{number}	pageSize
			 *	@param		{number}	page			1-based
			 *
			 *	@return		{Object}						{ rows, page, pages, from, to, total }
			 */
			page : function( rows, pageSize, page ) {

				const total = rows.length;
				const pages = Nino.adminUi.tableModel.pageCount( total, pageSize );
				const at 		= Math.min( Math.max( 1, page | 0 || 1 ), pages );
				const start = ( at - 1 ) * pageSize;

				return {
					rows 	: rows.slice( start, start + pageSize ),
					page 	: at,
					pages : pages,
					from 	: total === 0 ? 0 : start + 1,
					to 		: Math.min( start + pageSize, total ),
					total : total,
				};
			},
		},

		/**
		 *	A sortable, searchable, paged data table.
		 *
		 *	Owns no strings. Every word it can show comes from the caller
		 *	through `labels`, translated through Nino.content or not, so the
		 *	component is never half in one language and half in another.
		 *	Everything that can be a number or a glyph - the pager arrows, the
		 *	row range, a boolean cell - is one, so the caller only has to
		 *	supply two actual sentences.
		 *
		 *	What is drawn as a glyph still has to be *called* something: a
		 *	button whose face is '‹' is announced as "button ‹" and the search
		 *	box's placeholder is gone the moment somebody types in it. Those
		 *	three names come from `labels` where the caller has a word, and
		 *	from the workbench's shared fills where it has not - the same rule
		 *	switchField()'s on/off pair follows, and the reason the caller
		 *	still does not have to know them.
		 *
		 *	The whole set is passed in and paged here rather than fetched a
		 *	page at a time: an element type is one file that is read whole
		 *	on every request (see Nino\Elements::queryElements), so a page
		 *	request costs exactly what the full set costs - and searching
		 *	locally is instant instead of a round trip per keystroke.
		 *
		 *	@param		{Object}		options
		 *	@param		{Element}		options.mount					Container; emptied and taken over
		 *	@param		{Array}			options.columns				[ { key, label, type, render, sortable }, ... ]
		 *																					A column is sorted by the button its label is;
		 *																					`sortable` is true for a column that has a label
		 *																					and false for one that has none - the column of
		 *																					a row's own buttons - unless the caller says.
		 *																					`absent(row)` says that a row's cell counts as empty
		 *																					when the column is sorted, though it shows a value
		 *																					(searching still reads what is shown).
		 *																					`render(value, row)` may return an Element to
		 *																					put in the cell instead of text - what a column
		 *																					holding a mailto link or a per-row action needs.
		 *																					Such a column is sorted on its plain value and
		 *																					searched on the text its cell shows, so a search
		 *																					finds what a person sees. `wrap` lets the cell
		 *																					break its lines, where a cell holds a sentence.
		 *	@param		{Array}			options.rows					The complete set
		 *	@param		{Object}		options.labels				{ search, empty, noMatch } - and optionally
		 *																					{ prev, next, pageSize }, the three names the
		 *																					pager's glyphs carry; each falls back to
		 *																					/_admin/common/label/prevpage, -/nextpage and
		 *																					-/perpage
		 *	@param		{boolean}		[options.search]			True for the search of a directory: the field takes the full width over the table
		 *																					and filters the rows that are drawn, in the browser, with no request. Left out,
		 *																					the field is the short one it always was. Either way it reads the columns' values
		 *	@param		{Function}	[options.searchText]	searchText( row ) answers more text of a row that the search reads beside the columns' values - a key, a description
		 *	@param		{string}		[options.query]				What the search holds to begin with, for a table that is drawn again
		 *	@param		{Function}	[options.onQuery]			Called with what was typed in the search
		 *	@param		{Function}	[options.actions]			actions( row ) answers the small buttons of a row (at most three, the one that takes something
		 *																					away last, see rowActions()), drawn in a last column that has no head and stands at the right
		 *	@param		{number}		[options.pageSize]		Initial rows per page (default 50)
		 *	@param		{Array}			[options.pageSizes]		Offered sizes (default [50,100,150])
		 *	@param		{Function}	[options.onRowClick]	Called with the row object, on a click of the row and on Enter or Space
		 *																					with the row focused. The row is a stop of the Tab
		 *																					key only where no control stands in it: a row with
		 *																					buttons or links of its own is reached through them
		 *	@param		{string}		[options.rowKey]			Row property used as the row's identity - also written on the row as data-row, where a
		 *																					caller finds it again after the table was drawn anew
		 *	@param		{number}		[options.actionsInline]	How many actions stand in a row before a menu takes the rest, 2 or 3 (3 by default): with more
		 *																					than that the first two stand as they are and the others are in a menu behind "More actions"
		 *																					(see menuButton()), the one that takes something away last
		 *	@param		{Object}		[options.filter]			{ options, value, label, onChange( value ), id } - a select left of the search, in the same line (under 30 rem over it, both of the full
		 *																					width): options [ { value, label } ], value the one chosen, label its name for a screen reader, onChange told the value. The table
		 *																					does NOT filter by it - the caller gets the value and loads or filters the rows, drawing the table anew or with setRows(). The same bar for a
		 *																					list that is no table is filterBar( { filter, search } )
		 *	@param		{Object}		[options.select]			{ actions, label, rowLabel } - the rows can be chosen. A first column of checkboxes, each named by
		 *																					rowLabel( row ) (the text of the first column by default), and in the head one for the rows of the page
		 *																					that is shown, with the three states none, some, all of them. With one chosen a bar over the table says how
		 *																					many, how many of them are not on this page (by the search or the paging), offers "All N matches" where the
		 *																					search matches more rows than the page shows, has the actions as buttons - actions : [ { key, label, icon,
		 *																					danger, run( keys ) } ], run is handed the keys (the rowKey, as it is in the row) of ALL the chosen rows, in the
		 *																					order of rows - and "Clear selection". A search, a sort and a page keep what is chosen; setRows() drops the keys
		 *																					that are gone. Shift and a click chooses the range from the box touched last to this one, on the page that is
		 *																					shown (the box is remembered by its row, and forgotten by a search, a sort, a page and new rows). label names
		 *																					the head's checkbox. Without select the table has none of it
		 *
		 *	@return		{Object}													{ setRows, destroy, selected(), clearSelection() } - selected() the keys of the chosen rows, in the order of rows
		 */
		table : function( options ) {

			const mount 		= options.mount;
			const columns 	= options.columns || [];
			const labels 		= options.labels || {};
			const pageSizes = options.pageSizes || [ 50, 100, 150 ];
			const rowKey 		= options.rowKey || 'uri';

			let rows 			= options.rows || [];
			let pageSize 	= options.pageSize || pageSizes[0];
			let page 			= 1;
			let sortKey 	= '';
			let sortDir 	= 1;
			let query 		= typeof options.query === 'string' ? options.query : '';

			const model = Nino.adminUi.tableModel;

			// The rows that can be chosen: the key (as a string) of each, and the key as it is in the row
			const selecting = typeof options.select === 'object' && options.select !== null;
			const chosen = new Map();
			let matchedNow = [];
			let pageNow = [];
			let anchor = null;
			const keyOf = function( row ) {
				return row !== null && typeof row === 'object' && row[rowKey] !== undefined && row[rowKey] !== null && String( row[rowKey] ) !== '' ? row[rowKey] : null;
			};

			// What a cell shows is what the search reads. The text of a column that has a drawing function is
			// what the drawing makes, found once for each row and column and kept until the rows change
			let shownText = new Map();

			const shown = function( row, column ) {

				if( typeof column.render !== 'function' )
					return model.format( row[column.key], column.type );

				let cells = shownText.get( row );

				if( cells === undefined ) {
					cells = new Map();
					shownText.set( row, cells );
				}

				if( cells.has( column.key ) === false ) {

					const cell = column.render( row[column.key], row );

					cells.set( column.key, typeof cell === 'object' && cell !== null && cell.nodeType ? String( cell.textContent ?? '' ) : ( cell === undefined ? model.format( row[column.key], column.type ) : String( cell ) ) );
				}

				return cells.get( column.key );
			};

			/*	The three names the pager's glyphs carry: the caller's word where
				it has one, the workbench's shared fill where it has not, English
				where neither is there - the rule switchField()'s on/off pair
				follows. Three literal lookups rather than one built from a key:
				a fill that only a concatenated argument ever names is invisible
				to the static check every workbench script is held to	*/
			const prevLabel	= labels.prev 		?? ( Nino.content.getText('/_admin/common/label/prevpage') || 'Previous page' );
			const nextLabel	= labels.next 		?? ( Nino.content.getText('/_admin/common/label/nextpage') || 'Next page' );
			const sizeLabel	= labels.pageSize	?? ( Nino.content.getText('/_admin/common/label/perpage') || 'Rows per page' );

			mount.innerHTML = '';
			mount.classList.add('nino-admin-table-wrap');

			// --- search -----------------------------------------------
			// The toolbar is the filter bar of the design system: the select that picks a kind, where the caller has one (options.filter), and the search.
			// The table filters by the search alone; what the select chooses is the caller's to apply, who is told
			const filtered = typeof options.filter === 'object' && options.filter !== null;
			const made = Nino.adminUi.filterBar( {
				filter : filtered === true ? options.filter : null,
				search : {
					className : 'nino-admin-table-search',
					// A placeholder is not a name: it disappears on the first keystroke
					// and never reaches the accessibility tree as one. The toolbar has
					// no room for a visible label, so the box carries the same word the
					// placeholder shows
					placeholder : labels.search || '',
					label : labels.search || '',
					value : query,
					onInput : function( typed ) {
						query = typed;
						page = 1;
						draw();
						if( typeof options.onQuery === 'function' )
							options.onQuery( query );
					},
				},
			} );
			const toolbar = made.el;
			// The toolbar is the bar's content - the select and the search - under the table's own classes: it is not a second filter bar of the page
			toolbar.className = 'nino-admin-table-toolbar'+ ( options.search === true ? ' nino-admin-table-toolbar--wide' : '' )+ ( filtered === true ? ' nino-admin-table-toolbar--filtered' : '' );
			mount.appendChild( toolbar );

			// --- the bar of the chosen rows ---------------------------------
			let bar = null;
			let barCount = null;
			let barHidden = null;
			let barAll = null;
			let said = null;

			if( selecting === true ) {

				bar = dc.createElement('div');
				bar.className = 'nino-admin-table-selection nino-admin-hidden';
				barCount = Nino.adminUi.el( 'span', 'nino-admin-table-selection-count' );
				bar.appendChild( barCount );
				barHidden = Nino.adminUi.el( 'span', 'nino-admin-table-selection-hidden' );
				bar.appendChild( barHidden );

				( Array.isArray( options.select.actions ) === true ? options.select.actions : [] ).forEach( function( action ) {

					bar.appendChild( Nino.adminUi.button( String( action.label ?? '' ), function() {
						if( typeof action.run === 'function' )
							action.run( selected() );
					}, { kind : action.danger === true ? 'danger' : 'secondary', small : true, icon : action.icon } ) );
				} );

				// The rows of the other pages, offered with their number - never chosen without it being said
				barAll = Nino.adminUi.button( '', function() {

					matchedNow.map( keyOf ).filter( function( key ) { return key !== null } ).forEach( function( key ) { chosen.set( String( key ), key ) } );
					anchor = null;
					syncSelection( true );
				}, { kind : 'secondary', small : true } );
				bar.appendChild( barAll );

				bar.appendChild( Nino.adminUi.button( Nino.adminUi.text('/_admin/common/table/clear'), function() { clearSelection() }, { kind : 'link', small : true } ) );

				// The number is said once for each change, to a screen reader, and is always in the page: a live region that comes and goes says nothing
				said = Nino.adminUi.el( 'span', 'nino-admin-sr-only' );
				said.setAttribute( 'aria-live', 'polite' );
				said.setAttribute( 'role', 'status' );
				mount.appendChild( said );
				mount.appendChild( bar );
			}

			// --- table ------------------------------------------------
			const scroller = dc.createElement('div');
			scroller.className = 'nino-admin-table-scroll';

			const table = dc.createElement('table');
			table.className = 'nino-admin-table';

			const thead = dc.createElement('thead');
			const headRow = dc.createElement('tr');

			// A box of the chosen rows, 24 px to press: the label is the target, the input is drawn in it
			const checkbox = function( name ) {

				const box = dc.createElement('label');
				box.className = 'nino-admin-table-selectcell';

				const input = dc.createElement('input');
				input.type = 'checkbox';
				input.className = 'nino-admin-table-check';
				input.setAttribute( 'aria-label', name );
				box.appendChild( input );

				return { box : box, input : input };
			};

			let headCheck = null;

			if( selecting === true ) {
				const th = dc.createElement('th');
				const all = checkbox( options.select.label ?? Nino.adminUi.text('/_admin/common/table/select-page') );

				th.className = 'nino-admin-table-select';
				headCheck = all.input;
				// The box of the head is for the rows of the page that is shown: what a person sees is what is chosen. The rows of the other pages are chosen
				// by the offer in the bar, which says how many they are
				headCheck.addEventListener( 'click', function() {

					const keys = pageNow.map( keyOf ).filter( function( key ) { return key !== null } );
					const every = keys.length > 0 && keys.every( function( key ) { return chosen.has( String( key ) ) } );

					keys.forEach( function( key ) { every === true ? chosen.delete( String( key ) ) : chosen.set( String( key ), key ) } );
					anchor = null;
					syncSelection( true );
				} );
				th.appendChild( all.box );
				headRow.appendChild( th );
			}

			columns.forEach( function( column ) {

				const th = dc.createElement('th');
				th.dataset.key = column.key;
				if( column.type === 'integer' || column.type === 'double' )
					th.classList.add('nino-admin-table-num');

				// A sort button with no word on it is announced as nothing and
				// offers an order nobody can name: a column without a label has none
				if( column.sortable === undefined ? String( column.label ?? '' ) === '' : column.sortable !== true ) {
					th.textContent = column.label ?? '';
					headRow.appendChild( th );
					return;
				}

				const btn = dc.createElement('button');
				btn.type = 'button';
				btn.className = 'nino-admin-table-sort';
				btn.textContent = column.label;
				btn.addEventListener( 'click', function() {
					sortDir = ( sortKey === column.key ) ? -sortDir : 1;
					sortKey = column.key;
					page = 1;
					draw();
				} );

				th.appendChild( btn );
				headRow.appendChild( th );
			} );

			// The column of a row's buttons has no head: a head with no word is announced as nothing. A table whose rows open
			// has the column too, for the "\u203a" that says so
			if( typeof options.actions === 'function' || typeof options.onRowClick === 'function' ) {
				const th = dc.createElement('th');
				th.className = 'nino-admin-table-actions';
				headRow.appendChild( th );
			}

			thead.appendChild( headRow );
			table.appendChild( thead );

			const tbody = dc.createElement('tbody');
			table.appendChild( tbody );
			scroller.appendChild( table );
			mount.appendChild( scroller );

			const empty = Nino.adminUi.emptyState( labels.empty || '' );
			mount.appendChild( empty );

			// --- pager ------------------------------------------------
			const pager = dc.createElement('div');
			pager.className = 'nino-admin-table-pager';

			const range = dc.createElement('span');
			range.className = 'nino-admin-table-range';

			const sizeSelect = dc.createElement('select');
			sizeSelect.className = 'nino-admin-table-size';
			sizeSelect.setAttribute( 'aria-label', sizeLabel );
			pageSizes.forEach( function( size ) {
				const option = dc.createElement('option');
				option.value = String( size );
				option.textContent = String( size );
				option.selected = ( size === pageSize );
				sizeSelect.appendChild( option );
			} );
			sizeSelect.addEventListener( 'change', function() {
				pageSize = parseInt( sizeSelect.value, 10 ) || pageSizes[0];
				page = 1;
				draw();
			} );

			/*	The two arrows are glyphs, and a glyph is not a name: a screen
				reader announced the pager as "button ‹" and "button ›". The
				component owns no strings, so the word comes from the caller
				where there is one and from the shared fills where there is
				not - the rule switchField()'s on/off pair already follows	*/
			const prev = dc.createElement('button');
			prev.type = 'button';
			prev.className = 'nino-admin-table-step';
			prev.textContent = '\u2039';
			prev.setAttribute( 'aria-label', prevLabel );
			prev.addEventListener( 'click', function() { page--; draw() } );

			const next = dc.createElement('button');
			next.type = 'button';
			next.className = 'nino-admin-table-step';
			next.textContent = '\u203a';
			next.setAttribute( 'aria-label', nextLabel );
			next.addEventListener( 'click', function() { page++; draw() } );

			pager.appendChild( sizeSelect );
			pager.appendChild( range );
			pager.appendChild( prev );
			pager.appendChild( next );
			mount.appendChild( pager );

			function draw() {

				const matched = model.filter( rows, columns, query, options.searchText, shown );
				const sortColumn = columns.find( function( c ) { return c.key === sortKey } ) || {};
				const sorted 	= model.sort( matched, sortKey, sortColumn.type, sortDir, sortColumn.absent );
				const view 		= model.page( sorted, pageSize, page );

				page = view.page;
				matchedNow = sorted;
				pageNow = view.rows;

				// A press with Shift reaches back to a box on the page that is shown, and a page that was drawn anew has none that was touched
				anchor = null;

				headRow.querySelectorAll('th').forEach( function( th ) {

					// "none" tells the reader a column can be sorted: not one that cannot
					if( th.querySelector('button') === null )
						return;

					const sorted = th.dataset.key === sortKey;

					th.classList.toggle( 'is-sorted', sorted );
					th.classList.toggle( 'is-desc', sorted && sortDir < 0 );

					/*	Which column the table is ordered by, said rather than only
						drawn: the ↑/↓ above is a ::after on the sorted header, and a
						generated glyph is one of the two things a screen reader is
						never handed - the other being the colour it is drawn in. A
						header that is not the sorted one says "none", which is what
						tells the reader the column can be sorted at all	*/
					th.setAttribute( 'aria-sort', sorted === false ? 'none' : ( sortDir < 0 ? 'descending' : 'ascending' ) );
				} );

				tbody.innerHTML = '';
				view.rows.forEach( function( row ) {

					const tr = dc.createElement('tr');
					if( row[rowKey] !== undefined && row[rowKey] !== null )
						tr.dataset.row = String( row[rowKey] );
					if( typeof options.onRowClick === 'function' ) {
						tr.classList.add('is-clickable');
						tr.addEventListener( 'click', function( ev ) {
							// A press on a button or a link in the row is the control's, not the row's
							if( ev && ev.target && ev.target !== tr && typeof ev.target.closest === 'function' && ev.target.closest('a, button, input, select, textarea') !== null )
								return;
							options.onRowClick( row );
						} );
						tr.addEventListener( 'keydown', function( ev ) {
							// A key pressed on a button in the row is the button's, not the row's
							if( ev.target === tr && ( ev.key === 'Enter' || ev.key === ' ' ) ) { ev.preventDefault(); options.onRowClick( row ) }
						} );
					}

					if( selecting === true ) {

						const td = dc.createElement('td');
						const key = keyOf( row );

						td.className = 'nino-admin-table-select';

						if( key !== null ) {

							const first = columns.length > 0 ? shown( row, columns[0] ) : '';
							const named = typeof options.select.rowLabel === 'function' ? String( options.select.rowLabel( row ) ?? '' ) : first;
							const one = checkbox( named !== '' ? named : String( key ) );
							const at = view.rows.indexOf( row );

							one.input.addEventListener( 'click', function( ev ) {

								const on = one.input.checked === true;
								const from = anchor === null ? -1 : pageNow.findIndex( function( other ) { return keyOf( other ) !== null && String( keyOf( other ) ) === anchor } );

								// Shift and a click: the range on the page from the last box touched - the row, by its key, and only where it is on this page - in the state this one now has
								if( ev.shiftKey === true && from !== -1 )
									pageNow.slice( Math.min( from, at ), Math.max( from, at ) + 1 ).forEach( function( other ) {

										const otherKey = keyOf( other );

										if( otherKey !== null )
											on === true ? chosen.set( String( otherKey ), otherKey ) : chosen.delete( String( otherKey ) );
									} );
								else
									on === true ? chosen.set( String( key ), key ) : chosen.delete( String( key ) );

								anchor = String( key );
								syncSelection( true );
							} );

							td.appendChild( one.box );
						}

						tr.appendChild( td );
					}

					columns.forEach( function( column ) {

						const td = dc.createElement('td');
						const text = model.format( row[column.key], column.type );

						if( typeof column.render === 'function' ) {
							const cell = column.render( row[column.key], row );
							// A returned node goes in as-is; anything else is
							// still treated as text, never as markup
							if( typeof cell === 'object' && cell !== null && cell.nodeType )
								td.appendChild( cell );
							else
								td.textContent = cell === undefined ? text : String( cell );
						}
						else
							// textContent throughout: every value here is content
							// someone typed, and some of it is deliberately html
							td.textContent = text;
						if( column.type === 'integer' || column.type === 'double' )
							td.classList.add('nino-admin-table-num');
						if( column.type === 'element' || column.key === rowKey )
							td.classList.add('nino-admin-table-uri');
						if( column.wrap === true )
							td.classList.add('is-wrap');
						if( text === '' )
							td.classList.add('is-empty');
						tr.appendChild( td );
					} );

					if( typeof options.actions === 'function' || typeof options.onRowClick === 'function' ) {
						const td = dc.createElement('td');
						td.className = 'nino-admin-table-actions';

						if( typeof options.actions === 'function' )
							td.appendChild( Nino.adminUi._rowActionsMenu( options.actions( row ), options.actionsInline ) );

						// A row that opens says so
						if( typeof options.onRowClick === 'function' ) {
							const chev = Nino.adminUi.el( 'span', 'nino-admin-row-chev', '\u203a' );
							chev.setAttribute( 'aria-hidden', 'true' );
							td.appendChild( chev );
						}

						tr.appendChild( td );
					}

					// Its own stop of the Tab key only where nothing inside it is one: a
					// row that holds its buttons or links is reached through them, and
					// would be a second stop for the same thing
					if( typeof options.onRowClick === 'function' && tr.querySelector('a, button, input, select, textarea') === null )
						tr.tabIndex = 0;

					tbody.appendChild( tr );
				} );

				// "nothing here yet" and "nothing matched" are different facts
				// and read differently in every language, so the caller
				// supplies both rather than one doing duty for the other
				empty.textContent = ( rows.length > 0 && query !== '' )
					? ( labels.noMatch || labels.empty || '' )
					: ( labels.empty || '' );

				// A range and a page count read the same in every language
				range.textContent = view.total === 0
					? '0'
					: view.from+ '\u2013'+ view.to+ ' / '+ view.total;

				prev.disabled = ( view.page <= 1 );
				next.disabled = ( view.page >= view.pages );

				// .nino-admin-hidden, not the workbench's own .admin-hidden:
				// this component belongs to the design system, which uses
				// nino-admin-* classes only and does not lean on a class of
				// the nino.tool layer (see _admin/assets/style.css)
				scroller.classList.toggle( 'nino-admin-hidden', view.total === 0 );
				// The toolbar stays put when a search emptied the table -
				// hiding it would trap the user with no way to clear the box
				toolbar.classList.toggle( 'nino-admin-hidden', rows.length === 0 && query === '' && filtered === false );
				empty.classList.toggle( 'nino-admin-hidden', view.total !== 0 );
				pager.classList.toggle( 'nino-admin-hidden', view.total === 0 );

				syncSelection( false );
			}

			// What is chosen, in the order of the rows
			function selected() {
				return rows.map( keyOf ).filter( function( key ) { return key !== null && chosen.has( String( key ) ) } );
			}

			// The boxes, the head's three states and the bar say what is chosen; say tells a screen reader the number
			function syncSelection( say ) {

				if( selecting !== true )
					return;

				const keys = pageNow.map( keyOf ).filter( function( key ) { return key !== null } );
				const among = keys.filter( function( key ) { return chosen.has( String( key ) ) } ).length;
				const matched = matchedNow.map( keyOf ).filter( function( key ) { return key !== null } );
				const matchedChosen = matched.filter( function( key ) { return chosen.has( String( key ) ) } ).length;

				headCheck.checked = keys.length > 0 && among === keys.length;
				headCheck.indeterminate = among > 0 && among < keys.length;
				headCheck.disabled = keys.length === 0;

				Array.from( tbody.querySelectorAll('tr') ).forEach( function( tr ) {

					const box = tr.querySelector('.nino-admin-table-check');
					const on = tr.dataset.row !== undefined && chosen.has( tr.dataset.row );

					if( box !== null )
						box.checked = on;

					tr.classList.toggle( 'is-selected', on );
				} );

				// What is chosen and cannot be seen on this page - by the search or by the page - is said with the number, so that an action never reaches more than is shown
				const text = Nino.adminUi.format( Nino.adminUi.text('/_admin/common/table/selected'), chosen.size );
				const unseen = chosen.size - among;
				const unseenText = unseen > 0 ? Nino.adminUi.format( Nino.adminUi.text('/_admin/common/table/unseen'), unseen ) : '';
				const offer = matched.length > keys.length && matchedChosen < matched.length;

				barCount.textContent = text;
				barHidden.textContent = unseenText;
				barHidden.classList.toggle( 'nino-admin-hidden', unseen < 1 );
				barAll.textContent = offer === true ? Nino.adminUi.format( Nino.adminUi.text('/_admin/common/table/all'), matched.length ) : '';
				barAll.classList.toggle( 'nino-admin-hidden', offer === false );
				bar.classList.toggle( 'nino-admin-hidden', chosen.size === 0 );

				if( say === true )
					said.textContent = chosen.size === 0 ? '' : text+ ( unseenText === '' ? '' : ', '+ unseenText );
			}

			function clearSelection() {
				chosen.clear();
				anchor = null;
				syncSelection( true );
			}

			draw();

			return {
				setRows : function( next ) {

					rows = next || [];
					shownText = new Map();
					page = 1;

					// A key that is not in the new rows is gone from the choice
					const present = new Set( rows.map( keyOf ).filter( function( key ) { return key !== null } ).map( String ) );
					Array.from( chosen.keys() ).forEach( function( key ) { if( present.has( key ) === false ) chosen.delete( key ) } );

					draw();
				},
				destroy : function() { mount.innerHTML = ''; mount.classList.remove('nino-admin-table-wrap') },
				selected : selected,
				clearSelection : clearSelection,
			};
		},

		/**
		 *	Put one "show-<panel>" state class on an app shell, replacing
		 *	whichever one it currently carries and leaving everything else
		 *	alone.
		 *
		 *	The shell element carries two unrelated things at once: the
		 *	design system's own classes (.nino-admin, .nino-admin-shell -
		 *	which every layout rule in _admin/assets/style.css hangs off) and
		 *	the "which panel is open" state the shell script switches on.
		 *	Assigning className outright is the obvious way to do the second
		 *	and silently destroys the first, so both shells - the workbench's
		 *	and the setup wizard's - go through here instead.
		 *
		 *	@param		{Element}	shell					The tool's own page wrapper
		 *	@param		{string}	stateClass		Eg. "show-elements"
		 *
		 *	@return		void
		 */
		setStateClass : function( shell, stateClass ) {

			if( shell === null || shell === undefined )
				return;

			Array.from( shell.classList ).forEach( function( name ) {
				if( name.indexOf('show-') === 0 )
					shell.classList.remove( name );
			} );

			if( stateClass )
				shell.classList.add( stateClass );
		},

		/**
		 *	Fill a message's placeholders - %s, %d and %n - from the params, in
		 *	the order they stand. A function replaces them rather than a string:
		 *	String.replace() reads '$&' and '$$' in a replacement string, and
		 *	the params are what a person typed (an uri, a key, a file name), so
		 *	"a$&b" went in and "a%sb" came out. A placeholder with no param left
		 *	stays as it is
		 *
		 *	@param		{string}	text					The message, eg. 'Saved at %s.'
		 *	@param		{...*}		params				What goes into it
		 *
		 *	@return		{string}
		 */
		format : function( text, ...params ) {

			let at = 0;

			// A number with a fraction is written the way the interface language
			// writes it: 0,5 MB in German
			const words = function( value ) {

				const plain = String( value );
				if( typeof value !== 'number' || Number.isInteger( value ) === true || typeof Nino.content !== 'object' || Nino.content === null )
					return plain;

				const mark = Nino.content.getText('/_admin/common/unit/decimal') || '.';
				return plain.replace( '.', function() { return mark } );
			};

			return String( text ?? '' ).replace( /%[sdn]/g, function( token ) {
				return at < params.length ? words( params[at++] ) : token;
			} );
		},

		/**
		 *	Say a failed request in a container: what errorText() makes of it, in
		 *	the design system's error text, in place of whatever stood there -
		 *	the load that failed, where there is nothing else to show
		 *
		 *	@param		{Element}		container
		 *	@param		{number}		status					Xhr status code
		 *	@param		{*}					response				Parsed response body, if any
		 *	@param		{string}		[fallbackKey]		The panel's own sentence for this failure
		 *
		 *	@return		{Element}										The paragraph written
		 */
		showError : function( container, status, response, fallbackKey ) {

			container.innerHTML = '';
			const p = dc.createElement('p');
			p.className = 'nino-admin-error';
			p.textContent = Nino.adminUi.api.errorText( status, response, fallbackKey );
			container.appendChild( p );
			return p;
		},

		/**
		 *	The limits an image upload runs into, as the shell wrote them onto
		 *	its wrapper (see \Nino\Admin\Admin::handleGet()) - 0 for one it did
		 *	not name, which is no check
		 *
		 *	@return		{Object}									{ bytes, pixels }
		 */
		limits : function() {

			const wrap = typeof dc.getElementById === 'function' ? dc.getElementById('admin-page-wrap') : null;
			const read = function( name ) {
				const value = wrap && wrap.dataset ? parseInt( wrap.dataset[name], 10 ) : NaN;
				return isNaN( value ) === true || value < 1 ? 0 : value;
			};

			return { bytes : read('uploadBytes'), pixels : read('uploadPixels') };
		},

		/**
		 *	What an upload control tells the person before they choose a file:
		 *	the size and the pixels the server will take. Permanent text rather
		 *	than an error to be earned - nobody should have to fail an upload to
		 *	learn the limit
		 *
		 *	@return		{Element|null}						null where the limits are unknown
		 */
		uploadHint : function() {

			const limits = Nino.adminUi.limits();
			if( limits.bytes === 0 || limits.pixels === 0 )
				return null;

			const hint = dc.createElement('p');
			hint.className = 'nino-admin-hint';
			hint.textContent = Nino.adminUi.format( Nino.content.getText('/_admin/common/hint/upload'), Nino.adminUi.megabytes( limits.bytes ), limits.pixels / 1000000 );
			return hint;
		},

		/**
		 *	A size in bytes as the megabytes a sentence names: whole where it
		 *	is whole, one decimal where it is not - the same rounding
		 *	\Nino\Admin\Admin::megabytes() applies on the server
		 *
		 *	@param		{number}	bytes
		 *
		 *	@return		{number}
		 */
		megabytes : function( bytes ) {
			return Math.round( bytes / 104857.6 ) / 10;
		},

		/**
		 *	Check a chosen image against the server's limits before it is sent,
		 *	so a file that cannot work fails at once, in words, instead of after
		 *	an upload of however many megabytes. The server stays the authority
		 *	- this only spares the wait: what it cannot judge (a format the
		 *	browser cannot decode, a limit it was not told) it lets through.
		 *
		 *	The answer has the shape of a failed request's body, so errorText()
		 *	says it exactly as it would say the server's
		 *
		 *	@param		{File}				file
		 *	@param		{Function}		done						Called once with null, or { code, params }
		 *
		 *	@return		void
		 */
		checkImage : function( file, done ) {

			const limits = Nino.adminUi.limits();

			if( limits.bytes > 0 && file.size > limits.bytes ) {
				done( { code : 'image_too_large', params : [ Nino.adminUi.megabytes( limits.bytes ) ] } );
				return;
			}

			if( limits.pixels === 0 || typeof wn.createImageBitmap !== 'function' ) {
				done( null );
				return;
			}

			// The pixels need the picture decoded, which is as much work as
			// the server will do - and the browser answers for a format it
			// cannot read with a rejection that says nothing about the file
			wn.createImageBitmap( file ).then( function( bitmap ) {
				const tooMany = bitmap.width * bitmap.height > limits.pixels;
				if( typeof bitmap.close === 'function' )
					bitmap.close();
				done( tooMany === true ? { code : 'image_too_many_pixels', params : [ limits.pixels / 1000000 ] } : null );
			}, function() {
				done( null );
			} );
		},

		/**
		 *	The state a status line is in, as plain data: which state, and the
		 *	fill key and params of its words. The pure half of status(), so
		 *	the states can be tested without a dom. An error has no key of its
		 *	own - what failed is errorText()'s to say
		 *
		 *	@param		{string}	state					idle, dirty, saving, saved or error
		 *
		 *	@return		{Object}									{ state, key, params }
		 */
		statusModel : function( state ) {

			switch( state ) {
				case 'saving':
					return { state : 'saving', key : '/_admin/common/msg/saving', params : [] };
				case 'dirty':
					return { state : 'dirty', key : '/_admin/common/msg/dirty', params : [] };
				case 'saved':
					// The words of a success are the toast's and the badge of the bar's: the line itself stays empty
					return { state : 'saved', key : '', params : [] };
				case 'error':
					return { state : 'error', key : '', params : [] };
				default:
					return { state : 'idle', key : '', params : [] };
			}
		},

		/**
		 *	A panel's status line: one element that says whether what is on
		 *	screen is saved - "saving", "unsaved changes", or why it failed -
		 *	instead of each panel keeping its own "Saved." that stayed on screen
		 *	long after the next keystroke made it a lie. A save that went well
		 *	leaves the line empty: it is said by the toast (say()) and by the badge
		 *	of the bar (barState()), and a line that kept the time would be one
		 *	more place that goes stale.
		 *
		 *	The state is on the element as data-state, for the stylesheet,
		 *	which gives each one a prefix of its own so the meaning does not
		 *	rest on a colour. It is announced as a status, and an error as an
		 *	alert. Owns no strings: its words are the /_admin/common/msg/*
		 *	fills, and a caller that needs others passes them in labels
		 *
		 *	@param		{Element}		el							The line itself - the panel's own, usually in its action bar
		 *	@param		{Object}		[labels]				Text for a state instead of the fill: { saving, saved, dirty } - 'saved' is the sentence of the toast
		 *
		 *	@return		{Object}										{ element, state, idle( [text] ), saving(), saved(), dirty( [text] ), fail( text ), error( status, response, fallbackKey ), bind( form, isDirty ) }
		 */
		status : function( el, labels ) {

			labels = labels || {};

			el.classList.add('nino-admin-status');

			// Set before the first words, so that screen readers have the live
			// region when they arrive
			el.setAttribute( 'role', 'status' );
			el.setAttribute( 'aria-live', 'polite' );

			let scope = null;
			let marked = null;
			let typed = false;

			const unmark = function() {
				if( marked !== null )
					marked.removeAttribute('aria-invalid');
				marked = null;
			};

			const show = function( model, text ) {

				// A refused field is let go with the state it was refused in
				if( model.state !== 'error' )
					unmark();

				const words = typeof labels[model.state] === 'string' ? labels[model.state] : ( model.key === '' ? '' : Nino.content.getText( model.key ) );

				el.dataset.state = model.state;
				el.setAttribute( 'role', model.state === 'error' ? 'alert' : 'status' );
				el.setAttribute( 'aria-live', model.state === 'error' ? 'assertive' : 'polite' );
				el.textContent = typeof text === 'string' ? text : Nino.adminUi.format( words, ...model.params );
				control.state = model.state;
			};

			const control = {

				element : el,
				state		: 'idle',

				idle	 : function( text ) { show( Nino.adminUi.statusModel('idle'), text ) },
				saving : function() {
					typed = false;
					show( Nino.adminUi.statusModel('saving') );
				},

				/**
				 *	Saved - unless something was typed while the save was on its
				 *	way: that is not in what was sent, and the line says so. The line
				 *	is emptied and the toast says it
				 */
				saved	 : function() {
					if( typed === true ) {
						typed = false;
						control.dirty();
						return;
					}
					show( Nino.adminUi.statusModel('saved'), '' );
					Nino.adminUi.say( typeof labels.saved === 'string' ? labels.saved : Nino.content.getText('/_admin/common/msg/saved') );
				},

				/**
				 *	Unsaved changes - with the panel's own words where it has
				 *	more to say than that (a copy that has not been named yet)
				 */
				dirty	 : function( text ) { show( Nino.adminUi.statusModel('dirty'), text ) },

				/**
				 *	A refusal the panel made itself, before anything was sent:
				 *	the error state with the panel's own words
				 */
				fail	 : function( text ) {
					unmark();
					show( Nino.adminUi.statusModel('error'), text );
				},

				/**
				 *	A failed request: what errorText() says of it, and - when the
				 *	server named the field - that field marked aria-invalid,
				 *	focused, and let go again by the next thing typed into it
				 */
				error : function( status, response, fallbackKey ) {

					unmark();
					show( Nino.adminUi.statusModel('error'), Nino.adminUi.api.errorText( status, response, fallbackKey ) );

					const name = response !== null && typeof response === 'object' && typeof response.field === 'string' ? response.field : '';
					const root = scope !== null ? scope : ( typeof el.closest === 'function' ? el.closest('form, [data-panel]') : null );
					if( name === '' || root === null || /^[A-Za-z0-9_.\/-]+$/.test( name ) === false )
						return;

					const field = root.querySelector( '[data-field="'+ name+ '"], [name="'+ name+ '"]' );
					if( field === null )
						return;

					field.setAttribute( 'aria-invalid', 'true' );
					marked = field;
					if( typeof field.focus === 'function' )
						field.focus();
					field.addEventListener( 'input', unmark, { once : true } );
				},

				/**
				 *	Follow a form: what is typed into it turns a saved (or fresh)
				 *	line into "unsaved changes" - except for a file input, whose
				 *	file is saved by its own upload. isDirty() is the panel's own
				 *	word on it - Elements and Text already know which languages
				 *	they hold changes in (_dirtyLocales) - and is asked after
				 *	every change, so a value typed back to what it was can turn
				 *	the line back. Nothing here keeps a second copy of that state
				 */
				bind : function( form, isDirty ) {

					scope = form;

					const follow = function( ev ) {

						// A picked file is not a change to the record: it is saved on
						// its own, by the upload. A search box is a way to find
						// something, not a value
						if( ev && ev.target && ( ev.target.type === 'file' || ev.target.type === 'search' ) )
							return;

						// The line says "saving" until the answer; what is typed meanwhile
						// is remembered for saved()
						if( control.state === 'saving' ) {
							typed = true;
							return;
						}

						if( typeof isDirty === 'function' && isDirty() === false ) {
							if( control.state === 'dirty' )
								control.idle();
							return;
						}

						// A refusal stays until the next save: it names what to fix, and
						// the first keystroke into the form is usually that fix
						if( control.state === 'saved' || control.state === 'idle' )
							control.dirty();
					};

					form.addEventListener( 'input', follow );
					form.addEventListener( 'change', follow );
				},
			};

			return control;
		},

		/**
		 *	The levels of a panel: its types, its list and its form are elements of
		 *	the pane, ids that start alike (<prefix>-list, <prefix>-form), and the
		 *	panel shows one of them at a time. Nearly every panel wrote the same
		 *	function to hide the others - toggling the shell's admin-hidden class,
		 *	the one every copy and the panel recipe use, which is not the
		 *	design system's own nino-admin-hidden - and several of them wrote the
		 *	level into the url hash as well.
		 *
		 *	Only that: a redraw, a refresh or a dropped working copy that goes
		 *	with showing a level stays with the panel that needs it. Where the
		 *	shell has its router, show() writes the level into the hash when it
		 *	is given the parts; where it has not (the wizard, a test) it leaves
		 *	the address alone. Owns no strings
		 *
		 *	@param		{string}		prefix					The ids' beginning: 'catalog' for catalog-list
		 *	@param		{Array}			names						The levels, in the ids' endings: [ 'list', 'form' ]
		 *
		 *	@return		{Object}										{ show( name, parts ) } - shows the level of that name and hides the others (an id the page does not have is skipped); with parts, an array, it also asks the router to write #<prefix>/<parts>
		 */
		levels : function( prefix, names ) {

			return {
				show : function( name, parts ) {

					names.forEach( function( level ) {
						const el = dc.getElementById( prefix+ '-'+ level );
						if( el !== null )
							el.classList.toggle( 'admin-hidden', level !== name );
					} );

					const router = shell().router;
					if( Array.isArray( parts ) === true && typeof router === 'object' && router !== null )
						router.set( prefix, parts );
				},
			};
		},

		/**
		 *	An accordion: sections one above the other, each a heading with a
		 *	button that opens it. One section is open (the way a screen of
		 *	settings reads: a list of groups, the one in hand spread out) or, with
		 *	options.multiple, as many as the person opens, or - with
		 *	options.collapsible - at most one: a press on the open head closes it, so
		 *	that the head below it can be reached, and none is open then. An accordion stands in
		 *	the body of a section of another - two levels is what the panels use -
		 *	and each is its own: the arrows walk the heads of one accordion and
		 *	never cross into the one inside.
		 *
		 *	The head is a button in a heading (options.level) carrying
		 *	aria-expanded and aria-controls, the body a region labelled by it.
		 *	Where one section is open at a time and the accordion is not collapsible
		 *	its head is aria-disabled - it says it is open and a press changes
		 *	nothing - and stays in the tab order; a head that can be closed is not.
		 *	Enter and Space press it, Up and Down walk the heads, Home and
		 *	End go to the first and the last. A closed body is hidden, not
		 *	removed: what a person entered into a section is still there when it
		 *	opens a second time, and a body given as a function is made when its section
		 *	opens for the first time.
		 *
		 *	The open sections are the address of the panel where it is given
		 *	options.address: the parts of the level the accordion stands in, to
		 *	which the open key follows (the keys joined by a comma where several
		 *	may be open, so a key holds none); with no section open the address is
		 *	the level's own, and follow() on such an address closes every section
		 *	of a collapsible accordion (any other has one open at all times). A press is the person's move - a
		 *	new entry for Back to return to - and set() and follow() move without
		 *	writing, which is what a panel does when the address has changed under
		 *	it. Without the shell's router the address is left alone, as levels()
		 *	does. Owns no strings: the labels, the summary and the badge are the
		 *	caller's
		 *
		 *	@param		{Object}		options					{ id, sections, open, multiple, collapsible, level, address, onToggle }
		 *														- id          the beginning of the ids it makes, one the page has not
		 *														- sections    [ { key, label, summary, badge, help, content } ] - content an Element, or a function that answers one when the section is first opened; summary is read on the closed head, badge a chip beside the label (a state such as "changed"); help the sentence behind a (?) beside the heading of the section, in a row with it and not in it: a button of its own that does not open or close it, one more stop for the Tab key behind the head (the arrow keys walk the heads alone), the sentence described by the head as a field's help is. Without help the head is the button in its heading and nothing more
		 *														- open        the key open at the start, or an array of them
		 *														- multiple    several sections may be open
		 *														- collapsible at most one section is open and a press on it closes it; none may be open, so without open none is at the start. Ignored with multiple
		 *														- level       the heading level of the heads, 3 by default; 4 for an accordion inside a section
		 *														- address     { panel, parts } - the router's panel and the parts before the open key
		 *														- onToggle    called with the open keys and the key that was pressed, after a press
		 *
		 *	@return		{Object}										{ el, get(), set( keys ), follow(), update( key, { summary, badge } ), head( key ), body( key ) }
		 */
		accordion : function( options ) {

			const prefix		= options.id || 'nino-admin-accordion-'+ ( ++serial );
			const multiple	= options.multiple === true;
			const collapsible	= multiple === false && options.collapsible === true;
			const level			= Number.isInteger( options.level ) === true && options.level >= 1 && options.level <= 6 ? options.level : 3;
			const root			= dc.createElement('div');
			root.className	= 'nino-admin-accordion';

			let open = [];
			const parts = [];

			const keys = options.sections.map( function( section ) { return section.key } );

			const known = function( wanted ) {
				const list = ( Array.isArray( wanted ) === true ? wanted : [ wanted ] ).filter( function( key ) { return keys.indexOf( key ) !== -1 } );
				return multiple === true ? list : list.slice( 0, 1 );
			};

			// Where one section is open at a time one is, unless the accordion is collapsible:
			// what was asked for, else the one the accordion started with, else the first - a
			// Back to the level with no section in its address, or a key the accordion has not,
			// leaves no screen of closed heads
			const settle = function( wanted ) {

				const list = known( wanted );

				if( list.length > 0 || multiple === true || collapsible === true )
					return list;

				const first = known( options.open ?? [] );

				return first.length > 0 ? first : keys.slice( 0, 1 );
			};

			const paint = function() {
				parts.forEach( function( part, at ) {

					const on = open.indexOf( part.key ) !== -1;

					part.button.setAttribute( 'aria-expanded', on === true ? 'true' : 'false' );

					if( multiple === false && collapsible === false && on === true )
						part.button.setAttribute( 'aria-disabled', 'true' );
					else
						part.button.removeAttribute('aria-disabled');

					part.chevron.textContent = on === true ? '▾' : '▸';
					part.summary.hidden = on === true;
					part.body.hidden = on === false;
					part.head.classList.toggle( 'is-open', on );

					if( on === true && part.made === false ) {
						part.made = true;
						const content = typeof options.sections[at].content === 'function' ? options.sections[at].content() : options.sections[at].content;
						if( content !== null && content !== undefined )
							part.body.appendChild( content );
					}
				} );
			};

			// The router the shell has, if it has one: the address is the panel's, not the accordion's
			const write = function() {

				const address = options.address;
				const router = shell().router;

				if( typeof address !== 'object' || address === null || typeof router !== 'object' || router === null )
					return;

				const at = address.parts.slice();

				if( open.length > 0 )
					at.push( open.join(',') );

				if( typeof router.go === 'function' )
					router.go( address.panel, at );

				if( typeof router.set === 'function' )
					router.set( address.panel, at );
			};

			const press = function( key ) {

				if( multiple === true )
					open = open.indexOf( key ) === -1 ? open.concat( [ key ] ) : open.filter( function( other ) { return other !== key } );
				else if( open[0] === key && collapsible === false )
					return;
				else if( open[0] === key )
					open = [];
				else
					open = [ key ];

				paint();
				write();

				if( typeof options.onToggle === 'function' )
					options.onToggle( open.slice(), key );
			};

			options.sections.forEach( function( section, at ) {

				const id = prefix+ '-'+ at;

				const heading = dc.createElement( 'h'+ level );
				heading.className = 'nino-admin-accordion-head';

				const button = dc.createElement('button');
				button.type = 'button';
				button.className = 'nino-admin-accordion-button';
				button.id = id+ '-head';
				button.setAttribute( 'aria-controls', id+ '-body' );

				const label = Nino.adminUi.el( 'span', 'nino-admin-accordion-label', section.label );
				const badge = Nino.adminUi.el( 'span', 'nino-admin-badge nino-admin-accordion-badge', section.badge ?? '' );
				badge.dataset.state = 'warn';
				badge.hidden = ( section.badge ?? '' ) === '';

				const summary = Nino.adminUi.el( 'span', 'nino-admin-accordion-summary', section.summary ?? '' );
				const chevron = Nino.adminUi.el( 'span', 'nino-admin-accordion-chevron' );
				chevron.setAttribute( 'aria-hidden', 'true' );

				[ label, badge, summary, chevron ].forEach( function( piece ) { button.appendChild( piece ) } );
				heading.appendChild( button );

				// The (?) of the section: a button of its own after the heading - not in it, so that the name of the heading is its title alone, and
				// not in the button that opens the section, a button in a button being no markup. A press on it does not turn the section over. The
				// sentence is in the document and describes the button of the head
				let row = null;

				if( typeof section.help === 'string' && section.help !== '' ) {
					row = dc.createElement('div');
					row.className = 'nino-admin-accordion-row';
					row.appendChild( heading );
					row.appendChild( Nino.adminUi.fieldHelp( section.help, { field : button, name : section.label } ) );
				}

				const body = dc.createElement('div');
				body.id = id+ '-body';
				body.className = 'nino-admin-accordion-body';
				body.setAttribute( 'role', 'region' );
				body.setAttribute( 'aria-labelledby', id+ '-head' );

				button.addEventListener( 'click', function() { press( section.key ) } );

				button.addEventListener( 'keydown', function( ev ) {

					let next = -1;
					if( ev.key === 'ArrowDown' )	next = at + 1;
					if( ev.key === 'ArrowUp' )		next = at - 1;
					if( ev.key === 'Home' )				next = 0;
					if( ev.key === 'End' )				next = parts.length - 1;

					if( [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].indexOf( ev.key ) === -1 )
						return;

					// The arrows scroll the page otherwise, and a head has no other use for them
					ev.preventDefault();

					if( next >= 0 && next < parts.length )
						parts[next].button.focus();
				} );

				root.appendChild( row ?? heading );
				root.appendChild( body );

				parts.push( { key : section.key, head : heading, button : button, badge : badge, summary : summary, chevron : chevron, body : body, made : false } );
			} );

			open = settle( options.open ?? [] );
			paint();

			const part = function( key ) {
				return parts.find( function( candidate ) { return candidate.key === key } ) ?? null;
			};

			return {

				el : root,

				get : function() {
					return open.slice();
				},

				// Open what the address, or the caller, says: no address written, no callback
				set : function( wanted ) {
					open = settle( wanted ?? [] );
					paint();
				},

				// Take the open sections from the router's current address, where it
				// is the level this accordion stands in
				follow : function() {

					const address = options.address;
					const router = shell().router;

					if( typeof address !== 'object' || address === null || typeof router !== 'object' || router === null || typeof router.current !== 'function' )
						return;

					const current = router.current();
					const own = address.parts;

					if( current.panel !== address.panel || own.some( function( value, at ) { return current.parts[at] !== value } ) === true )
						return;

					const named = current.parts[own.length];
					open = settle( typeof named === 'string' ? named.split(',') : [] );
					paint();
				},

				update : function( key, changes ) {

					const found = part( key );
					if( found === null )
						return;

					if( changes.summary !== undefined )
						found.summary.textContent = changes.summary;

					if( changes.badge !== undefined ) {
						found.badge.textContent = changes.badge;
						found.badge.hidden = changes.badge === '';
					}
				},

				head : function( key ) {
					const found = part( key );
					return found === null ? null : found.button;
				},

				body : function( key ) {
					const found = part( key );
					return found === null ? null : found.body;
				},
			};
		},

		/**
		 *	A choice among a few steps, drawn as one strip: -2 to +2, or named
		 *	steps. A radiogroup of buttons in the pattern's way - one tab stop
		 *	(the chosen step, or the first where none is), the arrows move the
		 *	choice and the focus with it and wrap at the ends, Home and End go
		 *	to the first and the last. The step that is the default says so
		 *	by a mark and by its name ("0 (Default)"), so that the way back is
		 *	visible and a screen reader reads it; the chosen one is filled.
		 *
		 *	The strip does not own the value: it reports a choice made by hand
		 *	through onChange and takes one from outside through set(), which
		 *	reports nothing. A step may be disabled alone, the strip as a whole
		 *	through disable(). A step with an icon (a name of ICONS) shows the icon in
		 *	the middle of the step in place of its word, which stays the step's name
		 *	for a screen reader and its tooltip. Owns no strings, the word for the
		 *	default apart
		 *
		 *	@param		{Object}		options					{ label, labelledby, steps, value, standard, disabled, id, wide, onChange }
		 *														- label       the group's name for a screen reader; or labelledby, the id of what names it
		 *														- steps       the steps, in order: a number or a string (a number is written -2, 0, +2), or { value, label, title, disabled, icon } - icon a name of ICONS, drawn in place of the label; the label is then the name of the step, and its tooltip where the step has no title
		 *														- value       the chosen step's value; none is chosen where it matches no step
		 *														- standard    the value of the step that is the default
		 *														- disabled    the strip cannot be operated
		 *														- wide        the steps share the width, where the strip is otherwise as wide as they are
		 *														- onChange    called with the value and its index after a choice made by hand
		 *
		 *	@return		{Object}										{ el, get(), set( value ), disable( bool ), focus() }
		 */
		steps : function( options ) {

			const written = function( value ) {
				if( typeof value !== 'number' )
					return String( value );
				return value < 0 ? '−'+ Math.abs( value ) : ( value > 0 ? '+'+ value : '0' );
			};

			const steps = options.steps.map( function( step ) {
				return typeof step === 'object' && step !== null
					? { value : step.value, label : step.label ?? written( step.value ), title : step.title, disabled : step.disabled === true, icon : typeof step.icon === 'string' && Object.hasOwn( ICONS, step.icon ) === true ? step.icon : '' }
					: { value : step, label : written( step ), title : undefined, disabled : false, icon : '' };
			} );

			const group = dc.createElement('div');
			group.className = 'nino-admin-steps'+ ( options.wide === true ? ' nino-admin-steps--wide' : '' );
			group.setAttribute( 'role', 'radiogroup' );

			if( options.labelledby )
				group.setAttribute( 'aria-labelledby', options.labelledby );
			else if( options.label )
				group.setAttribute( 'aria-label', options.label );

			if( options.id )
				group.id = options.id;

			let chosen = -1;
			let off = options.disabled === true;

			const buttons = steps.map( function( step, at ) {

				const button = dc.createElement('button');
				button.type = 'button';
				button.className = 'nino-admin-steps-step';
				button.setAttribute( 'role', 'radio' );

				// A picture is the face of the step, centred in it; the word stays as its name for a screen reader and as its tooltip
				if( step.icon !== '' ) {
					const face = Nino.adminUi.el( 'span', 'nino-admin-steps-icon' );
					face.appendChild( Nino.adminUi.icon( step.icon ) );
					face.appendChild( Nino.adminUi.el( 'span', 'nino-admin-sr-only', step.label ) );
					button.appendChild( face );
				}
				else
					button.appendChild( Nino.adminUi.el( 'span', '', step.label ) );

				if( step.title || step.icon !== '' )
					button.title = step.title || step.label;

				if( options.standard !== undefined && step.value === options.standard ) {
					button.dataset.standard = 'true';
					button.appendChild( Nino.adminUi.el( 'span', 'nino-admin-sr-only', ' ('+ Nino.adminUi.text('/_admin/common/label/default')+ ')' ) );
				}

				button.addEventListener( 'click', function() { choose( at, true ) } );
				button.addEventListener( 'keydown', function( ev ) {

					if( [ 'ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End' ].indexOf( ev.key ) === -1 )
						return;

					ev.preventDefault();

					const free = steps.map( function( other, index ) { return other.disabled === true ? -1 : index } ).filter( function( index ) { return index !== -1 } );
					if( free.length === 0 )
						return;

					const here = Math.max( 0, free.indexOf( at ) );
					let next = here;

					if( ev.key === 'ArrowRight' || ev.key === 'ArrowDown' )	next = ( here + 1 ) % free.length;
					if( ev.key === 'ArrowLeft' || ev.key === 'ArrowUp' )		next = ( here - 1 + free.length ) % free.length;
					if( ev.key === 'Home' )																	next = 0;
					if( ev.key === 'End' )																	next = free.length - 1;

					choose( free[next], true );
					buttons[free[next]].focus();
				} );

				group.appendChild( button );

				return button;
			} );

			const paint = function() {

				// The one tab stop is the chosen step - or, where none is or it is disabled, the first that can be chosen
				const stop = chosen !== -1 && steps[chosen].disabled === false ? chosen : steps.findIndex( function( step ) { return step.disabled === false } );

				buttons.forEach( function( button, at ) {
					button.setAttribute( 'aria-checked', at === chosen ? 'true' : 'false' );
					button.classList.toggle( 'is-active', at === chosen );
					button.tabIndex = at === stop ? 0 : -1;
					button.disabled = off === true || steps[at].disabled === true;
				} );

				if( off === true )
					group.setAttribute( 'aria-disabled', 'true' );
				else
					group.removeAttribute('aria-disabled');
			};

			const choose = function( at, byHand ) {

				if( off === true || steps[at].disabled === true )
					return;

				const changed = chosen !== at;
				chosen = at;
				paint();

				if( byHand === true && changed === true && typeof options.onChange === 'function' )
					options.onChange( steps[at].value, at );
			};

			chosen = steps.findIndex( function( step ) { return step.value === options.value } );
			paint();

			return {

				el : group,

				get : function() {
					return chosen === -1 ? null : steps[chosen].value;
				},

				set : function( value ) {
					chosen = steps.findIndex( function( step ) { return step.value === value } );
					paint();
				},

				disable : function( on ) {
					off = on === true;
					paint();
				},

				focus : function() {
					const stop = buttons.find( function( button ) { return button.tabIndex === 0 } );
					if( stop !== undefined )
						stop.focus();
				},
			};
		},

		/**
		 *	The tools of a preview: the choice of the page it shows, the width it is laid out at and "Reload" - what Themes and the Builder
		 *	each built for themselves. One group, for the context bar (contextBar( { tools } ), which folds it into a menu where it is narrow)
		 *	or the place beside a preview. The widths are a strip of steps, so that the one that is chosen is the one that is filled; the
		 *	choice of a page is a select named "Shows", drawn only where there is a page to choose from; Reload is an icon button.
		 *	The group owns no state: it reports what was done by hand through onPage, onWidth and onReload, and takes a value from outside
		 *	through setPage() and setWidth(), which report nothing
		 *
		 *	@param		{Object}		options					{ pages, page, widths, width, note, reload, text, onPage, onWidth, onReload }
		 *														- pages       the pages to show, [ { value, label } ]; none, or an empty list, draws no choice
		 *														- page        the value of the page that is shown
		 *														- widths      the names of the widths the strip offers, 'mobile', 'tablet' and 'desktop' by default; the words are the fills /_admin/common/preview/<name>, or text[name]
		 *														- width       the name of the width that is chosen; none is chosen where it is none of the names
		 *														- icons       true draws the widths as pictures - a phone, a tablet, a monitor for mobile, tablet and desktop - in place of their words, which stay the name of each step for a screen reader and its tooltip; a width with another name keeps its word. Default false
		 *														- note        a quiet line beside the choice of the page - text, or an element -, what the page shows where the choice does not say it ("Automatic")
		 *														- reload      false leaves the button out, where the view has one of its own (a split() that is no surface has it in its bar)
		 *														- text        { shows, width, reload } to say the three words differently
		 *														- onPage      called with the value after a page was chosen by hand
		 *														- onWidth     called with the name after a width was chosen by hand
		 *														- onReload    called after a press on Reload
		 *
		 *	@return		{Object}										{ el, page(), width(), setPage( value ), setWidth( name ), setNote( text ), select, widths, reload, note } - select is null where there is no choice of a page, reload where the button is left out, note where there is no line
		 */
		previewTools : function( options ) {

			const text = options.text || {};
			const word = function( key ) { return text[key] ?? Nino.adminUi.text( '/_admin/common/preview/'+ key ) };
			const pages = Array.isArray( options.pages ) === true ? options.pages : [];
			const names = Array.isArray( options.widths ) === true ? options.widths : [ 'mobile', 'tablet', 'desktop' ];

			const root = Nino.adminUi.el( 'div', 'nino-admin-previewtools' );
			let select = null;

			if( pages.length > 0 ) {

				const label = Nino.adminUi.el( 'label', 'nino-admin-previewtools-page' );

				select = Nino.adminUi.el( 'select', 'nino-admin-input' );
				pages.forEach( function( page ) {
					const option = Nino.adminUi.el( 'option', '', page.label );
					option.value = page.value;
					select.appendChild( option );
				} );
				select.value = options.page ?? pages[0].value;
				select.addEventListener( 'change', function() {
					if( typeof options.onPage === 'function' )
						options.onPage( select.value );
				} );

				label.appendChild( Nino.adminUi.el( 'span', '', word('shows') ) );
				label.appendChild( select );
				root.appendChild( label );
			}

			let note = null;

			if( options.note !== undefined && options.note !== null ) {
				note = Nino.adminUi.el( 'span', 'nino-admin-previewtools-note' );
				note.appendChild( isElement( options.note ) === true ? options.note : dc.createTextNode( String( options.note ) ) );
				root.appendChild( note );
			}

			const group = Nino.adminUi.el( 'div', 'nino-admin-previewtools-width' );
			const caption = Nino.adminUi.el( 'span', '', word('width') );

			caption.id = 'nino-admin-previewtools-width-'+ ( ++serial );

			const widths = Nino.adminUi.steps( {
				labelledby	: caption.id,
				steps				: names.map( function( name ) {

					const device = { mobile : 'smartphone', tablet : 'tablet', desktop : 'monitor' };

					return { value : name, label : text[name] ?? ( Nino.content.getText( '/_admin/common/preview/'+ name ) || name ), icon : options.icons === true && Object.hasOwn( device, name ) === true ? device[name] : undefined };
				} ),
				value				: options.width,
				onChange		: function( name ) {
					if( typeof options.onWidth === 'function' )
						options.onWidth( name );
				},
			} );

			group.appendChild( caption );
			group.appendChild( widths.el );
			root.appendChild( group );

			const reload = options.reload === false ? null : Nino.adminUi.iconButton( 'refresh-cw', text.reload ?? Nino.adminUi.text('/_admin/common/split/reload'), function() {
				if( typeof options.onReload === 'function' )
					options.onReload();
			}, { className : 'nino-admin-previewtools-reload' } );

			if( reload !== null )
				root.appendChild( reload );

			return {
				el : root,
				select : select,
				widths : widths,
				reload : reload,
				note : note,
				setNote : function( value ) { if( note !== null ) note.textContent = String( value ) },
				page : function() { return select === null ? ( options.page ?? '' ) : select.value },
				width : function() { return widths.get() },
				setPage : function( value ) { if( select !== null ) select.value = value },
				setWidth : function( name ) { widths.set( name ) },
			};
		},

		/**
		 *	The cards of things whose look is the content - a theme, a block: a preview picture, the name and its badges in ONE line that does not
		 *	wrap, and a line of small buttons under it. The cards of a list are as high as one another, the buttons at the foot; a name that is too
		 *	long is cut and whole in its title. No description stands on a card - the picture says it. Owns no strings
		 *
		 *	@param		{Array}			items						[ { key, title, picture, badges, actions } ]
		 *														key the card's identity, written on it as data-key; picture an element, drawn as it comes (scaleFrame() makes one
		 *														from a page); badges a list of short words, each a string or { text, state }; actions the small buttons of the line, made by
		 *														button( ..., { small : true } ), at most three, with short words: they share the width of the card
		 *	@param		{Object}		[options]				{ label } - the name of the list for a screen reader
		 *
		 *	@return		{Element}										<ul class="nino-admin-cards">, one <li><article class="nino-admin-card"> for each item
		 */
		cards : function( items, options ) {

			options = options || {};

			const list = Nino.adminUi.el( 'ul', 'nino-admin-cards' );

			if( typeof options.label === 'string' && options.label !== '' )
				list.setAttribute( 'aria-label', options.label );

			items.forEach( function( item ) {

				const given = Array.isArray( item.actions ) === true ? item.actions : [];

				if( given.length > 3 )
					throw new RangeError( 'Nino.adminUi.cards(): a card has at most three buttons, '+ given.length+ ' were given' );

				const li = Nino.adminUi.el( 'li', 'nino-admin-cards-item' );
				const card = Nino.adminUi.el( 'article', 'nino-admin-card nino-admin-cards-card' );

				if( item.key !== undefined )
					card.dataset.key = String( item.key );

				if( isElement( item.picture ) === true ) {
					const picture = Nino.adminUi.el( 'div', 'nino-admin-cards-picture' );
					picture.appendChild( item.picture );
					card.appendChild( picture );
				}

				const head = Nino.adminUi.el( 'div', 'nino-admin-cards-head' );
				const title = Nino.adminUi.el( 'h3', 'nino-admin-cards-title', item.title );

				// The whole name is the title of the heading: the line cuts a long one
				title.title = item.title;
				head.appendChild( title );

				( Array.isArray( item.badges ) === true ? item.badges : [] ).forEach( function( badge ) {

					const given = typeof badge === 'object' && badge !== null ? badge : { text : String( badge ) };
					const mark = Nino.adminUi.el( 'span', 'nino-admin-badge', given.text );

					if( given.state !== undefined )
						mark.dataset.state = given.state;

					head.appendChild( mark );
				} );

				card.appendChild( head );

				if( given.length > 0 ) {

					const actions = Nino.adminUi.el( 'div', 'nino-admin-cards-actions' );

					// A word that is cut is whole in the title of its button
					given.forEach( function( button ) {

						if( button.title === '' )
							button.title = button.textContent;

						actions.appendChild( button );
					} );
					card.appendChild( actions );
				}

				li.appendChild( card );
				list.appendChild( li );
			} );

			return list;
		},

		/**
		 *	A split view: what a person sets on one side, the result on the
		 *	other - a column of controls and a preview beside it. Where the
		 *	view is too narrow for both (the container's, not the window's: the
		 *	pane is narrower than the screen by the rail) a switch of two
		 *	buttons, "Settings" and "Preview", shows one and keeps the other
		 *	in the page, so that neither is rebuilt by a switch and what was
		 *	typed is still there. Where there is room the switch is not drawn
		 *	and both stand side by side.
		 *
		 *	The preview is a page in an iframe (options.preview.src) or an
		 *	element the caller fills (an Element, or { slot }: a preview that is
		 *	a drawing, not a page). reload() loads the frame once more, or another
		 *	address into it, and touches nothing else (the frame says aria-busy until
		 *	it has loaded): the controls are not
		 *	redrawn, which is what a preview that has to be asked for a second time after
		 *	every change needs. Another address replaces the one the frame shows in the
		 *	history of the window - a preview that is reloaded after every change would
		 *	otherwise put a step into it for each, and the Back key would walk through
		 *	them before it came to the address of the panel. Only the project's own
		 *	origin is loaded into the frame - a relative address or one on this host; //host, \\host, other
		 *	schemes and other hosts are not: an address a person or a file could
		 *	have written is not allowed to run in the workbench's origin.
		 *	options.sandbox, where given, is the frame's sandbox.
		 *
		 *	With preview.width, or width( px ), the page is laid out as a browser that
		 *	wide would lay it out and scaled down as a whole into the room the pane has
		 *	(see scaleFrame()), so that a page of 1280px is seen on a pane of 600
		 *	with everything in proportion. With none the frame is as wide as the pane
		 *
		 *	With fill the view is a work surface: it fills the height between the context bar and the bar at
		 *	the foot of the page, which the page does not scroll beyond - each column has a scroll bar of its own, and the
		 *	preview's is the one of its page. It stands directly under the context bar, has no frame and no gap, and has no bar
		 *	over the preview: the tools of the preview stand in the context bar (previewTools()). Without it the view is a
		 *	block in the page
		 *
		 *	With aside a third column stands at the left edge, which folds: open it is a column of a fixed width on the
		 *	ground of the preview, folded a strip of 48 px that holds what the caller gives for it (rail - icons, say). The
		 *	button at the top of it stays in both, shows the list icon and the double arrow of the rail's button, says
		 *	aria-expanded and is named by the caller; whether the column is folded is held for the session for each view
		 *	with an id. Where the view is too narrow for the columns the aside stands over the settings, in the same view
		 *
		 *	@param		{Object}		options					{ id, settings, preview, view, sandbox, previewTitle, text, fill, aside, onView, onReload, onLoad }
		 *														- settings    the controls; without them there is no column for them - the view is the side column and the preview (the switch is then named by aside.label), or the preview alone
		 *														- fill        true for a work surface
		 *														- settingsWidth  'wide' gives the settings column 480 px where the view has the room - half of the view at the most, and only from 46 rem of view on, where half is the usual width; with or without fill, and a side column keeps its layout; any other value is the usual width
		 *														- aside       { content, label, collapsed, rail } - content an element, label the name of the button (it says what the column holds: "Outline"), collapsed whether the column starts folded where the session holds nothing, rail an element drawn in the folded strip
		 *														- settings    the controls, an Element (more can be put into split.settings later)
		 *														- preview     { src, title, width } for a page, an Element for a preview the caller draws; width is the layout width of the page in px, 1 to 10000
		 *														- view        'settings' (the default) or 'preview', which one the narrow view starts on
		 *														- sandbox     the iframe's sandbox attribute, as a string
		 *														- tools       an Element for the bar over the preview, beside its title (split.tools is the place, where it is made later)
		 *														- text        { settings, preview, reload, view } to say the four words differently
		 *														- onView      called with the view after a switch
		 *														- onReload    called when the preview is asked to load once more, by the button or by reload()
		 *														- onLoad      called with the frame when the page in it has loaded
		 *
		 *	@return		{Object}										{ el, settings (null without settings), preview, frame, tools, aside, view(), show( view ), reload( [src] ), width( [px] ), words( { settings, preview } ) } - aside is { el, body, rail, toggle, collapsed(), collapse( on ) } or null; width( px ) lays the page out at px and scales it into the pane, width( null ) takes it back to the pane's own width, width() answers the one that is set (null for none); a number that is not 1 to 10000 is none. With no preview given the view is the settings alone: no switch, no bar, and preview, frame and tools are null. tools is null for a work surface as well
		 */
		split : function( options ) {

			const prefix	= options.id || 'nino-admin-split-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key ) { return text[key] ?? Nino.adminUi.text( '/_admin/common/split/'+ key ) };

			const root = dc.createElement('div');
			const fill = options.fill === true;

			root.className = 'nino-admin-split'+ ( fill === true ? ' nino-admin-split--fill' : '' )+ ( options.settingsWidth === 'wide' ? ' nino-admin-split--settings-wide' : '' );
			root.dataset.view = options.view === 'preview' ? 'preview' : 'settings';

			// Without settings there is no column for them: the view is the side column and the preview, or the preview alone
			const bare = options.settings === undefined || options.settings === null;
			const sided = typeof options.aside === 'object' && options.aside !== null;
			const single = bare === true && sided === false;

			if( bare === true )
				root.dataset.bare = 'true';

			if( single === true )
				root.dataset.view = 'preview';

			const settings = dc.createElement('div');
			settings.className = 'nino-admin-split-settings';
			settings.id = prefix+ '-settings';

			if( options.settings )
				settings.appendChild( options.settings );

			const pane = dc.createElement('div');
			pane.className = 'nino-admin-split-preview';
			pane.id = prefix+ '-preview';

			const bar = dc.createElement('div');
			bar.className = 'nino-admin-split-bar';

			// The place in the bar for what the panel wants beside the title: the widths of the preview, a line of advice
			const tools = dc.createElement('div');
			tools.className = 'nino-admin-split-tools';

			if( options.tools )
				tools.appendChild( options.tools );

			const preview = options.preview;
			// An element is a slot even where it has a src of its own - an image, a frame the caller made
			const isPage = typeof preview === 'object' && preview !== null && preview.nodeType !== 1 && typeof preview.src === 'string';
			let frame = null;
			let port = null;
			let layout = null;

			// The preview is a page of the project itself, so only the project's own origin is
			// loaded into the frame: a relative address, or an absolute one on this host. What
			// the browser takes for a scheme ignores the tabs, the line breaks and the other
			// control characters in it (java<TAB>script: runs) and takes a backslash for a
			// slash (/\evil is //evil), so both are read the way it reads them
			const allowed = function( src ) {

				const plain = String( src ).replace( /[\u0000-\u0020]/g, '' ).replace( /\\/g, '/' );

				if( /^[a-z][a-z0-9+.-]*:/i.test( plain ) === false )
					return plain.indexOf('//') !== 0;

				// The origin as the browser reads it: another scheme is another origin, and an
				// address it cannot read is none of ours
				try {
					return /^https?:\/\//i.test( plain ) === true && new URL( plain, wn.location.href ).origin === wn.location.origin;
				} catch( e ) {
					return false;
				}
			};

			// The address the panel asked the frame to show last: the page that is loaded once more
			let shown = '';

			const reload = function( src ) {

				if( frame !== null ) {

					const next = typeof src === 'string' ? src : shown;

					// A frame that was refused its first address has nothing to load once more
					if( next !== '' && allowed( next ) === true ) {

						// Set to the address it shows, an address with a # is not loaded: the browser
						// takes it for a move within the page, so the frame reloads itself - when it
						// shows exactly this page. After a click in the preview it shows another, and
						// one of another origin cannot be asked: either way the panel's address is
						// set. aria-busy ends with the next load
						let same = false;

						try {
							same = frame.contentWindow.location.href === new URL( next, wn.location.href ).href;
						} catch( e ) {}

						frame.setAttribute( 'aria-busy', 'true' );
						shown = next;

						if( same === true )
							frame.contentWindow.location.reload();
						else {

							// The address takes the place of the one the frame shows: a src
							// set to another address is a step of its own in the history of the window, one for
							// every change of the panel. A frame that cannot be asked (another
							// origin, none yet) is given the attribute
							try {
								frame.contentWindow.location.replace( new URL( next, wn.location.href ).href );
							} catch( e ) {
								frame.setAttribute( 'src', next );
							}
						}
					}
				}

				if( typeof options.onReload === 'function' )
					options.onReload();
			};

			const title = Nino.adminUi.el( 'span', 'nino-admin-split-title', word('preview') );
			const refresh = Nino.adminUi.button( '⟳', function() { reload() }, { className : 'nino-admin-btn--small', name : word('reload') } );

			bar.appendChild( title );
			bar.appendChild( tools );
			bar.appendChild( refresh );

			// A work surface has no bar over the preview: the tools stand in the context bar
			if( fill === false )
				pane.appendChild( bar );

			if( isPage === true ) {

				frame = dc.createElement('iframe');
				frame.className = 'nino-admin-split-frame';
				frame.title = options.previewTitle ?? preview.title ?? word('preview');

				if( typeof options.sandbox === 'string' )
					frame.setAttribute( 'sandbox', options.sandbox );

				frame.addEventListener( 'load', function() {
					frame.removeAttribute('aria-busy');
					if( typeof options.onLoad === 'function' )
						options.onLoad( frame );
				} );

				// The frame stands in a box of its own, which it is scaled inside where the page
				// has a layout width: moving it there later would reload it
				port = dc.createElement('div');
				port.className = 'nino-admin-split-port';
				port.appendChild( frame );
				pane.appendChild( port );

				if( allowed( preview.src ) === true ) {
					frame.setAttribute( 'aria-busy', 'true' );
					frame.setAttribute( 'src', preview.src );
					shown = preview.src;
				}

			} else if( typeof preview === 'object' && preview !== null ) {

				const slot = preview.nodeType === 1 ? preview : preview.slot;
				if( typeof slot === 'object' && slot !== null )
					pane.appendChild( slot );
			}

			const switchBar = dc.createElement('div');
			switchBar.className = 'nino-admin-steps nino-admin-steps--wide nino-admin-split-switch';
			switchBar.setAttribute( 'role', 'group' );
			switchBar.setAttribute( 'aria-label', word('view') );

			const buttons = {
				// Where the side column stands in for the settings the switch is named by it: the caller says what it holds
				settings	: Nino.adminUi.button( bare === true && String( options.aside?.label ?? '' ) !== '' ? String( options.aside.label ) : word('settings'), null, { className : 'nino-admin-steps-step' } ),
				preview		: Nino.adminUi.button( word('preview'), null, { className : 'nino-admin-steps-step' } ),
			};

			switchBar.appendChild( buttons.settings );
			switchBar.appendChild( buttons.preview );

			buttons.settings.setAttribute( 'aria-controls', bare === true ? prefix+ '-aside' : settings.id );
			buttons.preview.setAttribute( 'aria-controls', pane.id );

			const paint = Nino.adminUi.buttonRow( buttons, root.dataset.view, function( view ) {
				root.dataset.view = view;
				if( typeof options.onView === 'function' )
					options.onView( view );
			} );

			// The grid is a box of its own inside the root: the root is the container the
			// narrow rules ask about, and a container's own box is not what they style
			const grid = dc.createElement('div');
			grid.className = 'nino-admin-split-grid';

			// The side column: a button that stays and what it holds, open, or what stands for it, folded
			let aside = null;

			if( typeof options.aside === 'object' && options.aside !== null ) {

				const given = options.aside;
				const column = dc.createElement('div');
				const body = dc.createElement('div');
				const rail = dc.createElement('div');
				const toggle = Nino.adminUi.button( '', null, { className : 'nino-admin-aside-toggle', name : String( given.label ?? '' ) } );
				const held = typeof options.id === 'string' && options.id !== '' ? options.id : null;

				column.className = 'nino-admin-split-aside';
				column.id = prefix+ '-aside';
				body.className = 'nino-admin-split-aside-body';
				body.id = prefix+ '-aside-body';
				rail.className = 'nino-admin-split-aside-rail';

				if( isElement( given.content ) === true )
					body.appendChild( given.content );

				if( isElement( given.rail ) === true )
					rail.appendChild( given.rail );

				toggle.appendChild( Nino.adminUi.icon('list') );
				toggle.appendChild( Nino.adminUi.icon('chevrons-left') );
				toggle.setAttribute( 'aria-controls', body.id );

				const stored = function() {

					if( held === null )
						return undefined;

					if( Object.hasOwn( ASIDE_FOLDED, held ) === true )
						return ASIDE_FOLDED[held];

					try {
						const value = wn.sessionStorage.getItem( ASIDE_KEY+ held );
						return value === null ? undefined : value === 'folded';
					} catch( e ) {
						return undefined;
					}
				};

				let folded = stored() ?? given.collapsed === true;

				const paint = function() {

					root.dataset.aside = folded === true ? 'folded' : 'open';
					toggle.setAttribute( 'aria-expanded', String( folded === false ) );
					body.hidden = folded;
					rail.hidden = folded === false;
				};

				const set = function( on ) {

					folded = on === true;
					paint();

					if( held !== null ) {

						ASIDE_FOLDED[held] = folded;

						try {
							wn.sessionStorage.setItem( ASIDE_KEY+ held, folded === true ? 'folded' : 'open' );
						} catch( e ) {}
					}
				};

				toggle.addEventListener( 'click', function() { set( folded === false ) } );

				const head = dc.createElement('div');
				head.className = 'nino-admin-split-aside-head';
				head.appendChild( toggle );

				column.appendChild( head );
				column.appendChild( body );
				column.appendChild( rail );
				grid.appendChild( column );
				paint();

				aside = { el : column, body : body, rail : rail, toggle : toggle, collapsed : function() { return folded }, collapse : set };
			}

			if( bare === false )
				grid.appendChild( settings );

			grid.appendChild( pane );

			// The page at a layout width, scaled into the pane; none lets the frame be as wide as the pane
			const width = function( px ) {

				if( arguments.length === 0 || frame === null )
					return layout;

				layout = typeof px === 'number' && Number.isFinite( px ) === true && px >= 1 && px <= 10000 ? Math.round( px ) : null;

				port.classList.toggle( 'nino-admin-split-port--scaled', layout !== null );

				if( layout !== null )
					Nino.adminUi.scaleFrame( frame, port, layout );
				else {

					if( port._ninoScaleObserver )
						port._ninoScaleObserver.disconnect();
					port._ninoScaleObserver = null;

					[ 'width', 'height', 'transform', 'left', 'top' ].forEach( function( name ) { frame.style[name] = '' } );
				}

				return layout;
			};

			if( isPage === true && preview.width !== undefined )
				width( preview.width );

			// With nothing to preview there is nothing to switch to, to reload or to name
			const solo = isPage === false && ( typeof preview !== 'object' || preview === null );

			if( solo === true ) {
				root.className += ' nino-admin-split--solo';
				root.dataset.view = 'settings';
				grid.removeChild( pane );
			} else if( single === false ) {
				root.appendChild( switchBar );
			}

			root.appendChild( grid );

			// A work surface does not scroll the page: the pane that holds the one that is on the screen says so (--surface), and the stylesheet takes the
			// padding and the scroll from it. Which one is on the screen is told by the size of the surface - a level that is hidden is none - so the mark
			// follows the levels of a panel that are shown and hidden, and a surface that leaves the page
			if( fill === true && typeof wn.ResizeObserver === 'function' ) {

				let marked = null;
				let seen = false;
				let observer = null;

				const release = function() {

					if( marked === null )
						return;

					marked._ninoSurfaces.delete( root );

					if( marked._ninoSurfaces.size === 0 )
						marked.classList.remove('nino-admin-pane--surface');

					marked = null;
				};

				observer = new wn.ResizeObserver( function() {

					// A view that was in the page and is gone - drawn again by its panel - is not watched any longer
					if( root.isConnected === true )
						seen = true;
					else if( seen === true ) {
						release();
						observer.disconnect();
						return;
					}

					const pane = root.isConnected === true && root.getClientRects().length > 0 ? root.closest('.nino-admin-pane') : null;

					if( pane === marked )
						return;

					release();

					if( pane !== null ) {
						pane._ninoSurfaces = pane._ninoSurfaces ?? new Set();
						pane._ninoSurfaces.add( root );
						pane.classList.add('nino-admin-pane--surface');
						marked = pane;
					}
				} );
				observer.observe( root );
			}

			return {

				el : root,
				settings : bare === true ? null : settings,
				preview : solo === true ? null : pane,
				frame : frame,
				tools : solo === true || fill === true ? null : tools,
				aside : aside,

				// The words of the switch and of the bar, said after the build - the frame is not touched
				words : function( given ) {

					if( given.settings !== undefined )
						buttons.settings.textContent = given.settings;

					if( given.preview !== undefined ) {
						buttons.preview.textContent = given.preview;
						title.textContent = given.preview;
					}
				},

				view : function() {
					return root.dataset.view;
				},

				show : function( view ) {

					if( solo === true || single === true )
						return;

					root.dataset.view = view === 'preview' ? 'preview' : 'settings';
					paint( root.dataset.view );
				},

				reload : reload,
				width : width,
			};
		},

		/**
		 *	The loop that keeps a preview in step with a draft that is not saved: the draft is sent once the hand has rested,
		 *	one request at a time - what changes while one is out is sent in the next, once -, the token is asked for again
		 *	before it runs out, and the frame of a split view (split()) is loaded with the page when an answer holds. A failure
		 *	leaves the last picture where it is and is told to the caller; the draft is the person's, a failed request takes nothing
		 *	of it. What the draft is and how it is sent is the caller's: send() does it, with the request of its own panel.
		 *
		 *	The answer of send() is { token, query, ttl } - the token, the query that makes a request for the page ask for it
		 *	(\Nino\Preview::QUERY and the token) and how many seconds it holds. The token goes to the next call of send(), so that
		 *	the server renews it instead of making a new one; it is counted from the answer on the clock of this page, so that a
		 *	clock that is off cannot ask for ever. Owns no strings: what an error or a renewal says is the caller's
		 *
		 *	@param		{Object}		options					{ split, send, address, widths, mark, quiet, grace, onError, onUnavailable, onRenew, onUpdate }
		 *														- split         a split() with a page: reload( src ) and width( px ) are what is called
		 *														- mark          a word (lower case letters, digits, hyphens) the document in the frame is marked with - data-nino-preview="<word>" on its <html> -, written by the kernel from the first byte of a page that asks for the draft (the query of the request says it, and only a request that holds a draft is believed) and by the script after every page the frame loads, so that a page can draw itself differently for the workbench and for nobody else: 'loose' lets the sticky header of Basis scroll away with the page. Nothing else is written in the page
		 *														- send          send( token, done ) asks the server for the draft; done( status, response ) is its answer
		 *														- address       address( query ) is the address of the page the frame shows, with the query that asks for the draft
		 *														- widths        { name : px } the widths of the preview, mobile, tablet and desktop by default
		 *														- quiet         how long the hand rests before the draft is sent, in milliseconds, 150
		 *														- grace         how long before a token runs out - in milliseconds - a new one is asked for, 5000
		 *														- onError       called with ( status, response ) after a request that failed
		 *														- onUnavailable called after an answer that is no draft: the last picture stays
		 *														- onRenew       called when the token had run out and the draft is asked for again
		 *														- onUpdate      called after an answer that held, the frame loaded
		 *
		 *	@return		{Object}										{ schedule( [quiet] ), flush( [then] ), reload(), width( name ), token(), stop() } - flush( then ) calls then( true ) after the last request of a run that held and then( false ) where one failed
		 */
		previewFrame : function( options ) {

			const quiet		= Number.isInteger( options.quiet ) === true && options.quiet >= 0 ? options.quiet : 150;
			const grace		= Number.isInteger( options.grace ) === true && options.grace >= 0 ? options.grace : 5000;
			const widths	= options.widths ?? { mobile : 390, tablet : 768, desktop : 1280 };
			const tell		= function( name, ...args ) {
				if( typeof options[name] === 'function' )
					options[name]( ...args );
			};

			const mark = options.mark === undefined ? '' : String( options.mark );

			if( mark !== '' && /^[a-z][a-z0-9-]{0,31}$/.test( mark ) === false )
				throw new TypeError( 'Nino.adminUi.previewFrame(): "'+ mark+ '" is no word to mark a page with' );

			// A page of another origin or an error page has no document to mark: it is passed over, as frameMarks() does
			const marked = function() {

				try {
					options.split.frame.contentDocument.documentElement.setAttribute( 'data-nino-preview', mark );
				} catch( e ) {}
			};

			if( mark !== '' && options.split.frame !== null && options.split.frame !== undefined ) {
				options.split.frame.addEventListener( 'load', marked );
				marked();
			}

			let token = '';
			let query = '';
			let until = 0;
			let timer = null;
			let inFlight = false;
			let again = false;
			let stopped = false;
			let waiting = [];
			let width = '';

			const finish = function( ok ) {
				const calls = waiting;
				waiting = [];
				calls.forEach( function( call ) { call( ok ) } );
			};

			const load = function() {

				if( stopped === true || query === '' )
					return;

				// A token that has run out draws the page as the website has it, and says nothing: the draft is asked for again, which gives a new one
				if( until > 0 && Date.now() >= until ) {
					tell('onRenew');
					flush();
					return;
				}

				options.split.reload( options.address( mark === '' ? query : query+ '&nino-preview-mark='+ mark ) );
			};

			const flush = function( then ) {

				if( typeof then === 'function' )
					waiting.push( then );

				// A wait that is still out is this send: it is taken back, so that a change and an at once make one request
				wn.clearTimeout( timer );

				if( stopped === true ) {
					finish( false );
					return;
				}

				if( inFlight === true ) {
					again = true;
					return;
				}

				inFlight = true;
				again = false;

				options.send( token, function( status, response ) {

					inFlight = false;

					if( stopped === true ) {
						finish( false );
						return;
					}

					if( status !== 200 ) {
						again = false;
						tell( 'onError', status, response );
						finish( false );
						return;
					}

					// An answer that is no draft is no page to load: the last picture stays
					if( response === null || typeof response !== 'object' || typeof response.token !== 'string' || typeof response.query !== 'string' || typeof response.ttl !== 'number' ) {
						again = false;
						tell('onUnavailable');
						finish( false );
						return;
					}

					token = response.token;
					query = response.query;
					until = Date.now() + response.ttl * 1000 - grace;

					if( again === true ) {
						flush();
						return;
					}

					load();
					tell( 'onUpdate', response );
					finish( true );
				} );
			};

			return {

				// The hand has moved: the draft is sent when it rests - longer where the hand types a text
				schedule : function( rest ) {
					wn.clearTimeout( timer );
					timer = wn.setTimeout( function() { flush() }, rest ?? quiet );
				},

				flush : flush,
				reload : load,
				token : function() { return token },

				// The page is laid out at the width of that name and scaled into the pane; a name that is none gives the pane its own width back
				width : function( name ) {
					width = Object.prototype.hasOwnProperty.call( widths, name ) === true ? name : '';
					return options.split.width( width === '' ? null : widths[width] );
				},

				// The panel is left: what is out is not answered, nothing is sent
				stop : function() {
					stopped = true;
					wn.clearTimeout( timer );
				},

				// The marks of the page in the frame, which come back with every page the frame loads: see frameMarks()
				marks : function( marks ) {
					return Nino.adminUi.frameMarks( Object.assign( { frame : options.split.frame }, marks ) );
				},
			};
		},

		/**
		 *	The page in a frame, marked from the workbench and marking back: the elements a key stands for have a box of the accent colour round
		 *	them - every one of them, where one thing is drawn more than once - and the pointer over an element of the page is told with the key
		 *	of what it is over, so that the preview takes part in hoverSync() as the tree does. What a key stands for is the caller's: elements( key )
		 *	answers the elements of the page that are that key, keyAt( element ) the key of the thing an element is part of, or null. The boxes are
		 *	drawn in a shadow root of an element appended to the page, so that nothing of the page's style reaches them, they take no pointer
		 *	and are hidden from a screen reader, and are drawn again where the page is laid out again or scrolls - they stand fixed in its viewport, where the elements are, so a header that sticks keeps its box -, all the measures read before the first box is written; a page of another origin or an error page is no page to mark and is passed over in silence; the frame is read where it has a page
		 *	and after each one it loads, so the marks outlive a reload. Nothing else is written in the page. The colour is the workbench's: the
		 *	tokens --editor-blue and --editor-blue-soft, read when the page is marked. Owns no strings
		 *
		 *	@param		{Object}		options					{ frame, elements, keyAt, onHover }
		 *														- frame       the iframe element
		 *														- elements    elements( key ) answers the elements of the page that stand for the key
		 *														- keyAt       keyAt( element ) answers the key of what the element is part of, or null
		 *														- onHover     called with the key when the pointer comes over a thing of the page, with null when it leaves them; told once for each change
		 *														- hover       true to show the thing under the pointer as well, quietly: the innermost element of the page that stands for the key of what the pointer is over has a frame of
		 *																			one pixel on exactly its border box, drawn in two rings - the ground of the workbench inside, its ink outside, so that it is seen on a light page and on a dark one - that goes
		 *																			when the pointer leaves the page or comes over nothing, and is not drawn where that element is marked already (a marked thing keeps its solid box). The frame
		 *																			takes no pointer and is no pencil and no "+". Without it the page is marked as it always was; the thing under the pointer is told by onHover either way
		 *
		 *	@return		{Object}										{ highlight( keys, [options] ), refresh(), stop() } - highlight() takes a key, a list of them or null, marks those and no others and tells nobody; with { scroll : true } the page rolls to the first of them; refresh() draws the boxes again where the page was laid out again; stop() takes the marks and the listeners away
		 */
		frameMarks : function( options ) {

			const frame = options.frame;
			let keys = [];
			let layer = null;
			let host = null;
			let doc = null;
			let reported = null;
			let observer = null;
			// The element the pointer is over, innermost of those that stand for the key it is over: with the option hover it has a quiet frame
			let under = null;
			// What is listened to in the page that is there: the page goes when the frame loads another, and these with it
			const listeners = [];

			const say = function( key ) {

				if( key === reported )
					return;

				reported = key;

				if( typeof options.onHover === 'function' )
					options.onHover( key );
			};

			const paint = function( scroll ) {

				if( layer === null )
					return;

				const win = frame.contentWindow;
				const rects = [];
				let quiet = null;

				// Every measure is read before anything is written: reading after a write makes the page lay itself out for each box
				const marked = [];

				keys.forEach( function( key ) {
					options.elements( key ).forEach( function( element ) { marked.push( element ); rects.push( element.getBoundingClientRect() ) } );
				} );

				// The element under the pointer, where it is not marked already and is still in the page
				if( options.hover === true && under !== null && marked.indexOf( under ) === -1 && under.isConnected !== false )
					quiet = under.getBoundingClientRect();

				while( layer.firstChild !== null )
					layer.removeChild( layer.firstChild );

				// The boxes are fixed in the viewport of the page - a header that sticks keeps its box, a body that is moved or positioned moves none - and follow the scroll
				rects.forEach( function( rect ) {

					const box = doc.createElement( 'div' );

					box.className = 'mark';
					box.style.top = rect.top+ 'px';
					box.style.left = rect.left+ 'px';
					box.style.width = rect.width+ 'px';
					box.style.height = rect.height+ 'px';
					layer.appendChild( box );
				} );

				if( quiet !== null ) {

					const frameBox = doc.createElement( 'div' );

					frameBox.className = 'hover';
					frameBox.style.top = quiet.top+ 'px';
					frameBox.style.left = quiet.left+ 'px';
					frameBox.style.width = quiet.width+ 'px';
					frameBox.style.height = quiet.height+ 'px';
					layer.appendChild( frameBox );
				}

				if( scroll === true && rects.length > 0 )
					win.scrollTo( { top : Math.max( 0, rects[0].top + win.scrollY - win.innerHeight / 3 ) } );
			};

			const release = function() {

				// The page may be another one's by now - a page of another origin, the page of an error: its window answers nothing, and there is nothing left to take off it
				listeners.splice( 0 ).forEach( function( held ) {
					try {
						held[0].removeEventListener( held[1], held[2] );
					}
					catch( blocked ) {
						// the listeners go with the page
					}
				} );

				if( observer !== null )
					observer.disconnect();

				if( host !== null && host.parentNode !== null )
					host.parentNode.removeChild( host );

				observer = null;
				layer = null;
				host = null;
				doc = null;
				under = null;
			};

			const listen = function( target, name, fn ) {
				target.addEventListener( name, fn );
				listeners.push( [ target, name, fn ] );
			};

			// The page the frame has now: the layer of boxes, the pointer, the layout
			const attach = function() {

				release();

				const now = frame.contentDocument;
				const win = frame.contentWindow;

				if( now === null || now === undefined || now.body === null || now.body === undefined || win === null )
					return;

				const tokens = typeof wn.getComputedStyle === 'function' ? wn.getComputedStyle( dE ) : null;
				const shadow = ( host = now.createElement( 'nino-ui-marks' ) ).attachShadow( { mode : 'open' } );
				const style = now.createElement( 'style' );

				doc = now;
				layer = now.createElement( 'div' );
				style.textContent = ':host{all:initial;position:fixed;top:0;left:0;width:0;height:0;z-index:2147483000}.mark{position:absolute;pointer-events:none;box-sizing:border-box;border:2px solid var(--nino-mark);border-radius:3px;background:var(--nino-mark-soft)}.hover{position:absolute;pointer-events:none;box-sizing:border-box;border:1px solid var(--nino-hover-ink);box-shadow:inset 0 0 0 1px var(--nino-hover-ground)}';
				host.style.setProperty( '--nino-mark', tokens === null ? '' : tokens.getPropertyValue( '--editor-blue' ) );
				host.style.setProperty( '--nino-mark-soft', tokens === null ? '' : tokens.getPropertyValue( '--editor-blue-soft' ) );
				host.style.setProperty( '--nino-hover-ink', tokens === null ? '' : tokens.getPropertyValue( '--editor-text' ) );
				host.style.setProperty( '--nino-hover-ground', tokens === null ? '' : tokens.getPropertyValue( '--editor-bg-elevated' ) );
				host.setAttribute( 'aria-hidden', 'true' );
				shadow.appendChild( style );
				shadow.appendChild( layer );
				now.body.appendChild( host );

				listen( now, 'pointerover', function( ev ) {

					const key = options.keyAt( ev.target ) ?? null;

					if( options.hover === true ) {

						const before = under;
						const target = ev.target;

						// Of the elements that stand for the key, the innermost one that has the pointer in it
						under = key === null ? null : options.elements( key ).filter( function( element ) {
							return element === target || ( typeof element.contains === 'function' && element.contains( target ) === true );
						} ).reduce( function( best, element ) { return best !== null && typeof best.contains === 'function' && best.contains( element ) === false ? best : element }, null );

						if( under !== before )
							paint( false );
					}

					say( key );
				} );
				listen( now.documentElement, 'pointerleave', function() {

					if( under !== null ) {
						under = null;
						paint( false );
					}

					say( null );
				} );
				listen( win, 'resize', function() { paint( false ) } );

				// Drawn again when the page has scrolled, once for the frames that come between
				let waiting = false;

				listen( win, 'scroll', function() {

					if( waiting === true )
						return;

					waiting = true;
					win.requestAnimationFrame( function() {
						waiting = false;
						paint( false );
					} );
				} );

				if( typeof win.ResizeObserver === 'function' ) {
					observer = new win.ResizeObserver( function() { paint( false ) } );
					observer.observe( now.body );
				}

				paint( false );
			};

			frame.addEventListener( 'load', attach );
			attach();

			return {

				highlight : function( wanted, how ) {

					keys = wanted === null || wanted === undefined ? [] : ( Array.isArray( wanted ) === true ? wanted : [ wanted ] ).map( String );
					paint( how !== undefined && how.scroll === true );
				},

				refresh : function() {
					paint( false );
				},

				stop : function() {
					frame.removeEventListener( 'load', attach );
					release();
					keys = [];
				},
			};
		},

		/**
		 *	The question a refused save asks - another tab or another person wrote what this one was about to write: Overwrite
		 *	does it once more over what is there now, Reload drops the work of this tab and shows what is there - the question says so in a
		 *	sentence of its own under the message, since it cannot be taken back, and is asked once: a second question after the answer
		 *	would ask the same thing twice - and Cancel leaves everything as it is. The answers stand as every dialog's do: the other
		 *	ways on, Cancel, and at the far right the one that goes on, which here is the dangerous one - so Reload, Cancel, Overwrite.
		 *	Escape is Cancel, and the focus goes back to the opener. The words are /_admin/common/conflict/*, and the caller's where it gives them
		 *
		 *	@param		{Object}		options					{ message, onOverwrite, onReload, onCancel, opener, text }
		 *														- message     what the server said of the refusal, usually Nino.adminUi.api.errorText( status, response )
		 *														- onOverwrite called to write over what is there; the caller sends the save again with the state the refusal was about
		 *														- onReload    called when Reload is pressed: the caller drops its work and loads what the server holds
		 *														- onCancel    called when nothing is done: Cancel or Escape
		 *														- opener      what gets the focus back, see choiceDialog()
		 *														- text        { title, overwrite, reload, reloadMessage, cancel } - reloadMessage is the sentence under the message that says what Reload drops
		 *
		 *	@return		{boolean}										false when a question is already open and this one was not asked
		 */
		conflict : function( options ) {

			const text = options.text || {};
			const word = function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };
			const call = function( name ) {
				if( typeof options[name] === 'function' )
					options[name]();
			};

			return Nino.adminUi.choiceDialog( {
				title		: word( 'title', '/_admin/common/conflict/title' ),
				message	: options.message,
				note		: word( 'reloadMessage', '/_admin/common/conflict/reload-message' ),
				opener	: options.opener,
				escape	: 'cancel',
				choices	: [
					{ value : 'reload', label : word( 'reload', '/_admin/common/conflict/reload' ), kind : 'secondary' },
					{ value : 'cancel', label : word( 'cancel', '/_admin/common/label/cancel' ), kind : 'secondary' },
					{ value : 'overwrite', label : word( 'overwrite', '/_admin/common/conflict/overwrite' ), kind : 'danger' },
				],
				onChoose : function( value ) {

					if( value === 'overwrite' ) {
						call('onOverwrite');
						return;
					}

					call( value === 'reload' ? 'onReload' : 'onCancel' );
				},
			} );
		},

		/**
		 *	The bar at the foot of a screen that saves what is on it as a whole: the state - "● Changed" or "✓ Saved" -, the
		 *	status line (status()) that says what the last action did, the buttons of the caller (undo, redo...), "Restore the
		 *	last state" once there is one and nothing unsaved, and Save. Save is pressed once at a time: while the save is on its way
		 *	it is disabled, and where the caller says what keeps it from being saved, the line says it and Save waits. The
		 *	caller says what is the case, with set(); the bar draws it and does nothing of its own accord. The first change of a
	 *	saved screen is said once on the line - a status, which a screen reader reads out - and not again while the changes stand; the
	 *	sentence is for the screen reader - the stylesheet keeps it off the screen - and goes after four seconds, the badge says the state.
	 *	What the caller says (an error, a reason, words of its own) stays on the line until the caller changes it. A save that
	 *	ends with done( false ) leaves the line as the caller left it: where it still says "Saving" it is back to "unsaved
	 *	changes", and a caller that has an error to say says it before it ends the save
		 *
		 *	press() is the Save of the keyboard - a panel hangs it on Ctrl+S through the shell's dirty registry (entry() gives
		 *	the entry of that registry, with the bar as the one the marker is drawn in). Where the registry is there, set() tells it
		 *	that the state changed. The words are /_admin/common/savebar/*, and the caller's where it gives them
		 *
		 *	@param		{Object}		options					{ id, text, buttons, restore, fresh, onSave, onRestore }
		 *														- id          the beginning of the ids it makes
		 *														- text        { changed, saved, save, restore, restoreHint } to say the words differently
		 *														- buttons     [ { key, label, name, onPress } ] put between the line and Restore, in this order
		 *														- restore     false for a bar that has no last state to go back to
		 *														- fresh       true for a screen whose thing was never saved: the badge says nothing until the first change, and "Saved" after the first save
		 *														- onSave      onSave( done ) saves, and says with done( true ) or done( false ) how it ended - on every way it can end
		 *														- onRestore   onRestore( opener ) is called by Restore, which is on the screen only where set() says there is a last state
		 *
		 *	@return		{Object}										{ el, line, state, save, restore, buttons, set( { dirty, blocked, restorable, calm, fresh } ), press( [done] ), entry( [extra] ) } - buttons is key => element, line the status() control. calm is a third, quiet state for a screen whose saved state is something else than "saved" ("Applied"): its word, shown with a check mark where nothing is changed; '' takes it back
		 */
		saveBar : function( options ) {

			const prefix	= options.id || 'nino-admin-savebar-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };

			const buttons	= {};
			const side		= [];

			( Array.isArray( options.buttons ) === true ? options.buttons : [] ).forEach( function( given ) {

				const button = Nino.adminUi.button( given.label, given.onPress, { name : given.name, id : prefix+ '-'+ given.key } );

				buttons[given.key] = button;
				side.push( button );
			} );

			const restore = Nino.adminUi.button( word( 'restore', '/_admin/common/savebar/restore' ), function() {
				if( typeof options.onRestore === 'function' )
					options.onRestore( restore );
			}, { id : prefix+ '-restore' } );

			const save = Nino.adminUi.button( word( 'save', '/_admin/common/savebar/save' ), function() { press() }, { id : prefix+ '-save' } );

			restore.hidden = true;

			if( options.restore !== false )
				side.push( restore );

			// The bar is the one of every screen (actionBar()): the state, the line, the buttons of the caller, Restore, and Save at the right edge
			const core		= Nino.adminUi.actionBar( { id : prefix, className : 'nino-admin-savebar', status : { fresh : options.fresh === true }, secondary : side, primary : save, text : { changed : text.changed, saved : text.saved } } );
			const bar			= core.el;
			const state		= core.state;
			const line		= core.line;

			state.classList.add('nino-admin-savebar-state');
			state.id = prefix+ '-state';

			let dirty = false;
			let blocked = '';
			let said = '';
			let calm = '';
			let fresh = options.fresh === true;
			let restorable = false;
			let busy = false;
			let typed = false;
			let heard = null;
			let saying = null;

			// The line says "unsaved changes" once, for the screen reader (the stylesheet keeps it off the screen: the badge shows
			// the state), and the sentence goes after SAID_FOR where nobody has put another in its place
			const tell = function() {

				wn.clearTimeout( heard );
				line.dirty();
				line.element.dataset.once = 'dirty';

				const words = line.element.textContent;

				heard = wn.setTimeout( function() {

					delete line.element.dataset.once;

					if( line.state === 'dirty' && line.element.textContent === words )
						line.idle();
				}, SAID_FOR );
			};

			const paint = function() {

				core.paint( { dirty : dirty, calm : calm, fresh : fresh } );
				save.disabled = busy === true || dirty === false || blocked !== '';
				restore.hidden = restorable === false;
				restore.disabled = busy === true || dirty === true;
				restore.title = dirty === true ? word( 'restoreHint', '/_admin/common/savebar/restore-hint' ) : '';

				// What keeps the draft from being saved is said where the person is looking; it goes when the cause does
				if( blocked !== '' && said !== blocked ) {
					said = blocked;
					line.fail( blocked );
				}
				else if( blocked === '' && said !== '' ) {
					said = '';

					if( dirty === true )
						tell();
					else
						line.idle();
				}
			};

			const refresh = function() {
				if( typeof shell().dirty === 'object' && shell().dirty !== null && typeof shell().dirty.refresh === 'function' )
					shell().dirty.refresh();
			};

			const press = function( done ) {

				if( busy === true || dirty === false || blocked !== '' || typeof options.onSave !== 'function' ) {
					if( typeof done === 'function' )
						done( false );
					return;
				}

				busy = true;
				typed = false;
				paint();

				// "Saving" is said when the save has taken a moment, and not at the press: a caller that asks something first (a name, a
				// confirmation) has not saved anything yet, and a save that is quick needs no sentence
				wn.clearTimeout( saying );
				saying = wn.setTimeout( function() {
					if( busy === true && line.state !== 'error' )
						line.saving();
				}, SAVING_AFTER );

				options.onSave( function( ok ) {

					wn.clearTimeout( saying );
					busy = false;

					// What was changed while the save was on its way is not in what was sent: it stays a change
					if( ok === true ) {
						dirty = typed;
						fresh = false;

						if( typed === true )
							tell();
						else
							line.saved();
					}
					else if( line.state === 'saving' )
						tell();

					paint();
					refresh();

					if( typeof done === 'function' )
						done( ok === true );
				} );
			};

			paint();

			return {

				el : bar,
				line : line,
				state : state,
				save : save,
				restore : restore,
				buttons : buttons,
				press : press,

				// What is the case now: the screen holds changes, something keeps it from being saved (a sentence, '' for nothing), there is a last state to go back to
				set : function( now ) {

					if( typeof now.dirty === 'boolean' ) {

						// The first change of a saved screen is said, once: the line is a status that is read out
						if( now.dirty === true && dirty === false && busy === false && blocked === '' && ( typeof now.blocked !== 'string' || now.blocked === '' ) && line.state !== 'error' )
							tell();

						// What the line said of changes is not true of a screen that is back to what is saved
						if( now.dirty === false && dirty === true && busy === false && line.state === 'dirty' )
							line.idle();

						dirty = now.dirty;
						typed = busy === true && now.dirty === true ? true : typed;
					}

					if( typeof now.calm === 'string' )
						calm = now.calm;

					if( typeof now.fresh === 'boolean' )
						fresh = now.fresh;

					if( typeof now.blocked === 'string' )
						blocked = now.blocked;

					if( typeof now.restorable === 'boolean' )
						restorable = now.restorable;

					paint();
					refresh();
				},

				// The entry of the shell's registry of unsaved input (Nino.admin.dirty.register()), with what else the caller adds to it
				entry : function( extra ) {
					return Object.assign( {
						isDirty : function() { return dirty },
						save : function( done ) { press( done ) },
						bar : function() { return bar },
					}, extra || {} );
				},
			};
		},

		/**
		 *	A switchboard for views that show the same things: what one of them points at - the pointer over a
		 *	component, the focus on a row - the others show as well, in every direction. The preview, the outline
		 *	and the tree of a builder join it, each with the function that marks a key on its own side, and report
		 *	what its own pointer is over; the switchboard hands it on to every member but the one that reported, so
		 *	that nothing is told its own word back and a view that marks what it is told cannot start a loop. A key
		 *	is a string, or a list of them where one thing is drawn more than once (the pattern of a loop in a
		 *	preview), or null for nothing. Reporting what is reported already tells nobody. It draws nothing and
		 *	owns no strings
		 *
		 *	@return		{Object}										{ join( on ), get() } - join( on ) takes a function that is called with the key
		 *																	another member reported and answers { set( key ), leave() }: set() reports a key, null for
		 *																	nothing - which takes the key away only from the member that reported it: a view that
		 *																	has reported nothing cannot clear what another is pointing at -, leave() takes the member
		 *																	out and, where it had the key, reports nothing; get() is the key reported last
		 */
		hoverSync : function() {

			const members = new Set();
			let held = null;
			let holder = null;

			const same = function( one, other ) {

				if( Array.isArray( one ) === true || Array.isArray( other ) === true )
					return Array.isArray( one ) === true && Array.isArray( other ) === true && one.length === other.length && one.every( function( key, at ) { return key === other[at] } );

				return one === other;
			};

			// A member that has no key reported cannot take one away: what is reported last is what is shown
			const report = function( from, key ) {

				const now = key === undefined ? null : key;

				if( now === null && from !== null && from !== holder )
					return;

				if( same( now, held ) === true )
					return;

				held = now;
				holder = now === null ? null : from;
				members.forEach( function( member ) {
					if( member !== from )
						member.on( now );
				} );
			};

			return {

				join : function( on ) {

					const member = { on : on };
					members.add( member );

					return {

						set : function( key ) {

							if( members.has( member ) === true )
								report( member, key );
						},

						leave : function() {

							if( members.delete( member ) === true && holder === member )
								report( null, null );
						},
					};
				},

				get : function() {
					return held;
				},
			};
		},

		/**
		 *	A tree: entries one under another, each a row with a type icon, a name in two voices - the
		 *	kind in bold, the content quietly after it -, a quiet note at the right, and buttons of its
		 *	own - the components of a page, the folders of a store. The caller gives the entries as data
		 *	and draws nothing itself; the tree is the pattern of the same name: role tree, treeitem and
		 *	group, aria-level, aria-expanded on the entries that have children, aria-selected, and exactly
		 *	one entry in the tab order (the one that last had the focus, else the chosen one, else the
		 *	first), the others reached by the keys.
		 *
		 *	It is drawn the one way for every tree of the workbench: a vertical guide line for each entry
		 *	that holds others, with a branch to each of its children; the chosen row a tint with a hairline
		 *	of the accent colour round it - no shadow - and its name in the text colour, heavier; the buttons
		 *	of a row only under the pointer, on the row that has the stop while the focus is in the tree - Tab from the row goes to its first button - and on the chosen row (a device that
		 *	has no pointer to hover with shows them there alone). A hint is a line of its own under its row.
		 *	An entry that says nothing of its kind has the neutral icon of the set.
		 *
		 *	A loop is an entry whose children are drawn once as the pattern of what is repeated for each
		 *	element of its source: the entry carries the loop icon and its note, the guide of its children is
		 *	dashed and in the accent colour, and a tag "per element" stands over the first of them. Where a
		 *	loop stands inside the pattern of another the tag names the type of its elements. A field the
		 *	pattern reads is a tag with the database icon and the name of the field, which is never cut
		 *	short: the name of the kind gives way to it first.
		 *
		 *	Up and Down walk the entries that can be seen, Right opens a closed entry and goes to the
		 *	first child of an open one, Left closes an open entry and goes to the parent of a closed
		 *	one, Home and End go to the first and the last, Enter and Space choose, a letter goes to
		 *	the next entry whose kind begins with it (wrapping). A click on the arrow opens or closes,
		 *	a click on the row chooses. The buttons of a row are in the tab order only while the row
		 *	is the one that has the stop, so that a tree of fifty rows is one stop and not two hundred;
		 *	Tab from the row reaches them, a mouse reaches all of them. A locked entry can be walked to,
		 *	opened and closed but not chosen, and says so in a word with a lock, seen and in its description
		 *	(not by aria-disabled, which would take its buttons from a screen reader too); what its buttons
		 *	may do is the caller's, through disabled
		 *
		 *	The tree does not own the data and writes no address: the caller binds the chosen entry
		 *	to its own address, as levels() and accordion() leave theirs to the panel. What it keeps
		 *	is what the eye needs between two drawings - which entries are open, which is chosen,
		 *	where the stop is - and update() gives them back where the ids stay: an id the tree has
		 *	seen keeps its open state (item.open is read for an id it has not), the stop and the focus
		 *	stay on their entry and, where the focus was on a button of it that is still there and can be
		 *	pressed, on that button, else on the row. An id follows the component, not its place: where an
		 *	entry is gone the stop and the focus go to its next sibling, else the previous one, else its
		 *	parent. A person's move of the stop, and this one, is told through onFocus; select(), focus() and
		 *	update() otherwise call nobody back. An update with no entries leaves the focus nowhere.
		 *
		 *	What the pointer or the focus is over is told through onHover, with the id, and with null where
		 *	it is over none - the hook of hoverSync(); and highlight() marks entries the way the pointer
		 *	does, from outside, without moving the focus or the page. With options.path the tree makes a
		 *	line that names the way to the chosen entry, "kind > kind > kind", each name a button that
		 *	chooses its entry: the caller puts tree.path above tree.el, where it stays at the top of
		 *	what scrolls. Owns no strings: the labels, the names and the hints are the caller's, the words
		 *	for a locked and a marked entry, a loop, a field and the path are fills
		 *
		 *	@param		{Object}		options					{ id, label, labelledby, items, selected, path, text, onSelect, onToggle, onAction, onFocus, onHover }
		 *														- id          the beginning of the ids it makes, a token (a space in it becomes a hyphen)
		 *														- label       the tree's name for a screen reader; or labelledby, the id of what names it
		 *														- items       [ { id, kind, name, detail, label, icon, meta, children, open, marked, locked, hint, loop, source, actions } ] - id a string, unique in the tree; kind the bold word (label is the same word, where an entry has no kind); name the content, quiet, after it; detail a second quiet note; icon the Lucide name of its type icon, an unknown one is the neutral icon; meta a quiet note at the right that gives way to the buttons; children the same shape; open whether it is open when the tree first sees it; marked says that the entry is the one shown elsewhere (the preview); locked as above; hint { text, state } - or finding, the same - a sign and its sentence on a line of its own under the row, state warn, error or muted; loop true, a string - the note - or { detail, type } - the note and the type of the elements, which the tag of a loop in a loop names; source a field name, or { field, label } - label the word before it; actions [ { key, icon, label, name, disabled, danger } ] - icon the Lucide name of the button, which the glyphs + ↑ ↓ × of label stand for where there is none, name what a screen reader and a title say, danger draws it as one that destroys; locked and marked show as words beside the row
		 *														- selected    the id chosen at the start
		 *														- path        true makes the line of the way to the chosen entry: tree.path
		 *														- text        { locked, marked, each, eachNote, loop, source, path } to say the words differently
		 *														- onSelect    called with the id and the item after a choice made by hand, also of the entry that is chosen already
		 *														- onToggle    called with the id, whether it is open or closed after the move, and the item after a hand opened or closed it
		 *														- onAction    called with the key of the button, the id and the item after a press
		 *														- onFocus     called with the id and the item when the stop moves to another entry, by a hand or because its entry is gone
		 *														- onHover     called with the id when the pointer or the focus comes over an entry, and with null when it leaves them; not for a redrawing that leaves it where it was
		 *
		 *	@return		{Object}										{ el, path, update( items ), select( id ), focus( [id], [key] ), highlight( ids, [options] ), get() } - select() chooses an entry (not a locked one: it answers false), opens what is above it and scrolls it into view, null chooses none; focus() puts the focus on the entry (the one with the stop by default), on its button with the key where there is one that can be pressed; both answer whether the id is there, and focus() of an id that is not there yet - the row a caller is about to draw - is kept for the next update(), which puts the focus there once; highlight() takes an id, a list of them or null, marks those and no others, tells nobody, opens what is above the first of them and rolls it into view where it lies outside the window of the tree (scrollIntoView with block 'nearest') - { scroll : false } leaves the tree where it is; it answers whether one of them is there; path is the line of the way, or null; get() is { selected, stop, open }
		 */
		tree : function( options ) {

			// The id is the beginning of the ids it makes, which are listed in aria-labelledby: a token, with no space in it
			const prefix	= typeof options.id === 'string' && options.id !== '' ? options.id.replace( /\s+/g, '-' ) : 'nino-admin-tree-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key, fill ) { return text[key] ?? Nino.adminUi.text( fill ) };

			// The glyphs the label of a button may be where it names no icon: what each stands for
			const GLYPHS	= { '+' : 'plus', '↑' : 'arrow-up', '↓' : 'arrow-down', '×' : 'trash-2' };

			const root = Nino.adminUi.el( 'ul', 'nino-admin-tree' );
			root.id = prefix;
			root.setAttribute( 'role', 'tree' );

			if( typeof options.labelledby === 'string' && options.labelledby !== '' )
				root.setAttribute( 'aria-labelledby', options.labelledby );
			else if( typeof options.label === 'string' )
				root.setAttribute( 'aria-label', options.label );

			// The line of the way to the chosen entry, over the tree: its own element, since a list holds no line
			const bar = options.path === true ? Nino.adminUi.el( 'nav', 'nino-admin-tree-path' ) : null;

			if( bar !== null ) {
				bar.setAttribute( 'aria-label', word( 'path', '/_admin/common/tree/path' ) );
				bar.hidden = true;
			}

			let order = [];
			let byId = new Map();
			let parts = new Map();
			let drawn = 0;

			const open = new Set();
			let selected = typeof options.selected === 'string' ? options.selected : null;
			let stop = null;

			// What the pointer or the focus is over - whichever of the two moved last, as an id; what was told last; what the caller marks from outside; and the entry a caller asked the focus for that is not there yet
			let hoverAt = null;
			let hoverBy = '';
			let told = null;
			let wish = null;
			let lit = new Set();
			let drawing = false;

			// Said once for each change of the entry that is pointed at, and never from inside a redrawing, which leaves what is pointed at where it was
			const tellOver = function() {

				const now = hoverAt;

				if( drawing === true || now === told )
					return;

				told = now;

				if( typeof options.onHover === 'function' )
					options.onHover( now );
			};

			// What stands for an element of the tree: the entry it is of, and which piece of the row it is
			const locate = function( target ) {

				for( let node = target; node !== null && node !== undefined; node = node.parentNode ) {

					const found = parts.get( node );

					if( found !== undefined )
						return found;

					if( node === root )
						break;
				}

				return null;
			};

			const inside = function( node ) {

				for( ; node !== null && node !== undefined; node = node.parentNode )
					if( node === root )
						return true;

				return false;
			};

			const shown = function( entry ) {

				for( let up = entry.parent; up !== null; up = up.parent )
					if( open.has( up.id ) === false )
						return false;

				return true;
			};

			const visible = function() {
				return order.filter( shown );
			};

			// The word of an entry: its kind, which its label is where it has none
			const kindOf = function( item ) {
				return String( item.kind ?? item.label ?? '' );
			};

			const paintOpen = function( entry ) {

				if( entry.group === null )
					return;

				const on = open.has( entry.id );

				entry.li.setAttribute( 'aria-expanded', on === true ? 'true' : 'false' );
				entry.group.hidden = on === false;
			};

			// The line of the way: the kinds of the entries from the top to the chosen one, each of the ones above a button that chooses it
			const paintPath = function() {

				if( bar === null )
					return;

				while( bar.firstChild !== null )
					bar.removeChild( bar.firstChild );

				const entry = selected === null ? null : ( byId.get( selected ) ?? null );

				bar.hidden = entry === null;

				if( entry === null )
					return;

				const chain = [];

				for( let up = entry; up !== null; up = up.parent )
					chain.unshift( up );

				chain.forEach( function( link, at ) {

					if( at > 0 ) {
						const sign = Nino.adminUi.el( 'span', 'nino-admin-tree-path-sign' );
						sign.setAttribute( 'aria-hidden', 'true' );
						sign.appendChild( Nino.adminUi.icon( 'chevron-right', { size : 12 } ) );
						bar.appendChild( sign );
					}

					const name = kindOf( link.item )+ ( link.loop !== null ? ' · '+ word( 'each', '/_admin/common/tree/each' ) : '' );

					if( at === chain.length - 1 ) {
						const here = Nino.adminUi.el( 'span', 'nino-admin-tree-path-now', name );
						here.setAttribute( 'aria-current', 'location' );
						bar.appendChild( here );
						return;
					}

					bar.appendChild( Nino.adminUi.button( name, function() {
						go( link );
						choose( link );
					}, { kind : 'link', className : 'nino-admin-tree-path-link' } ) );
				} );

				// One line: a long way scrolls inside it, and the place - the end - is the part that stays in sight
				bar.scrollLeft = bar.scrollWidth;

				// ...also where the tree is not on the page yet and has no width to measure
				if( typeof wn.requestAnimationFrame === 'function' )
					wn.requestAnimationFrame( function() { bar.scrollLeft = bar.scrollWidth } );
			};

			const paintSelected = function() {
				order.forEach( function( entry ) {
					entry.li.setAttribute( 'aria-selected', entry.id === selected ? 'true' : 'false' );
					entry.row.classList.toggle( 'is-selected', entry.id === selected );
				} );

				paintPath();
			};

			const paintLit = function() {
				order.forEach( function( entry ) {
					entry.row.classList.toggle( 'is-lit', lit.has( entry.id ) );
				} );
			};

			// The one entry in the tab order, and with it the buttons of its row
			const setStop = function( entry, told ) {

				const moved = stop !== entry;

				if( stop !== null && stop !== entry ) {
					stop.li.tabIndex = -1;
					stop.row.classList.remove( 'is-stop' );
					stop.buttons.forEach( function( button ) { button.tabIndex = -1 } );
				}

				stop = entry;

				if( entry === null )
					return;

				entry.li.tabIndex = 0;
				entry.row.classList.add( 'is-stop' );
				entry.buttons.forEach( function( button ) { button.tabIndex = 0 } );

				if( moved === true && told === true && typeof options.onFocus === 'function' )
					options.onFocus( entry.id, entry.item );
			};

			const setOpen = function( entry, on, told ) {

				if( entry.group === null || open.has( entry.id ) === on )
					return;

				const held = inside( dc.activeElement );

				if( on === true )
					open.add( entry.id );
				else
					open.delete( entry.id );

				paintOpen( entry );

				// A closed entry hides what is under it: the stop, and the focus with it, stay with the entry
				if( stop !== null && shown( stop ) === false ) {
					setStop( entry );

					if( held === true )
						go( entry );
				}

				if( told === true && typeof options.onToggle === 'function' )
					options.onToggle( entry.id, on, entry.item );
			};

			const choose = function( entry ) {

				if( entry.item.locked === true )
					return;

				selected = entry.id;
				paintSelected();

				if( typeof options.onSelect === 'function' )
					options.onSelect( entry.id, entry.item );
			};

			// What is above an entry is opened for it, without a word to the caller
			const reveal = function( entry ) {
				for( let up = entry.parent; up !== null; up = up.parent )
					setOpen( up, true, false );
			};

			// The entry holds the whole open subtree, so a focus that scrolls would leave the row out of sight: the row is scrolled to
			const show = function( entry, target ) {
				target.focus( { preventScroll : true } );
				entry.row.scrollIntoView( { block : 'nearest' } );
			};

			const go = function( entry ) {
				setStop( entry, true );
				show( entry, entry.li );
			};

			// The focus on an entry, and on the button of it that has the key where there is one that can be pressed
			const focusOn = function( entry, key ) {

				reveal( entry );
				go( entry );

				const button = key === undefined ? undefined : entry.keyed.get( key );

				if( button !== undefined && button.disabled === false )
					show( entry, button );
			};

			// A button of a row: an icon button where there is an icon to give it - its own, or the one its glyph stands for - and a word of its own where there is none
			const actionButton = function( action, press ) {

				const icon = Object.hasOwn( ICONS, String( action.icon ) ) === true ? action.icon : ( Object.hasOwn( GLYPHS, String( action.label ) ) === true ? GLYPHS[String( action.label )] : undefined );
				const name = typeof action.name === 'string' ? action.name : '';
				const classes = 'nino-admin-tree-action'+ ( action.danger === true ? ' nino-admin-tree-action--danger' : '' );
				let button;

				if( icon !== undefined && name.trim() !== '' )
					button = Nino.adminUi.iconButton( icon, name, press, { danger : action.danger === true, small : true, className : classes, disabled : action.disabled === true } );
				else
					button = Nino.adminUi.button( String( action.label ?? '' ), press, { className : classes, name : name === '' ? undefined : name, disabled : action.disabled === true } );

				button.dataset.key = String( action.key );

				return button;
			};

			const make = function( list, parent, level, into ) {

				list.forEach( function( item, at ) {

					const id = String( item.id );
					const n = ++drawn;
					const kids = Array.isArray( item.children ) === true ? item.children : [];
					const found = item.hint ?? item.finding;
					const hint = typeof found === 'string' && found !== '' ? { text : found } : ( typeof found === 'object' && found !== null && typeof found.text === 'string' && found.text !== '' ? found : null );
					const locked = item.locked === true;
					const marked = item.marked === true;
					const named = typeof item.name === 'string' ? item.name : '';
					const detail = typeof item.detail === 'string' ? item.detail : '';
					const meta = typeof item.meta === 'string' ? item.meta : '';
					const loop = item.loop === true ? {} : ( typeof item.loop === 'string' ? { detail : item.loop } : ( typeof item.loop === 'object' && item.loop !== null ? item.loop : null ) );
					const source = typeof item.source === 'string' ? { field : item.source } : ( typeof item.source === 'object' && item.source !== null && typeof item.source.field === 'string' ? item.source : null );

					const li = Nino.adminUi.el( 'li', 'nino-admin-tree-item' );
					const row = Nino.adminUi.el( 'div', 'nino-admin-tree-row' );
					const toggle = Nino.adminUi.el( 'span', 'nino-admin-tree-toggle' );
					const label = Nino.adminUi.el( 'span', 'nino-admin-tree-label', kindOf( item ) );

					const entry = { id : id, item : item, parent : parent, level : level, loop : loop, li : li, row : row, toggle : toggle, label : label, group : null, buttons : [], keyed : new Map() };

					li.setAttribute( 'role', 'treeitem' );
					li.setAttribute( 'aria-level', String( level ) );
					li.setAttribute( 'aria-setsize', String( list.length ) );
					li.setAttribute( 'aria-posinset', String( at + 1 ) );
					li.setAttribute( 'aria-labelledby', prefix+ '-'+ n+ '-label' );
					li.dataset.id = id;
					li.tabIndex = -1;

					li.dataset.locked = locked === true ? 'true' : 'false';
					li.dataset.marked = marked === true ? 'true' : 'false';
					li.dataset.loop = loop !== null ? 'true' : 'false';

					// The indent is the level's: a rule of the stylesheet cannot count levels
					li.style.setProperty( '--depth', String( level - 1 ) );

					label.id = prefix+ '-'+ n+ '-label';
					label.title = label.textContent;
					toggle.setAttribute( 'aria-hidden', 'true' );

					if( kids.length > 0 )
						toggle.appendChild( Nino.adminUi.icon( 'chevron-right', { size : 14 } ) );

					row.appendChild( toggle );

					// The icon of the entry's type: the loop's own where it is one, the neutral square where the entry names none
					const type = Nino.adminUi.el( 'span', 'nino-admin-tree-type' );
					type.appendChild( Nino.adminUi.icon( loop !== null ? 'repeat' : ( Object.hasOwn( ICONS, String( item.icon ) ) === true ? item.icon : 'square' ) ) );
					row.appendChild( type );

					// The kind in bold, the content and the notes quietly after it, the field the entry reads last, and never cut short
					const column = Nino.adminUi.el( 'span', 'nino-admin-tree-text' );
					const lead = Nino.adminUi.el( 'span', 'nino-admin-tree-lead' );
					lead.appendChild( label );
					column.appendChild( lead );
					row.appendChild( column );

					// What names the entry besides its kind: its content, or the field it reads - the kind alone names no entry among its like
					const says = [];
					const names = [];

					if( loop !== null )
						says.push( word( 'loop', '/_admin/common/tree/loop' ) );

					[ [ named, 'nino-admin-tree-name' ], [ detail, 'nino-admin-tree-detail' ], [ typeof loop?.detail === 'string' ? loop.detail : '', 'nino-admin-tree-detail' ] ].forEach( function( quiet ) {

						if( quiet[0] === '' )
							return;

						const note = Nino.adminUi.el( 'span', quiet[1], quiet[0] );
						note.title = quiet[0];
						note.setAttribute( 'aria-hidden', 'true' );
						lead.appendChild( note );

						if( quiet[1] === 'nino-admin-tree-name' )
							names.push( quiet[0] );
						else
							says.push( quiet[0] );
					} );

					if( source !== null ) {

						const field = String( source.field );
						const said = ( typeof source.label === 'string' && source.label !== '' ? source.label : word( 'source', '/_admin/common/tree/source' ) )+ ' '+ field;
						const tag = Nino.adminUi.el( 'span', 'nino-admin-tree-source' );

						if( typeof source.label === 'string' && source.label !== '' )
							tag.appendChild( Nino.adminUi.el( 'span', 'nino-admin-tree-source-word', source.label ) );

						tag.appendChild( Nino.adminUi.icon( 'database', { size : 13 } ) );
						tag.appendChild( Nino.adminUi.el( 'code', 'nino-admin-tree-source-field', field ) );
						tag.title = said;
						tag.setAttribute( 'aria-hidden', 'true' );
						column.appendChild( tag );
						names.push( said );
					}

					if( meta !== '' ) {
						const note = Nino.adminUi.el( 'span', 'nino-admin-tree-meta', meta );
						note.title = meta;
						note.setAttribute( 'aria-hidden', 'true' );
						row.appendChild( note );
						says.push( meta );
					}

					// Words, not signs: the state of an entry is said where the eye is, quietly, and also in the description
					[ [ marked, 'marked' ], [ locked, 'locked' ] ].forEach( function( state ) {

						if( state[0] !== true )
							return;

						const said = word( state[1], '/_admin/common/tree/'+ state[1] );
						const chip = Nino.adminUi.el( 'span', 'nino-admin-tree-state', said );
						chip.dataset.state = state[1];
						chip.setAttribute( 'aria-hidden', 'true' );

						if( state[1] === 'locked' )
							chip.insertBefore( Nino.adminUi.icon( 'lock', { size : 14 } ), chip.firstChild );

						row.appendChild( chip );
						says.push( said );
					} );

					const actions = Array.isArray( item.actions ) === true ? item.actions : [];

					if( actions.length > 0 ) {

						const buttons = Nino.adminUi.el( 'span', 'nino-admin-tree-actions' );

						actions.forEach( function( action ) {

							const button = actionButton( action, function() {
								if( typeof options.onAction === 'function' )
									options.onAction( action.key, id, item );
							} );

							button.tabIndex = -1;
							entry.buttons.push( button );
							entry.keyed.set( action.key, button );
							parts.set( button, { entry : entry, part : 'action' } );
							buttons.appendChild( button );
						} );

						parts.set( buttons, { entry : entry, part : 'actions' } );
						row.classList.add( 'is-actionable' );
						row.appendChild( buttons );
					}

					// A hint is a line of its own under the row, as wide as the row and not part of its tint
					let line = null;

					if( hint !== null ) {
						line = Nino.adminUi.el( 'span', 'nino-admin-tree-hint' );
						const sign = Nino.adminUi.el( 'span', 'nino-admin-tree-sign' );
						sign.dataset.state = [ 'error', 'muted' ].indexOf( hint.state ) !== -1 ? hint.state : 'warn';
						sign.appendChild( Nino.adminUi.icon( 'triangle-alert', { size : 14 } ) );
						line.setAttribute( 'aria-hidden', 'true' );
						line.appendChild( sign );
						line.appendChild( Nino.adminUi.el( 'span', 'nino-admin-tree-hint-text', hint.text ) );
						line.title = hint.text;
						says.push( hint.text );
					}

					// A screen reader reads the name - the kind and what names it - then what the row says besides
					if( names.length > 0 ) {
						const known = Nino.adminUi.el( 'span', 'nino-admin-sr-only', names.join( ', ' ) );
						known.id = prefix+ '-'+ n+ '-name';
						li.setAttribute( 'aria-labelledby', label.id+ ' '+ known.id );
						row.appendChild( known );
					}

					if( says.length > 0 ) {
						const info = Nino.adminUi.el( 'span', 'nino-admin-sr-only', says.join( ', ' ) );
						info.id = prefix+ '-'+ n+ '-info';
						li.setAttribute( 'aria-describedby', info.id );
						row.appendChild( info );
					}

					li.appendChild( row );

					if( line !== null )
						li.appendChild( line );

					into.appendChild( li );

					parts.set( li, { entry : entry, part : 'item' } );
					parts.set( toggle, { entry : entry, part : 'toggle' } );
					order.push( entry );
					byId.set( id, entry );

					if( kids.length > 0 ) {

						entry.group = Nino.adminUi.el( 'ul', 'nino-admin-tree-group'+ ( loop !== null ? ' nino-admin-tree-group--each' : '' ) );
						entry.group.setAttribute( 'role', 'group' );

						if( loop !== null )
							entry.group.setAttribute( 'aria-label', word( 'eachNote', '/_admin/common/tree/each-note' ) );

						li.appendChild( entry.group );

						// What stands under a loop is the pattern of what is repeated: a tag over it says so, and in a loop in a loop names the type of its elements
						if( loop !== null ) {

							const tag = Nino.adminUi.el( 'li', 'nino-admin-tree-each' );
							const inner = Nino.adminUi.el( 'span', 'nino-admin-tree-each-tag' );
							let nested = false;

							for( let up = parent; up !== null; up = up.parent )
								nested = nested || up.loop !== null;

							tag.setAttribute( 'role', 'presentation' );
							tag.setAttribute( 'aria-hidden', 'true' );
							tag.style.setProperty( '--depth', String( level ) );
							inner.appendChild( Nino.adminUi.icon( 'repeat', { size : 12 } ) );
							inner.appendChild( dc.createTextNode( word( 'each', '/_admin/common/tree/each' )+ ( nested === true && typeof loop.type === 'string' && loop.type !== '' ? ' · '+ loop.type : '' ) ) );
							tag.appendChild( inner );
							tag.appendChild( Nino.adminUi.el( 'span', 'nino-admin-tree-each-note', word( 'eachNote', '/_admin/common/tree/each-note' ) ) );
							entry.group.appendChild( tag );
						}

						make( kids, entry, level + 1, entry.group );
					}
				} );
			};

			const draw = function( list ) {

				const held = inside( dc.activeElement ) === true ? keyOf( dc.activeElement ) : null;
				const known = new Set( byId.keys() );
				const was = stop === null ? null : stop.id;

				// Where the stop's entry is gone the stop goes to its next sibling, else the previous one, else its parent
				const near = [];

				if( stop !== null ) {
					const siblings = order.filter( function( other ) { return other.parent === stop.parent } );
					const at = siblings.indexOf( stop );
					[ siblings[at + 1], siblings[at - 1], stop.parent ].forEach( function( other ) { if( other !== undefined && other !== null ) near.push( other.id ) } );
				}

				// Taking the old rows away takes the focus from them, and giving it back puts it on a row: neither is news to whoever is told of what is pointed at
				drawing = true;

				while( root.firstChild !== null )
					root.removeChild( root.firstChild );

				order = [];
				byId = new Map();
				parts = new Map();
				stop = null;

				make( list, null, 1, root );

				// An id the tree has seen keeps what the hand did; one it has not takes what the item says
				order.forEach( function( entry ) {
					if( entry.group !== null && known.has( entry.id ) === false && entry.item.open === true )
						open.add( entry.id );
				} );

				Array.from( open ).forEach( function( id ) {
					if( byId.has( id ) === false || byId.get( id ).group === null )
						open.delete( id );
				} );

				if( selected !== null && byId.has( selected ) === false )
					selected = null;

				order.forEach( paintOpen );
				paintSelected();
				paintLit();

				const seen = visible();
				const kept = was !== null && byId.has( was ) === true && shown( byId.get( was ) ) === true ? byId.get( was ) : null;
				const chosen = selected !== null && shown( byId.get( selected ) ) === true ? byId.get( selected ) : null;

				const neighbour = near.map( function( id ) { return byId.get( id ) } ).find( function( other ) { return other !== undefined && shown( other ) === true } ) ?? null;

				setStop( kept ?? neighbour ?? chosen ?? seen[0] ?? null, held !== null && kept === null );

				// The focus goes back where it was: on the button of the row where it stood on one, if it is still there and can be pressed
				if( held !== null && stop !== null ) {

					const back = held.part === 'action' && held.entry.id === stop.id && stop.keyed.has( held.key ) === true ? stop.keyed.get( held.key ) : null;

					show( stop, back !== null && back.disabled === false ? back : stop.li );
				}

				// What the caller asked the focus for before the entry was there: asked once, kept over one redrawing
				if( wish !== null && byId.has( wish.id ) === true ) {
					const asked = wish;
					wish = null;
					focusOn( byId.get( asked.id ), asked.key );
				}
				else
					wish = null;

				drawing = false;

				if( inside( dc.activeElement ) === false )
					root.classList.remove( 'is-inside' );

				if( hoverAt !== null && ( byId.has( hoverAt ) === false || ( hoverBy === 'focus' && inside( dc.activeElement ) === false ) ) )
					hoverAt = null;

				tellOver();
			};

			const keyOf = function( node ) {

				const found = locate( node );

				if( found === null || found.part !== 'action' )
					return found;

				const key = Array.from( found.entry.keyed.keys() ).find( function( name ) { return found.entry.keyed.get( name ) === node } );

				return { entry : found.entry, part : 'action', key : key };
			};

			root.addEventListener( 'keydown', function( ev ) {

				const found = parts.get( ev.target );

				if( found === undefined || found.part !== 'item' || ev.ctrlKey === true || ev.altKey === true || ev.metaKey === true )
					return;

				const entry = found.entry;
				const seen = visible();
				const at = seen.indexOf( entry );
				let next = null;

				if( ev.key === 'ArrowDown' )	next = seen[at + 1] ?? null;
				else if( ev.key === 'ArrowUp' )	next = seen[at - 1] ?? null;
				else if( ev.key === 'Home' )		next = seen[0];
				else if( ev.key === 'End' )			next = seen[seen.length - 1];
				else if( ev.key === 'ArrowRight' ) {

					if( entry.group !== null && open.has( entry.id ) === false )
						setOpen( entry, true, true );
					else if( entry.group !== null )
						next = order[order.indexOf( entry ) + 1] ?? null;
				}
				else if( ev.key === 'ArrowLeft' ) {

					if( entry.group !== null && open.has( entry.id ) === true )
						setOpen( entry, false, true );
					else
						next = entry.parent;
				}
				else if( ev.key === 'Enter' || ev.key === ' ' )
					choose( entry );
				else if( typeof ev.key === 'string' && ev.key.length === 1 ) {

					const letter = ev.key.toLowerCase();
					const ring = seen.slice( at + 1 ).concat( seen.slice( 0, at ) );

					next = ring.find( function( other ) { return kindOf( other.item ).toLowerCase().indexOf( letter ) === 0 } ) ?? null;

					if( next === null )
						return;
				}
				else
					return;

				// The arrows and Space scroll the page otherwise
				ev.preventDefault();

				if( next !== null )
					go( next );
			} );

			root.addEventListener( 'click', function( ev ) {

				const found = locate( ev.target );

				if( found === null || found.part === 'action' || found.part === 'actions' )
					return;

				if( found.part === 'toggle' ) {
					setOpen( found.entry, open.has( found.entry.id ) === false, true );
					return;
				}

				go( found.entry );
				choose( found.entry );
			} );

			// The stop follows the hand: a click or a Tab that lands in a row makes it the row of the tab order; and the focus in a row is what is pointed at, as the pointer is
			root.addEventListener( 'focusin', function( ev ) {

				const found = locate( ev.target );

				if( found !== null && found.entry !== stop )
					setStop( found.entry, true );

				root.classList.add( 'is-inside' );

				// The focus a redrawing gives back is no move of what is pointed at
				if( found !== null && drawing === false ) {
					hoverAt = found.entry.id;
					hoverBy = 'focus';
					tellOver();
				}
			} );

			// The row with the stop shows its buttons while the focus is anywhere in the tree - on the row or on one of them - and a redrawing, which takes the focus for a moment, leaves it so
			root.addEventListener( 'focusout', function( ev ) {

				if( drawing === true || inside( ev.relatedTarget ) === true )
					return;

				root.classList.remove( 'is-inside' );

				if( hoverBy === 'focus' ) {
					hoverAt = null;
					tellOver();
				}
			} );

			root.addEventListener( 'pointerover', function( ev ) {

				const found = locate( ev.target );

				hoverAt = found === null ? null : found.entry.id;
				hoverBy = 'pointer';
				tellOver();
			} );

			root.addEventListener( 'pointerleave', function() {

				hoverAt = null;
				hoverBy = 'pointer';
				tellOver();
			} );

			draw( Array.isArray( options.items ) === true ? options.items : [] );

			return {

				el : root,

				path : bar,

				update : function( items ) {
					draw( Array.isArray( items ) === true ? items : [] );
				},

				select : function( id ) {

					if( id === null ) {
						selected = null;
						paintSelected();
						return true;
					}

					const entry = byId.get( String( id ) );

					if( entry === undefined || entry.item.locked === true )
						return false;

					reveal( entry );
					selected = entry.id;
					paintSelected();
					setStop( entry );
					entry.row.scrollIntoView( { block : 'nearest' } );

					return true;
				},

				focus : function( id, key ) {

					const entry = id === undefined ? stop : byId.get( String( id ) );

					if( entry === undefined || entry === null ) {
						// An entry the caller is about to draw: the next redrawing puts the focus there
						wish = id === undefined ? null : { id : String( id ), key : key };
						return false;
					}

					focusOn( entry, key );

					return true;
				},

				highlight : function( keys, how ) {

					const list = keys === null || keys === undefined ? [] : ( Array.isArray( keys ) === true ? keys : [ keys ] ).map( String );

					lit = new Set( list );
					paintLit();

					const first = list.map( function( id ) { return byId.get( id ) } ).find( function( entry ) { return entry !== undefined } );

					// The marked row is brought into view where it lies outside the tree's window: 'nearest' leaves a row that is in view where it is
					if( first !== undefined && ( how === undefined || how === null || how.scroll !== false ) ) {
						reveal( first );
						first.row.scrollIntoView( { block : 'nearest' } );
					}

					return first !== undefined;
				},

				get : function() {
					return { selected : selected, stop : stop === null ? null : stop.id, open : Array.from( open ) };
				},
			};
		},

		/**
		 *	A field for a colour: the hex code in a text field, the system's own
		 *	colour picker beside it, a surface that shows the colour, and - where
		 *	options.against names a second colour - the contrast between them as
		 *	a number, with whether it reaches the ratio a text needs (4.5 : 1 by
		 *	default, the level WCAG asks of ordinary text). The surface shows the
		 *	second colour as text on this one, so the number can be checked by eye.
		 *	The verdict is a word and a mark, not a colour alone.
		 *
		 *	What is typed is kept as it was typed while it is being typed: a
		 *	valid code (#rrggbb, #rgb, the # may be left out) is taken at once and
		 *	reported through onChange as #rrggbb; a code that is not one says so
		 *	at the field - aria-invalid, the sentence pointed at by aria-describedby -
		 *	and reports through onInvalid. The picker and the field follow each
		 *	other. A short code is written out in full when the field is left. The field
		 *	takes whatever is pasted into it - parse() alone says what is a colour, so
		 *	#11223344 and rgb(37, 99, 235) are none, with the sentence - and trims the
		 *	space round it. The contrast line is written once the typing has rested
		 *	(COLOR_QUIET) for a screen reader - a copy of it in a live region of its
		 *	own - while the line itself follows the typing; set() tells it at once and shows
		 *	an error as a typed one would, without telling the caller; against() takes the
		 *	way of the typing.
		 *	The name stands at the left of the code in a form that is wide enough, as the name of every field does
		 *	(see the rules of fieldset > .nino-admin-field in style.css), and above it where there is no room. What the field is
		 *	described by besides the block's own sentences - the help behind the name, a note - is kept; a field with nothing in it
		 *	has an empty picker, not a black one.
		 *	The pure half, parsing and the contrast, is colorModel. Owns no
		 *	strings, the words of the contrast and of the error apart
		 *
		 *	@param		{Object}		options					{ label, value, against, minimum, required, id, text, onChange, onInvalid }
		 *														- label       the field's name
		 *														- value       the colour at the start
		 *														- against     the colour the contrast is measured against; none draws no contrast
		 *														- minimum     the ratio that is enough, 4.5
		 *														- required    the field may not be empty: an empty field is an error with the required field's sentence, and without it an empty field is no colour and no error (onChange is told '')
		 *														- id          the text field's id, and the beginning of the ids around it
		 *														- text        { invalid, pick, contrast, enough, short } to say the words differently
		 *														- onChange    called with #rrggbb after a valid code, with '' after the emptying of a field that is not required
		 *														- onInvalid   called with what stands in the field after one that is not
		 *
		 *	@return		{Object}										{ el, input, get(), set( value ), against( value ) } - get() is #rrggbb, or null where the field holds no colour
		 */
		color : function( options ) {

			const model		= Nino.adminUi.colorModel;
			const prefix	= options.id || 'nino-admin-color-'+ ( ++serial );
			const text		= options.text || {};
			const word		= function( key ) { return text[key] ?? Nino.adminUi.text( '/_admin/common/color/'+ key ) };
			const minimum	= typeof options.minimum === 'number' ? options.minimum : model.ENOUGH;

			const root = dc.createElement('div');
			root.className = 'nino-admin-color';

			let value = null;
			let second = model.parse( options.against ?? '' );

			const field = Nino.adminUi.textField( {
				label : options.label, id : prefix, value : options.value ?? '', autocomplete : 'off', required : options.required === true,
				onInput : function( typed ) { typedIn( typed, true ) },
			} );

			const input = field.querySelector('input');
			input.spellcheck = false;
			input.className += ' nino-admin-color-hex';

			const picker = dc.createElement('input');
			picker.type = 'color';
			picker.className = 'nino-admin-color-picker';
			picker.setAttribute( 'aria-label', ( options.label ? options.label+ ' – ' : '' )+ word('pick') );

			const swatch = Nino.adminUi.el( 'span', 'nino-admin-color-swatch', 'Aa' );
			swatch.setAttribute( 'aria-hidden', 'true' );

			const contrast = dc.createElement('p');
			contrast.className = 'nino-admin-color-contrast';
			contrast.id = prefix+ '-contrast';
			contrast.hidden = true;

			// What a screen reader is told: a copy of the line in a live region of its own, written
			// when the typing has rested - the line itself follows the typing and is no live region
			const announce = Nino.adminUi.el( 'span', 'nino-admin-sr-only' );
			announce.setAttribute( 'aria-live', 'polite' );

			const mark = Nino.adminUi.el( 'span', 'nino-admin-color-mark' );
			mark.setAttribute( 'aria-hidden', 'true' );

			const verdict = dc.createElement('span');

			contrast.appendChild( mark );
			contrast.appendChild( verdict );

			// The identifiers the field is described by that the block did not give it: the caller's. fieldError() replaces them with its own, so they are
			// taken before it is asked
			let others = [];

			const foreign = function() {
				return String( input.getAttribute('aria-describedby') ?? '' ).split(/\s+/).filter( function( token ) { return token !== '' && token !== prefix+ '-error' && token !== contrast.id } );
			};

			// What the field is described by: its error, and the contrast where that is shown - a
			// hidden line that is still named is read out with the sentence that says it is stale
			const describe = function() {

				// What the field is described by that is not the block's - its help, a note, whatever the caller named - stays behind the block's own
				const ids = [];

				if( root.querySelector('.nino-admin-field-error') !== null )
					ids.push( prefix+ '-error' );

				if( contrast.hidden === false )
					ids.push( contrast.id );

				others.concat( foreign() ).forEach( function( token ) {
					if( ids.indexOf( token ) === -1 )
						ids.push( token );
				} );

				if( ids.length === 0 )
					input.removeAttribute('aria-describedby');
				else
					input.setAttribute( 'aria-describedby', ids.join(' ') );
			};

			const clear = function() {
				const old = root.querySelector('.nino-admin-field-error');
				if( old !== null )
					old.remove();
				input.removeAttribute('aria-invalid');
			};

			const write = function() {

				const ratio = model.contrast( value, second );
				const enough = ratio >= minimum;

				contrast.dataset.state = enough === true ? 'ok' : 'error';
				mark.textContent = enough === true ? '✓' : '✕';
				verdict.textContent = Nino.adminUi.format( word('contrast'), model.truncate( ratio ) )+ ' · '+ Nino.adminUi.format( word( enough === true ? 'enough' : 'short' ), minimum );
				contrast.hidden = false;
				describe();
			};

			let quiet = null;

			// The line is seen as it is typed; what is read out is written once the typing has rested -
			// a code typed digit by digit, or a picker dragged beside it, passes through many valid ones
			const settle = function( now ) {

				wn.clearTimeout( quiet );
				quiet = null;

				if( value === null || second === null ) {
					contrast.hidden = true;
					announce.textContent = '';
					return;
				}

				write();

				if( now === true )
					announce.textContent = verdict.textContent;
				else
					quiet = wn.setTimeout( function() { announce.textContent = verdict.textContent }, Nino.adminUi.COLOR_QUIET );
			};

			const draw = function( now ) {

				// What is not a colour is not written anywhere: the picker keeps the last one it showed
				if( value !== null ) {
					picker.value = value;
					swatch.style.backgroundColor = value;
					swatch.style.color = second ?? value;
				}

				swatch.hidden = value === null;

				// A field with nothing in it shows no colour: the picker is drawn empty and not as the black it holds
				if( value === null && input.value.trim() === '' )
					picker.setAttribute( 'data-empty', '' );
				else
					picker.removeAttribute('data-empty');

				settle( now );
				describe();
			};

			// What is in the field, by hand (typed or picked: the caller is told) or put there (it is not)
			const typedIn = function( typed, byHand ) {

				others = foreign();
				clear();

				const parsed = model.parse( typed );

				if( parsed === null ) {

					value = null;
					draw( true );

					if( typed.trim() === '' && options.required !== true ) {
						if( byHand === true && typeof options.onChange === 'function' )
							options.onChange( '' );
						return;
					}

					Nino.adminUi.fieldError( field, input, prefix+ '-error', typed.trim() === '' ? Nino.adminUi.text('/_admin/common/error/required') : word('invalid') );
					describe();

					if( byHand === true && typeof options.onInvalid === 'function' )
						options.onInvalid( typed );

					return;
				}

				value = parsed;
				draw( byHand !== true );

				if( byHand === true && typeof options.onChange === 'function' )
					options.onChange( parsed );
			};

			picker.addEventListener( 'input', function() {
				input.value = picker.value;
				typedIn( picker.value, true );
			} );

			// A short code is the long one when the person is done with the field
			input.addEventListener( 'change', function() {
				if( value !== null )
					input.value = value;
			} );

			root.appendChild( field );
			root.appendChild( picker );
			root.appendChild( swatch );
			root.appendChild( contrast );
			root.appendChild( announce );

			typedIn( String( options.value ?? '' ), false );

			return {

				el : root,
				input : input,

				get : function() {
					return value;
				},

				set : function( next ) {
					input.value = next ?? '';
					typedIn( String( next ?? '' ), false );
				},

				against : function( next ) {
					second = model.parse( next ?? '' );
					draw( false );
				},
			};
		},

		/**
		 *	The pure half of color(): what is a colour, how light it is, how far
		 *	two colours are apart. Needs no DOM, so it is tested on its own and a
		 *	panel that has colours without a field (the pairs a palette is made
		 *	of) asks it the same questions
		 *
		 *	  - ENOUGH       4.5, the ratio WCAG 2.1 asks of ordinary text (AA)
		 *	  - parse        #rrggbb of a code written #rrggbb, rrggbb, #rgb or rgb, in either
		 *	                 case, or null for anything else - a name, rgb(), an alpha, a number
		 *	  - luminance    the relative luminance of a code parse() accepts, 0 to 1
		 *	  - contrast     the contrast ratio of two codes, 1 to 21, or null where one is none
		 *	  - truncate     a ratio cut - not rounded - to two decimals: 4.499 is written 4.49,
		 *	                 so that a number that reads as enough is enough
		 */
		colorModel : {

			ENOUGH : 4.5,

			parse : function( text ) {

				const found = typeof text === 'string' ? /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec( text.trim() ) : null;

				if( found === null )
					return null;

				const code = found[1].length === 3 ? found[1].replace( /./g, '$&$&' ) : found[1];

				return '#'+ code.toLowerCase();
			},

			luminance : function( code ) {

				const hex = Nino.adminUi.colorModel.parse( code );
				if( hex === null )
					return null;

				const channel = function( at ) {
					const value = parseInt( hex.slice( at, at + 2 ), 16 ) / 255;
					return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
				};

				return 0.2126 * channel( 1 ) + 0.7152 * channel( 3 ) + 0.0722 * channel( 5 );
			},

			contrast : function( first, second ) {

				const one = Nino.adminUi.colorModel.luminance( first );
				const two = Nino.adminUi.colorModel.luminance( second );

				if( one === null || two === null )
					return null;

				return ( Math.max( one, two ) + 0.05 ) / ( Math.min( one, two ) + 0.05 );
			},

			truncate : function( ratio ) {
				return Math.floor( ratio * 100 + 1e-9 ) / 100;
			},
		},


		/**
		 *	The lifecycle of a panel, from a spec: when the panel asks the server
		 *	for its screen, what it does with the answer, what a second showing
		 *	does and how it says that a load failed. Every panel script wrote its
		 *	own init() and showCurrent() for this - the mount that may be missing,
		 *	the request, the failure line, the flag that says the panel was loaded,
		 *	the rule for the next showing - and a panel bound to the ready event
		 *	asked for its list twice on a page opened at its hash: the shell shows
		 *	the panel before the panel's own ready callback runs. This is that
		 *	once, and a request that is in flight is asked for once.
		 *
		 *	The spec:
		 *
		 *	  - uri      the pane's uri, required: the endpoints are <uri>/<action>,
		 *	             the dirty registry knows the panel by it and the default
		 *	             error key is made of it
		 *	  - mount    the id of the element the panel draws into, required:
		 *	             where the page has none, nothing is ever requested
		 *	  - render   render( data, panel ), required: draws the screen from the
		 *	             body of an answer
		 *	  - action   the first endpoint, 'list' by default
		 *	  - payload  an object, or a function answering one, sent with the first
		 *	             request - {} by default
		 *	  - show     what showCurrent() does: 'once' (the default) asks while the
		 *	             panel is not loaded and does nothing afterwards, 'always'
		 *	             asks on every showing, 'keep' asks while the panel is not
		 *	             loaded and afterwards draws the last answer again - unless
		 *	             the dirty registry says the panel holds input nobody has
		 *	             saved, which it leaves alone
		 *	  - stamps   the kinds of data the panel shows and another panel
		 *	             writes, a list of the names touch() of the shell has
		 *	             (templates, texts, elements, slots, routes, navigations):
		 *	             the stamp of each is kept as it was when the request for
		 *	             the screen went out, and a showCurrent() with show 'keep'
		 *	             asks again, instead of drawing the last answer, when one
		 *	             of them has moved since - not while the dirty registry
		 *	             says the panel holds input nobody has saved, which is
		 *	             left alone and answered at the next showing. Nothing
		 *	             moves on a page that has no shell, and 'once' and
		 *	             'always' have their own rule for a second showing
		 *	  - boot     'lazy' (the default): the first showCurrent() starts the
		 *	             panel; 'ready': the ready event does, and a showCurrent()
		 *	             that comes first starts it too, once
		 *	  - dirty    { isDirty, save, discard, bar, shortcut }, registered under the uri at
		 *	             the first init() where the shell has its registry
		 *	  - error    the fill key of a failed load, '/_admin/<uri>/error/load'
		 *	             unless it is given
		 *	  - onError  onError( status, response, panel ), called once the
		 *	             failure is shown
		 *
		 *	It answers a plain object. The script publishes it itself, as
		 *	Nino.admin.<uri> = Nino.adminUi.panel( { ... } ), and may add members
		 *	to it or replace some: a panel that follows the url hash into a level
		 *	of its own keeps its own showCurrent(), since panel() reads nothing of
		 *	the hash (levels() is the helper for the level). Its members:
		 *
		 *	  - uri, ready (false until a load succeeded), loading (true while a
		 *	    request is out), data (the body of the last answer, null before)
		 *	  - mount()               the element, or null
		 *	  - call( action, payload, done, extra )   Nino.adminUi.api.call() for
		 *	                          <uri>/<action>
		 *	  - fail( container, status, response, [key] )   showError() with the
		 *	                          panel's key; answers its paragraph
		 *	  - init( [then] )        asks for the screen: nothing while a request is
		 *	                          out or the mount is missing; on an answer draws
		 *	                          it and calls then(), on a failure shows it. The
		 *	                          request is over before render() or fail() run,
		 *	                          so that a render may ask again
		 *	  - reload( [then] )      init() by the name a caller reads better
		 *	  - showCurrent()         what the shell calls when the panel is shown
		 *
		 *	The dirty registry is the shell's (Nino.admin.dirty), looked up when the
		 *	panel needs it and not at load: without it the panel works the same,
		 *	and 'keep' counts it as clean. Owns no strings
		 *
		 *	@param		{Object}		spec						{ uri, mount, render, action, payload, show, stamps, boot, dirty, error, onError }
		 *
		 *	@return		{Object}										The panel
		 */
		panel : function( spec ) {

			const fallback	= spec.error ?? '/_admin/'+ spec.uri+ '/error/load';
			const topics		= Array.isArray( spec.stamps ) === true ? spec.stamps : [];
			let registered	= false;
			// The stamps of the topics as they were when the screen was asked for
			let seen				= [];

			const dirty = function() {
				const registry = shell().dirty;
				return typeof registry === 'object' && registry !== null && registry.isDirty( [ spec.uri ] ) === true;
			};

			// The stamps now, in the order the spec names the topics: a page that
			// has no shell - the wizard, a test - has no counters, and nothing on
			// it ever moves
			const stamped = function() {
				const registry = shell();
				return topics.map( function( topic ) { return typeof registry.stamp === 'function' ? registry.stamp( topic ) : 0 } );
			};

			const panel = {

				uri			: spec.uri,
				ready		: false,
				loading	: false,
				data		: null,

				mount : function() {
					return dc.getElementById( spec.mount );
				},

				call : function( action, payload, done, extra ) {
					Nino.adminUi.api.call( spec.uri+ '/'+ action, payload, done, extra );
				},

				fail : function( container, status, response, key ) {
					return Nino.adminUi.showError( container, status, response, key || fallback );
				},

				init : function( then ) {

					const mount = panel.mount();

					if( panel.loading === true || mount === null )
						return;

					// The payload is made before the panel counts as loading: a payload
					// function that throws is the script's error to see, not a request
					// that is out for good and blocks every showing after it
					const payload = typeof spec.payload === 'function' ? spec.payload() : ( spec.payload ?? {} );

					panel.loading = true;

					// A write that lands while the request is out is one this answer
					// may not hold: the stamps are the ones from before it went out
					const asked = stamped();

					if( registered === false && typeof spec.dirty === 'object' && spec.dirty !== null && typeof shell().dirty === 'object' && shell().dirty !== null ) {
						shell().dirty.register( spec.uri, spec.dirty );
						registered = true;
					}

					panel.call( spec.action ?? 'list', payload, function( status, response ) {

						panel.loading = false;

						if( status === 200 ) {
							panel.data = response;
							panel.ready = true;
							seen = asked;
							spec.render( panel.data, panel );
							if( typeof then === 'function' )
								then();
							return;
						}

						panel.fail( panel.mount(), status, response );
						panel.ready = false;
						if( typeof spec.onError === 'function' )
							spec.onError( status, response, panel );
					} );
				},

				reload : function( then ) {
					panel.init( then );
				},

				showCurrent : function() {

					if( panel.ready === false || spec.show === 'always' ) {
						panel.init();
						return;
					}

					if( spec.show !== 'keep' || dirty() === true )
						return;

					// What another panel wrote since the answer was asked for is not in
					// it: ask again, and the render of that answer is the redraw
					if( stamped().some( function( stamp, at ) { return stamp !== seen[at] } ) === true ) {
						panel.init();
						return;
					}

					spec.render( panel.data, panel );
				},
			};

			if( spec.boot === 'ready' )
				Nino.events.bindCallback( 'ready', function() { panel.init() } );

			return panel;
		},

		/**
		 *	The one way a panel talks to the server. Every panel posts one
		 *	action with a json payload to the workbench's single endpoint and
		 *	gets ( status, body ) back, which used to be a copy of the same
		 *	dozen lines in every panel script - and a copy that could not know
		 *	the page had outlived its session.
		 *
		 *	Names no Nino.admin member and touches nothing at load: the setup
		 *	wizard loads this file without the workbench shell, and the panels
		 *	of a project or a feature may be older than the shell that runs
		 *	them, so Nino.http, Nino.dir and Nino.content are looked up when
		 *	a request is made
		 */
		api : {

			// The shell's answer to a session that is gone, if it registered
			// one (see onSessionLost()), and the requests waiting on the
			// check that asks whose session this is now. null while no check
			// is open - a request sent then waits with the others
			_handler	: null,
			_queue		: null,

			/**
			 *	Where the workbench answers: the project's directory plus the
			 *	endpoint with its trailing slash, the way every panel posted it
			 *	(a redirect from the slashless form can end up on the wrong
			 *	scheme behind a reverse proxy, which browsers block for XHR)
			 *
			 *	@return		{string}
			 */
			_uri : function() {
				return ( typeof Nino.dir === 'string' ? Nino.dir : '' )+ '/_admin/';
			},

			/**
			 *	Whether an answer is the end of the session rather than a
			 *	refusal: a 401 with the code 'session' (nobody is logged in) or
			 *	a 403 with the code 'csrf' (the page's token is not this
			 *	session's). Other 401s and 403s are the panel's to read - the
			 *	Users panel answers a 401 for a wrong password
			 *
			 *	@param		{Object}		xhr
			 *
			 *	@return		{boolean}
			 */
			_sessionLost : function( xhr ) {

				const body = xhr.responseJSON;
				if( body === null || typeof body !== 'object' )
					return false;

				return ( xhr.status === 401 && body.code === 'session' ) || ( xhr.status === 403 && body.code === 'csrf' );
			},

			/**
			 *	Whether the request never got an answer: a failed connection
			 *	arrives as a 500 (see Nino.http.sendRequest()), the same
			 *	number a server's own error has, and the only difference is that
			 *	a server's answer has headers. A caller's xhr without the method
			 *	(a test's) is taken to be an answer
			 *
			 *	@param		{Object}		xhr
			 *
			 *	@return		{boolean}
			 */
			_offline : function( xhr ) {
				return typeof xhr.getAllResponseHeaders === 'function' && xhr.getAllResponseHeaders() === '';
			},

			/**
			 *	Hand a request's answer to its caller, synchronously and with
			 *	what the panels have always been given: ( status, body ). A
			 *	request that never reached the server is given the code
			 *	'offline' for a body, which errorText() has a sentence for
			 *
			 *	@param		{Object}		request
			 *	@param		{Object}		xhr
			 *
			 *	@return		void
			 */
			_finish : function( request, xhr ) {
				request.callback( xhr.status, Nino.adminUi.api._offline( xhr ) === true ? { code : 'offline' } : xhr.responseJSON );
			},

			/**
			 *	Post one action
			 *
			 *	@param		{string}		action				Eg. 'users/save'
			 *	@param		{Object}		[payload]			Sent json-encoded as 'data'
			 *	@param		{Function}	callback			Called with ( status, body ), the body parsed
			 *	@param		{Object}		[extra]				Extra multipart fields (eg. { file : File })
			 *
			 *	@return		void
			 */
			call : function( action, payload, callback, extra ) {
				Nino.adminUi.api._send( { action : action, payload : payload, callback : callback, extra : extra, retried : false, xhr : null } );
			},

			/**
			 *	Send a request - or, while a session check is open, let it wait
			 *	with the others
			 *
			 *	@param		{Object}		request
			 *
			 *	@return		void
			 */
			_send : function( request ) {

				const api = Nino.adminUi.api;

				if( api._queue !== null ) {
					api._queue.push( request );
					return;
				}

				Nino.http.sendRequest( api._uri(), 'POST', function( xhr ) {

					// Anything but the end of the session goes straight back, as
					// does a second one for a request already sent again once: that
					// session is not coming back by being asked again
					if( api._handler === null || request.retried === true || api._sessionLost( xhr ) === false ) {
						api._finish( request, xhr );
						return;
					}

					// Another request found the session gone first: this one waits
					// with it for the one check
					request.xhr = xhr;
					if( api._queue !== null ) {
						api._queue.push( request );
						return;
					}

					api._queue = [ request ];
					api._check();
				}, Object.assign( { action : request.action, data : JSON.stringify( request.payload || {} ) }, request.extra || {} ) );
			},

			/**
			 *	Ask the workbench whose session this is now, and what its token
			 *	is - the answer decides what the waiting requests do: with the
			 *	same account they are sent again, with a session that is gone the
			 *	handler asks for a login, with another account they are never sent
			 *	(a form filled in for one account must not be saved by another).
			 *	The token goes into the csrf fields of the page only where the
			 *	page can use it: for a session that ended (the login that follows
			 *	needs the anonymous session's) and for the same account (the
			 *	requests are sent again). With another account the page keeps
			 *	its own, dead token - a request that does not go through this api
			 *	(a feature's panel, still on its own) would otherwise be saved
			 *	by the account that was not the one the form was filled in for
			 *
			 *	@param		{Function}	[done]				Called once it is decided, with 'resumed' (the
			 *																		requests were sent again), 'expired', 'other'
			 *																		or 'failed' and the status of the check
			 *
			 *	@return		void
			 */
			_check : function( done ) {

				const api = Nino.adminUi.api;

				const finish = function( outcome, status ) {
					if( typeof done === 'function' )
						done( outcome, status );
				};

				// The question is asked even when nothing waits any more (a login
				// that was answered after the requests it was for were given up):
				// the login rotated the token, and the page has to learn the new one
				Nino.http.sendRequest( api._uri()+ '?session=1', 'GET', function( xhr ) {

					const info = xhr.status === 200 && xhr.responseJSON !== null && typeof xhr.responseJSON === 'object' ? xhr.responseJSON : null;

					// No answer to the question: the requests that were sent get the
					// answers they had, which is all there is to tell, and the ones
					// that only waited are sent after all
					if( info === null || typeof info.user !== 'string' || typeof info.csrf !== 'string' ) {
						api._release( false );
						finish( 'failed', xhr.status );
						return;
					}

					if( info.user === '' || info.user === api._account() )
						dc.querySelectorAll('input[name="_csrf"]').forEach( function( input ) { input.value = info.csrf } );

					if( info.user === '' || info.user !== api._account() ) {
						const reason = info.user === '' ? 'expired' : 'other';
						api._handler( api._check, reason );
						finish( reason, xhr.status );
						return;
					}

					const waiting = api._queue === null ? [] : api._queue;
					api._queue = null;
					waiting.forEach( function( request ) {
						request.retried = true;
						api._send( request );
					} );
					finish( 'resumed', xhr.status );
				} );
			},

			/**
			 *	Let the waiting requests go without a session: each one gets the
			 *	answer it already had, and one that only waited is sent as it
			 *	is. The panels hear of the failure and take their forms back
			 *
			 *	@param		{boolean}		final					true when the person gave up on a login: a request that
			 *																		only waited is sent once and told what comes back,
			 *																		not held for another check (and another dialog)
			 *
			 *	@return		void
			 */
			_release : function( final ) {

				const api = Nino.adminUi.api;
				const waiting = api._queue === null ? [] : api._queue;
				api._queue = null;

				waiting.forEach( function( request ) {
					if( request.xhr !== null ) {
						api._finish( request, request.xhr );
						return;
					}
					request.retried = final;
					api._send( request );
				} );
			},

			/**
			 *	Whether requests wait for the session to come back - the dialog
			 *	holds the page until they are decided
			 *
			 *	@return		{boolean}
			 */
			waiting : function() {
				return Nino.adminUi.api._queue !== null;
			},

			/**
			 *	The person closed the dialog without logging in: the requests
			 *	that waited get the answer they already had (see _release()), so
			 *	that the panels show their error and let the input be copied out.
			 *	Nothing to do when no request waits
			 *
			 *	@return		void
			 */
			dismiss : function() {
				Nino.adminUi.api._release( true );
			},

			/**
			 *	The account this page was rendered for, as the shell wrote it
			 *	into its rail
			 *
			 *	@return		{string}
			 */
			_account : function() {

				const el = typeof dc.getElementById === 'function' ? dc.getElementById('admin-user-email') : null;

				return el !== null && typeof el.textContent === 'string' ? el.textContent.trim() : '';
			},

			/**
			 *	Register what happens when the session is gone and a login can
			 *	bring it back - the shell's dialog. Called with ( check, reason ):
			 *	check( done ) asks again whose session this is (call it after a
			 *	login) and says what became of the waiting requests through
			 *	done( outcome ), reason is 'expired' for a session that ended or 'other' for one
			 *	that now belongs to another account, where the page can only be
			 *	reloaded. Without a handler (the wizard, a test) a session
			 *	failure is handed back like any other answer
			 *
			 *	@param		{Function}	handler
			 *
			 *	@return		void
			 */
			onSessionLost : function( handler ) {
				Nino.adminUi.api._handler = typeof handler === 'function' ? handler : null;
			},

			/**
			 *	What to tell a person about a failed request. The server's code
			 *	says it in the interface language ('/_admin/error/<code>', with
			 *	the params in it); failing that the server's own message, which
			 *	is content more often than it is a log line - a veto a project's
			 *	callback wrote, the reason a feature cannot be activated - and
			 *	failing that the panel's own sentence for what it was doing
			 *	(fallbackKey, a fill key or text). The status number is in the
			 *	last two only: with a sentence that says what happened, the
			 *	number is noise, and without one it is what somebody reports. A
			 *	request that never reached the server is told as that
			 *
			 *	@param		{number}		status
			 *	@param		{*}					response					Parsed response body, if any
			 *	@param		{string}		[fallbackKey]			Fill key or text for what the panel was doing
			 *
			 *	@return		{string}
			 */
			errorText : function( status, response, fallbackKey ) {

				const body = response !== null && typeof response === 'object' ? response : {};

				if( body.code === 'offline' )
					return Nino.content.getText('/_admin/common/error/offline');

				if( typeof body.code === 'string' && /^[a-z][a-z0-9_-]*$/.test( body.code ) === true ) {
					const known = Nino.content.getText( '/_admin/error/'+ body.code );
					if( known !== '' )
						return Nino.adminUi.format( known, ...( Array.isArray( body.params ) === true ? body.params : [] ) );
				}

				const own = typeof fallbackKey === 'string' && fallbackKey !== '' ? Nino.adminUi.text( fallbackKey ) : '';

				return '('+ status+ ') '+ ( ( typeof body.error === 'string' ? body.error : '' ) || own || Nino.content.getText('/_admin/common/error/request') );
			},
		},
	};

})(window, document, document.documentElement, document.body);
