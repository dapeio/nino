

/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	Nino										Framework
 *	script.js								Main script
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	const script = {

		/**
		 *	Site-specific ready hook (currently unused)
		 *
		 *	@return		void
		 */
		onReady : function() {

		},


		/**
		 *	Site-specific resize hook (currently unused)
		 *
		 *	@return		void
		 */
		onResize : function() {

		},

		/**
		 *	Site-specific scroll hook (currently unused)
		 *
		 *	@return		void
		 */
		onScroll : function() {

		},
	};

	Nino.events.bindCallback( 'ready', script.onReady );
	Nino.events.bindCallback( 'scroll', script.onScroll );
	Nino.events.bindCallback( 'resize', script.onResize );

})(window, document, document.documentElement, document.body);