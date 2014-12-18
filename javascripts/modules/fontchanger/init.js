( function ( M, $ ) {
	var settings = M.require( 'settings' ),
		mainmenu = M.require( 'mainmenu' ),
		userFontSize = settings.get( 'userFontSize', true ),
		FontChanger = M.require( 'modules/fontchanger/FontChanger' ),
		MobileWebClickTracking = M.require( 'loggingSchemas/MobileWebClickTracking' );

	// set the user font size if needed
	if ( userFontSize !== '100' ) {
		$( '.content' ).css( 'font-size', userFontSize + '%' );
	}

	// show and add handler to fontchanger link in Special:MobileMenu
	$( '.fontchanger.link' )
		.removeClass( 'hidden' )
		.on( 'click', function () {
			// the different sizes
			var fcDrawer = new FontChanger( {} );

			// close the main menu drawer
			mainmenu.closeNavigationDrawers();

			// show the fontchanger drawer
			fcDrawer.show();
			MobileWebClickTracking.log( 'UI', 'fontchanger-menu' );
		} );
}( mw.mobileFrontend, jQuery ) );