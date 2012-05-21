MobileFrontend.navigationLegacy = (function() {
	function init() {
		var languageSelection = document.getElementById( 'languageselection' ),
			u = MobileFrontend.utils;

		function navigateToLanguageSelection() {
			var url;
			if ( languageSelection ) {
				url = languageSelection.options[languageSelection.selectedIndex].value;
				if ( url ) {
					location.href = url;
				}
			}
		}
		u( languageSelection ).bind( 'change', navigateToLanguageSelection );

		function logoClick() {
			var n = document.getElementById( 'nav' ).style;
			n.display = n.display === 'block' ? 'none' : 'block';
		}
		u( document.getElementById( 'mw-mf-logo' ) ).bind( 'click', logoClick );
	}
	init();
	return {
		init: init
	}
}());
