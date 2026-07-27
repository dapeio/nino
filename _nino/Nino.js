/**
 *	Nino										A compact filesystembased php framework
 *	Modules									Optional modules
 *	NinoJS									Basic js functionality & helper - client detection, cookies,
 *													dom-ready/resize/scroll events, xhr requests, auth,
 *													jstext lookups. Used by _admin/_dev and the public site
 *													alike. Frontend design-system behaviors (cover, parallax,
 *													vpa, autoheight, slider, generic forms) live separately
 *													in Nino.ui.js, since only the public site needs those.
 *
 *	@package								Dape/Nino
 *	@author									David Perchermeier <mail@dape.io>
 *	@link										https://github.com/dapeio/nino
 */

( function(wn,dc,dE,bd) {

	wn.Nino = {

		auth : {

			/**
			 *	Log in a user and redirect on success
			 *
			 *	@param		{string}		user					Username / mail
			 *	@param		{string}		pw						Password
			 *	@param		{string}		redirect			Uri to redirect to on success
			 *	@param		{Function}	[onError]			(optional) Called with the xhr on a failed login
			 *
			 *	@return		void
			 */
			login : function( user, pw, redirect, onError ) {
				onError = ( typeof onError === 'function' ) ? onError : function(){};
				Nino.http.sendRequest(
					'/.nino/auth/login',
					'POST',
					function( xhr ) { if( xhr.status !== 200 ) return onError( xhr ); wn.location.replace( redirect ) },
					{},
					{ 'user' : user, 'pw' : pw }
				);
			},

			/**
			 *	Log out the current user and redirect
			 *
			 *	@param		{string}		redirect			Uri to redirect to afterwards
			 *
			 *	@return		void
			 */
			logout : function( redirect ) {
				Nino.http.sendRequest(
					'/.nino/auth/logout',
					'POST',
					function(){ window.location.replace( redirect ) }
				);
			},

		},

		client : {
			/**
			 *	Detect and store whether the current client is a mobile device
			 *
			 *	@return		void
			 */
			init : function() {
				Nino.client.isMobile = /Mobi|Android|iPhone|Android|webOS|iPhone|iPod|Blackberry|iPad/i.test( navigator.userAgent );
			},
			isMobile : false,


		},

		content : {

			/**
			 *	Return a translated text for a jstext key
			 *
			 *	@param		{string}		key						Text key, eg. '/form/info/success'
			 *
			 *	@return		{string}									Translated text or an empty string
			 */
			getText : function( key ) {
				return Nino.content.text[key] || '';
			},

		},


		controller : {

			/**
			 *	Bootstrap Nino: load js text translations, capture the current
			 *	request and initialize client detection and the event system
			 *
			 *	@return		void
			 */
			init : function() {
				Nino.content.text = ( typeof wn.NinoJstext !== 'undefined' ) ? wn.NinoJstext : [];
				Nino.http.request	= {
					'uri'						: wn.location.pathname,
					'domain'				: wn.location.origin,
					'query'					: Nino.http.readQueryVars(),
				};
				Nino.client.init();
				Nino.events.init();
			},
		},

		cookie : {

			/**
			 *	Set a cookie for one year, valid for the whole site
			 *
			 *	@param		{string}		key						Cookie name
			 *	@param		{string}		val						Cookie value (will be uri-encoded)
			 *
			 *	@return		void
			 */
			set : function(key,val) {
        dc.cookie = key + '=' + encodeURIComponent(val) + '; expires=' + ( new Date(Date.now() + 365 * 864e5).toUTCString() ) + '; path=/';
			},

			/**
			 *	Read a cookie value by name
			 *
			 *	@param		{string}		key						Cookie name
			 *
			 *	@return		{string|null}							Cookie value, or null if not set
			 */
			get : function(key) {
				if(!key) return null;
			  const match = document.cookie.match( new RegExp('(?:^|; )' + key.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)') );
			  return match ? decodeURIComponent(match[1]) : null;
			},

		},

		events : {

			_events : {
				'ready'	: [],
				'resize' : [],
				'scroll' : [],
			},
			_calls : {
				'ready' : false,
				'resize' : false,
				'scroll' : false,
			},

			/**
			 *	Register a callback for a lifecycle event ('ready'/'resize'/'scroll').
			 *	If the event already fired once, the callback is invoked immediately.
			 *
			 *	@param		{string}		evt						Event name ('ready', 'resize' or 'scroll')
			 *	@param		{Function}	cb						Callback to register
			 *
			 *	@return		void
			 */
			bindCallback : function( evt, cb ) {

				if( typeof Nino.events._events[evt] === 'undefined' )
					return;

				Nino.events._events[evt].push( cb );

				if( Nino.events._calls[evt] !== false )
					cb.call( cb, Nino.events._calls[evt] );
			},

			/**
			 *	Wire up the ready/resize/scroll lifecycle events and dispatch an
			 *	initial resize/scroll once the document is ready
			 *
			 *	@return		void
			 */
			init : function() {

				// domReady
				/**
				 *	Fire all registered 'ready' callbacks once, either on
				 *	DOMContentLoaded or immediately if already ready
				 *
				 *	@param		{Event}		e							domReady event (or the setTimeout call)
				 *
				 *	@return		void
				 */
				const cbReady = function( e ) {
					if( Nino.events._calls['ready'] !== false )
						return;

					wn.dispatchEvent( new Event( 'resize' ) );
					wn.dispatchEvent( new Event( 'scroll' ) );

					Nino.events._calls['ready'] = e;
					Nino.events._events['ready'].forEach( cb => { cb(Nino.events._calls['ready']) } );
				};

				if( dc.readyState === 'complete' || dc.readyState === 'interactive' )
					setTimeout( cbReady, 1 );
				else
					dc.addEventListener( 'DOMContentLoaded', cbReady );

				// onResize
				const resizeEvent = ( Nino.client.isMobile === false ) ? 'resize' : 'deviceorientation';
				// Throttle native resize/orientation events and notify registered 'resize' callbacks
				wn.addEventListener( resizeEvent, ( e ) => {
					if( Nino.events._calls['resize'] !== false )
						return;

					Nino.events._calls['resize'] = e;
					window.requestAnimationFrame( () => { Nino.events._events['resize'].forEach( cb => { cb.call(cb,Nino.events._calls['scroll']) } ) } );
					Nino.events._calls['resize'] = false;
				} );

				// onScroll
				// Throttle native scroll events and notify registered 'scroll' callbacks
				wn.addEventListener( 'scroll', ( e ) => {
					if( Nino.events._calls['scroll'] !== false )
						return;

					Nino.events._calls['scroll'] = e;
					window.requestAnimationFrame( () => { Nino.events._events['scroll'].forEach( cb => { cb.call(cb,Nino.events._calls['scroll']) } ) } );
					Nino.events._calls['scroll'] = false;
				} );
			},
		},


		http : {

			/**
			 *	Navigate the browser to a uri
			 *
			 *	@param		{string}		uri						Target uri
			 *
			 *	@return		void
			 */
			redirect : function( uri ) {
				wn.location.replace(uri);
			},

			/**
			 *	Parse the current location's query string into a key/value array
			 *
			 *	@return		{Array}										Parsed query vars
			 */
			readQueryVars : function( ) {
				let
					result = [],
					tmp = [],
					a = location.search.substr(1).split("&"),
					l = a.length;
				for( let i = 0; i < l; i++ ) {
						tmp = a[i].split("=");
						result[tmp[0]] = decodeURIComponent(tmp[1]);
				}

				return result;
			},

			/**
			 *	Send an xhr request with optional form data and basic-auth
			 *	credentials, normalizing the response/error into one callback.
			 *	The page's csrf token (from the [csrf] shortcode) is attached
			 *	to `data` automatically unless already set. The parsed json
			 *	body (or the raw text, if it isn't json) is passed back on
			 *	xhr.responseJSON, since the native xhr.response is read-only.
			 *
			 *	@param		{string}		uri						Target uri
			 *	@param		{string}		method				Http method
			 *	@param		{Function}	callback			Called with the (normalized) xhr once done
			 *	@param		{Object}		[data]				(optional) Key/value pairs sent as form data
			 *	@param		{Object}		[auth]				(optional) { user, pw } sent as basic-auth header
			 *
			 *	@return		void
			 */
			sendRequest : function( uri, method, callback, data, auth ) {
				let
					formdata		= new FormData(),
					xhr 				= new XMLHttpRequest(),
					/**
					 *	Normalize the xhr result (parse a json body, map network
					 *	error types to a status code) before calling back
					 *
					 *	@param		{Event}		e							Load/error/timeout/abort event
					 *
					 *	@return		void
					 */
					responseFn = function(e) {

					  const xhrCopy = xhr;
					  // xhr.response is a read-only accessor (assigning to it is a silent no-op),
					  // so the parsed body goes on a separate .responseJSON property instead
					  try { xhrCopy.responseJSON = JSON.parse( xhrCopy.responseText ); } catch (e) { xhrCopy.responseJSON = xhrCopy.responseText; }

					  if( e.type === 'error' ) xhrCopy.status = 500;
					  if( e.type === 'timeout' ) xhrCopy.status = 408;
					  if( e.type === 'abort' ) xhrCopy.status = 499;

					  callback.call( callback, xhrCopy );
					};

				xhr.open( method, uri, true );
				xhr.onload				= responseFn;
			  xhr.onerror 			= responseFn;
			  xhr.ontimeout 		= responseFn;
			  xhr.onabort 			= responseFn;

				// Data
				data = ( data && typeof data === 'object' ) ? data : {};

				// Auto-attach the page's csrf token (added by the [csrf] shortcode) to every POST,
				// so callers like Nino.auth.login/logout don't each have to know about it
				if( typeof data._csrf === 'undefined' ) {
					const csrfInput = dc.querySelector( 'input[name="_csrf"]' );
					if( csrfInput !== null )
						data._csrf = csrfInput.value;
				}

				Object.keys( data ).forEach( function( key ) { formdata.append( key, data[key] ) } );

				// Auth
				if( typeof auth !== 'undefined' && typeof auth.user !== 'undefined' && typeof auth.pw !== 'undefined' )
					xhr.setRequestHeader( 'Authorization', "Basic " + btoa( auth.user + ':' + auth.pw ) );

				xhr.send( formdata );
			},
		},
	};

	Nino.controller.init();

})(window, document, document.documentElement, document.body);