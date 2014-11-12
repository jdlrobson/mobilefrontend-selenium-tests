// Determine whether or not it is appropriate to load WikiGrok, and if so, load it.
( function ( M, $ ) {
	// Only run in alpha or beta mode
	M.assertMode( [ 'beta', 'alpha' ] );

	var wikidataID = mw.config.get( 'wgWikibaseItemId' ),
		errorSchema = M.require( 'loggingSchemas/mobileWebWikiGrokError' ),
		permittedOnThisDevice = mw.config.get( 'wgMFEnableWikiGrokOnAllDevices' ) || !M.isWideScreen(),
		idOverride,
		versionConfigs = {
			A: {
				module: 'mobile.wikigrok.dialog',
				view: 'modules/wikigrok/WikiGrokDialog',
				name: 'a'
			},
			B: {
				module: 'mobile.wikigrok.dialog.b',
				view: 'modules/wikigrok/WikiGrokDialogB',
				name: 'b'
			}
		},
		versionConfig,
		WikiGrokAbTest = M.require( 'WikiGrokAbTest' ),
		wikiGrokUser = M.require( 'wikiGrokUser' );

	/*
	 * Gets the configuration for the version of WikiGrok to use.
	 *
	 * The `wikigrokversion` query parameter can be used to override this logic,
	 * `wikigrokversion=a` means that A will always be used. If the override
	 * version doesn't exist, then the default version (currently A) will be used.
	 *
	 * If the user is eligible to enter the WikiGrok AB test, then the test
	 * determines which version to use.
	 *
	 * @return {Object|null}
	 */
	function getWikiGrokConfig() {
		var versionOverride,
			versionConfig = null,
			wikiGrokAbTest = WikiGrokAbTest.newFromMwConfig();

		// See if there is a query string override
		if ( M.query.wikigrokversion ) {
			versionOverride = M.query.wikigrokversion.toUpperCase();

			if ( versionConfigs.hasOwnProperty( versionOverride ) ) {
				versionConfig = versionConfigs[versionOverride];
			}
		// Otherwise, see if A/B test is running, and if so, choose a version.
		} else if ( wikiGrokAbTest.isEnabled ) {
			versionConfig = versionConfigs[wikiGrokAbTest.getVersion( wikiGrokUser )];
		}

		return versionConfig;
	}

	versionConfig = getWikiGrokConfig();

	// Allow query string override for testing, for example, '?wikidataid=Q508703'
	if ( !wikidataID ) {
		idOverride = M.query.wikidataid;
		if ( idOverride ) {
			mw.config.set( 'wgWikibaseItemId', idOverride );
			wikidataID = idOverride;
		}
	}

	if (
		// WikiGrok is enabled
		mw.config.get( 'wgMFEnableWikiGrok' ) &&
		// We're not on the Main Page
		!mw.config.get( 'wgIsMainPage' ) &&
		// Permitted on this device
		permittedOnThisDevice &&
		// We're in 'view' mode
		mw.config.get( 'wgAction' ) === 'view' &&
		// Wikibase is active and this page has an item ID
		wikidataID &&
		// We're in Main namespace,
		mw.config.get( 'wgNamespaceNumber' ) === 0 &&
		versionConfig
	) {

		// Load the required module and view based on the version for the user
		mw.loader.using( versionConfig.module ).done( function () {
			var WikiGrokDialog = M.require( versionConfig.view );

			// Initialize the dialog and insert it into the page (but don't display yet)
			function init() {
				var dialog = new WikiGrokDialog( {
					itemId: wikidataID,
					title: mw.config.get( 'wgTitle' ),
					userToken: wikiGrokUser.getToken(),
					testing: ( idOverride ) ? true : false
				} );

				if ( $( '.toc-mobile' ).length ) {
					dialog.insertBefore( '.toc-mobile' );
				} else {
					dialog.appendTo( M.getLeadSection() );
				}
			}

			init();
		} ).fail( function () {
			var data = {
				error: 'no-impression-cannot-load-interface',
				taskType: 'version ' + versionConfig.name,
				taskToken: mw.user.generateRandomSessionId(),
				userToken: wikiGrokUser.getToken(),
				isLoggedIn: !wikiGrokUser.isAnon()
			};
			if ( idOverride ) {
				data.testing = true;
			}
			errorSchema.log( data );
		} );

		// Make OverlayManager handle '#/wikigrok/about' links.
		M.overlayManager.add( /^\/wikigrok\/about$/, function () {
			var d = $.Deferred();
			mw.loader.using( 'mobile.wikigrok.dialog' ).done( function () {
				var WikiGrokMoreInfo = M.require( 'modules/wikigrok/WikiGrokMoreInfo' );
				d.resolve( new WikiGrokMoreInfo() );
			} );
			return d;
		} );
	}
}( mw.mobileFrontend, jQuery ) );
