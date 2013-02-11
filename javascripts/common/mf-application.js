// FIXME: make this an object with a constructor to facilitate testing
// (see https://bugzilla.wikimedia.org/show_bug.cgi?id=44264)
( function( M, $ ) {
	var EventEmitter = M.require( 'eventemitter' ),
		// FIXME: when mobileFrontend is an object with a constructor,
		// just inherit from EventEmitter instead
		eventEmitter = new EventEmitter(),
		scrollY;

	/**
	 * Wraps our template engine of choice (currently Hogan).
	 *
	 * @param {string} templateBody Template body.
	 * @return {Object} Template object which has a render() function that
	 * accepts template data object as its argument.
	 */
	function template( templateBody ) {
		return Hogan.compile( templateBody );
	}

	/**
	 * See EventEmitter#on.
	 */
	function on(/* event, callback */ ) {
		return eventEmitter.on.apply( eventEmitter, arguments );
	}

	/**
	 * See EventEmitter#emit.
	 */
	function emit(/* event, arg1, arg2, ... */ ) {
		return eventEmitter.emit.apply( eventEmitter, arguments );
	}

	/**
	 * @deprecated
	 */
	function message( name, arg1 ) {
		return mw.msg( name, arg1 );
	}

	// TODO: only apply to places that need it
	// http://www.quirksmode.org/blog/archives/2010/12/the_fifth_posit.html
	// https://github.com/Modernizr/Modernizr/issues/167
	function supportsPositionFixed() {
		// TODO: don't use device detection
		var agent = navigator.userAgent,
			support = false,
			supportedAgents = [
			// match anything over Webkit 534
			/AppleWebKit\/(53[4-9]|5[4-9]\d?|[6-9])\d?\d?/,
			// Android 3+
			/Android [3-9]/
		];
		supportedAgents.forEach( function( item ) {
			if( agent.match( item ) ) {
				support = true;
			}
		} );
		return support;
	}

	// Try to scroll and hide URL bar
	scrollY = window.scrollY || 0;
	if( !window.location.hash && scrollY < 10 ) {
		window.scrollTo( 0, 1 );
	}

	function triggerPageReadyHook( pageTitle, sectionData, anchorSection ) {
		emit( 'page-loaded', {
			title: pageTitle, data: sectionData, anchorSection: anchorSection
		} );
	}

	// TODO: separate main menu navigation code into separate module
	function init() {
		// FIXME: use wgIsMainPage
		var mainPage = document.getElementById( 'mainpage' ),
			$doc = $( 'html' );

		if ( mainPage ) {
			emit( 'homepage-loaded' );
		}

		$doc.removeClass( 'page-loading' );
		if( supportsPositionFixed() ) {
			$doc.addClass( 'supportsPositionFixed' );
		}

		// when rotating to landscape stop page zooming on ios
		// allow disabling of transitions in android ics 4.0.2
		function fixBrowserBugs() {
			// see http://adactio.com/journal/4470/
			var viewportmeta = document.querySelector && document.querySelector( 'meta[name="viewport"]' ),
				ua = navigator.userAgent,
				android = ua.match( /Android/ );
			if( viewportmeta && ua.match( /iPhone|iPad/i )  ) {
				viewportmeta.content = 'minimum-scale=1.0, maximum-scale=1.0';
				document.addEventListener( 'gesturestart', function() {
					viewportmeta.content = 'minimum-scale=0.25, maximum-scale=1.6';
				}, false );
			} else if( ua.match(/Android 4\.0\.2/) ){
				$doc.addClass( 'android4-0-2' );
			}
			if ( android ) {
				$doc.addClass( 'android' );
			}
		}
		fixBrowserBugs();
	}

	function getConfig( name, defaultValue ) {
		if ( mwMobileFrontendConfig.settings[ name ] !== undefined ) {
			return mwMobileFrontendConfig.settings[ name ];
		} else if ( defaultValue !== undefined ) {
			return defaultValue;
		}
		return null;
	}

	function setConfig( name, value ) {
		mwMobileFrontendConfig.settings[ name ] = value;
	}

	// FIXME: remove when we use api module everywhere
	/**
	 * @deprecated
	 */
	function getApiUrl() {
		return getConfig( 'scriptPath', '' ) + '/api.php';
	}

	// FIXME: Kill the need for this horrible function by giving me a nicer API
	function getPageArrayFromApiResponse( response ) {
		var key, results = [], pages = response.query.pages;

		for ( key in pages ) {
			if ( pages.hasOwnProperty( key ) ) {
				results.push( pages[ key ] );
			}
		}
		return results;
	}

	function isLoggedIn() {
		return getConfig( 'authenticated', false );
	}

	function getOrigin() {
		return window.location.protocol + '//' + window.location.hostname;
	}

	function prettyEncodeTitle( title ) {
		return encodeURIComponent( title.replace( / /g, '_' ) ).replace( /%3A/g, ':' ).replace( /%2F/g, '/' );
	}

	// FIXME: sandbox from mf-application.js
	function log( schemaName, data ) {
		if ( mw.eventLog ) {
			mw.eventLog.logEvent( schemaName, data );
		}
	}

	$( init );

	$.extend( M, {
		init: init,
		emit: emit,
		jQuery: typeof jQuery  !== 'undefined' ? jQuery : false,
		getApiUrl: getApiUrl,
		getOrigin: getOrigin,
		getPageArrayFromApiResponse: getPageArrayFromApiResponse,
		isLoggedIn: isLoggedIn,
		log: log,
		message: message,
		on: on,
		prefix: 'mw-mf-',
		getConfig: getConfig,
		setConfig: setConfig,
		supportsPositionFixed: supportsPositionFixed,
		triggerPageReadyHook: triggerPageReadyHook,
		prettyEncodeTitle: prettyEncodeTitle,
		utils: $, // FIXME: deprecate
		template: template
	} );

}( mw.mobileFrontend, jQuery ) );
