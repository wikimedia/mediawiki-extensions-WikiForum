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
	 * Show the header for thread and search pages
	 *
	 * @return string html
	 */
	static function showFrameHeader() {
		return '<table class="mw-wikiforum-frame" cellspacing="10"><tr><td class="mw-wikiforum-innerframe">';
	}

	/**
	 * Show the footer for thread and search pages
	 *
	 * @return string HTML
	 */
	static function showFrameFooter() {
		return '</td></tr></table>';
	}

	/**
	 * Show the search box
	 *
	 * @return string
	 */
	static function showSearchbox() {
		global $wgExtensionAssetsPath;

		$url = SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( [ 'wfaction' => 'search' ] );

		$icon = '<img src="' . $wgExtensionAssetsPath . '/WikiForum/resources/images/zoom.png" id="mw-wikiforum-searchbox-picture" title="' . wfMessage( 'search' )->escaped() . '" />';

		$output = '<div id="mw-wikiforum-searchbox">' .
			Html::openElement( 'form', [ 'method' => 'post', 'action' => $url ] ) .
			'<div id="mw-wikiforum-searchbox-border">' . $icon .
			'<input type="text" value="" name="query" id="txtSearch" /></div>' .
			Html::closeElement( 'form' ) .
			'</div>';

		return $output;
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
	static function showFooterRow( $page, $maxIssues, $limit, $params ) {
		if ( $limit <= 0 ) {
			return '';
		}

		$page = max( 1, (int)$page );
		$maxIssues = max( 0, (int)$maxIssues );
		$totalPages = (int)ceil( $maxIssues / $limit );

		if ( $totalPages <= 1 ) {
			return '';
		}

		$specialPage = SpecialPage::getTitleFor( 'WikiForum' );
		$output = '<table class="mw-wikiforum-footerrow"><tr>';
		$output .= '<td class="mw-wikiforum-leftside">';
		$output .= wfMessage( 'wikiforum-pages' )->escaped();

		for ( $i = 1; $i <= $totalPages; $i++ ) {
			$urlParams = array_merge( [ 'page' => $i ], $params );
			$pageNumber = str_pad( (string)$i, 2, '0', STR_PAD_LEFT );

			if ( $i !== $page ) {
				$output .= Html::element(
					'a',
					[ 'href' => $specialPage->getFullURL( $urlParams ) ],
					$pageNumber
				);
			} else {
				$output .= Html::element( 'span', [], '[' . $pageNumber . ']' );
			}

			$output .= wfMessage( 'word-separator' )->escaped();
		}

		$output .= '</td>';
		$output .= '<td class="mw-wikiforum-rightside"></td>';
		$output .= '</tr></table>';

		return $output;
	}

	/**
	 * Show the header for Forum and Category pages
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
	static function showMainHeader( $title1, $title2, $title3, $title4, $title5 = '' ) {
		return self::showFrameHeader() . '<table class="mw-wikiforum-title">' .
			self::showMainHeaderRow( $title1, $title2, $title3, $title4, $title5 );
	}

	/**
	 * Show the header for the <WikiForumList> tag
	 *
	 * @note Caller(s) should escape the $titleN variables!
	 *
	 * @param string $title1
	 * @param string $title2
	 * @param string $title3
	 * @param string $title4
	 * @return string HTML
	 */
	static function showListTagHeader( $title1, $title2, $title3, $title4 ) {
		return '<table class="mw-wikiforum-mainpage" cellspacing="0">' .
			self::showMainHeaderRow( $title1, $title2, $title3, $title4 );
	}

	/**
	 * Show the header row. Only called from other GUI methods.
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
		$output = '<tr class="mw-wikiforum-title"><th class="mw-wikiforum-title">' . $title1 . '</th>';

		if ( $title5 ) {
			$output .= '<th class="mw-wikiforum-admin"><p class="mw-wikiforum-valuetitle">' . $title5 . '</p></th>';
		}
		$output .= '<th class="mw-wikiforum-value"><p class="mw-wikiforum-valuetitle">' . $title2 . '</p></th>
			<th class="mw-wikiforum-value"><p class="mw-wikiforum-valuetitle">' . $title3 . '</p></th>
			<th class="mw-wikiforum-lastpost"><p class="mw-wikiforum-valuetitle">' . $title4 . '</p></th></tr>';

		return $output;
	}

	/**
	 * Show the footer for Forum and Category pages
	 *
	 * @return string HTML
	 */
	static function showMainFooter() {
		return '</table>' . self::showFrameFooter();
	}

	/**
	 * Show the footer for the <WikiForumList> tag
	 *
	 * @return string HTML
	 */
	static function showListTagFooter() {
		return '</table>';
	}

	/**
	 * Only for search results: show the header row
	 *
	 * @param string $title
	 * @return string
	 */
	static function showSearchHeader( $title ) {
		return self::showFrameHeader() . '
			<table style="width:100%">
				<tr>
					<th class="mw-wikiforum-thread-top" colspan="2">' .
			$title .
			'</th>
				</tr>';
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
	 * @param string $height Height of the textarea, i.e. '10em' (will be escaped by Html::textarea())
	 * @param-taint $height escapes_html
	 * @param string $text_prev Previous text content (will be escaped by Html::textarea())
	 * @param-taint $text_prev escapes_html
	 * @param string $saveButton Save button text or message key (will be escaped)
	 * @param-taint $saveButton escapes_html
	 * @param User $user
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showWriteForm( $showCancel, $params, $input, $height, $text_prev, $saveButton, User $user ) {
		global $wgWikiForumAllowAnonymous;

		$output = '';

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

		if ( $wgWikiForumAllowAnonymous || $user->isRegistered() ) {
			$out->addModules( 'mediawiki.action.edit' ); // Required for the edit buttons to display

			$output = Html::openElement( 'form', [
				'name' => 'frmMain',
				'method' => 'post',
				'action' => SpecialPage::getTitleFor( 'WikiForum' )->getFullURL( $params ),
				'id' => 'writereply'
			] ) . '
			<table class="mw-wikiforum-frame" cellspacing="10">' . $input . '
				<tr>
					<td>' . $toolbar . '</td>
				</tr>
				<tr>
					<td>' . Html::textarea( 'text', $text_prev, [
						'id' => 'wpTextbox1',
						'style' => 'height: ' . $height
					] ) . '</td>
				</tr>';
			if ( WikiForum::useCaptcha( $user ) ) {
				$output .= '<tr><td>' . WikiForum::getCaptcha( $out ) . '</td></tr>';
			}
			// Translate message key (all keys should be in i18n/en.json)
			$saveButtonEscaped = wfMessage( $saveButton )->escaped();
			$output .= '<tr>
					<td>
						<input type="hidden" name="wpToken" value="' . $user->getEditToken() . '" />
						<input type="submit" value="' . $saveButtonEscaped . '" accesskey="s" title="' . $saveButtonEscaped . ' [s]" />';
			if ( $showCancel ) {
				$output .= ' <input type="button" value="' . wfMessage( 'cancel' )->escaped() . '" accesskey="c" onclick="javascript:history.back();" title="' . wfMessage( 'cancel' )->escaped() . ' [c]" />';
			}
			$output .= '</td>
					</tr>
				</table>' . "\n" .
			Html::closeElement( 'form' ) . "\n";
		}
		return $output;
	}

	/**
	 * Get the main form for forums and categories
	 *
	 * SECURITY: This method uses Html helpers for escaping.
	 * All parameters except $extraRow are automatically escaped.
	 * The $extraRow should be pre-escaped HTML (e.g., from Html::rawElement()).
	 *
	 * @param string $url URL to send form to, with GET params (will be escaped by Html::openElement)
	 * @param-taint $url escapes_html
	 * @param string $extraRow Pre-escaped HTML row to add after title input (empty string for categories)
	 * @param-taint $extraRow exec_html
	 * @param string $formTitle Title for the form (will be escaped by Html::element)
	 * @param-taint $formTitle escapes_html
	 * @param string $titlePlaceholder Placeholder value for the title input (will be escaped by Html::input)
	 * @param-taint $titlePlaceholder escapes_html
	 * @param string $titleValue Value for the title input (will be escaped by Html::input)
	 * @param-taint $titleValue escapes_html
	 * @return string HTML content (safe for output)
	 * @return-taint escaped
	 */
	static function showTopLevelForm( $url, $extraRow, $formTitle, $titlePlaceholder, $titleValue ) {
		$output = Html::openElement( 'form', [
			'name' => 'frmMain',
			'method' => 'post',
			'action' => $url,
			'id' => 'form'
		] ) . "\n";

		$output .= '<table class="mw-wikiforum-frame" cellspacing="10">' . "\n";

		// Title row
		$output .= '<tr>' . "\n";
		$output .= Html::element( 'th', [ 'class' => 'mw-wikiforum-title' ], $formTitle ) . "\n";
		$output .= '</tr>' . "\n";

		// Name input row
		$output .= '<tr><td>' . "\n";
		$output .= Html::element( 'p', [], wfMessage( 'wikiforum-name' )->text() ) . "\n";
		$output .= Html::input( 'name', $titleValue, 'text', [
			'style' => 'width: 100%',
			'placeholder' => $titlePlaceholder
		] ) . "\n";
		$output .= '</td></tr>' . "\n";

		// Extra row (pre-escaped HTML)
		$output .= $extraRow;

		// Buttons row
		$output .= '<tr><td>' . "\n";
		$output .= Html::hidden( 'wpToken', RequestContext::getMain()->getUser()->getEditToken() ) . "\n";
		$output .= Html::submitButton(
			wfMessage( 'wikiforum-save' )->text(),
			[
				'accesskey' => 's',
				'title' => wfMessage( 'wikiforum-save' )->text() . ' [s]'
			]
		) . "\n";
		$output .= Html::rawElement( 'input', [
			'type' => 'button',
			'value' => wfMessage( 'cancel' )->text(),
			'accesskey' => 'c',
			'onclick' => 'javascript:history.back();',
			'title' => wfMessage( 'cancel' )->text() . ' [c]'
		] ) . "\n";
		$output .= '</td></tr>' . "\n";

		$output .= '</table>' . "\n";
		$output .= Html::closeElement( 'form' );

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
