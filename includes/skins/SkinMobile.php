<?php
// FIXME: kill the need for this file (SkinMinerva instead)
/**
 * SkinMobile: Extends Minerva with mobile specific code
 */

class SkinMobile extends SkinMinerva {
	public $skinname = 'mobile';
	public $template = 'MobileTemplate';

	protected $hookOptions;
	protected $mode = 'stable';
	protected $customisations = array();

	/** @var array of classes that should be present on the body tag */
	private $pageClassNames = array();

	protected function getMode() {
		return $this->mode;
	}

	public function __construct( IContextSource $context ) {
		parent::__construct();
		$this->setContext( $context );
		$this->addPageClass( 'mobile' );
		$this->addPageClass( $this->getMode() );
		if ( !$this->getUser()->isAnon() ) {
			$this->addPageClass( 'is-authenticated' );
		}
	}

	/**
	 * Overrides Skin::doEditSectionLink
	 */
	public function doEditSectionLink( Title $nt, $section, $tooltip = null, $lang = false ) {
		$lang = wfGetLangObj( $lang );
		$message = wfMessage( 'mobile-frontend-editor-edit' )->inLanguage( $lang )->text();
		return Html::element( 'a', array(
			'href' => '#editor/' . $section,
			'data-section' => $section,
			'class' => 'edit-page'
		), $message );
	}

	public function setTemplateVariable( $key, $val ) {
		$this->customisations[$key] = $val;
	}

	private function applyCustomisations( $tpl ) {
		foreach( $this->customisations as $key => $value ) {
			$tpl->set( $key, $value );
		}
	}

	public function outputPage( OutputPage $out = null ) {
		global $wgMFNoindexPages;
		wfProfileIn( __METHOD__ );
		if ( !$out ) {
			$out = $this->getOutput();
		}
		if ( $wgMFNoindexPages ) {
			$out->setRobotPolicy( 'noindex,nofollow' );
		}

		$options = null;
		if ( wfRunHooks( 'BeforePageDisplayMobile', array( &$out, &$options ) ) ) {
			if ( is_array( $options ) ) {
				$this->hookOptions = $options;
			}
		}
		$html = ExtMobileFrontend::DOMParse( $out );

		wfProfileIn( __METHOD__  . '-tpl' );
		$tpl = $this->prepareTemplate();
		$tpl->set( 'headelement', $out->headElement( $this ) );
		$tpl->set( 'bodytext', $html );
		$tpl->set( 'reporttime', wfReportTime() );
		$tpl->execute();
		wfProfileOut( __METHOD__  . '-tpl' );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * @param string $className: valid class name
	 */
	private function addPageClass( $className ) {
		$this->pageClassNames[ $className ] = true;
	}

	/**
	 * Takes a title and returns classes to apply to the body tag
	 * @param $title Title
	 * @return String
	 */
	public function getPageClasses( $title ) {
		if ( $title->isMainPage() ) {
			$className = 'page-Main_Page ';
		} else if ( $title->isSpecialPage() ) {
			$className = 'mw-mf-special ';
		} else {
			$className = '';
		}
		return $className . implode( ' ', array_keys( $this->pageClassNames ) );
	}

	protected function getSearchPlaceHolderText() {
		return wfMessage( 'mobile-frontend-placeholder' )->text();
	}

	public function prepareData( BaseTemplate $tpl ) {
		parent::prepareData( $tpl );
		$search = $tpl->data['searchBox'];
		$search['placeholder'] = $this->getSearchPlaceHolderText();
		$tpl->set( 'searchBox', $search );
		$this->applyCustomisations( $tpl );
	}

	public function getSkinConfigVariables() {
		global $wgCookiePath;
		$wgUseFormatCookie = array(
			'name' => MobileContext::USEFORMAT_COOKIE_NAME,
			'duration' => -1, // in days
			'path' => $wgCookiePath,
			'domain' => $this->getRequest()->getHeader( 'Host' ),
		);
		$vars = parent::getSkinConfigVariables();
		$vars['wgUseFormatCookie'] = $wgUseFormatCookie;
		$vars['wgMFMode'] = $this->getMode();
		return $vars;
	}

	public function getDefaultModules() {
		$out = $this->getOutput();
		$modules = parent::getDefaultModules();

		// flush unnecessary modules
		$modules['content'] = array();
		$modules['legacy'] = array();

		$this->addExternalModules( $out );
		// FIXME: This is duplicate code of that in MobileFrontend.hooks.php. Please apply hygiene.
		if ( class_exists( 'ResourceLoaderSchemaModule' ) ) {
			$modules['eventlogging'] = array(
				'mobile.uploads.schema',
				'mobile.watchlist.schema',
				'mobile.editing.schema',
				'schema.MobileWebCta',
				'schema.MobileWebClickTracking',
			);
		}
		return $modules;
	}

	private function addExternalModules( $out ) {
		wfRunHooks( 'EnableMobileModules', array( $out, $this->getMode() ) );
	}

	protected function prepareTemplate() {
		global $wgAppleTouchIcon;

		wfProfileIn( __METHOD__ );
		$tpl = $this->setupTemplate( $this->template );
		$out = $this->getOutput();

		$tpl->setRef( 'skin', $this );
		$tpl->set( 'wgScript', wfScript() );

		$this->initPage( $this->getOutput() );
		$tpl->set( 'searchField', $this->getRequest()->getText( 'search', '' ) );
		$this->loggedin = $this->getUser()->isLoggedIn();
		$content_navigation = $this->buildContentNavigationUrls();
		$tpl->setRef( 'content_navigation', $content_navigation );
		$tpl->set( 'language_urls', $this->mobilizeUrls( $this->getLanguages() ) );

		// add head items
		if ( $wgAppleTouchIcon !== false ) {
			$out->addHeadItem( 'touchicon',
				Html::element( 'link', array( 'rel' => 'apple-touch-icon', 'href' => $wgAppleTouchIcon ) )
			);
		}
		$out->addHeadItem( 'canonical',
			Html::element( 'link', array( 'href' => $this->getTitle()->getCanonicalURL(), 'rel' => 'canonical' ) )
		);
		$out->addHeadItem( 'viewport',
			Html::element( 'meta', array( 'name' => 'viewport', 'content' => 'initial-scale=1.0, user-scalable=yes, minimum-scale=0.25, maximum-scale=1.6' ) )
		);
		// hide chrome on bookmarked sites
		$out->addHeadItem( 'apple-mobile-web-app-capable',
			Html::element( 'meta', array( 'name' => 'apple-mobile-web-app-capable', 'content' => 'yes' ) )
		);
		$out->addHeadItem( 'loadingscript', Html::inlineScript(
			"document.documentElement.className += ' page-loading';"
		) );

		$tpl->set( 'pagetitle', $out->getHTMLTitle() );

		$this->prepareTemplatePageContent( $tpl );
		$this->prepareFooterLinks( $tpl );

		$out->setTarget( 'mobile' );

		$bottomScripts = Html::inlineScript(
			"document.documentElement.className = document.documentElement.className.replace( 'page-loading', '' );"
		);
		$bottomScripts .= $out->getBottomScripts();
		$tpl->set( 'bottomscripts', $bottomScripts );

		wfProfileOut( __METHOD__ );
		return $tpl;
	}

	protected function prepareDiscoveryTools( QuickTemplate $tpl ) {
		global $wgMFNearby;

		$items = array(
			'home' => array(
				'text' => wfMessage( 'mobile-frontend-home-button' )->escaped(),
				'href' => Title::newMainPage()->getLocalUrl(),
				'class' => 'icon-home',
			),
			'random' => array(
				'text' => wfMessage( 'mobile-frontend-random-button' )->escaped(),
				'href' => SpecialPage::getTitleFor( 'Randompage' )->getLocalUrl( array( 'campaign' => 'random' ) ),
				'class' => 'icon-random',
				'id' => 'randomButton',
			),
			'nearby' => array(
				'text' => wfMessage( 'mobile-frontend-main-menu-nearby' )->escaped(),
				'href' => SpecialPage::getTitleFor( 'Nearby' )->getLocalURL(),
				'class' => 'icon-nearby jsonly',
			),
		);
		if ( !$wgMFNearby ) {
			unset( $items['nearby'] );
		}
		$tpl->set( 'discovery_urls', $items );
	}

	/**
	 * Prepares urls and links used by the page
	 * @param QuickTemplate
	 */
	protected function preparePersonalTools( QuickTemplate $tpl ) {
		$returnToTitle = $this->getTitle()->getPrefixedText();
		$donateTitle = SpecialPage::getTitleFor( 'Uploads' );
		$watchTitle = SpecialPage::getTitleFor( 'Watchlist' );

		// watchlist link
		$watchlistQuery = array();
		$user = $this->getUser();
		if ( $user ) {
			$view = $user->getOption( SpecialMobileWatchlist::VIEW_OPTION_NAME, false );
			$filter = $user->getOption( SpecialMobileWatchlist::FILTER_OPTION_NAME, false );
			if ( $view ) {
				$watchlistQuery['watchlistview'] = $view;
			}
			if ( $filter && $view === 'feed' ) {
				$watchlistQuery['filter'] = $filter;
			}
		}

		$items = array(
			'watchlist' => array(
				'text' => wfMessage( 'mobile-frontend-main-menu-watchlist' )->escaped(),
				'href' => $this->getUser()->isLoggedIn() ?
					$watchTitle->getLocalUrl( $watchlistQuery ) :
					$this->getLoginUrl( array( 'returnto' => $watchTitle ) ),
				'class' => 'icon-watchlist',
			),
			'uploads' => array(
				'text' => wfMessage( 'mobile-frontend-main-menu-upload' )->escaped(),
				'href' => $this->getUser()->isLoggedIn() ? $donateTitle->getLocalUrl() :
					$this->getLoginUrl( array( 'returnto' => $donateTitle ) ),
				'class' => 'icon-uploads jsonly',
			),
			'settings' => array(
				'text' => wfMessage( 'mobile-frontend-main-menu-settings' )->escaped(),
				'href' => SpecialPage::getTitleFor( 'MobileOptions' )->
					getLocalUrl( array( 'returnto' => $returnToTitle ) ),
				'class' => 'icon-settings',
			),
			'auth' => $this->getLogInOutLink(),
		);
		$tpl->set( 'personal_urls', $items );
	}

	/**
	 * Returns the site name for the footer, either as a text or <img> tag
	 */
	protected function getSitename() {
		global $wgMFCustomLogos, $wgMFTrademarkSitename;

		$footerSitename = $this->msg( 'mobile-frontend-footer-sitename' )->text();

		if ( isset( $wgMFCustomLogos['copyright'] ) ) {
			$suffix = $wgMFTrademarkSitename ? ' ®' : '';
			$sitename = Html::element( 'img', array(
				'src' => $wgMFCustomLogos['copyright'],
				'alt' => $footerSitename . $suffix
			) );
		} else {
			$suffix = $wgMFTrademarkSitename ? ' ™' : '';
			$sitename = $footerSitename . $suffix;
		}

		return $sitename;
	}

	/**
	 * Prepares links used in the footer
	 * @param QuickTemplate $tpl
	 */
	protected function prepareFooterLinks( $tpl ) {
		global $wgRightsPage, $wgRightsUrl, $wgRightsText;

		$req = $this->getRequest();

		$url = $this->mobileContext->getDesktopUrl( wfExpandUrl(
			$req->appendQuery( 'mobileaction=toggle_view_desktop' )
		) );
		if ( is_array( $this->hookOptions ) && isset( $this->hookOptions['toggle_view_desktop'] ) ) {
			$hookQuery = $this->hookOptions['toggle_view_desktop'];
			$url = $req->appendQuery( $hookQuery ) . urlencode( $url );
		}
		$url = htmlspecialchars( $url );

		$desktop = wfMessage( 'mobile-frontend-view-desktop' )->escaped();
		$mobile = wfMessage( 'mobile-frontend-view-mobile' )->escaped();

		$switcherHtml = <<<HTML
<h2>{$this->getSitename()}</h2>
<ul>
	<li>{$mobile}</li><li><a id="mw-mf-display-toggle" href="{$url}">{$desktop}</a></li>
</ul>
HTML;

		// Construct the link to the licensinsing terms
		if ( $wgRightsText ) {
			// Use shorter text for some common licensing strings. See Installer.i18n.php
			// for the currently offered strings. Unfortunately, there is no good way to
			// comprehensively support localized licensing strings since the license (as
			// stored in LocalSetttings.php) is just freeform text, not an i18n key.
			$licenses = array(
				'Creative Commons Attribution-Share Alike 3.0' => 'CC BY-SA 3.0',
				'Creative Commons Attribution Share Alike' => 'CC BY-SA',
				'Creative Commons Attribution' => 'CC BY',
				'Creative Commons Attribution Non-Commercial Share Alike' => 'CC BY-NC-SA',
				'Creative Commons Zero (Public Domain)' => 'CC0 (Public Domain)',
				'GNU Free Documentation License 1.3 or later' => 'GFDL 1.3 or later',
			);
			if ( isset( $licenses[$wgRightsText] ) ) {
				$wgRightsText = $licenses[$wgRightsText];
			}
			if ( $wgRightsPage ) {
				$title = Title::newFromText( $wgRightsPage );
				$link = Linker::linkKnown( $title, $wgRightsText );
			} elseif ( $wgRightsUrl ) {
				$link = Linker::makeExternalLink( $wgRightsUrl, $wgRightsText );
			} else {
				$link = $wgRightsText;
			}
		} else {
			$link = '';
		}
		// The license message is displayed in the content language rather than the user
		// language. See Skin::getCopyright.
		if ( $link ) {
			$licenseText = $this->msg( 'mobile-frontend-copyright' )->rawParams( $link )->inContentLanguage()->text();
		} else {
			$licenseText = '';
		}

		$tpl->set( 'mobile-switcher', $switcherHtml );
		$tpl->set( 'mobile-license', $licenseText );
		$tpl->set( 'privacy', $this->footerLink( 'mobile-frontend-privacy-link-text', 'privacypage' ) );
		$tpl->set( 'terms-use', $this->getTermsLink( 'mobile-frontend-terms-url' ) );
	}

	/**
	 * Returns HTML of terms of use link or null if it shouldn't be displayed
	 *
	 * @param $messageKey
	 *
	 * @return null|string
	 */
	public function getTermsLink( $messageKey ) {
		$urlMsg = $this->msg( $messageKey )->inContentLanguage();
		if ( $urlMsg->isDisabled() ) {
			return null;
		}
		$url = $urlMsg->plain();
		// Support both page titles and URLs
		if ( preg_match( '#^(https?:)?//#', $url ) === 0 ) {
			$title = Title::newFromText( $url );
			if ( !$title ) {
				return null;
			}
			$url = $title->getLocalURL();
		}
		return Html::element(
			'a',
			array( 'href' => $url ),
			$this->msg( 'mobile-frontend-terms-text' )->text()
		);
	}

	/**
	 * Prepares a url to the Special:UserLogin with query parameters,
	 * taking into account $wgSecureLogin
	 * @param array $query
	 * @return string
	 */
	public function getLoginUrl( $query ) {
		global $wgSecureLogin;

		if ( WebRequest::detectProtocol() != 'https' && $wgSecureLogin ) {
			$loginUrl = SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( $query );
			return $this->mobileContext->getMobileUrl( $loginUrl, $wgSecureLogin );
		}
		return SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL( $query );
	}

	/**
	 * Prepares the header and the content of a page
	 * Stores in QuickTemplate prebodytext, postbodytext keys
	 * @param QuickTemplate
	 */
	function prepareTemplatePageContent( QuickTemplate $tpl ) {
		$title = $this->getTitle();

		// If it's a talk page, add a link to the main namespace page
		if ( $title->isTalkPage() ) {
			$tpl->set( 'subject-page', Linker::link(
				$title->getSubjectPage(),
				wfMessage( 'mobile-frontend-talk-back-to-page', $title->getText() ),
				array( 'class' => 'return-link' )
			) );
		}
	}

	/**
	 * Takes an array of link elements and applies mobile urls to any urls contained in them
	 * @param $urls Array
	 * @return Array
	 */
	public function mobilizeUrls( $urls ) {
		$ctx = $this->mobileContext; // $this in closures is allowed only in PHP 5.4
		return array_map( function( $url ) use ( $ctx ) {
				$url['href'] = $ctx->getMobileUrl( $url['href'] );
				return $url;
			},
			$urls );
	}
}
