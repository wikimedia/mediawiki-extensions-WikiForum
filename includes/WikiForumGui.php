<?php

use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * Graphical User Interface (GUI) methods used by WikiForum extension.
 *
 * All class methods are static.
 *
 * SECURITY NOTES:
 * ===============
 * This class handles user input and HTML generation. To prevent XSS vulnerabilities:
 *
 * 1. AUTOMATIC ESCAPING:
 *    - Html::element() and Html::openElement() automatically escape ALL content and attributes
 *    - Message::escaped() and Message::text() provide escaped text
 *    - Always prefer Html::element() over Html::rawElement() when possible
 *
 * 2. RAW HTML (USE WITH CAUTION):
 *    - Html::rawElement() does NOT escape content - use only with pre-escaped HTML
 *    - Parameters marked with @param-taint exec_html MUST be pre-escaped
 *    - When using string concatenation, ALWAYS escape with htmlspecialchars()
 *
 * 3. MESSAGE FORMATTING:
 *    - Message::rawParams() passes parameters unescaped (for HTML links)
 *    - Message::params() escapes parameters automatically (for user text)
 *    - Use rawParams() ONLY for trusted HTML from Html::element() or similar
 *
 * 4. TAINT ANNOTATIONS:
 *    - @param-taint escapes_html = parameter will be escaped automatically
 *    - @param-taint exec_html = parameter MUST be pre-escaped HTML
 *    - @return-taint escaped = return value is safe for output
 *
 * See https://www.mediawiki.org/wiki/Phan-taint-check-plugin for details on taint checking.
 *
 * @file
 * @ingroup Extensions
 */
class WikiForumGui {
	/**
	 * Show the search box
	 *
	 * @return string
	 */
	static function showSearchbox() {
		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'search' ] );

		return Html::rawElement(
			'div',
			[ 'class' => 'mw-wikiforum-searchbox' ],
			Html::rawElement(
				'form',
				[ 'method' => 'post', 'action' => $url ],
				(
					Html::rawElement(
						'label',
						[ 'for' => 'mw-wikiforum-searchbox-text' ],
						WikiForum::getIconHTML( 'wikiforum-searchbox', wfMessage( 'search' ) )
					) .
					Html::element(
						'input',
						[
							'type' => 'text',
							'name' => 'query',
							'value' => '',
							'id' => 'mw-wikiforum-searchbox-text',
							'placeholder' => wfMessage( 'search' )->text()
						]
					)
				)
			)
		);
	}

	/**
	 * Builds the header row -- the breadcrumb navigation
	 * (Overview > Category name > Forum > Thread)
	 *
	 * @param string $links the actual overview/category/etc links
	 * @param UserIdentity $user
	 * @param string $additionalLinks more links to add on the other side - 'Add a new forum'-type links
	 * @return string HTML
	 */
	static function showHeaderRow( $links, UserIdentity $user, $additionalLinks = '' ) {
		global $wgWikiForumAllowAnonymous;

		$output = '<table class="mw-wikiforum-headerrow"><tr><td class="mw-wikiforum-leftside">';
		$output .= $links;

		if ( strlen( $additionalLinks ) > 0 && ( $wgWikiForumAllowAnonymous || $user->isRegistered() ) ) {
			$output .= '</td><td class="mw-wikiforum-rightside">' . $additionalLinks;
		}

		$output .= '</td></tr></table>';
		return $output;
	}

	/**
	 * Gets the footer row, in other words: pagination links.
	 *
	 * @param int $page current page number (1-based)
	 * @param int $maxIssues total number of items (threads or replies)
	 * @param int $limit items per page
	 * @param array $params URL params to be passed, should have a thread or forum number
	 * @return string HTML
	 */
	static function showFooterRow( int $page, int $maxIssues, int $limit, array $params ) {
		if ( $limit <= 0 ) {
			return '';
		}

		$page = max( 1, $page );
		$maxIssues = max( 0, $maxIssues );
		$totalPages = (int)ceil( $maxIssues / $limit );

		if ( $totalPages <= 1 ) {
			return '';
		}

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );

		$links = [];

		for ( $i = 1; $i <= $totalPages; $i++ ) {
			$urlParams = array_merge( [ 'page' => $i ], $params );
			$pageNumber = str_pad( (string)$i, 2, '0', STR_PAD_LEFT );

			if ( $i === $page ) {
				$links[] = Html::element( 'span',
					[ 'class' => 'mw-wikiforum-page mw-wikiforum-current-page' ],
					$pageNumber
				);
			} else {
				$links[] = Html::element( 'a',
					[
						'class' => 'mw-wikiforum-page',
						'href' => $specialPage->getFullURL( $urlParams )
					 ],
					$pageNumber
				);
			}
		}

		return Html::rawElement( 'div',
			[ 'class' => 'mw-wikiforum-pagination' ],
			Html::element( 'span',
				[ 'class' => 'wikiforum-pagination-name' ],
				wfMessage( 'wikiforum-pages' )->numParams( $pageNumber )->text()
			) .
			wfMessage( 'word-separator' )->escaped() .
			implode( wfMessage( 'word-separator' )->escaped(), $links )
		);
	}

	/**
	 * Show the header row for Forum and Category pages, <WikiForumList> tag
	 *
	 * @note Caller(s) should escape the $titleN variables!
	 *
	 * @param string $title1
	 * @param string $title2
	 * @param string $title3
	 * @param string $title4
	 * @param string $title5 optional, admin icons if given
	 * @return string HTML
	 */
	public static function showMainHeaderRow( $title1, $title2, $title3, $title4, $title5 = '' ) {
		$output = Html::openElement( 'tr', [ 'class' => 'mw-wikiforum-header-row' ] )
			. Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-title' ], $title1 );

		if ( $title5 ) {
			$output .= Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-admin' ], $title5 );
		}

		$output .= Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-value' ], $title2 )
			. Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-value' ], $title3 )
			. Html::rawElement( 'th', [ 'class' => 'mw-wikiforum-lastpost' ], $title4 )
			. Html::closeElement( 'tr' );
		return $output;
	}

	/**
	 * Show the bottom line of a thread or reply
	 *
	 * @param string $posted
	 * @param User $user
	 * @param string $buttons optional, admin icons if given
	 * @return string HTML
	 */
	static function showBottomLine( $posted, User $user, $buttons = '' ) {
		$output = '<table cellspacing="0" cellpadding="0" class="mw-wikiforum-posted">' .
			'<tr><td class="mw-wikiforum-leftside">' . $posted . '</td>';

		if ( $user->isRegistered() ) {
			$output .= '<td class="mw-wikiforum-rightside">' . $buttons . '</td>';
		}

		$output .= '</tr></table>';

		return $output;
	}

	/**
	 * Get the editor form for writing a new thread, a reply, etc.
	 *
	 * SECURITY: This method uses raw HTML concatenation. The $input parameter must be safe HTML.
	 * All user-provided text should be escaped before passing to this method.
	 *
	 * @param bool $showCancel Show the cancel button?
	 * @param array $params URL parameter(s) to be passed to the form (i.e. array( 'thread' => $threadId ))
	 * @param string $input Pre-escaped HTML for extra input fields (e.g., from Html::rawElement())
	 * @param-taint $input exec_html
	 * @param string $boxClass CSS class to assign to the outer div (will be escaped by Html::textarea())
	 * @param-taint $boxClass escapes_html
	 * @param string $text_prev Previous text content (will be escaped by Html::textarea())
	 * @param-taint $text_prev escapes_html
	 * @param string $saveButton Save button text or message key (will be escaped)
	 * @param-taint $saveButton escapes_html
	 * @param User $user
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showWriteForm( $showCancel, $params, $input, $boxClass, $text_prev, $saveButton, User $user ) {
		global $wgWikiForumAllowAnonymous;

		if ( !( $wgWikiForumAllowAnonymous || $user->isRegistered() ) ) {
			return '';
		}

		$requestContext = RequestContext::getMain();
		$out = $requestContext->getOutput();
		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikiEditor' ) ) {
			if ( MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $user, 'usebetatoolbar' ) ) {
				$out->addModuleStyles( 'ext.wikiEditor.styles' );
				$out->addModules( 'ext.wikiEditor' );
			}
			$toolbar = '';
		} else {
			$toolbar = EditPage::getEditToolbar();
		}

		$captcha = '';
		if ( WikiForum::useCaptcha( $user ) ) {
			$captcha = Html::rawElement( 'div',
				[ 'class' => 'mw-wikiforum-captcha' ],
				WikiForum::getCaptcha( $out )
			);
		}

		$out->addModules( 'mediawiki.action.edit' ); // Required for the edit buttons to display

		$output = Html::openElement( 'form',
				[
					'name' => 'frmMain',
					'method' => 'post',
					'action' => SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( $params ),
					'id' => "writereply",
				]
			) .
			Html::openElement( 'div', [ 'class' => 'mw-wikiforum-edit-reply mw-wikiforum-frame ' . $boxClass ] ) .
			$input .
			$toolbar .
			Html::rawElement( 'div', [],
				Html::element( 'textarea',
					[
						'name' => 'text',
						'id' => 'wpTextbox1',
					],
					$text_prev
				)
			) .
			$captcha .
			Html::rawElement( 'div',
				[ 'class' => 'mw-wikiforum-replybuttons' ],
				Html::element( 'input',
					[
						'type' => 'hidden',
						'name' => 'wpToken',
						'value' => $user->getEditToken(),
					]
				) .
				Html::element( 'button',
					[
						'type' => 'submit',
						'accesskey' => 's',
						'title' => wfMessage( $saveButton )->text() . ' [s]',
					],
					 wfMessage( $saveButton )->text()
				) .
				( $showCancel ? Html::element( 'button',
					[
						'accesskey' => 'c',
						'onclick' => 'javascript:history.back();',
						'title' => wfMessage( 'cancel' )->text() . ' [c]',
					],
					wfMessage( 'cancel' )->text()
				) : '' )
			) .
			Html::closeElement( 'div' ) .
			Html::closeElement( 'form' );

		return $output;
	}

	/**
	 * Show the user and timestamp of when something was first posted. (With link)
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string
	 */
	static function showPostedInfo( $timestamp, User $user ) {
		$userLink = WikiForum::showUserLink( $user );
		return self::showInfo( 'wikiforum-posted', $timestamp, $userLink, $user->getName() );
	}

	/**
	 * Show the user and timestamp of when something was first posted, without any link. (Apparently needed for quoting)
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showPlainPostedInfo( $timestamp, User $user ) {
		// Note: htmlspecialchars() is needed for the first parameter (userLink) because it's passed
		// via rawParams() in showInfo() and will be used as HTML. The second parameter (userText)
		// is passed via params() which automatically escapes it, and is needed unescaped for GENDER.
		return self::showInfo( 'wikiforum-posted', $timestamp, htmlspecialchars( $user->getName() ), $user->getName() );
	}

	/**
	 * Show the user and timestamp of when something was edited
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showEditedInfo( $timestamp, User $user ) {
		$userLink = WikiForum::showUserLink( $user );
		return self::showInfo( 'wikiforum-edited', $timestamp, $userLink, $user->getName() );
	}

	/**
	 * Show the user and timestamp of the last post in a container
	 *
	 * @param string $timestamp
	 * @param User $user
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showByInfo( $timestamp, User $user ) {
		$userLink = WikiForum::showUserLink( $user );
		return self::showInfo( 'wikiforum-by', $timestamp, $userLink, $user->getName() );
	}

	/**
	 * Show an 'info' link, with user and timestamp of an action. Do not use, use show*Info() methods above.
	 *
	 * SECURITY: This method uses rawParams() for $userLink and params() for other parameters.
	 * - $userLink MUST be pre-escaped HTML (e.g., from WikiForum::showUserLink() or htmlspecialchars())
	 * - $userText should be plain text (will be escaped automatically by params())
	 * - Other parameters will be escaped automatically by params()
	 *
	 * @param string $message Message key
	 * @param string $timestamp Timestamp string
	 * @param string $userLink Pre-escaped HTML for user link (e.g., from WikiForum::showUserLink())
	 * @param-taint $userLink exec_html
	 * @param string $userText Plain text username for GENDER support (will be escaped automatically)
	 * @param-taint $userText escapes_html
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	private static function showInfo( $message, $timestamp, $userLink, $userText ) {
		$lang = RequestContext::getMain()->getLanguage();

		return wfMessage( $message, $lang->timeanddate( $timestamp ) )
			->rawParams( $userLink )
			->params(
				$userText,
				$lang->date( $timestamp ),
				$lang->time( $timestamp )
			)
			->parse();
	}
}
