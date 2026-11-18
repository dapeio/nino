/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	datetext.js							The one way the workbench writes a date and a time it
 *													draws itself: Nino.adminUi.dateText(). German as
 *													"17.11.2026, 06:02", every other interface language as
 *													"2026-11-17, 06:02" - never with seconds, never as the
 *													raw ISO string of a file, and not as the formatter of the
 *													site's pages writes it (\Nino\Format::datetime() is for
 *													visitors, see docs/admin-ui.md, "Words"). Loads after
 *													Nino.admin.js, whose namespace it extends, and before any
 *													panel file.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	// A moment as the files and the server write it: "2026-11-17", "2026-11-17 06:02", "2026-11-17T06:02:15" - the seconds, where there are
	// some, are read and left out
	const MOMENT = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/;

	Object.assign( wn.Nino.adminUi, {

		/**
		 *	A date, and where it has one a time, in the form of the interface language. The clock is the one the value
		 *	carries: a string is not converted to any zone, a Date is read on the clock of the browser
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
	} );

})(window, document, document.documentElement, document.body);
