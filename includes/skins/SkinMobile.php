<?php

class SkinMobile extends SkinMobileBase {
	public $skinname = 'mobile';
	public $stylename = 'mobile';
	public $template = 'SkinMobileTemplate';

	protected function prepareTemplate() {
		global $wgAppleTouchIcon, $wgMFCustomLogos, $wgVersion, $wgMFTrademarkSitename,
			$wgMFEnableSiteNotice;

		wfProfileIn( __METHOD__ );
		$tpl = parent::prepareTemplate();
		$out = $this->getOutput();
		$title = $this->getTitle();
		$user = $this->getUser();
		$tpl->set( 'title', $title );
		$tpl->set( 'user', $user );
		$tpl->set( 'menuButton', $this->getMenuButton() );
		$specialPage = $title->isSpecialPage();
		$context = MobileContext::singleton();

		$device = $context->getDevice();
		$inBeta = $context->isBetaGroupMember();
		$inAlpha = $context->isAlphaGroupMember();

		$tpl->set( 'action', $context->getRequest()->getText( 'action' ) );
		$tpl->set( 'isAlphaGroupMember', $inAlpha );
		$tpl->set( 'isBetaGroupMember', $inBeta );

		// add head items
		if ( $wgAppleTouchIcon !== false ) {
			$out->addHeadItem( 'touchicon',
				Html::element( 'link', array( 'rel' => 'apple-touch-icon', 'href' => $wgAppleTouchIcon ) )
			);
		}
		$out->addHeadItem( 'canonical',
			Html::element( 'link', array( 'href' => $title->getCanonicalURL() ) )
		);
		$out->addHeadItem( 'viewport',
			Html::element( 'meta', array( 'name' => 'viewport', 'content' => 'initial-scale=1.0, user-scalable=yes' ) )
		);
		$out->addHeadItem( 'loadingscript', Html::inlineScript(
			"document.documentElement.className += ' page-loading';"
		) );

		$tpl->set( 'pagetitle', $out->getHTMLTitle() );

		$this->prepareTemplatePageContent( $tpl );
		$this->prepareTemplateLinks( $tpl );

		$tpl->set( 'isMainPage', $title->isMainPage() );
		$tpl->set( 'languageCount', count( $this->getLanguageUrls() ) + 1 );

		wfProfileIn( __METHOD__ . '-modules' );

		wfProfileOut( __METHOD__ . '-modules' );

		// setup destinations for styles/scripts at top and at bottom
		$tpl = $this->attachResources( $title, $tpl, $device );

		$tpl->set( 'isSpecialPage', $title->isSpecialPage() );

		// footer
		$tpl->set( 'copyright', $this->getCopyright() );

		// display site notice
		$tpl->set( 'enableSiteNotice', $wgMFEnableSiteNotice );

		$footerSitename = $this->msg( 'mobile-frontend-footer-sitename' )->text();
		if ( is_array( $wgMFCustomLogos ) && isset( $wgMFCustomLogos['copyright'] ) ) {
			if ( $wgMFTrademarkSitename ) {
				$suffix = ' ®';
			} else {
				$suffix = '';
			}
			$license = Html::element( 'img', array(
				'src' => $wgMFCustomLogos['copyright'],
				'class' => 'license',
				'alt' => "{$footerSitename}" . $suffix
			) );
		} else {
			if ( $wgMFTrademarkSitename ) {
				$suffix = ' ™';
			} else {
				$suffix = '';
			}
			$license = Html::element( 'span', array( 'class' => 'license' ),
				"{$footerSitename}" . $suffix
			);
		}
		$tpl->set( 'license', $license );

		// @todo: kill me with fire
		if ( version_compare( $wgVersion, '1.20alpha', '<' ) ) {
			$tpl->set( 'bcHack', '<script type="text/javascript">mw={loader:{state:function(){}}};</script>' );
		} else {
			$tpl->set( 'bcHack', '' );
		}

		wfProfileOut( __METHOD__ );
		return $tpl;
	}


	/**
	 * Prepares urls and links used by the page
	 * @param QuickTemplate
	 */
	public function prepareTemplateLinks( QuickTemplate $tpl ) {
		$title = $this->getTitle();
		$req = $this->getRequest();
		$ctx = MobileContext::singleton();
		$returnToTitle = $title->getPrefixedText();

		$donateTitle = SpecialPage::getTitleFor( 'Uploads' );
		if ( $this->getUser()->isLoggedIn() ) {
			$donateUrl = $donateTitle->getLocalUrl();
		} else {
			$donateUrl = static::getLoginUrl( array( 'returnto' => $donateTitle ) );
		}

		// urls that do not vary on authentication status
		if ( !$title->isSpecialPage() ) {
			$historyUrl = $ctx->getMobileUrl( wfExpandUrl( $req->appendQuery( 'action=history' ) ) );
			$historyKey = 'mobile-frontend-footer-contributors-text';
			// FIXME: this creates a link with class external - it should be local
			$historyLink = wfMessage( $historyKey, htmlspecialchars( $historyUrl ) )->parse();
		} else {
			$historyLink = '';
		}
		$nearbyUrl = SpecialPage::getTitleFor( 'Nearby' )->getLocalURL();
		$settingsUrl = SpecialPage::getTitleFor( 'MobileOptions' )->
			getLocalUrl( array( 'returnto' => $returnToTitle ) );

		// set urls
		$tpl->set( 'donateImageUrl', $donateUrl );
		$tpl->set( 'historyLink', $historyLink );
		$tpl->set( 'nearbyURL', $nearbyUrl );
		$tpl->set( 'settingsUrl', $settingsUrl );
		$tpl->set( 'disclaimerLink', $this->disclaimerLink() );
		$tpl->set( 'privacyLink', $this->footerLink( 'mobile-frontend-privacy-link-text', 'privacypage' ) );
		$tpl->set( 'aboutLink', $this->footerLink( 'mobile-frontend-about-link-text', 'aboutpage' ) );
		$tpl->set( 'logInOut', $this->getLogInOutLink() );
	}

	/**
	 * Prepares a url to the Special:UserLogin with query parameters,
	 * taking into account $wgMFForceSecureLogin
	 * @param array $query
	 * @return string
	 */
	public static function getLoginUrl( $query ) {
		global $wgMFForceSecureLogin;

		if ( WebRequest::detectProtocol() != 'https' && $wgMFForceSecureLogin ) {
			$ctx = MobileContext::singleton();
			$loginUrl = SpecialPage::getTitleFor( 'UserLogin' )->getFullURL( $query );
			return $ctx->getMobileUrl( $loginUrl, $wgMFForceSecureLogin );
		}
		return SpecialPage::getTitleFor( 'UserLogin' )->getLocalURL( $query );
	}

	/**
	 * Prepares the header and the content of a page
	 * Stores in QuickTemplate prebodytext, postbodytext keys
	 * @param QuickTemplate
	 */
	function prepareTemplatePageContent( QuickTemplate $tpl ) {
		$title = $this->getTitle();
		$isSpecialPage = $title->isSpecialPage();
		$isMainPage = $title->isMainPage();
		$user = $this->getUser();
		$userLogin = $title->isSpecial( 'Userlogin' );
		$out = $this->getOutput();
		$inAlpha = MobileContext::singleton()->isAlphaGroupMember();

		if ( $userLogin ) {
			$pageHeading = $this->getLoginPageHeading();
		} else {
			$pageHeading = $out->getPageTitle();
		}

		$preBodyText = '';
		$postBodyText = '';
		if ( !$isSpecialPage ) {
			$headingOptions = array();
			if ( $isMainPage ) {
				$pageHeading = $user->isLoggedIn() ?
					wfMessage( 'mobile-frontend-logged-in-homepage-notification', $user->getName() )->text() : '';
			} else {
				$headingOptions = array( 'id' => 'section_0' );
			}
			// prepend heading to articles
			if ( $pageHeading ) {
				$preBodyText = Html::rawElement( 'h1', $headingOptions, $pageHeading );
				// talk page link for logged in alpha users
				if ( $inAlpha && $user->isLoggedIn() ) {
					$talkLabel = wfMessage( 'mobile-frontend-talk-overlay-header' ); // FIXME: make this the number of sections on the talk page
					if ( $title->getNamespace() !== NS_TALK ) {
						$preBodyText .= Html::element( 'a',
							array( 'href' => $title->getTalkPage()->getFullUrl(), 'id' => 'talk' ),
							$talkLabel );
					}
				}
			}

			$timestamp = Revision::getTimestampFromId( $this->getTitle(), $this->getRevisionId() );
			$lastModified = wfMessage( 'mobile-frontend-last-modified-date',
				$this->getLanguage()->userDate( $timestamp, $user ),
				$this->getLanguage()->userTime( $timestamp, $user )
			)->parse();
			$timestamp = wfTimestamp( TS_UNIX, $timestamp );
			$postBodyText = $this->buildLanguageSelection();
			// add last modified timestamp
			$postBodyText .= "<p id=\"mw-mf-last-modified\" data-timestamp=\"$timestamp\">$lastModified</p>";
		}

		$htmlHeader = $this->getHtmlHeader();
		if ( !$htmlHeader && $isSpecialPage ) {
			$htmlHeader = Html::element( 'h1', array( 'class' => 'header' ), $pageHeading );
		}

		$tpl->set( 'prebodytext', $preBodyText );
		$tpl->set( 'postbodytext', $postBodyText );
		$tpl->set( 'htmlHeader', $htmlHeader );
	}

	/**
	 * Determines what the heading of the login page should be based on the context
	 * @return string
	 */
	protected function getLoginPageHeading() {
		if ( $this->getRequest()->getVal( 'type' ) == 'signup' ) {
			$key = 'mobile-frontend-sign-up-heading';
		} else {
			$key = 'mobile-frontend-sign-in-heading';
		}
		return wfMessage( $key )->plain();
	}

	protected function attachResources( Title $title, QuickTemplate $tpl, IDeviceProperties $device ) {
		global $wgResourceModules, $wgMFVaryResources;

		$rlSupport = $device->supportsJQuery();
		$out = $this->getOutput();
		$out->setTarget( 'mobile' );
		$context = MobileContext::singleton();

		$moduleNames = $this->getEnabledModules( $wgResourceModules, $title );
		$contextModules = $this->attachAdditionalPageResources( $title, $context );

		// attach styles
		$out->addModuleStyles( 'mobile.styles' );
		if ( count( $contextModules['top'] ) > 0 ) {
			$out->addModuleStyles( $contextModules['top'] );
		}
		// add device specific CSS separately to avoid cache fragmentation
		if ( $wgMFVaryResources ) {
			$out->addModuleStyles( 'mobile.device.detect' );
		} elseif ( $device->moduleName() ) {
			$out->addModuleStyles( $device->moduleName() );
		}

		// attach modules
		if ( $rlSupport || $wgMFVaryResources ) {
			$out->addModules( $moduleNames['top'] );
			$out->addModules( $moduleNames['bottom'] );
			$out->addModules( $contextModules['bottom'] );

			// FIXME: EditPage.php adds an inline script that breaks editing without this - dirty hack
			if ( in_array( 'mobile.action.edit', $contextModules['bottom'] ) ) {
				$bottomScripts = Html::inlineScript(
					'if(typeof(mw) != "undefined") { mw.loader.implement("mediawiki.action.edit", [],{},{});' .
					'mw.toolbar = { addButton: function(){}, init: function(){} }; }'
				);
			} else {
				$bottomScripts = '';
			}

			$bottomScripts .= $out->getBottomScripts();
		} else {
			$bottomScripts = '';
		}
		$bottomScripts .= Html::inlineScript(
			"document.documentElement.className = document.documentElement.className.replace( 'page-loading', '' );"
		);

		$tpl->set( 'bottomScripts', $bottomScripts );
		return $tpl;
	}

	/**
	 * Gathers potential javascript modules to load
	 * @param array $modules
	 * @param Title $title
	 *
	 * @return array
	 */
	public function getEnabledModules( array $modules, Title $title ) {
		$context = MobileContext::singleton();
		$inBeta = $context->isBetaGroupMember();
		$inAlpha = $context->isAlphaGroupMember();

		$headModuleNames = array();
		$moduleNames = array();

		// gather modules
		foreach( $modules as $moduleName => $module ) {
			if ( isset( $module['mobileTargets'] ) ) {
				$targets = $module['mobileTargets'];
			} else {
				$targets = array(); // by default assume none - modules might want to add themselves programmatically.
			}
			if ( isset( $module['position'] ) ) {
				$pos = $module['position'];
			} else {
				$pos = 'bottom';
			}

			$stableModule = !$inBeta && !$inAlpha && in_array( 'stable', $targets );
			$betaModule = $inBeta && !$inAlpha && in_array( 'beta', $targets );
			$alphaModule = $inAlpha && in_array( 'alpha', $targets );
			$enabledModule = $stableModule || $betaModule || $alphaModule;

			if ( $enabledModule ) {
					if ( $pos == 'top' ) {
						$headModuleNames[] = $moduleName;
					} else {
						$moduleNames[] = $moduleName;
					}
			}
		}

		return array(
			'top' => $headModuleNames,
			'bottom' => $moduleNames,
		);
	}

	/**
	 * @param Title $title
	 * @param MobileContext $context
	 *
	 * @return array
	 */
	protected function attachAdditionalPageResources( Title $title, MobileContext $context ) {
		global $wgResourceModules;
		$isFilePage = $title->getNamespace() == NS_FILE;
		$action = $context->getRequest()->getText( 'action' );
		$isSpecialPage = $title->isSpecialPage();

		$moduleNames = array();
		$headModuleNames = array();

		// specific to current context
		if ( $isFilePage ) {
			$moduleNames[] = 'mobile.file.scripts';
			$headModuleNames[] = 'mobile.file.styles';
		}

		if ( $isSpecialPage ) {
			list( $name, /* $subpage */ ) = SpecialPageFactory::resolveAlias( $title->getDBkey() );
			$id = strtolower( $name );
			$specialStyleModuleName = 'mobile.' . $id . '.styles';
			$specialScriptModuleName = 'mobile.' . $id . '.scripts';

			if ( isset( $wgResourceModules[ $specialStyleModuleName ] ) ) {
				$headModuleNames[] = $specialStyleModuleName;
			}

			if ( isset( $wgResourceModules[ $specialScriptModuleName ] ) ) {
				$module = $wgResourceModules[ $specialScriptModuleName ];
				if ( isset( $module['position'] ) && $module['position'] === 'top' ) {
					$headModuleNames[] = $specialScriptModuleName;
				} else {
					$moduleNames[] = $specialScriptModuleName;
				}
			}
		} else {
			$headModuleNames[] = 'mobile.styles.page';
		}

		if ( $action === 'edit' ) {
			$moduleNames[] = 'mobile.action.edit';
		} else if ( $action === 'history' ) {
			$moduleNames[] = 'mobile.action.history';
		}

		return array(
			'top' => $headModuleNames,
			'bottom' => $moduleNames,
		);
	}

	public function buildLanguageSelection() {
		wfProfileIn( __METHOD__ );
		$supportedLanguages = array();
		if ( is_array( $this->hookOptions ) && isset( $this->hookOptions['supported_languages'] ) ) {
			$supportedLanguages = $this->hookOptions['supported_languages'];
		}
		$languageUrls = $this->getLanguageUrls();
		$languageVariantUrls = $this->getLanguageVariantUrls();
		if ( empty( $languageUrls ) && count( $languageVariantUrls ) <= 1 ) {
			wfProfileOut( __METHOD__ );
			return '';
		}

		// language variants list
		if ( count( $languageVariantUrls ) > 1 ) {
			$msgLanguageVariants = wfMessage( 'mobile-frontend-language-variant-header' )->text();
			$languageVariants = '<p id="mw-mf-language-variant-header">' . $msgLanguageVariants . '</p>';
			$languageVariants .= Html::openElement( 'ul', array( 'id' => 'mw-mf-language-variant-selection' ) );

			foreach ( $languageVariantUrls as $languageVariantUrl ) {
				$languageVariants .= Html::openElement( 'li' ) . Html::element( 'a',
					array( 'href' => $languageVariantUrl['href'],
						'lang' => $languageVariantUrl['lang'],
						'hreflang' => $languageVariantUrl['lang']
					),
					$languageVariantUrl['text'] ) . Html::closeElement( 'li' );
				}

			$languageVariants .= Html::closeElement( 'ul' );
		} else {
			$languageVariants = '';
		}

		// languages list
		$msgLanguages = wfMessage( 'mobile-frontend-language-header', count( $languageUrls ) )->text();
		$languages = '<p id="mw-mf-language-header">' . $msgLanguages . '</p>';
		$languages .= Html::openElement( 'ul', array( 'id' => 'mw-mf-language-selection' ) );

		foreach ( $languageUrls as $languageUrl ) {
			$languageUrlHref = $languageUrl['href'];
			$languageUrlLanguage = $languageUrl['language'];
			if ( is_array( $supportedLanguages ) && in_array( $languageUrl['lang'], $supportedLanguages ) ) {
				if ( isset( $this->hookOptions['toggle_view_desktop'] ) ) {
					$request = $this->getRequest();
					$returnto = $request->appendQuery( $this->hookOptions['toggle_view_desktop'] );
					$languageUrlHref =  $returnto  .
						urlencode( $languageUrlHref );
				}
			}
			$languages .= Html::openElement( 'li' ) . Html::element( 'a',
				array( 'href' => $languageUrlHref,
					'lang' => $languageUrl['lang'],
					'hreflang' => $languageUrl['lang']
				),
				$languageUrlLanguage ) . Html::closeElement( 'li' );
		}

		$languages .= Html::closeElement( 'ul' );

		$heading = wfMessage( 'mobile-frontend-language-article-heading' )->text();
		$output = <<<HTML
			<div class="section" id="mw-mf-language-section">
				<h2 id="section_language" class="section_heading">{$heading}</h2>
				<div id="content_language" class="content_block">
					{$languageVariants}
					{$languages}
				</div>
			</div>
HTML;
		wfProfileOut( __METHOD__ );
		return $output;
	}


	public function getLanguageUrls() {
		global $wgContLang;

		wfProfileIn( __METHOD__ );
		$context = MobileContext::singleton();
		$languageUrls = array();
		$out = $this->getOutput();

		foreach ( $out->getLanguageLinks() as $l ) {
			$tmp = explode( ':', $l, 2 );
			$class = 'interwiki-' . $tmp[0];
			$lang = $tmp[0];
			unset( $tmp );
			$nt = Title::newFromText( $l );
			if ( $nt ) {
				$languageUrl = $context->getMobileUrl( $nt->getFullURL() );
				$languageUrls[] = array(
					'href' => $languageUrl,
					'text' => ( $wgContLang->fetchLanguageName( $nt->getInterwiki() ) != ''
						? $wgContLang->fetchLanguageName( $nt->getInterwiki() )
						: $l ),
					'language' => $wgContLang->fetchLanguageName( $lang ),
					'class' => $class,
					'lang' => $lang,
				);
			}
		}
		wfProfileOut( __METHOD__ );

		return $languageUrls;
	}

	public function getLanguageVariantUrls() {
		global $wgDisableLangConversion;

		wfProfileIn( __METHOD__ );
		$languageVariantUrls = array();
		$title = $this->getRelevantTitle();
		$user = $this->getUser();
		$userCanRead = $title->quickUserCan( 'read', $user );

		if ( $userCanRead && !$wgDisableLangConversion ) {
			$pageLang = $title->getPageLanguage();
			// Gets list of language variants
			$variants = $pageLang->getVariants();
			// Checks that language conversion is enabled and variants exist
			// And if it is not in the special namespace
			if ( count( $variants ) > 1 ) {
				// Loops over each variant
				foreach ( $variants as $code ) {
					// Gets variant name from language code
					$varname = $pageLang->getVariantname( $code );
					// Checks if the variant is marked as disabled
					if ( $varname == 'disable' ) {
						// Skips this variant
						continue;
					}
					// Appends variant link
					$languageVariantUrls[] = (array(
						'text' => $varname,
						'href' => $title->getLocalURL( array( 'variant' => $code ) ),
						'lang' => $code
					));
				}
			}
		}

		wfProfileOut( __METHOD__ );

		return $languageVariantUrls;
	}

	private function getLogInOutLink() {
		global $wgMFForceSecureLogin;
		wfProfileIn( __METHOD__ );
		$context = MobileContext::singleton();
		$query = array();
		if ( !$this->getRequest()->wasPosted() ) {
			$returntoquery = $this->getRequest()->getValues();
			unset( $returntoquery['title'] );
			unset( $returntoquery['returnto'] );
			unset( $returntoquery['returntoquery'] );
		}
		$query[ 'returnto' ] = $this->getTitle()->getPrefixedText();
		if ( $this->getUser()->isLoggedIn() ) {
			if ( !empty( $returntoquery ) ) {
				$query[ 'returntoquery' ] = wfArrayToCgi( $returntoquery );
			}
			$url = SpecialPage::getTitleFor( 'UserLogout' )->getFullURL( $query );
			$url = $context->getMobileUrl( $url, $wgMFForceSecureLogin );
			$link = Linker::makeExternalLink(
				$url,
				wfMessage( 'mobile-frontend-main-menu-logout' )->escaped(),
				true,
				'',
				array( 'class' => 'logout' )
			);
		} else {
			 // note returnto is not set for mobile (per product spec)
			$returntoquery[ 'welcome' ] = 'yes';
			$query[ 'returntoquery' ] = wfArrayToCgi( $returntoquery );
			$url = SpecialPage::getTitleFor( 'UserLogin' )->getFullURL( $query );
			$url = $context->getMobileUrl( $url, $wgMFForceSecureLogin );
			$link = Linker::makeExternalLink(
				$url,
				wfMessage( 'mobile-frontend-main-menu-login' )->escaped(),
				true,
				'',
				array( 'class' => 'login' )
			);
		}
		wfProfileOut( __METHOD__ );
		return $link;
	}

	public function getMenuButton() {
		$url = SpecialPage::getTitleFor( 'MobileMenu' )->getLocalUrl() . '#mw-mf-page-left';
		return Html::element( 'a', array(
				'title' => wfMessage( 'mobile-frontend-main-menu-button-tooltip' )->text(),
				'href' => $url,
				'id'=> 'mw-mf-main-menu-button',
			) );
	}
}
